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
 * Fixture library file for a fake add-on plugin used by the stasher tests.
 *
 * @package    tool_pluginstash
 * @copyright  2026 Vernon Spain
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * A marker function so the fixture file has something to copy.
 *
 * @return string a fixed marker string.
 */
function tool_pluginstash_fakeplugin_marker(): string {
    return 'fakeplugin-lib';
}
