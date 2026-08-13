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
 * Plugin Stash administration page.
 *
 * Lists the installed add-on plugins as a checklist and copies the ticked ones
 * into the stash directory.
 *
 * @package    tool_pluginstash
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('tool_pluginstash');

require_capability('tool/pluginstash:manage', context_system::instance());

$stasher = new \tool_pluginstash\stasher();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'tool_pluginstash'));

if (!$stasher->is_enabled()) {
    echo $OUTPUT->notification(get_string('disablednotice', 'tool_pluginstash'), 'info');
    echo $OUTPUT->footer();
    die();
}

$addons = $stasher->get_addon_plugins();

if (empty($addons)) {
    echo $OUTPUT->notification(get_string('noaddons', 'tool_pluginstash'), 'info');
    echo $OUTPUT->footer();
    die();
}

$form = new \tool_pluginstash\form\stash_form($PAGE->url->out(false), ['addons' => $addons]);

if ($data = $form->get_data()) {
    $selected = [];
    foreach (array_keys($addons) as $component) {
        $field = 'plugin_' . $component;
        if (!empty($data->$field)) {
            $selected[] = $component;
        }
    }

    $results = $stasher->stash($selected);
    $stashed = count(array_filter($results));
    redirect($PAGE->url, get_string('stashcount', 'tool_pluginstash', $stashed));
}

echo $OUTPUT->box(get_string('stashintro', 'tool_pluginstash', $stasher->get_stash_dir()));
$form->display();

echo $OUTPUT->footer();
