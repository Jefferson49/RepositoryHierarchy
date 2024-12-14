<?php

/**
 * webtrees: online genealogy
 * Copyright (C) 2022 webtrees development team
 *                    <http://webtrees.net>
 *
 * Fancy Research Links (webtrees custom module):
 * Copyright (C) 2022 Carmen Just
 *                    <https://justcarmen.nl>
 *
 * Extended Relationships (webtrees custom module):
 * Copyright (C) 2022 Richard Cissee
 *                    <http://cissee.de>
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

namespace Jefferson49\Webtrees\Module\RepositoryHierarchy;

use Composer\Autoload\ClassLoader;
use Composer\InstalledVersions;

$loader = new ClassLoader(__DIR__);
$loader->addPsr4('Jefferson49\\Webtrees\\Module\\RepositoryHierarchy\\', __DIR__);
$loader->addPsr4('Matriphe\\ISO639\\', __DIR__ . "/vendor/matriphe/iso-639/src/");
$loader->register();

//Autoload the latest version of the common code library, which is shared between webtrees custom modules
$loader = new ClassLoader(__DIR__ .'/vendor');

try {
    $autoload_common_library_version = InstalledVersions::getVersion('jefferson49/webtrees-common');
}
catch (\OutOfBoundsException $e) {
    $autoload_common_library_version = '';
}

$local_composer_versions = require __DIR__ . '/vendor/composer/installed.php';
$local_common_library_version = $local_composer_versions['versions']['jefferson49/webtrees-common']['version'];

//If the found library is later than the current autoload version, prepend the found library to autoload
//This ensures that always the latest library version is autoloaded
if (version_compare($local_common_library_version, $autoload_common_library_version, '>')) {
    $loader->addPsr4('Jefferson49\\Webtrees\\Helpers\\', __DIR__ . '/vendor/jefferson49/webtrees-common/Helpers');
    $loader->addPsr4('Jefferson49\\Webtrees\\Internationalization\\', __DIR__. '/vendor/jefferson49/webtrees-common/Internationalization');    
    $loader->addPsr4('Jefferson49\\Webtrees\\Log\\', __DIR__ . '/vendor/jefferson49/webtrees-common/Log');
    $loader->register(true);    
}

return new RepositoryHierarchy();
