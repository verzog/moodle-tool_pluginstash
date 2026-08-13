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

namespace tool_pluginstash;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/testable_stasher.php');

/**
 * Unit tests for the stash/restore/manifest logic.
 *
 * @package    tool_pluginstash
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL v3 or later
 */
#[CoversClass(stasher::class)]
final class stasher_test extends \advanced_testcase {
    /**
     * The kill-switch config value is reflected by is_enabled().
     *
     * @return void
     */
    public function test_is_enabled_tracks_config(): void {
        $this->resetAfterTest();
        $stasher = new stasher();

        set_config('enabled', 0, 'tool_pluginstash');
        $this->assertFalse($stasher->is_enabled());

        set_config('enabled', 1, 'tool_pluginstash');
        $this->assertTrue($stasher->is_enabled());
    }

    /**
     * get_relative_dir() strips dirroot and normalises slashes.
     *
     * @return void
     */
    public function test_get_relative_dir_strips_dirroot(): void {
        global $CFG;
        $this->resetAfterTest();
        $stasher = new stasher();

        $this->assertSame('mod/forum', $stasher->get_relative_dir($CFG->dirroot . '/mod/forum'));
        $this->assertSame('mod/forum', $stasher->get_relative_dir($CFG->dirroot . '/mod/forum/'));
        // A path outside dirroot is returned with its leading slash trimmed.
        $this->assertSame('some/where', $stasher->get_relative_dir('/some/where'));
    }

    /**
     * get_addon_plugins() returns only non-standard plugins and never a core one.
     *
     * @return void
     */
    public function test_get_addon_plugins_returns_only_addons(): void {
        $this->resetAfterTest();
        $stasher = new stasher();

        $addons = $stasher->get_addon_plugins();
        $this->assertIsArray($addons);

        foreach ($addons as $component => $plugin) {
            $this->assertFalse($plugin->is_standard(), "{$component} should be a non-standard plugin");
        }

        // A known core plugin must never be listed as an add-on.
        $this->assertArrayNotHasKey('mod_assign', $addons);

        // The result matches the plugin manager's non-standard set, minus this tool itself.
        $expected = [];
        foreach (\core_plugin_manager::instance()->get_plugins() as $plugins) {
            foreach ($plugins as $plugin) {
                if ($plugin->component === 'tool_pluginstash') {
                    continue;
                }
                if (!$plugin->is_standard() && !empty($plugin->rootdir)) {
                    $expected[] = $plugin->component;
                }
            }
        }
        $this->assertEqualsCanonicalizing($expected, array_keys($addons));

        // This tool never lists itself as a stashable add-on.
        $this->assertArrayNotHasKey('tool_pluginstash', $addons);
    }

    /**
     * copy_dir() copies a whole tree, including nested directories.
     *
     * @return void
     */
    public function test_copy_dir_recurses(): void {
        $this->resetAfterTest();
        $stasher = new stasher();
        $dest = make_request_directory();

        $stasher->copy_dir(__DIR__ . '/fixtures/fakeplugin', $dest);

        $this->assertFileExists($dest . '/lib.php');
        $this->assertFileExists($dest . '/sub/note.txt');
    }

    /**
     * stash() copies the source tree and records a full manifest entry.
     *
     * @return void
     */
    public function test_stash_copies_tree_and_writes_manifest(): void {
        $this->resetAfterTest();
        $stashdir = make_request_directory();
        set_config('stashdir', $stashdir, 'tool_pluginstash');

        $source = __DIR__ . '/fixtures/fakeplugin';
        $stasher = new testable_stasher();
        $stasher->set_source('local_fake', $source, 2026010100);

        $results = $stasher->stash(['local_fake']);
        $this->assertSame(['local_fake' => true], $results);

        $reldir = $stasher->get_relative_dir($source);
        $this->assertFileExists($stashdir . '/' . $reldir . '/lib.php');
        $this->assertFileExists($stashdir . '/' . $reldir . '/sub/note.txt');

        $manifest = $stasher->read_manifest();
        $this->assertArrayHasKey('local_fake', $manifest);
        $entry = $manifest['local_fake'];
        $this->assertSame('local_fake', $entry['component']);
        $this->assertSame($reldir, $entry['reldir']);
        $this->assertSame(2026010100, $entry['version']);
        $this->assertArrayHasKey('stashed', $entry);
        $this->assertGreaterThan(0, $entry['stashed']);
    }

    /**
     * stash() reports false for a component it cannot resolve.
     *
     * @return void
     */
    public function test_stash_unknown_component_reports_false(): void {
        $this->resetAfterTest();
        set_config('stashdir', make_request_directory(), 'tool_pluginstash');

        $stasher = new testable_stasher();
        $this->assertSame(['does_not_exist' => false], $stasher->stash(['does_not_exist']));
    }

    /**
     * restore_component() copies back and honours the overwrite flag.
     *
     * @return void
     */
    public function test_restore_component_honours_overwrite(): void {
        $this->resetAfterTest();
        $stashdir = make_request_directory();
        set_config('stashdir', $stashdir, 'tool_pluginstash');

        $source = __DIR__ . '/fixtures/fakeplugin';
        $stasher = new testable_stasher();
        $stasher->set_source('local_fake', $source, 2026010100);
        $stasher->stash(['local_fake']);
        $reldir = $stasher->get_relative_dir($source);

        $target = make_request_directory();
        $restored = $target . '/' . $reldir . '/lib.php';

        // Restore into an empty target succeeds.
        $this->assertTrue($stasher->restore_component('local_fake', false, $target));
        $this->assertFileExists($restored);

        // Without overwrite, an existing destination is left untouched.
        file_put_contents($restored, 'CHANGED');
        $this->assertFalse($stasher->restore_component('local_fake', false, $target));
        $this->assertStringEqualsFile($restored, 'CHANGED');

        // With overwrite, the destination is replaced with the stashed copy.
        $this->assertTrue($stasher->restore_component('local_fake', true, $target));
        $this->assertStringEqualsFile($restored, file_get_contents($source . '/lib.php'));
    }

    /**
     * restore_component() reports false for a component missing from the manifest.
     *
     * @return void
     */
    public function test_restore_unknown_component_reports_false(): void {
        $this->resetAfterTest();
        set_config('stashdir', make_request_directory(), 'tool_pluginstash');

        $stasher = new stasher();
        $this->assertFalse($stasher->restore_component('missing_component', true, make_request_directory()));
    }

    /**
     * An overwriting restore replaces the destination and drops stale files.
     *
     * @return void
     */
    public function test_restore_overwrite_replaces_stale_files(): void {
        $this->resetAfterTest();
        $stashdir = make_request_directory();
        set_config('stashdir', $stashdir, 'tool_pluginstash');

        $source = __DIR__ . '/fixtures/fakeplugin';
        $stasher = new testable_stasher();
        $stasher->set_source('local_fake', $source, 2026010100);
        $stasher->stash(['local_fake']);
        $reldir = $stasher->get_relative_dir($source);

        $target = make_request_directory();
        $stasher->restore_component('local_fake', true, $target);

        // Introduce a file that does not exist in the stashed copy.
        $stale = $target . '/' . $reldir . '/stale.php';
        file_put_contents($stale, '<?php // Stale.');
        $this->assertFileExists($stale);

        // A second overwriting restore must remove it.
        $this->assertTrue($stasher->restore_component('local_fake', true, $target));
        $this->assertFileDoesNotExist($stale);
        $this->assertFileExists($target . '/' . $reldir . '/lib.php');
    }

    /**
     * copy_dir() does not follow symlinks out of the tree.
     *
     * @return void
     */
    public function test_copy_dir_skips_symlinks(): void {
        $this->resetAfterTest();
        $stasher = new stasher();

        $source = make_request_directory();
        file_put_contents($source . '/real.txt', 'real');
        $outside = make_request_directory();
        file_put_contents($outside . '/secret.txt', 'secret');
        symlink($outside, $source . '/link');

        $dest = make_request_directory();
        $stasher->copy_dir($source, $dest);

        $this->assertFileExists($dest . '/real.txt');
        $this->assertFileDoesNotExist($dest . '/link');
        $this->assertFileDoesNotExist($dest . '/link/secret.txt');
    }

    /**
     * stash() refuses a stash directory inside the code tree.
     *
     * @return void
     */
    public function test_stash_rejects_stashdir_in_code_tree(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('stashdir', $CFG->dirroot . '/admin', 'tool_pluginstash');

        $stasher = new testable_stasher();
        $stasher->set_source('local_fake', __DIR__ . '/fixtures/fakeplugin', 2026010100);

        $this->expectException(\moodle_exception::class);
        $stasher->stash(['local_fake']);
    }

    /**
     * read_manifest() throws when the manifest file is corrupt.
     *
     * @return void
     */
    public function test_read_manifest_throws_on_corrupt_json(): void {
        $this->resetAfterTest();
        $stashdir = make_request_directory();
        set_config('stashdir', $stashdir, 'tool_pluginstash');
        file_put_contents($stashdir . '/manifest.json', '{ not valid json ');

        $stasher = new stasher();
        $this->expectException(\moodle_exception::class);
        $stasher->read_manifest();
    }

    /**
     * The stash directory setting rejects a path inside the code tree.
     *
     * @return void
     */
    public function test_stashdir_setting_rejects_code_tree_path(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/adminlib.php');

        $setting = new admin_setting_stashdir('tool_pluginstash/stashdir', 'name', 'desc', '', PARAM_PATH);

        $this->assertIsString($setting->validate($CFG->dirroot . '/admin'));
        $this->assertTrue($setting->validate(make_request_directory()));
        $this->assertTrue($setting->validate(''));
    }
}
