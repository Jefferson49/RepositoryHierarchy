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

use Fisharebest\Webtrees\Auth;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Validator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function response;
use function view;

/**
 * Process a form to create a new source.
 */
class CreateSourceModal implements RequestHandlerInterface
{
    /**
     * Handle the create source modal request
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree               = Validator::attributes($request)->tree();
        $user               = Validator::attributes($request)->user();
        $source_repository  = Validator::attributes($request)->string('xref');

        $source_call_number = Validator::queryParams($request)->string('source_call_number');

        //If no user is logged in, show error text
        if (Auth::accessLevel($tree, $user) === Auth::PRIV_PRIVATE) {
            $error_text =   I18N::translate('Currently, you do not have the user rights to create new sources. In order to create new sources, you need to be logged in as a user.');

            return response(
                view(
                    RepositoryHierarchy::viewsNamespace() . '::modals/message',
                    [
                        'title' => I18N::translate('Create new sources'),
                        'text'  => $error_text,
                    ]
                )
            );
        }

        return response(
            view(
                RepositoryHierarchy::viewsNamespace() . '::modals/create-source',
                [
                    'tree'               => $tree,
                    'source_repository'  => $source_repository,
                    'source_call_number' => $source_call_number,
                ]
            )
        );
    }
}
