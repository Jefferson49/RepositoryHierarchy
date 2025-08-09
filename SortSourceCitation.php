<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2025 webtrees development team
 *                    <http://webtrees.net>
 *
 * RepositoryHierarchy (webtrees custom module):
 * Copyright (C) 2025 Markus Hemprich
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
 * Sort source citations
 */
class SortSourceCitation implements RequestHandlerInterface
{
    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $tree              = Validator::attributes($request)->tree();
        $xref              = Validator::attributes($request)->isXref()->string('xref');
        $fact_id           = Validator::attributes($request)->string('fact_id');
        $xref_type         = Validator::queryParams($request)->string('xref_type');
        $matched_citations = Validator::queryParams($request)->array('matched_citations');
        $old_position      = Validator::queryParams($request)->integer('old_position', 1);
        $new_position      = Validator::queryParams($request)->integer('new_position', 1);

        $module_service = new ModuleService();
        $linked_record_service = new LinkedRecordService();
        $individual_facts_service = new IndividualFactsService($linked_record_service, $module_service);
        $ordered_citations = [];

        //Re-order source citations
        if ($new_position > $old_position) {

            for ($i = 0; $i < $old_position; $i++) {
                $ordered_citations[$i] = $matched_citations[$i];
            }

            $ordered_citations[$old_position] = $matched_citations[$new_position];
            $ordered_citations[$new_position] = $matched_citations[$old_position];

            for ($i = $new_position +1 ; $i < sizeof($matched_citations); $i++) {
                $ordered_citations[$i] = $matched_citations[$i];
            }
        }
        elseif ($new_position < $old_position) {

            for ($i = 0; $i < $new_position; $i++) {
                $ordered_citations[$i] = $matched_citations[$i];
            }

            $ordered_citations[$new_position] = $matched_citations[$old_position];
            $ordered_citations[$old_position] = $matched_citations[$new_position];

            for ($i = $old_position + 1; $i < sizeof($matched_citations); $i++) {
                $ordered_citations[$i] = $matched_citations[$i];
            }
        }

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

                $gedcom = $fact->gedcom();

                //Delete old citations
                foreach ($ordered_citations as $citation) {
                    $gedcom = str_replace($citation, "", $gedcom);
                    $gedcom = str_replace("\n\n", "\n", $gedcom);
                }

                //Delete \n at the end
                if(substr($gedcom, -1) === "\n") {
                    $gedcom = substr($gedcom, 0, strlen($gedcom) -1);
                }

                //Add ordered citations at the end
                foreach ($ordered_citations as $citation) {
                    $gedcom = $gedcom . "\n" . $citation;
                }

                $record = $fact->record();
                $record->updateFact($fact_id, $gedcom, false);
                break;
            }
        }

        $url = Validator::parsedBody($request)->isLocalUrl()->string('url', $record->url());

        return redirect($url);
    }
}
