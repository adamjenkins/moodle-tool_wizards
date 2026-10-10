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
 * Adds one point (Moodle: "aspect", "criterion", "assertion") to a workshop's assessment form,
 * for the workshop's current grading strategy: comments, accumulative, number of errors or rubric.
 *
 * The point is appended after the existing ones: the existing points are passed back unchanged, as
 * mod/workshop/editform.php posts them, and the strategy's own save_edit_strategy_form() saves them
 * all (mod/workshop/form/<strategy>/lib.php). The data keys mirror each strategy's
 * prepare_form_fields() / prepare_database_fields() in those files.
 *
 * Fields:
 * - description: what reviewers look at (editor value or plain text), every strategy;
 * - points: the most points the point is worth (accumulative; 1-100);
 * - weight: how much the point counts (accumulative, number of errors; 0-16);
 * - wordyes, wordno: the words for a met / not met statement (number of errors);
 * - leveldefinition[n], levelpoints[n]: rubric levels n = 1..4 (empty definitions are skipped).
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class workshop_form extends content {
    /** @var string[] The grading strategies this handler knows (the ones shipped with Moodle). */
    const STRATEGIES = ['comments', 'accumulative', 'numerrors', 'rubric'];

    /** @var int Rubric level slots the wizard offers. */
    const LEVELS = 4;

    /**
     * The activity module this content belongs to.
     *
     * @return string
     */
    public static function modname(): string {
        return 'workshop';
    }

    /**
     * The fields the wizard's answers may set.
     *
     * @return array
     */
    public static function fields(): array {
        return [
            'description' => 'what reviewers look at (editor or text), every strategy',
            'points' => 'the most points it is worth, 1-100 (accumulative)',
            'weight' => 'how much it counts, 0-16 (accumulative, numerrors)',
            'wordyes' => 'word for a met statement (numerrors)',
            'wordno' => 'word for a statement not met (numerrors)',
            'leveldefinition' => 'rubric level descriptions, leveldefinition[1] to leveldefinition[4] (rubric)',
            'levelpoints' => 'rubric level points 0-100, levelpoints[1] to levelpoints[4] (rubric)',
        ];
    }

    /**
     * Page types: the assessment form editor and the workshop page.
     *
     * @return string[]
     */
    public static function pagetypes(): array {
        return ['mod-workshop-editform', 'mod-workshop-view'];
    }

    /**
     * Editing the assessment form needs the workshop's own capability (as editform.php).
     *
     * @return string[]
     */
    public static function capabilities(): array {
        return ['mod/workshop:editdimensions'];
    }

    /**
     * The workshop API object.
     *
     * @param \cm_info $cm the activity
     * @return \workshop
     */
    protected static function workshop(\cm_info $cm): \workshop {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/workshop/locallib.php');
        $record = $DB->get_record('workshop', ['id' => $cm->instance], '*', MUST_EXIST);
        return new \workshop($record, $cm, $cm->get_course());
    }

    /**
     * Available for the shipped grading strategies, before reviewing starts (setup and submission
     * phases): changing the form while students review would make their reviews inconsistent.
     *
     * @param \cm_info $cm the activity
     * @return bool
     */
    public static function is_available(\cm_info $cm): bool {
        $workshop = self::workshop($cm);
        return in_array($workshop->strategy, self::STRATEGIES, true)
            && in_array((int) $workshop->phase, [\workshop::PHASE_SETUP, \workshop::PHASE_SUBMISSION], true);
    }

    /**
     * The workshop's grading strategy, for {"offered": {"field": "strategy", "value": "rubric"}}.
     *
     * @param \cm_info $cm the activity
     * @return array
     */
    public static function offered(\cm_info $cm): array {
        return ['strategy' => [(string) self::workshop($cm)->strategy]];
    }

    /**
     * An editor value from a field that may be an editor array or plain text.
     *
     * @param mixed $value the field
     * @return array text, format and (when the editor had one) itemid
     */
    protected static function editor($value): array {
        if (is_array($value)) {
            return ['text' => (string) ($value['text'] ?? ''), 'format' => (int) ($value['format'] ?? FORMAT_HTML)]
                + (empty($value['itemid']) ? [] : ['itemid' => (int) $value['itemid']]);
        }
        $text = trim((string) $value);
        return ['text' => $text === '' ? '' : '<p>' . nl2br(s($text), false) . '</p>', 'format' => FORMAT_HTML];
    }

    /**
     * Whether a value is a whole number from $min to $max.
     *
     * @param mixed $value the value
     * @param int $min lowest
     * @param int $max highest
     * @return bool
     */
    protected static function whole($value, int $min, int $max): bool {
        $value = is_string($value) ? trim($value) : $value;
        if (!is_numeric($value) || (float) $value != (int) $value) {
            return false;
        }
        return (int) $value >= $min && (int) $value <= $max;
    }

    /**
     * The filled rubric levels.
     *
     * @param array $fields the fields
     * @return array n => [definition, points]
     */
    protected static function levels(array $fields): array {
        $levels = [];
        for ($n = 1; $n <= self::LEVELS; $n++) {
            $definition = trim((string) ($fields['leveldefinition'][$n] ?? ''));
            if ($definition !== '') {
                $levels[$n] = [$definition, $fields['levelpoints'][$n] ?? $n - 1];
            }
        }
        return $levels;
    }

    /**
     * Check the fields for the workshop's strategy (rubric rules as in form/rubric/edit_form.php validation_inner()).
     *
     * @param \cm_info $cm the activity
     * @param array $fields the fields
     * @return array field => message
     */
    public static function check(\cm_info $cm, array $fields): array {
        $workshop = self::workshop($cm);
        $errors = [];
        if (!in_array($workshop->strategy, self::STRATEGIES, true)) {
            return ['description' => get_string('workshopform_error_strategy', 'tool_wizards')];
        }
        if (html_is_blank(self::editor($fields['description'] ?? '')['text'])) {
            $errors['description'] = get_string('workshopform_error_description', 'tool_wizards');
        }
        $strategy = $workshop->strategy;
        if ($strategy === 'accumulative' && !self::whole($fields['points'] ?? 10, 1, 100)) {
            $errors['points'] = get_string('error_range', 'tool_wizards', ['min' => 1, 'max' => 100]);
        }
        if (in_array($strategy, ['accumulative', 'numerrors'], true) && !self::whole($fields['weight'] ?? 1, 0, 16)) {
            $errors['weight'] = get_string('error_range', 'tool_wizards', ['min' => 0, 'max' => 16]);
        }
        if ($strategy === 'numerrors') {
            foreach (['wordyes', 'wordno'] as $name) {
                if (array_key_exists($name, $fields) && trim((string) $fields[$name]) === '') {
                    $errors[$name] = get_string('required');
                } else if (\core_text::strlen(trim((string) ($fields[$name] ?? ''))) > 50) {
                    $errors[$name] = get_string('maximumchars', '', 50);
                }
            }
        }
        if ($strategy === 'rubric') {
            $levels = self::levels($fields);
            if (!$levels) {
                $errors['leveldefinition'] = get_string('mustdefinelevel', 'workshopform_rubric');
            }
            $seen = [];
            foreach ($levels as [, $points]) {
                if (!self::whole($points, 0, 100)) {
                    $errors['levelpoints'] = get_string('error_range', 'tool_wizards', ['min' => 0, 'max' => 100]);
                    break;
                }
                if (isset($seen[(int) $points])) {
                    $errors['levelpoints'] = get_string('mustbeunique', 'workshopform_rubric');
                    break;
                }
                $seen[(int) $points] = true;
            }
        }
        return $errors;
    }

    /**
     * Append the point to the assessment form through the strategy's save_edit_strategy_form().
     *
     * @param \cm_info $cm the activity
     * @param array $fields the fields
     * @return string e.g. "Assessment form, point 3: Structure"
     */
    public static function save(\cm_info $cm, array $fields): string {
        global $DB;
        $workshop = self::workshop($cm);
        $strategyname = $workshop->strategy;
        $strategy = $workshop->grading_strategy_instance();

        // The current points, as the strategy's prepare_form_fields() gives them to editform.php.
        $existing = array_values($DB->get_records('workshopform_' . $strategyname, ['workshopid' => $workshop->id], 'sort, id'));
        $levels = [];
        if ($strategyname === 'rubric' && $existing) {
            $dimensionids = array_column($existing, 'id');
            $records = $DB->get_records_list('workshopform_rubric_levels', 'dimensionid', $dimensionids, 'grade, id');
            foreach ($records as $level) {
                $levels[$level->dimensionid][] = $level;
            }
        }

        $data = new \stdClass();
        $data->workshopid = $workshop->id;
        $data->strategy = $strategyname;
        $data->norepeats = count($existing) + 1;
        if ($strategyname === 'rubric') {
            $layout = $DB->get_field('workshopform_rubric_config', 'layout', ['workshopid' => $workshop->id]);
            $data->config_layout = $layout ?: 'list';
        }
        foreach ($existing as $i => $dimension) {
            $data->{'dimensionid__idx_' . $i} = $dimension->id;
            $data->{'description__idx_' . $i . '_editor'} = ['text' => $dimension->description,
                'format' => $dimension->descriptionformat];
            switch ($strategyname) {
                case 'accumulative':
                    $data->{'grade__idx_' . $i} = $dimension->grade;
                    $data->{'weight__idx_' . $i} = $dimension->weight;
                    break;
                case 'numerrors':
                    $data->{'grade0__idx_' . $i} = $dimension->grade0;
                    $data->{'grade1__idx_' . $i} = $dimension->grade1;
                    $data->{'weight__idx_' . $i} = $dimension->weight;
                    break;
                case 'rubric':
                    foreach ($levels[$dimension->id] ?? [] as $j => $level) {
                        $data->{'levelid__idx_' . $i . '__idy_' . $j} = $level->id;
                        $data->{'grade__idx_' . $i . '__idy_' . $j} = $level->grade;
                        $data->{'definition__idx_' . $i . '__idy_' . $j} = $level->definition;
                    }
                    break;
            }
        }

        // The new point, last.
        $n = count($existing);
        $description = self::editor($fields['description'] ?? '');
        $data->{'dimensionid__idx_' . $n} = 0;
        $data->{'description__idx_' . $n . '_editor'} = $description;
        switch ($strategyname) {
            case 'accumulative':
                $data->{'grade__idx_' . $n} = (int) ($fields['points'] ?? 10);
                $data->{'weight__idx_' . $n} = (int) ($fields['weight'] ?? 1);
                break;
            case 'numerrors':
                $config = get_config('workshopform_numerrors');
                $data->{'grade0__idx_' . $n} = clean_param(trim((string) ($fields['wordno'] ?? $config->grade0)), PARAM_TEXT);
                $data->{'grade1__idx_' . $n} = clean_param(trim((string) ($fields['wordyes'] ?? $config->grade1)), PARAM_TEXT);
                $data->{'weight__idx_' . $n} = (int) ($fields['weight'] ?? 1);
                break;
            case 'rubric':
                $j = 0;
                foreach (self::levels($fields) as [$definition, $points]) {
                    $data->{'levelid__idx_' . $n . '__idy_' . $j} = 0;
                    $data->{'grade__idx_' . $n . '__idy_' . $j} = (int) $points;
                    $data->{'definition__idx_' . $n . '__idy_' . $j} = clean_param($definition, PARAM_TEXT);
                    $j++;
                }
                break;
        }
        $strategy->save_edit_strategy_form($data);

        $title = shorten_text(trim(html_to_text($description['text'], 0, false)), 60);
        return get_string('workshopform_added', 'tool_wizards', ['number' => $n + 1, 'title' => $title]);
    }

    /**
     * Where the teacher sees the result: the assessment form editor.
     *
     * @param \cm_info $cm the activity
     * @return \moodle_url|null
     */
    public static function url(\cm_info $cm): ?\moodle_url {
        return new \moodle_url('/mod/workshop/editform.php', ['cmid' => $cm->id]);
    }
}
