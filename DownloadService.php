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

use Fisharebest\Webtrees\I18N;

/**
 * Download Service
 */
class DownloadService
{

    //Download and EAD XML types    
    public const DOWNLOAD_OPTION_EAD_XML = 'download_option_ead_xml';
    public const DOWNLOAD_OPTION_APE_EAD = 'download_option_ape_ead';
    public const DOWNLOAD_OPTION_DDB_EAD = 'download_option_ddb_ead';
    public const DOWNLOAD_OPTION_ATOM = 'download_option_atom';
    public const DOWNLOAD_OPTION_HTML = 'download_option_html';
    public const DOWNLOAD_OPTION_PDF = 'download_option_pdf';
    public const DOWNLOAD_OPTION_TEXT = 'download_option_text';
    public const DOWNLOAD_OPTION_XML = 'download_option_xml';
    public const DOWNLOAD_OPTION_ALL = 'download_option_all';


    /**
     * Options for downloads
     *
     * @return array<string>
     */
    public static function getDownloadOptions(string $selection = self::DOWNLOAD_OPTION_ALL): array
    {        
        $xml_options = [
            self::DOWNLOAD_OPTION_EAD_XML   => I18N::translate('EAD XML'),
            //self::DOWNLOAD_OPTION_APE_EAD   => I18N::translate('apeEAD XML'),
            //self::DOWNLOAD_OPTION_DDB_EAD   => I18N::translate('DDB EAD XML'),
            //self::DOWNLOAD_OPTION_ATOM      => I18N::translate('AtoM EAD XML'),
        ];

        $text_options = [
            self::DOWNLOAD_OPTION_HTML      => I18N::translate('Finding aid as HTML'),
            self::DOWNLOAD_OPTION_PDF       => I18N::translate('Finding aid as PDF'),
        ];

        switch($selection) {

            case self::DOWNLOAD_OPTION_XML:
                $options = $xml_options;
                break;

            case self::DOWNLOAD_OPTION_TEXT:
                $options = $text_options;
                break;

            case self::DOWNLOAD_OPTION_ALL:
                $options = $text_options + $xml_options;
                break;

            default:
                $options = $text_options + $xml_options;
            }

        return $options;
    }    

    /**
     * Is XML download command
     *
     * @return bool
     */
    public static function isXmlDownloadCommand(string $command): bool
    {
        $xml_options = self::getDownloadOptions(self::DOWNLOAD_OPTION_XML);

        return  array_key_exists($command, $xml_options); 
    }
      
}