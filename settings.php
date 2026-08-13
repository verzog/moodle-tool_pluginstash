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
 * Admin settings and links for tool_pluginstash.
 *
 * @package    tool_pluginstash
 * @copyright  2026 Vernon Spain
 * @license    http://www.gnu.org/licenses/gpl-3.0.txt GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('tools', new admin_externalpage(
        'tool_pluginstash',
        get_string('pluginname', 'tool_pluginstash'),
        new moodle_url('/admin/tool/pluginstash/index.php'),
        'tool/pluginstash:manage'
    ));

    $settings = new admin_settingpage('tool_pluginstash_settings', get_string('settings', 'tool_pluginstash'));

    $settings->add(new admin_setting_configcheckbox(
        'tool_pluginstash/enabled',
        get_string('enabled', 'tool_pluginstash'),
        get_string('enabled_desc', 'tool_pluginstash'),
        1
    ));

    $settings->add(new admin_setting_configtext(
        'tool_pluginstash/stashdir',
        get_string('stashdir', 'tool_pluginstash'),
        get_string('stashdir_desc', 'tool_pluginstash'),
        '',
        PARAM_PATH
    ));

    $ADMIN->add('tools', $settings);
}
