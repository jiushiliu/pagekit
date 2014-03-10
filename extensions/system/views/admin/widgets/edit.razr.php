<form class="uk-form uk-form-horizontal" action="@url('@system/widgets/save', ['id' => widget.id ?: 0])" method="post">

    @set(type = app.widgets.type(widget.type))

    <ul class="uk-tab" data-uk-tab="{ connect: '#tab-content' }">
        <li class="uk-active"><a href="#">@trans('Settings')</a></li>
        <li class=""><a href="#">@trans('Assignment')</a></li>
    </ul>

    <ul id="tab-content" class="uk-switcher uk-margin">
        <li>

            <div class="uk-form-row">
                <label for="form-title" class="uk-form-label">@trans('Title')</label>
                <div class="uk-form-controls">
                    <p class="uk-form-controls-condensed">
                        <input id="form-title" class="uk-form-width-large" type="text" name="widget[title]" value="@widget.title" required>
                    </p>
                     <p class="uk-form-controls-condensed">
                        <label><input type="checkbox" name="widget[settings][show_title]" value="1"@(widget.showTitle ? ' checked')> @trans('Display the title')</label>
                    </p>
                </div>
            </div>
            <div class="uk-form-row">
                <span class="uk-form-label">@trans('Status')</span>
                <div class="uk-form-controls uk-form-controls-text">
                    <p class="uk-form-controls-condensed">
                        <label><input type="radio" name="widget[status]" value="1"@(widget.status ? ' checked')> @trans('Enabled')</label>
                    </p>
                    <p class="uk-form-controls-condensed">
                        <label><input type="radio" name="widget[status]" value="0"@(!widget.status ? ' checked')> @trans('Disabled')</label>
                    </p>
                </div>
            </div>
            <div class="uk-form-row">
                <label for="form-position" class="uk-form-label">@trans('Position')</label>
                <div class="uk-form-controls">
                    <select id="form-position" class="uk-form-width-large" name="widget[position]">
                        @foreach (positions as position)
                        <option value="@position.id"@(widget.position == position.id ? ' selected')>@trans(position.name)</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="uk-form-row">
                <label for="form-access" class="uk-form-label">@trans('Access')</label>
                <div class="uk-form-controls">
                    <select id="form-access" class="uk-form-width-large" name="widget[accessId]">
                        @foreach (levels as level)
                        <option value="@level.id"@(widget.accessId == level.id ? ' selected')>@level.name</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <h2 class="pk-form-heading">@trans('Advanced')</h2>
            @type.renderForm(widget)
        </li>
        <li>

            <div class="uk-form-row">
                <label for="form-h-it" class="uk-form-label">@trans('Menu Items')</label>
                <div class="uk-form-controls">
                    <div class="uk-scrollable-box uk-form-width-large">
                        @foreach (app.menus as id => menu)
                        @set (root = app.menus.getTree(menu.id))
                        @if (id != 'admin' && root.hasChildren())
                        @if (prev)<hr>@endif@set (prev = 1)
                        <h3 class="uk-h4 uk-margin-top-remove">@menu.name</h3>
                        @include('view://system/admin/widgets/select.razr.php', ['root' => root, 'widget' => widget])
                        @endif
                        @endforeach
                        <input type="hidden" name="widget[menuItems][]" value="">
                    </div>
                </div>
            </div>
            <div class="uk-form-row">
                <label for="form-position" class="uk-form-label">@trans('Pattern')</label>
                <div class="uk-form-controls">
                    <textarea class="uk-form-width-large" name="widget[pages]" rows="5">@widget.pages</textarea>
                    <p class="uk-form-help-block">@trans('Enter one page path per line. The "*" character is a wildcard. Exclude pages by prepending an exclamation mark to the path. Example paths: "blog" for the blog page, "blog/*" for blog entries or "!blog" if you want to exclude the blog.')</p>
                </div>
            </div>

        </li>
    </ul>

    <input type="hidden" name="widget[type]" value="@widget.type">

    <p>
        <button class="uk-button uk-button-primary" type="submit">@trans('Save')</button>
        <a class="uk-button" href="@url('@system/widgets/index')">@(widget.id ? trans('Close') : trans('Cancel'))</a>
    </p>

</form>