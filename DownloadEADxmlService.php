<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2022 webtrees development team
 *                    <http://webtrees.net>
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
use Fisharebest\Webtrees\Repository;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Source;
use Matriphe\ISO639\ISO639;
use Psr\Http\Message\ResponseInterface;

use function date;

/**
 * Download Service for EAD XML files
 */
class DownloadEADxmlService extends DownloadService
{
    //The xml object for EAD XML export
    private DOMDocument $ead_xml;

    //The top level collection within the xml
    private DOMNode $collection;

    //The repository hierarchy, to which the service relates
    private RepositoryHierarchy $repository_hierarchy;

    //The repository, to which the service relates
    private Repository $repository;

    //The user, for which the service is executed
    private UserInterface $user;

    //The ISO ISO-639-2b language tag related to the webtrees session
    private string $ISO_639_2b_language_tag;

    //A flag, whether 'encodinganalog' tags are included in EAD XML
    private bool $use_encoding_analog;

	//Module service to search and find modules
    private ModuleService $module_service;

    /**
     * Constructor
     *
     * @param string              $xml_type
     * @param RepositoryHierarchy $repository_hierarchy
     * @param CallNumberCategory  $root_category
     * @param UserInterface       $user
     */
    public function __construct(
        string $xml_type,
        RepositoryHierarchy $repository_hierarchy,
        CallNumberCategory $root_category,
        UserInterface $user
    ) {
        //Initialize variables
        $this->repository_hierarchy = $repository_hierarchy;
        $this->repository = $repository_hierarchy->getRepository();
        $this->user = $user;
        $this->use_encoding_analog = ($xml_type !== self::DOWNLOAD_OPTION_DDB_EAD);
        $this->module_service = new ModuleService();
		
        //Get language
        $language_tag = Session::get('language');

        //Convert different English 'en-*' tags to simple 'en' tag
        $language_tag = substr($language_tag, 0, 2) === 'en' ? 'en' : $language_tag;

        //Set language
        $iso_table = new ISO639();
        $language = $iso_table->languageByCode1($language_tag);
        $this->ISO_639_2b_language_tag = $iso_table->code2bByLanguage($language);

        //Create DOM document
        $dom_implementation = new DOMImplementation();

        //Include DTD
        $dtd = $dom_implementation->createDocumentType(
            'ead',
            '+//ISBN 1-931666-00-8//DTD ead.dtd (Encoded Archival Description (EAD) Version 2002)//EN',
            'http://lcweb2.loc.gov/xmlcommon/dtds/ead2002/ead.dtd'
        );
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
        $archive_dom = $this->addArchive($xml_type, $ead_dom, $root_category);
        $dsc_dom = $archive_dom->appendChild($this->ead_xml->createElement('dsc'));
        $this->collection = $this->addCollection($xml_type, $dsc_dom, $root_category);
    }

    /**
     * Get collection
     *
     * @return DOMNode
     */
    public function getCollection(): DOMNode
    {
        return $this->collection;
    }

    /**
     * Create XML for a hierarchy of call numbers
     *
     * @param string             $xml_type
     * @param DOMNode            $dom
     * @param CallNumberCategory $call_number_category
     *
     * @return void
     */
    public function createXMLforCategory(string $xml_type, DOMNode $dom, CallNumberCategory $call_number_category)
    {
        $categories = $call_number_category->getSubCategories();

        foreach ($categories as $category) {
            //Add node to xml structure
            $series_dom = $this->addSeries($xml_type, $dom, $category);

            //Add sources to xml structure
            foreach ($category->getSources() as $source) {
                $this->addFile($xml_type, $series_dom, $source);
            }

            //Call recursion for sub categories
            $this->createXMLforCategory($xml_type, $series_dom, $category);
        }
    }

    /**
     * Add the header to EAD XML
     *
     * @param string  $xml_type
     * @param DOMNode $dom
     *
     * @return DOMNode
     */
    private function addHeader(string $xml_type, DOMNode $dom): DOMNode
    {
        $repository_hierarchy = $this->module_service->findByName(RepositoryHierarchy::activeModuleName());
        $user_id = $this->user->id();

        //<eadheader>
        $header_dom = $dom->appendChild($this->ead_xml->createElement('eadheader'));
        $header_dom->appendChild(new DOMAttr('countryencoding', 'iso3166-1'));
        $header_dom->appendChild(new DOMAttr('dateencoding', 'iso8601'));
        $header_dom->appendChild(new DOMAttr('langencoding', 'iso639-2b'));
        $header_dom->appendChild(new DOMAttr('repositoryencoding', 'iso15511'));
        $header_dom->appendChild(new DOMAttr('scriptencoding', 'iso15924'));

        //<eadid>
        $eadid_dom = $header_dom->appendChild(
            $this->ead_xml->createElement(
                'eadid',
                $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_FINDING_AID_TITLE . $this->repository->tree()->id() . '_' . $this->repository->xref() . '_' . $user_id, '')
            )
        );

        $pref = $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_MAIN_AGENCY_CODE . $this->repository->tree()->id() . '_' . $this->repository->xref() . '_' . $user_id, '');
        if ($pref !== '') {
            $eadid_dom->appendChild(new DOMAttr('mainagencycode', $pref));
        }
        $pref = $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_FINDING_AID_IDENTIFIER . $this->repository->tree()->id() . '_' . $this->repository->xref() . '_' . $user_id, '');
        if ($pref !== '') {
            $eadid_dom->appendChild(new DOMAttr('identifier', $pref));
        }
        $pref = $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_COUNTRY_CODE . $this->repository->tree()->id() . '_' . $this->repository->xref() . '_' . $user_id, '');
        if ($pref !== '') {
            $eadid_dom->appendChild(new DOMAttr('countrycode', $pref));
        }
        $pref = $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_FINDING_AID_URL . $this->repository->tree()->id() . '_' . $this->repository->xref() . '_' . $user_id, '');
        if ($pref !== '') {
            $eadid_dom->appendChild(new DOMAttr('url', $pref));
        } else {
            $eadid_dom->appendChild(new DOMAttr('url', Functions::getRepositoryUrl($this->repository)));
        }


        //<filedesc>
        $filedesc_dom = $header_dom->appendChild($this->ead_xml->createElement('filedesc'));

        //<titlestmt>
        $titlestmt_dom = $filedesc_dom->appendChild($this->ead_xml->createElement('titlestmt'));

        //<titleproper>
        $titleproper_dom = $titlestmt_dom->appendChild(
            $this->ead_xml->createElement(
                'titleproper',
                $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_FINDING_AID_TITLE . $this->repository->tree()->id() . '_' . $this->repository->xref() . '_' . $user_id, '')
            )
        );
        if ($this->use_encoding_analog) {
            $titleproper_dom->appendChild(new DOMAttr('encodinganalog', '245'));
        }

        //<publicationstmt>
        $publicationstmt_dom = $filedesc_dom->appendChild($this->ead_xml->createElement('publicationstmt'));

        //<publisher>
        $pref = $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_FINDING_AID_PUBLISHER . $this->repository->tree()->id() . '_' . $this->repository->xref() . '_' . $user_id, '');
        if ($pref !== '') {
            $publisher_dom = $publicationstmt_dom->appendChild($this->ead_xml->createElement('publisher', $pref));
            if ($this->use_encoding_analog) {
                $publisher_dom->appendChild(new DOMAttr('encodinganalog', '260$b'));
            }
        }

        //<date>
        $date_dom = $publicationstmt_dom->appendChild($this->ead_xml->createElement('date', date('Y-m-d')));
        $date_dom->appendChild(new DOMAttr('normal', date('Y-m-d')));
        if ($this->use_encoding_analog) {
            $date_dom->appendChild(new DOMAttr('encodinganalog', '260$c'));
        }

        //<profiledesc>
        $profiledesc_dom = $header_dom->appendChild($this->ead_xml->createElement('profiledesc'));

        //<creation>
        $creation_dom = $profiledesc_dom->appendChild($this->ead_xml->createElement('creation'));

        //<date>
        $date_dom = $creation_dom->appendChild($this->ead_xml->createElement('date', date('Y-m-d')));
        $date_dom->appendChild(new DOMAttr('normal', date('Y-m-d')));

        //<langusage>
        $langusage_dom = $profiledesc_dom->appendChild($this->ead_xml->createElement('langusage'));

        //<language>
        $iso_table = new ISO639();
        $language_dom = $langusage_dom->appendChild($this->ead_xml->createElement('language', $iso_table->nativeByCode2b($this->ISO_639_2b_language_tag)));
        $language_dom->appendChild(new DOMAttr('langcode', $this->ISO_639_2b_language_tag));
        if ($this->use_encoding_analog) {
            $date_dom->appendChild(new DOMAttr('encodinganalog', '041'));
        }

        return $header_dom;
    }

    /**
     * Add an archive to EAD XML
     *
     * @param string             $xml_type
     * @param DOMNode            $dom
     * @param CallNumberCategory $root_category
     *
     * @return DOMNode
     */
    private function addArchive(string $xml_type, DOMNode $dom, CallNumberCategory $root_category): DOMNode
    {
        //<archdesc>
        $archive_dom = $dom->appendChild($this->ead_xml->createElement('archdesc'));
        $archive_dom->appendChild(new DOMAttr('level', 'fonds'));
        $archive_dom->appendChild(new DOMAttr('type', 'inventory'));
        if ($this->use_encoding_analog) {
            $archive_dom->appendChild(new DOMAttr('relatedencoding', 'ISAD(G)v2'));
            $archive_dom->appendChild(new DOMAttr('encodinganalog', '3.1.4'));
        }
        //<did>
        $did_dom = $archive_dom->appendChild($this->ead_xml->createElement('did'));

        //<unittitle>
        $unittitle_dom = $did_dom->appendChild($this->ead_xml->createElement('unittitle', Functions::removeHtmlTags($this->repository->fullName())));
        if ($this->use_encoding_analog) {
            $unittitle_dom->appendChild(new DOMAttr('encodinganalog', '3.1.2'));
        }

        //<unitid>
        $unitid_dom = $did_dom->appendChild($this->ead_xml->createElement('unitid', $this->repository->xref()));
        if ($this->use_encoding_analog) {
            $unitid_dom->appendChild(new DOMAttr('encodinganalog', '3.1.1'));
        }

        //<langmaterial>
        $langmaterial_dom = $did_dom->appendChild($this->ead_xml->createElement('langmaterial'));
        if ($this->use_encoding_analog) {
            $langmaterial_dom->appendChild(new DOMAttr('encodinganalog', '3.4.3'));
        }

        //<language>
        $iso_table = new ISO639();
        $language_dom = $langmaterial_dom->appendChild($this->ead_xml->createElement('language', $iso_table->nativeByCode2b($this->ISO_639_2b_language_tag)));
        $language_dom->appendChild(new DOMAttr('langcode', $this->ISO_639_2b_language_tag));

        //<unitdate>        example: <unitdate normal="1900-01-01/1902-12-31">Laufzeit</unitdate>
        $date_range = $root_category->getOverallDateRange();

        if ($date_range !== null) {
            $date_range_text = Functions::getISOformatForDateRange($date_range);

            $unitdate_dom = $did_dom->appendChild($this->ead_xml->createElement('unitdate', MoreI18N::xlate('Date range')));
            $unitdate_dom->appendChild(new DOMAttr('normal', $date_range_text));
            if ($this->use_encoding_analog) {
                $unitdate_dom->appendChild(new DOMAttr('encodinganalog', '3.1.3'));
            }
        }

        //<physdesc>
        //encodinganalog 3.1.5
        //TBD

        //<repository>
        $repository_dom = $did_dom->appendChild($this->ead_xml->createElement('repository'));

        //<corpname>
        $repository_dom->appendChild($this->ead_xml->createElement('corpname', Functions::removeHtmlTags($this->repository->fullName())));
        //@role
        //TBD

        //<address>
        $address_lines = Functions::getRepositoryAddressLines($this->repository);

        if (!empty($address_lines)) {
            $address_dom = $repository_dom->appendChild($this->ead_xml->createElement('address'));

            foreach ($address_lines as $line) {
                //<addressline>
                $address_dom->appendChild($this->ead_xml->createElement('addressline', $line));
            }
        }
        //<extref> bzw. www
        if (isset($address_lines['REPO:WWW'])) {
            $extref_dom = $repository_dom->appendChild($this->ead_xml->createElement('extref'));
            $extref_dom->appendChild(new DOMAttr('xlink:href', Functions::getRepositoryUrl($this->repository)));
            $extref_dom->appendChild(new DOMAttr('xlink:title', Functions::removeHtmlTags($this->repository->fullName())));
            $extref_dom->appendChild(new DOMAttr('xlink:role', 'url_archive'));
        }

        //<origination>
        $origination_dom = $did_dom->appendChild($this->ead_xml->createElement('origination'));
        if ($this->use_encoding_analog) {
            $origination_dom->appendChild(new DOMAttr('encodinganalog', '3.2.1'));
        }

        //<name>
        $origination_dom->appendChild(
            $this->ead_xml->createElement(
                'name',
                Functions::removeHtmlTags($this->repository->fullName())
            )
        );

        //<otherfindaid> //=http link to online finding aid of archive
        //TBD

        return $archive_dom;
    }

    /**
     * Add a collection (for the whole repository) to EAD XML
     *
     * @param string             $xml_type
     * @param DOMNode            $dom
     * @param CallNumberCategory $root_category
     *
     * @return DOMNode
     */
    private function addCollection(string $xml_type, DOMNode $dom, CallNumberCategory $root_category): DOMNode
    {
        //<c>
        $collection_dom = $dom->appendChild($this->ead_xml->createElement('c'));
        $collection_dom->appendChild(new DOMAttr('level', 'collection'));
        $collection_dom->appendChild(new DOMAttr('id', $this->repository->xref()));

        //<did>
        $did_dom = $collection_dom->appendChild($this->ead_xml->createElement('did'));

        //<unittitle>
        $unittitle_dom = $did_dom->appendChild($this->ead_xml->createElement('unittitle', Functions::removeHtmlTags($this->repository->fullName())));
        if ($this->use_encoding_analog) {
            $unittitle_dom->appendChild(new DOMAttr('encodinganalog', '3.1.2'));
        }

        //<unitid>
        $unitid_dom = $did_dom->appendChild($this->ead_xml->createElement('unitid', $this->repository->xref()));
        if ($this->use_encoding_analog) {
            $unitid_dom->appendChild(new DOMAttr('encodinganalog', '3.1.1'));
        }

        //<unitdate>        example: <unitdate normal="1900-01-01/1902-12-31">Laufzeit</unitdate>
        $date_range = $root_category->getOverallDateRange();

        if ($date_range !== null) {
            $date_range_text = Functions::getISOformatForDateRange($date_range);

            $unitdate_node = $did_dom->appendChild($this->ead_xml->createElement('unitdate', MoreI18N::xlate('Date range')));
            $unitdate_node->appendChild(new DOMAttr('normal', $date_range_text));
            if ($this->use_encoding_analog) {
                $unitdate_node->appendChild(new DOMAttr('encodinganalog', '3.1.3'));
            }
        }

        //<langmaterial>
        $langmaterial_dom = $did_dom->appendChild($this->ead_xml->createElement('langmaterial'));
        if ($this->use_encoding_analog) {
            $langmaterial_dom->appendChild(new DOMAttr('encodinganalog', '3.4.3'));
        }

        //<language>
        $iso_table = new ISO639();
        $language_dom = $langmaterial_dom->appendChild($this->ead_xml->createElement('language', $iso_table->nativeByCode2b($this->ISO_639_2b_language_tag)));
        $language_dom->appendChild(new DOMAttr('langcode', $this->ISO_639_2b_language_tag));

        //<origination>
        $origination_dom = $did_dom->appendChild($this->ead_xml->createElement('origination'));
        $origination_dom->appendChild(new DOMAttr('label', 'final'));
        if ($this->use_encoding_analog) {
            $origination_dom->appendChild(new DOMAttr('encodinganalog', '3.2.1'));
        }

        //<name>
        $origination_dom->appendChild($this->ead_xml->createElement('name', Functions::removeHtmlTags($this->repository->fullName())));

        //<dao>
        $dao_node =$did_dom->appendChild($this->ead_xml->createElement('dao'));
        $dao_node->appendChild(new DOMAttr('xlink:href', $this->repository->url()));
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
     * @param string             $xml_type
     * @param DOMNode            $dom
     * @param CallNumberCategory $call_number_category
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
        $title = $this->repository_hierarchy->getCallNumberCategoryTitleService()->getCallNumberCategoryTitle($call_number_category->getFrontEndName(true));
        $title = ($title === '') ? $call_number_category->getFrontEndName(true) : $title;

        $unittitle_dom = $did_dom->appendChild($this->ead_xml->createElement('unittitle', $title));
        if ($this->use_encoding_analog) {
            $unittitle_dom->appendChild(new DOMAttr('encodinganalog', '3.1.2'));
        }

        //<unitid>
        $unitid_dom = $did_dom->appendChild($this->ead_xml->createElement('unitid', $call_number_category->getFrontEndName(true)));
        if ($this->use_encoding_analog) {
            $unitid_dom->appendChild(new DOMAttr('encodinganalog', '3.1.1'));
        }

        //<langmaterial>
        $langmaterial_dom = $did_dom->appendChild($this->ead_xml->createElement('langmaterial'));
        if ($this->use_encoding_analog) {
            $langmaterial_dom->appendChild(new DOMAttr('encodinganalog', '3.4.3'));
        }

        //<language>
        $iso_table = new ISO639();
        $language_dom = $langmaterial_dom->appendChild($this->ead_xml->createElement('language', $iso_table->nativeByCode2b($this->ISO_639_2b_language_tag)));
        $language_dom->appendChild(new DOMAttr('langcode', $this->ISO_639_2b_language_tag));

        //<unitdate>        example: <unitdate normal="1900-01-01/1902-12-31">Laufzeit</unitdate>
        $date_range = $call_number_category->getOverallDateRange();

        if ($date_range !== null) {
            $date_range_text = Functions::getISOformatForDateRange($date_range);

            $unitdate_dom = $did_dom->appendChild($this->ead_xml->createElement('unitdate', MoreI18N::xlate('Date range')));
            $unitdate_dom->appendChild(new DOMAttr('normal', $date_range_text));
            if ($this->use_encoding_analog) {
                $unitdate_dom->appendChild(new DOMAttr('encodinganalog', '3.1.3'));
            }
        }

        return $series_dom;
    }

    /**
     * Add a file (i.e. source) to EAD XML
     *
     * @param string      $xml_type
     * @param DOMDocument $dom
     * @param Source      $source
     *
     * @return void
     */
    private function addFile(string $xml_type, DOMNode $dom, Source $source)
    {
        $fact_values = Functions::sourceValuesByTag($source, $this->repository);
        $call_number = Functions::getCallNumberForSource($source, $this->repository_hierarchy->getAllRepositories());

        //<c>
        $c_dom = $dom->appendChild($this->ead_xml->createElement('c'));
        $c_dom->appendChild(new DOMAttr('level', 'file'));
        $c_dom->appendChild(new DOMAttr('id', $source->xref()));

        //<did>
        $did_dom = $c_dom->appendChild($this->ead_xml->createElement('did'));

        //<unittitle>
        $unittitle_node =$did_dom->appendChild($this->ead_xml->createElement('unittitle', e($fact_values['SOUR:TITL'])));
        if ($this->use_encoding_analog) {
            $unittitle_node->appendChild(new DOMAttr('encodinganalog', '3.1.2'));
        }

        //<unitid>
        if ($call_number !== '') {
            $unitid_dom = $did_dom->appendChild($this->ead_xml->createElement('unitid', $call_number));
            if ($this->use_encoding_analog) {
                $unitid_dom->appendChild(new DOMAttr('encodinganalog', '3.1.1'));
            }
        }

        //<unitdate>        example: <unitdate normal="1900-01-01/1902-12-31">Laufzeit</unitdate>
        if (isset($fact_values['SOUR:DATA:EVEN:DATE'])) {
            $unitdate_node = $did_dom->appendChild($this->ead_xml->createElement('unitdate', MoreI18N::xlate('Date range')));
            $unitdate_node->appendChild(new DOMAttr('normal', Functions::removeHtmlTags($fact_values['SOUR:DATA:EVEN:DATE'])));
            if ($this->use_encoding_analog) {
                $unitdate_node->appendChild(new DOMAttr('encodinganalog', '3.1.3'));
            }
        }

        //<dao>
        $dao_node =$did_dom->appendChild($this->ead_xml->createElement('dao'));
        $dao_node->appendChild(new DOMAttr('xlink:href', $source->url()));
        $dao_node->appendChild(new DOMAttr('xlink:title', $fact_values['SOUR:TITL']));


        //<note> link to webtrees, needed for AtoM, only. For simplicity reasons, always include to XML
        $note_node = $did_dom->appendChild($this->ead_xml->createElement('note'));
        $note_node->appendChild(new DOMAttr('type', 'generalNote'));
        if ($this->use_encoding_analog) {
            $unittitle_node->appendChild(new DOMAttr('encodinganalog', '3.6.1'));
        }

        //<p>
        $note_node->appendChild($this->ead_xml->createElement('p', '[webtrees: ' .$source->xref() . '](' . $source->url() . ')'));

        //<place>   within EVEN:PLAC
        //TBD
    }

    /**
     * Return response to download an EAD XML file
     *
     * @param string $filename Name of download file without extension
     *
     * @return ResponseInterface
     */
    public function downloadResponse(string $filename): ResponseInterface
    {
        return self::responseForDOMDownload($this->ead_xml, $filename);
    }

    /**
     * Get AtoM slug
     *
     * @param string $text
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
