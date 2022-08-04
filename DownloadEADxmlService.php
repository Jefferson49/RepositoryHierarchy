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


/**
 * Download Service for EAD XML files
 */
class DownloadEADxmlService
{
    //The xml object for EAD XML export
    private DOMDocument $ead_xml;

    //The top level collection node within the xml
    private DOMNode $collection;

    //The ResponseFactory used
    private ResponseFactoryInterface $response_factory;

    //The StreamFactory used
    private StreamFactoryInterface $stream_factory;


    /**
     * Constructor
     * 
     * @param string    $template_filename    The path of the xml template file name with file extension 
     *
     */
    public function __construct(string $template_filename)
    {
        //New DOM and settings for a nice xml format
        $this->ead_xml = new DOMDocument();
        $this->ead_xml->preserveWhiteSpace = false;
        $this->ead_xml->formatOutput = true;

        $this->ead_xml->load($template_filename);
        $this->response_factory = app(ResponseFactoryInterface::class);
        $this->stream_factory   = new Psr17Factory();
        $this->linked_record_service = new LinkedRecordService();

        //Get xml element for collection
        $dom = $this->ead_xml->getElementsByTagName('archdesc')->item(0);
        $dom = $dom->getElementsByTagName('dsc')->item(0);
        $this->collection = $dom->getElementsByTagName('c')->item(0);
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
            $this->addSeries($dom, $category);

            //Add sources to xml structure
            foreach ($category->getSources() as $source) {
                $this->addItem($dom, $source);
            }

            //Call recursion for sub categories
            $this->createXMLforCategory($dom, $category);
        }
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
                        $date_range = RepositoryHierarchy::getDateRange($source);
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
     * Substitue special characters in URL
     * 
     * @param Source    $source
     * 
     * @return array    [$tag => $value]
     */
    public function substitueSpecialcharsInURL(string $text): string {
        return $this->validateURL($text) ? htmlspecialchars($text, ENT_XML1, 'UTF-8') : $text;
    }
     
    /**
     * Add a series to EAD XML
     * 
     * @param DOMDocument           $dom
     * @param CallNumberCategory    $call_number_category
     */
    public function addSeries(DOMNode $dom, CallNumberCategory $call_number_category)
    {
         //<c>
         $dom = $dom->appendChild($this->ead_xml->createElement('c'));

         $attribute = $this->ead_xml->createAttribute('level');
         $attribute->value = 'series';
         $dom->appendChild($attribute);
 
         $attribute = $this->ead_xml->createAttribute('id');
         $attribute->value = $call_number_category->getName();
         $dom->appendChild($attribute);
 
             //<did>
             $dom = $dom->appendChild($this->ead_xml->createElement('did'));
 
             $attribute = $this->ead_xml->createAttribute('unitid');
             $attribute->value = $call_number_category->getFullName();
             $dom->appendChild($attribute);
     
             $attribute = $this->ead_xml->createAttribute('unittitle');
             $attribute->value = $call_number_category->getName();
             $dom->appendChild($attribute);
    }
  
    /**
     * Add an item to EAD XML
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

        if (isset($fact_values['SOUR:TITL'])) {
            $dom->appendChild(new DOMAttr('id', $fact_values['SOUR:TITL']));
        }

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
     * @return resource
     */
    public function export(DOMDocument $dom, string $encoding = UTF8::NAME) 
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
     * Validate whether a string is an URL
     *
     * @param string  $url 
     * 
     * @return  bool
     */
    private function validateURL(string $url): bool {
        $path = parse_url($url, PHP_URL_PATH);
        $encoded_path = array_map('urlencode', explode('/', $path));
        $url = str_replace($path, implode('/', $encoded_path), $url);
    
        return filter_var($url, FILTER_VALIDATE_URL) ? true : false;
    }
}