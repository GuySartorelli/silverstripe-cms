<?php

namespace SilverStripe\CMS\Controllers;

use Psr\SimpleCache\CacheInterface;
use SilverStripe\Admin\ModelTreeAdmin;
use SilverStripe\CMS\Model\RedirectorPage;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldGroup;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;

/**
 * The main "content" area of the CMS.
 */
class CMSMain extends ModelTreeAdmin
{

    private static $url_segment = 'pages';

    // Maintain a lower priority than other administration sections
    // so that Director does not think they are actions of CMSMain
    private static $url_priority = 39;

    private static $menu_title = 'Pages';

    private static $menu_icon_class = 'font-icon-sitemap';

    private static $menu_priority = 10;

    private static $tree_class = SiteTree::class;

    private static $session_namespace = self::class;

    private static $required_permission_codes = 'CMS_ACCESS_CMSMain';

    private static $dependencies = [
        'HintsCache' => '%$' . CacheInterface::class . '.CMSMain_SiteTreeHints',
    ];

    public function LinkPreview()
    {
        $record = $this->getRecord($this->currentPageID());
        // if we are an external redirector don't show a link
        if ($record && ($record instanceof RedirectorPage) && $record->RedirectionType == 'External') {
            return false;
        }
        return parent::LinkPreview();
    }

    public function providePermissions()
    {
        $title = self::menu_title();
        return [
            "CMS_ACCESS_CMSMain" => [
                'name' => _t(__CLASS__ . '.ACCESS', "Access to '{title}' section", ['title' => $title]),
                'category' => _t(Permission::class . '.CMS_ACCESS_CATEGORY', 'CMS Access'),
                'help' => _t(
                    __CLASS__ . '.ACCESS_HELP',
                    'Allow viewing of the section containing page tree and content. View and edit permissions can be handled through page specific dropdowns, as well as the separate "Content permissions".'
                ),
                'sort' => -99 // below "CMS_ACCESS_LeftAndMain", but above everything else
            ]
        ];
    }

    public function getSearchForm()
    {
        // Create the fields
        $dateFrom = DateField::create(
            'Search__LastEditedFrom',
            _t('SilverStripe\\CMS\\Search\\SearchForm.FILTERDATEFROM', 'From')
        )->setLocale(Security::getCurrentUser()->Locale);
        $dateTo = DateField::create(
            'Search__LastEditedTo',
            _t('SilverStripe\\CMS\\Search\\SearchForm.FILTERDATETO', 'To')
        )->setLocale(Security::getCurrentUser()->Locale);
        $filters = CMSSiteTreeFilter::get_all_filters();
        // Remove 'All pages' as we set that to empty/default value
        unset($filters[CMSSiteTreeFilter_Search::class]);
        $pageFilter = DropdownField::create(
            'Search__FilterClass',
            _t('SilverStripe\\CMS\\Controllers\\CMSMain.PAGES', 'Page status'),
            $filters
        );
        $pageFilter->setEmptyString(_t('SilverStripe\\CMS\\Controllers\\CMSMain.PAGESALLOPT', 'All pages'));
        $pageClasses = DropdownField::create(
            'Search__ClassName',
            _t('SilverStripe\\CMS\\Controllers\\CMSMain.PAGETYPEOPT', 'Page type', 'Dropdown for limiting search to a page type'),
            $this->getPageTypes()
        );
        $pageClasses->setEmptyString(_t('SilverStripe\\CMS\\Controllers\\CMSMain.PAGETYPEANYOPT', 'Any'));

        // Group the Datefields
        $dateGroup = FieldGroup::create(
            _t('SilverStripe\\CMS\\Search\\SearchForm.PAGEFILTERDATEHEADING', 'Last edited'),
            [$dateFrom, $dateTo]
        )->setName('Search__LastEdited')
        ->addExtraClass('fieldgroup--fill-width');

        // Create the Field list
        $fields = FieldList::create(
            $pageFilter,
            $pageClasses,
            $dateGroup
        );

        // Create the form
        /** @skipUpgrade */
        $form = Form::create(
            $this,
            'SearchForm',
            $fields,
            FieldList::create()
        );
        $form->addExtraClass('cms-search-form');
        $form->setFormMethod('GET');
        $form->setFormAction(static::singleton()->Link());
        $form->disableSecurityToken();
        $form->unsetValidator();

        // Load the form with previously sent search data
        $form->loadDataFrom($this->getRequest()->getVars());

        // Allow decorators to modify the form
        $this->extend('updateSearchForm', $form);

        return $form;
    }
}
