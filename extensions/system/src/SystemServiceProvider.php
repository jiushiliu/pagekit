<?php

namespace Pagekit;

use Pagekit\Extension\ExtensionManager;
use Pagekit\Extension\Package\ExtensionLoader;
use Pagekit\Extension\Package\ExtensionRepository;
use Pagekit\Filesystem\Adapter\FileAdapter;
use Pagekit\Filesystem\Adapter\StreamAdapter;
use Pagekit\Filesystem\FileLocator;
use Pagekit\Package\Installer\PackageInstaller;
use Pagekit\System\Package\Event\LoadFailureEvent;
use Pagekit\System\Package\Exception\ExtensionLoadException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Finder\Finder;

class SystemServiceProvider implements ServiceProviderInterface, EventSubscriberInterface
{
    protected $app;

    public function register(Application $app)
    {
        $this->app = $app;

        $app['locator'] = function($app) {
            return new FileLocator($app['path']);
        };

        $app['finder'] = $app->factory(function() {
            return Finder::create();
        });

        // -TODO-

        // $app->extend('migrator', function($migrator, $app) {
        //     $migrator->setLoader(new FilesystemLoader($app['locator']));
        //     return $migrator;
        // });

        $app->extend('view', function($view, $app) {

            $view->setEngine($app['tmpl']);
            $view->set('app', $app);
            $view->set('url', $app['url']);

            return $view;
        });

        $app['extensions'] = function($app) {

            $loader     = new ExtensionLoader;
            $repository = new ExtensionRepository($app['config']['extension.path'], $loader);
            $file       = isset($app['file']) ? $app['file'] : null;

            $installer  = new PackageInstaller($repository, $loader, $file);

            return new ExtensionManager($repository, $installer);
        };

        $app['config']['app.storage'] = ltrim(($app['config']['app.storage'] ?: 'storage'), '/');
        $app['path.storage'] = $app['config']['locator.paths.storage'] = rtrim($app['path'] . '/' . $app['config']['app.storage'], '/');

        $app['extensions.boot'] = [];
    }

    public function boot(Application $app)
    {
        foreach (array_unique($app['extensions.boot']) as $extension) {
            try {
                $app['extensions']->load($extension)->boot($app);
            } catch (ExtensionLoadException $e) {
                $app['events']->dispatch('extension.load_failure', new LoadFailureEvent($extension));
            }
        }

        if ($app->runningInConsole()) {

            $app['isAdmin'] = false;

            $app['events']->dispatch('system.init');
            $app['events']->addListener('console.init', function($event) {

                $console = $event->getConsole();
                $namespace = 'Pagekit\\System\\Console\\';

                foreach (glob(__DIR__.'/System/Console/*Command.php') as $file) {
                    $class = $namespace.basename($file, '.php');
                    $console->add(new $class);
                }

            });
        }

        $app['events']->addSubscriber($this);
    }

    public function onKernelRequest($event, $name, $dispatcher)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $this->app['file']->registerAdapter('file', new FileAdapter($this->app['path'], $event->getRequest()->getBaseUrl()));
        $this->app['file']->registerAdapter('app', new StreamAdapter($this->app['path'], $event->getRequest()->getBaseUrl()));

        $this->app['view.sections']->register('head', ['renderer' => 'delayed']);
        $this->app['view.sections']->prepend('head', function() {
            return sprintf('        <meta name="generator" content="Pagekit %1$s" data-version="%1$s" data-url="%2$s" data-csrf="%3$s">', $this->app['config']['app.version'], $this->app['router']->getContext()->getBaseUrl(), $this->app['csrf']->generate());
        });

        $this->app['isAdmin'] = (bool) preg_match('#^/admin(/?$|/.+)#', $event->getRequest()->getPathInfo());

        $dispatcher->dispatch('system.init', $event);
    }

    public function onRequestMatched($event, $name, $dispatcher)
    {
        if (!$event->isMasterRequest()) {
            return;
        }

        $dispatcher->dispatch('system.loaded', $event);
    }

    public function onTemplateReference($event)
    {
        $template = $event->getTemplateReference();

        if ($path = $this->app['locator']->get($template->get('path'))) {
            $template->set('name', $path); // php engine uses name
            $template->set('path', $path);
        }
    }

    public function onKernelResponse()
    {
        $require = [];
        $requeue = [];

        foreach ($scripts = $this->app['view.scripts'] as $script) {
            if ($script['requirejs']) {
                $require[] = $script;
            } elseif (array_key_exists('requirejs', $scripts->resolveDependencies($script))) {
                $requeue[] = $script;
            }
        }

        if (!$requeue) {
            return;
        }

        foreach ($require as $script) {
            $script['dependencies'] = array_merge((array) $script['dependencies'], ['requirejs']);
            $scripts->queue($script->getName());
        }

        foreach ($requeue as $script) {
            $scripts->dequeue($name = $script->getName());
            $scripts->queue($name);
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            'kernel.request' => [
                ['onKernelRequest', 50],
                ['onRequestMatched', 0]
            ],
            'templating.reference' => 'onTemplateReference',
            'kernel.response'      => ['onKernelResponse', 15]
        ];
    }
}
