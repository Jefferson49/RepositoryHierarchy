<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2023 webtrees development team
 *                    <http://webtrees.net>
 *
 * Fancy Research Links (webtrees custom module):
 * Copyright (C) 2023 Carmen Just
 *                    <https://justcarmen.nl>
 *
 * Fancy Simple media display module (webtrees custom module):
 * Copyright (C) 2023 Carmen Just
 *                    <https://justcarmen.nl>
 *
 * RepositoryHierarchy (webtrees custom module):
 * Copyright (C) 2023 Markus Hemprich
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
use Exception;
use Fig\Http\Message\RequestMethodInterface;
use Fig\Http\Message\StatusCodeInterface;
use Fisharebest\Localization\Translation;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\ElementInterface;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\RequestHandlers\FamilyPage;
use Fisharebest\Webtrees\Http\RequestHandlers\IndividualPage;
use Fisharebest\Webtrees\Http\RequestHandlers\MediaPage;
use Fisharebest\Webtrees\Http\RequestHandlers\NotePage;
use Fisharebest\Webtrees\Http\RequestHandlers\RepositoryPage;
use Fisharebest\Webtrees\Http\RequestHandlers\SourcePage;
use Fisharebest\Webtrees\Http\RequestHandlers\SubmitterPage;
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
use Fisharebest\Webtrees\Module\ModuleInterface;
use Fisharebest\Webtrees\Module\ModuleListInterface;
use Fisharebest\Webtrees\Module\ModuleListTrait;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Repository;
use Fisharebest\Webtrees\Services\DataFixService;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\View;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

use function route;

/**
 * Main class to create and view a Repository Hierarchy
 */
class RepositoryHierarchy extends AbstractModule implements
    MiddlewareInterface,
    ModuleConfigInterface,
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

    //Custom module version
    public const CUSTOM_VERSION = '1.3.7';

    //Routes, attributes
    protected const MODULE_NAME_IN_ROUTE = 'repositoryhierarchy';
    protected const HELP_TEXTS_IN_ROUTE = 'repositoryhierarchy_helptexts';
    protected const CREATE_SOURCE_IN_ROUTE = 'repositoryhierarchy_create_source';
    protected const FIX_CALL_NUMBER_IN_ROUTE = 'repositoryhierarchy_fix_callnumbers';
    protected const REPO_ACTIONS_IN_ROUTE = 'repositoryhierarchy_repo_actions';
    protected const XML_SETTINGS_MODAL_IN_ROUTE = 'repositoryhierarchy_xml_settings_modal';
    protected const XML_SETTINGS_ACTION_IN_ROUTE = 'repositoryhierarchy_xml_settings_action';
    protected const COPY_SOURCE_CITATION_IN_ROUTE = 'repositoryhierarchy_copy_citation';
    protected const PASTE_SOURCE_CITATION_IN_ROUTE = 'repositoryhierarchy_paste_citation';
    protected const TREE_ATTRIBUTE_DEFAULT = '{tree}';
    protected const XREF_ATTRIBUTE_DEFAULT = '{xref}';
    protected const DELIMITER_ATTRIBUTE_DEFAULT = '{delimiter_expression}';
    protected const COMMAND_ATTRIBUTE_DEFAULT = '{command}';
    protected const TOPIC_ATTRIBUTE_DEFAULT = '{topic}';
    protected const SOURCE_CALL_NUMBER_ATTRIBUTE_DEFAULT = '{source_call_number}';
    protected const CATEGORY_NAME_ATTRIBUTE_DEFAULT = '{category_name}';
    protected const CATEGORY_FULL_NAME_ATTRIBUTE_DEFAULT = '{category_full_name}';
    protected const FACT_ID_ATTRIBUTE_DEFAULT = '{fact_id}';

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
    public const PREF_SHOW_CATEGORY_TITLE = 'show_category_title';
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
    public const PREF_SHOWN_SOURCE_FACTS_IN_CITATIONS ='shown_source_facts_in_citations';
    public const PREF_EXPANDED_SOURCE_FACTS_IN_CITATIONS ='explanded_facts_in_citations';
	public const PREF_SHOW_MEDIA_AFTER_CITATIONS = 'show_media_after_citations';
    public const PREF_ENABLE_COPY_PASTE_CITATIONS ='enable_copy_paste_citations';
    public const PREF_SHOW_DATE_RANGE_FOR_CATEGORY ='show_date_range_for_category';
    public const PREF_SHOW_ATOM_LINKS ='show_atom_links';
    public const PREF_ATOM_SLUG ='atom_slug';
    public const PREF_ATOM_SLUG_TITLE ='title';
    public const PREF_ATOM_SLUG_CALL_NUMBER ='call_number';
    public const PREF_WEBTREES_BASE_URL = 'webtrees_base_url';
    public const PREF_ATOM_BASE_URL = 'atom_base_url';
    public const PREF_ATOM_REPOSITORIES = 'atom_repositories';
    public const PREF_XML_VERSION = 'xml_version_';
    public const PREF_FINDING_AID_TITLE = 'fi_aid_title_';
    public const PREF_COUNTRY_CODE = 'country_code_';
    public const PREF_MAIN_AGENCY_CODE = 'agency_code_';
    public const PREF_FINDING_AID_IDENTIFIER = 'fi_aid_id_';
    public const PREF_FINDING_AID_URL = 'fi_aid_url_';
    public const PREF_FINDING_AID_PUBLISHER = 'fi_aid_publ_';
    public const PREF_ALLOW_ADMIN_XML_SETTINGS = 'allow_admin_xml_settings';
    public const PREF_SHOW_FINDING_AID_CATEGORY_TITLE = 'show_finding_aid_category_title';
    public const PREF_SHOW_FINDING_AID_ADDRESS = 'show_finding_aid_address';
    public const PREF_SHOW_FINDING_AID_TOC = 'show_finding_aid_toc';
    public const PREF_SHOW_FINDING_AID_TOC_LINKS = 'show_finding_aid_toc_links';
    public const PREF_SHOW_FINDING_AID_TOC_TITLES = 'show_finding_aid_toc_titles';
    public const PREF_SHOW_FINDING_AID_WT_LINKS = 'show_finding_aid_wt_links';
    public const PREF_USE_META_REPOSITORIES = 'use_meta_repositories';
    public const PREF_ALLOW_RENAME = 'allow_rename';
    public const PREF_ALLOW_NEW_SOURCE = 'allow_new_source';
    public const PREF_CITATION_GEDCOM = 'citation_gedcom';

    //Old prefences/settings not used any more, but needed for version updates
    public const PREF_DELETED = 'deleted';
    public const OLD_PREF_FINDING_AID_TITLE = 'finding_aid_title_';
    public const OLD_PREF_FINDING_AID_IDENTIFIER = 'finding_aid_id_';
    public const OLD_PREF_FINDING_AID_URL = 'finding_aid_url_';
    public const OLD_PREF_FINDING_AID_PUBLISHER = 'finding_aid_publ_';
    public const OLD_PREF_MAIN_AGENCY_CODE = 'main_agency_code_';
    public const OLD_PREF_SHOW_SOURCE_FACTS_IN_CITATIONS = 'show_source_facts_in_citations';
    public const OLD_PREF_SHOW_REPO_FACTS_IN_CITATIONS = 'show_repo_facts_in_citations';
    public const OLD_PREF_EXPAND_REPOS_IN_CITATIONS ='expand_repos_in_citations';
    public const OLD_PREF_SHOW_SOURCE_MEDIA_IN_CITATIONS ='show_source_media_in_citations';
    public const OLD_PREF_SHOW_FURTHER_FACTS_IN_CITATIONS ='show_further_facts_in_citations';

    //String for admin for use in preferences names
    public const ADMIN_USER_STRING = 'admin';

    //Commands to load and save delimiters
    public const CMD_NONE = 'none';
    public const CMD_LOAD_ADMIN_DELIM = 'load_delimiter_from_admin';
    public const CMD_LOAD_DELIM = 'load_delimiter';
    public const CMD_SAVE_DELIM = 'save_delimiter';
    public const CMD_SAVE_REPO = 'save_repository';
    public const CMD_LOAD_REPO = 'load_repository';
    public const CMD_DOWNLOAD = 'command_download';
    public const CMD_LOAD_ADMIN_XML_SETTINGS = 'load_admin_xml_settings';

    //Constants for page names
    public const LAST_PAGE_NAME = 'last_page_name';
    public const LAST_PAGE_TREE = 'last_page_tree';
    public const LAST_PAGE_PARAMETER = 'last_page_parameter';
    public const PAGE_NAME_INDIVIDUAL = 'individual.php';
    public const PAGE_NAME_FAMILY = 'family.php';
    public const PAGE_NAME_OTHER = 'other';

    //Comands for repositories
    public const CMD_SET_AS_START_REPO = 'set as start repository';

    //User reference types in SOUR:REFN:TYPE
    public const SOUR_REFN_TYPE_META_REPO = 'META_REPOSITORY';

    //Github repository
    public const GITHUB_REPO = 'Jefferson49/RepositoryHierarchy';

    //Github API URL to get the information about the latest releases
    public const GITHUB_API_LATEST_VERSION = 'https://api.github.com/repos/'. self::GITHUB_REPO . '/releases/latest';
    public const GITHUB_API_TAG_NAME_PREFIX = '"tag_name":"v';

    //Author of custom module
    public const CUSTOM_AUTHOR = 'Markus Hemprich';

    //Website of author
    public const AUTHOR_WEBSITE = 'http://www.familienforschung-hemprich.de';

    //The tree, to which the repository hierarchy relates
    private Tree $tree;

    //The xref string of the repository, to which the repository hierarchy relates
    private string $repository_xref;

    //The xref string of the meta repository (if available)
    private string $meta_repository_xref;

    //The repository, to which the repository hierarchy relates
    private Repository $repository;

    //The meta repository (if available)
    private Repository $meta_repository;

    //Root element of category hierarchy
    private CallNumberCategory $root_category;

    //The data fix service
    private DataFixService $data_fix_service;

    //The full name of the call number category to be fixed
    private string $data_fix_category_full_name = '';

    //The name of the call number category to be fixed
    private string $data_fix_category_name = '';

    //The path of the .po files for call number category titles
    private string $call_number_category_titles_po_file_path;

    //The call number category title service, which is used
    private C16Y $call_number_category_title_service;

    //A list of custom views, which are registered by the module
    private Collection $custom_view_list;

    //Tables for fast access to source data
    public array $title_of_source;
    public array $author_of_source;
    public array $call_number_of_source;
    public array $truncated_call_number_of_source;
    public array $date_range_of_source;
    public array $date_range_text_of_source;
    public array $iso_date_range_text_of_source;

    //All source facts, which can be shown within source citations
    public static Collection $ALL_SOURCE_FACTS_IN_CITATIONS;

    //All source facts, which can be expanded within source citations
    public static Collection $EXPANDABLE_SOURCE_FACTS_IN_CITATIONS;

    /**
     * Constructor
     */
    public function __construct()
    {
        //Create data fix service
        $this->data_fix_service = new DataFixService();

        //Path for .po files (if call number category titles are used)
        $this->call_number_category_titles_po_file_path = __DIR__ . '/resources/caln/';  

        //Initialization of source data tables
        $this->title_of_source = [];
        $this->author_of_source = [];
        $this->call_number_of_source = [];
        $this->date_range_of_source = [];
        $this->date_range_text_of_source = [];
        $this->iso_date_range_text_of_source = [];
    }

    /**
     * {@inheritDoc}
     *
     * @return void
     *
     * @see \Fisharebest\Webtrees\Module\AbstractModule::boot()
     */
    public function boot(): void
    {
        //Initialization of the custom view list
        $this->custom_view_list = new Collection;

        $ignore_facts = ['TITL', 'CHAN'];

        //Initialization of all source facts, which can be shown within source citations
        self::$ALL_SOURCE_FACTS_IN_CITATIONS = Collection::make(Registry::elementFactory()->make('SOUR')->subtags())
        ->filter(static fn (string $value, string $key): bool => !in_array($key, $ignore_facts, true))
        ->mapWithKeys(static fn (string $value, string $key): array => [$key => 'SOUR:' . $key])
        ->map(static fn (string $tag): ElementInterface => Registry::elementFactory()->make($tag))
        ->filter(static fn (ElementInterface $element): bool => !$element instanceof UnknownElement)
        ->map(static fn (ElementInterface $element): string => $element->label())
        ->sort(I18N::comparator());

        //Initialization of source facts, which can expanded within source citations
        self::$EXPANDABLE_SOURCE_FACTS_IN_CITATIONS = Collection::make([
            'REPO'  => MoreI18N::xlate('Repository'),
            'OBJE'  => MoreI18N::xlate('Media object'),
            'DATA'  => MoreI18N::xlate('Data'),
            'TEXT'  => MoreI18N::xlate('Text'),
        ]);

        //Check module version and update preferences etc.
        $this->checkModuleVersionUpdate();

        $router = Registry::routeFactory()->routeMap();

        //Register a route for the class
        $router ->get(
            self::class,
            '/tree/'.self::TREE_ATTRIBUTE_DEFAULT.
            '/'.self::MODULE_NAME_IN_ROUTE.
            '/xref/'.self::XREF_ATTRIBUTE_DEFAULT.
            '/command/'.self::COMMAND_ATTRIBUTE_DEFAULT,
            $this
        )
            ->allows(RequestMethodInterface::METHOD_POST);

        //Register a route for the help texts
        $router->get(
            RepositoryHierarchyHelpTexts::class,
            '/'.self::HELP_TEXTS_IN_ROUTE.
            '/topic/'.self::TOPIC_ATTRIBUTE_DEFAULT
        )
            ->allows(RequestMethodInterface::METHOD_POST);

        //Register a route for the create source modal
        $router ->get(
            CreateSourceModal::class,
            '/tree/'.self::TREE_ATTRIBUTE_DEFAULT.
            '/'.self::CREATE_SOURCE_IN_ROUTE.
            '/xref/'.self::XREF_ATTRIBUTE_DEFAULT
        )
            ->allows(RequestMethodInterface::METHOD_POST);

        //Register a route for the call number fix action
        $router ->get(
            CallNumberDataFix::class,
            '/tree/'.self::TREE_ATTRIBUTE_DEFAULT.
            '/'.self::FIX_CALL_NUMBER_IN_ROUTE.
            '/xref/'.self::XREF_ATTRIBUTE_DEFAULT
        )
            ->allows(RequestMethodInterface::METHOD_POST);

        //Register a route for the XML export settings modal
        $router ->get(
            XmlExportSettingsModal::class,
            '/tree/'.self::TREE_ATTRIBUTE_DEFAULT.
            '/'.self::XML_SETTINGS_MODAL_IN_ROUTE.
            '/xref/'.self::XREF_ATTRIBUTE_DEFAULT.
            '/command/'.self::COMMAND_ATTRIBUTE_DEFAULT
        )
            ->allows(RequestMethodInterface::METHOD_POST);

        //Register a route for the XML export settings action
        $router ->get(
            XmlExportSettingsAction::class,
            '/tree/'.self::TREE_ATTRIBUTE_DEFAULT.
            '/'.self::XML_SETTINGS_ACTION_IN_ROUTE.
            '/xref/'.self::XREF_ATTRIBUTE_DEFAULT.
            '/command/'.self::COMMAND_ATTRIBUTE_DEFAULT
        )
            ->allows(RequestMethodInterface::METHOD_POST);

        //Register a route for the copy source citation action
        $router ->get(
            CopySourceCitation::class,
            '/tree/'.self::TREE_ATTRIBUTE_DEFAULT.
            '/'.self::COPY_SOURCE_CITATION_IN_ROUTE.
            '/xref/'.self::XREF_ATTRIBUTE_DEFAULT
        )
            ->allows(RequestMethodInterface::METHOD_POST);

        //Register a route for the paste source citation action
        $router ->get(
            PasteSourceCitation::class,
            '/tree/'.self::TREE_ATTRIBUTE_DEFAULT.
            '/'.self::PASTE_SOURCE_CITATION_IN_ROUTE.
            '/xref/'.self::XREF_ATTRIBUTE_DEFAULT.
            '/fact_id/'.self::FACT_ID_ATTRIBUTE_DEFAULT
        )
            ->allows(RequestMethodInterface::METHOD_POST);

        //Register a namespace for the views
        View::registerNamespace(self::viewsNamespace(), $this->resourcesFolder() . 'views/');

        //Register a custom view for facts in order to show additional source facts in citations, media objects in facts, or AtoM links
        //Also used to show additonal icons to copy/delete source citation
        //Also used to show media objects with several images (code from jc-simple-media-display) 
        View::registerCustomView('::fact-gedcom-fields', $this->name() . '::fact-gedcom-fields');
        $this->custom_view_list->add($this->name() . '::fact-gedcom-fields');

        //Register a custom view for fact edit links in order to allow pasting source citations
        View::registerCustomView('::fact-edit-links', $this->name() . '::fact-edit-links');
        $this->custom_view_list->add($this->name() . '::fact-edit-links');
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\AbstractModule::title()
     */
    public function title(): string
    {
        return I18n::translate('Repository Hierarchy');
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\AbstractModule::description()
     */
    public function description(): string
    {
        /* I18N: Description of the “AncestorsChart” module */
        return I18N::translate('A hierarchical structured list of the sources of an archive based on the call numbers of the sources');
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\AbstractModule::resourcesFolder()
     */
    public function resourcesFolder(): string
    {
        return __DIR__ . '/resources/';
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleAuthorName()
     */
    public function customModuleAuthorName(): string
    {
        return self::CUSTOM_AUTHOR;
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleVersion()
     */
    public function customModuleVersion(): string
    {
        return self::CUSTOM_VERSION;
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleLatestVersion()
     */
    public function customModuleLatestVersion(): string
    {
        // No update URL provided.
        if (self::GITHUB_API_LATEST_VERSION === '') {
            return $this->customModuleVersion();
        }
        return Registry::cache()->file()->remember(
            $this->name() . '-latest-version',
            function (): string {
                try {
                    $client = new Client(
                        [
                        'timeout' => 3,
                        ]
                    );

                    $response = $client->get(self::GITHUB_API_LATEST_VERSION);

                    if ($response->getStatusCode() === StatusCodeInterface::STATUS_OK) {
                        $content = $response->getBody()->getContents();
                        preg_match_all('/' . self::GITHUB_API_TAG_NAME_PREFIX . '\d+\.\d+\.\d+/', $content, $matches, PREG_OFFSET_CAPTURE);

						if(!empty($matches[0]))
						{
							$version = $matches[0][0][0];
							$version = substr($version, strlen(self::GITHUB_API_TAG_NAME_PREFIX));	
						}
						else
						{
							$version = $this->customModuleVersion();
						}

                        return $version;
                    }
                } catch (GuzzleException $ex) {
                    // Can't connect to the server?
                }

                return $this->customModuleVersion();
            },
            86400
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleCustomInterface::customModuleSupportUrl()
     */
    public function customModuleSupportUrl(): string
    {
        return 'https://github.com/' . self::GITHUB_REPO;
    }

    /**
     * {@inheritDoc}
     *
     * @param string $language
     *
     * @return array
     *
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
     * Get the namespace for the views
     *
     * @return string
     */
    public static function viewsNamespace(): string
    {
        return '_' . basename(__DIR__) . '_';
    }

    /**
     * Get the active module name, e.g. the name of the currently running module
     *
     * @return string
     */
    public static function activeModuleName(): string
    {
        return '_' . basename(__DIR__) . '_';
    }

    /**
     * View module settings in control panel
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function getAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $this->checkCustomViewAvailability();

        $this->layout = 'layouts/administration';

        return $this->viewResponse(
            self::viewsNamespace() . '::settings',
            [
                'title'                                             => $this->title(),
                self::PREF_SHOW_CATEGORY_LABEL                 => boolval($this->getPreference(self::PREF_SHOW_CATEGORY_LABEL, '1')),
                self::PREF_SHOW_CATEGORY_TITLE                 => boolval($this->getPreference(self::PREF_SHOW_CATEGORY_TITLE, '0')),
                self::PREF_SHOW_HELP_ICON                      => boolval($this->getPreference(self::PREF_SHOW_HELP_ICON, '1')),
                self::PREF_SHOW_HELP_LINK                      => boolval($this->getPreference(self::PREF_SHOW_HELP_LINK, '1')),
                self::PREF_SHOW_TRUNCATED_CALL_NUMBER          => boolval($this->getPreference(self::PREF_SHOW_TRUNCATED_CALL_NUMBER, '1')),
                self::PREF_SHOW_TRUNCATED_CATEGORY             => boolval($this->getPreference(self::PREF_SHOW_TRUNCATED_CATEGORY, '1')),
                self::PREF_SHOW_DATE_RANGE_FOR_CATEGORY        => boolval($this->getPreference(self::PREF_SHOW_DATE_RANGE_FOR_CATEGORY, '1')),
                self::PREF_ALLOW_RENAME                        => boolval($this->getPreference(self::PREF_ALLOW_RENAME, '1')),
                self::PREF_ALLOW_NEW_SOURCE                    => boolval($this->getPreference(self::PREF_ALLOW_NEW_SOURCE, '1')),
                self::PREF_SHOW_TITLE                          => boolval($this->getPreference(self::PREF_SHOW_TITLE, '1')),
                self::PREF_SHOW_XREF                           => boolval($this->getPreference(self::PREF_SHOW_XREF, '1')),
                self::PREF_SHOW_AUTHOR                         => boolval($this->getPreference(self::PREF_SHOW_AUTHOR, '1')),
                self::PREF_SHOW_DATE_RANGE                     => boolval($this->getPreference(self::PREF_SHOW_DATE_RANGE, '1')),
                self::PREF_ALLOW_ADMIN_DELIMITER               => boolval($this->getPreference(self::PREF_ALLOW_ADMIN_DELIMITER, '1')),
                self::PREF_SHOWN_SOURCE_FACTS_IN_CITATIONS     => $this->getPreference(self::PREF_SHOWN_SOURCE_FACTS_IN_CITATIONS, implode(',', self::$ALL_SOURCE_FACTS_IN_CITATIONS->keys()->toArray())),
                self::PREF_EXPANDED_SOURCE_FACTS_IN_CITATIONS  => $this->getPreference(self::PREF_EXPANDED_SOURCE_FACTS_IN_CITATIONS, ''),
                self::PREF_SHOW_MEDIA_AFTER_CITATIONS   	   => boolval($this->getPreference(self::PREF_SHOW_MEDIA_AFTER_CITATIONS, '0')),
                self::PREF_ENABLE_COPY_PASTE_CITATIONS   	   => boolval($this->getPreference(self::PREF_ENABLE_COPY_PASTE_CITATIONS, '0')),
                self::PREF_SHOW_FINDING_AID_CATEGORY_TITLE     => boolval($this->getPreference(self::PREF_SHOW_FINDING_AID_CATEGORY_TITLE, '0')),
                self::PREF_SHOW_FINDING_AID_ADDRESS            => boolval($this->getPreference(self::PREF_SHOW_FINDING_AID_ADDRESS, '1')),
                self::PREF_SHOW_FINDING_AID_WT_LINKS           => boolval($this->getPreference(self::PREF_SHOW_FINDING_AID_WT_LINKS, '1')),
                self::PREF_SHOW_FINDING_AID_TOC                => boolval($this->getPreference(self::PREF_SHOW_FINDING_AID_TOC, '1')),
                self::PREF_SHOW_FINDING_AID_TOC_LINKS          => boolval($this->getPreference(self::PREF_SHOW_FINDING_AID_TOC_LINKS, '1')),
                self::PREF_SHOW_FINDING_AID_TOC_TITLES         => boolval($this->getPreference(self::PREF_SHOW_FINDING_AID_TOC_TITLES, '1')),
                self::PREF_ALLOW_ADMIN_XML_SETTINGS            => boolval($this->getPreference(self::PREF_ALLOW_ADMIN_XML_SETTINGS, '1')),
                self::PREF_USE_META_REPOSITORIES               => boolval($this->getPreference(self::PREF_USE_META_REPOSITORIES, '0')),
                self::PREF_ATOM_SLUG                           => $this->getPreference(self::PREF_ATOM_SLUG, self::PREF_ATOM_SLUG_CALL_NUMBER),
                self::PREF_SHOW_ATOM_LINKS                     => boolval($this->getPreference(self::PREF_SHOW_ATOM_LINKS, '0')),
                self::PREF_ATOM_BASE_URL                       => $this->getPreference(self::PREF_ATOM_BASE_URL, ''),
                self::PREF_ATOM_REPOSITORIES                   => $this->getPreference(self::PREF_ATOM_REPOSITORIES, ''),
            ]
        );
    }  
    
    /**
     * Save module settings after returning from control panel
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function postAdminAction(ServerRequestInterface $request): ResponseInterface
    {
        $params = (array) $request->getParsedBody();

        $shown_source_facts_in_citations = Validator::parsedBody($request)->array(self::PREF_SHOWN_SOURCE_FACTS_IN_CITATIONS);
        $explanded_facts_in_citations = Validator::parsedBody($request)->array(self::PREF_EXPANDED_SOURCE_FACTS_IN_CITATIONS);

        //Save the received settings to the user preferences
        if ($params['save'] === '1') {
            $this->setPreference(self::PREF_SHOW_CATEGORY_LABEL, isset($params[self::PREF_SHOW_CATEGORY_LABEL]) ? '1' : '0');
            $this->setPreference(self::PREF_SHOW_CATEGORY_TITLE, isset($params[self::PREF_SHOW_CATEGORY_TITLE]) ? '1' : '0');
            $this->setPreference(self::PREF_SHOW_HELP_ICON, isset($params[self::PREF_SHOW_HELP_ICON]) ? '1' : '0');
            $this->setPreference(self::PREF_SHOW_HELP_LINK, isset($params[self::PREF_SHOW_HELP_LINK]) ? '1' : '0');
            $this->setPreference(self::PREF_SHOW_TRUNCATED_CALL_NUMBER, isset($params[self::PREF_SHOW_TRUNCATED_CALL_NUMBER]) ? '1' : '0');
            $this->setPreference(self::PREF_SHOW_TRUNCATED_CATEGORY, isset($params[self::PREF_SHOW_TRUNCATED_CATEGORY]) ? '1' : '0');
            $this->setPreference(self::PREF_SHOW_DATE_RANGE_FOR_CATEGORY, isset($params[self::PREF_SHOW_DATE_RANGE_FOR_CATEGORY]) ? '1' : '0');
            $this->setPreference(self::PREF_ALLOW_RENAME, isset($params[self::PREF_ALLOW_RENAME]) ? '1' : '0');
            $this->setPreference(self::PREF_ALLOW_NEW_SOURCE, isset($params[self::PREF_ALLOW_NEW_SOURCE]) ? '1' : '0');
            $this->setPreference(self::PREF_SHOW_TITLE, isset($params[self::PREF_SHOW_TITLE]) ? '1' : '0');
            $this->setPreference(self::PREF_SHOW_XREF, isset($params[self::PREF_SHOW_XREF]) ? '1' : '0');
            $this->setPreference(self::PREF_SHOW_AUTHOR, isset($params[self::PREF_SHOW_AUTHOR]) ? '1' : '0');
            $this->setPreference(self::PREF_SHOW_DATE_RANGE, isset($params[self::PREF_SHOW_DATE_RANGE]) ? '1' : '0');
            $this->setPreference(self::PREF_ALLOW_ADMIN_DELIMITER, isset($params[self::PREF_ALLOW_ADMIN_DELIMITER]) ? '1' : '0');
            $this->setPreference(self::PREF_SHOWN_SOURCE_FACTS_IN_CITATIONS, implode(',', $shown_source_facts_in_citations));
            $this->setPreference(self::PREF_EXPANDED_SOURCE_FACTS_IN_CITATIONS, implode(',', $explanded_facts_in_citations));
            $this->setPreference(self::PREF_SHOW_MEDIA_AFTER_CITATIONS, isset($params[self::PREF_SHOW_MEDIA_AFTER_CITATIONS]) ? '1' : '0');
            $this->setPreference(self::PREF_ENABLE_COPY_PASTE_CITATIONS, isset($params[self::PREF_ENABLE_COPY_PASTE_CITATIONS]) ? '1' : '0');
            $this->setPreference(self::PREF_SHOW_FINDING_AID_CATEGORY_TITLE, isset($params[self::PREF_SHOW_FINDING_AID_CATEGORY_TITLE]) ? '1' : '0');
            $this->setPreference(self::PREF_SHOW_FINDING_AID_ADDRESS, isset($params[self::PREF_SHOW_FINDING_AID_ADDRESS]) ? '1' : '0');
            $this->setPreference(self::PREF_SHOW_FINDING_AID_WT_LINKS, isset($params[self::PREF_SHOW_FINDING_AID_WT_LINKS]) ? '1' : '0');
            $this->setPreference(self::PREF_SHOW_FINDING_AID_TOC, isset($params[self::PREF_SHOW_FINDING_AID_TOC]) ? '1' : '0');
            $this->setPreference(self::PREF_SHOW_FINDING_AID_TOC_LINKS, isset($params[self::PREF_SHOW_FINDING_AID_TOC_LINKS]) ? '1' : '0');
            $this->setPreference(self::PREF_SHOW_FINDING_AID_TOC_TITLES, isset($params[self::PREF_SHOW_FINDING_AID_TOC_TITLES]) ? '1' : '0');
            $this->setPreference(self::PREF_ALLOW_ADMIN_XML_SETTINGS, isset($params[self::PREF_ALLOW_ADMIN_XML_SETTINGS]) ? '1' : '0');
            $this->setPreference(self::PREF_USE_META_REPOSITORIES, isset($params[self::PREF_USE_META_REPOSITORIES]) ? '1' : '0');
            $this->setPreference(self::PREF_SHOW_ATOM_LINKS, isset($params[self::PREF_SHOW_ATOM_LINKS]) ? '1' : '0');
            $this->setPreference(self::PREF_ATOM_SLUG, isset($params[self::PREF_ATOM_SLUG]) ? $params[self::PREF_ATOM_SLUG] : self::PREF_ATOM_SLUG_CALL_NUMBER);

            //Remove slashes at the end of the URL
            if (isset($params[self::PREF_ATOM_BASE_URL])) {
                $atom_base_url = $params[self::PREF_ATOM_BASE_URL];

                if (substr($atom_base_url, -1) === '/') {
                    $atom_base_url = substr($atom_base_url, 0, -1);
                }
            } else {
                $atom_base_url = '';
            }

            $this->setPreference(self::PREF_ATOM_BASE_URL, $atom_base_url);
            $this->setPreference(self::PREF_ATOM_REPOSITORIES, isset($params[self::PREF_ATOM_REPOSITORIES]) ? $params[self::PREF_ATOM_REPOSITORIES] : '');

            $message = I18N::translate('The preferences for the module “%s” were updated.', $this->title());
            FlashMessages::addMessage($message, 'success');
        }

        return redirect($this->getConfigLink());
    }

    /**
     * Check if module version is new and start update activities if needed
     *
     * @return void
     */
    public function checkModuleVersionUpdate(): void
    {
        //If new custom module version is detected
        if ($this->getPreference(self::PREF_MODULE_VERSION) !== self::CUSTOM_VERSION) {

            //Update prefences stored in database
            $update_result = $this->updatePreferences();

            //Show flash message for error or sucessful update of preferences
            if ($update_result !== '') {

                $message = I18N::translate('Error while trying to update the custom module "%s" to the new module version %s: ' . $update_result, $this->title(), self::CUSTOM_VERSION);
                FlashMessages::addMessage($message, 'danger');
            } 
            else {

                $message = I18N::translate('The preferences for the custom module "%s" were sucessfully updated to the new module version %s.', $this->title(), self::CUSTOM_VERSION);
                FlashMessages::addMessage($message, 'success');	    
                
                //Update custom module version
                $this->setPreference(self::PREF_MODULE_VERSION, self::CUSTOM_VERSION);
            }
        }        
    }

    /**
     * Update the preferences (after new module version is detected)
     *
     * @return string
     */
    public function updatePreferences(): string
    {       
        //Rename old preferences
        if (    $this->getPreference(self::OLD_PREF_SHOW_REPO_FACTS_IN_CITATIONS) === ''
            &&  $this->getPreference(self::OLD_PREF_SHOW_SOURCE_FACTS_IN_CITATIONS) !== self::PREF_DELETED) {

            //Copy old value to new preference
            $this->setPreference(self::OLD_PREF_SHOW_REPO_FACTS_IN_CITATIONS, $this->getPreference(self::OLD_PREF_SHOW_SOURCE_FACTS_IN_CITATIONS));
        } 
        $this->setPreference(self::OLD_PREF_SHOW_SOURCE_FACTS_IN_CITATIONS, self::PREF_DELETED);

        //Move old preferences for source facts to new preferences
        if (    $this->getPreference(self::OLD_PREF_SHOW_FURTHER_FACTS_IN_CITATIONS) !== self::PREF_DELETED
            &&  boolval($this->getPreference(self::OLD_PREF_SHOW_FURTHER_FACTS_IN_CITATIONS, '0'))) {

            $this->setPreference(self::PREF_SHOWN_SOURCE_FACTS_IN_CITATIONS, implode(',', self::$ALL_SOURCE_FACTS_IN_CITATIONS->keys()->toArray()));
            $this->setPreference(self::OLD_PREF_SHOW_FURTHER_FACTS_IN_CITATIONS, self::PREF_DELETED);
        }

        $old_facts_in_citations_preferences = [
            [self::OLD_PREF_SHOW_REPO_FACTS_IN_CITATIONS,   'REPO', false],
            [self::OLD_PREF_SHOW_SOURCE_MEDIA_IN_CITATIONS, 'OBJE', false],
            [self::OLD_PREF_EXPAND_REPOS_IN_CITATIONS,      'REPO', true],
        ];

        foreach($old_facts_in_citations_preferences as $update) {
            $this->updateShownFactsInCitationsPrerences($update[0], $update[1], $update[2]);
        }
        
        //Delete old preferences, i.e. set old preference value to deleted
        $this->setPreference(self::OLD_PREF_SHOW_SOURCE_FACTS_IN_CITATIONS, self::PREF_DELETED);

        $error = '';
        return $error;
    }

    /**
     * Update preferences to show facts in citations
     *
     * @return void
     */
    public function updateShownFactsInCitationsPrerences(string $old_preference, string $gedcom_tag, $expanded = false) : void {

        //Do nothing if old preference was not activated or has already been deleted
        if ($this->getPreference($old_preference, '') === self::PREF_DELETED) return;

        //If old preference was not activated, delete it and return
        if (!boolval($this->getPreference($old_preference, '0'))) {
            $this->setPreference($old_preference, self::PREF_DELETED);
            return;
        }

        $new_preference = $expanded ? self::PREF_EXPANDED_SOURCE_FACTS_IN_CITATIONS : self::PREF_SHOWN_SOURCE_FACTS_IN_CITATIONS;
        $preference_value =$this->getPreference($new_preference, '');

        if (!str_contains($preference_value, $gedcom_tag)) {
            if ($preference_value === '') {
                $preference_value = $gedcom_tag;
            }
            else {
                $preference_value .= ',' . $gedcom_tag;
            }
        }

        //Copy value to new preference
        $this->setPreference($new_preference, $preference_value);

        //Delete old preferences, i.e. set old preference value to deleted
        $this->setPreference($old_preference, self::PREF_DELETED);

        return;
    }


    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleListInterface::listMenuClass()
     */
    public function listMenuClass(): string
    {
        //CSS class for module Icon (included in CSS file) is returned to be shown in the list menu
        return 'menu-list-repository-hierarchy';
    }

    /**
     * {@inheritDoc}
     *
     * @param Tree $tree
     *
     * @return bool
     *
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
     *
     * @param Tree  $tree
     * @param array $parameters
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleListInterface::listUrl()
     */

    public function listUrl(Tree $tree, array $parameters = []): string
    {
        $parameters['tree']                  = $tree->name();
        $parameters['delimiter_expression']  = '';

        return route(RepositoryHierarchy::class, $parameters);
    }

    /**
     * {@inheritDoc}
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleGlobalInterface::headContent()
     */
    public function headContent(): string
    {
        //Include CSS file in head of webtrees HTML to make sure it is always found
        return '<link href="' . $this->assetUrl('css/repository-hierarchy.css') . '" type="text/css" rel="stylesheet" />';
    }

    /**
     * {@inheritDoc}
     *
     * @param Tree $tree
     *
     * @return string
     *
     * @see \Fisharebest\Webtrees\Module\ModuleDataFixInterface::fixOptions()
     */
    public function fixOptions(Tree $tree): string
    {
        //If data fix is called from wrong context, show error text
        if (!isset($this->repository_xref)) {
            $error_text =   I18N::translate('The Repository Hierarchy data fix cannot be used in the "control panel".') . ' '.
                            I18N::translate('The data fix can be called from the user front end by clicking on the link to rename a call number category.');

            return view(
                self::viewsNamespace() . '::error',
                [				
				'title' => I18N::translate('Error in custom module') . ': ' . $this->getListTitle(),
                'text' => $error_text,
                ]
            );
        }

        //If user is not a manager for this tree, show error text
        if (!Auth::isManager($tree)) {
            $error_text =   I18N::translate('Currently, you do not have the user rights to change call number categories.') . ' ' .
                            I18N::translate('In order to change call number categories, you need to have a "Manager" role for the corresponding tree.');

            return view(
                self::viewsNamespace() . '::error',
                [
				'title' => I18N::translate('Error in custom module') . ': ' . $this->getListTitle(),
                'text' => $error_text,
                ]
            );
        }

        return view(
            self::viewsNamespace() . '::options',
            [
            CallNumberCategory::VAR_REPOSITORY_XREF     => $this->repository_xref,
            CallNumberCategory::VAR_CATEGORY_FULL_NAME  => $this->data_fix_category_full_name,
            CallNumberCategory::VAR_CATEGORY_NAME       => $this->data_fix_category_name,
            self::VAR_DATA_FIX_CATEGORY_NAME_REPLACE    => $this->data_fix_category_name,
            self::VAR_DATA_FIX_TYPES                    => [Source::RECORD_TYPE => MoreI18N::xlate('Sources')],
            ]
        );
    }

    /**
     * {@inheritDoc}
     *
     * @param GedcomRecord $record
     * @param array        $params
     *
     * @return bool
     *
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
     *
     * @param GedcomRecord $record
     * @param array        $params
     *
     * @return string
     *
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
     *
     * @param GedcomRecord $record
     * @param array        $params
     *
     * @return void
     *
     * @see \Fisharebest\Webtrees\Module\ModuleDataFixInterface::updateRecord()
     */
    public function updateRecord(GedcomRecord $record, array $params): void
    {
        $record->updateRecord($this->updateGedcom($record, $params), false);
    }

    /**
     * Check availability of the registered custom views and show flash messages with warnings if any errors occur 
     *
     * @return void
     */
    private function checkCustomViewAvailability() : void {

        $module_service = new ModuleService();
        $custom_modules = $module_service->findByInterface(ModuleCustomInterface::class);
        $alternative_view_found = false;

        foreach($this->custom_view_list as $custom_view) {

            [[$namespace], $view_name] = explode(View::NAMESPACE_SEPARATOR, $custom_view, 2);

            foreach($custom_modules->forget($this->activeModuleName()) as $custom_module) {

                $view = new View('test');

                try {
                    $file_name = $view->getFilenameForView($custom_module->name() . View::NAMESPACE_SEPARATOR . $view_name);
                    $alternative_view_found = true;
    
                    //If a view of one of the custom modules is found, which are known to use the same view
                    if (in_array($custom_module->name(), ['_jc-simple-media-display_', '_webtrees-simple-media-display_'])) {
                        
                        $message =  '<b>' . MoreI18N::xlate('Warning') . ':</b><br>' .
                                    I18N::translate('The custom module "%s" is activated in parallel to the %s custom module. This can lead to unintended behavior. If using the %s module, it is strongly recommended to deactivate the "%s" module, because the identical functionality is also integrated in the %s module.', 
                                    '<b>' . $custom_module->title() . '</b>', $this->title(), $this->title(), $custom_module->title(), $this->title());
                    }
                    else {
                        $message =  '<b>' . MoreI18N::xlate('Warning') . ':</b><br>' . 
                                    I18N::translate('The custom module "%s" is activated in parallel to the %s custom module. This can lead to unintended behavior, because both of the modules have registered the same custom view "%s". It is strongly recommended to deactivate one of the modules.', 
                                    '<b>' . $custom_module->title() . '</b>', $this->title(),  '<b>' . $view_name . '</b>');
                    }
                    FlashMessages::addMessage($message, 'danger');
                }    
                catch (RuntimeException $e) {
                    //If no file name (i.e. view) was found, do nothing
                }
            }
            if (!$alternative_view_found) {

                $view = new View('test');

                try {
                    $file_name = $view->getFilenameForView($view_name);

                    //Check if the view is registered with a file path other than the current module; e.g. another moduleS probably registered it with an unknown views namespace
                    if (!str_contains($file_name, $this->resourcesFolder())) {
                        throw new RuntimeException;
                    }
                }
                catch (RuntimeException $e) {
                    $message =  '<b>' . I18N::translate('Error') . ':</b><br>' .
                                I18N::translate(
                                    'The custom module view "%s" is not registered as replacement for the standard webtrees view. There might be another module installed, which registered the same custom view. This can lead to unintended behavior. It is strongly recommended to deactivate one of the modules. The path of the parallel view is: %s',
                                    '<b>' . $custom_view . '</b>', '<b>' . $file_name  . '</b>');
                    FlashMessages::addMessage($message, 'danger');
                }
            }
        }
        
        return;
    }   

    /**
     * Update Gedcom for a record
     *
     * @param GedcomRecord $record
     * @param array        $params
     *
     * @return string
     */
    private function updateGedcom(GedcomRecord $record, array $params): string
    {
        $repository_xref = $params[CallNumberCategory::VAR_REPOSITORY_XREF];
        $pos = strpos($params[CallNumberCategory::VAR_CATEGORY_FULL_NAME], $params[CallNumberCategory::VAR_CATEGORY_NAME]);
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
     * @param Repository $repository
     *
     * @return string
     */
    public function getListTitle(Repository $repository = null): string
    {
        //In this module, repositories are listed
        if ($repository === null) {
            return I18N::translate('Repository Hierarchy');
        } else {
            return I18N::translate('Repository Hierarchy of: %s', $repository->fullName());
        }
    }

    /**
     * Get related tree
     *
     * @return Tree     $tree;
     */
    public function getTree(): Tree
    {
        return $this->tree;
    }

    /**
     * Get repository
     *
     * @return Repository
     */
    public function getRepository(): Repository
    {
        return $this->repository;
    }

    /**
     * Get all repositories (including meta repository)
     *
     * @return array
     */
    public function getAllRepositories(): array
    {
        $repositories = [$this->repository];

        if (isset($this->meta_repository)) {
            array_push($repositories, $this->meta_repository);
        }

        return $repositories;
    }

    /**
     * Get xref of the related repository
     *
     * @return string
     */
    public function getRepositoryXref(): string
    {
        return $this->repository_xref;
    }

    /**
     * Get root category
     *
     * @return CallNumberCategory
     */
    public function getRootCategory(): CallNumberCategory
    {
        return $this->root_category;
    }

    /**
     * Get stored repository for a user
     *
     * @param Tree          $tree
     * @param UserInterface $user
     *
     * @return string
     */
    public function getStoredRepositoryXref(Tree $tree, UserInterface $user): string
    {
        return $this->getPreference(self::PREF_REPOSITORY . $tree->id() . '_' . $user->id(), '');
    }

    /**
     * Get call number category titles service
     *
     * @return C16Y
     */
    public function getCallNumberCategoryTitleService(): C16Y
    {
        return $this->call_number_category_title_service;
    }

    /**
     * Get call number category titles .po file path
     *
     * @param Tree   $tree
     * @param string $xref
     *
     * @return string
     */
    public function getCallNumberCategoryTitlesPoFilePath(): string
    {
        return $this->call_number_category_titles_po_file_path;
    }

    /**
     * Sorting sources by call number
     *
     * @param Collection $sources
     * @param Repository $repository
     *
     * @return Collection
     */
    public function sortSourcesByCallNumber(Collection $sources): Collection
    {
        return $sources->sort(
            function (Source $source1, Source $source2) {
                return strnatcmp($this->call_number_of_source[$source1->xref()], $this->call_number_of_source[$source2->xref()]);
            }
        );
    }

    /**
     * Create source data tables
     *
     * @param Collection $sources
     * @param Repository $repository
     * @param Repository $meta_repository
     *
     * @return void
     */
    private function createDataTablesForSources(Collection $sources, Repository $repository, Repository $meta_repository = null): void
    {
        if ($meta_repository === null) {
            $meta_repository = $repository;
        }

        foreach ($sources as $source) {

            $call_number_meta_repository = '';
            $call_number_repository = '';
            $dates = [];
            $dates_found = 0;

            foreach ($source->facts() as $fact) {

                switch($fact->tag()) {

                    case 'SOUR:AUTH':
                        $this->author_of_source[$source->xref()] = $fact->value();

                    case 'SOUR:TITL':
                        $this->title_of_source[$source->xref()] = $fact->value();

                    case 'SOUR:REPO':
                        if ($fact->value() === '@'. $meta_repository->xref() . '@') {
                            $call_number_meta_repository = $fact->attribute('CALN');
                        }

                        if ($fact->value() === '@'. $repository->xref() . '@') {
                            $call_number_repository = $fact->attribute('CALN');
                        }

                    case 'SOUR:DATA':

                        preg_match_all('/3 DATE (.{1,35})/', $fact->gedcom(), $matches, PREG_PATTERN_ORDER);
                
                        foreach ($matches[1] as $match) {
                            array_push($dates, new Date($match));
                            $dates_found++;
                        }
                }
            }

            //If dates were found, calculate date range and save values
            if ($dates_found !== 0) {

                $date_range = Functions::getOverallDateRange($dates);
                $this->date_range_of_source[$source->xref()] = $date_range;
                $this->date_range_text_of_source[$source->xref()] = Functions::displayDateRange($date_range);
                $this->iso_date_range_text_of_source[$source->xref()] = Functions::displayISOformatForDateRange($date_range);    
            } 

            //If call number of meta repository was found, take it. Otherwise take call number of repository
            if ($call_number_meta_repository !== '') {
                $this->call_number_of_source[$source->xref()] = $call_number_meta_repository;
            }
            elseif ($call_number_repository !== '') {
                $this->call_number_of_source[$source->xref()] = $call_number_repository;
            }
            else {
                $this->call_number_of_source[$source->xref()] = '';
            }               
        }

        return;
    }

    /**
     * Error text with a header
     *
     * @param string $error_text
     * @param bool   $show_module_name
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
     * @param Tree   $tree
     * @param string $xref
     * @param string $category_name
     * @param string $category_full_name
     *
     * @return void
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
     * @param string $delimiter_expression
     *
     * @return array [found reg exps , errorlist]
     */
    public function parseDelimiterExpression(string $delimiter_expression): array
    {
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
            if ((@preg_match('/' . $match . '/', '') === false) or
                ($delimiter_expression == '$') or
                ($delimiter_expression == '.')
            ) {
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
     * @param array  $delimiter_reg_exps
     *
     * @return bool
     */
    public function regExpFoundInCallNumber(string $call_number, array $delimiter_reg_exps): bool
    {
        foreach ($delimiter_reg_exps as $delimiter_reg_exp) {
            //Try to find regular expression provided in delimiter
            preg_match_all('/' . $delimiter_reg_exp . '/', $call_number, $matches, PREG_SET_ORDER);

            if (!empty($matches)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add a source to a call number category (usually a hierarchy of call number categories)
     *
     * @param CallNumberCategory $category
     * @param Source             $source
     * @param string             $call_number_chunk
     *
     * @return void
     */
    public function addSourceToCallNumberCategory(CallNumberCategory $category, Source $source, string $call_number_chunk)
    {
        $delimiter_reg_exps = $category->getDelimiterRegExps();
        $found = false;

        //If call number chunk contains default delimiter, use default delimiter in delimiter reg exps
        if (strpos($call_number_chunk, self::DELIMITER_ATTRIBUTE_DEFAULT)) {
            $delimiter_reg_exps = [self::DELIMITER_ATTRIBUTE_DEFAULT];
        }

        foreach ($delimiter_reg_exps as $delimiter_reg_exp) {
            //Try to find delimiter reg exp in the call number chunk
            preg_match_all('/' . $delimiter_reg_exp . '/', $call_number_chunk, $matches, PREG_OFFSET_CAPTURE);

            if (!empty($matches[0])) {
                if (empty($matches[1])) {
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
            $right  = substr($call_number_chunk, -($length - (int) $pos_end), $length - (int) $pos_end);

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
                $sub_category = new CallNumberCategory(
                    $category->getTree(),
                    $delimiter_reg_exps,
                    false,
                    $left . $matched_part,
                    $category->getFullName() . $left . $matched_part,
                    $category->getHierarchyLevel() + 1,
                    []
                );
                $category->addSubCategory($sub_category);
            }

            //recursion with the rest of the call_number_chunk
            $this->addSourceToCallNumberCategory($sub_category, $source, $right);
        }

        //if expression for delimiter not found in call_number_chunk, add source to category
        else {
            $category->addSource($source);
            $category->addTruncatedCallNumber($source, $call_number_chunk);
            $this->truncated_call_number_of_source[$source->xref()] = $call_number_chunk;
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
     * A middleware method to identify, whether an individual or Family page is shown
     *
     * @param ServerRequestInterface  $request
     * @param RequestHandlerInterface $handler
     *
     * @return ResponseInterface
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = Validator::attributes($request)->route();

        switch ($route->name) {

            case IndividualPage::class:
                $tree  = Validator::attributes($request)->treeOptional();
                $xref = Validator::attributes($request)->isXref()->string('xref');
                Session::put($this->name() . self::LAST_PAGE_NAME, self::PAGE_NAME_INDIVIDUAL);
                Session::put($this->name() . self::LAST_PAGE_TREE, $tree->name());
                Session::put($this->name() . self::LAST_PAGE_PARAMETER, $xref);
                break;

            case FamilyPage::class:
                $tree  = Validator::attributes($request)->treeOptional();
                $xref = Validator::attributes($request)->isXref()->string('xref');
                Session::put($this->name() . self::LAST_PAGE_NAME, self::PAGE_NAME_FAMILY);
                Session::put($this->name() . self::LAST_PAGE_TREE, $tree->name());
                Session::put($this->name() . self::LAST_PAGE_PARAMETER, $xref);
                break;

            case MediaPage::class:
            case NotePage::class:
            case RepositoryPage::class:
            case SourcePage::class:
            case SubmitterPage::class:
                $tree  = Validator::attributes($request)->treeOptional();
                Session::put($this->name() . self::LAST_PAGE_NAME, self::PAGE_NAME_OTHER);                
                Session::put($this->name() . self::LAST_PAGE_TREE, $tree->name());
                Session::put($this->name() . self::LAST_PAGE_PARAMETER, '');
                break;
            }

        return $handler->handle($request);
    }

    /**
     * The major handle to view the module
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree                      = Validator::attributes($request)->tree();
        $user                      = Validator::attributes($request)->user();
        $xref                      = Validator::attributes($request)->string('xref');
        $command                   = Validator::attributes($request)->string('command');

        try {
            $delimiter_expression = Validator::parsedBody($request)->string('delimiter_expression');
        }
        catch (Exception $ex) {
            $delimiter_expression = Validator::queryParams($request)->string('delimiter_expression');
        }

        // Convert POST requests into GET requests for pretty URLs.
        if ($request->getMethod() === RequestMethodInterface::METHOD_POST) {

            if ($command === self::CMD_DOWNLOAD) {

                $command = Validator::parsedBody($request)->string('download_command');
            }
            else {
                $command = Validator::parsedBody($request)->string('command');
                $xref    = Validator::parsedBody($request)->isXref()->string('xref');
            }

            return redirect(
                route(
                    self::class,
                    [
                        'tree'                  => $tree->name(),
                        'xref'                  => $xref,
                        'delimiter_expression'  => $delimiter_expression,
                        'command'               => $command,
                    ]
                )
            );
        }
    
        //Variable for error texts; default is empty
        $error_text = '';

        //If requested, load stored repository and reset delimiter
        if ($command === self::CMD_LOAD_REPO) {
            $load_value = $this->getPreference(self::PREF_REPOSITORY . $tree->id() . '_' . $user->id());
            if ($load_value != '') {
                $xref = $load_value;
                $delimiter_expression = '';
            } else {
                $error_text = $this->errorTextWithHeader(I18N::translate('Could not load repository. No repository stored.'));
            }
        }

        //Validate xref
        if (($xref === self::XREF_ATTRIBUTE_DEFAULT) or ($xref === '')) {
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
            return $this->viewResponse(
                self::viewsNamespace() . '::error',
                [
					'title' => I18N::translate('Error in custom module') . ': ' . $this->getListTitle(),
                    'text'  => I18N::translate('The tree “%s” does not contain any repository', $tree->name()),
                ]
            );
        }

        //Create and check repository from xref
        Auth::checkComponentAccess($this, ModuleInterface::class, $tree, $user);
        $repository  = Registry::repositoryFactory()->make($xref, $tree);
        $repository  = Auth::checkRepositoryAccess($repository, false);

        //Copy values to this instance
        $this->tree = $tree;
        $this->repository_xref = $xref;
        $this->repository = $repository;

        //Check for meta repository. If available generate meta repository, check access, and copy to instance
        if (boolval($this->getPreference(self::PREF_USE_META_REPOSITORIES, '0'))) {
            $meta_xref = Functions::getMetaRepository($repository);

            if ($meta_xref !== '') {
                $meta_repository  = Registry::repositoryFactory()->make($meta_xref, $tree);
                $meta_repository  = Auth::checkRepositoryAccess($meta_repository, false);

                $this->meta_repository_xref = $meta_xref;
                $this->meta_repository = $meta_repository;
            }
        }

        //Create call mumber category title service
        $this->call_number_category_title_service = new C16Y($this->call_number_category_titles_po_file_path, $tree->name(), $this->repository);

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
        if (($delimiter_expression === '') or ($delimiter_expression === self::DELIMITER_ATTRIBUTE_DEFAULT)) {
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
            } elseif ($command === self::CMD_SAVE_DELIM) {
                $this->setPreference(self::PREF_DELIMITER . $tree->id() . '_' . $user->id() . '_' . $xref, $delimiter_expression);

                //If user is admin, store same preference a second time with an admin string as user
                if (Auth::isManager($tree, $user)) {
                    $this->setPreference(self::PREF_DELIMITER . $tree->id() . '_' . self::ADMIN_USER_STRING . '_' . $xref, $delimiter_expression);
                }
            }

            //Find all sources linked to the repository
            $linked_record_service = new LinkedRecordService();
            $linked_sources = $linked_record_service->linkedSources($repository);

            //Add linked sources of meta repository (if available)
            if (isset($this->meta_repository)) {
                $linked_sources = $linked_sources->merge($linked_record_service->linkedSources($this->meta_repository));
            }

            //Create data tables for sources
            if (isset ($this->meta_repository)) {
                $this->createDataTablesForSources($linked_sources, $this->repository, $this->meta_repository);
            }
            else {
                $this->createDataTablesForSources($linked_sources, $this->repository);
            }

            //Sort linked sources
            $linked_sources = $this->sortSourcesByCallNumber($linked_sources);

            //Generate root category
            $this->root_category = new CallNumberCategory($tree, $delimiter_reg_exps, true);

            //Generate the (recursive) hierarchy of call numbers
            foreach ($linked_sources as $source) {
                $call_number = $this->call_number_of_source[$source->xref()];

                //If call number is empty, assign empty category and default delimiter
                if ($call_number === '') {
                    $call_number = CallNumberCategory::EMPTY_CATEGORY_NAME . self::DELIMITER_ATTRIBUTE_DEFAULT;
                }

                //If call number does not match reg exp, assign default category to call number
                elseif (!$this->regExpFoundInCallNumber($call_number, $delimiter_reg_exps)) {
                    $call_number = CallNumberCategory::DEFAULT_CATEGORY_NAME . self::DELIMITER_ATTRIBUTE_DEFAULT . $call_number;
                }

                $this->addSourceToCallNumberCategory($this->root_category, $source, $call_number);
            }
        } else {
            $this->root_category = new CallNumberCategory($tree, array());
        }

        //Calculate date ranges for the whole hierarchy of call number categories
        $date_range = $this->getRootCategory()->calculateDateRange($this->date_range_of_source);

        //If download of EAD XML is requested, create and return download
        if (DownloadService::isXmlDownloadCommand($command)) {
            $xml_type = $command;
            $title = $this->getPreference(RepositoryHierarchy::PREF_FINDING_AID_TITLE . $tree->id() . '_' . $xref . '_' . $user->id(), '');

            if ($title === '') {
                $error_text = $this->errorTextWithHeader('<b>'. I18N::translate('XML export settings not found. Please open EAD XML settings and provide settings.') . '</b>' . '<p>');
            } else {

                //Update EAD XML settings
                XmlExportSettingsModal::updatePreferenes($tree, $xref, $user->id(), $delimiter_expression);

                //Initialize EAD XML
                $download_ead_xml_service = new DownloadEADxmlService($xml_type, $this, $this->root_category, $user);

                //Create EAD XML export
                $download_ead_xml_service->createXMLforCategory($xml_type, $download_ead_xml_service->getCollection(), $this->root_category);

                //Start download
                return $download_ead_xml_service->downloadResponse('EAD');
            }
        }

        //If download of HTML finding aid is requested, create and return download
        if ($command === DownloadService::DOWNLOAD_OPTION_HTML) {
            $title = I18N::translate('Finding aid');

            //Create finding aid and download
            $download_finding_aid_service = new DownloadFindingAidService($this, $user);
            return $download_finding_aid_service->downloadHtmlResponse('finding_aid');
        }

        //If download of PDF finding aid is requested, create and return download
        if ($command === DownloadService::DOWNLOAD_OPTION_PDF) {
            $title = I18N::translate('Finding aid');

            //Create finding aid and download
            $download_finding_aid_service = new DownloadFindingAidService($this, $user);
            return $download_finding_aid_service->downloadPDFResponse('finding_aid');
        }

        //Create file for call number category titles
        CallNumberCategory::saveC16YFile($this->call_number_category_titles_po_file_path, $tree->name(), $this->repository->xref(), $this->root_category);


        //Return the page view
        return $this->viewResponse(
            self::viewsNamespace() . '::page',
            [
                'tree'                              => $tree,
                'title'                             => $this->getListTitle($repository),
                'repository_hierarchy'              => $this,
                'delimiter_expression'              => $delimiter_expression,
                'error'                             => $error_text,
                'command'                           => self::CMD_NONE,
            ]
        );
    }
}
