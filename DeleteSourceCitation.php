<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2023 webtrees development team
 *                    <http://webtrees.net>
 *
 * RepositoryHierarchy (webtrees custom module):
 * Copyright (C) 2023 Markus Hemprich
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

use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Services\IndividualFactsService;
use Fisharebest\Webtrees\Services\LinkedRecordService;
use Fisharebest\Webtrees\Services\ModuleService;
use Fisharebest\Webtrees\Validator;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function redirect;

/**
 * Delete a source citation.
 */
class DeleteSourceCitation implements RequestHandlerInterface
{
    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree      = Validator::attributes($request)->tree();
        $xref      = Validator::attributes($request)->isXref()->string('xref');
        $fact_id   = Validator::attributes($request)->string('fact_id');
        $xref_type = Validator::queryParams($request)->string('xref_type');
        $gedcom    = Validator::queryParams($request)->string('gedcom');

        $module_service = new ModuleService();
        $linked_record_service = new LinkedRecordService();
        $individual_facts_service = new IndividualFactsService($linked_record_service, $module_service);

        if ($xref_type === 'INDI') {
            $individual  = Registry::individualFactory()->make($xref, $tree);
            $facts = $individual->facts();
            $family_facts = $individual_facts_service->familyFacts($individual, new Collection);
            $facts = $facts->merge($family_facts);    
        }
        elseif ($xref_type === 'FAM') {
            $family  = Registry::familyFactory()->make($xref, $tree);
            $facts = $family->facts();
        }
        else {
            $facts = new Collection;
        }

        foreach ($facts as $fact) {
            if ($fact->id() === $fact_id && $fact->canEdit()) {
                $old_gedcom = $fact->gedcom();
                $last_pos = strrpos($old_gedcom, $gedcom);
                $new_gedcom = substr($old_gedcom, 0, $last_pos).str_replace($gedcom, '', substr($old_gedcom, $last_pos));
                $record = $fact->record();
                $record->updateFact($fact_id, $new_gedcom, false);
                break;
            }
        }

        $url = Validator::parsedBody($request)->isLocalUrl()->string('url', $record->url());

        return redirect($url);
    }
}
