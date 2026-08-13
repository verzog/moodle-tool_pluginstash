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

namespace tool_pluginstash\privacy;

use core_privacy\local\metadata\null_provider;

/**
 * Privacy provider for tool_pluginstash.
 *
 * The plugin stores no personal data: it only copies plugin code directories and
 * a manifest of what was copied.
 *
 * @package    tool_pluginstash
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL v3 or later
 */
class provider implements null_provider {
    /**
     * Get the language string identifier explaining why this plugin stores no personal data.
     *
     * @return string the identifier of the string in tool_pluginstash to explain the absence of data.
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
