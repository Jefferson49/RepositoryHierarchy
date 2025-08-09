<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2025 webtrees development team
 *                    <http://webtrees.net>
 *
 * Extended Relationships (webtrees custom module):
 * Copyright (C) 2022 Richard Cissee
 *                    <http://cissee.de>
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

use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\Validator;
use Fisharebest\Webtrees\Factories\MarkdownFactory;
use Jefferson49\Webtrees\Internationalization\MoreI18N;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

use function view;

/**
 * Help texts used in the Repository Hierarchy module
 */
class RepositoryHierarchyHelpTexts implements RequestHandlerInterface
{
    /**
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $topic = Validator::attributes($request)->string('topic');

        switch ($topic) {
            case 'Delimiter Expression':

                $title = I18N::translate('How to use delimiter expressions?');
                $markdown_text =
I18N::translate('A delimiter is a sequence of one or more characters for specifying the boundary between separate, independent regions in a text. Use delimiters to cut call numbers into chunks of call number categories. The call number categories will be used to construct a hierarchy of call numbers.').'
1. '. I18N::translate('Use a single delimiter, e.g.').' "**/**" '. I18N::translate('or').' "**-**"
2. '. I18N::translate('Use a set of delimiters separated by').' "**'. RepositoryHierarchy::DELIMITER_SEPARATOR . '**" ' .  I18N::translate('e.g.') . ' "**/;-**"'.  I18N::translate('or') .' "**#;,**"'.'
3. '. I18N::translate('Use a regular expressions, which contain the delimiter in brackets, e.g.').' **'. I18N::translate('Fonds').'(/)** ' . I18N::translate('or').' **(-)'.MoreI18N::xlate('Item').'**
4. '. I18N::translate('Use a set of regular expressions separated by') .' "**'. RepositoryHierarchy::DELIMITER_SEPARATOR . '**" '.I18N::translate('e.g.'). ' **'.I18N::translate('Fonds').'(/)'.I18N::translate('Series').RepositoryHierarchy::DELIMITER_SEPARATOR.I18N::translate('Series').'(-)'.MoreI18N::xlate('Item').'**
###### '.I18N::translate('Example').' 1:
+ '. I18N::translate('Call numbers').':
    + '. I18N::translate('Fonds').'/'.I18N::translate('Series').'/'.MoreI18N::xlate('Item') . ' 1
    + '. I18N::translate('Fonds').'/'.I18N::translate('Series').'/'.MoreI18N::xlate('Item') . ' 2
+ '. I18N::translate('Delimiter expression'). ': **/**'.'
+ '. I18N::translate('Repository Hierarchy').':
    + '.I18N::translate('Fonds').'/
        + '.I18N::translate('Series').'/
            + '.MoreI18N::xlate('Item').' 1
            + '.MoreI18N::xlate('Item').' 2
###### '.I18N::translate('Example').' 2:
+ '. I18N::translate('Call numbers').':
    + '. I18N::translate('Fonds').'/'.I18N::translate('Series').'-'.MoreI18N::xlate('Item') . ' 1
    + '. I18N::translate('Fonds').'/'.I18N::translate('Series').'-'.MoreI18N::xlate('Item') . ' 2
+ '. I18N::translate('Delimiter expression'). ': **/;-**'.'
+ '. I18N::translate('Repository Hierarchy').':
    + '.I18N::translate('Fonds').'/
        + '.I18N::translate('Series').'-
            + '.MoreI18N::xlate('Item').' 1
            + '.MoreI18N::xlate('Item').' 2
###### '.I18N::translate('Example').' 3:
+ '. I18N::translate('Call numbers').':
    + '. I18N::translate('Film Number'). ' 5
    + '. I18N::translate('Film Number'). ' 8
+ '. I18N::translate('Delimiter expression'). ': **'. I18N::translate('Film').'( )'. I18N::translate('Number').'**'.'
+ '. I18N::translate('Repository Hierarchy').':
    + '.I18N::translate('Film').'
        + '.I18N::translate('Number').' 5
        + '.I18N::translate('Number').' 8
###### '.I18N::translate('Example').' 4:
+ '. I18N::translate('Call numbers').':
    + '. I18N::translate('Fonds').' A, '. I18N::translate('Biography Number').' 1
    + '. I18N::translate('Fonds').' D, '. I18N::translate('Photo Number').' 7
+ '. I18N::translate('Delimiter expression:'). ' **'. I18N::translate('Fonds').' \[A-D\](, );( )'. I18N::translate('Number').'**'.'
+ '. I18N::translate('Repository Hierarchy').':
    + '.I18N::translate('Fonds').' A,
        + '.I18N::translate('Biography').'
            + '.I18N::translate('Number').' 1
    + '.I18N::translate('Fonds').' D,
        + '.I18N::translate('Photo').'
            + '.I18N::translate('Number').' 7
##### '.I18N::translate('Important Note').'
+ '. I18N::translate('Please note that the following characters need to be escaped if not used as meta characters in a regular expression').': **' . RepositoryHierarchy::ESCAPE_CHARACTERS . RepositoryHierarchy::DELIMITER_SEPARATOR .'**
+ '. I18N::translate('For example, use').' "**\\\[**" ' . I18N::translate('instead of') . ' "**\[**" '. I18N::translate('or') . ' "**\\\+**" '. I18N::translate('instead of') . ' "**\+**" ' . I18N::translate('if they should be recognized as plain characters.').'
##### '. I18N::translate('Detailed description').'
[Readme](https://github.com/Jefferson49/RepositoryHierarchy)
##### '. I18N::translate('Information about regular expressions').'
[Wikipedia](https://en.wikipedia.org/wiki/Regular_expression)';


                $markdown_factory = new MarkdownFactory();
                $text = $markdown_factory->markdown($markdown_text);

                break;

            default:
                $title = I18N::translate('Help');
                $text = I18N::translate('The help text has not yet been written for this item.');
                break;
        }

        $html = view(
            'modals/help',
            [
                'title' => $title,
                'text'  => $text,
            ]
        );

        return response($html);
    }
}
