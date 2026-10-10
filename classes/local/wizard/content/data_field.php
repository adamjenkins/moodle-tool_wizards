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

namespace tool_wizards\local\wizard\content;

use tool_wizards\local\wizard\content;

/**
 * A field added to a database activity.
 *
 * Saved as mod/data/field.php saves a new field (mode "add"): the field type's class from
 * data_get_field_new(), its validate(), define_field() and insert_field() (which triggers
 * field_created), then data_append_new_field_to_templates().
 *
 * @package    tool_wizards
 * @copyright  2005 Martin Dougiamas  http://dougiamas.com
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data_field extends content {
    /**
     * @var array The field types this wizard offers, with the settings each gets that the teacher is
     *     not asked about: the defaults of each type's own form in mod/data/field/<type>/templates.
     */
    const TYPES = [
        'text' => [],
        'textarea' => ['param2' => '60', 'param3' => '35', 'param4' => '1', 'param5' => '0'],
        'number' => [],
        'date' => [],
        'menu' => [],
        'radiobutton' => [],
        'checkbox' => [],
        // Shown as a link (the form's "Autolink" option), as students expect of a web address.
        'url' => ['param1' => '1'],
        'picture' => ['param3' => '0'],
        'file' => ['param3' => '0'],
    ];

    /** @var string[] The types whose options the teacher lists. */
    const WITHOPTIONS = ['menu', 'radiobutton', 'checkbox'];

    /** @var int The longest name the data_fields.name column holds. */
    const MAXNAME = 255;

    /**
     * The activity module this content belongs to.
     *
     * @return string
     */
    public static function modname(): string {
        return 'data';
    }

    /**
     * The fields the wizard's answers may set.
     *
     * @return array
     */
    public static function fields(): array {
        return [
            'type' => 'the kind of field: ' . implode(', ', array_keys(self::TYPES)),
            'name' => 'the field\'s name',
            'description' => 'a short description',
            'required' => '1 if students must fill it in',
            'options' => 'the options of a menu, radiobutton or checkbox field, one per line',
        ];
    }

    /**
     * The database's Fields page.
     *
     * @return string[]
     */
    public static function pagetypes(): array {
        return ['mod-data-field'];
    }

    /**
     * Managing fields needs mod/data:managetemplates (mod/data/field.php).
     *
     * @return string[]
     */
    public static function capabilities(): array {
        return ['mod/data:managetemplates'];
    }

    /**
     * The field types installed on this site.
     *
     * @param \cm_info $cm the activity
     * @return array
     */
    public static function offered(\cm_info $cm): array {
        $installed = array_keys(\core_component::get_plugin_list('datafield'));
        return ['type' => array_values(array_map('strval', array_intersect(array_keys(self::TYPES), $installed)))];
    }

    /**
     * The options, one per line, without empty lines.
     *
     * @param array $fields field => value
     * @return string[]
     */
    protected static function options(array $fields): array {
        $lines = array_map('trim', preg_split('/\R/', (string) ($fields['options'] ?? '')));
        return array_values(array_filter($lines, fn($line) => $line !== ''));
    }

    /**
     * A known, installed type; a name not used yet in this database (as mod/data/field.php checks);
     * options for the types that list them; and the type's own validate().
     *
     * @param \cm_info $cm the activity
     * @param array $fields field => value
     * @return array field => error message
     */
    public static function check(\cm_info $cm, array $fields): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/data/lib.php');
        $errors = [];
        $type = (string) ($fields['type'] ?? '');
        if (!in_array($type, self::offered($cm)['type'], true)) {
            $errors['type'] = get_string('databasefield_error_type', 'tool_wizards');
            return $errors;
        }
        $name = trim((string) ($fields['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = get_string('databasefield_error_name', 'tool_wizards');
        } else if (\core_text::strlen($name) > self::MAXNAME) {
            $errors['name'] = get_string('maximumchars', '', self::MAXNAME);
        } else if (data_fieldname_exists($name, $cm->instance)) {
            $errors['name'] = get_string('databasefield_error_nameused', 'tool_wizards');
        }
        if (in_array($type, self::WITHOPTIONS, true) && !self::options($fields)) {
            $errors['options'] = get_string('databasefield_error_options', 'tool_wizards');
        }
        if (!$errors) {
            $field = data_get_field_new($type, self::data($cm));
            foreach ($field->validate(self::fieldinput($fields)) as $message) {
                $errors['name'] = $message;
            }
        }
        return $errors;
    }

    /**
     * The database.
     *
     * @param \cm_info $cm the activity
     * @return \stdClass
     */
    protected static function data(\cm_info $cm): \stdClass {
        global $DB;
        return $DB->get_record('data', ['id' => $cm->instance], '*', MUST_EXIST);
    }

    /**
     * The field as mod/data/field.php receives it from the type's form.
     *
     * @param array $fields field => value
     * @return \stdClass
     */
    protected static function fieldinput(array $fields): \stdClass {
        $type = (string) $fields['type'];
        $input = (object) [
            'type' => $type,
            'name' => trim((string) ($fields['name'] ?? '')),
            'description' => trim((string) ($fields['description'] ?? '')),
            'required' => empty($fields['required']) ? 0 : 1,
        ];
        foreach (self::TYPES[$type] as $param => $value) {
            $input->$param = $value;
        }
        if (in_array($type, self::WITHOPTIONS, true)) {
            $input->param1 = implode("\n", self::options($fields));
        }
        return $input;
    }

    /**
     * Add the field.
     *
     * @param \cm_info $cm the activity
     * @param array $fields field => value
     * @return string
     */
    public static function save(\cm_info $cm, array $fields): string {
        global $CFG;
        require_once($CFG->dirroot . '/mod/data/lib.php');

        $data = self::data($cm);
        $input = self::fieldinput($fields);
        $field = data_get_field_new($input->type, $data);
        $field->define_field($input);
        $field->insert_field();
        data_append_new_field_to_templates($data, $input->name);

        return get_string('databasefield_saved', 'tool_wizards', $input->name);
    }

    /**
     * The database's Fields page.
     *
     * @param \cm_info $cm the activity
     * @return \moodle_url|null
     */
    public static function url(\cm_info $cm): ?\moodle_url {
        return new \moodle_url('/mod/data/field.php', ['id' => $cm->id]);
    }
}
