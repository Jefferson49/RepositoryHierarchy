<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2026 webtrees development team
 *					  <http://webtrees.net>
 *
 * RepositoryHierarchy (webtrees custom module):
 * Copyright (C) 2026 Markus Hemprich
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

namespace Jefferson49\Webtrees\Module\RepositoryHierarchy;

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\Http\RequestHandlers\PendingChanges;
use Fisharebest\Webtrees\Http\ViewResponseTrait;
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

    //Module service to search and find modules
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
		$user               = Validator::attributes($request)->user();
        $repository_xref    = Validator::attributes($request)->string(CallNumberCategory::VAR_XREF);
        $category_name      = Validator::queryParams($request)->string(CallNumberCategory::VAR_CATEGORY_NAME);
        $category_full_name = Validator::queryParams($request)->string(CallNumberCategory::VAR_CATEGORY_FULL_NAME);

		//If user does not have access
        if (Auth::accessLevel($tree, $user) === Auth::PRIV_PRIVATE) {
            return response();
		}
		
        /** @var RepositoryHierarchy $repository_hierarchy To avoid IDE warnings */
        $repository_hierarchy = $this->module_service->findByName(RepositoryHierarchy::activeModuleName());
        $repository_hierarchy->setDataFixParams($tree, $repository_xref, $category_name, $category_full_name);

        $this->layout = 'layouts/administration';

        $title       = $repository_hierarchy->title() . ' — ' . e($tree->title());
        $data_fix    = RepositoryHierarchy::activeModuleName();
        $page_url    = route(self::class, ['data_fix' => $data_fix, 'tree' => $tree->name()]);
        $pending_url = route(PendingChanges::class, ['tree' => $tree->name(), 'url' => $page_url]);

        return $this->viewResponse(
            'admin/data-fix-page',
            [
                RepositoryHierarchy::VAR_DATA_FIX               => $repository_hierarchy,
                RepositoryHierarchy::VAR_DATA_FIX_TITLE         => $title,
                CallNumberCategory::VAR_TREE                    => $tree,
                RepositoryHierarchy::VAR_DATA_FIX_PENDING_URL   => $pending_url,
            ]
        );
    }
}
