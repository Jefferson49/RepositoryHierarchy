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

use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function response;
use function view;

/**
 * Process a modal for EAD XML settings.
 */
class XmlExportSettingsModal implements RequestHandlerInterface
{
    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree = Validator::attributes($request)->tree();
        $repository_xref = Validator::attributes($request)->string('xref');

        $module_service = new ModuleService();
        $repository_hierarchy = $module_service->findByName(RepositoryHierarchy::MODULE_NAME);

        return response(view(RepositoryHierarchy::MODULE_NAME . '::modals/xml-export-settings', [
            'tree' 					    => $tree,
            'xref' 		                => $repository_xref,
            'finding_aid_title'         => $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_FINDING_AID_TITLE . $tree->id() . '_' . $repository_xref, ''),	
            'country_code' 			    => $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_COUNTRY_CODE . $tree->id() . '_' . $repository_xref, ''),	
            'main_agency_code' 		    => $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_MAIN_AGENCY_CODE . $tree->id() . '_' . $repository_xref, ''),
            'finding_aid_identifier'    => $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_FINDING_AID_IDENTIFIER . $tree->id() . '_' . $repository_xref, ''),	
            'finding_aid_url'           => $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_FINDING_AID_URL . $tree->id() . '_' . $repository_xref, ''),	
            'finding_aid_publisher'     => $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_FINDING_AID_PUBLISHER . $tree->id() . '_' . $repository_xref, ''),	
        ]));
    }
}