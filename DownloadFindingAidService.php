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
class DownloadFindingAidService extends DownloadService
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
    public function generateHtml(bool $forPDF = false): string 
    {
        $language_tag = Session::get('language');
        
        //Convert different English 'en-*' tags to simple 'en' tag
        $language_tag = substr($language_tag, 0, 2) === 'en' ? 'en' : $language_tag;

        return view(RepositoryHierarchy::MODULE_NAME . '::finding-aid', [  
            'title'             => I18N::translate('Finding aid') . ': ' . $this->repository->fullName(),
            'language_tag'      => $language_tag,
            'root_category'     => $this->root_category,
            'repository'        => $this->repository,
            'forPDF'            => $forPDF,
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
        
        //Show PDF values (for debugging)
        //return $this->getPDFvalues($pdf);

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
        $pdf->setup();
        $pdf->tcpdf->setFontSize(10);

        //Load HTML and render
        $html = $this->generateHtml(true);
        $pdf->tcpdf->AddPage();
        $pdf->tcpdf->writeHTML($html);
        $pdf->tcpdf->lastPage();

        return $pdf;
    }

    /**
     * Return response with PDF values (for debugging purposes)
     * 
     * @param string    $filename       Name of download file without extension
     *
     * @return ResponseInterface
     */
    public function getPDFvalues(PdfRenderer $pdf): ResponseInterface 
    {
        //Get settings
        $margins = $pdf->tcpdf->getMargins();
        $original_margins = $pdf->tcpdf->getOriginalMargins();
        $scale_factor = $pdf->tcpdf->getScaleFactor();
        $width_page_current_units = $pdf->tcpdf->getPageWidth();
        $height_page_current_units = $pdf->tcpdf->getPageHeight();
        $left_margin = $margins['left'];
        $right_margin =  $margins['right'];
        $original_left_margin = $original_margins['left'];
        $original_right_margin =  $original_margins['right'];
        $font_size = $pdf->tcpdf->getFontSize();
        $font_size_pt = $pdf->tcpdf->getFontSizePt();
        $font_family = $pdf->tcpdf-> getFontFamily();
        $font_style = $pdf->tcpdf-> getFontStyle();
        
        //Create modal HTML text
        $text = 
        '<p>scale_factor: ' . $scale_factor . '</p>'.
        '<p>width_page_current_units: ' . $width_page_current_units . '</p>'.
        '<p>height_page_current_units: ' . $height_page_current_units . '</p>'.
        '<p>left_margin: ' . $left_margin . '</p>'.
        '<p>right_margin: ' . $right_margin . '</p>' .
        '<p>original_left_margin: ' . $original_left_margin . '</p>'.
        '<p>original_right_margin: ' . $original_right_margin . '</p>' .
        '<p>font_size: ' . $font_size . '</p>' .
        '<p>font_size_pt: ' . $font_size_pt . '</p>' .
        '<p>font_family: ' . $font_family . '</p>' .
        '<p>font_style: ' . $font_style . '</p>' .
        '';

        //Return modal with text
        return response(view(RepositoryHierarchy::MODULE_NAME . '::error', [  
            'text'  => $text,
            ]));              
    } 

    /**
     * Generate test HTML (for debugging)
     * 
     * @return string
     */
    public function generateTestHtml(bool $forPDF = false): string 
    {
        return view(RepositoryHierarchy::MODULE_NAME . '::test', []);
    }

}