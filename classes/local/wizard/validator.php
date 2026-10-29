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

    /** @var array Choice sources: the source => the wizards that may use it ("course", or module names). */
    const CHOICE_SOURCES = [
        'course:formats' => ['course'],
        'course:ltitools' => ['lti'],
        // The categories of the question bank an in-activity wizard is working in (with or without its top level).
        'cm:questioncategories' => ['qbank', 'quiz'],
        'cm:questioncategoryparents' => ['qbank', 'quiz'],
    ];

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
            return ['(document): ' . get_string('problem_notjson', 'tool_wizards', json_last_error_msg())];
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
     * Note a problem, worded by the language string problem_<identifier>.
     *
     * @param string $path where, as above
     * @param string $identifier the string, without its "problem_" prefix
     * @param mixed $a the string's placeholder values
     */
    protected function problem(string $path, string $identifier, $a = null): void {
        $this->error($path, get_string('problem_' . $identifier, 'tool_wizards', $a));
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
                $this->problem($path . '.' . $key, 'unknownproperty', implode(', ', $allowed));
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
            $this->problem('', 'jsonobject');
            return;
        }
        $this->known_keys($doc, self::DOC_KEYS, '');
        if (($doc['format'] ?? null) !== self::FORMAT) {
            $this->problem('format', 'mustbe', self::FORMAT);
        }
        if (!is_string($doc['key'] ?? null) || !preg_match('/^[a-z][a-z0-9_]{1,59}$/', $doc['key'])) {
            $this->problem('key', 'wizardkey');
        }
        if (isset($doc['defaultversion']) && (!is_int($doc['defaultversion']) || $doc['defaultversion'] < 1)) {
            $this->problem('defaultversion', 'wholenumber');
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
            $this->problem('notes', 'string');
        }

        $screens = $doc['screens'] ?? null;
        if (!is_array($screens) || !array_is_list($screens) || !$screens) {
            $this->problem('screens', 'nonemptylist');
            return;
        }
        // Questions first, so conditions and presets can refer to questions on later screens.
        foreach ($screens as $i => $screen) {
            foreach ((is_array($screen) && is_array($screen['items'] ?? null)) ? $screen['items'] : [] as $j => $item) {
                if (is_array($item) && isset($item['key']) && is_string($item['key'])) {
                    if (isset($this->questions[$item['key']])) {
                        $this->problem("screens[$i].items[$j].key", 'duplicatequestion', $item['key']);
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
                    $this->problem("screens[$i].key", 'duplicatescreen', $key);
                }
                $screenkeys[] = $key;
            }
        }
        if (isset($doc['actions'])) {
            if (!is_array($doc['actions']) || !array_is_list($doc['actions'])) {
                $this->problem('actions', 'list');
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
            $this->problem('target', 'target');
            return;
        }
        $this->known_keys($target, ['type', 'modname', 'content'], 'target');
        if (($target['type'] ?? null) === 'course') {
            $this->course = true;
            $this->target = 'course';
            return;
        }
        if (!in_array($target['type'] ?? null, ['module', 'content'], true)) {
            $this->problem('target.type', 'targettype');
            return;
        }
        $modname = $target['modname'] ?? null;
        if (!is_string($modname) || !preg_match('/^[a-z][a-z0-9_]*$/', $modname)) {
            $this->problem('target.modname', 'modname');
        } else if (!\core_component::get_component_directory('mod_' . $modname)) {
            $this->problem('target.modname', 'modulenotinstalled', $modname);
        }
        $this->target = is_string($modname) ? $modname : '';
        if ($target['type'] === 'content') {
            $class = extensions::contents()[$target['content'] ?? ''] ?? null;
            if (!$class) {
                $this->problem('target.content', 'oneof', implode(', ', array_keys(extensions::contents())));
            } else if ($class::modname() !== $modname) {
                $this->problem('target.content', 'contentbelongs', $class::modname());
            } else {
                $this->content = $class;
            }
        } else if (isset($target['content'])) {
            $this->problem('target.content', 'contentonly');
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
                $this->problem($path, 'required');
            }
            return;
        }
        if (is_string($text)) {
            return;
        }
        if (!is_array($text) || ($text !== [] && array_is_list($text))) {
            $this->problem($path, 'text');
            return;
        }
        if (!$text && $required) {
            $this->problem($path, 'empty');
        }
        foreach ($text as $key => $value) {
            if ($key === 'string' || $key === 'component') {
                if (!is_string($value)) {
                    $this->problem("$path.$key", 'string');
                }
                continue;
            }
            if (!text::is_language_key((string) $key)) {
                $this->problem("$path.$key", 'language');
            } else if (!is_string($value)) {
                $this->problem("$path.$key", 'string');
            }
        }
        if (isset($text['component']) && !isset($text['string'])) {
            $this->problem("$path.component", 'componentwithstring');
        }
        if (isset($text['string']) && is_string($text['string'])) {
            $component = is_string($text['component'] ?? null) ? $text['component'] : text::DEFAULT_COMPONENT;
            if (!get_string_manager()->string_exists($text['string'], $component)) {
                $this->problem("$path.string", 'nostring', (object) ['string' => $text['string'], 'component' => $component]);
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
            $this->problem($path, 'picture');
            return;
        }
        if (preg_match('/^pix:([a-z0-9_\/]+)$/', $picture, $m)) {
            if (!is_readable($CFG->dirroot . '/admin/tool/wizards/pix/' . $m[1] . '.svg')) {
                $this->problem($path, 'noshippedpicture', $m[1]);
            }
            return;
        }
        if (!preg_match('/^file:[A-Za-z0-9._-]+\.(png|jpe?g|webp|svg|gif)$/i', $picture)) {
            $this->problem($path, 'picturefile');
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
            $this->problem($path, 'object');
            return null;
        }
        $this->known_keys($screen, self::SCREEN_KEYS, $path);
        if (isset($screen['use'])) {
            if (!in_array($screen['use'], self::SHARED, true)) {
                $this->problem("$path.use", 'oneof', implode(', ', self::SHARED));
                return null;
            }
            if ($this->course || $this->content) {
                $this->problem("$path.use", 'sharedactivity');
            }
            if (isset($screen['items'])) {
                $this->problem("$path.items", 'shareditems');
            }
            if (isset($screen['choices'])) {
                if ($screen['use'] !== 'shared:completion') {
                    $this->problem("$path.choices", 'sharedchoices');
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
            $this->problem("$path.key", 'itemkey');
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
            $this->problem("$path.items", 'nonemptylist');
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
            $this->problem($path, 'object');
            return;
        }
        $this->known_keys($item, self::ITEM_KEYS, $path);
        $kind = $item['kind'] ?? null;
        $kinds = array_merge(self::VALUE_KINDS, ['hint', 'review']);
        if (!in_array($kind, $kinds, true)) {
            $this->problem("$path.kind", 'oneof', implode(', ', $kinds));
            return;
        }
        if (in_array($kind, self::COURSE_KINDS, true) && !$this->course) {
            $this->problem("$path.kind", 'coursekind', $kind);
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
            $this->problem("$path.key", 'itemkey');
        } else if (
            in_array($item['key'], ['courseid', 'wizard', 'section', 'preview', 'sesskey', 'id', 'startcategory',
                'fullsettings', 'wizarderrors'], true)
        ) {
            $this->problem("$path.key", 'reserved', $item['key']);
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
                $this->problem("$path.$bound", 'number');
            }
        }
        if (isset($item['maxlength']) && (!is_int($item['maxlength']) || $item['maxlength'] < 1)) {
            $this->problem("$path.maxlength", 'wholenumber');
        }
        if (in_array($kind, self::CHOICE_KINDS, true)) {
            if (isset($item['choicesfrom'])) {
                $for = self::CHOICE_SOURCES[$item['choicesfrom']] ?? [];
                $content = str_starts_with($item['choicesfrom'], 'cm:');
                if (!in_array($this->target, $for, true) || $content !== ($this->content !== null)) {
                    $this->problem("$path.choicesfrom", 'choicesfrom');
                }
                if (isset($item['choices'])) {
                    $this->problem("$path.choices", 'choicesboth');
                }
            } else {
                $this->choices($item['choices'] ?? null, "$path.choices", $item);
            }
        } else if (isset($item['choices']) || isset($item['choicesfrom'])) {
            $this->problem("$path.choices", 'choicesonly');
        }
        if (isset($item['accept'])) {
            $ok = $item['accept'] === '*' || (is_array($item['accept']) && array_is_list($item['accept'])
                && !array_filter($item['accept'], fn($e) => !is_string($e) || !preg_match('/^\.[a-z0-9]+$|^[a-z_]+$/', $e)));
            if ($kind !== 'file' || !$ok) {
                $this->problem("$path.accept", 'accept');
            }
        }
        if (
            isset($item['maxfiles']) && ($kind !== 'file' || !is_int($item['maxfiles'])
                || ($item['maxfiles'] !== -1 && ($item['maxfiles'] < 1 || $item['maxfiles'] > 100)))
        ) {
            $this->problem("$path.maxfiles", 'maxfiles');
        }
        if (isset($item['extensions'])) {
            $this->extensions($item['extensions'], "$path.extensions", $kind);
        }
        if (
            isset($item['units']) && ($kind !== 'duration' || !is_array($item['units'])
                || array_diff($item['units'], [1, 60, 3600, 86400, 604800]))
        ) {
            $this->problem("$path.units", 'units');
        }
        if (isset($item['optional']) && (!is_bool($item['optional']) || !in_array($kind, ['date', 'duration'], true))) {
            $this->problem("$path.optional", 'optional');
        }
        if (isset($item['suggestfrom']) && ($kind !== 'shortname' || !isset($this->questions[$item['suggestfrom']]))) {
            $this->problem("$path.suggestfrom", 'suggestfrom');
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
            $this->problem($path, 'filecheck');
            return;
        }
        $this->known_keys($rule, ['in', 'when', 'message'], $path);
        if (!is_array($rule['in'] ?? null) || !$rule['in']) {
            $this->problem("$path.in", 'filecheckin');
        }
        if (isset($rule['when'])) {
            $this->condition($rule['when'], "$path.when");
        }
        if (isset($rule['message'])) {
            $this->text($rule['message'], "$path.message", false);
        }
    }

    /**
     * Check a "config:plugin/name" reference: its shape, and that it is a course or activity default
     * a wizard may show ({@see engine::config_default_allowed()}).
     *
     * @param string $reference the reference
     * @param string $path where
     */
    protected function config_reference(string $reference, string $path): void {
        if (!preg_match(engine::CONFIG_DEFAULT, $reference, $m)) {
            $this->problem($path, 'configshape');
        } else if (!engine::config_default_allowed($m[1], $m[2])) {
            $this->problem($path, 'confignotallowed', $reference);
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
            if (is_string($default) && preg_match('/^config:/', $default)) {
                $this->config_reference($default, $path);
            }
            return;
        }
        $this->problem($path, 'default');
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
            $this->problem($path, 'nonemptylist');
            return;
        }
        $values = [];
        foreach ($choices as $i => $choice) {
            $cpath = "{$path}[$i]";
            if (!is_array($choice) || array_is_list($choice)) {
                $this->problem($cpath, 'object');
                continue;
            }
            $this->known_keys($choice, self::CHOICE_KEYS, $cpath);
            if (!array_key_exists('value', $choice) || !is_scalar($choice['value'])) {
                $this->problem("$cpath.value", 'choicevalue');
            } else {
                if (in_array((string) $choice['value'], $values, true)) {
                    $this->problem("$cpath.value", 'duplicatevalue', $choice['value']);
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
                    $this->problem("$cpath.sets", 'sets');
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
            $this->problem($path, 'preset');
            return;
        }
        foreach ($preset as $key => $value) {
            if (!isset($this->questions[$key])) {
                $this->problem("$path.$key", 'noquestion');
                continue;
            }
            if (!is_scalar($value)) {
                $this->problem("$path.$key", 'value');
                continue;
            }
            $question = $this->questions[$key];
            if (
                empty($question['shared']) && in_array($question['kind'] ?? '', self::CHOICE_KINDS, true)
                    && is_array($question['choices'] ?? null)
            ) {
                $values = array_map(fn($c) => (string) ($c['value'] ?? ''), $question['choices']);
                if (!in_array((string) $value, $values, true)) {
                    $this->problem("$path.$key", 'notachoice');
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
            $this->problem($path, 'mapping');
            return;
        }
        $this->known_keys($map, ['field', 'as', 'emptyfrom', 'percentof', 'transform', 'from', 'when'], $path);
        if (isset($map['when'])) {
            $this->condition($map['when'], "$path.when");
        }
        if (isset($map['transform'])) {
            if (!isset(extensions::transforms()[$map['transform']])) {
                $this->problem("$path.transform", 'unknowntransform', (object) [
                    'name' => is_string($map['transform']) ? $map['transform'] : '?',
                    'available' => implode(', ', array_keys(extensions::transforms())),
                ]);
            }
            if (isset($map['from']) && (!is_array($map['from']) || array_is_list($map['from']))) {
                $this->problem("$path.from", 'from');
            } else {
                foreach ($map['from'] ?? [] as $name => $key) {
                    if (!isset($this->questions[$key])) {
                        $this->problem("$path.from.$name", 'noquestionkey', $key);
                    }
                }
            }
            return;
        }
        if (!is_string($map['field'] ?? null) || !self::field_name_ok($map['field'])) {
            $this->problem("$path.field", 'field');
        } else if ($this->content && !array_key_exists(strtok($map['field'], '['), $this->content::fields())) {
            $this->problem("$path.field", 'contentfield', implode(', ', array_keys($this->content::fields())));
        }
        if (isset($map['as']) && !in_array($map['as'], ['editor', 'string', 'int', 'float'], true)) {
            $this->problem("$path.as", 'as');
        }
        if (isset($map['emptyfrom'])) {
            $from = $map['emptyfrom'];
            if (!is_array($from) || !isset($from['filename']) || !isset($this->questions[$from['filename']])) {
                $this->problem("$path.emptyfrom", 'emptyfrom');
            }
        }
        if (isset($map['percentof'])) {
            $of = $map['percentof'];
            if (is_string($of) && preg_match('/^config:/', $of)) {
                $this->config_reference($of, "$path.percentof");
            } else if (!is_int($of) && !is_float($of)) {
                $this->problem("$path.percentof", 'percentof');
            }
            if (($item['kind'] ?? '') !== 'percent') {
                $this->problem("$path.percentof", 'percentonly');
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
                $this->problem("$path.$name", 'notfield');
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
            $this->problem($path, 'noquestionkey', $m[1]);
        } else if (!is_scalar($value) && $value !== null) {
            $this->problem($path, 'value');
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
            $this->problem($path, 'condition');
            return;
        }
        if (isset($cond['all']) || isset($cond['any'])) {
            $name = isset($cond['all']) ? 'all' : 'any';
            $this->known_keys($cond, [$name], $path);
            if (!is_array($cond[$name]) || !array_is_list($cond[$name]) || !$cond[$name]) {
                $this->problem("$path.$name", 'conditions');
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
                $this->problem("$path.answer", 'noquestionkey', $cond['answer']);
            }
            $tests = array_intersect(array_keys($cond), ['is', 'not', 'in', 'has', 'empty']);
            if (count($tests) !== 1) {
                $this->problem($path, 'oneoperator');
            }
            if (isset($cond['in']) && (!is_array($cond['in']) || !array_is_list($cond['in']))) {
                $this->problem("$path.in", 'list');
            }
            return;
        }
        if (isset($cond['course'])) {
            $this->known_keys($cond, ['course', 'is'], $path);
            if (!in_array($cond['course'], self::COURSE_FACTS, true)) {
                $this->problem("$path.course", 'oneof', implode(', ', self::COURSE_FACTS));
            }
            return;
        }
        if (isset($cond['site'])) {
            $this->known_keys($cond, ['site', 'name'], $path);
            if (!in_array($cond['site'], self::SITE_FACTS, true)) {
                $this->problem("$path.site", 'oneof', implode(', ', self::SITE_FACTS));
            }
            if (in_array($cond['site'], ['filter', 'qtype'], true) && !is_string($cond['name'] ?? null)) {
                $this->problem("$path.name", 'sitename', $cond['site']);
            }
            return;
        }
        if (isset($cond['capability'])) {
            $this->known_keys($cond, ['capability'], $path);
            $caps = (array) $cond['capability'];
            foreach ($caps as $cap) {
                if (!is_string($cap) || !get_capability_info($cap)) {
                    $this->problem("$path.capability", 'unknowncapability', is_string($cap) ? $cap : '?');
                }
            }
            return;
        }
        if (isset($cond['offered'])) {
            $this->known_keys($cond, ['offered'], $path);
            $o = $cond['offered'];
            if (!is_array($o) || !is_string($o['field'] ?? null) || !array_key_exists('value', $o)) {
                $this->problem("$path.offered", 'offered');
            }
            return;
        }
        $this->problem($path, 'unknowncondition');
    }

    /**
     * Check an action.
     *
     * @param mixed $action the action
     * @param string $path where
     */
    protected function action($action, string $path): void {
        if (!is_array($action) || !is_string($action['action'] ?? null)) {
            $this->problem($path, 'action');
            return;
        }
        $actions = extensions::actions();
        if (!isset($actions[$action['action']])) {
            $this->problem("$path.action", 'unknownaction', (object) [
                'name' => $action['action'],
                'available' => implode(', ', array_keys($actions)),
            ]);
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
