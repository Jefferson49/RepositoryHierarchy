<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2022 webtrees development team
 *					  <http://webtrees.net>
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
use DOMAttr;
use DOMDocument;
use DOMImplementation;
use DOMNode;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Repository;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Source;
use Matriphe\ISO639\ISO639;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function date;

/**
 * Download Service for EAD XML files
 */
class DownloadEADxmlService extends DownloadService
{
    //Routes to webtrees views
    public const WEBTREES_ROUTE_TO_TREE = '/index.php?route=%2Fwebtrees%2Ftree%2F';
    public const WEBTREES_TREE_TO_SOURCE = '%2Fsource%2F';
    public const WEBTREES_TREE_TO_REPO = '%2Frepository%2F';

    //The xml object for EAD XML export
    private DOMDocument $ead_xml;

    //The top level collection within the xml
    private DOMNode $collection;

    //The ResponseFactory used
    private ResponseFactoryInterface $response_factory;

    //The StreamFactory used
    private StreamFactoryInterface $stream_factory;

    //The LinkedRecordService used
    private LinkedRecordService $linked_record_service;

    //The repository, to which the service relates
    private Repository $repository;

    //The user, for which the service is executed
    private UserInterface $user;

    //The ISO ISO-639-2b language tag related to the webtrees session
    private string $ISO_639_2b_language_tag;


    /**
     * Constructor
     * 
     * @param string            	$xml_type 
     * @param Repository            $repository    
     * @param CallNumberCategory    $root_category
     * @param UserInterface         $user
     * 
     */
    public function __construct(string $xml_type, 
                                Repository $repository, 
                                CallNumberCategory $root_category,
                                UserInterface $user)
    {
        //Initialize variables
        $this->repository = $repository;
        $this->user = $user;

        //Set language
        $iso_table = new ISO639;
        $language = $iso_table->languageByCode1(Session::get('language'));
        $this->ISO_639_2b_language_tag = $iso_table->code2bByLanguage($language);

        //Create DOM document
        $dom_implementation = new DOMImplementation();
 
        //Include DTD
        $dtd = $dom_implementation->createDocumentType('ead',
        '+//ISBN 1-931666-00-8//DTD ead.dtd (Encoded Archival Description (EAD) Version 2002)//EN',
        'http://lcweb2.loc.gov/xmlcommon/dtds/ead2002/ead.dtd');    
        $this->ead_xml = $dom_implementation->createDocument('', '', $dtd);

        //Set encoding
        $this->ead_xml->encoding="UTF-8";

        //Settings for a nice xml format
        $this->ead_xml->preserveWhiteSpace = false;
        $this->ead_xml->formatOutput = true;

        //Initialize EAD xml
        $ead_dom = $this->ead_xml->appendChild($this->ead_xml->createElement('ead')); 
            $ead_dom->appendChild(new DOMAttr('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance'));
            $ead_dom->appendChild(new DOMAttr('xmlns', 'urn:isbn:1-931666-22-9'));            
            $ead_dom->appendChild(new DOMAttr('xmlns:xlink', 'http://www.w3.org/1999/xlink'));            
            $ead_dom->appendChild(new DOMAttr('xsi:schemaLocation', 'urn:isbn:1-931666-22-9 http://www.loc.gov/ead/ead.xsd http://www.w3.org/1999/xlink http://www.loc.gov/standards/xlink/xlink.xsd'));            
            $ead_dom->appendChild(new DOMAttr('audience', 'external'));     

        //Create header, archive, and top level collection
        $this->addHeader($xml_type, $ead_dom);
        $archive_dom = $this->addArchive($xml_type,$ead_dom, $root_category);  
        $dsc_dom = $archive_dom->appendChild($this->ead_xml->createElement('dsc'));
        $this->collection = $this->addCollection($xml_type, $dsc_dom, $root_category);
    }

    /**
     * Get collection
     *
     * @return DOMNode
     */
    public function getCollection(): DOMNode {
        return $this->collection;
    }     

    /**
     * Create XML for a hierarchy of call numbers
     * 
     * @param CallNumberCategory  $call_number_category
     */
    public function createXMLforCategory(string $xml_type, DOMNode $dom, CallNumberCategory $call_number_category)
    {
        $categories = $call_number_category->getSubCategories();

        foreach ($categories as $category) {

            //Add node to xml structure
            $series_dom = $this->addSeries($xml_type, $dom, $category);

            //Add sources to xml structure
            foreach ($category->getSources() as $source) {
                $this->addItem($xml_type, $series_dom, $source, $this->repository);
            }

            //Call recursion for sub categories
            $this->createXMLforCategory($xml_type, $series_dom, $category);
        }
    }

    /**
     * Add the header to EAD XML
     * 
     * @param DOMNode       $dom
     * 
     * @return DOMNode      
     */
    private function addHeader(string $xml_type, DOMNode $dom): DOMNode
    {         
        $module_service = new ModuleService();
        $repository_hierarchy = $module_service->findByName(RepositoryHierarchy::MODULE_NAME);
        $user_id = $this->user->id();

        //<eadheader>
        $header_dom = $dom->appendChild($this->ead_xml->createElement('eadheader'));
            $header_dom->appendChild(new DOMAttr('countryencoding', 'iso3166-1'));
            $header_dom->appendChild(new DOMAttr('dateencoding', 'iso8601'));            
            $header_dom->appendChild(new DOMAttr('langencoding', 'iso639-2b'));            
            $header_dom->appendChild(new DOMAttr('repositoryencoding', 'iso15511'));            
            $header_dom->appendChild(new DOMAttr('scriptencoding', 'iso15924'));            

            //<eadid>
            $eadid_dom = $header_dom->appendChild($this->ead_xml->createElement('eadid', 
                $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_FINDING_AID_TITLE . $this->repository->tree()->id() . '_' . $this->repository->xref() . '_' . $user_id, '')));
                
                $pref = $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_MAIN_AGENCY_CODE . $this->repository->tree()->id() . '_' . $this->repository->xref() . '_' . $user_id, '');
                if($pref !== '') {
                    $eadid_dom->appendChild(new DOMAttr('mainagencycode', $pref));
                }
                $pref = $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_FINDING_AID_IDENTIFIER . $this->repository->tree()->id() . '_' . $this->repository->xref() . '_' . $user_id, '');
                if($pref !== '') {
                    $eadid_dom->appendChild(new DOMAttr('identifier', $pref));
                }
                $pref = $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_COUNTRY_CODE . $this->repository->tree()->id() . '_' . $this->repository->xref() . '_' . $user_id, '');
                if($pref !== '') {
                    $eadid_dom->appendChild(new DOMAttr('countrycode', $pref));
                }
                $pref = $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_FINDING_AID_URL . $this->repository->tree()->id() . '_' . $this->repository->xref() . '_' . $user_id, '');
                if($pref !== '') {
                    $eadid_dom->appendChild(new DOMAttr('url', $pref));
                }

            //<filedesc>
            $filedesc_dom = $header_dom->appendChild($this->ead_xml->createElement('filedesc'));

                //<titlestmt>
                $titlestmt_dom = $filedesc_dom->appendChild($this->ead_xml->createElement('titlestmt'));

                    //<titleproper>
                    $titleproper_dom = $titlestmt_dom->appendChild($this->ead_xml->createElement('titleproper',
                        $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_FINDING_AID_TITLE . $this->repository->tree()->id() . '_' . $this->repository->xref() . '_' . $user_id, '')));
                        $titleproper_dom->appendChild(new DOMAttr('encodinganalog', '245'));

                //<publicationstmt>
                $publicationstmt_dom = $filedesc_dom->appendChild($this->ead_xml->createElement('publicationstmt'));

                    //<publisher>
                    $pref = $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_FINDING_AID_PUBLISHER . $this->repository->tree()->id() . '_' . $this->repository->xref() . '_' . $user_id, '');
                    if($pref !== '') {
                        $publisher_dom = $publicationstmt_dom->appendChild($this->ead_xml->createElement('publisher', $pref));
                            $publisher_dom->appendChild(new DOMAttr('encodinganalog', '260$b'));
                    }

                    //<date>
                    $date_dom = $publicationstmt_dom->appendChild($this->ead_xml->createElement('date', date('Y-m-d')));
                        $date_dom->appendChild(new DOMAttr('normal', date('Y-m-d')));
                        $date_dom->appendChild(new DOMAttr('encodinganalog', '260$c'));
  
            //<profiledesc>
            $profiledesc_dom = $header_dom->appendChild($this->ead_xml->createElement('profiledesc'));

                //<creation>
                $creation_dom = $profiledesc_dom->appendChild($this->ead_xml->createElement('creation',
                    I18N::translate('Generated by webtrees')));
            
                    //<date>
                    $date_dom = $creation_dom->appendChild($this->ead_xml->createElement('date', date('Y-m-d')));
                        $date_dom->appendChild(new DOMAttr('normal', date('Y-m-d')));

                //<langusage>
                $langusage_dom = $profiledesc_dom->appendChild($this->ead_xml->createElement('langusage'));

                    //<language>
                    $iso_table = new ISO639;
                    $language_dom = $langusage_dom->appendChild($this->ead_xml->createElement('language', $iso_table->nativeByCode2b($this->ISO_639_2b_language_tag)));
                        $language_dom->appendChild(new DOMAttr('langcode', $this->ISO_639_2b_language_tag));
                        $date_dom->appendChild(new DOMAttr('encodinganalog', '041'));

        return $header_dom;
    }
  
    /**
     * Add an archive to EAD XML
     * 
     * @param DOMNode       
     * 
     * @return DOMNode      
     */
    private function addArchive(string $xml_type, DOMNode $dom, CallNumberCategory $root_category): DOMNode
    {
         //<archdesc>
         $archive_dom = $dom->appendChild($this->ead_xml->createElement('archdesc'));
            $archive_dom->appendChild(new DOMAttr('level', 'fonds'));
            $archive_dom->appendChild(new DOMAttr('type','Findbuch'));
            $archive_dom->appendChild(new DOMAttr('encodinganalog','3.1.4'));
            $archive_dom->appendChild(new DOMAttr('relatedencoding','ISAD(G)v2'));

             //<did>
            $did_dom = $archive_dom->appendChild($this->ead_xml->createElement('did'));

                //<unittitle>
                $unittitle_dom = $did_dom->appendChild($this->ead_xml->createElement('unittitle', Functions::removeHtmlTags($this->repository->fullName())));
                    $unittitle_dom->appendChild(new DOMAttr('encodinganalog', '3.1.2'));
                
                //<unitid>
                $unitid_dom = $did_dom->appendChild($this->ead_xml->createElement('unitid', Functions::removeHtmlTags($this->repository->fullName())));
                    $unitid_dom->appendChild(new DOMAttr('encodinganalog', '3.1.1'));

                //<langmaterial>
                $langmaterial_dom = $did_dom->appendChild($this->ead_xml->createElement('langmaterial'));
                    $langmaterial_dom->appendChild(new DOMAttr('encodinganalog', '3.4.3'));

                    //<language>
                    $iso_table = new ISO639;
                    $language_dom = $langmaterial_dom->appendChild($this->ead_xml->createElement('language', $iso_table->nativeByCode2b($this->ISO_639_2b_language_tag)));
                        $language_dom->appendChild(new DOMAttr('langcode', $this->ISO_639_2b_language_tag));

                //<unitdate>        example: <unitdate normal="1900-01-01/1902-12-31">Laufzeit</unitdate>
                $date_range = $root_category->getOverallDateRange();

                if ( $date_range !== null) {
                    $date_range_text = Functions::getISOformatForDateRange($date_range);
                    
                $unitdate_dom = $did_dom->appendChild($this->ead_xml->createElement('unitdate', MoreI18N::xlate('Date range')));
                    $unitdate_dom->appendChild(new DOMAttr('normal', $date_range_text));
                    $unitdate_dom->appendChild(new DOMAttr('encodinganalog', '3.1.3'));
                }

                //<physdesc>
                //encodinganalog 3.1.5
                //TBD

                //<repository>
                $repository_dom = $did_dom->appendChild($this->ead_xml->createElement('repository'));

                    //<corpname>
                    $repository_dom->appendChild($this->ead_xml->createElement('corpname', Functions::removeHtmlTags($this->repository->fullName())));

                    //<address>
                    $address_lines = Functions::getRepositoryAddressLines($this->repository);

                    if(!empty($address_lines)){
                        $address_dom = $repository_dom->appendChild($this->ead_xml->createElement('address'));

                        foreach($address_lines as $line) {
                            //<addressline>
                            $address_dom->appendChild($this->ead_xml->createElement('addressline', $line));    
                        }
                    }
                    //<extref> bzw. www
                    if (isset($address_lines['REPO:WWW'])) {
                        $extref_dom = $repository_dom->appendChild($this->ead_xml->createElement('extref'));
                            $extref_dom->appendChild(new DOMAttr('xlink:href', $address_lines['REPO:WWW']));
                            $extref_dom->appendChild(new DOMAttr('xlink:title', Functions::removeHtmlTags($this->repository->fullName())));                    
                    }

                //<origination>
                $origination_dom = $did_dom->appendChild($this->ead_xml->createElement('origination'));
                    $origination_dom->appendChild(new DOMAttr('encodinganalog', '3.2.1'));

                    //<name>
                    $origination_dom->appendChild($this->ead_xml->createElement('name', 
                    Functions::removeHtmlTags($this->repository->fullName())));

            //<otherfindaid> //=http link to online finding aid of archive
            //TBD

        return $archive_dom;
    }

    /**
     * Add a collection (for the whole repository) to EAD XML
     * 
     * @param DOMNode       $dom
     * 
     * @return DOMNode      
     */
    private function addCollection(string $xml_type, DOMNode $dom, CallNumberCategory $root_category): DOMNode
    {
        $module_service = new ModuleService();
        $repository_hierarchy = $module_service->findByName(RepositoryHierarchy::MODULE_NAME);
        $base_url = $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_WEBTREES_BASE_URL, ''); 

         //<c>
         $collection_dom = $dom->appendChild($this->ead_xml->createElement('c'));
            $collection_dom->appendChild(new DOMAttr('level', 'collection'));
            $collection_dom->appendChild(new DOMAttr('id', $this->repository->xref()));
 
             //<did>
             $did_dom = $collection_dom->appendChild($this->ead_xml->createElement('did'));

                //<unitid>
                $unitid_dom = $did_dom->appendChild($this->ead_xml->createElement('unitid', $this->repository->xref()));
                    $unitid_dom->appendChild(new DOMAttr('encodinganalog', '3.1.1'));

                //<unittitle>
                $unittitle_dom = $did_dom->appendChild($this->ead_xml->createElement('unittitle', Functions::removeHtmlTags($this->repository->fullName())));
                    $unittitle_dom->appendChild(new DOMAttr('encodinganalog', '3.1.2'));
                
                //<unitdate>        example: <unitdate normal="1900-01-01/1902-12-31">Laufzeit</unitdate>
                $date_range = $root_category->getOverallDateRange();

                if ( $date_range !== null) {
                    $date_range_text = Functions::getISOformatForDateRange($date_range);
                                        
                $unitdate_node = $did_dom->appendChild($this->ead_xml->createElement('unitdate', MoreI18N::xlate('Date range')));
                    $unitdate_node->appendChild(new DOMAttr('normal', $date_range_text));
                    $unitdate_node->appendChild(new DOMAttr('encodinganalog', '3.1.3'));
                }

                //<langmaterial>
                $langmaterial_dom = $did_dom->appendChild($this->ead_xml->createElement('langmaterial'));
                    $langmaterial_dom->appendChild(new DOMAttr('encodinganalog', '3.4.3'));

                    //<language>
                    $iso_table = new ISO639;
                    $language_dom = $langmaterial_dom->appendChild($this->ead_xml->createElement('language', $iso_table->nativeByCode2b($this->ISO_639_2b_language_tag)));
                        $language_dom->appendChild(new DOMAttr('langcode', $this->ISO_639_2b_language_tag));

                //<origination>
                $origination_dom = $did_dom->appendChild($this->ead_xml->createElement('origination'));
                    $origination_dom->appendChild(new DOMAttr('label', 'final'));
                    $origination_dom->appendChild(new DOMAttr('encodinganalog', '3.2.1'));

                    //<name>
                    $origination_dom->appendChild($this->ead_xml->createElement('name', Functions::removeHtmlTags($this->repository->fullName())));

                //<dao>   
                $dao_node =$did_dom->appendChild($this->ead_xml->createElement('dao'));
                    $dao_node->appendChild(new DOMAttr('xlink:href', $base_url . self::WEBTREES_ROUTE_TO_TREE . $this->repository->tree()->name() . self::WEBTREES_TREE_TO_REPO . $this->repository->xref()));
                    $dao_node->appendChild(new DOMAttr('xlink:title', Functions::removeHtmlTags($this->repository->fullName())));                    

                //<otherfindaid>
                //TBD

                //<scopecontent>
                //TBD

                //<accessrestrict>
                //TBD

                //<controlaccess>
                //TBD

        return $collection_dom;
    }

    /**
     * Add a series (i.e. call number category) to EAD XML
     * 
     * @param DOMNode               $dom
     * @param CallNumberCategory    $call_number_category
     * 
     * @return DOMNode      
     */
    private function addSeries(string $xml_type, DOMNode $dom, CallNumberCategory $call_number_category): DOMNode
    {
         //<c>
         $series_dom = $dom->appendChild($this->ead_xml->createElement('c'));
            $series_dom->appendChild(new DOMAttr('level', 'series'));
            $series_dom->appendChild(new DOMAttr('id', 'CN' . $call_number_category->getId()));
 
             //<did>
             $did_dom = $series_dom->appendChild($this->ead_xml->createElement('did'));

                //<unittitle>
                $unittitle_dom = $did_dom->appendChild($this->ead_xml->createElement('unittitle', $call_number_category->getName()));
                    $unittitle_dom->appendChild(new DOMAttr('encodinganalog', '3.1.2'));
                
                //<unitid>
                $unitid_dom = $did_dom->appendChild($this->ead_xml->createElement('unitid', $call_number_category->getFullName()));
                    $unitid_dom->appendChild(new DOMAttr('encodinganalog', '3.1.1'));

                //<langmaterial>
                $langmaterial_dom = $did_dom->appendChild($this->ead_xml->createElement('langmaterial'));
                    $langmaterial_dom->appendChild(new DOMAttr('encodinganalog', '3.4.3'));

                    //<language>
                    $iso_table = new ISO639;
                    $language_dom = $langmaterial_dom->appendChild($this->ead_xml->createElement('language', $iso_table->nativeByCode2b($this->ISO_639_2b_language_tag)));
                        $language_dom->appendChild(new DOMAttr('langcode', $this->ISO_639_2b_language_tag));

                //<unitdate>        example: <unitdate normal="1900-01-01/1902-12-31">Laufzeit</unitdate>
                $date_range = $call_number_category->getOverallDateRange();

                if ( $date_range !== null) {
                    $date_range_text = Functions::getISOformatForDateRange($date_range);
                                        
                $unitdate_dom = $did_dom->appendChild($this->ead_xml->createElement('unitdate', MoreI18N::xlate('Date range')));
                    $unitdate_dom->appendChild(new DOMAttr('normal', $date_range_text));
                    $unitdate_dom->appendChild(new DOMAttr('encodinganalog', '3.1.3'));
                }

        return $series_dom;
    } 

    /**
     * Add an item (i.e. source) to EAD XML
     * 
     * @param DOMDocument      $dom
     * @param Source           $source
     */
    private function addItem(string $xml_type, DOMNode $dom, Source $source, Repository $repository)
    {
        $module_service = new ModuleService();
        $repository_hierarchy = $module_service->findByName(RepositoryHierarchy::MODULE_NAME);
        $base_url = $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_WEBTREES_BASE_URL, ''); 

        $fact_values =Functions::sourceValuesByTag($source, $repository);

        //<c>
        $c_dom = $dom->appendChild($this->ead_xml->createElement('c'));
            $c_dom->appendChild(new DOMAttr('level', 'item'));
            $c_dom->appendChild(new DOMAttr('id', $source->xref()));

            //<did>
            $did_dom = $c_dom->appendChild($this->ead_xml->createElement('did'));

                //<unitid>
                if (isset($fact_values['SOUR:REPO:CALN'])) {
                    $unitid_dom = $did_dom->appendChild($this->ead_xml->createElement('unitid', $fact_values['SOUR:REPO:CALN']));
                        $unitid_dom->appendChild(new DOMAttr('encodinganalog', '3.1.1'));
                }

                //<unittitle>
                $unittitle_node =$did_dom->appendChild($this->ead_xml->createElement('unittitle', $fact_values['SOUR:TITL']));
                    $unittitle_node->appendChild(new DOMAttr('encodinganalog', '3.1.2'));

                //<unitdate>        example: <unitdate normal="1900-01-01/1902-12-31">Laufzeit</unitdate>
                if (isset($fact_values['SOUR:DATA:EVEN:DATE'])) {
                    $unitdate_node = $did_dom->appendChild($this->ead_xml->createElement('unitdate', MoreI18N::xlate('Date range')));
                        $unitdate_node->appendChild(new DOMAttr('normal', Functions::removeHtmlTags($fact_values['SOUR:DATA:EVEN:DATE'])));
                        $unitdate_node->appendChild(new DOMAttr('encodinganalog', '3.1.3'));
                 }
                
                //<dao>
                $dao_node =$did_dom->appendChild($this->ead_xml->createElement('dao'));
                    $dao_node->appendChild(new DOMAttr('xlink:href', $base_url . self::WEBTREES_ROUTE_TO_TREE . $source->tree()->name() . self::WEBTREES_TREE_TO_SOURCE . $source->xref()));
                    $dao_node->appendChild(new DOMAttr('xlink:title', $fact_values['SOUR:TITL']));
                    

                //<note> link to webtrees, needed for AtoM, only. For simplicity reasons, always include to XML
                if (//($xml_type === DownloadEADxmlService::DOWNLOAD_OPTION_ATOM) &&
                    ($repository_hierarchy !== null) &&
                    ($base_url !== ''))  
                {
                    $note_node = $did_dom->appendChild($this->ead_xml->createElement('note'));
                        $note_node->appendChild(new DOMAttr('type', 'generalNote'));
                        $unittitle_node->appendChild(new DOMAttr('encodinganalog', '3.6.1'));

                    //<p>                        
                        $note_node->appendChild($this->ead_xml->createElement('p', '[webtrees: ' .$source->xref() . '](' . $base_url . self::WEBTREES_ROUTE_TO_TREE . $source->tree()->name() . self::WEBTREES_TREE_TO_SOURCE . $source->xref() . ')'));
                }

                //<place>   within EVEN:PLAC
                //TBD
   }    

    /**
     * Return response to download an EAD XML file
     * 
     * @param string    $filename       Name of download file without extension
     *
     * @return ResponseInterface
     */
    public function downloadResponse(string $filename): ResponseInterface 
    {
        return Functions::responseForDOMDownload($this->ead_xml, $filename);
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
        $text = Functions::removeHtmlTags($text);
        $text = str_replace(['Ä','Ö','Ü','ä','ö','ü','ß'], ['AE','OE','UE','ae','oe','ue','ss'], $text);

        return strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $text));
    }
}