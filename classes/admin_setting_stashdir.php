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
 * Admin setting for the stash directory that rejects paths inside the code tree.
 *
 * @package    tool_pluginstash
 * @copyright  2026 Vernon Spain
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class admin_setting_stashdir extends \admin_setting_configtext {
    /**
     * Validate the submitted path, additionally rejecting anything inside dirroot.
     *
     * @param string $data the submitted path.
     * @return true|string true if valid, or an error string.
     */
    public function validate($data) {
        global $CFG;

        $parent = parent::validate($data);
        if ($parent !== true) {
            return $parent;
        }

        if (trim($data) === '') {
            // Empty means "fall back to a directory inside dataroot", which is fine.
            return true;
        }

        $dirroot = rtrim($CFG->dirroot, '/');
        $real = realpath($data);
        $check = ($real !== false) ? $real : rtrim($data, '/');
        if ($check === $dirroot || strpos($check . '/', $dirroot . '/') === 0) {
            return get_string('errorstashdirincodetree', 'tool_pluginstash', $data);
        }

        return true;
    }
}
