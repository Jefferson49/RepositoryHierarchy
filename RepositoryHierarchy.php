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

use Fig\Http\Message\RequestMethodInterface;
use Fisharebest\Localization\Translation;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\Date\AbstractCalendarDate;
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
	

class RepositoryHierarchy
{

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

    //A service to download EAD XML
    private DownloadEADxmlService $download_ead_xml_service;

    /**
     * Constructor
     */
    public function __construct()
    {
        //Create data fix service
        $this->data_fix_service = new DataFixService;
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
     * get AtoM slug
     * 
     * @param string
     *
     * @return string
     */
    public static function getAtoMSlug(string $text): string
    {
		return strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $text));
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
			$category->addDateRange(self::getDateRangeForSource($source));
            $category->addTruncatedCallNumber($source, $call_number_chunk);
		}						
	}

    /**
     * Sorting sources by call number
     *
	 * @param Collection $sources
     *
     * @return Collection
     */
    public function sortSourcesByCallNumber(Collection $sources): Collection {
		
        return $sources->sortBy(function (Source $source) {
            return $this->getCallNumber($source);
        });
    }

    /**
     * Get call number for a source
     *
	 * @param Source
	 * @param CallNumberCategory
	 * @param bool $truncated
     *
     * @return string
     */
    public function getCallNumber(Source $source, CallNumberCategory $category = null, bool $truncated = false): string{	
	
        $call_number = '';

        foreach($source->facts(['REPO']) as $repository) {

            preg_match_all('/1 REPO @(.*)@/', $repository->gedcom(), $matches, PREG_SET_ORDER);
                    
            if (!empty($matches[0]) ) {
                $match = $matches[0];
                $xref = $match[1];
            }
            else $xref = '';

            //only if it is the requested repository
            if ($xref === $this->repository_xref) {

                preg_match_all('/\n2 CALN (.*)/', $repository->gedcom(), $matches, PREG_SET_ORDER);
                
                if (!empty($matches[0]) ) {
                    $match = $matches[0];
                    $call_number = $match[1];
                }

                break;
            }
        }    

        //If activated, take truncated call number
        if ($truncated) {
            $call_number = $category->getTruncatedCallNumber($source);
        }

        return $call_number;
	}

    /**
     * Get the date range for a source
     *
	 * @param Source
     *
     * @return Date
     */
    public static function getDateRangeForSource(Source $source): ?Date {	
			
        $dates = [];
        $dates_found = 0;

        if ($source->facts(['DATA'])->isNotEmpty() ) {

            foreach($source->facts(['DATA']) as $data) {

                preg_match_all('/3 DATE (.{1,32})/', $data->gedcom(), $matches, PREG_PATTERN_ORDER);
                
                foreach($matches[1] as $match) {
                    array_push($dates, new Date($match));
                    $dates_found++;
                }       
            }
        }

        $date_range = RepositoryHierarchy::getOverallDateRange($dates);

        return ($dates_found > 0) ? $date_range : null;
    }

    /**
     * Display the date range for a source
     *
	 * @param Source
     * @param string  date format
     *
     * @return string
     */
    public static function displayDateRangeForSource(Source $source, Tree $tree = null, string $date_format = null): string {	
	
        $date_range = self::getDateRangeForSource($source);

        if(($date_range !== null) && $date_range->isOK()) {
            return $date_range->display($tree, $date_format);
        } else {
            return '';
        }
    }

    /**
     * Get overall date range for a set of date ranges, i.e. minimum and maximum dates of all the date ranges
     *
	 * @param array   [Date]
     *
     * @return Date
     */
    public static function getOverallDateRange(array $dates): ?Date {	

        $dates_found = 0;

        foreach($dates as $date) {

            $dates_found++;

            //Calclulate new max/min values for date range if more than one date is found
            if ($dates_found > 1) {

                if(AbstractCalendarDate::compare($date->minimumDate(), $date_range->minimumDate()) < 1) {
                    $min_date = $date->minimumDate();
                } else {
                    $min_date = $date_range->minimumDate();
                }
                if(AbstractCalendarDate::compare($date->maximumDate(), $date_range->maximumDate()) > 0) {
                    $max_date = $date->maximumDate();
                } else {
                    $max_date = $date_range->maximumDate();
                }

                $date_range = new Date('FROM ' . $min_date->format('%A %O %E') . ' TO ' . $max_date->format('%A %O %E') );

            } else {
                $date_range = $date;
            }
        }    

        return ($dates_found > 0) ? $date_range : null; 
    }
}