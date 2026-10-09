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
 * Adds a wizard's screens to a form: one fieldset per screen, one element per question,
 * choices as cards, and the Back / Next / "Enough questions, let's go" / Add buttons that
 * tool_wizards/modal_stepper drives.
 *
 * @package    tool_wizards
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form_builder {
    /** @var engine the wizard */
    protected engine $engine;

    /** @var \MoodleQuickForm the form */
    protected \MoodleQuickForm $mform;

    /**
     * Constructor.
     *
     * @param engine $engine the wizard
     * @param \MoodleQuickForm $mform the form
     */
    public function __construct(engine $engine, \MoodleQuickForm $mform) {
        $this->engine = $engine;
        $this->mform = $mform;
    }

    /**
     * Add every screen and the buttons.
     *
     * @param string $finishlabel the last button's label
     * @param bool $preview whether this is "Try it"
     */
    public function build(string $finishlabel, bool $preview = false): void {
        $mform = $this->mform;
        $doc = $this->engine->definition();
        $first = true;
        foreach ($this->engine->screens() as $screen) {
            $this->open_screen($screen);
            if ($first) {
                $mform->addElement('static', 'wizarderrors', '', '');
                if ($preview) {
                    $mform->addElement('html', \html_writer::div(
                        get_string('preview_notice', 'tool_wizards'),
                        'alert alert-info'
                    ));
                }
                if (!empty($doc['intro'])) {
                    $mform->addElement('html', \html_writer::tag('p', s(text::get($doc['intro']))));
                }
                $first = false;
            }
            foreach ($screen['items'] as $item) {
                $this->add_item($item);
            }
            $mform->addElement('html', \html_writer::end_tag('fieldset'));
        }
        $mform->addElement('html', $this->navigation($finishlabel));
    }

    /**
     * Start a screen.
     *
     * @param array $screen the screen
     */
    protected function open_screen(array $screen): void {
        $title = text::get($screen['title']);
        $question = isset($screen['question']) ? text::get($screen['question']) : $title;
        $attributes = ['class' => 'tool_wizards-step', 'data-step' => $screen['key'], 'data-title' => $title,
            'data-items' => json_encode(array_column($screen['items'], 'key'))];
        if ($screen['cond'] !== null) {
            $attributes['data-when'] = json_encode($screen['cond']);
        }
        $conds = [];
        foreach ($screen['items'] as $item) {
            if ($item['cond'] !== null) {
                $conds[$item['key']] = $item['cond'];
            }
        }
        if ($conds) {
            $attributes['data-itemwhen'] = json_encode($conds);
        }
        $required = [];
        foreach ($screen['items'] as $item) {
            if (($item['required'] ?? false) === true) {
                $required[] = $item['key'];
            } else if (is_array($item['required'] ?? null)) {
                $cond = $this->engine->client_condition($item['required']);
                if ($cond !== false) {
                    $required[] = ['key' => $item['key'], 'when' => $cond];
                }
            }
        }
        if ($required) {
            $attributes['data-required'] = json_encode($required);
        }
        $html = \html_writer::start_tag('fieldset', $attributes);
        $html .= \html_writer::tag('legend', s($question), ['class' => 'h5 mb-3', 'tabindex' => '-1']);
        $this->mform->addElement('html', $html);
    }

    /**
     * Add one question or hint.
     *
     * @param array $item the item
     */
    protected function add_item(array $item): void {
        $mform = $this->mform;
        $key = $item['key'];
        $label = isset($item['label']) ? text::get($item['label']) : '';
        $attributes = ['data-wizard-question' => $key];
        switch ($item['kind']) {
            case 'hint':
                $mform->addElement('html', \html_writer::div(
                    s(text::get($item['text'])),
                    'tool_wizards-hint text-body-secondary mb-3',
                    ['data-wizard-question' => $key]
                ));
                return;
            case 'review':
                $mform->addElement('html', \html_writer::div('', 'tool_wizards-review', ['data-region' => 'tool_wizards-review']));
                return;
            case 'text':
            case 'shortname':
                if (isset($item['placeholder'])) {
                    $attributes['placeholder'] = text::get($item['placeholder']);
                }
                if ($item['kind'] === 'shortname') {
                    $attributes['data-wizard-shortname'] = $item['suggestfrom'] ?? '';
                }
                $mform->addElement('text', $key, $label, $attributes + ['size' => 40]);
                $mform->setType($key, PARAM_TEXT);
                break;
            case 'textarea':
                $mform->addElement('textarea', $key, $label, $attributes + ['rows' => 3, 'cols' => 50]);
                $mform->setType($key, PARAM_TEXT);
                break;
            case 'number':
            case 'percent':
                $mform->addElement('text', $key, $label, $attributes + ['size' => 6, 'inputmode' => 'decimal']);
                $mform->setType($key, PARAM_RAW_TRIMMED);
                break;
            case 'select':
                $options = [];
                foreach ($item['choices'] as $choice) {
                    $options[(string) $choice['value']] = text::get($choice['title']);
                }
                $mform->addElement('select', $key, $label, $options, $attributes);
                $mform->setType($key, PARAM_RAW);
                break;
            case 'cards':
            case 'choice':
                $this->add_cards($item, $label);
                break;
            case 'yesno':
                // Without a separate checkbox text, the label is the checkbox text.
                $text = isset($item['text']) ? text::get($item['text']) : $label;
                $mform->addElement('advcheckbox', $key, isset($item['text']) ? $label : '', $text, $attributes);
                break;
            case 'date':
                $mform->addElement('date_time_selector', $key, $label, ['optional' => $item['optional'] ?? true]);
                break;
            case 'duration':
                $units = $item['units'] ?? [MINSECS, HOURSECS];
                $mform->addElement('duration', $key, $label, ['optional' => $item['optional'] ?? true,
                    'defaultunit' => $units[0], 'units' => $units]);
                break;
            case 'file':
                global $CFG;
                $accept = $item['accept'] ?? '*';
                $mform->addElement('filemanager', $key, $label, null, ['maxfiles' => 1, 'subdirs' => 0,
                    'maxbytes' => $CFG->maxbytes, 'accepted_types' => $accept]);
                break;
            case 'editor':
                $mform->addElement('editor', $key, $label, ['rows' => 10], self::editor_options($this->engine->context()));
                $mform->setType($key, PARAM_RAW);
                break;
            case 'category':
                $mform->addElement('select', $key, $label, \tool_wizards\local\course_creator::get_categories(), $attributes);
                $mform->setType($key, PARAM_INT);
                break;
        }
        if (!empty($item['help'])) {
            $mform->addElement('static', $key . '_help', '', \html_writer::span(
                s(text::get($item['help'])),
                'small text-body-secondary'
            ));
        }
        $default = $this->engine->default_for($item);
        if ($default !== null && !in_array($item['kind'], ['file', 'editor'], true)) {
            $mform->setDefault($key, $default);
        }
    }

    /**
     * Choices as cards, each with its title, explanation and picture.
     *
     * @param array $item the question
     * @param string $label the group label
     */
    protected function add_cards(array $item, string $label): void {
        $mform = $this->mform;
        $key = $item['key'];
        $radios = [];
        foreach ($item['choices'] as $choice) {
            $attributes = ['class' => 'tool_wizards-cardinput'];
            if (!empty($choice['flags'])) {
                $attributes['data-flags'] = implode(' ', $choice['flags']);
            }
            if ($choice['cond'] ?? null) {
                $attributes['data-when'] = json_encode($choice['cond']);
            }
            $picture = $item['kind'] === 'cards' ? $this->picture_html($choice['picture'] ?? null) : '';
            $radios[] = $mform->createElement(
                'radio',
                $key,
                '',
                self::card_html(
                    $picture,
                    text::get($choice['title']),
                    isset($choice['desc']) ? text::get($choice['desc']) : ''
                ),
                (string) $choice['value'],
                $attributes
            );
        }
        $mform->addGroup($radios, $key . 'group', $label, '', false);
        $mform->setType($key, PARAM_RAW);
    }

    /**
     * A choice card's label: a picture, a title and a line saying when to choose it.
     *
     * @param string $picture the picture markup, or ''
     * @param string $title the title
     * @param string $desc when to choose it
     * @return string HTML
     */
    public static function card_html(string $picture, string $title, string $desc): string {
        return \html_writer::span(
            ($picture !== '' ? \html_writer::span($picture, 'tool_wizards-cardpicture', ['aria-hidden' => 'true']) : '') .
            \html_writer::span(
                \html_writer::tag('strong', s($title), ['class' => 'd-block']) .
                ($desc !== '' ? \html_writer::span(s($desc), 'small text-body-secondary') : ''),
                'tool_wizards-cardtext'
            ),
            'tool_wizards-card'
        );
    }

    /**
     * A picture's markup: shipped pictures inline (they follow the theme's colours),
     * uploaded ones as an image, never inline.
     *
     * @param string|null $ref "pix:<name>" or "file:<name>"
     * @return string
     */
    public function picture_html(?string $ref): string {
        return self::picture($ref, $this->engine->wizardid());
    }

    /**
     * A picture's markup.
     *
     * @param string|null $ref "pix:<name>" or "file:<name>"
     * @param int $wizardid the wizard
     * @return string
     */
    public static function picture(?string $ref, int $wizardid): string {
        global $CFG;
        if ($ref === null) {
            return '';
        }
        if (preg_match('/^pix:([a-z0-9_\/]+)$/', $ref, $m)) {
            $file = $CFG->dirroot . '/admin/tool/wizards/pix/' . $m[1] . '.svg';
            return is_readable($file) ? trim(file_get_contents($file)) : '';
        }
        if (preg_match('/^file:([A-Za-z0-9._-]+)$/', $ref, $m) && $wizardid) {
            $url = \moodle_url::make_pluginfile_url(
                \core\context\system::instance()->id,
                'tool_wizards',
                'picture',
                $wizardid,
                '/',
                $m[1]
            );
            return \html_writer::empty_tag('img', ['src' => $url->out(false), 'alt' => '', 'class' => 'img-fluid']);
        }
        return '';
    }

    /**
     * Editor options for an editor question.
     *
     * @param \context $context the context
     * @return array
     */
    public static function editor_options(\context $context): array {
        global $CFG;
        return ['maxfiles' => EDITOR_UNLIMITED_FILES, 'maxbytes' => $CFG->maxbytes, 'context' => $context,
            'noclean' => false, 'trusttext' => false];
    }

    /**
     * Fresh draft areas for the file and editor questions.
     *
     * @return array question key => initial value
     */
    public function draft_defaults(): array {
        $out = [];
        foreach ($this->engine->questions() as $key => $item) {
            if ($item['kind'] === 'file') {
                $draftitemid = 0;
                file_prepare_draft_area($draftitemid, null, null, null, null);
                $out[$key] = $draftitemid;
            } else if ($item['kind'] === 'editor') {
                $draftitemid = 0;
                file_prepare_draft_area($draftitemid, null, null, null, null);
                $out[$key] = ['text' => '', 'format' => editors_get_preferred_format(), 'itemid' => $draftitemid];
            }
        }
        return $out;
    }

    /**
     * Back, Next, "Enough questions, let's go", the finish button, and the presets for the stepper.
     *
     * @param string $finishlabel the finish button's label
     * @return string HTML
     */
    protected function navigation(string $finishlabel): string {
        // The stepper shows its buttons; without JavaScript, every screen shows and a plain submit button finishes.
        $button = fn($action, $label, $class) => \html_writer::tag(
            'button',
            $label,
            ['type' => 'button', 'class' => 'btn ' . $class, 'data-wizard' => $action, 'hidden' => 'hidden']
        );
        $buttons = \html_writer::tag('button', $finishlabel, ['type' => 'submit', 'class' => 'btn btn-primary',
                'data-wizard' => 'nojs'])
            . $button('back', get_string('back', 'tool_wizards'), 'btn-secondary')
            . $button('go', get_string('letsgo', 'tool_wizards'), 'btn-outline-primary')
            . $button('next', get_string('next', 'tool_wizards'), 'btn-primary')
            . $button('add', $finishlabel, 'btn-primary');
        return \html_writer::div($buttons, 'tool_wizards-stepnav d-flex flex-wrap gap-2 justify-content-end mt-3', [
            'data-region' => 'tool_wizards-stepnav',
            'data-presets' => json_encode((object) $this->engine->presets()),
            'data-progresslabel' => get_string('progress', 'tool_wizards'),
        ]);
    }
}
