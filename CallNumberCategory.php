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

use Fisharebest\Webtrees\Date;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Tree;

use function md5;
	
class CallNumberCategory  {

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
	private bool $is_root = FALSE;

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

	//List of date ranges of the sources of the category
	private array $date_ranges;	



   /**
     * Constructor.
     *
     */
    public function __construct(Tree $tree, array $delimiter_reg_exps, bool $is_root = FALSE, string $name = '', 
								string $full_name = '', int $hierarchy_level = 1, 
								array $sources = [], array $sub_categories = [])
    {
		$this->tree = $tree;
		$this->delimiter_reg_exps = $delimiter_reg_exps;
		$this->is_root = $is_root;
		$this->name = $name;
		$this->full_name = $full_name;
		$this->id = md5($full_name);
		$this->hierarchy_level = $hierarchy_level;
		$this->sources = $sources;
		$this->sub_categories = $sub_categories;
		$this->date_ranges = [];
	}
 
 /**
     * Get tree
     *
	 * @return Tree
     */
	public function getTree(): Tree {
		return $this->tree;
	}

    /**
     * Get delimiter
     *
	 * @return string
     */
	public function getDelimiter(): string {
		return $this->delimiter;
	}

	/**
     * Get getDelimiterRegExps
     *
	 * @return array
     */
	public function getDelimiterRegExps(): array {
		return $this->delimiter_reg_exps;
	}

 	/**
     * Whether it is the root category 
     *
	 * @return bool
     */
	public function isRoot(): bool {
		return $this->is_root;
	}

	/**
     * Get name
     *
	 * @return string
     */
	public function getName(): string {
		return $this->name;
	}

	/**
     * Get name to show in front end
     *
	 * @param bool $get_full_name
	 * 
	 * @return string
     */
	public function getFrontEndName(bool $get_full_name = false): string {

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
	public function getFullName(): string {
		return $this->full_name;
	}

	/**
     * Get id
     *
	 * @return string
     */
	public function getId(): string {
		return $this->id;
	}

	/**
     * Get hierarchy level
     *
	 * @return int
     */
	public function getHierarchyLevel(): int {
		return $this->hierarchy_level;
	}

   /**
     * Get sources
     *
	 * @return array
     */
	public function getSources(): array {
		return $this->sources;
	}

   /**
     * Add truncated call number for a source to the truncated call numbers list
     *
	 * @param Source
	 * @param string
	 */
	public function addTruncatedCallNumber(Source $source, string $truncated_call_number) {
		$this->truncated_call_numbers[$source->xref()] = $truncated_call_number;
	}

   /**
     * Get truncated call number for a source
     *
	 * @param Source 
	 * 
	 * @return string
     */
	public function getTruncatedCallNumber(Source $source): string {
		if (array_key_exists($source->xref(), $this->truncated_call_numbers)) {
			return $this->truncated_call_numbers[$source->xref()];
		}
		else {
			return '';
		}
	}

	/**
     * Get sub categories
     *
	 * @return array
     */
	public function getSubCategories(): array {
		return $this->sub_categories;
	}

   /**
     * Add source
     *
	 * @param Source
     */
	public function addSource(Source $source) {
		array_push($this->sources, $source);
	}

   /**
     * Add date range
     *
	 * @param Source
     */
	public function addDateRange(?Date $date) {
		if($date !== null) {
			array_push($this->date_ranges, $date);
		}
	}

   /**
     * Add sub category
     *
 	 * @param CallNumberCategory
    */
	public function addSubCategory(CallNumberCategory $sub_category) {
		array_push($this->sub_categories, $sub_category);
	}

 }
