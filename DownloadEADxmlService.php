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

use Fisharebest\Webtrees\Encodings\UTF8;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Fisharebest\Webtrees\Source;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use DOMDocument;
use DOMNode;
use DOMAttr;
use RuntimeException;
use Fisharebest\Webtrees\Repository;


/**
 * Download Service for EAD XML files
 */
class DownloadEADxmlService
{
    //The xml object for EAD XML export
    private DOMDocument $ead_xml;

    //The top level collection within the xml
    private DOMNode $collection;

    //The ResponseFactory used
    private ResponseFactoryInterface $response_factory;

    //The StreamFactory used
    private StreamFactoryInterface $stream_factory;

    //The repository, to which the service relates
    private Repository $repository;

    /**
     * Constructor
     * 
     * @param string        $template_filename    The path of the xml template file name with file extension 
     * @param Repository    $repository    
     *
     */
    public function __construct(string $template_filename, Repository $repository)
    {
        //Set repository
        $this->repository = $repository;

        //New DOM and settings for a nice xml format
        $this->ead_xml = new DOMDocument();
        $this->ead_xml->preserveWhiteSpace = false;
        $this->ead_xml->formatOutput = true;

        $this->ead_xml->load($template_filename);
        $this->response_factory = app(ResponseFactoryInterface::class);
        $this->stream_factory   = new Psr17Factory();
        $this->linked_record_service = new LinkedRecordService();

        //Initialize EAD xml
        $dom = $this->ead_xml->getElementsByTagName('ead')->item(0);
        $dom = $this->addArchive($dom);  
        $dom = $dom->appendChild($this->ead_xml->createElement('dsc'));
        $this->collection = $this->addCollection($dom);
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
    public function createXMLforCategory(DOMNode $dom, CallNumberCategory $call_number_category)
    {
        $categories = $call_number_category->getSubCategories();

        foreach ($categories as $category) {

            //Add node to xml structure
            $series_dom = $this->addSeries($dom, $category);

            //Add sources to xml structure
            foreach ($category->getSources() as $source) {
                $this->addItem($series_dom, $source);
            }

            //Call recursion for sub categories
            $this->createXMLforCategory($series_dom, $category);
        }
    }

    /**
     * Add an archive to EAD XML
     * 
     * @param DOMDocument       $dom
     * 
     * @return DOMDocument      
     */
    public function addArchive(DOMNode $dom): DOMNode
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
                $dom = $did_dom->appendChild($this->ead_xml->createElement('unittitle', $this->removeHtmlTags($this->repository->fullName())));
                    $dom->appendChild(new DOMAttr('encodinganalog', '3.1.2'));
                
                //<unitid>
                $dom = $did_dom->appendChild($this->ead_xml->createElement('unitid', $this->removeHtmlTags($this->repository->fullName())));
                    $dom->appendChild(new DOMAttr('encodinganalog', '3.1.1'));

                //<unitdate>
                //TBD

                //<physdesc>
                //TBD

                //<repository>
                $repository_dom = $did_dom->appendChild($this->ead_xml->createElement('repository'));

                    //<corpname>
                    $corpname_dom = $repository_dom->appendChild($this->ead_xml->createElement('corpname', $this->removeHtmlTags($this->repository->fullName())));

                    //<address>
                    $address_lines = $this->getRepositoryAddressLines($this->repository);

                    if(!empty($address_lines)){
                        $address_dom = $repository_dom->appendChild($this->ead_xml->createElement('address'));

                        foreach($address_lines as $line) {
                            //<addressline>
                            $address_dom->appendChild($this->ead_xml->createElement('addressline', $line));    
                        }
                    }

                //<origination>
                $origination_dom = $did_dom->appendChild($this->ead_xml->createElement('origination'));
                    $origination_dom->appendChild(new DOMAttr('encodinganalog', '3.2.1'));

                    //<persname>
                    //TBD MY_NAME
                    $origination_dom->appendChild($this->ead_xml->createElement('persname', 'MY_NAME'));

        return $archive_dom;
    }

    /**
     * Add a collection (for the whole repository) to EAD XML
     * 
     * @param DOMDocument       $dom
     * 
     * @return DOMDocument      
     */
    public function addCollection(DOMNode $dom): DOMNode
    {
         //<c>
         $collection_dom = $dom->appendChild($this->ead_xml->createElement('c'));
            $collection_dom->appendChild(new DOMAttr('level', 'collection'));
            $collection_dom->appendChild(new DOMAttr('id', $this->repository->xref()));
 
             //<did>
             $did_dom = $collection_dom->appendChild($this->ead_xml->createElement('did'));

                //<unittitle>
                $dom = $did_dom->appendChild($this->ead_xml->createElement('unittitle', $this->removeHtmlTags($this->repository->fullName())));
                    $dom->appendChild(new DOMAttr('encodinganalog', '3.1.2'));
                
                //<unitid>
                $dom = $did_dom->appendChild($this->ead_xml->createElement('unitid', $this->repository->xref()));
                    $dom->appendChild(new DOMAttr('encodinganalog', '3.1.1'));

                //<unitdate>
                //TBD

                //<langmaterial>
                //TBD

                //<origination>
                $origination_dom = $did_dom->appendChild($this->ead_xml->createElement('origination'));
                    $origination_dom->appendChild(new DOMAttr('label', 'final'));
                    $origination_dom->appendChild(new DOMAttr('encodinganalog', '3.2.1'));

                    //<name>
                    $origination_dom->appendChild($this->ead_xml->createElement('name', $this->removeHtmlTags($this->repository->fullName())));

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
     * @param DOMDocument           $dom
     * @param CallNumberCategory    $call_number_category
     * 
     * @return DOMDocument      
     */
    public function addSeries(DOMNode $dom, CallNumberCategory $call_number_category): DOMNode
    {
         //<c>
         $dom = $dom->appendChild($this->ead_xml->createElement('c'));
         $series_dom = $dom;
            $dom->appendChild(new DOMAttr('level', 'series'));
            $dom->appendChild(new DOMAttr('id', 'CN' . $call_number_category->getId()));
 
             //<did>
             $did_dom = $series_dom->appendChild($this->ead_xml->createElement('did'));

                //<unittitle>
                $dom = $did_dom->appendChild($this->ead_xml->createElement('unittitle', $call_number_category->getName()));
                    $dom->appendChild(new DOMAttr('encodinganalog', '3.1.1'));
                
                //<unitid>
                $dom = $did_dom->appendChild($this->ead_xml->createElement('unitid', $call_number_category->getFullName()));
                    $dom->appendChild(new DOMAttr('encodinganalog', '3.1.2'));

        return $series_dom;
    }
  
    /**
     * Add an item (i.e. source) to EAD XML
     * 
     * @param DOMDocument      $dom
     * @param Source           $source
     */
    public function addItem(DOMNode $dom, Source $source)
    {
        $fact_values = $this->sourceValuesByTag($source);

        //<c>
        $dom = $dom->appendChild($this->ead_xml->createElement('c'));
            $dom->appendChild(new DOMAttr('level', 'item'));
            $dom->appendChild(new DOMAttr('id', $source->xref()));

            //<did>
            $dom = $dom->appendChild($this->ead_xml->createElement('did'));

            //<unitid>
            if (isset($fact_values['SOUR:REPO:CALN'])) {
                $dom->appendChild($this->ead_xml->createElement('unitid', $fact_values['SOUR:REPO:CALN']));
            }

            //<unittitle>
            if (isset($fact_values['SOUR:TITL'])) {
                $dom_node = $this->ead_xml->createElement('unittitle', $fact_values['SOUR:TITL']);
                    $dom_node->appendChild(new DOMAttr('type', 'title'));

                $dom->appendChild($dom_node);
            }

            //<unitdate>
            //<unitdate normal="1900-01-01/1902-12-31">Laufzeit</unitdate>
            if (isset($fact_values['SOUR:DATA:EVEN:DATE'])) {
                $dom_node = $this->ead_xml->createElement('unitdate', I18N::translate("Date range"));
                    $dom_node->appendChild(new DOMAttr('normal', $fact_values['SOUR:DATA:EVEN:DATE']));

                $dom->appendChild($dom_node);
            }
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
            $resource = $this->export($this->ead_xml);
            $stream   = $this->stream_factory->createStreamFromResource($resource);

            return $this->response_factory->createResponse()
                ->withBody($stream)
                ->withHeader('content-type', 'text/xml; charset=' . UTF8::NAME)
                ->withHeader('content-disposition', 'attachment; filename="' . addcslashes($filename, '"') . '.xml"');
    }

    /**
     * Write XML data to a stream
     *
     * @param DOMDocument   $dom
     * 
     * @return resource
     */
    public function export(DOMDocument $dom) 
    {
        $stream = fopen('php://memory', 'wb+');

        if ($stream === false) {
            throw new RuntimeException('Failed to create temporary stream');
        }

        //Write xml to stream
        $bytes_written = fwrite($stream, $dom->saveXML());

        if ($bytes_written !== strlen($dom->saveXML())) {
            throw new RuntimeException('Unable to write to stream.  Perhaps the disk is full?');
        }

        if (rewind($stream) === false) {
            throw new RuntimeException('Cannot rewind temporary stream');
        }

        return $stream;
    }

    /**
     * Get address lines of a repository 
     * 
     * @param Repository    $repository
     * 
     * @return array    [string with adress line]
     */
    public function getRepositoryAddressLines(Repository $repository): array
    {
        $address_lines = [];
        $level1_address_tags = [
            'REPO:ADDR',
            'REPO:PHON',
            'REPO:EMAIL',
            'REPO:FAX',
            'REPO:WWW',
        ];
        $level2_address_tags = [
            'ADR1',
            'ADR2',
            'ADR3',
            'CITY',
            'STAE',
            'POST',
            'CTRY',
        ];       

        foreach($repository->facts() as $fact) {

            if (in_array($fact->tag(), $level1_address_tags)) {
                $address_lines[$fact->tag()] = $fact->value();       
            }

            if ($fact->tag() === 'REPO:ADDR') {

                foreach($level2_address_tags as $tag) {

                    if($fact->attribute($tag) !== '') {
                        $address_lines[$tag] = $fact->attribute($tag);
                    }
                }
            }
        }

        return $address_lines;
    }

    /**
     * Source value by tag
     * 
     * @param Source    $source
     * 
     * @return array    [$tag => $value]
     */
    public function sourceValuesByTag(Source $source): array
    {
        $source_values = [];
        $level1_source_tags = [
            'SOUR:DATA',
            'SOUR:AUTH',
            'SOUR:TITL',
            'SOUR:ABBR',
            'SOUR:PUBL',
            'SOUR:TEXT',
            'SOUR:REPO',
            'SOUR:REFN',
            'SOUR:RIN',
        ];

        foreach($source->facts() as $fact) {

            if (in_array($fact->tag(), $level1_source_tags )) {
 
                $source_values[$fact->tag()] = $fact->value();              
                
                switch($fact->tag()) {
                    case 'SOUR:REPO':
                        if($fact->attribute('CALN') !== '') {
                            $source_values['SOUR:REPO:CALN'] = $fact->attribute('CALN');
                        }
                        break;

                    case 'SOUR:DATA':
                        $date_range = RepositoryHierarchy::getDateRange($source, '%Y-%m-%d');
                        $date_range = $this->formatDateRange($date_range);

                        if($date_range !== '') {
                            $source_values['SOUR:DATA:EVEN:DATE'] = $date_range;
                        }
                        break;
                }
            }
        }

        //Substitue characters, which cause errors in XML/HTML
        foreach($source_values as $key=>$value) {
            $source_values[$key] = htmlspecialchars($value, ENT_XML1, 'UTF-8');
        }

        return $source_values;
    }

    /**
     * Format date range
     * 
     * @param string    $date_range
     * 
     * @return string   
     */
    public function formatDateRange(string $date_range): string {

        $date_range = $this->removeHtmlTags($date_range);
        $date_range = str_replace(' ', '', $date_range);
        $date_range = str_replace(I18N::translateContext('Start of date range', 'From'), '', $date_range); 
        $date_range = str_replace(I18N::translateContext('End of date range', 'To'), '/', $date_range); 
        
        $patterns = [
            '/\A(\d+)\/\Z/',            //  1659/
            '/\A\/(\d+)\Z/',            //  /1659
            '/\A(\d\d\d)\/(.*)/',       //  873/*
            '/\A(\d\d)\/(.*)/',         //  87/*
            '/\A(\d)\/(.*)/',           //  7/*
            '/\A(\d\d\d)-(.+?)\/(.*)/', //  873-*/*
            '/\A(\d\d)-(.+?)\/(.*)/',   //  87-*/*
            '/\A(\d)-(.+?)\/(.*)/',     //  7-*/*
            '/(.*)\/(\d\d\d)\Z/',       //  */873
            '/(.*)\/(\d\d)\Z/',         //  */87
            '/(.*)\/(\d)\Z/',           //  */8
            '/(.*)\/(\d\d\d)-(.+)/',    //  */873-
            '/(.*)\/(\d\d)-(.+)/',      //  */87-
            '/(.*)\/(\d)-(.+)/',        //  */8-
        ];
        $replacements = [
            '$1',                       //  1659/
            '$1',                       //  /1659
            '0$1/$2',                   //  873/*
            '00$1/$2',                  //  87/*
            '000$1/$2',                 //  8/*
            '0$1-$2/$3',                //  873-*/*
            '00$1-$2/$3',               //  87-*/*
            '000$1-$2/$3',              //  8-*/*
            '$1/0$2',                   //  */873
            '$1/00$2',                  //  */87
            '$1/000$2',                 //  */8
            '$1/0$2/$3',                //  */873-
            '$1/00$2/$3',               //  */87-
            '$1/000$2/$3',              //  */8-
        ];
        
        return preg_replace($patterns, $replacements, $date_range);     
    }    

    /**
     * Validate whether a string is an URL
     *
     * @param string  $url 
     * 
     * @return  bool
     */
    private function validateWhetherURL(string $url): bool {
        $path = parse_url($url, PHP_URL_PATH);
        $encoded_path = array_map('urlencode', explode('/', $path));
        $url = str_replace($path, implode('/', $encoded_path), $url);
    
        return filter_var($url, FILTER_VALIDATE_URL) ? true : false;
    }
    
    /**
     * Remove html tags
     *
     * @param string  $text 
     * 
     * @return string
     */
    private function removeHtmlTags(string $text): string {
            return preg_replace('/<[a-z]+[^<>]+?>([^<>]+?)<\/[a-z]+?>/', '$1', $text);
    }

}