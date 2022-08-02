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
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;
use SimpleXMLElement;

/**
 * Download Service for EAD XML files
 */
class DownloadEADxmlService
{
    //The xml object for EAD XML export
    private SimpleXMLElement $ead_xml;

    //The ResponseFactory used
    private ResponseFactoryInterface $response_factory;

    //The StreamFactory used
    private StreamFactoryInterface $stream_factory;

    /**
     * Constructor
     * 
     * @param string    $template_filename    The file name of the xml template 
     *
     */
    public function __construct(string $template_filename)
    {
        $this->ead_xml = simplexml_load_file($template_filename);
        $this->response_factory = app(ResponseFactoryInterface::class);
        $this->stream_factory   = new Psr17Factory();
    }

    /**
     * Create XML for a hierarchy of call numbers
     * 
     * @param CallNumberCategory  $root_category
     */
    public function createXML(CallNumberCategory  $root_category)
    {
        //Create XML
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
    public function export(SimpleXMLElement $xml, string $encoding = UTF8::NAME) 
    {
        $stream = fopen('php://memory', 'wb+');

        if ($stream === false) {
            throw new RuntimeException('Failed to create temporary stream');
        }

        $bytes_written = fwrite($stream, $xml->asXML());

        if ($bytes_written !== strlen($xml->asXML())) {
            throw new RuntimeException('Unable to write to stream.  Perhaps the disk is full?');
        }

        if (rewind($stream) === false) {
            throw new RuntimeException('Cannot rewind temporary stream');
        }

        return $stream;
    }

}