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

use Cissee\WebtreesExt\MoreI18N;
use Fisharebest\Webtrees\Http\RequestHandlers\PendingChanges;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Module\ModuleDataFixInterface;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function route;

/**
 * Run data-fix for call number categories
 */
class CallNumberDataFix implements RequestHandlerInterface
{
    use ViewResponseTrait;

    private ModuleService $module_service;

    /**
     * DataFix constructor.
     *
     * @param ModuleService $module_service
     */
    public function __construct(ModuleService $module_service)
    {
        $this->module_service = $module_service;
    }

    /**
     * Handle the request for the call number data fix
     *
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

        $data_fixes = $this->module_service->findByInterface(ModuleDataFixInterface::class);
        $data_fix = RepositoryHierarchy::activeModuleName();
        $module = $data_fixes->get($data_fix);
        $module->setDataFixParams($tree, $repository_xref, $category_name, $category_full_name);

        $this->layout = 'layouts/administration';

        if ($module instanceof ModuleDataFixInterface) {
            $title       = $module->title() . ' — ' . e($tree->title());
            $page_url    = route(self::class, ['data_fix' => $data_fix, 'tree' => $tree->name()]);
            $pending_url = route(PendingChanges::class, ['tree' => $tree->name(), 'url' => $page_url]);

            return $this->viewResponse(
                'admin/data-fix-page',
                [
                    RepositoryHierarchy::VAR_DATA_FIX               => $module,
                    RepositoryHierarchy::VAR_DATA_FIX_TITLE         => $title,
                    CallNumberCategory::VAR_TREE                    => $tree,
                    RepositoryHierarchy::VAR_DATA_FIX_PENDING_URL   => $pending_url,
                ]
            );
        }

        //Default: continue with general data fix selection
        $title = MoreI18N::xlate('Data fixes') . ' — ' . e($tree->title());
        $data_fixes = $this->module_service->findByInterface(ModuleDataFixInterface::class, false, true);

        return $this->viewResponse(
            'admin/data-fix-select',
            [
                RepositoryHierarchy::VAR_DATA_FIX_TITLE => $title,
                RepositoryHierarchy::VAR_DATA_FIXES     => $data_fixes,
                CallNumberCategory::VAR_TREE            => $tree,
            ]
        );
    }
}
