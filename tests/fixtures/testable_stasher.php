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

/**
 * Testable subclass of the stasher that resolves components from a fixture tree.
 *
 * It maps component names onto real directories supplied by the test, so
 * {@see \tool_pluginstash\stasher::stash()} can be exercised without depending on
 * a real installed add-on plugin.
 *
 * @package    tool_pluginstash
 * @copyright  2026 Vernon Spain
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class testable_stasher extends stasher {
    /** @var array<string, array{0: string, 1: int}> Map of component to [rootdir, version]. */
    protected $sources = [];

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
     * @return array{0: string, 1: int}|null [absolute root directory, version], or null if unregistered.
     */
    protected function get_component_source(string $component): ?array {
        return $this->sources[$component] ?? null;
    }
}
