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

use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Tree;
use Illuminate\Support\Collection;
use RuntimeException;

use function md5;

/**
 * Call number category class
 */
class CallNumberCategory
{
    //Strings corresponding to variable names, e.g. for views
    public const VAR_TREE = 'tree';
    public const VAR_XREF = 'xref';
    public const VAR_REPOSITORY_XREF = 'repository_xref';
    public const VAR_CATEGORY_NAME = 'category_name';
    public const VAR_CATEGORY_FULL_NAME = 'category_full_name';

    //Default category name for sources without call number
    public const EMPTY_CATEGORY_NAME = 'Sources without call number';

    //Default category name for sources without category in the call number
    public const DEFAULT_CATEGORY_NAME = 'Default category';

    //Tree, to which this call number category belongs
    private Tree $tree;

    //The delimiter used for this call number category
    private string $delimiter;

    //An arrray with regular expressions describing the delimiters used for this call number category
    private array $delimiter_reg_exps = [];

    //Whether it is the root category
    private bool $is_root = false;

    //The name of this call number category
    private string $name ='';

    //The full name of this call number category including the full trunk of the call number
    private string $full_name ='';

    //An id (typically a hash value), which identifies this call number category
    private string $id = '';

    //Hierarchy level, on which this call number category is located in the hierarchy of sub categories
    private int $hierarchy_level = 1;

    //List of sources related to this category
    private array $sources = [];

    //List of truncated call numbers for sources
    //[source xref => truncated call number]
    private array $truncated_call_numbers = [];

    //List of related sub categories. Provides a recursive structure for a hierarchy of sub categories
    private array $sub_categories = [];

    //Overall date range of the category
    private ?Date $overall_date_range = null;


    /**
     * Constructor
     */
    public function __construct(
        Tree $tree,
        array $delimiter_reg_exps,
        bool $is_root = false,
        string $name = '',
        string $full_name = '',
        int $hierarchy_level = 1,
        array $sources = [],
        array $sub_categories = []
    ) {
        $this->tree = $tree;
        $this->delimiter_reg_exps = $delimiter_reg_exps;
        $this->is_root = $is_root;
        $this->name = $name;
        $this->full_name = $full_name;
        $this->id = md5($full_name);
        $this->hierarchy_level = $hierarchy_level;
        $this->sources = $sources;
        $this->sub_categories = $sub_categories;
    }

    /**
     * Get tree
     *
     * @return Tree
     */
    public function getTree(): Tree
    {
        return $this->tree;
    }

    /**
     * Get delimiter
     *
     * @return string
     */
    public function getDelimiter(): string
    {
        return $this->delimiter;
    }

    /**
     * Get getDelimiterRegExps
     *
     * @return array
     */
    public function getDelimiterRegExps(): array
    {
        return $this->delimiter_reg_exps;
    }

    /**
     * Whether it is the root category
     *
     * @return bool
     */
    public function isRoot(): bool
    {
        return $this->is_root;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get name to show in front end
     *
     * @param bool $get_full_name
     *
     * @return string
     */
    public function getFrontEndName(bool $get_full_name = false): string
    {
        if ($get_full_name) {
            $name = $this->full_name;
        } else {
            $name = $this->name;
        }

        if (strpos($name, CallNumberCategory::EMPTY_CATEGORY_NAME) !== false) {
            return I18N::translate('Sources without call number');
        } elseif (strpos($name, CallNumberCategory::DEFAULT_CATEGORY_NAME) !== false) {
            return I18N::translate('Default call number category');
        } else {
            return $name;
        }
    }

    /**
     * Get full_name
     *
     * @return string
     */
    public function getFullName(): string
    {
        return $this->full_name;
    }

    /**
     * Get id
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Get hierarchy level
     *
     * @return int
     */
    public function getHierarchyLevel(): int
    {
        return $this->hierarchy_level;
    }

    /**
     * Get sources
     *
     * @return array
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    /**
     * Get sub categories
     *
     * @return array
     */
    public function getSubCategories(): array
    {
        return $this->sub_categories;
    }

    /**
     * Get overall date range
     *
     * @return Date
     */
    public function getOverallDateRange(): ?Date
    {
        return $this->overall_date_range;
    }

    /**
     * Add source
     *
     * @param Source $source
     *
     * @return void
     */
    public function addSource(Source $source)
    {
        array_push($this->sources, $source);
    }

    /**
     * Add sub category
     *
     * @param CallNumberCategory $sub_category
     *
     * @return void
     */
    public function addSubCategory(CallNumberCategory $sub_category)
    {
        array_push($this->sub_categories, $sub_category);
    }

    /**
     * Calculate date range
     *
     * @param CallNumberCategory
     *
     * @return Date
     */
    public function calculateDateRange(): ?Date
    {
        $date_ranges = [];

        //Collect all date ranges for sub categories
        $sub_categories = $this->getSubCategories();

        foreach ($sub_categories as $sub_category) {
            $date_range = $sub_category->calculateDateRange();
            if ($date_range !== null) {
                array_push($date_ranges, $date_range);
            }
        }

        //Collect all date ranges for sources
        $sources = $this->sources;

        foreach ($sources as $source) {
            $date_range = Functions::getDateRangeForSource($source);
            if ($date_range !== null) {
                array_push($date_ranges, $date_range);
            }
        }

        $this->overall_date_range = Functions::getOverallDateRange($date_ranges);

        return $this->overall_date_range;
    }

    /**
     * Add truncated call number for a source to the truncated call numbers list
     *
     * @param Source $source
     * @param string $truncated_call_number
     *
     * @return void
     */
    public function addTruncatedCallNumber(Source $source, string $truncated_call_number)
    {
        $this->truncated_call_numbers[$source->xref()] = $truncated_call_number;
    }

    /**
     * Get truncated call number for a source
     *
     * @param Source $source
     *
     * @return string
     */
    public function getTruncatedCallNumber(Source $source): string
    {
        if (array_key_exists($source->xref(), $this->truncated_call_numbers)) {
            return $this->truncated_call_numbers[$source->xref()];
        } else {
            return '';
        }
    }

    /**
     * Display date range
     *
     * @param string $delimiter [ISO 8601 allows: '/' odr '--']
     *
     * @return string
     */
    public function displayISODateRange(string $delimiter = '/'): string
    {
        if (($this->overall_date_range !== null) && $this->overall_date_range->isOK()) {
            return Functions::getISOformatForDateRange($this->overall_date_range, $delimiter);
        }
        return '';
    }

    /**
     * Sort call number categories by call number
     *
     * @param Collection $categories
     *
     * @return Collection
     */
    public static function sortCallNumberCategoriesByName(Collection $categories): Collection
    {
        return $categories->sort(
            function (CallNumberCategory $category1, CallNumberCategory $category2) {
                return strnatcmp($category1->getFullName(), $category2->getFullName());
            }
        );
    }

    /**
     * Save a file with C16Y data for call number category titles
     *
     * @param string             $path
     * @param string             $repository_xref
     * @param CallNumberCategory $root_category
     *
     * @return void
     */
    public static function saveC16YFile(string $path, string $repository_xref, CallNumberCategory $root_category): void
    {
        $po_file = $path . $repository_xref . '.php';

        //Delete file if already existing
        if (file_exists($po_file)) {
            unlink($po_file);
        }

        if (!$fp = fopen($po_file, "c")) {
            throw new RuntimeException('Cannot open file: ' . $po_file);
        }

        if (fwrite($fp, "<?php\n") === false) {
            throw new RuntimeException('Cannot write to file: ' . $po_file);
        }

        self::writeCallNumberCategoryTitleToStream($fp, $root_category);

        fclose($fp);
    }

    /**
     * Write call number category title to stream
     *
     * @param $stream
     * @param CallNumberCategory $category
     *
     * @return Collection
     */
    public static function writeCallNumberCategoryTitleToStream($stream, CallNumberCategory $category): void
    {
        //$sub_categories = Functions::getCollectionForArray($category->getSubCategories());
        //$sub_categories = CallNumberCategory::sortCallNumberCategoriesByName($sub_categories);
        $sub_categories = $category->getSubCategories();

        foreach ($sub_categories as $sub_category) {
            fwrite($stream, "gettext('" . $sub_category->getFrontEndName(true) . "');\n");
            self::writeCallNumberCategoryTitleToStream($stream, $sub_category);
        }
    }
}
