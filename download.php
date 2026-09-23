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
 * Stream a stashed plugin to the browser as a re-installable zip archive.
 *
 * @package    tool_pluginstash
 * @copyright  2026 Vernon Spain
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();
require_capability('tool/pluginstash:manage', context_system::instance());
require_sesskey();

$component = required_param('component', PARAM_COMPONENT);

$returnurl = new moodle_url('/admin/tool/pluginstash/index.php');

$stasher = new \tool_pluginstash\stasher();
$manifest = $stasher->read_manifest();
if (!isset($manifest[$component])) {
    throw new moodle_exception('errornotstashed', 'tool_pluginstash', $returnurl, $component);
}

$tempdir = make_request_directory();
$zippath = $tempdir . '/' . $component . '.zip';
if (!$stasher->zip_component($component, $zippath)) {
    throw new moodle_exception('errornotstashed', 'tool_pluginstash', $returnurl, $component);
}

send_temp_file($zippath, $component . '.zip');
