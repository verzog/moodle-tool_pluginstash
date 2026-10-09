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

/**
 * Copy stashed plugins back into the code tree, then hand over to Notifications.
 *
 * @package    tool_pluginstash
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$component = optional_param('component', '', PARAM_COMPONENT);
$all = optional_param('all', 0, PARAM_BOOL);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

$pageurl = new moodle_url('/admin/tool/pluginstash/reinstall.php', ['component' => $component, 'all' => $all]);
admin_externalpage_setup('tool_pluginstash', '', null, $pageurl);

$context = context_system::instance();
require_capability('tool/pluginstash:manage', $context);
// Writing plugin code into the site is as powerful as Moodle's own "Install plugins",
// which requires full site configuration rights.
require_capability('moodle/site:config', $context);

$returnurl = new moodle_url('/admin/tool/pluginstash/index.php');

$stasher = new \tool_pluginstash\stasher();
if (!$stasher->is_enabled()) {
    redirect($returnurl, get_string('disablednotice', 'tool_pluginstash'), null, \core\output\notification::NOTIFY_ERROR);
}

$components = $all ? $stasher->get_reinstallable_components() : array_filter([$component]);
if (empty($components)) {
    redirect($returnurl, get_string('nothingtoreinstall', 'tool_pluginstash'), null, \core\output\notification::NOTIFY_WARNING);
}

if ($confirm) {
    require_sesskey();
    // Reinstalling copies a handful of plugin directories for one administrator,
    // after an explicit confirmation, so it runs inline rather than as a task.
    foreach ($components as $name) {
        $stasher->reinstall($name);
    }
    // Notifications rebuilds its caches when visited with cache=0, finds the
    // copied plugins and runs their install or upgrade.
    redirect(new moodle_url('/admin/index.php', ['cache' => 0]));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('reinstall', 'tool_pluginstash'));

$continueurl = new moodle_url($pageurl, ['confirm' => 1, 'sesskey' => sesskey()]);
echo $OUTPUT->confirm(
    get_string('reinstallconfirm', 'tool_pluginstash', html_writer::alist(array_map('s', $components))),
    $continueurl,
    $returnurl,
    ['continuestr' => get_string('reinstallyes', 'tool_pluginstash', count($components))]
);

echo $OUTPUT->footer();
