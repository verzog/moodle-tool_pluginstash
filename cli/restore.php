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
 * CLI script to restore stashed add-on plugins back into the code tree.
 *
 * Run this after an upgrade or rebuild, before Notifications, so the add-on
 * plugins are back on disk when Moodle boots them.
 *
 * @package    tool_pluginstash
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(
    [
        'help'      => false,
        'list'      => false,
        'component' => '',
        'overwrite' => false,
    ],
    [
        'h' => 'help',
        'l' => 'list',
        'c' => 'component',
        'o' => 'overwrite',
    ]
);

if ($unrecognised) {
    $unrecognised = implode("\n  ", $unrecognised);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognised));
}

if ($options['help']) {
    cli_writeln(get_string('cli_help', 'tool_pluginstash'));
    exit(0);
}

$stasher = new \tool_pluginstash\stasher();

try {
    $manifest = $stasher->read_manifest();
} catch (moodle_exception $e) {
    // A corrupt manifest must fail loudly: this runs when the administrator is
    // relying on it for recovery.
    cli_error($e->getMessage());
}

if (empty($manifest)) {
    cli_writeln(get_string('cli_nomanifest', 'tool_pluginstash', $stasher->get_stash_dir()));
    exit(0);
}

if ($options['list']) {
    foreach ($manifest as $entry) {
        cli_writeln($entry['component'] . "\t" . $entry['reldir']);
    }
    exit(0);
}

$overwrite = (bool) $options['overwrite'];
$components = ($options['component'] !== '') ? [$options['component']] : array_keys($manifest);

$restored = 0;
$skipped = 0;
$failed = 0;
foreach ($components as $component) {
    try {
        if ($stasher->restore_component($component, $overwrite)) {
            cli_writeln(get_string('cli_restored', 'tool_pluginstash', $component));
            $restored++;
        } else {
            cli_writeln(get_string('cli_skipped', 'tool_pluginstash', $component));
            $skipped++;
        }
    } catch (moodle_exception $e) {
        cli_writeln(get_string('cli_failed', 'tool_pluginstash', (object) [
            'component' => $component,
            'error'     => $e->getMessage(),
        ]));
        $failed++;
    }
}

cli_writeln(get_string('cli_done', 'tool_pluginstash', (object) [
    'restored' => $restored,
    'skipped'  => $skipped,
    'failed'   => $failed,
]));
exit($failed > 0 ? 1 : 0);
