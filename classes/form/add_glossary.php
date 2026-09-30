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

namespace tool_wizards\form;

/**
 * Mini-wizard: add a glossary (mod_glossary).
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class add_glossary extends add_module_base {
    /**
     * The type.
     *
     * @return string
     */
    protected static function get_type(): string {
        return 'glossary';
    }

    /**
     * Questions: a name and what the glossary is for.
     */
    protected function define_questions(): void {
        $mform = $this->_form;
        $mform->addElement('html', \html_writer::tag('p', get_string('add_glossary_intro', 'tool_wizards')));
        $this->add_name_field('glossary_name');
        $this->add_description_field('glossary_description');
    }

    /**
     * The module form fields.
     *
     * @param \stdClass $data submitted data
     * @return array
     */
    protected function answers_to_fields(\stdClass $data): array {
        return ['name' => $data->name, 'introeditor' => self::description_editor($data->description ?? '')];
    }
}
