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

use Fisharebest\Localization\Locale;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Tree;
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
	//Module service to search and find modules
	private ModuleService $module_service;
	
	/**
    * Constructor.
    *
    * @param ModuleService $module_service
    */
    public function __construct(ModuleService $module_service)
    {
        $this->module_service = $module_service;
    }	

    /**
    * Update EAD/XML preferences/settings from former module versions
    *
    * @param int                 $tree_id
    * @param string              $repository_xref
    * @param int                 $user_id
    * @param string              $delimiter_expression
    *
    * @return void
    */
    public static function updatePreferenes(Tree $tree, string $repository_xref, int $user_id, string $delimiter_expression)
    {
        $module_service = new ModuleService();
        $repository_hierarchy = $module_service->findByName(RepositoryHierarchy::activeModuleName());

        $replaces_list = [
            ['search'   => RepositoryHierarchy::OLD_PREF_FINDING_AID_TITLE, 'replace'  => RepositoryHierarchy::PREF_FINDING_AID_TITLE],
            ['search'   => RepositoryHierarchy::OLD_PREF_FINDING_AID_IDENTIFIER, 'replace'  => RepositoryHierarchy::PREF_FINDING_AID_IDENTIFIER],
            ['search'   => RepositoryHierarchy::OLD_PREF_FINDING_AID_URL, 'replace'  => RepositoryHierarchy::PREF_FINDING_AID_URL],
            ['search'   => RepositoryHierarchy::OLD_PREF_FINDING_AID_PUBLISHER, 'replace'  => RepositoryHierarchy::PREF_FINDING_AID_PUBLISHER],
            ['search'   => RepositoryHierarchy::OLD_PREF_MAIN_AGENCY_CODE, 'replace'  => RepositoryHierarchy::PREF_MAIN_AGENCY_CODE],
        ];

        foreach ($replaces_list as $replace_pair) {

            foreach ([$user_id, RepositoryHierarchy::ADMIN_USER_STRING] as $user_id_in_pref) {

                $old_setting = $repository_hierarchy->getPreference($replace_pair['search'] . $tree->id() . '_' . $repository_xref . '_' . $user_id_in_pref, '');
                $new_setting = $repository_hierarchy->getPreference($replace_pair['replace'] . $tree->id() . '_' . $repository_xref . '_' . $user_id_in_pref, '');

                if ($old_setting !== '') {

                    //If new preference does not already exist
                    if ($new_setting === '') {

                        //If URL matches old webtrees URL without pretty URLs (before bugfix #25), create new default URL
                        if (($replace_pair['search'] === RepositoryHierarchy::OLD_PREF_FINDING_AID_URL) && str_contains($old_setting, '%2Fdelimiter_expression%2F')) {
                            $old_setting = self::defaultURL($tree, $repository_xref,  $delimiter_expression);
                        }

                        //Save old setting to new preference name
                        $repository_hierarchy->setPreference($replace_pair['replace'] . $tree->id() . '_' . $repository_xref . '_' . $user_id_in_pref, $old_setting);
                    }
        
                    //Delete old setting (i.e. set to '')
                    $repository_hierarchy->setPreference($replace_pair['search'] . $tree->id() . '_' . $repository_xref . '_' . $user_id_in_pref, '');
                }
    
            }
        }
    }	

    /**
    * Provide default URL (i.e. PDF download from webtrees installation)
    *
    * @param int $tree_id
    * @param string $repository_xref
    * @param string $delimiter_expression
    *
    * @return string
    */
    public static function defaultURL(Tree $tree, string $repository_xref, string $delimiter_expression): string
    {
        return route(RepositoryHierarchy::class, [
            'tree'                  => $tree->name(),
            'xref'                  => $repository_xref,
            'command'               => DownloadService::DOWNLOAD_OPTION_PDF,
            ]
            ) .
            '&delimiter_expression=' . $delimiter_expression;
    }

    /**
     * Handle a request to view a modal for EAD XML settings
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree                   = Validator::attributes($request)->tree();
        $user                   = Validator::attributes($request)->user();
        $repository_xref        = Validator::attributes($request)->string('xref');
        $command                = Validator::attributes($request)->string('command');
        $delimiter_expression   = Validator::queryParams($request)->string('delimiter_expression');

        $repository_hierarchy = $this->module_service->findByName(RepositoryHierarchy::activeModuleName());
        $repository = Registry::repositoryFactory()->make($repository_xref, $tree);

        //If XML settings shall be loaded from administrator
        if (($command === RepositoryHierarchy::CMD_LOAD_ADMIN_XML_SETTINGS) &&
            ($repository_hierarchy->getPreference(RepositoryHierarchy::PREF_ALLOW_ADMIN_XML_SETTINGS))
        ) {
            $user_id = RepositoryHierarchy::ADMIN_USER_STRING;
        } else {
            $user_id = $user->id();
        }

        //Update old preferences/settings
        self::updatePreferenes($tree, $repository_xref, $user_id, $delimiter_expression);


        $locale = Locale::create(Session::get('language'));
        //ISO-3166 country code
        $country_code = $locale->territory()->code();
        $main_agency_code_default = $country_code . '-XXXXX';

        $finding_aid_url = $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_FINDING_AID_URL . $tree->id() . '_' . $repository_xref . '_' . $user_id, '');

        if ($finding_aid_url === '') {

            $finding_aid_url = $this->defaultURL( $tree, $repository_xref,  $delimiter_expression);
        } 

        return response(
            view(
                RepositoryHierarchy::viewsNamespace() . '::modals/xml-export-settings',
                [
                    'tree'                      => $tree,
                    'xref'                      => $repository_xref,
                    'finding_aid_title'         => $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_FINDING_AID_TITLE . $tree->id() . '_' . $repository_xref . '_' . $user_id, I18N::translate('Finding aid') . ': ' . Functions::removeHtmlTags($repository->fullName())),
                    'country_code'              => $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_COUNTRY_CODE . $tree->id() . '_' . $repository_xref . '_' . $user_id, $country_code),
                    'main_agency_code'          => $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_MAIN_AGENCY_CODE . $tree->id() . '_' . $repository_xref . '_' . $user_id, $main_agency_code_default),
                    'finding_aid_identifier'    => $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_FINDING_AID_IDENTIFIER . $tree->id() . '_' . $repository_xref . '_' . $user_id, I18N::translate('Finding aid')),
                    'finding_aid_url'           => $finding_aid_url,
                    'finding_aid_publisher'     => $repository_hierarchy->getPreference(RepositoryHierarchy::PREF_FINDING_AID_PUBLISHER . $tree->id() . '_' . $repository_xref . '_' . $user_id, Functions::removeHtmlTags($repository->fullName())),
                    'show_load_from_admin'      => true,
                ]
            )
        );
    }
}
