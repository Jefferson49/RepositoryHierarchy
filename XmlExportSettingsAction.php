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
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Process a form to change XML export settings.
 */
class XmlExportSettingsAction implements RequestHandlerInterface
{
    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree               = Validator::attributes($request)->tree();
        $repository_xref    = Validator::attributes($request)->string('xref');
        $params             = (array) $request->getParsedBody();

        $module_service = new ModuleService();
        $repository_hierarchy = $module_service->findByName(RepositoryHierarchy::MODULE_NAME);
    
        //Save received values to preferences
        $repository_hierarchy->setPreference(RepositoryHierarchy::PREF_FINDING_AID_TITLE . $tree->id() . '_' . $repository_xref, isset($params['finding_aid_title'])? $params['finding_aid_title'] : '');
        $repository_hierarchy->setPreference(RepositoryHierarchy::PREF_COUNTRY_CODE . $tree->id() . '_' . $repository_xref, isset($params['country_code'])? $params['country_code'] : '');   
        $repository_hierarchy->setPreference(RepositoryHierarchy::PREF_MAIN_AGENCY_CODE . $tree->id() . '_' . $repository_xref, isset($params['main_agency_code'])? $params['main_agency_code'] : '');
        $repository_hierarchy->setPreference(RepositoryHierarchy::PREF_FINDING_AID_IDENTIFIER . $tree->id() . '_' . $repository_xref, isset($params['finding_aid_identifier'])? $params['finding_aid_identifier'] : '');
        $repository_hierarchy->setPreference(RepositoryHierarchy::PREF_FINDING_AID_URL . $tree->id() . '_' . $repository_xref, isset($params['finding_aid_url'])? $params['finding_aid_url'] : '');
        $repository_hierarchy->setPreference(RepositoryHierarchy::PREF_FINDING_AID_PUBLISHER . $tree->id() . '_' . $repository_xref, isset($params['finding_aid_publisher'])? $params['finding_aid_publisher'] : '');

        return response([      
            'html'  => view(RepositoryHierarchy::MODULE_NAME . '::modals/message', [  
                'title' => I18N::translate('EAD XML seetings'),
                'text'  => I18N::translate('The EAD XML seetings have been changed'),
                ])
            ]);
    }
}
