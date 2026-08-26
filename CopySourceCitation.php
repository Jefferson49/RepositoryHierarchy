<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2026 webtrees development team
 *                    <http://webtrees.net>
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
use Fisharebest\Webtrees\FlashMessages;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Validator;
use Jefferson49\Webtrees\Helpers\Functions;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function response;


/**
 * Copy a source citation.
 */
class CopySourceCitation implements RequestHandlerInterface
{
    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree    = Validator::attributes($request)->tree();
		$user    = Validator::attributes($request)->user();
        $gedcom  = Validator::queryParams($request)->string('gedcom', '');

		//If user does not have access to the tree
        if (Auth::accessLevel($tree, $user) === Auth::PRIV_PRIVATE) {
            FlashMessages::addMessage(I18N::translate('Access denied'));
            return response();
        }

        //Save received GEDCOM to session
        /** @var RepositoryHierarchy $repository_hierarchy To avoid IDE warnings */
        $repository_hierarchy = Functions::getFromContainer(RepositoryHierarchy::class);
        Session::put($repository_hierarchy->name() . RepositoryHierarchy::PREF_CITATION_GEDCOM . '_' . $tree->id(), $gedcom);

        FlashMessages::addMessage(I18N::translate('The source citation was copied to an internal clipboard.') . '<br>' .
            I18N::translate('The source citation can be added to other facts/events by clicking on the source icon in the edit area of the fact/event.'));

        return response();
    }
}
