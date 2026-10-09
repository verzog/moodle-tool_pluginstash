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
 * Language strings for tool_pluginstash.
 *
 * @package    tool_pluginstash
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['addonplugins'] = 'Add-on plugins';
$string['allstashed'] = 'All add-on plugins are already stashed at their installed version. A plugin appears here again when a
newer version is installed.';
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
$string['disablednotice'] = 'Plugin stashing is currently disabled. Enable it in the plugin settings to copy add-on plugins to the
stash.';
$string['downloadself'] = 'Download Plugin Stash as zip';
$string['downloadzip'] = 'Download as zip';
$string['enabled'] = 'Enable stashing';
$string['enabled_desc'] = 'When enabled, the Plugin Stash page can copy add-on plugins to the stash directory and reinstall them
from it. Disable this to lock the tool without uninstalling it.';
$string['errorcopyfailed'] = 'Failed to copy file: {$a}';
$string['errormanifestcorrupt'] = 'The stash manifest is corrupt and could not be read: {$a}';
$string['errornotstashed'] = 'The plugin "{$a}" is not recorded in the stash.';
$string['errorreinstall'] = 'Could not reinstall {$a->component}: {$a->reason}';
$string['errorremovefailed'] = 'Could not remove the existing directory {$a}. Check that the web server can write to all of it.';
$string['errorstashdirincodetree'] = 'The stash directory must be outside the Moodle code tree, but "{$a}" is inside it.';
$string['errorstashlock'] = 'Could not acquire the stash lock. Another stash operation may be in progress.';
$string['errorzipfailed'] = 'Failed to build a zip archive for {$a}.';
$string['noaddons'] = 'No additional plugins are installed. There is nothing to stash.';
$string['nothingtoreinstall'] = 'There are no stashed plugins that can be reinstalled right now.';
$string['pluginname'] = 'Plugin Stash';
$string['pluginstash:manage'] = 'Manage the Plugin Stash tool';
$string['privacy:metadata'] = 'The Plugin Stash tool only copies plugin code directories and a manifest of what was copied. It does
not store any personal data.';
$string['reinstall'] = 'Reinstall';
$string['reinstall_current'] = 'Installed';
$string['reinstall_disabled'] = 'Not available: installing plugins from the web is turned off on this site.';
$string['reinstall_downgrade'] = 'Not available: the site already has a newer version of this plugin.';
$string['reinstall_incompatible'] = 'Not available: the stashed version does not support this version of Moodle.';
$string['reinstall_missing'] = 'Not available: the stashed copy is missing from the stash directory.';
$string['reinstall_notwritable'] = 'Not available: the web server cannot write to this plugin\'s folder in the code tree.';
$string['reinstall_unknowntype'] = 'Not available: this site does not recognise the plugin type. Install the plugin it belongs to
first.';
$string['reinstallall'] = 'Reinstall all {$a} available plugins';
$string['reinstallconfirm'] = 'Copy these plugins from the stash back into the Moodle code tree? Any copy already there is
replaced. {$a} You will then be taken to Notifications to finish installing them.';
$string['reinstallyes'] = 'Yes, reinstall {$a} plugin(s)';
$string['selfrecovery'] = 'If an upgrade removes Plugin Stash';
$string['selfrecovery_desc'] = 'Plugin Stash cannot stash itself, because it has to be installed to bring your other plugins back.
Before you upgrade or rebuild the site, download a copy of it and keep it on your computer. If Plugin Stash disappears after an
upgrade, install that zip through Site administration > Plugins > Install plugins, then return to this page. Your stash and
settings are kept, so you can reinstall your other plugins from here.';
$string['settings'] = 'Plugin Stash settings';
$string['stashcount'] = 'Stashed {$a} plugin(s).';
$string['stashdir'] = 'Stash directory';
$string['stashdir_desc'] = 'Absolute path to the directory, outside the code tree, where add-on plugins are copied. Leave blank to
use a directory inside the Moodle data root.';
$string['stashedon'] = 'Stashed on';
$string['stashedplugins'] = 'Stashed plugins';
$string['stashintro'] = 'Ticked plugins are copied to the stash directory ({$a}). Existing copies there are overwritten.';
$string['stashselected'] = 'Stash selected plugins';
