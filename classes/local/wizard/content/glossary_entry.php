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
 * An entry (a term and its definition) added to a glossary.
 *
 * Saved with glossary_edit_entry() from mod/glossary/lib.php, as mod/glossary/edit.php does, so
 * approval, files, the entry_created event, completion and the linking cache behave as usual.
 * The checks follow mod_glossary_entry_form::validation() in mod/glossary/edit_form.php.
 *
 * @package    tool_wizards
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class glossary_entry extends content {
    /** @var int The longest term the glossary_entries.concept column holds. */
    const MAXCONCEPT = 255;

    /**
     * The activity module this content belongs to.
     *
     * @return string
     */
    public static function modname(): string {
        return 'glossary';
    }

    /**
     * The fields the wizard's answers may set.
     *
     * @return array
     */
    public static function fields(): array {
        return [
            'concept' => 'the term',
            'definition' => 'what it means (an editor value: text, format, itemid)',
            'aliases' => 'other words for it, one per line (keywords)',
            'usedynalink' => '1 to link the term wherever it appears in the course (when the glossary allows it)',
            'casesensitive' => '1 to link only when the case matches',
            'fullmatch' => '1 to link whole words only',
        ];
    }

    /**
     * The glossary's own page.
     *
     * @return string[]
     */
    public static function pagetypes(): array {
        return ['mod-glossary-view'];
    }

    /**
     * Adding an entry needs mod/glossary:write (mod/glossary/edit.php).
     *
     * @return string[]
     */
    public static function capabilities(): array {
        return ['mod/glossary:write'];
    }

    /**
     * Linking can be chosen per entry only when the glossary allows entries to be linked.
     *
     * @param \cm_info $cm the activity
     * @return array
     */
    public static function offered(\cm_info $cm): array {
        $glossary = self::glossary($cm);
        return ['usedynalink' => $glossary->usedynalink ? ['0', '1'] : ['0']];
    }

    /**
     * The glossary.
     *
     * @param \cm_info $cm the activity
     * @return \stdClass
     */
    protected static function glossary(\cm_info $cm): \stdClass {
        global $DB;
        return $DB->get_record('glossary', ['id' => $cm->instance], '*', MUST_EXIST);
    }

    /**
     * A term and a definition are required, the term must be new unless the glossary allows
     * duplicates, and a one-character keyword must not be a reserved symbol.
     *
     * @param \cm_info $cm the activity
     * @param array $fields field => value
     * @return array field => error message
     */
    public static function check(\cm_info $cm, array $fields): array {
        global $CFG;
        require_once($CFG->dirroot . '/mod/glossary/lib.php');
        $errors = [];
        $glossary = self::glossary($cm);
        $concept = trim((string) ($fields['concept'] ?? ''));
        if ($concept === '') {
            $errors['concept'] = get_string('glossaryentry_error_concept', 'tool_wizards');
        } else if (\core_text::strlen($concept) > self::MAXCONCEPT) {
            $errors['concept'] = get_string('maximumchars', '', self::MAXCONCEPT);
        } else if (!$glossary->allowduplicatedentries && glossary_concept_exists($glossary, $concept)) {
            $errors['concept'] = get_string('errconceptalreadyexists', 'glossary');
        }
        $definition = $fields['definition'] ?? null;
        if (!is_array($definition) || html_is_blank((string) ($definition['text'] ?? ''))) {
            $errors['definition'] = get_string('glossaryentry_error_definition', 'tool_wizards');
        }
        foreach (preg_split('/\R/', (string) ($fields['aliases'] ?? '')) as $alias) {
            // The same rule as the glossary's own entry form: no single reserved character.
            if (strlen(trim($alias)) == 1 && preg_match('/[$-\/:-?{-~!"^_\x60\[\]]/', trim($alias))) {
                $errors['aliases'] = get_string('errreservedkeywords', 'glossary');
            }
        }
        return $errors;
    }

    /**
     * Add the entry.
     *
     * @param \cm_info $cm the activity
     * @param array $fields field => value
     * @return string
     */
    public static function save(\cm_info $cm, array $fields): string {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->dirroot . '/mod/glossary/lib.php');

        $glossary = self::glossary($cm);
        $course = get_course($cm->course);
        $editor = $fields['definition'];

        $entry = new \stdClass();
        $entry->id = null;
        $entry->concept = trim((string) $fields['concept']);
        $entry->definition_editor = [
            'text' => (string) ($editor['text'] ?? ''),
            'format' => (int) ($editor['format'] ?? FORMAT_HTML),
            'itemid' => (int) ($editor['itemid'] ?? 0),
        ];
        // One keyword per line, as the entry form's textarea gives them.
        $entry->aliases = implode("\n", array_map('trim', preg_split('/\R/', (string) ($fields['aliases'] ?? ''))));
        if ($glossary->usedynalink && array_key_exists('usedynalink', $fields)) {
            $entry->usedynalink = empty($fields['usedynalink']) ? 0 : 1;
            $entry->casesensitive = $entry->usedynalink && !empty($fields['casesensitive']) ? 1 : 0;
            $entry->fullmatch = $entry->usedynalink && !empty($fields['fullmatch']) ? 1 : 0;
        } else {
            // The entry form's defaults (its hidden values when the glossary does not link entries).
            $entry->usedynalink = (int) ($CFG->glossary_linkentries ?? 0);
            $entry->casesensitive = (int) ($CFG->glossary_casesensitive ?? 0);
            $entry->fullmatch = (int) ($CFG->glossary_fullmatch ?? 0);
        }

        $entry = glossary_edit_entry($entry, $course, $cm, $glossary, $cm->context);

        $concept = format_string($entry->concept, true, ['context' => $cm->context, 'escape' => false]);
        return get_string($entry->approved ? 'glossaryentry_saved' : 'glossaryentry_saved_pending', 'tool_wizards', $concept);
    }
}
