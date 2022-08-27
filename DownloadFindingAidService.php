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

use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Encodings\UTF8;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Report\PdfRenderer;
use Fisharebest\Webtrees\Repository;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Fisharebest\Webtrees\Session;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use RuntimeException;
use TCPDF;

use function date;

/**
 * Download Service for EAD XML files
 */
class DownloadFindingAidService
{

    //The ResponseFactory used
    private ResponseFactoryInterface $response_factory;

    //The StreamFactory used
    private StreamFactoryInterface $stream_factory;

    //The LinkedRecordService used
    private LinkedRecordService $linked_record_service;

    //The repository, to which the service relates
    private Repository $repository;

    //The root category of the Repository Hierarchy, to which the service relates
    private CallNumberCategory $root_category;

    //The user, for which the service is executed
    private UserInterface $user;


    /**
     * Constructor
     * 
     * @param Repository            $repository    
     * @param CallNumberCategory    $root_category
     * @param UserInterface         $user
     *
     */
    public function __construct(Repository $repository, 
                                CallNumberCategory $root_category,
                                UserInterface $user)
    {
        //Initialize variables
        $this->repository = $repository;
        $this->root_category = $root_category;
        $this->user = $user;
    }

    /**
     * Generate HTML for finding aid
     * 
     * @return string
     */
    public function generateHtml(): string 
    {
        return view(RepositoryHierarchy::MODULE_NAME . '::finding-aid', [  
            'title'         => I18N::translate('Finding aid'),
            'language_tag'  => Session::get('language'),
            'root_category' => $this->root_category,
            'repository'    => $this->repository,
            ]);
    }

    /**
     * Return HTML response to download a finding aid
     * 
     * @param string    $filename       Name of download file without extension
     *
     * @return ResponseInterface
     */
    public function downloadHtmlResponse(string $filename): ResponseInterface 
    {
        $html = $this->generateHtml();
        $stream = fopen('php://memory', 'wb+');

        if ($stream === false) {
            throw new RuntimeException('Failed to create temporary stream');
        }

        //Write html to stream
        $bytes_written = fwrite($stream, $html);

        if ($bytes_written !== strlen($html)) {
            throw new RuntimeException('Unable to write to stream.  Perhaps the disk is full?');
        }

        if (rewind($stream) === false) {
            throw new RuntimeException('Cannot rewind temporary stream');
        }

        //Prepare and return the response
        $resource = $stream;
        $stream_factory = new Psr17Factory();
        $response_factory = app(ResponseFactoryInterface::class);
        $stream = $stream_factory->createStreamFromResource($resource);

        return $response_factory->createResponse()
            ->withBody($stream)
            ->withHeader('content-type', 'text/html; charset=' . UTF8::NAME)
            ->withHeader('content-disposition', 'attachment; filename="' . addcslashes($filename, '"') . '.html"');  
    }

    /**
     * Return PDF response to download a finding aid
     * 
     * @param string    $filename       Name of download file without extension
     *
     * @return ResponseInterface
     */
    public function downloadPDFResponse(string $filename): ResponseInterface 
    {
        $pdf = $this->createPDF();
        $stream = fopen('php://memory', 'wb+');

        if ($stream === false) {
            throw new RuntimeException('Failed to create temporary stream');
        }

        //Write pdf to stream
        $bytes_written = fwrite($stream, $pdf->tcpdf->Output('doc.pdf', 'S'));

        if ($bytes_written !== strlen($pdf->tcpdf->Output('doc.pdf', 'S'))) {
            throw new RuntimeException('Unable to write to stream.  Perhaps the disk is full?');
        }

        if (rewind($stream) === false) {
            throw new RuntimeException('Cannot rewind temporary stream');
        }

        //Prepare and return the response
        $resource = $stream;
        $stream_factory = new Psr17Factory();
        $response_factory = app(ResponseFactoryInterface::class);
        $stream = $stream_factory->createStreamFromResource($resource);

        return $response_factory->createResponse()
            ->withBody($stream)
            ->withHeader('content-type', 'application/pdf; charset=' . UTF8::NAME)
            ->withHeader('content-disposition', 'attachment; filename="' . addcslashes($filename, '"') . '.pdf"');  
    }

    /**
     * Create PDF
     *     
     * @return PdfRenderer
     */
    public function createPDF(): PdfRenderer 
    {
        //Create PDF document and settings
        $pdf = new PdfRenderer();
        $pdf->default_font_size = 10;
        $pdf->setup();

        //Load HTML and render
        $html = $this->generateHtml();
        $pdf->tcpdf->AddPage();
        $pdf->tcpdf->writeHTML($html);
        $pdf->tcpdf->lastPage();

        return $pdf;
    }

}