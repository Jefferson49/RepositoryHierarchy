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

use Exception;
use Fisharebest\Localization\Locale;
use Fisharebest\Localization\Locale\LocaleInterface;
use Fisharebest\Localization\Translator;
use Fisharebest\Localization\Translation;
use Fisharebest\Webtrees\Repository;
use Fisharebest\Webtrees\Session;
use Fisharebest\Webtrees\Module\ModuleLanguageInterface;

use function sprintf;

/**
 * Provide full names for call number category, using the translation mechanism of the Translator class
 */
class C16Y
{
    private static ?ModuleLanguageInterface $language;

    private static LocaleInterface $locale;

    private static Translator $translator;

    /**
     * Constructor
     *
     * @param string     $path
     * @param Repository $repository
     *
     * @return void
     */
    public function __construct(string $path, string $tree_name, Repository $repository)
    {
        $po_file = $path . $tree_name . '_' . $repository->xref() . '_' .  Session::get('language') .'.po';
        $default_po_file = $path . $tree_name . '_' . $repository->xref() .'.po';

        //Create locale (is required by the Translator for the plural rule)
        $locale = Locale::create(Session::get('language'));
        $default_locale = Locale::create('en-GB');

        // Load the "translation" file
        try {
            $translation  = new Translation($po_file);
            $translations = $translation->asArray();
            self::$translator = new Translator($translations, $locale->pluralRule());
        } catch (Exception $ex) {
            //if no .po file is found, try the default file (without language tag)
            try {
                $translation  = new Translation($default_po_file);
                $translations = $translation->asArray();
                self::$translator = new Translator($translations, $default_locale->pluralRule());
            } catch (Exception $ex) {
                //if still no .po file is found, create empty translator
                self::$translator = new Translator([], $default_locale->pluralRule());
            }
        }
    }

    /**
     * Get the title for a call number category
     *
     * @param string $call_number_category_full_name
     *
     * @return string
     */
    public static function getCallNumberCategoryTitle(string $call_number_category_full_name): string
    {
        $title = self::$translator->translate($call_number_category_full_name);

        if ($title === $call_number_category_full_name) {
            return '';
        } else {
            return sprintf($title);
        }
    }
}
