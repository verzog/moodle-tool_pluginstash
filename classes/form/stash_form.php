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

namespace tool_pluginstash\form;

use moodleform;

/**
 * Checklist form listing the add-on plugins that can be stashed.
 *
 * @package    tool_pluginstash
 * @copyright  2026 Vernon Spain
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stash_form extends moodleform {
    /**
     * Build one checkbox per add-on plugin plus a submit button.
     *
     * @return void
     */
    protected function definition() {
        $mform = $this->_form;
        $addons = $this->_customdata['addons'];

        $mform->addElement('header', 'pluginsheader', get_string('addonplugins', 'tool_pluginstash'));

        foreach ($addons as $component => $plugin) {
            $label = $plugin->displayname . ' (' . $component . ')';
            $mform->addElement('advcheckbox', 'plugin_' . $component, $label);
            $mform->setDefault('plugin_' . $component, 0);
        }

        $this->add_action_buttons(false, get_string('stashselected', 'tool_pluginstash'));
    }
}
