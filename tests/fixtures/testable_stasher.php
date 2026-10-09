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

/**
 * Testable subclass of the stasher that resolves components from a fixture tree.
 *
 * It maps component names onto real directories supplied by the test, so
 * {@see \tool_pluginstash\stasher::stash()} can be exercised without depending on
 * a real installed add-on plugin.
 *
 * @package    tool_pluginstash
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testable_stasher extends stasher {
    /** @var array Map of component to [rootdir, version]. */
    protected $sources = [];

    /** @var array Map of component to [version on disk or null, version in the database or null]. */
    protected $installed = [];

    /** @var string|null Base directory that reinstalls are written into, or null for none. */
    protected $targetroot = null;

    /**
     * Register the directory and version that a component should stash from.
     *
     * @param string $component frankenstyle component name.
     * @param string $rootdir absolute directory to copy from.
     * @param int $version version to record in the manifest.
     * @return void
     */
    public function set_source(string $component, string $rootdir, int $version): void {
        $this->sources[$component] = [$rootdir, $version];
    }

    /**
     * Resolve a component from the registered fixture sources.
     *
     * @param string $component frankenstyle component name.
     * @return array|null [absolute root directory, version], or null if unregistered.
     */
    protected function get_component_source(string $component): ?array {
        return $this->sources[$component] ?? null;
    }

    /**
     * Simulate the versions of a component in the code tree and the database.
     *
     * @param string $component frankenstyle component name.
     * @param int|null $diskversion version in the code tree, or null if missing.
     * @param int|null $dbversion version recorded in the database, or null if never installed.
     * @return void
     */
    public function set_installed(string $component, ?int $diskversion, ?int $dbversion): void {
        $this->installed[$component] = [$diskversion, $dbversion];
    }

    /**
     * Send reinstalls into a temporary directory instead of the code tree.
     *
     * @param string|null $targetroot base directory, or null to report an unknown plugin type.
     * @return void
     */
    public function set_target_root(?string $targetroot): void {
        $this->targetroot = $targetroot;
    }

    /**
     * Return the simulated versions, defaulting to a plugin that was never installed.
     *
     * @param string $component frankenstyle component name.
     * @return array [version on disk or null, version in the database or null].
     */
    protected function get_installed_versions(string $component): array {
        return $this->installed[$component] ?? [null, null];
    }

    /**
     * Resolve the reinstall directory under the temporary target root.
     *
     * @param string $component frankenstyle component name.
     * @return string|null absolute plugin directory, or null when no target root is set.
     */
    protected function get_reinstall_target(string $component): ?string {
        return ($this->targetroot === null) ? null : $this->targetroot . '/' . $component;
    }
}
