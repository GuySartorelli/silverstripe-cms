<% if $currentPageID %>

    <div id="pages-controller-cms-content" class="has-panel cms-content flexbox-area-grow fill-width fill-height $BaseCSSClasses" data-layout-type="border" data-pjax-fragment="Content" data-ignore-tab-state="true">
        $Tools

        <div class="fill-height flexbox-area-grow">
            <div class="cms-content-header north">
                <div class="cms-content-header-info flexbox-area-grow vertical-align-items">
                    <a href="$BreadcrumbsBackLink" class="btn btn-secondary btn--no-text font-icon-left-open-big hidden-lg-up toolbar__back-button"></a>
                    <% include SilverStripe\\Admin\\CMSBreadcrumbs %>
                </div>

                <div class="cms-content-header-tabs cms-tabset">
                    <ul class="cms-tabset-nav-primary nav nav-tabs">
                        <li class="nav-item content-treeview<% if $TabIdentifier == 'edit' %> ui-tabs-active<% end_if %>">
                            <a href="$LinkPageEdit" class="nav-link cms-panel-link" title="Form_EditForm" data-href="$LinkPageEdit">
                                <%t SilverStripe\\CMS\\Controllers\\CMSMain.TabContent 'Content' %>
                            </a>
                        </li>
                        <li class="nav-item content-listview<% if $TabIdentifier == 'settings' %> ui-tabs-active<% end_if %>">
                            <a href="$LinkPageSettings" class="nav-link cms-panel-link" title="Form_EditForm" data-href="$LinkPageSettings">
                                <%t SilverStripe\\CMS\\Controllers\\CMSMain.TabSettings 'Settings' %>
                            </a>
                        </li>
                        <li class="nav-item content-listview<% if $TabIdentifier == 'history' %> ui-tabs-active<% end_if %>">
                            <a href="$LinkPageHistory" class="nav-link cms-panel-link" title="Form_EditForm" data-href="$LinkPageHistory">
                                <%t SilverStripe\\CMS\\Controllers\\CMSMain.TabHistory 'History' %>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="flexbox-area-grow fill-height">
                $EditForm
            </div>
        </div>
    </div>

<% else %>

    <div id="pages-controller-cms-content" class="flexbox-area-grow fill-height cms-content $BaseCSSClasses" data-layout-type="border" data-pjax-fragment="Content">
        <div class="cms-content-header north vertical-align-items">
            <div class="cms-content-header-info fill-width vertical-align-items">
                <% if $TreeIsFiltered %>
                    <a class="btn btn-secondary font-icon-left-open-big toolbar__back-button btn--no-text" href="$BreadcrumbsBacklink">
                        <span class="sr-only"><%t SilverStripe\Admin\LeftAndMain.NavigateUp "Return to Pages" %></span>
                    </a>
                <% end_if %>
                <% include SilverStripe\\Admin\\CMSBreadcrumbs %>
                <% include SilverStripe\\CMS\\Controllers\\CMSMain_Filter %>
            </div>
        </div>

        <div class="flexbox-area-grow fill-height cms-content-fields ui-widget-content cms-panel-padded">
            $Tools
            $PageList
        </div>
    </div>

<% end_if %>
