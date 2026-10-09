<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace tool_pluginstash;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/testable_stasher.php');

/**
 * Unit tests for the stash/restore/manifest logic.
 *
 * @package    tool_pluginstash
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
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

        // The result matches the plugin manager's installed, on-disk, non-standard
        // set, minus this tool itself.
        $expected = [];
        foreach (\core_plugin_manager::instance()->get_plugins() as $plugins) {
            foreach ($plugins as $plugin) {
                if ($plugin->component === 'tool_pluginstash') {
                    continue;
                }
                if ($plugin->is_standard() || empty($plugin->versiondb)) {
                    continue;
                }
                if (!empty($plugin->rootdir) && is_dir($plugin->rootdir)) {
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

    /**
     * zip_component() builds a zip with the plugin under its own top-level folder.
     *
     * @return void
     */
    public function test_zip_component_builds_installable_zip(): void {
        $this->resetAfterTest();
        $stashdir = make_request_directory();
        set_config('stashdir', $stashdir, 'tool_pluginstash');

        $source = __DIR__ . '/fixtures/fakeplugin';
        $stasher = new testable_stasher();
        $stasher->set_source('local_fake', $source, 2026010100);
        $stasher->stash(['local_fake']);

        $zippath = make_request_directory() . '/out.zip';
        $this->assertTrue($stasher->zip_component('local_fake', $zippath));
        $this->assertFileExists($zippath);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zippath) === true);
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }
        $zip->close();

        $this->assertContains('fakeplugin/lib.php', $names);
        $this->assertContains('fakeplugin/sub/note.txt', $names);
    }

    /**
     * zip_component() returns false for a component that is not stashed.
     *
     * @return void
     */
    public function test_zip_component_unknown_returns_false(): void {
        $this->resetAfterTest();
        set_config('stashdir', make_request_directory(), 'tool_pluginstash');

        $stasher = new stasher();
        $this->assertFalse($stasher->zip_component('not_stashed', make_request_directory() . '/x.zip'));
    }

    /**
     * get_stashable_plugins() hides up-to-date stashed plugins but offers newer or unstashed ones.
     *
     * @return void
     */
    public function test_get_stashable_plugins_hides_up_to_date_stash(): void {
        $this->resetAfterTest();
        set_config('stashdir', make_request_directory(), 'tool_pluginstash');

        $stasher = new testable_stasher();
        $stasher->set_source('local_fake', __DIR__ . '/fixtures/fakeplugin', 2026010100);
        $stasher->stash(['local_fake']);

        $plugin = static function (string $component, int $version): \stdClass {
            return (object) ['component' => $component, 'versiondisk' => $version];
        };

        // Same version as the stash: hidden. Not stashed at all: offered.
        $addons = [
            'local_fake' => $plugin('local_fake', 2026010100),
            'local_other' => $plugin('local_other', 2026010100),
        ];
        $this->assertSame(['local_other'], array_keys($stasher->get_stashable_plugins($addons)));

        // A newer installed version than the stash: offered again.
        $addons['local_fake'] = $plugin('local_fake', 2026020100);
        $this->assertSame(['local_fake', 'local_other'], array_keys($stasher->get_stashable_plugins($addons)));
    }

    /**
     * get_stashable_plugins() offers a plugin again if its stashed copy is missing from disk.
     *
     * @return void
     */
    public function test_get_stashable_plugins_offers_missing_stash_copy(): void {
        $this->resetAfterTest();
        $stashdir = make_request_directory();
        set_config('stashdir', $stashdir, 'tool_pluginstash');

        $source = __DIR__ . '/fixtures/fakeplugin';
        $stasher = new testable_stasher();
        $stasher->set_source('local_fake', $source, 2026010100);
        $stasher->stash(['local_fake']);
        remove_dir($stashdir . '/' . $stasher->get_relative_dir($source));

        $addons = ['local_fake' => (object) ['component' => 'local_fake', 'versiondisk' => 2026010100]];
        $this->assertSame(['local_fake'], array_keys($stasher->get_stashable_plugins($addons)));
    }

    /**
     * Stash the fixture plugin as local_fake at a given version, for the reinstall tests.
     *
     * @param int $version version to record in the manifest.
     * @return testable_stasher stasher with a temporary target root for reinstalls.
     */
    private function stash_fake_for_reinstall(int $version = 2026010100): testable_stasher {
        set_config('stashdir', make_request_directory(), 'tool_pluginstash');

        $stasher = new testable_stasher();
        $stasher->set_source('local_fake', __DIR__ . '/fixtures/fakeplugin', $version);
        $stasher->stash(['local_fake']);
        $stasher->set_target_root(make_request_directory());
        return $stasher;
    }

    /**
     * Data provider for test_get_reinstall_statuses().
     *
     * @return array test cases of [version on disk, version in database, expected status].
     */
    public static function reinstall_status_provider(): array {
        return [
            'Never installed' => [null, null, stasher::REINSTALL_READY],
            'Missing from disk, same version in database' => [null, 2026010100, stasher::REINSTALL_READY],
            'Missing from disk, older version in database' => [null, 2025010100, stasher::REINSTALL_READY],
            'Missing from disk, newer version in database' => [null, 2026020100, stasher::REINSTALL_DOWNGRADE],
            'Older version on disk' => [2025010100, 2025010100, stasher::REINSTALL_READY],
            'Same version on disk' => [2026010100, 2026010100, stasher::REINSTALL_CURRENT],
            'Newer version on disk' => [2026020100, 2026020100, stasher::REINSTALL_CURRENT],
        ];
    }

    /**
     * get_reinstall_statuses() compares the stashed version with the code tree and database.
     *
     * @param int|null $diskversion version in the code tree, or null if missing.
     * @param int|null $dbversion version in the database, or null if never installed.
     * @param string $expected expected REINSTALL_* status.
     * @return void
     */
    #[DataProvider('reinstall_status_provider')]
    public function test_get_reinstall_statuses(?int $diskversion, ?int $dbversion, string $expected): void {
        $this->resetAfterTest();
        $stasher = $this->stash_fake_for_reinstall();
        $stasher->set_installed('local_fake', $diskversion, $dbversion);

        $this->assertSame(['local_fake' => $expected], $stasher->get_reinstall_statuses());
    }

    /**
     * Reinstalling is refused when the site turns off installing plugins from the web.
     *
     * @return void
     */
    public function test_get_reinstall_statuses_respects_disableupdateautodeploy(): void {
        global $CFG;
        $this->resetAfterTest();
        $stasher = $this->stash_fake_for_reinstall();

        $CFG->disableupdateautodeploy = true;
        $this->assertSame(['local_fake' => stasher::REINSTALL_DISABLED], $stasher->get_reinstall_statuses());
    }

    /**
     * A plugin type the site does not know cannot be reinstalled.
     *
     * @return void
     */
    public function test_get_reinstall_statuses_unknown_type(): void {
        $this->resetAfterTest();
        $stasher = $this->stash_fake_for_reinstall();
        $stasher->set_target_root(null);

        $this->assertSame(['local_fake' => stasher::REINSTALL_UNKNOWNTYPE], $stasher->get_reinstall_statuses());
    }

    /**
     * A stashed copy that has gone from the stash directory cannot be reinstalled.
     *
     * @return void
     */
    public function test_get_reinstall_statuses_missing_stash_copy(): void {
        $this->resetAfterTest();
        $stasher = $this->stash_fake_for_reinstall();
        remove_dir($stasher->get_stash_dir() . '/' . $stasher->get_relative_dir(__DIR__ . '/fixtures/fakeplugin'));

        $this->assertSame(['local_fake' => stasher::REINSTALL_MISSING], $stasher->get_reinstall_statuses());
        $this->assertSame([], $stasher->get_reinstallable_components());
    }

    /**
     * reinstall() copies the stashed plugin into its directory and replaces an older copy.
     *
     * @return void
     */
    public function test_reinstall_copies_plugin_into_target(): void {
        $this->resetAfterTest();
        $stasher = $this->stash_fake_for_reinstall();
        $this->assertSame(['local_fake'], $stasher->get_reinstallable_components());

        $target = (new \ReflectionMethod($stasher, 'get_reinstall_target'))->invoke($stasher, 'local_fake');
        make_writable_directory($target);
        file_put_contents($target . '/stale.php', '<?php // Stale.');

        $stasher->reinstall('local_fake');

        $this->assertFileExists($target . '/lib.php');
        $this->assertFileExists($target . '/sub/note.txt');
        $this->assertFileDoesNotExist($target . '/stale.php');
    }

    /**
     * reinstall() refuses a plugin whose reinstall would be a downgrade.
     *
     * @return void
     */
    public function test_reinstall_refuses_downgrade(): void {
        $this->resetAfterTest();
        $stasher = $this->stash_fake_for_reinstall();
        $stasher->set_installed('local_fake', null, 2026020100);

        $this->expectException(\moodle_exception::class);
        $stasher->reinstall('local_fake');
    }

    /**
     * The reinstall target comes from the site's plugin type folders, never the manifest.
     *
     * @return void
     */
    public function test_get_reinstall_target_uses_plugin_type_root(): void {
        global $CFG;
        $this->resetAfterTest();
        $stasher = new stasher();
        $method = new \ReflectionMethod($stasher, 'get_reinstall_target');

        $this->assertSame($CFG->dirroot . '/local/fake', $method->invoke($stasher, 'local_fake'));
        $this->assertSame($CFG->dirroot . '/admin/tool/fake', $method->invoke($stasher, 'tool_fake'));
        $this->assertNull($method->invoke($stasher, 'core'));
        $this->assertNull($method->invoke($stasher, 'notatype_fake'));
    }

    /**
     * zip_self() packs this tool from the code tree under its own folder.
     *
     * @return void
     */
    public function test_zip_self_builds_installable_zip(): void {
        $this->resetAfterTest();
        $stasher = new stasher();

        $zippath = make_request_directory() . '/self.zip';
        $this->assertTrue($stasher->zip_self($zippath));

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zippath) === true);
        $this->assertNotFalse($zip->locateName('pluginstash/version.php'));
        $this->assertNotFalse($zip->locateName('pluginstash/classes/stasher.php'));
        $zip->close();
    }

    /**
     * Every capability declared in db/access.php has a language string.
     *
     * The roles UI resolves the capability's name via get_string(), so a missing
     * string throws there even though phpunit and behat never open that page.
     *
     * @return void
     */
    public function test_capabilities_have_language_strings(): void {
        global $CFG;

        $capabilities = [];
        require($CFG->dirroot . '/admin/tool/pluginstash/db/access.php');

        $sm = get_string_manager();
        foreach (array_keys($capabilities) as $capname) {
            // Capability "tool/pluginstash:manage" maps to string "pluginstash:manage".
            $stringid = preg_replace('#^[^/]+/#', '', $capname);
            $this->assertTrue(
                $sm->string_exists($stringid, 'tool_pluginstash'),
                "Missing language string '{$stringid}' for capability '{$capname}'"
            );
        }
    }
}
