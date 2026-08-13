<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Behat step definitions for tool_pluginstash.
 *
 * @package    tool_pluginstash
 * @category   test
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before Moodle is installed.

require_once(__DIR__ . '/../../../../../lib/behat/behat_base.php');

/**
 * Step definitions to seed the stash for tool_pluginstash acceptance tests.
 *
 * @package    tool_pluginstash
 * @category   test
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL v3 or later
 */
class behat_tool_pluginstash extends behat_base {
    /**
     * Place a fake stashed plugin on disk and record it in the manifest.
     *
     * @Given the plugin :component is stashed as :reldir
     * @param string $component frankenstyle component name.
     * @param string $reldir path of the plugin relative to dirroot.
     * @return void
     */
    public function the_plugin_is_stashed(string $component, string $reldir): void {
        $stasher = new \tool_pluginstash\stasher();
        $stashdir = $stasher->get_stash_dir();

        $target = $stashdir . '/' . $reldir;
        make_writable_directory($target);
        file_put_contents($target . '/version.php', "<?php // Fake stashed plugin.\n");

        $manifestfile = $stashdir . '/' . \tool_pluginstash\stasher::MANIFEST_FILE;
        $manifest = [];
        if (is_readable($manifestfile)) {
            $manifest = json_decode(file_get_contents($manifestfile), true) ?: [];
        }
        $manifest[$component] = [
            'component' => $component,
            'reldir'    => $reldir,
            'version'   => 2026010100,
            'stashed'   => time(),
        ];
        file_put_contents($manifestfile, json_encode($manifest, JSON_PRETTY_PRINT));
    }
}
