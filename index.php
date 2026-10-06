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
 * Plugin Stash administration page.
 *
 * Lists the installed add-on plugins as a checklist and copies the ticked ones
 * into the stash directory.
 *
 * @package    tool_pluginstash
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');

admin_externalpage_setup('tool_pluginstash');

require_capability('tool/pluginstash:manage', context_system::instance());

$stasher = new \tool_pluginstash\stasher();

$enabled = $stasher->is_enabled();
$addons = $enabled ? $stasher->get_addon_plugins() : [];
// Only offer plugins that are not stashed yet, or whose installed version is newer
// than the stashed copy; up-to-date ones appear in the stashed list below instead.
$stashable = $stasher->get_stashable_plugins($addons);
$form = empty($stashable) ? null : new \tool_pluginstash\form\stash_form($PAGE->url->out(false), ['addons' => $stashable]);

// Process the submission before emitting any output so the redirect is a clean
// post/redirect/get and never hits "headers already sent".
if ($form !== null && ($data = $form->get_data())) {
    $selected = [];
    foreach (array_keys($stashable) as $component) {
        $field = 'plugin_' . $component;
        if (!empty($data->$field)) {
            $selected[] = $component;
        }
    }

    $results = $stasher->stash($selected);
    $stashed = count(array_filter($results));
    redirect($PAGE->url, get_string('stashcount', 'tool_pluginstash', $stashed));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'tool_pluginstash'));

if (!$enabled) {
    echo $OUTPUT->notification(get_string('disablednotice', 'tool_pluginstash'), 'info');
} else if (empty($addons)) {
    echo $OUTPUT->notification(get_string('noaddons', 'tool_pluginstash'), 'info');
} else if (empty($stashable)) {
    echo $OUTPUT->notification(get_string('allstashed', 'tool_pluginstash'), 'info');
} else {
    echo $OUTPUT->box(get_string('stashintro', 'tool_pluginstash', $stasher->get_stash_dir()));
    $form->display();
}

// The list of already-stashed plugins is a read-only view, shown regardless of
// whether stashing is currently enabled, with a download link per plugin.
$stashed = $stasher->read_manifest();
if (!empty($stashed)) {
    echo $OUTPUT->heading(get_string('stashedplugins', 'tool_pluginstash'), 3);

    $table = new html_table();
    $table->head = [
        get_string('plugin'),
        get_string('stashedon', 'tool_pluginstash'),
        get_string('download'),
    ];
    foreach ($stashed as $entry) {
        $url = new moodle_url('/admin/tool/pluginstash/download.php', [
            'component' => $entry['component'],
            'sesskey'   => sesskey(),
        ]);
        $table->data[] = [
            s($entry['component']),
            userdate($entry['stashed'], '%d/%m/%Y'),
            html_writer::link($url, get_string('downloadzip', 'tool_pluginstash')),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
