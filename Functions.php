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

use DOMDocument;
use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Contracts\UserInterface;
use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\Date\AbstractCalendarDate;
use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleListInterface;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Repository;
use Fisharebest\Webtrees\Tree;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

use RuntimeException;

/**
 * General functions and utils, which can be used by several classes
 */
class Functions extends \Jefferson49\Webtrees\Helpers\Functions
{
    /**
     * Write DOM document to a stream
     *
     * @param DOMDocument $dom
     *
     * @return resource
     */
    public static function export(DOMDocument $dom)
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
     * Get level 1 or 2 address tags for repositories in GEDCOM
     *
     * @param int $level
     *
     * @return array [string with address tags]
     */
    public static function getGedcomAddressTags(int $level): array
    {
        switch($level) {
            case 1:
                return [
                    'REPO:ADDR',
                    'REPO:PHON',
                    'REPO:EMAIL',
                    'REPO:FAX',
                    'REPO:WWW',
                ];

            case 2:
                return [
                    'ADR1',
                    'ADR2',
                    'ADR3',
                    'CITY',
                    'STAE',
                    'POST',
                    'CTRY',
                ];

            default:
                return [];
        }
    }

    /**
     * Get address lines of a repository
     *
     * @param Repository $repository
     *
     * @return array [string with address line]
     */
    public static function getRepositoryAddressLines(Repository $repository): array
    {
        $address_lines = [];
        $level1_address_tags = self::getGedcomAddressTags(1);
        $level2_address_tags = self::getGedcomAddressTags(2);


        foreach ($repository->facts() as $fact) {
            if ($fact->tag() === 'REPO:ADDR') {
                if ($fact->value() !== '') {
                    $matches = preg_split('/\n/', $fact->value(), -1, PREG_SPLIT_NO_EMPTY);
                    $line = 1;
                    foreach ($matches as $match) {
                        $address_lines[$fact->tag() . ':LINE' . $line] = $match;
                        $line++;
                    }
                }

                foreach ($level2_address_tags as $tag) {
                    if ($fact->attribute($tag) !== '') {
                        $address_lines[$tag] = Registry::elementFactory()->make('REPO:ADDR:' . $tag)->label() . ': ' . $fact->attribute($tag);
                    }
                }
            } else {
                if (in_array($fact->tag(), $level1_address_tags)) {
                    $address_lines[$fact->tag()] = $fact->label() . ': ' . $fact->value();
                }
            }
        }

        return $address_lines;
    }

    /**
     * Get URL of a repository
     *
     * @param Repository $repository
     *
     * @return string
     */
    public static function getRepositoryUrl(Repository $repository): string
    {
        foreach ($repository->facts() as $fact) {
            if ($fact->tag() === 'REPO:WWW') {
                if ($fact->value() !== '') {
                    $matches = preg_split('/\n/', $fact->value(), -1, PREG_SPLIT_NO_EMPTY);
                    return $matches[0];
                }
            }
        }
        return '';
    }

    /**
     * Display a date range
     *
     * @param Date   $date_range
     * @param Tree   $tree
     * @param string $date   format
     *
     * @return string
     */
    public static function displayDateRange(Date $date_range = null, Tree $tree = null, string $date_format = null): string
    {
        if (($date_range !== null) && $date_range->isOK()) {
            return $date_range->display($tree, $date_format);
        } else {
            return '';
        }
    }

    /**
     * Display a date range in ISO format
     *
     * @param Date   $date_range
     * @param string $delimiter  [ISO 8601 allows: '/' or '--']
     *
     * @return string
     */
    public static function displayISOformatForDateRange(Date $date_range = null, string $delimiter = '/'): string
    {
        if (($date_range !== null) && $date_range->isOK()) {
            $min_date = $date_range->minimumDate();
            $max_date = $date_range->maximumDate();

            $date_range_text = $min_date->format('%Y-%m-%d') . $delimiter . $max_date->format('%Y-%m-%d');

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

            return preg_replace($patterns, $replacements, $date_range_text);
        }

        return '';
    }

    /**
     * Validate whether a string is an URL
     *
     * @param string $url
     *
     * @return bool
     */
    public static function validateWhetherURL(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);
        $encoded_path = array_map('urlencode', explode('/', $path));
        $url = str_replace($path, implode('/', $encoded_path), $url);

        return filter_var($url, FILTER_VALIDATE_URL) ? true : false;
    }

    /**
     * Remove html tags
     *
     * @param string $text
     *
     * @return string
     */
    public static function removeHtmlTags(string $text): string
    {
        return preg_replace('/<[a-z]+[^<>]+?>([^<>]+?)<\/[a-z]+?>/', '$1', $text);
    }

    /**
     * Get overall date range for a set of date ranges,
     * i.e. minimum and maximum dates of all the date ranges
     *
     * @param array $dates [Date]
     *
     * @return Date
     */
    public static function getOverallDateRange(array $dates): ?Date
    {
        $dates_found = 0;

        foreach ($dates as $date) {
            $dates_found++;

            //Calclulate new max/min values for date range if more than one date is found
            if ($dates_found > 1) {
                if (AbstractCalendarDate::compare($date->minimumDate(), $date_range->minimumDate()) < 1) {
                    $min_date = $date->minimumDate();
                } else {
                    $min_date = $date_range->minimumDate();
                }
                if (AbstractCalendarDate::compare($date->maximumDate(), $date_range->maximumDate()) > 0) {
                    $max_date = $date->maximumDate();
                } else {
                    $max_date = $date_range->maximumDate();
                }

                $date_range = new Date('FROM ' . $min_date->format('%A %O %E') . ' TO ' . $max_date->format('%A %O %E'));
            } else {
                $date_range = $date;
            }
        }

        return ($dates_found > 0) ? $date_range : null;
    }

    /**
     * Get collection for an array
     *
     * @param array $items
     *
     * @return Collection
     */
    public static function getCollectionForArray(array $items): Collection
    {
        $collection = new Collection();

        foreach ($items as $item) {
            $collection->push($item);
        }

        return $collection;
    }

    /**
     * Get xref of default repository
     *
     * @param AbstractModule $module
     * @param Tree           $tree
     * @param UserInterface  $user
     *
     * @return string
     */
    public static function getDefaultRepositoryXref(AbstractModule $module, Tree $tree, UserInterface $user): string
    {
        Auth::checkComponentAccess($module, ModuleListInterface::class, $tree, $user);

        $repositories = DB::table('other')
            ->where('o_file', '=', $tree->id())
            ->where('o_type', '=', Repository::RECORD_TYPE)
            ->get()
            ->map(Registry::repositoryFactory()->mapper($tree))
            ->filter(GedcomRecord::accessFilter());

        foreach ($repositories as $repository) {
            return $repository->xref();
        }
        return '';
    }

    /**
     * Get meta repositories for a repository
     *
     * @param Repository $repository
     *
     * @return string    xref
     */
    public static function getMetaRepository(Repository $repository): string
    {
        $meta_repository = '';

        foreach ($repository->facts() as $fact) {
            if (($fact->tag() === 'REPO:REFN') && ($fact->attribute('TYPE') === RepositoryHierarchy::SOUR_REFN_TYPE_META_REPO)) {
                $meta_repository = $fact->value();
                break;
            }
        }

        return $meta_repository;
    }
}
