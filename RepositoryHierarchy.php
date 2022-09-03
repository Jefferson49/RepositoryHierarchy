<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2022 webtrees development team
 *					  <http://webtrees.net>
 *
 * Fancy Research Links (webtrees custom module):  
 * Copyright (C) 2022 Carmen Just
 *					  <https://justcarmen.nl>
 *
 * RepositoryHierarchy (webtrees custom module):  
 * Copyright (C) 2022 Markus Hemprich
 *                    <http://www.familienforschung-hemprich.de>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */
 
declare(strict_types=1);

namespace Jefferson49\Webtrees\Module\RepositoryHierarchyNamespace;

use Cissee\WebtreesExt\MoreI18N;
use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Localization\Translation;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleConfigInterface;
use Fisharebest\Webtrees\Module\ModuleConfigTrait;
use Fisharebest\Webtrees\Module\ModuleCustomInterface;
use Fisharebest\Webtrees\Module\ModuleCustomTrait;
use Fisharebest\Webtrees\Module\ModuleDataFixInterface;
use Fisharebest\Webtrees\Module\ModuleDataFixTrait;
use Fisharebest\Webtrees\Module\ModuleGlobalInterface;
use Fisharebest\Webtrees\Module\ModuleGlobalTrait;
use Fisharebest\Webtrees\Module\ModuleListInterface;
use Fisharebest\Webtrees\Module\ModuleListTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Repository;
use Fisharebest\Webtrees\Services\DataFixService;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\View;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function route;
	
class RepositoryHierarchy   extends     AbstractModule 
                            implements  ModuleConfigInterface,
                                        ModuleCustomInterface,
                                        ModuleDataFixInterface,
                                        ModuleGlobalInterface, 
                                        ModuleListInterface, 
                                        RequestHandlerInterface
{
    use ModuleConfigTrait;
    use ModuleCustomTrait;
    use ModuleListTrait;
    use ModuleGlobalTrait;
    use ModuleDataFixTrait;

    //Module name
    public const MODULE_NAME = '_repository_hierarchy_';

    //Routes, attributes
    protected const MODULE_NAME_IN_ROUTE = 'repositoryhierarchy';
    protected const HELP_TEXTS_IN_ROUTE = 'repositoryhierarchy_helptexts';
    protected const CREATE_SOURCE_IN_ROUTE = 'repositoryhierarchy_create_source';
    protected const FIX_CALL_NUMBER_IN_ROUTE = 'repositoryhierarchy_fix_callnumbers';
    protected const REPO_ACTIONS_IN_ROUTE = 'repositoryhierarchy_repo_actions';
    protected const XML_SETTINGS_MODAL_IN_ROUTE = 'repositoryhierarchy_xml_settings_modal';
    protected const XML_SETTINGS_ACTION_IN_ROUTE = 'repositoryhierarchy_xml_settings_action';
    protected const TREE_ATTRIBUTE_DEFAULT = '{tree}';
    protected const XREF_ATTRIBUTE_DEFAULT = '{xref}';
    protected const DELIMITER_ATTRIBUTE_DEFAULT = '{delimiter_expression}';
    protected const COMMAND_ATTRIBUTE_DEFAULT = '{command}';
    protected const TOPIC_ATTRIBUTE_DEFAULT = '{topic}';
    protected const SOURCE_CALL_NUMBER_ATTRIBUTE_DEFAULT = '{source_call_number}';
    protected const CATEGORY_NAME_ATTRIBUTE_DEFAULT = '{category_name}';
    protected const CATEGORY_FULL_NAME_ATTRIBUTE_DEFAULT = '{category_full_name}';

    //Strings cooresponding to variable names
    public const VAR_DATA_FIX = 'data_fix';
    public const VAR_DATA_FIXES = 'data_fixes';
    public const VAR_DATA_FIX_TITLE = 'title';
    public const VAR_DATA_FIX_TYPES = 'types';
    public const VAR_DATA_FIX_CATEGORY_NAME_REPLACE = 'category_name_replace';
    public const VAR_DATA_FIX_PENDING_URL = 'pending_url';     
    
    //The separator for delimiter expressions and its substitue
    public const DELIMITER_SEPARATOR = ';';
    public const DELIMITER_ESCAPE = '{delimiter_escape}';

    //All the characters, which need to be escaped because of regular expressions
    public const ESCAPE_CHARACTERS = '$ ( ) * + . ? [ ] \ | ';

    //Prefences, Settings
    public const PREF_DELIMITER = 'DELIM_';
    public const PREF_REPOSITORY = 'REPO_';
    public const PREF_SHOW_HELP_ICON = 'show_help_icon';
    public const PREF_SHOW_HELP_LINK = 'show_help_link';
    public const PREF_SHOW_CATEGORY_LABEL = 'show_category_label';
    public const PREF_SHOW_TRUNCATED_CALL_NUMBER = 'show_truncated_call_number';
    public const PREF_SHOW_TRUNCATED_CATEGORY = 'show_truncated_category';
    public const PREF_SHOW_TITLE = 'show_title';
    public const PREF_SHOW_XREF = 'show_xref';
    public const PREF_SHOW_AUTHOR = 'show_author';
    public const PREF_SHOW_DATE_RANGE = 'show_date_range';
    public const PREF_SHOW_CATEGORY_EXPANDED = 'show_category_expanded';
    public const PREF_ALLOW_ADMIN_DELIMITER = 'allow_admin_delimiter';
    public const PREF_MODULE_VERSION = 'module_version';
    public const PREF_START_REPOSITORY = 'start_repository';
    public const PREF_VIRTUAL_REPOSITORY = 'virtual_repository';
    public const PREF_SHOW_SOURCE_FACTS_IN_CITATIONS = 'show_source_facts_in_citations';
    public const PREF_SHOW_DATE_RANGE_FOR_CATEGORY ='show_date_range_for-category';
    public const PREF_SHOW_ATOM_LINKS ='show_atom_links';
    public const PREF_ATOM_SLUG ='atom_slug';
    public const PREF_ATOM_SLUG_TITLE ='title';
    public const PREF_ATOM_SLUG_CALL_NUMBER ='call_number';
    public const PREF_WEBTREES_BASE_URL = 'webtrees_base_url';
    public const PREF_ATOM_BASE_URL = 'atom_base_url';
    public const PREF_ATOM_REPOSITORIES = 'atom_repositories';
    public const PREF_XML_VERSION = 'xml_version_';
    public const PREF_FINDING_AID_TITLE = 'finding_aid_title_';
    public const PREF_COUNTRY_CODE = 'country_code_';
    public const PREF_MAIN_AGENCY_CODE = 'main_agency_code_';
    public const PREF_FINDING_AID_IDENTIFIER = 'finding_aid_id_';
    public const PREF_FINDING_AID_URL = 'finding_aid_url_';
    public const PREF_FINDING_AID_PUBLISHER = 'finding_aid_publ_';
    public const PREF_ALLOW_ADMIN_XML_SETTINGS = 'allow_admin_xml_settings';

    //String for admin for use in preferences names
    public const ADMIN_USER_STRING = 'admin';    

    //Commands to load and save delimiters
    public const CMD_NONE = 'none';
    public const CMD_LOAD_ADMIN_DELIM = 'load_delimiter_from_admin';    
    public const CMD_LOAD_DELIM = 'load_delimiter';
    public const CMD_SAVE_DELIM = 'save_delimiter';    
    public const CMD_SAVE_REPO = 'save_repository';
    public const CMD_LOAD_REPO = 'load_repository';
    public const CMD_DOWNLOAD_XML = 'download_xml';
    public const CMD_LOAD_ADMIN_XML_SETTINGS = 'load_admin_xml_settings';

    //Comands for repositories
    public const CMD_SET_AS_START_REPO = 'set as start repository';

    //Custom module version
    public const CUSTOM_VERSION = '1.1.0';

    //Github repository
    public const GITHUB_REPO = 'Jefferson49/RepositoryHierarchy';

    //Author of custom module
    public const CUSTOM_AUTHOR = 'Markus Hemprich';

    //Website of author
    public const AUTHOR_WEBSITE = 'http://www.familienforschung-hemprich.de';

    //The tree, to which the repository hierarchy relates
    private Tree $tree;

    //The xref string of the repository, to which the repository hierarchy relates
    private string $repository_xref;

    //The repository, to which the repository hierarchy relates
    private Repository $repository;

    //Root element of category hierarchy
	private CallNumberCategory $root_category;

    //The data fix service
    private DataFixService $data_fix_service;

    //The full name of the call number category to be fixed
    private string $data_fix_category_full_name = '';

    //The name of the call number category to be fixed
    private string $data_fix_category_name = '';


    /**
     * Constructor
     */
    public function __construct()
    {
        //Create data fix service
        $this->data_fix_service = new DataFixService;
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\AbstractModule::boot()
     */
    public function boot(): void
    {
        $router = Registry::routeFactory()->routeMap();

        //Register a route for the class  
        $router ->get(self::class,   
                    '/tree/'.self::TREE_ATTRIBUTE_DEFAULT.
                    '/'.self::MODULE_NAME_IN_ROUTE.
                    '/xref/'.self::XREF_ATTRIBUTE_DEFAULT.
                    '/delimiter_expression/'.self::DELIMITER_ATTRIBUTE_DEFAULT.
                    '/command/'.self::COMMAND_ATTRIBUTE_DEFAULT
                , $this)
                ->allows(RequestMethodInterface::METHOD_POST);
        
        //Register a route for the help texts    
        $router->get(RepositoryHierarchyHelpTexts::class,     
                    '/'.self::HELP_TEXTS_IN_ROUTE.
                    '/topic/'.self::TOPIC_ATTRIBUTE_DEFAULT
                    )             
                ->allows(RequestMethodInterface::METHOD_POST);    

        //Register a route for the create source modal    
        $router ->get(CreateSourceModal::class,   
                    '/tree/'.self::TREE_ATTRIBUTE_DEFAULT.
                    '/'.self::CREATE_SOURCE_IN_ROUTE.
                    '/xref/'.self::XREF_ATTRIBUTE_DEFAULT.
                    '/source_call_number/'.self::SOURCE_CALL_NUMBER_ATTRIBUTE_DEFAULT
                    )
                ->allows(RequestMethodInterface::METHOD_POST);

        //Register a route for the call number fix action
        $router ->get(CallNumberDataFix::class,   
                    '/tree/'.self::TREE_ATTRIBUTE_DEFAULT.
                    '/'.self::FIX_CALL_NUMBER_IN_ROUTE.
                    '/xref/'.self::XREF_ATTRIBUTE_DEFAULT.
                    '/category_name/'.self::CATEGORY_NAME_ATTRIBUTE_DEFAULT.
                    '/category_full_name/'.self::CATEGORY_FULL_NAME_ATTRIBUTE_DEFAULT
                    )
                ->allows(RequestMethodInterface::METHOD_POST);

        //Register a route for the XML export settings modal
        $router ->get(XmlExportSettingsModal::class,   
                    '/tree/'.self::TREE_ATTRIBUTE_DEFAULT.
                    '/'.self::XML_SETTINGS_MODAL_IN_ROUTE.
                    '/xref/'.self::XREF_ATTRIBUTE_DEFAULT.
                    '/command/'.self::COMMAND_ATTRIBUTE_DEFAULT
                    )
                ->allows(RequestMethodInterface::METHOD_POST);

        //Register a route for the XML export settings ation
        $router ->get(XmlExportSettingsAction::class,   
                    '/tree/'.self::TREE_ATTRIBUTE_DEFAULT.
                    '/'.self::XML_SETTINGS_ACTION_IN_ROUTE.
                    '/xref/'.self::XREF_ATTRIBUTE_DEFAULT.
                    '/command/'.self::COMMAND_ATTRIBUTE_DEFAULT
                    )
                ->allows(RequestMethodInterface::METHOD_POST);

        //Register a namespace for the views
		View::registerNamespace($this->name(), $this->resourcesFolder() . 'views/');

        //Register a custom view for facts in order to show additional source facts in citations
        if( (boolval($this->getPreference(self::PREF_SHOW_SOURCE_FACTS_IN_CITATIONS, '0'))) OR
            (boolval($this->getPreference(self::PREF_SHOW_ATOM_LINKS, '0'))))  {

            View::registerCustomView('::fact-gedcom-fields', $this->name() . '::fact-gedcom-fields');
        }
	}
	
    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\AbstractModule::title()
     */
    public function title(): string
    {
        return I18n::translate('Repository Hierarchy');
    }
	
    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\AbstractModule::description()
     */
    public function description(): string
    {
        /* I18N: Description of the “AncestorsChart” module */
        return I18N::translate('A hierarchical structured list of the sources of an archive based on the call numbers of the sources');
    }
	
    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\AbstractModule::resourcesFolder()
     */
    public function resourcesFolder(): string
    {
		return __DIR__ . '/resources/';
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleAuthorName()
     */
    public function customModuleAuthorName(): string
    {
        return self::CUSTOM_AUTHOR;
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleVersion()
     */
    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleLatestVersionUrl()
     */
    public function customModuleLatestVersionUrl(): string
    {
        return 'https://raw.githubusercontent.com/' . self::GITHUB_REPO . '/main/latest-version.txt';
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleLatestVersion()
     */
    public function customModuleLatestVersion(): string
    {
        return 'https://github.com/' . self::GITHUB_REPO . '/releases/latest';
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleSupportUrl()
     */
    public function customModuleSupportUrl(): string
    {
        return 'https://github.com/' . self::GITHUB_REPO . '/issues';
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customTranslations()
     */
    public function customTranslations(string $language): array
    {
        $lang_dir   = $this->resourcesFolder() . 'lang/';
        $file       = $lang_dir . $language . '.mo';
        if (file_exists($file)) {
            return (new Translation($file))->asArray();
        } else {
            return [];
        }
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleInterface::getAdminAction()
     */
    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->layout = 'layouts/administration';

        return $this->viewResponse($this->name() . '::settings', [
            'title'                                     => $this->title(),
            self::PREF_SHOW_CATEGORY_LABEL              => boolval($this->getPreference(self::PREF_SHOW_CATEGORY_LABEL, '1')),
            self::PREF_SHOW_HELP_ICON                   => boolval($this->getPreference(self::PREF_SHOW_HELP_ICON, '1')),
            self::PREF_SHOW_HELP_LINK                   => boolval($this->getPreference(self::PREF_SHOW_HELP_LINK, '1')),
            self::PREF_SHOW_TRUNCATED_CALL_NUMBER       => boolval($this->getPreference(self::PREF_SHOW_TRUNCATED_CALL_NUMBER, '1')),
            self::PREF_SHOW_TRUNCATED_CATEGORY          => boolval($this->getPreference(self::PREF_SHOW_TRUNCATED_CATEGORY, '1')),
            self::PREF_SHOW_TITLE                       => boolval($this->getPreference(self::PREF_SHOW_TITLE, '1')),
            self::PREF_SHOW_XREF                        => boolval($this->getPreference(self::PREF_SHOW_XREF, '1')),
            self::PREF_SHOW_AUTHOR                      => boolval($this->getPreference(self::PREF_SHOW_AUTHOR, '1')),
            self::PREF_SHOW_DATE_RANGE                  => boolval($this->getPreference(self::PREF_SHOW_DATE_RANGE, '1')),
            self::PREF_ALLOW_ADMIN_DELIMITER            => boolval($this->getPreference(self::PREF_ALLOW_ADMIN_DELIMITER, '1')),
            self::PREF_SHOW_SOURCE_FACTS_IN_CITATIONS   => boolval($this->getPreference(self::PREF_SHOW_SOURCE_FACTS_IN_CITATIONS, '0')),
            self::PREF_ALLOW_ADMIN_XML_SETTINGS         => boolval($this->getPreference(self::PREF_ALLOW_ADMIN_XML_SETTINGS, '1')),
            self::PREF_ATOM_SLUG                        => $this->getPreference(self::PREF_ATOM_SLUG, self::PREF_ATOM_SLUG_CALL_NUMBER),
            self::PREF_SHOW_ATOM_LINKS                  => boolval($this->getPreference(self::PREF_SHOW_ATOM_LINKS, '0')),
            self::PREF_WEBTREES_BASE_URL                => $this->getPreference(self::PREF_WEBTREES_BASE_URL, ''),
            self::PREF_ATOM_BASE_URL                    => $this->getPreference(self::PREF_ATOM_BASE_URL, ''),
            self::PREF_ATOM_REPOSITORIES                => $this->getPreference(self::PREF_ATOM_REPOSITORIES, ''),
        ]);    
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleInterface::postAdminAction()
     */
    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getParsedBody();

        //Save the received settings to the user preferences
        if ($params['save'] === '1') {
            $this->setPreference(self::PREF_SHOW_CATEGORY_LABEL, isset($params[self::PREF_SHOW_CATEGORY_LABEL])? '1':'0');
            $this->setPreference(self::PREF_SHOW_HELP_ICON, isset($params[self::PREF_SHOW_HELP_ICON])? '1':'0');
            $this->setPreference(self::PREF_SHOW_HELP_LINK, isset($params[self::PREF_SHOW_HELP_LINK])? '1':'0');
            $this->setPreference(self::PREF_SHOW_TRUNCATED_CALL_NUMBER, isset($params[self::PREF_SHOW_TRUNCATED_CALL_NUMBER])? '1':'0');
            $this->setPreference(self::PREF_SHOW_TRUNCATED_CATEGORY, isset($params[self::PREF_SHOW_TRUNCATED_CATEGORY])? '1':'0');
            $this->setPreference(self::PREF_SHOW_TITLE, isset($params[self::PREF_SHOW_TITLE])? '1':'0');
            $this->setPreference(self::PREF_SHOW_XREF, isset($params[self::PREF_SHOW_XREF])? '1':'0');
            $this->setPreference(self::PREF_SHOW_AUTHOR, isset($params[self::PREF_SHOW_AUTHOR])? '1':'0');
            $this->setPreference(self::PREF_SHOW_DATE_RANGE, isset($params[self::PREF_SHOW_DATE_RANGE])? '1':'0');
            $this->setPreference(self::PREF_ALLOW_ADMIN_DELIMITER, isset($params[self::PREF_ALLOW_ADMIN_DELIMITER])? '1':'0');
            $this->setPreference(self::PREF_SHOW_SOURCE_FACTS_IN_CITATIONS, isset($params[self::PREF_SHOW_SOURCE_FACTS_IN_CITATIONS])? '1':'0');
            $this->setPreference(self::PREF_ALLOW_ADMIN_XML_SETTINGS, isset($params[self::PREF_ALLOW_ADMIN_XML_SETTINGS])? '1':'0');
            $this->setPreference(self::PREF_SHOW_ATOM_LINKS, isset($params[self::PREF_SHOW_ATOM_LINKS])? '1':'0');
            $this->setPreference(self::PREF_ATOM_SLUG, isset($params[self::PREF_ATOM_SLUG])? $params[self::PREF_ATOM_SLUG]: self::PREF_ATOM_SLUG_CALL_NUMBER);

            //Remove slashes at the end of the URL
            if(isset($params[self::PREF_WEBTREES_BASE_URL])) {
                
                $webtrees_base_url = $params[self::PREF_WEBTREES_BASE_URL];

                if(substr($webtrees_base_url, -1) === '/') {
                    $webtrees_base_url = substr($webtrees_base_url, 0, -1);
                } 
            } else {
                $webtrees_base_url = '';
            }

            //Remove slashes at the end of the URL
            if(isset($params[self::PREF_ATOM_BASE_URL])) {

                $atom_base_url = $params[self::PREF_ATOM_BASE_URL];

                if(substr($atom_base_url, -1) === '/') {
                    $atom_base_url = substr($atom_base_url, 0, -1);
                } 
            } else {
                $atom_base_url = '';
            }
            
            $this->setPreference(self::PREF_WEBTREES_BASE_URL, $webtrees_base_url);
            $this->setPreference(self::PREF_ATOM_BASE_URL, $atom_base_url);
            $this->setPreference(self::PREF_ATOM_REPOSITORIES, isset($params[self::PREF_ATOM_REPOSITORIES])? $params[self::PREF_ATOM_REPOSITORIES]:'');

            $message = I18N::translate('The preferences for the module “%s” were updated.', $this->title());
            FlashMessages::addMessage($message, 'success');
        }

        return redirect($this->getConfigLink());
    }

    /**
     * Update the preferences (after new module version is detected)
     *
     * @return string
     */
    public function updatePreferences(): string
    {
        //Currently empty. Might be used in further versions of the module
        $error = '';
        return $error;
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleListInterface::listMenuClass()
     */
    public function listMenuClass(): string {

        //CSS class for module Icon (included in CSS file) is returned to be shown in the list menu
        return 'menu-list-repository-hierarchy';
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleListInterface::listIsEmpty()
     */
    public function listIsEmpty(Tree $tree): bool
    {
        return !DB::table('other')
            ->where('o_file', '=', $tree->id())
            ->where('o_type', '=', Repository::RECORD_TYPE)
            ->exists();
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleListInterface::listUrl()
     */

    public function listUrl(Tree $tree, array $parameters = []): string
    {
        $parameters['tree'] = $tree->name();

        return route(RepositoryHierarchy::class, $parameters);
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleGlobalInterface::headContent()
     */
    public function headContent(): string {
        //Include CSS file in head of webtrees HTML to make sure it is always found
        return '<link href="' . $this->assetUrl('css/repository-hierarchy.css') . '" type="text/css" rel="stylesheet" />';
    }    

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleDataFixInterface::fixOptions()
     */
    public function fixOptions(Tree $tree): string
    {
        //If data fix is called from wrong context, show error text
        if (!isset($this->repository_xref)) {
            $error_text =   I18N::translate('The Repository Hierarchy data fix cannot be used in the "control panel".') . '<br>' . 
                            I18N::translate('The data fix can be called from the user front end by clicking on the link to rename a call number category.');

            return view($this->name() . '::error', [
                'text' => $error_text,
                ]
            );
        }

        //If user is not a manager for this tree, show error text
        if (!Auth::isManager($tree)) {
            $error_text =   I18N::translate('Currently, you do not have the user rights to change call number categories.') . '<br>' . 
                            I18N::translate('In order to change call number categories, you need to have a "Manager" role for the corresponding tree.');

            return view($this->name() . '::error', [
                'text' => $error_text,
                ]
            );
        }

        return view($this->name() . '::options', [
            CallNumberCategory::VAR_REPOSITORY_XREF     => $this->repository_xref,
            CallNumberCategory ::VAR_CATEGORY_FULL_NAME => $this->data_fix_category_full_name,
            CallNumberCategory::VAR_CATEGORY_NAME       => $this->data_fix_category_name,
            self::VAR_DATA_FIX_CATEGORY_NAME_REPLACE    => $this->data_fix_category_name,
            self::VAR_DATA_FIX_TYPES                    => [Source::RECORD_TYPE => MoreI18N::xlate('Sources')],
            ]
        );
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleDataFixInterface::doesRecordNeedUpdate()
     */
    public function doesRecordNeedUpdate(GedcomRecord $record, array $params): bool
    {
        $search = preg_quote($params[CallNumberCategory::VAR_CATEGORY_FULL_NAME], '/');
        $regex  = '/\n1 REPO @'. $params[CallNumberCategory::VAR_REPOSITORY_XREF] . '@.*?\n2 CALN +' . $search . '[^$]*?$/';

        $test = preg_match($regex, $record->gedcom());
        return preg_match($regex, $record->gedcom()) === 1;
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleDataFixInterface::previewUpdate()
     */
    public function previewUpdate(GedcomRecord $record, array $params): string
    {
        $old = $record->gedcom();
        $new = $this->updateGedcom($record, $params);

        return $this->data_fix_service->gedcomDiff($record->tree(), $old, $new);
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleDataFixInterface::updateRecord()
     */
    public function updateRecord(GedcomRecord $record, array $params): void
    {
        $record->updateRecord($this->updateGedcom($record, $params), false);
    }

    /**
     * Update Gedcom for a record
     * 
     * @param GedcomRecord  $record
     * @param array         $params
     *
     * @return string
     */
    private function updateGedcom(GedcomRecord $record, array $params): string
    {
        $repository_xref = $params[CallNumberCategory::VAR_REPOSITORY_XREF];
        $pos = strpos($params[CallNumberCategory::VAR_CATEGORY_FULL_NAME],$params[CallNumberCategory::VAR_CATEGORY_NAME]);
        $truncated_category = substr($params[CallNumberCategory::VAR_CATEGORY_FULL_NAME], 0, $pos);
        $new_category_name = $truncated_category . $params[self::VAR_DATA_FIX_CATEGORY_NAME_REPLACE];
    
        $search  = preg_quote($params[CallNumberCategory::VAR_CATEGORY_FULL_NAME], '/');
        $regex  = '/(\n1 REPO @'. $repository_xref .'@.*?\n2 CALN +)' . $search . '([^$]*?$)/';

        $replace = '$1' . addcslashes($new_category_name, '$\\') . '$2';

        return preg_replace($regex, $replace, $record->gedcom());
    }

    /**
     * A  list of all source records that might need fixing.
     *
     * @param Tree                 $tree
     * @param array<string,string> $params
     *
     * @return Collection<int,object>
     */
    protected function sourcesToFix(Tree $tree, array $params): ?Collection
    {
        if ($params[CallNumberCategory::VAR_CATEGORY_NAME] === '' || $params[self::VAR_DATA_FIX_CATEGORY_NAME_REPLACE] === '') {
            return null;
        }

        $search = '%' . addcslashes($params[CallNumberCategory::VAR_CATEGORY_FULL_NAME], '\\%_') . '%';

        return  $this->sourcesToFixQuery($tree, $params)
            ->where('s_file', '=', $tree->id())
            ->where('s_gedcom', 'LIKE', $search)
            ->pluck('s_id');
    }

    /**
     * The title for a specific instance of this list.
     *
     * @param Repository
     *
     * @return string
     */
    public function getListTitle(Repository $repository = null): string 
    {
        //In this module, repositories are listed
        if ($repository === null) {
            return I18N::translate('Repository Hierarchy');
        } else {
            return I18N::translate('Repository Hierachy of: %s', $repository->fullName());
        }
    }

    /**
     * Get related tree
     * 
     * @return Tree     $tree;
     */
    public function getTree(): Tree {
        return $this->tree;
    }
    
    /**
     * Get repository
     * 
     * @return Repository
     */
    public function getRepository(): Repository {
        return $this->repository;
    }

    /**
     * Get xref of the related repository
     * 
     * @return string
     */
    public function getRepositoryXref(): string {
        return $this->repository_xref;
    }

    /**
     * Get root category
     * 
     * @return CallNumberCategory
     */
    public function getRootCategory(): CallNumberCategory {
        return $this->root_category;
    }

    /**
     * Get stored repository for a user
     *
     * @param Tree      $tree
     * @param string    $xref
     * 
     */
    public function getStoredRepositoryXref(Tree $tree, UserInterface $user): string
    {
        return $this->getPreference(self::PREF_REPOSITORY . $tree->id() . '_' . $user->id(), '');
    }

    /**
     * Error text with a header
     * 
     * @param string $error_text
     * @param bool $show_module_name
     * 
     * @return string
     */
    public function errorTextWithHeader(string $error_text = '', bool $show_module_name = false): string
    {
        if ($show_module_name) {
            return MoreI18N::xlate('Custom module') . ': ' . $this->name() . '<br>' . $error_text;        
        } else {
            return $error_text;
        }
    }

    /**
     * Set data fix params
     *
     * @param Tree      $tree
     * @param string    $xref
     * @param string    $category_name
     * @param string    $category_full_name
     */
    public function setDataFixParams(Tree $tree, string $xref, string $category_name, string $category_full_name)
    {
        $this->tree = $tree;
        $this->repository_xref = $xref;
        $this->data_fix_category_name = $category_name;
        $this->data_fix_category_full_name = $category_full_name;
    }

    /**
     * Parse a delimiter expression
     *
     * @param string         $delimiter_expression
     * @return array         [found reg exps , errorlist]
     */
     public function parseDelimiterExpression(string $delimiter_expression): array {

        $parsed_expressions = [];
        $error_list = [];

        //Ecape '/', because it is used as delimiter in preg_split, preg_match, etc.
        $delimiter_expression = str_replace('/', '\/', $delimiter_expression);

        //Substitute escaped delimiter separator
        $delimiter_expression = str_replace('\\' . self::DELIMITER_SEPARATOR, self::DELIMITER_ESCAPE, $delimiter_expression);

        //Find delimitor expressions separated by delimiter separator (substitute)
        $matches = preg_split('/' . self::DELIMITER_SEPARATOR . '/', $delimiter_expression, -1, PREG_SPLIT_NO_EMPTY);
        
        foreach ($matches as $match) {

            //re-substitue escaped delimiter separator
            $match = str_replace(self::DELIMITER_ESCAPE, self::DELIMITER_SEPARATOR, $match);

            //If found regex is not valid, fill array with error message
            if ((@preg_match('/' . $match . '/', '') === false) OR 
                ($delimiter_expression == '$') OR
                ($delimiter_expression == '.') )             
            {  
                array_push($error_list, I18N::translate('Regular expression not accepted') . ': <b>' . $match . '</b>');
            }
            //If found regex is valid, add to list of delimitor expressions
            else {
                array_push($parsed_expressions, $match);
            }
        }

        if (empty($parsed_expressions)) {
            array_push($error_list, I18N::translate('No valid delimiter or valid regular expression for delimiter found.'));
        }

        return [$parsed_expressions, $error_list];
    }

    /**
     * Whether a certain regular expression is found in a call number
     *
     * @param string $call_number
     * @param array $delimiter_reg_exps 
     *      
     * @return bool
     */
    public function regExpFoundInCallNumber(string $call_number, array $delimiter_reg_exps): bool {

        foreach($delimiter_reg_exps as $delimiter_reg_exp) {

            //Try to find regular expression provided in delimiter 
            preg_match_all('/' . $delimiter_reg_exp . '/', $call_number, $matches, PREG_SET_ORDER);
                            
            if (!empty($matches) ) {
                 return true;
            }
        }

        return false;
    }

    /**
     * Add a source to a call number category (usually a hierarchy of call number categories)
     *
     * @param CallNumberCategory
     * @param Source 
     * @param string $call_number_chunk
     */
    public function addSourceToCallNumberCategory(CallNumberCategory $category, Source $source, string $call_number_chunk)
    {
        $delimiter_reg_exps = $category->getDelimiterRegExps();
        $found = false;

        //If call number chunk contains default delimiter, use default delimiter in delimiter reg exps 
        if (strpos($call_number_chunk, self::DELIMITER_ATTRIBUTE_DEFAULT)) {
            $delimiter_reg_exps = [self::DELIMITER_ATTRIBUTE_DEFAULT];
        }

        foreach($delimiter_reg_exps as $delimiter_reg_exp) {

            //Try to find delimiter reg exp in the call number chunk
            preg_match_all('/' . $delimiter_reg_exp . '/', $call_number_chunk, $matches, PREG_OFFSET_CAPTURE);

            if (!empty($matches[0]) ) {

                if (empty($matches[1]) ) {
                    $matched_part = $matches[0][0][0];
                    $pos_start = $matches[0][0][1];
                } else {
                    $matched_part = $matches[1][0][0];
                    $pos_start = $matches[1][0][1];
                }

                $found = true;
                break;
            }
        }        

        //If delimiter expression found in call_number_chunk, call recursion
        if ($found) {
                	
                $pos_end = $pos_start + strlen($matched_part);                    
                $length = strlen($call_number_chunk);
                $left   = substr($call_number_chunk, 0, $pos_start);
                $right  = substr($call_number_chunk, -($length - $pos_end), $length - $pos_end);

                //If found category name is empty, take default
                if ($left === '') {
                    $left = CallNumberCategory::DEFAULT_CATEGORY_NAME;
                }

                $category_found = false;
                
                //Search categories if category is already available
                foreach ($category->getSubCategories() as $sub_category) {				

                    if ($sub_category->getName() === $left . $matched_part) {
                        $category_found = true;		
                        break;
                    }
                }

                //Create new category if not yet available
                if (!$category_found) {						
                    $sub_category = new CallNumberCategory(	$category->getTree(), $delimiter_reg_exps, FALSE, $left . $matched_part, 
                        $category->getFullName() . $left . $matched_part, $category->getHierarchyLevel() + 1, [] );
                    $category->addSubCategory($sub_category);
                }
                
                //recursion with the rest of the call_number_chunk
                $this->addSourceToCallNumberCategory($sub_category, $source, $right);	
        }

        //if expression for delimiter not found in call_number_chunk, add source to category
		else {
			$category->addSource($source);
			$category->addDateRange(Functions::getDateRangeForSource($source));
            $category->addTruncatedCallNumber($source, $call_number_chunk);
		}						
	}

    /**
     * Options for load/save delimiter expressions
     *
     * @return array<string>
     */
    public function getLoadSaveOptions(): array
    {
        if (boolval($this->getPreference(self::PREF_ALLOW_ADMIN_DELIMITER, '1'))) {
            $admin_option = [self::CMD_LOAD_ADMIN_DELIM => I18N::translate('load delimiter expression from administrator')];
        } else {
            $admin_option = [];
        }

        $options = [
            self::CMD_NONE              => I18N::translate('none'),
            self::CMD_SAVE_REPO         => I18N::translate('save repository'),
            self::CMD_LOAD_REPO         => I18N::translate('load repository'),
            self::CMD_SAVE_DELIM        => I18N::translate('save delimiter expression'),
            self::CMD_LOAD_DELIM        => I18N::translate('load delimiter expression'),
        ];

        return $options + $admin_option;
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree                   = Validator::attributes($request)->tree();
        $user                   = Validator::attributes($request)->user();
        $xref                   = Validator::attributes($request)->string('xref');
		$delimiter_expression   = Validator::attributes($request)->string('delimiter_expression');
        $command                = Validator::attributes($request)->string('command');

        if($command === self::CMD_DOWNLOAD_XML) {
            $download_command = Validator::parsedBody($request)->string('download_command');
        }

        // Convert POST requests into GET requests for pretty URLs.
        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {

            if($command === self::CMD_DOWNLOAD_XML) {
                return redirect(route(self::class, [
                    'tree'        	        => $tree->name(),
                    'xref'        	        => $xref,
                    'delimiter_expression'	=> $delimiter_expression,
                    'command'               => $download_command,
                ]));
    
            } else {
                return redirect(route(self::class, [
                    'tree'        	        => $tree->name(),
                    'xref'        	        => Validator::parsedBody($request)->isXref()->string('xref'),
                    'delimiter_expression'	=> Validator::parsedBody($request)->string('delimiter_expression'),
                    'command'               => Validator::parsedBody($request)->string('command'),
                ]));
            }            
        }

        //Variable for error texts; default is empty
        $error_text = '';

        //Check module version
        if ($this->getPreference(self::PREF_MODULE_VERSION) !== self::CUSTOM_VERSION) {
            $this->setPreference(self::PREF_MODULE_VERSION, self::CUSTOM_VERSION);

            //Update prefences stored in database
            $update_result = $this->updatePreferences();

            //If error, show error message
            if ($update_result !== '') {
                return $this->viewResponse($this->name() . '::error', [
                    'text' => $this->errorTextWithHeader(I18N::translate('Error during update of preferences') . ': ' . $update_result)
                ]);      
            }
        } 

        //If requested, load stored repository and reset delimiter
        if ($command === self::CMD_LOAD_REPO) {
            $load_value = $this->getPreference(self::PREF_REPOSITORY . $tree->id() . '_' . $user->id());
            if ($load_value != '') {
                $xref = $load_value;
                $delimiter_expression = '';
            } 
            else {
                $error_text = $this->errorTextWithHeader(I18N::translate('Could not load repository. No repository stored.'));
            }      
        }

        //Validate xref
        if (($xref === self::XREF_ATTRIBUTE_DEFAULT) OR ($xref === '')) {
            
            //If available, load user preferences for repository 
            $load_value = $this->getPreference(self::PREF_REPOSITORY . $tree->id() . '_' . $user->id());
            if ($load_value != '') {
                $xref = $load_value;
            }            
           //Otherwise, load default repository
            else {
                $xref = Functions::getDefaultRepositoryXref($this, $tree, $user);
            }
        }

        //If still no repository found, show error message
        if ($xref === '') {
            return $this->viewResponse($this->name() . '::error', [
                'tree'  => $tree,
                'title' => $this->getListTitle(),
                'text'  => $this->errorTextWithHeader(I18N::translate('The tree “%s” does not contain any repository', $tree->name() ), true)
            ]);        
        }

        //Create and check repository from xref
        Auth::checkComponentAccess($this, ModuleInterface::class, $tree, $user);
        $repository  = Registry::repositoryFactory()->make($xref, $tree);
        $repository  = Auth::checkRepositoryAccess($repository, false, true);

        //Copy values to this instance
        $this->tree = $tree;
        $this->repository_xref = $xref;
        $this->repository = $repository;        

        //If requested, load stored delimiter expression
        if ($command === self::CMD_LOAD_ADMIN_DELIM) {
            $load_value = $this->getPreference(self::PREF_DELIMITER . $tree->id() . '_' . self::ADMIN_USER_STRING . '_' . $xref);
            if ($load_value != '') {
                $delimiter_expression = $load_value;
            }  
            //Show error message if no delimiter could be loaded
            else {
                $error_text = $this->errorTextWithHeader(I18N::translate('Could not load delimiter expression from administrator. No administrator delimiter expression stored.'));
            }                
        } elseif ($command === self::CMD_LOAD_DELIM) {
            $load_value = $this->getPreference(self::PREF_DELIMITER . $tree->id() . '_' . $user->id() . '_' . $xref);
            if ($load_value != '') {
                $delimiter_expression = $load_value;
            }
            //Show error message if no delimiter could be loaded
            else {
                $error_text = $this->errorTextWithHeader(I18N::translate('Could not load delimiter expression. No delimiter expression stored.'));
            }   
        }

        //If delimiter expression is empty, try to load user preferences. If not found, default is ''
		if (($delimiter_expression === '') OR ($delimiter_expression === self::DELIMITER_ATTRIBUTE_DEFAULT)) {    
            $delimiter_expression = $this->getPreference(self::PREF_DELIMITER . $tree->id() . '_' . $user->id() . '_' . $xref);
        } 

        //If delimiter expression is still empty, try to load admin preferences if allowed. If not found, default is ''
		if (($delimiter_expression === '') && (boolval($this->getPreference(self::PREF_ALLOW_ADMIN_DELIMITER, '1')))) {    
            $delimiter_expression = $this->getPreference(self::PREF_DELIMITER . $tree->id() . '_' . self::ADMIN_USER_STRING . '_' . $xref);
        } 

        //Validate delimiter expression
        if ($delimiter_expression !== '') {
            $parse_result = $this->parseDelimiterExpression($delimiter_expression);
            $delimiter_reg_exps = $parse_result[0];
            $delimiter_errors = $parse_result[1];   
        }
        
        //If the parsed delimiter expression contains errors, generate error message text
        if (!empty($delimiter_errors)) {

            $error_text = $this->errorTextWithHeader('<b>'. I18N::translate('Error in delimiter expression') . '</b>' . '<p>');  

            foreach ($delimiter_errors as $delimiter_error) {
                $error_text .= $delimiter_error . '<p>';
            }       

            $error_text .= 
                '<p>' . I18N::translate('Please note that the following characters need to be escaped if not used as meta characters in a regular expression').
                ': ' . '<b>' . self::ESCAPE_CHARACTERS . self::DELIMITER_SEPARATOR .'</b><br>' .
                '</p>' .
                '<p>' . I18N::translate('For example, use').' "<b>\[</b>" ' . I18N::translate('instead of') . ' "<b>[</b>" '. I18N::translate('or') . 
                ' "<b>\+</b>" '. I18N::translate('instead of') . ' "<b>+</b>" ' . I18N::translate('if the characters shall be used as plain text.').
                '</p>';
        }

        //Generate the content
        if (($delimiter_expression !=='') && ($error_text === '')) {

            //Save user preferences if requested
            if ($command === self::CMD_SAVE_REPO) {
                $this->setPreference(self::PREF_REPOSITORY . $tree->id() . '_' . $user->id(), $xref);
            } 
            elseif ($command === self::CMD_SAVE_DELIM) {
                $this->setPreference(self::PREF_DELIMITER . $tree->id() . '_' . $user->id() . '_' . $xref, $delimiter_expression);
                
                //If user is admin, store same preference a second time with an admin string as user
                if (Auth::isManager($tree, $user)) {
                    $this->setPreference(self::PREF_DELIMITER . $tree->id() . '_' . self::ADMIN_USER_STRING . '_' . $xref, $delimiter_expression);
                }
            }
        
            //Find and sort all sources linked to the repository
            $linked_sources = (new LinkedRecordService())->linkedSources($repository);
            $linked_sources = Functions::sortSourcesByCallNumber($linked_sources);

            //Generate root category
            $this->root_category = new CallNumberCategory($tree, $delimiter_reg_exps, TRUE);

            //Generate the (recursive) hierarchy of call numbers
            foreach ($linked_sources as $source) {
    
                $call_number = Functions::getCallNumberForSource($source, $this->repository);

                //If call number is empty, assign empty category and default delimiter
                if ($call_number === '') {
                    $call_number = CallNumberCategory::EMPTY_CATEGORY_NAME . self::DELIMITER_ATTRIBUTE_DEFAULT;
                }

                //If call number does not match reg exp, assign default category to call number
                elseif (!$this->regExpFoundInCallNumber($call_number, $delimiter_reg_exps) ) {
                    $call_number = CallNumberCategory::DEFAULT_CATEGORY_NAME . self::DELIMITER_ATTRIBUTE_DEFAULT . $call_number;
                }

                $this->addSourceToCallNumberCategory($this->root_category, $source, $call_number);
            }
        } else {
            $this->root_category = new CallNumberCategory($tree, array() );
        }

        //Calculate date ranges for the whole hierarchy of call number categories
        $date_range = $this->getRootCategory()->calculateDateRange();

        //If download of EAD XML is requested, create and return download
        if ($command === DownloadEADxmlService::DOWNLOAD_OPTION_EAD_XML) {
            
            $xml_type = $command;
            $title = $this->getPreference(RepositoryHierarchy::PREF_FINDING_AID_TITLE . $tree->id() . '_' . $xref . '_' . $user->id(), '');

            if($title === '') {
                $error_text = $this->errorTextWithHeader('<b>'. I18N::translate('XML export settings not found. Please open EAD XML settings and provide settings.') . '</b>' . '<p>');  
            } 
            else {
                //Initialize EAD XML
                $download_ead_xml_service = new DownloadEADxmlService($xml_type, $this->repository, $this->root_category, $user);

                //Create EAD XML export
                $download_ead_xml_service->createXMLforCategory($xml_type, $download_ead_xml_service->getCollection(), $this->root_category);
    
                //Start download
                return $download_ead_xml_service->downloadResponse('apeEAD');
            }
        }         

        //If download of HTML finding aid is requested, create and return download
        if ($command === DownloadEADxmlService::DOWNLOAD_OPTION_HTML) {
            $title = I18N::translate('Finding aid');

            //Create finding aid and download
            $this->download_finding_aid_service = new DownloadFindingAidService($this->repository, $this->root_category, $user);
            return $this->download_finding_aid_service->downloadHtmlResponse('finding_aid');
        }

        //If download of PDF finding aid is requested, create and return download
        if ($command === DownloadEADxmlService::DOWNLOAD_OPTION_PDF) {
            $title = I18N::translate('Finding aid');

            //Create finding aid and download
            $this->download_finding_aid_service = new DownloadFindingAidService($this->repository, $this->root_category, $user);
            return $this->download_finding_aid_service->downloadPDFResponse('finding_aid');
        }


        //Return the page view
        return $this->viewResponse($this->name() . '::page', [
            'tree'                              => $tree,
            'title'                             => $this->getListTitle($repository),
            'repository_hierarchy'              => $this,
			'delimiter_expression'              => $delimiter_expression,
            'error'                             => $error_text,
            'command'                           => self::CMD_NONE,
        ]);
    }
}