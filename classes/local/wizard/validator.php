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

namespace tool_wizards\local\wizard;

/**
 * Checks a wizard definition document against the format (see runbook/wizard.schema.json).
 *
 * Every problem is reported with the path to it, e.g. "screens[2].items[0].sets.field".
 * Anything unknown is an error, never silently ignored: an imported or AI-edited file
 * can only combine the building blocks this plugin provides.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class validator {
    /** @var string The format this version reads and writes. */
    const FORMAT = 'tool_wizards/wizard@1';

    /** @var string[] Question kinds that hold a value. */
    const VALUE_KINDS = ['cards', 'choice', 'select', 'yesno', 'text', 'textarea', 'number', 'percent', 'date',
        'duration', 'file', 'editor', 'category', 'shortname'];

    /** @var string[] Question kinds that offer choices. */
    const CHOICE_KINDS = ['cards', 'choice', 'select'];

    /** @var string[] Kinds only a course wizard may use. */
    const COURSE_KINDS = ['category', 'shortname', 'review'];

    /** @var string[] Choice sources: the source => the wizards that may use it ("course", or an activity's module name). */
    const CHOICE_SOURCES = ['course:formats' => 'course', 'course:ltitools' => 'lti'];

    /** @var string[] Shared screens. */
    const SHARED = ['shared:visibility', 'shared:groups', 'shared:completion'];

    /** @var string[] Course facts for conditions. */
    const COURSE_FACTS = ['hasgroups', 'completion', 'groupmodeforce', 'forcedgroupmode'];

    /** @var string[] Site facts for conditions. */
    const SITE_FACTS = ['comments', 'filter', 'qtype', 'showstartedcourses', 'completion', 'categorychoice'];

    /** @var string[] Keys a document may have. */
    const DOC_KEYS = ['format', 'key', 'defaultversion', 'target', 'title', 'heading', 'description', 'intro', 'picture',
        'screens', 'actions', 'notes'];

    /** @var string[] Keys a screen may have. */
    const SCREEN_KEYS = ['key', 'title', 'question', 'when', 'items', 'use', 'choices'];

    /** @var string[] Keys a question may have. */
    const ITEM_KEYS = ['key', 'kind', 'label', 'help', 'text', 'required', 'requiredmessage', 'default', 'min', 'max',
        'maxlength', 'choices', 'choicesfrom', 'sets', 'when', 'accept', 'maxfiles', 'extensions', 'units', 'optional',
        'suggestfrom',
        'placeholder'];

    /** @var string[] Keys a choice may have. */
    const CHOICE_KEYS = ['value', 'title', 'desc', 'picture', 'when', 'preset', 'sets'];

    /** @var string[] errors found */
    protected array $errors = [];

    /** @var array question key => question, collected while checking */
    protected array $questions = [];

    /** @var bool whether the document is a course wizard */
    protected bool $course = false;

    /** @var string What the wizard creates: "course", or the module name. */
    protected string $target = '';

    /** @var string|null For an in-activity wizard, its content handler class. */
    protected ?string $content = null;

    /**
     * Check a document.
     *
     * @param mixed $doc the decoded JSON
     * @return string[] the problems, empty when the document is valid
     */
    public static function check($doc): array {
        $v = new self();
        $v->document($doc);
        return $v->errors;
    }

    /**
     * Check a JSON string.
     *
     * @param string $json the JSON
     * @return string[] the problems, empty when valid
     */
    public static function check_json(string $json): array {
        $doc = json_decode($json, true);
        if (!is_array($doc)) {
            return ['(document): not valid JSON: ' . json_last_error_msg()];
        }
        return self::check($doc);
    }

    /**
     * Record a problem.
     *
     * @param string $path where
     * @param string $message what
     */
    protected function error(string $path, string $message): void {
        $this->errors[] = ($path === '' ? '(document)' : $path) . ': ' . $message;
    }

    /**
     * Report keys that are not allowed here.
     *
     * @param array $value the object
     * @param string[] $allowed the allowed keys
     * @param string $path where
     */
    protected function known_keys(array $value, array $allowed, string $path): void {
        foreach (array_keys($value) as $key) {
            if (!in_array($key, $allowed, true)) {
                $this->error($path . '.' . $key, 'unknown property (allowed: ' . implode(', ', $allowed) . ')');
            }
        }
    }

    /**
     * Check the whole document.
     *
     * @param mixed $doc the decoded JSON
     */
    protected function document($doc): void {
        if (!is_array($doc) || array_is_list($doc)) {
            $this->error('', 'must be a JSON object');
            return;
        }
        $this->known_keys($doc, self::DOC_KEYS, '');
        if (($doc['format'] ?? null) !== self::FORMAT) {
            $this->error('format', 'must be "' . self::FORMAT . '"');
        }
        if (!is_string($doc['key'] ?? null) || !preg_match('/^[a-z][a-z0-9_]{1,59}$/', $doc['key'])) {
            $this->error('key', 'must be 2-60 characters: lower-case letters, digits and _, starting with a letter');
        }
        if (isset($doc['defaultversion']) && (!is_int($doc['defaultversion']) || $doc['defaultversion'] < 1)) {
            $this->error('defaultversion', 'must be a whole number from 1');
        }
        $this->target($doc['target'] ?? null);
        $this->text($doc['title'] ?? null, 'title', true);
        foreach (['heading', 'description', 'intro'] as $name) {
            if (array_key_exists($name, $doc)) {
                $this->text($doc[$name], $name, false);
            }
        }
        if (isset($doc['picture'])) {
            $this->picture($doc['picture'], 'picture');
        }
        if (isset($doc['notes']) && !is_string($doc['notes'])) {
            $this->error('notes', 'must be a string');
        }

        $screens = $doc['screens'] ?? null;
        if (!is_array($screens) || !array_is_list($screens) || !$screens) {
            $this->error('screens', 'must be a non-empty list');
            return;
        }
        // Questions first, so conditions and presets can refer to questions on later screens.
        foreach ($screens as $i => $screen) {
            foreach ((is_array($screen) && is_array($screen['items'] ?? null)) ? $screen['items'] : [] as $j => $item) {
                if (is_array($item) && isset($item['key']) && is_string($item['key'])) {
                    if (isset($this->questions[$item['key']])) {
                        $this->error("screens[$i].items[$j].key", 'duplicate question key "' . $item['key'] . '"');
                    }
                    $this->questions[$item['key']] = $item;
                }
            }
        }
        $this->add_shared_questions($screens);
        $screenkeys = [];
        foreach ($screens as $i => $screen) {
            $key = $this->screen($screen, "screens[$i]");
            if ($key !== null) {
                if (in_array($key, $screenkeys, true)) {
                    $this->error("screens[$i].key", 'duplicate screen key "' . $key . '"');
                }
                $screenkeys[] = $key;
            }
        }
        if (isset($doc['actions'])) {
            if (!is_array($doc['actions']) || !array_is_list($doc['actions'])) {
                $this->error('actions', 'must be a list');
            } else {
                foreach ($doc['actions'] as $i => $action) {
                    $this->action($action, "actions[$i]");
                }
            }
        }
    }

    /**
     * Note the questions shared screens add, so conditions may refer to them.
     *
     * @param array $screens the screens
     */
    protected function add_shared_questions(array $screens): void {
        foreach ($screens as $screen) {
            $use = is_array($screen) ? ($screen['use'] ?? null) : null;
            $map = ['shared:visibility' => 'visible', 'shared:groups' => 'groupmode', 'shared:completion' => 'completion'];
            if (is_string($use) && isset($map[$use])) {
                $this->questions[$map[$use]] = ['kind' => 'cards', 'key' => $map[$use], 'shared' => true];
            }
        }
    }

    /**
     * Check the target.
     *
     * @param mixed $target the target
     */
    protected function target($target): void {
        if (!is_array($target)) {
            $this->error('target', 'must be {"type": "course"}, {"type": "module", "modname": "…"} or '
                . '{"type": "content", "modname": "…", "content": "…"}');
            return;
        }
        $this->known_keys($target, ['type', 'modname', 'content'], 'target');
        if (($target['type'] ?? null) === 'course') {
            $this->course = true;
            $this->target = 'course';
            return;
        }
        if (!in_array($target['type'] ?? null, ['module', 'content'], true)) {
            $this->error('target.type', 'must be "course", "module" or "content"');
            return;
        }
        $modname = $target['modname'] ?? null;
        if (!is_string($modname) || !preg_match('/^[a-z][a-z0-9_]*$/', $modname)) {
            $this->error('target.modname', 'must be a module name such as "quiz"');
        } else if (!\core_component::get_component_directory('mod_' . $modname)) {
            $this->error('target.modname', 'module "' . $modname . '" is not installed on this site');
        }
        $this->target = is_string($modname) ? $modname : '';
        if ($target['type'] === 'content') {
            $class = extensions::contents()[$target['content'] ?? ''] ?? null;
            if (!$class) {
                $this->error('target.content', 'must be one of: ' . implode(', ', array_keys(extensions::contents())));
            } else if ($class::modname() !== $modname) {
                $this->error('target.content', 'this content belongs to "' . $class::modname() . '"');
            } else {
                $this->content = $class;
            }
        } else if (isset($target['content'])) {
            $this->error('target.content', 'only for {"type": "content"}');
        }
    }

    /**
     * Check a text value.
     *
     * @param mixed $text the text
     * @param string $path where
     * @param bool $required whether it must be present
     */
    protected function text($text, string $path, bool $required): void {
        if ($text === null) {
            if ($required) {
                $this->error($path, 'is required');
            }
            return;
        }
        if (is_string($text)) {
            return;
        }
        if (!is_array($text) || ($text !== [] && array_is_list($text))) {
            $this->error($path, 'must be a string, {"string": "key"} or translations like {"en": "…", "ja": "…"}');
            return;
        }
        if (!$text && $required) {
            $this->error($path, 'is empty');
        }
        foreach ($text as $key => $value) {
            if ($key === 'string' || $key === 'component') {
                if (!is_string($value)) {
                    $this->error("$path.$key", 'must be a string');
                }
                continue;
            }
            if (!text::is_language_key((string) $key)) {
                $this->error("$path.$key", 'not a language code (use e.g. "en", "ja", "pt_br")');
            } else if (!is_string($value)) {
                $this->error("$path.$key", 'must be a string');
            }
        }
        if (isset($text['component']) && !isset($text['string'])) {
            $this->error("$path.component", 'only allowed together with "string"');
        }
        if (isset($text['string']) && is_string($text['string'])) {
            $component = is_string($text['component'] ?? null) ? $text['component'] : text::DEFAULT_COMPONENT;
            if (!get_string_manager()->string_exists($text['string'], $component)) {
                $this->error("$path.string", 'no language string "' . $text['string'] . '" in ' . $component);
            }
        }
    }

    /**
     * Check a picture reference.
     *
     * @param mixed $picture the reference
     * @param string $path where
     */
    protected function picture($picture, string $path): void {
        global $CFG;
        if (!is_string($picture)) {
            $this->error($path, 'must be "pix:<name>" or "file:<name>"');
            return;
        }
        if (preg_match('/^pix:([a-z0-9_\/]+)$/', $picture, $m)) {
            if (!is_readable($CFG->dirroot . '/admin/tool/wizards/pix/' . $m[1] . '.svg')) {
                $this->error($path, 'no shipped picture "' . $m[1] . '"');
            }
            return;
        }
        if (!preg_match('/^file:[A-Za-z0-9._-]+\.(png|jpe?g|webp|svg|gif)$/i', $picture)) {
            $this->error($path, 'must be "pix:<name>" or "file:<name>.png|jpg|jpeg|webp|svg|gif"');
        }
    }

    /**
     * Check a screen.
     *
     * @param mixed $screen the screen
     * @param string $path where
     * @return string|null its key
     */
    protected function screen($screen, string $path): ?string {
        if (!is_array($screen) || array_is_list($screen)) {
            $this->error($path, 'must be an object');
            return null;
        }
        $this->known_keys($screen, self::SCREEN_KEYS, $path);
        if (isset($screen['use'])) {
            if (!in_array($screen['use'], self::SHARED, true)) {
                $this->error("$path.use", 'must be one of ' . implode(', ', self::SHARED));
                return null;
            }
            if ($this->course || $this->content) {
                $this->error("$path.use", 'shared screens are for activity wizards only');
            }
            if (isset($screen['items'])) {
                $this->error("$path.items", 'a shared screen has no items of its own');
            }
            if (isset($screen['choices'])) {
                if ($screen['use'] !== 'shared:completion') {
                    $this->error("$path.choices", 'only shared:completion takes choices');
                } else {
                    $this->choices($screen['choices'], "$path.choices", ['kind' => 'cards']);
                }
            }
            if (isset($screen['when'])) {
                $this->condition($screen['when'], "$path.when");
            }
            return substr($screen['use'], 7);
        }
        if (!is_string($screen['key'] ?? null) || !preg_match('/^[a-z][a-z0-9_]{0,59}$/', $screen['key'])) {
            $this->error("$path.key", 'must be lower-case letters, digits and _, starting with a letter');
        }
        $this->text($screen['title'] ?? null, "$path.title", true);
        if (isset($screen['question'])) {
            $this->text($screen['question'], "$path.question", false);
        }
        if (isset($screen['when'])) {
            $this->condition($screen['when'], "$path.when");
        }
        $items = $screen['items'] ?? null;
        if (!is_array($items) || !array_is_list($items) || !$items) {
            $this->error("$path.items", 'must be a non-empty list');
        } else {
            foreach ($items as $j => $item) {
                $this->item($item, "$path.items[$j]");
            }
        }
        return is_string($screen['key'] ?? null) ? $screen['key'] : null;
    }

    /**
     * Check a question or hint.
     *
     * @param mixed $item the item
     * @param string $path where
     */
    protected function item($item, string $path): void {
        if (!is_array($item) || array_is_list($item)) {
            $this->error($path, 'must be an object');
            return;
        }
        $this->known_keys($item, self::ITEM_KEYS, $path);
        $kind = $item['kind'] ?? null;
        $kinds = array_merge(self::VALUE_KINDS, ['hint', 'review']);
        if (!in_array($kind, $kinds, true)) {
            $this->error("$path.kind", 'must be one of ' . implode(', ', $kinds));
            return;
        }
        if (in_array($kind, self::COURSE_KINDS, true) && !$this->course) {
            $this->error("$path.kind", '"' . $kind . '" is only for course wizards');
        }
        if (isset($item['when'])) {
            $this->condition($item['when'], "$path.when");
        }
        if ($kind === 'hint') {
            $this->text($item['text'] ?? null, "$path.text", true);
            return;
        }
        if ($kind === 'review') {
            return;
        }
        if (!is_string($item['key'] ?? null) || !preg_match('/^[a-z][a-z0-9_]{0,59}$/', $item['key'])) {
            $this->error("$path.key", 'must be lower-case letters, digits and _, starting with a letter');
        } else if (
            in_array($item['key'], ['courseid', 'wizard', 'section', 'preview', 'sesskey', 'id', 'startcategory',
                'fullsettings', 'wizarderrors'], true)
        ) {
            $this->error("$path.key", '"' . $item['key'] . '" is reserved');
        }
        $this->text($item['label'] ?? null, "$path.label", true);
        if (isset($item['help'])) {
            $this->text($item['help'], "$path.help", false);
        }
        if (isset($item['requiredmessage'])) {
            $this->text($item['requiredmessage'], "$path.requiredmessage", false);
        }
        if (isset($item['placeholder'])) {
            $this->text($item['placeholder'], "$path.placeholder", false);
        }
        if (isset($item['required']) && !is_bool($item['required'])) {
            $this->condition($item['required'], "$path.required");
        }
        foreach (['min', 'max'] as $bound) {
            if (isset($item[$bound]) && !is_int($item[$bound]) && !is_float($item[$bound])) {
                $this->error("$path.$bound", 'must be a number');
            }
        }
        if (isset($item['maxlength']) && (!is_int($item['maxlength']) || $item['maxlength'] < 1)) {
            $this->error("$path.maxlength", 'must be a whole number from 1');
        }
        if (in_array($kind, self::CHOICE_KINDS, true)) {
            if (isset($item['choicesfrom'])) {
                $for = self::CHOICE_SOURCES[$item['choicesfrom']] ?? null;
                if ($for === null || $for !== $this->target) {
                    $this->error("$path.choicesfrom", 'must be course:formats (course wizards) or course:ltitools '
                        . '(external tool wizards)');
                }
                if (isset($item['choices'])) {
                    $this->error("$path.choices", 'give either choices or choicesfrom, not both');
                }
            } else {
                $this->choices($item['choices'] ?? null, "$path.choices", $item);
            }
        } else if (isset($item['choices']) || isset($item['choicesfrom'])) {
            $this->error("$path.choices", 'only cards, choice and select take choices');
        }
        if (isset($item['accept'])) {
            $ok = $item['accept'] === '*' || (is_array($item['accept']) && array_is_list($item['accept'])
                && !array_filter($item['accept'], fn($e) => !is_string($e) || !preg_match('/^\.[a-z0-9]+$|^[a-z_]+$/', $e)));
            if ($kind !== 'file' || !$ok) {
                $this->error("$path.accept", 'file questions only: "*" or a list like [".pdf", ".pptx"] or ["web_image"]');
            }
        }
        if (
            isset($item['maxfiles']) && ($kind !== 'file' || !is_int($item['maxfiles'])
                || ($item['maxfiles'] !== -1 && ($item['maxfiles'] < 1 || $item['maxfiles'] > 100)))
        ) {
            $this->error("$path.maxfiles", 'file questions only: how many files, 1 to 100, or -1 for no limit');
        }
        if (isset($item['extensions'])) {
            $this->extensions($item['extensions'], "$path.extensions", $kind);
        }
        if (
            isset($item['units']) && ($kind !== 'duration' || !is_array($item['units'])
                || array_diff($item['units'], [1, 60, 3600, 86400, 604800]))
        ) {
            $this->error("$path.units", 'duration questions only: a list of 1, 60, 3600, 86400, 604800');
        }
        if (isset($item['optional']) && (!is_bool($item['optional']) || !in_array($kind, ['date', 'duration'], true))) {
            $this->error("$path.optional", 'date and duration questions only: true or false');
        }
        if (isset($item['suggestfrom']) && ($kind !== 'shortname' || !isset($this->questions[$item['suggestfrom']]))) {
            $this->error("$path.suggestfrom", 'shortname questions only: the key of an earlier question');
        }
        if (isset($item['default'])) {
            $this->default_value($item['default'], "$path.default");
        }
        if (isset($item['sets'])) {
            $this->sets($item['sets'], "$path.sets", $item);
        }
    }

    /**
     * Check file extension rules.
     *
     * @param mixed $rule the rule
     * @param string $path where
     * @param string $kind the question kind
     */
    protected function extensions($rule, string $path, string $kind): void {
        if ($kind !== 'file' || !is_array($rule) || array_is_list($rule)) {
            $this->error($path, 'file questions only: {"in": [".pdf"], "when": {…}, "message": text}');
            return;
        }
        $this->known_keys($rule, ['in', 'when', 'message'], $path);
        if (!is_array($rule['in'] ?? null) || !$rule['in']) {
            $this->error("$path.in", 'must be a non-empty list like [".pdf"]');
        }
        if (isset($rule['when'])) {
            $this->condition($rule['when'], "$path.when");
        }
        if (isset($rule['message'])) {
            $this->text($rule['message'], "$path.message", false);
        }
    }

    /**
     * Check a default value.
     *
     * @param mixed $default the default
     * @param string $path where
     */
    protected function default_value($default, string $path): void {
        if (is_scalar($default)) {
            if (
                is_string($default) && preg_match('/^config:/', $default)
                    && !preg_match('/^config:[a-z][a-z0-9_]*\/[a-z0-9_]+$/', $default)
            ) {
                $this->error($path, '"config:" defaults look like "config:quiz/attempts"');
            }
            return;
        }
        $this->error($path, 'must be a value, or "config:plugin/name", or "course:groupmode"');
    }

    /**
     * Check a list of choices.
     *
     * @param mixed $choices the choices
     * @param string $path where
     * @param array $item the question
     */
    protected function choices($choices, string $path, array $item): void {
        if (!is_array($choices) || !array_is_list($choices) || !$choices) {
            $this->error($path, 'must be a non-empty list');
            return;
        }
        $values = [];
        foreach ($choices as $i => $choice) {
            $cpath = "{$path}[$i]";
            if (!is_array($choice) || array_is_list($choice)) {
                $this->error($cpath, 'must be an object');
                continue;
            }
            $this->known_keys($choice, self::CHOICE_KEYS, $cpath);
            if (!array_key_exists('value', $choice) || !is_scalar($choice['value'])) {
                $this->error("$cpath.value", 'is required: a string or number');
            } else {
                if (in_array((string) $choice['value'], $values, true)) {
                    $this->error("$cpath.value", 'duplicate value "' . $choice['value'] . '"');
                }
                $values[] = (string) $choice['value'];
            }
            $this->text($choice['title'] ?? null, "$cpath.title", true);
            if (isset($choice['desc'])) {
                $this->text($choice['desc'], "$cpath.desc", false);
            }
            if (isset($choice['picture'])) {
                $this->picture($choice['picture'], "$cpath.picture");
            }
            if (isset($choice['when'])) {
                $this->condition($choice['when'], "$cpath.when");
            }
            if (isset($choice['sets'])) {
                if (!is_array($choice['sets']) || array_is_list($choice['sets'])) {
                    $this->error("$cpath.sets", 'must be an object of form field => value');
                } else {
                    $this->field_values($choice['sets'], "$cpath.sets");
                }
            }
            if (isset($choice['preset'])) {
                $this->preset($choice['preset'], "$cpath.preset");
            }
        }
    }

    /**
     * Check a preset: question key => value for later screens.
     *
     * @param mixed $preset the preset
     * @param string $path where
     */
    protected function preset($preset, string $path): void {
        if (!is_array($preset) || array_is_list($preset)) {
            $this->error($path, 'must be an object of question key => value');
            return;
        }
        foreach ($preset as $key => $value) {
            if (!isset($this->questions[$key])) {
                $this->error("$path.$key", 'no question with this key');
                continue;
            }
            if (!is_scalar($value)) {
                $this->error("$path.$key", 'must be a value');
                continue;
            }
            $question = $this->questions[$key];
            if (
                empty($question['shared']) && in_array($question['kind'] ?? '', self::CHOICE_KINDS, true)
                    && is_array($question['choices'] ?? null)
            ) {
                $values = array_map(fn($c) => (string) ($c['value'] ?? ''), $question['choices']);
                if (!in_array((string) $value, $values, true)) {
                    $this->error("$path.$key", 'not one of that question\'s choice values');
                }
            }
        }
    }

    /**
     * Check what a question sets.
     *
     * @param mixed $sets one mapping or a list of them
     * @param string $path where
     * @param array $item the question
     */
    protected function sets($sets, string $path, array $item): void {
        if (is_array($sets) && array_is_list($sets)) {
            foreach ($sets as $i => $one) {
                $this->mapping($one, "{$path}[$i]", $item);
            }
            return;
        }
        $this->mapping($sets, $path, $item);
    }

    /**
     * Check one mapping.
     *
     * @param mixed $map the mapping
     * @param string $path where
     * @param array $item the question
     */
    protected function mapping($map, string $path, array $item): void {
        if (!is_array($map) || array_is_list($map)) {
            $this->error($path, 'must be an object such as {"field": "name"}');
            return;
        }
        $this->known_keys($map, ['field', 'as', 'emptyfrom', 'percentof', 'transform', 'from', 'when'], $path);
        if (isset($map['when'])) {
            $this->condition($map['when'], "$path.when");
        }
        if (isset($map['transform'])) {
            if (!isset(extensions::transforms()[$map['transform']])) {
                $this->error("$path.transform", 'unknown transform "' . (is_string($map['transform']) ? $map['transform'] : '?')
                    . '" (available: ' . implode(', ', array_keys(extensions::transforms())) . ')');
            }
            if (isset($map['from']) && (!is_array($map['from']) || array_is_list($map['from']))) {
                $this->error("$path.from", 'must be an object of name => question key');
            } else {
                foreach ($map['from'] ?? [] as $name => $key) {
                    if (!isset($this->questions[$key])) {
                        $this->error("$path.from.$name", 'no question with key "' . $key . '"');
                    }
                }
            }
            return;
        }
        if (!is_string($map['field'] ?? null) || !self::field_name_ok($map['field'])) {
            $this->error("$path.field", 'must be a form field name such as "attempts" or "grade_forum[modgrade_point]"');
        } else if ($this->content && !array_key_exists(strtok($map['field'], '['), $this->content::fields())) {
            $this->error("$path.field", 'must be one of this content\'s fields: '
                . implode(', ', array_keys($this->content::fields())));
        }
        if (isset($map['as']) && !in_array($map['as'], ['editor', 'string', 'int', 'float'], true)) {
            $this->error("$path.as", 'must be editor, string, int or float');
        }
        if (isset($map['emptyfrom'])) {
            $from = $map['emptyfrom'];
            if (!is_array($from) || !isset($from['filename']) || !isset($this->questions[$from['filename']])) {
                $this->error("$path.emptyfrom", 'must be {"filename": "<key of a file question>"}');
            }
        }
        if (isset($map['percentof'])) {
            $of = $map['percentof'];
            if (!(is_int($of) || is_float($of) || (is_string($of) && preg_match('/^config:[a-z][a-z0-9_]*\/[a-z0-9_]+$/', $of)))) {
                $this->error("$path.percentof", 'must be a number or "config:plugin/name"');
            }
            if (($item['kind'] ?? '') !== 'percent') {
                $this->error("$path.percentof", 'only for percent questions');
            }
        }
    }

    /**
     * Whether a form field name is acceptable: "name", "grade[modgrade_point]", "fraction[0]".
     *
     * @param string $name the name
     * @return bool
     */
    public static function field_name_ok(string $name): bool {
        return (bool) preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\[[a-zA-Z0-9_]*\])*$/', $name);
    }

    /**
     * Check field => value pairs set by a choice.
     *
     * @param array $fields the pairs
     * @param string $path where
     */
    protected function field_values(array $fields, string $path): void {
        foreach ($fields as $name => $value) {
            if (!self::field_name_ok((string) $name)) {
                $this->error("$path.$name", 'not a form field name');
            }
            $this->field_value($value, "$path.$name");
        }
    }

    /**
     * Check a field value: a scalar, "{answer:key}", or nested object/list of these.
     *
     * @param mixed $value the value
     * @param string $path where
     */
    protected function field_value($value, string $path): void {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $this->field_value($v, "$path.$k");
            }
            return;
        }
        if (is_string($value) && preg_match('/^\{answer:([a-z0-9_]+)\}$/', $value, $m) && !isset($this->questions[$m[1]])) {
            $this->error($path, 'no question with key "' . $m[1] . '"');
        } else if (!is_scalar($value) && $value !== null) {
            $this->error($path, 'must be a value');
        }
    }

    /**
     * Check a condition.
     *
     * @param mixed $cond the condition
     * @param string $path where
     */
    protected function condition($cond, string $path): void {
        if (!is_array($cond) || array_is_list($cond) || !$cond) {
            $this->error($path, 'must be a condition object such as {"answer": "purpose", "is": "exam"}');
            return;
        }
        if (isset($cond['all']) || isset($cond['any'])) {
            $name = isset($cond['all']) ? 'all' : 'any';
            $this->known_keys($cond, [$name], $path);
            if (!is_array($cond[$name]) || !array_is_list($cond[$name]) || !$cond[$name]) {
                $this->error("$path.$name", 'must be a non-empty list of conditions');
                return;
            }
            foreach ($cond[$name] as $i => $c) {
                $this->condition($c, "$path.{$name}[$i]");
            }
            return;
        }
        if (array_key_exists('not', $cond) && !isset($cond['answer'])) {
            $this->known_keys($cond, ['not'], $path);
            $this->condition($cond['not'], "$path.not");
            return;
        }
        if (isset($cond['answer'])) {
            $this->known_keys($cond, ['answer', 'is', 'not', 'in', 'has', 'empty'], $path);
            if (!isset($this->questions[$cond['answer']])) {
                $this->error("$path.answer", 'no question with key "' . $cond['answer'] . '"');
            }
            $tests = array_intersect(array_keys($cond), ['is', 'not', 'in', 'has', 'empty']);
            if (count($tests) !== 1) {
                $this->error($path, 'give exactly one of is, not, in, has, empty');
            }
            if (isset($cond['in']) && (!is_array($cond['in']) || !array_is_list($cond['in']))) {
                $this->error("$path.in", 'must be a list');
            }
            return;
        }
        if (isset($cond['course'])) {
            $this->known_keys($cond, ['course', 'is'], $path);
            if (!in_array($cond['course'], self::COURSE_FACTS, true)) {
                $this->error("$path.course", 'must be one of ' . implode(', ', self::COURSE_FACTS));
            }
            return;
        }
        if (isset($cond['site'])) {
            $this->known_keys($cond, ['site', 'name'], $path);
            if (!in_array($cond['site'], self::SITE_FACTS, true)) {
                $this->error("$path.site", 'must be one of ' . implode(', ', self::SITE_FACTS));
            }
            if (in_array($cond['site'], ['filter', 'qtype'], true) && !is_string($cond['name'] ?? null)) {
                $this->error("$path.name", 'required for site:' . $cond['site']);
            }
            return;
        }
        if (isset($cond['capability'])) {
            $this->known_keys($cond, ['capability'], $path);
            $caps = (array) $cond['capability'];
            foreach ($caps as $cap) {
                if (!is_string($cap) || !get_capability_info($cap)) {
                    $this->error("$path.capability", 'unknown capability "' . (is_string($cap) ? $cap : '?') . '"');
                }
            }
            return;
        }
        if (isset($cond['offered'])) {
            $this->known_keys($cond, ['offered'], $path);
            $o = $cond['offered'];
            if (!is_array($o) || !is_string($o['field'] ?? null) || !array_key_exists('value', $o)) {
                $this->error("$path.offered", 'must be {"field": "…", "value": …}');
            }
            return;
        }
        $this->error($path, 'unknown condition (use answer, course, site, capability, offered, all, any or not)');
    }

    /**
     * Check an action.
     *
     * @param mixed $action the action
     * @param string $path where
     */
    protected function action($action, string $path): void {
        if (!is_array($action) || !is_string($action['action'] ?? null)) {
            $this->error($path, 'must be an object with "action"');
            return;
        }
        $actions = extensions::actions();
        if (!isset($actions[$action['action']])) {
            $this->error("$path.action", 'unknown action "' . $action['action'] . '" (available: '
                . implode(', ', array_keys($actions)) . ')');
            return;
        }
        if (isset($action['when'])) {
            $this->condition($action['when'], "$path.when");
        }
        $class = $actions[$action['action']];
        foreach ($class::check_config($action, array_keys($this->questions)) as $problem) {
            $this->error($path, $problem);
        }
    }
}
