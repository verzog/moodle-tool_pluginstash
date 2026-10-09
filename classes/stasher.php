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
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stasher {
    /** @var string Frankenstyle name of this plugin, used as the config plugin scope. */
    const COMPONENT = 'tool_pluginstash';

    /** @var string Name of the manifest file written into the stash directory. */
    const MANIFEST_FILE = 'manifest.json';

    /** @var string Reinstall status: the stashed copy can be copied back into the code tree. */
    const REINSTALL_READY = 'ready';

    /** @var string Reinstall status: the code tree already holds the stashed version or newer. */
    const REINSTALL_CURRENT = 'current';

    /** @var string Reinstall status: the site has a newer version installed, so this would be a downgrade. */
    const REINSTALL_DOWNGRADE = 'downgrade';

    /** @var string Reinstall status: the stashed copy is missing from the stash directory. */
    const REINSTALL_MISSING = 'missing';

    /** @var string Reinstall status: the plugin type is not known to this site. */
    const REINSTALL_UNKNOWNTYPE = 'unknowntype';

    /** @var string Reinstall status: the web server cannot write to the plugin's directory. */
    const REINSTALL_NOTWRITABLE = 'notwritable';

    /** @var string Reinstall status: installing plugin code from the web is disabled on this site. */
    const REINSTALL_DISABLED = 'disabled';

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
     * Return the add-on (non-core) plugins that are installed on this site and present on disk.
     *
     * The list is limited to plugins Moodle records as installed (they have a
     * database version) and whose directory actually exists, so it never offers
     * a plugin that is only present on disk but was never installed, one that is
     * recorded in the database but missing from disk, a core plugin, or this tool
     * itself.
     *
     * @return \core\plugininfo\base[] add-on plugin info objects, keyed and sorted by component.
     */
    public function get_addon_plugins(): array {
        $pluginman = core_plugin_manager::instance();

        $addons = [];
        foreach ($pluginman->get_plugins() as $plugins) {
            foreach ($plugins as $plugin) {
                if ($plugin->component === self::COMPONENT) {
                    // Never offer to stash this tool itself: the CLI restore
                    // script lives here and must already be present on disk to
                    // run, so it cannot be bootstrapped out of its own stash.
                    // Keep this plugin in version control instead (see README).
                    continue;
                }
                if ($plugin->is_standard()) {
                    // Core plugins ship with Moodle; there is nothing to preserve.
                    continue;
                }
                if (empty($plugin->versiondb)) {
                    // Present on disk but never installed on this site (for example
                    // a leftover directory): not something the administrator asked
                    // to keep, so do not list it.
                    continue;
                }
                if (empty($plugin->rootdir) || !is_dir($plugin->rootdir)) {
                    // Recorded in the database but the files are gone: nothing to copy.
                    continue;
                }
                $addons[$plugin->component] = $plugin;
            }
        }
        ksort($addons);
        return $addons;
    }

    /**
     * Filter add-on plugins down to those that still need stashing.
     *
     * A plugin is left out when the stash already holds a copy at the installed
     * version or newer. It is offered again once the installed version is newer
     * than the stashed copy, or if the stashed copy has gone missing from disk.
     *
     * @param \core\plugininfo\base[] $addons add-on plugins keyed by component, from get_addon_plugins().
     * @return \core\plugininfo\base[] the plugins that are not stashed or have a newer version installed.
     */
    public function get_stashable_plugins(array $addons): array {
        $manifest = $this->read_manifest();
        $stashdir = $this->get_stash_dir();

        $stashable = [];
        foreach ($addons as $component => $plugin) {
            $entry = $manifest[$component] ?? null;
            $uptodate = $entry !== null && is_dir($stashdir . '/' . $entry['reldir'])
                && (int) $plugin->versiondisk <= (int) $entry['version'];
            if ($uptodate) {
                continue;
            }
            $stashable[$component] = $plugin;
        }
        return $stashable;
    }

    /**
     * Copy the given components into the stash directory and record them in the manifest.
     *
     * @param string[] $components frankenstyle component names to stash.
     * @return bool[] map of component name to whether it was stashed.
     */
    public function stash(array $components): array {
        $stashdir = $this->get_stash_dir();
        $this->guard_stash_dir($stashdir);

        // Hold the stash lock across the directory replacement and the manifest
        // write so a concurrent download never sees a half-replaced directory.
        return $this->with_lock(function () use ($components, $stashdir): array {
            $results = [];
            foreach ($components as $component) {
                $source = $this->get_component_source($component);
                if ($source === null) {
                    $results[$component] = false;
                    continue;
                }

                [$rootdir, $version] = $source;
                $reldir = $this->get_relative_dir($rootdir);
                // Replace (not merge) so a re-stash of a slimmer plugin version leaves
                // no stale files behind. copy_dir() throws if any copy fails, so the
                // manifest entry below is only written for a complete stash.
                $this->replace_dir($rootdir, $stashdir . '/' . $reldir);
                $this->write_manifest_entry($component, $reldir, $version);
                $results[$component] = true;
            }
            return $results;
        });
    }

    /**
     * Refuse to use a stash directory that lives inside the Moodle code tree.
     *
     * A stash under dirroot would be wiped by the very rebuild it is meant to
     * survive, and a stash inside a plugin being copied would make copy_dir()
     * recurse into its own output.
     *
     * @param string $stashdir absolute path of the configured stash directory.
     * @return void
     * @throws \moodle_exception if the path resolves inside dirroot.
     */
    protected function guard_stash_dir(string $stashdir): void {
        global $CFG;

        $dirroot = rtrim($CFG->dirroot, '/');
        $real = realpath($stashdir);
        $check = ($real !== false) ? $real : rtrim($stashdir, '/');
        if ($check === $dirroot || strpos($check . '/', $dirroot . '/') === 0) {
            throw new \moodle_exception('errorstashdirincodetree', 'tool_pluginstash', '', $stashdir);
        }
    }

    /**
     * Resolve the on-disk directory and version of an installed component.
     *
     * This is the seam used by {@see self::stash()} to read plugin locations from
     * the plugin manager; tests override it to drive stashing from a fixture tree.
     *
     * @param string $component frankenstyle component name.
     * @return array|null [absolute root directory, version], or null if unavailable.
     */
    protected function get_component_source(string $component): ?array {
        $plugin = core_plugin_manager::instance()->get_plugin_info($component);
        if ($plugin === null || empty($plugin->rootdir)) {
            return null;
        }
        return [$plugin->rootdir, (int) $plugin->versiondisk];
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

        // Hold the stash lock so the source directory cannot be replaced by a
        // concurrent stash while it is being read.
        return $this->with_lock(function () use ($component, $overwrite, $targetroot): bool {
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

            // Replace the destination wholesale so an overwrite never leaves a
            // hybrid of the old and stashed versions on disk.
            $this->replace_dir($source, $dest);
            return true;
        });
    }

    /**
     * Work out whether each stashed plugin can be reinstalled from the web UI.
     *
     * @return string[] map of component name to one of the REINSTALL_* constants.
     */
    public function get_reinstall_statuses(): array {
        global $CFG;

        $stashdir = $this->get_stash_dir();
        $statuses = [];
        foreach ($this->read_manifest() as $component => $entry) {
            [$diskversion, $dbversion] = $this->get_installed_versions($component);
            $stashedversion = (int) $entry['version'];
            $target = $this->get_reinstall_target($component);

            if (!is_dir($stashdir . '/' . $entry['reldir'])) {
                $status = self::REINSTALL_MISSING;
            } else if ($diskversion !== null && $diskversion >= $stashedversion) {
                $status = self::REINSTALL_CURRENT;
            } else if ($dbversion !== null && $dbversion > $stashedversion) {
                // Moodle refuses to run with code older than the version recorded in
                // the database, so copying this back would lock the site.
                $status = self::REINSTALL_DOWNGRADE;
            } else if (!empty($CFG->disableupdateautodeploy)) {
                // Respect the same switch that turns off Moodle's own "Install plugins".
                $status = self::REINSTALL_DISABLED;
            } else if ($target === null) {
                $status = self::REINSTALL_UNKNOWNTYPE;
            } else if (!is_writable(dirname($target)) || (is_dir($target) && !is_writable($target))) {
                $status = self::REINSTALL_NOTWRITABLE;
            } else {
                $status = self::REINSTALL_READY;
            }
            $statuses[$component] = $status;
        }
        return $statuses;
    }

    /**
     * Copy a stashed plugin back into the code tree so Notifications can install it.
     *
     * @param string $component frankenstyle component name.
     * @return void
     * @throws \moodle_exception if the plugin cannot be reinstalled.
     */
    public function reinstall(string $component): void {
        $status = $this->get_reinstall_statuses()[$component] ?? self::REINSTALL_MISSING;
        if ($status !== self::REINSTALL_READY) {
            throw new \moodle_exception(
                'errorreinstall',
                self::COMPONENT,
                '',
                (object) [
                    'component' => $component,
                    'reason' => get_string('reinstall_' . $status, self::COMPONENT),
                ]
            );
        }

        $target = $this->get_reinstall_target($component);
        $this->with_lock(function () use ($component, $target): void {
            $reldir = $this->read_manifest()[$component]['reldir'];
            $this->replace_dir($this->get_stash_dir() . '/' . $reldir, $target);
        });
        core_plugin_manager::reset_caches();
    }

    /**
     * Return the stashed components that can be reinstalled right now.
     *
     * @return string[] frankenstyle component names.
     */
    public function get_reinstallable_components(): array {
        return array_keys(array_filter(
            $this->get_reinstall_statuses(),
            static fn(string $status): bool => $status === self::REINSTALL_READY
        ));
    }

    /**
     * Return the version of a component in the code tree and the version recorded in the database.
     *
     * This is a seam: tests override it to simulate installed and missing plugins.
     *
     * @param string $component frankenstyle component name.
     * @return array [version on disk or null, version in the database or null].
     */
    protected function get_installed_versions(string $component): array {
        $diskversion = null;
        $plugin = core_plugin_manager::instance()->get_plugin_info($component);
        if ($plugin !== null && !empty($plugin->rootdir) && is_dir($plugin->rootdir) && !empty($plugin->versiondisk)) {
            $diskversion = (int) $plugin->versiondisk;
        }
        $dbversion = get_config($component, 'version');
        return [$diskversion, ($dbversion === false) ? null : (int) $dbversion];
    }

    /**
     * Return the directory a component belongs in, based on its plugin type.
     *
     * The path comes from the site's plugin types, never from the manifest, so a
     * tampered manifest cannot direct a reinstall elsewhere in the file system.
     * This is a seam: tests override it to reinstall into a temporary directory.
     *
     * @param string $component frankenstyle component name.
     * @return string|null absolute plugin directory, or null if the plugin type is unknown.
     */
    protected function get_reinstall_target(string $component): ?string {
        [$type, $name] = \core_component::normalize_component($component);
        if ($type === 'core' || empty($name)) {
            return null;
        }
        $root = core_plugin_manager::instance()->get_plugintype_root($type);
        return is_string($root) ? $root . '/' . $name : null;
    }

    /**
     * Build a zip archive of this tool itself, from the code tree.
     *
     * Plugin Stash is never stashed, so this is how an administrator keeps a copy
     * to reinstall through "Install plugins" if an upgrade removes it.
     *
     * @param string $zippath absolute path of the zip file to create.
     * @return bool true on success.
     * @throws \moodle_exception if the archive cannot be written.
     */
    public function zip_self(string $zippath): bool {
        $dir = \core_component::get_component_directory(self::COMPONENT);
        $files = $this->build_zip_filelist($dir, basename($dir));
        if (empty($files)) {
            return false;
        }
        $this->write_zip($files, $zippath, self::COMPONENT);
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
        $contents = (string) file_get_contents($file);
        if (trim($contents) === '') {
            return [];
        }
        $data = json_decode($contents, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Surface corruption loudly rather than silently reporting an empty
            // manifest when an administrator is relying on it for recovery.
            throw new \moodle_exception('errormanifestcorrupt', 'tool_pluginstash', '', $file);
        }
        return is_array($data) ? $data : [];
    }

    /**
     * Build a zip archive of a stashed component, ready to re-install.
     *
     * The archive contains the plugin under a single top-level folder named after
     * the plugin directory, matching what Moodle's "install from ZIP" expects.
     *
     * @param string $component frankenstyle component name.
     * @param string $zippath absolute path of the zip file to create.
     * @return bool true on success, false if the component is not stashed.
     * @throws \moodle_exception if the archive cannot be written.
     */
    public function zip_component(string $component, string $zippath): bool {
        // Hold the stash lock while enumerating and archiving so a concurrent
        // stash cannot replace the directory mid-pack and yield a partial zip.
        return $this->with_lock(function () use ($component, $zippath): bool {
            $manifest = $this->read_manifest();
            if (!isset($manifest[$component]['reldir'])) {
                return false;
            }

            $reldir = $manifest[$component]['reldir'];
            $dir = $this->get_stash_dir() . '/' . $reldir;
            if (!is_dir($dir)) {
                return false;
            }

            $files = $this->build_zip_filelist($dir, basename($reldir));
            if (empty($files)) {
                return false;
            }

            $this->write_zip($files, $zippath, $component);
            return true;
        });
    }

    /**
     * Write a zip archive from an archive-path => local-path map.
     *
     * @param string[] $files map of archive path to absolute file path.
     * @param string $zippath absolute path of the zip file to create.
     * @param string $component component being archived, for the error message.
     * @return void
     * @throws \moodle_exception if the archive cannot be written.
     */
    protected function write_zip(array $files, string $zippath, string $component): void {
        $packer = get_file_packer('application/zip');
        if ($packer->archive_to_pathname($files, $zippath) !== true) {
            throw new \moodle_exception('errorzipfailed', 'tool_pluginstash', '', $component);
        }
    }

    /**
     * Build the archive-path => local-path map for zipping a directory tree.
     *
     * Symlinks are skipped, consistent with {@see self::copy_dir()}.
     *
     * @param string $dir absolute source directory.
     * @param string $root name of the top-level folder inside the archive.
     * @return string[] map of archive path to absolute file path.
     */
    protected function build_zip_filelist(string $dir, string $root): array {
        $dir = rtrim($dir, '/');

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            if ($item->isLink() || !$item->isFile()) {
                continue;
            }
            $relative = substr($item->getPathname(), strlen($dir) + 1);
            $archivepath = $root . '/' . str_replace(DIRECTORY_SEPARATOR, '/', $relative);
            $files[$archivepath] = $item->getPathname();
        }
        ksort($files);
        return $files;
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
        // The caller (stash()) already holds the stash lock, which serialises
        // this read-modify-write against other stash requests.
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
     * Run a callback while holding the exclusive stash lock.
     *
     * The single lock guards every operation that reads or writes the stash
     * directory or its manifest (stash, restore, zip), so those cannot interleave.
     *
     * @param callable $callback the work to run under the lock.
     * @return mixed whatever the callback returns.
     * @throws \moodle_exception if the lock cannot be obtained.
     */
    protected function with_lock(callable $callback) {
        $factory = \core\lock\lock_config::get_lock_factory('tool_pluginstash');
        $lock = $factory->get_lock('stash', 10);
        if (!$lock) {
            throw new \moodle_exception('errorstashlock', 'tool_pluginstash');
        }
        try {
            return $callback();
        } finally {
            $lock->release();
        }
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
     * Replace a destination directory with a fresh copy of the source tree.
     *
     * Any existing destination is removed first so no files that are absent from
     * the source survive the copy.
     *
     * @param string $from source directory.
     * @param string $to destination directory.
     * @return void
     */
    protected function replace_dir(string $from, string $to): void {
        if (is_dir($to)) {
            remove_dir($to);
        }
        $this->copy_dir($from, $to);
    }

    /**
     * Recursively copy a directory tree.
     *
     * Symlinks are never followed: a link could point outside the plugin or, if
     * it targets an ancestor, cause unbounded recursion. A failed copy throws
     * rather than being silently ignored.
     *
     * @param string $from source directory.
     * @param string $to destination directory.
     * @param bool $overwrite whether to overwrite files that already exist at the destination.
     * @return void
     * @throws \moodle_exception if a file cannot be copied.
     */
    public function copy_dir(string $from, string $to, bool $overwrite = true): void {
        $from = rtrim($from, '/');
        $to = rtrim($to, '/');

        if (!is_dir($to)) {
            make_writable_directory($to);
        }

        foreach (new \DirectoryIterator($from) as $item) {
            if ($item->isDot() || $item->isLink()) {
                continue;
            }
            $src = $item->getPathname();
            $dst = $to . '/' . $item->getFilename();
            if ($item->isDir()) {
                $this->copy_dir($src, $dst, $overwrite);
            } else if (!file_exists($dst) || $overwrite) {
                if (!copy($src, $dst)) {
                    throw new \moodle_exception('errorcopyfailed', 'tool_pluginstash', '', $src);
                }
            }
        }
    }
}
