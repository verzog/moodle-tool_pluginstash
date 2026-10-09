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
$form = empty($stashable) ? null : new \tool_pluginstash\form\stash_form(
    $PAGE->url->out(false),
    ['addons' => $stashable]
);

// Process the submission before emitting any output so the redirect is a clean
// post/redirect/get and never hits "headers already sent".
// Stashing runs inline rather than as a queued adhoc task: this is an admin-only
// tool for test sites that copies a handful of plugin directories, it has a
// kill-switch (the "enabled" setting), and the page states that existing copies
// are overwritten before the administrator submits.
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

// The list of already-stashed plugins is shown regardless of whether stashing is
// enabled, with a download link per plugin. Reinstalling writes to the code tree,
// so it is only offered while the tool is enabled, to site administrators.
$stashed = $stasher->read_manifest();
if (!empty($stashed)) {
    echo $OUTPUT->heading(get_string('stashedplugins', 'tool_pluginstash'), 3);

    $canreinstall = $enabled && has_capability('moodle/site:config', context_system::instance());
    $statuses = $canreinstall ? $stasher->get_reinstall_statuses() : [];

    $table = new html_table();
    $table->head = [
        get_string('plugin'),
        get_string('stashedon', 'tool_pluginstash'),
        get_string('download'),
    ];
    if ($canreinstall) {
        $table->head[] = get_string('reinstall', 'tool_pluginstash');
    }
    foreach ($stashed as $entry) {
        $url = new moodle_url('/admin/tool/pluginstash/download.php', [
            'component' => $entry['component'],
            'sesskey'   => sesskey(),
        ]);
        $row = [
            s($entry['component']),
            userdate($entry['stashed'], '%d/%m/%Y'),
            html_writer::link($url, get_string('downloadzip', 'tool_pluginstash')),
        ];
        if ($canreinstall) {
            $status = $statuses[$entry['component']];
            if ($status === \tool_pluginstash\stasher::REINSTALL_READY) {
                $reinstallurl = new moodle_url('/admin/tool/pluginstash/reinstall.php', ['component' => $entry['component']]);
                $row[] = $OUTPUT->single_button($reinstallurl, get_string('reinstall', 'tool_pluginstash'), 'get');
            } else {
                $row[] = get_string('reinstall_' . $status, 'tool_pluginstash');
            }
        }
        $table->data[] = $row;
    }
    echo html_writer::table($table);

    $readycount = count(array_keys($statuses, \tool_pluginstash\stasher::REINSTALL_READY, true));
    if ($readycount > 1) {
        $allurl = new moodle_url('/admin/tool/pluginstash/reinstall.php', ['all' => 1]);
        echo $OUTPUT->single_button($allurl, get_string('reinstallall', 'tool_pluginstash', $readycount), 'get');
    }
}

// Plugin Stash cannot stash itself, so offer a copy to keep in case an upgrade
// removes it; installing that zip brings this page and the stash back.
echo $OUTPUT->heading(get_string('selfrecovery', 'tool_pluginstash'), 3);
echo $OUTPUT->box(get_string('selfrecovery_desc', 'tool_pluginstash'));
$selfurl = new moodle_url('/admin/tool/pluginstash/download.php', [
    'component' => \tool_pluginstash\stasher::COMPONENT,
    'sesskey'   => sesskey(),
]);
echo $OUTPUT->single_button($selfurl, get_string('downloadself', 'tool_pluginstash'), 'get');

echo $OUTPUT->footer();
