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

use core_plugin_manager;

/**
 * Copies add-on plugins into and out of a stash directory outside the code tree.
 *
 * This is a convenience tool for test and development sites: it lets an
 * administrator copy non-core plugins to a safe location before an upgrade or
 * rebuild, and restore them afterwards (via {@see \tool_pluginstash\stasher} from
 * the CLI) before Notifications is run.
 *
 * @package    tool_pluginstash
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL v3 or later
 */
class stasher {

    /** @var string Frankenstyle name of this plugin, used as the config plugin scope. */
    const COMPONENT = 'tool_pluginstash';

    /** @var string Name of the manifest file written into the stash directory. */
    const MANIFEST_FILE = 'manifest.json';

    /**
     * Whether the stash action is currently enabled.
     *
     * This is the kill-switch: when disabled the web UI refuses to copy anything.
     *
     * @return bool true if stashing is enabled.
     */
    public function is_enabled(): bool {
        return (bool) get_config(self::COMPONENT, 'enabled');
    }

    /**
     * Return the absolute path of the stash directory.
     *
     * Falls back to a directory inside dataroot when no path has been configured.
     *
     * @return string absolute path, without a trailing slash.
     */
    public function get_stash_dir(): string {
        global $CFG;

        $dir = get_config(self::COMPONENT, 'stashdir');
        if (empty($dir)) {
            $dir = $CFG->dataroot . '/' . self::COMPONENT;
        }
        return rtrim($dir, '/');
    }

    /**
     * Strip the Moodle dirroot from a full plugin path, yielding a relative directory.
     *
     * @param string $fulldir absolute path to a plugin directory.
     * @return string the path relative to dirroot, e.g. "mod/myplugin".
     */
    public function get_relative_dir(string $fulldir): string {
        global $CFG;

        $root = rtrim($CFG->dirroot, '/');
        $fulldir = rtrim($fulldir, '/');
        if (strpos($fulldir, $root) === 0) {
            $fulldir = substr($fulldir, strlen($root));
        }
        return ltrim($fulldir, '/');
    }

    /**
     * Return the installed add-on (non-core) plugins, keyed by component name.
     *
     * Plugins that are recorded in the database but missing from disk are skipped
     * because there is nothing to copy.
     *
     * @return \core\plugininfo\base[] add-on plugin info objects, keyed and sorted by component.
     */
    public function get_addon_plugins(): array {
        $pluginman = core_plugin_manager::instance();

        $addons = [];
        foreach ($pluginman->get_plugins() as $plugins) {
            foreach ($plugins as $plugin) {
                if (!$plugin->is_standard() && !empty($plugin->rootdir)) {
                    $addons[$plugin->component] = $plugin;
                }
            }
        }
        ksort($addons);
        return $addons;
    }

    /**
     * Copy the given components into the stash directory and record them in the manifest.
     *
     * @param string[] $components frankenstyle component names to stash.
     * @return bool[] map of component name to whether it was stashed.
     */
    public function stash(array $components): array {
        $pluginman = core_plugin_manager::instance();
        $stashdir = $this->get_stash_dir();

        $results = [];
        foreach ($components as $component) {
            $plugin = $pluginman->get_plugin_info($component);
            if ($plugin === null || empty($plugin->rootdir)) {
                $results[$component] = false;
                continue;
            }

            $reldir = $this->get_relative_dir($plugin->rootdir);
            $this->copy_dir($plugin->rootdir, $stashdir . '/' . $reldir, true);
            $this->write_manifest_entry($component, $reldir, (int) $plugin->versiondisk);
            $results[$component] = true;
        }
        return $results;
    }

    /**
     * Restore a previously stashed component back into the code tree.
     *
     * @param string $component frankenstyle component name to restore.
     * @param bool $overwrite whether to overwrite an existing destination directory.
     * @param string|null $targetroot base directory to restore into; defaults to dirroot.
     * @return bool true if the component was restored, false if it was missing or skipped.
     */
    public function restore_component(string $component, bool $overwrite = false, ?string $targetroot = null): bool {
        global $CFG;

        $targetroot = ($targetroot === null) ? $CFG->dirroot : rtrim($targetroot, '/');

        $manifest = $this->read_manifest();
        if (!isset($manifest[$component]['reldir'])) {
            return false;
        }

        $reldir = $manifest[$component]['reldir'];
        $source = $this->get_stash_dir() . '/' . $reldir;
        if (!is_dir($source)) {
            return false;
        }

        $dest = $targetroot . '/' . $reldir;
        if (is_dir($dest) && !$overwrite) {
            return false;
        }

        $this->copy_dir($source, $dest, $overwrite);
        return true;
    }

    /**
     * Read the stash manifest.
     *
     * @return array manifest entries keyed by component; empty if none exists.
     */
    public function read_manifest(): array {
        $file = $this->get_stash_dir() . '/' . self::MANIFEST_FILE;
        if (!is_readable($file)) {
            return [];
        }
        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    /**
     * Add or replace a single manifest entry and persist the manifest.
     *
     * @param string $component frankenstyle component name.
     * @param string $reldir path of the plugin relative to dirroot.
     * @param int $version the plugin version that was stashed.
     * @return void
     */
    protected function write_manifest_entry(string $component, string $reldir, int $version): void {
        $manifest = $this->read_manifest();
        $manifest[$component] = [
            'component' => $component,
            'reldir'    => $reldir,
            'version'   => $version,
            'stashed'   => time(),
        ];
        $this->write_manifest($manifest);
    }

    /**
     * Write the whole manifest to disk, creating the stash directory if required.
     *
     * @param array $manifest manifest entries keyed by component.
     * @return void
     */
    protected function write_manifest(array $manifest): void {
        $stashdir = $this->get_stash_dir();
        if (!is_dir($stashdir)) {
            make_writable_directory($stashdir);
        }
        $file = $stashdir . '/' . self::MANIFEST_FILE;
        file_put_contents($file, json_encode($manifest, JSON_PRETTY_PRINT));
    }

    /**
     * Recursively copy a directory tree.
     *
     * @param string $from source directory.
     * @param string $to destination directory.
     * @param bool $overwrite whether to overwrite files that already exist at the destination.
     * @return void
     */
    public function copy_dir(string $from, string $to, bool $overwrite = true): void {
        $from = rtrim($from, '/');
        $to = rtrim($to, '/');

        if (!is_dir($to)) {
            make_writable_directory($to);
        }

        foreach (new \DirectoryIterator($from) as $item) {
            if ($item->isDot()) {
                continue;
            }
            $src = $item->getPathname();
            $dst = $to . '/' . $item->getFilename();
            if ($item->isDir()) {
                $this->copy_dir($src, $dst, $overwrite);
            } else if (!file_exists($dst) || $overwrite) {
                copy($src, $dst);
            }
        }
    }
}
