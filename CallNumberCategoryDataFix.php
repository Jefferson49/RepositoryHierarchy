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

namespace Jefferson49\Webtrees\Module\CallNumberCategoryDataFixNamespace;

use Fisharebest\Webtrees\GedcomRecord;
use Fisharebest\Webtrees\Http\RequestHandlers\PendingChanges;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\AbstractModule;
use Fisharebest\Webtrees\Module\ModuleDataFixInterface;
use Fisharebest\Webtrees\Module\ModuleDataFixTrait;
use Fisharebest\Webtrees\Source;
use Fisharebest\Webtrees\Tree;
use Fisharebest\Webtrees\Validator;
use Illuminate\Support\Collection;
use Jefferson49\Webtrees\Module\RepositoryHierarchyNamespace\CallNumberCategory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function route;

/**
 * Run data-fix for call number categories
 */
class CallNumberCategoryDataFix extends AbstractModule implements   RequestHandlerInterface, 
                                                                    ModuleDataFixInterface
{
    use ViewResponseTrait;
    use ModuleDataFixTrait;

    //Strings cooresponding to variable names
    public const VAR_TREE = 'tree';
    public const VAR_DATA_FIX = 'data_fix';
    public const VAR_DATA_FIXES = 'data_fixes';
    public const VAR_DATA_FIX_TITLE = 'title';
    public const VAR_DATA_FIX_TYPES = 'types';
    public const VAR_DATA_FIX_CATEGORY_NAME_REPLACE = 'category_name_replace';
    public const VAR_DATA_FIX_PENDING_URL = 'pending_url';     

   //The tree, to which the repository hierarchy relates
    private Tree $tree;

    //The xref string of the repository, to which the repository hierarchy relates
    private string $repository_xref;

    //The full name of the call number category to be fixed
    private string $data_fix_category_full_name = '';

    //The name of the call number category to be fixed
    private string $data_fix_category_name = '';

    /**
     * DataFix constructor.
     *
     */
    public function __construct()
    {
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\AbstractModule::title()
     */
    public function title(): string
    {
        return ('Call number category data fix');
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleDataFixInterface::fixOptions()
     */
    public function fixOptions(Tree $tree): string
    {
        //If data fix is called from wrong context
        if (!isset($this->repository_xref)) return '';

        return view($this->name() . '::options', [
            CallNumberCategory::VAR_REPOSITORY_XREF     => $this->repository_xref,
            CallNumberCategory ::VAR_CATEGORY_FULL_NAME => $this->data_fix_category_full_name,
            CallNumberCategory::VAR_CATEGORY_NAME       => $this->data_fix_category_name,
            self::VAR_DATA_FIX_CATEGORY_NAME_REPLACE    => $this->data_fix_category_name,
            self::VAR_DATA_FIX_TYPES                    => [Source::RECORD_TYPE => I18N::translate('Sources')],
            ]
        );
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleDataFixInterface::doesRecordNeedUpdate()
     */
    public function doesRecordNeedUpdate(GedcomRecord $record, array $params): bool
    {
        $search = preg_quote($params[CallNumberCategory::VAR_CATEGORY_FULL_NAME], '/');
        $regex  = '/\n1 REPO @'. $params[CallNumberCategory::VAR_REPOSITORY_XREF] . '@.*?\n2 CALN +' . $search . '[^$]*?$/';

        $test = preg_match($regex, $record->gedcom());
        return preg_match($regex, $record->gedcom()) === 1;
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleDataFixInterface::previewUpdate()
     */
    public function previewUpdate(GedcomRecord $record, array $params): string
    {
        $old = $record->gedcom();
        $new = $this->updateGedcom($record, $params);

        return $this->data_fix_service->gedcomDiff($record->tree(), $old, $new);
    }

    /**
     * {@inheritDoc}
     * @see \Fisharebest\Webtrees\Module\ModuleDataFixInterface::updateRecord()
     */
    public function updateRecord(GedcomRecord $record, array $params): void
    {
        $record->updateRecord($this->updateGedcom($record, $params), false);
    }

    /**
     * Update Gedcom for a record
     * 
     * @param GedcomRecord  $record
     * @param array         $params
     *
     * @return string
     */
    private function updateGedcom(GedcomRecord $record, array $params): string
    {
        $repository_xref = $params[CallNumberCategory::VAR_REPOSITORY_XREF];
        $pos = strpos($params[CallNumberCategory::VAR_CATEGORY_FULL_NAME],$params[CallNumberCategory::VAR_CATEGORY_NAME]);
        $truncated_category = substr($params[CallNumberCategory::VAR_CATEGORY_FULL_NAME], 0, $pos);
        $new_category_name = $truncated_category . $params[self::VAR_DATA_FIX_CATEGORY_NAME_REPLACE];
    
        $search  = preg_quote($params[CallNumberCategory::VAR_CATEGORY_FULL_NAME], '/');
        $regex  = '/(\n1 REPO @'. $repository_xref .'@.*?\n2 CALN +)' . $search . '([^$]*?$)/';

        $replace = '$1' . addcslashes($new_category_name, '$\\') . '$2';

        return preg_replace($regex, $replace, $record->gedcom());
    }

    /**
     * A  list of all source records that might need fixing.
     *
     * @param Tree                 $tree
     * @param array<string,string> $params
     *
     * @return Collection<int,object>
     */
    protected function sourcesToFix(Tree $tree, array $params): ?Collection
    {
        if ($params[CallNumberCategory::VAR_CATEGORY_NAME] === '' || $params[self::VAR_DATA_FIX_CATEGORY_NAME_REPLACE] === '') {
            return null;
        }

        $search = '%' . addcslashes($params[CallNumberCategory::VAR_CATEGORY_FULL_NAME], '\\%_') . '%';

        return  $this->sourcesToFixQuery($tree, $params)
            ->where('s_file', '=', $tree->id())
            ->where('s_gedcom', 'LIKE', $search)
            ->pluck('s_id');
    }

    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree               = Validator::attributes($request)->tree();
        $repository_xref    = Validator::attributes($request)->string(CallNumberCategory::VAR_XREF);
        $category_name      = Validator::attributes($request)->string(CallNumberCategory::VAR_CATEGORY_NAME);
        $category_full_name = Validator::attributes($request)->string(CallNumberCategory::VAR_CATEGORY_FULL_NAME);

        $this->tree = $tree;
        $this->repository_xref = $repository_xref;
        $this->data_fix_category_name = $category_name;
        $this->data_fix_category_full_name = $category_full_name;

        $this->layout = 'layouts/administration';
    
        $title       = $this->title() . ' — ' . e($tree->title());
        $page_url    = route(self::class, ['data_fix' => $this->name(), 'tree' => $tree->name()]);
        $pending_url = route(PendingChanges::class, ['tree' => $tree->name(), 'url' => $page_url]);

        return $this->viewResponse('admin/data-fix-page', [
            self::VAR_DATA_FIX               => $this,
            self::VAR_DATA_FIX_TITLE         => $title,
            self::VAR_TREE                   => $tree,
            self::VAR_DATA_FIX_PENDING_URL   => $pending_url,          
        ]);
    }
}
