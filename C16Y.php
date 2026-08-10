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

use Exception;
use Fisharebest\Localization\Locale;
use Fisharebest\Localization\Translator as FisharebestTranslator;
use Fisharebest\Localization\Translation as FisharebestTranslation;
use Fisharebest\Webtrees\Factories\LanguageFactory;
use Fisharebest\Webtrees\Registry;
use Fisharebest\Webtrees\Webtrees;
use Fisharebest\Webtrees\Repository;
use Fisharebest\Webtrees\I18N;
use Fisharebest\Webtrees\I18N\Translation;
use Fisharebest\Webtrees\I18N\Translator;

use function sprintf;


/**
 * Provide full names for call number category, using the translation mechanism of the Translator class
 */
class C16Y
{
    private static Translator|FisharebestTranslator $translator;

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
        $default_po_file = $path . $tree_name . '_' . $repository->xref() .'.po';
        $default_language_tag = 'en-GB';

        // webtrees versions beyond 2.2.6
        if (version_compare(Webtrees::VERSION, '2.2.6', '>')) {

            $language_tag = I18N::languageTag();
            $po_file = $path . $tree_name . '_' . $repository->xref() . '_' .  $language_tag .'.po';

            //Create language (is required by the Translator for the plural rule)
            $language_factory = Registry::container()->get(LanguageFactory::class);
            $default_language = $language_factory->fromLanguageTag($default_language_tag);

            if (is_string($language_tag)) {
                $language = $language_factory->fromLanguageTag($language_tag);
            }
            else {
                $language = $language_factory->fromLanguageTag($default_language_tag);
            }

            // Load the "translation" file
            try {
                if (version_compare(Webtrees::VERSION, '2.2.6', '>')) {
                    $stream       = fopen($po_file, 'rb');
                    $translations = Translation::fromPoStream($stream)->toArray();
                    self::$translator = new Translator($translations, $language->pluralRule());
                }
            } catch (Exception $ex) {
                //if no .po file is found, try the default file (without language tag)
                try {
                    $stream       = fopen($default_po_file, 'rb');
                    $translations = Translation::fromPoStream($stream)->toArray();
                    self::$translator = new Translator($translations, $default_language->pluralRule());
                } catch (Exception $ex) {
                    //if still no .po file is found, create empty translator
                    self::$translator = new Translator([], $default_language->pluralRule());
                }
            }
        }

        // webtrees versions until 2.2.6
        else {
            $language_tag = I18N::locale()->languageTag();            
            $po_file = $path . $tree_name . '_' . $repository->xref() . '_' .  $language_tag .'.po';

            //Create language (is required by the Translator for the plural rule)
            $default_language = Locale::create($default_language_tag);

            if (is_string($language_tag)) {
                $language = Locale::create($language_tag);
            }
            else {
                $language = Locale::create($default_language_tag);
            }

            // Load the "translation" file
            try {
                $translation  = new FisharebestTranslation($po_file);
                $translations = $translation->asArray();
                self::$translator = new FisharebestTranslator($translations, $language->pluralRule());
            } catch (Exception $ex) {
                //if no .po file is found, try the default file (without language tag)
                try {
                    $translation  = new FisharebestTranslation($default_po_file);
                    $translations = $translation->asArray();
                    self::$translator = new FisharebestTranslator($translations, $default_language->pluralRule());
                } catch (Exception $ex) {
                    //if still no .po file is found, create empty translator
                    self::$translator = new FisharebestTranslator([], $default_language->pluralRule());
                }
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
