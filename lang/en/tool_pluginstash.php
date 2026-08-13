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
 * Language strings for tool_pluginstash.
 *
 * @package    tool_pluginstash
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['addonplugins'] = 'Add-on plugins';
$string['cli_done'] = 'Done. Restored {$a->restored}, skipped {$a->skipped}, failed {$a->failed}.';
$string['cli_failed'] = 'Failed to restore {$a->component}: {$a->error}';
$string['cli_help'] = 'Restore stashed add-on plugins back into the code tree.

Run this after an upgrade or rebuild, before you run Notifications.

Options:
  -h, --help              Print this help.
  -l, --list              List the components recorded in the manifest and exit.
  -c, --component=NAME    Restore only this component (frankenstyle name).
  -o, --overwrite         Overwrite destination directories that already exist.

Example:
  php restore.php --overwrite
';
$string['cli_nomanifest'] = 'No stash manifest found in {$a}. Nothing to restore.';
$string['cli_restored'] = 'Restored {$a}.';
$string['cli_skipped'] = 'Skipped {$a} (already present or missing from the stash).';
$string['disablednotice'] = 'Plugin stashing is currently disabled. Enable it in the plugin settings to copy add-on plugins to the stash.';
$string['downloadzip'] = 'Download as zip';
$string['enabled'] = 'Enable stashing';
$string['enabled_desc'] = 'When enabled, the Plugin Stash page can copy add-on plugins to the stash directory. Disable this to lock the tool without uninstalling it.';
$string['errorcopyfailed'] = 'Failed to copy file: {$a}';
$string['errormanifestcorrupt'] = 'The stash manifest is corrupt and could not be read: {$a}';
$string['errormanifestlock'] = 'Could not acquire a lock to update the stash manifest. Another stash operation may be in progress.';
$string['errornotstashed'] = 'The plugin "{$a}" is not recorded in the stash.';
$string['errorstashdirincodetree'] = 'The stash directory must be outside the Moodle code tree, but "{$a}" is inside it.';
$string['errorzipfailed'] = 'Failed to build a zip archive for {$a}.';
$string['noaddons'] = 'No additional plugins are installed. There is nothing to stash.';
$string['pluginname'] = 'Plugin Stash';
$string['privacy:metadata'] = 'The Plugin Stash tool only copies plugin code directories and a manifest of what was copied. It does not store any personal data.';
$string['settings'] = 'Plugin Stash settings';
$string['stashcount'] = 'Stashed {$a} plugin(s).';
$string['stashdir'] = 'Stash directory';
$string['stashdir_desc'] = 'Absolute path to the directory, outside the code tree, where add-on plugins are copied. Leave blank to use a directory inside the Moodle data root.';
$string['stashedon'] = 'Stashed on';
$string['stashedplugins'] = 'Stashed plugins';
$string['stashintro'] = 'Ticked plugins are copied to the stash directory ({$a}). Existing copies there are overwritten.';
$string['stashselected'] = 'Stash selected plugins';
