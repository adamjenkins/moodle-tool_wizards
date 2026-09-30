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

namespace tool_wizards\output;

use core\output\renderable;
use core\output\renderer_base;
use core\output\templatable;
use tool_wizards\local\module_types;
use tool_wizards\local\prompt;

/**
 * The first-content suggestions card on a course page.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class first_content implements renderable, templatable {
    /** @var \stdClass the course */
    protected \stdClass $course;

    /** @var string[] the types the user may add, in order */
    protected array $types;

    /** @var bool whether the user has just created the course with the wizard */
    protected bool $justcreated;

    /** @var int|null the course module a mini-wizard has just added */
    protected ?int $justadded;

    /** @var bool whether a quiz was added without the first question that was asked for */
    protected bool $questionfailed;

    /**
     * Constructor.
     *
     * @param \stdClass $course the course
     * @param string[] $types the types the user may add, in order
     * @param bool $justcreated whether the user has just created the course with the wizard
     * @param int|null $justadded the course module a mini-wizard has just added
     * @param bool $questionfailed whether a quiz was added without the first question asked for
     */
    public function __construct(
        \stdClass $course,
        array $types,
        bool $justcreated,
        ?int $justadded,
        bool $questionfailed = false
    ) {
        $this->questionfailed = $questionfailed;
        $this->course = $course;
        $this->types = $types;
        $this->justcreated = $justcreated;
        $this->justadded = $justadded;
    }

    /**
     * Export the data for the template.
     *
     * @param renderer_base $output the renderer
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $added = null;
        $types = $this->types;
        if ($this->justadded) {
            $modinfo = get_fast_modinfo($this->course);
            $cm = $modinfo->cms[$this->justadded] ?? null;
            if ($cm) {
                $added = [
                    'name' => $cm->get_formatted_name(),
                    'url' => $cm->url ? $cm->url->out(false) : '',
                ];
                // Suggest something different next.
                $others = array_values(array_filter($types, fn($t) => module_types::modname($t) !== $cm->modname));
                $types = array_slice($others ?: $types, 0, prompt::NEXT_SUGGESTIONS);
            }
        }

        $options = [];
        foreach ($types as $type) {
            $modname = module_types::modname($type);
            $options[] = [
                'type' => $type,
                'formclass' => module_types::formclass($type),
                'label' => get_string('type_' . $type, 'tool_wizards'),
                'description' => get_string('type_' . $type . '_desc', 'tool_wizards'),
                'modaltitle' => get_string('type_' . $type . '_title', 'tool_wizards'),
                'iconurl' => $output->image_url('monologo', 'mod_' . $modname)->out(false),
                'modname' => $modname,
            ];
        }

        if ($added) {
            $heading = get_string('prompt_added', 'tool_wizards', $added['name']);
            $question = get_string('prompt_next', 'tool_wizards');
        } else if ($this->justcreated) {
            $heading = get_string('prompt_ready', 'tool_wizards');
            $question = get_string('prompt_first', 'tool_wizards');
        } else {
            $heading = get_string('prompt_getstarted', 'tool_wizards');
            $question = get_string('prompt_first', 'tool_wizards');
        }

        return [
            'courseid' => (int) $this->course->id,
            'heading' => $heading,
            'question' => $question,
            'added' => $added,
            'questionfailed' => $this->questionfailed,
            'focus' => $this->justcreated || $added !== null,
            'options' => $options,
            'preferencesurl' => (new \moodle_url('/admin/tool/wizards/preferences.php'))->out(false),
        ];
    }
}
