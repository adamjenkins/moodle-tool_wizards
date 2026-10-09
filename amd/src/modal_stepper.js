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
 * Shows a wizard form one screen at a time, in a modal or on a page: Back, Next, "Enough
 * questions, let's go" and the finish button; screens, questions and choices that depend on
 * earlier answers; presets that re-fill later screens when a choice is made; the review
 * screen; and the course short name suggestion.
 *
 * Started by the form itself, so it runs again whenever a modal re-renders the form
 * (for example after a server-side validation error).
 *
 * @module     tool_wizards/modal_stepper
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {notifyFormSubmittedByJavascript} from 'core_form/events';
import {getString, getStrings} from 'core/str';

/**
 * Whether a screen shows an error. Empty error containers hold whitespace, so their text is checked.
 *
 * @param {HTMLElement} step the screen
 * @returns {boolean}
 */
const hasError = step => !!step.querySelector('.is-invalid, [aria-invalid="true"]')
    || Array.from(step.querySelectorAll('.invalid-feedback, .error')).some(el => el.textContent.trim() !== '');

/** @var {number} How long to wait after typing before suggesting a short name, in ms. */
const SUGGEST_DELAY = 400;

/**
 * Set up the stepper on the form.
 *
 * @param {string} selector the form
 */
export const init = (selector) => {
    const form = document.querySelector(selector);
    if (!form || form.dataset.stepperInitialised) {
        return;
    }
    form.dataset.stepperInitialised = '1';
    new Stepper(form).start();
};

/**
 * Parse a JSON data attribute.
 *
 * @param {HTMLElement} el the element
 * @param {string} name the dataset name
 * @param {*} fallback the value when missing
 * @returns {*}
 */
const data = (el, name, fallback) => {
    try {
        return el.dataset[name] ? JSON.parse(el.dataset[name]) : fallback;
    } catch (e) {
        return fallback;
    }
};

/**
 * One wizard form, shown a screen at a time.
 */
class Stepper {
    /**
     * @param {HTMLFormElement} form the form
     */
    constructor(form) {
        this.form = form;
        this.steps = Array.from(form.querySelectorAll('fieldset.tool_wizards-step'));
        this.nav = form.querySelector('[data-region="tool_wizards-stepnav"]');
        this.presets = this.nav ? data(this.nav, 'presets', {}) : {};
        this.touched = new Set();
        this.filling = false;
        this.current = 0;
        this.suggesttimer = null;
    }

    /**
     * Wire everything and show the first screen (or the first with an error).
     */
    start() {
        if (!this.nav || !this.steps.length) {
            return;
        }
        this.hideModalSave();
        this.nav.querySelector('[data-wizard="nojs"]')?.remove();
        this.progress = this.buildProgress();

        this.nav.addEventListener('click', e => {
            const button = e.target.closest('[data-wizard]');
            if (button) {
                e.preventDefault();
                this.act(button.dataset.wizard);
            }
        });
        this.form.addEventListener('click', e => {
            const change = e.target.closest('[data-wizard-goto]');
            if (change) {
                e.preventDefault();
                this.show(Number(change.dataset.wizardGoto), true);
            }
        });

        // Enter in a one-line field moves on instead of submitting the whole form early.
        this.form.addEventListener('keydown', e => {
            if (e.key !== 'Enter' || e.target.tagName !== 'INPUT' || ['submit', 'button'].includes(e.target.type)) {
                return;
            }
            e.preventDefault();
            this.act(this.isLast() ? 'add' : 'next');
        });

        this.form.addEventListener('change', e => {
            const name = e.target.name;
            if (this.filling || !name) {
                return;
            }
            this.touched.add(name);
            if (this.presets[name] && this.presets[name][this.value(name)]) {
                this.applyPreset(this.presets[name][this.value(name)]);
            }
            this.applyConditions();
        });
        this.form.addEventListener('input', () => this.applyConditions());

        this.setupShortname();
        this.applyConditions();
        const withError = this.steps.findIndex(hasError);
        this.show(withError > 0 ? withError : 0, withError > 0);
    }

    /**
     * Handle a button.
     *
     * @param {string} action back, next, go or add
     */
    act(action) {
        if (action === 'back') {
            this.show(this.neighbour(-1), true);
        } else if (action === 'next') {
            if (this.checkStep()) {
                this.show(this.neighbour(1), true);
            }
        } else if (action === 'go' || action === 'add') {
            if (this.checkStep()) {
                this.form.requestSubmit();
            }
        }
    }

    /**
     * The next or previous screen that applies.
     *
     * @param {number} direction 1 or -1
     * @returns {number}
     */
    neighbour(direction) {
        for (let i = this.current + direction; i >= 0 && i < this.steps.length; i += direction) {
            if (this.applies(this.steps[i])) {
                return i;
            }
        }
        return this.current;
    }

    /**
     * Whether a screen applies to the answers so far.
     *
     * @param {HTMLElement} step the screen
     * @returns {boolean}
     */
    applies(step) {
        if (!this.holds(data(step, 'when', null))) {
            return false;
        }
        // A screen whose every item is hidden by its condition has nothing to ask.
        const conds = data(step, 'itemwhen', {});
        const items = data(step, 'items', []);
        return !items.length || items.some(key => !conds[key] || this.holds(conds[key]));
    }

    /**
     * Whether this is the last screen that applies.
     *
     * @returns {boolean}
     */
    isLast() {
        return this.neighbour(1) === this.current;
    }

    /**
     * Show one screen and the buttons that belong to it.
     *
     * @param {number} index the screen
     * @param {boolean} focus whether to move focus to its heading
     */
    show(index, focus) {
        this.current = index;
        this.steps.forEach((step, i) => {
            step.hidden = i !== index;
        });
        const button = name => this.nav.querySelector(`[data-wizard="${name}"]`);
        const first = this.neighbour(-1) === index;
        button('back').hidden = first;
        button('next').hidden = this.isLast();
        button('add').hidden = !this.isLast();
        // "Enough questions" once the first screen is answered, while there is still something to skip.
        button('go').hidden = first || this.isLast();

        if (this.steps[index].querySelector('[data-region="tool_wizards-review"]')) {
            this.buildReview(this.steps[index].querySelector('[data-region="tool_wizards-review"]'));
        }
        this.updateProgress();
        if (focus) {
            this.steps[index].querySelector('legend')?.focus();
        }
    }

    /**
     * The theme names of the screens, as a list above them.
     *
     * @returns {HTMLElement|null}
     */
    buildProgress() {
        if (this.steps.length < 2) {
            return null;
        }
        const list = document.createElement('ol');
        list.className = 'tool_wizards-progress list-unstyled d-flex flex-wrap gap-1 small mb-3';
        list.setAttribute('aria-label', this.nav.dataset.progresslabel);
        this.steps.forEach(step => {
            const item = document.createElement('li');
            item.textContent = step.dataset.title;
            list.append(item);
        });
        this.steps[0].before(list);
        return list;
    }

    /**
     * Mark the current screen, hide screens that do not apply.
     */
    updateProgress() {
        this.progress?.querySelectorAll('li').forEach((item, i) => {
            item.hidden = !this.applies(this.steps[i]);
            item.classList.toggle('active', i === this.current);
            item.classList.toggle('done', i < this.current);
            if (i === this.current) {
                item.setAttribute('aria-current', 'step');
            } else {
                item.removeAttribute('aria-current');
            }
        });
    }

    /**
     * The current answer to a question.
     *
     * @param {string} name the question key
     * @returns {string|null}
     */
    value(name) {
        const controls = Array.from(this.form.querySelectorAll(`[name="${CSS.escape(name)}"]`));
        if (!controls.length) {
            // A rich text editor posts its text as name[text].
            const editor = this.form.querySelector(`[name="${CSS.escape(name)}[text]"]`);
            return editor ? editor.value : null;
        }
        if (controls[0].type === 'radio') {
            const checked = controls.find(c => c.checked);
            return checked ? checked.value : null;
        }
        const checkbox = controls.find(c => c.type === 'checkbox');
        if (checkbox) {
            return checkbox.checked ? (checkbox.value || '1') : '0';
        }
        return controls[controls.length - 1].value;
    }

    /**
     * The flags of a question's chosen choice.
     *
     * @param {string} name the question key
     * @returns {string[]}
     */
    flags(name) {
        // Formslib puts a radio's attributes on its label.
        const checked = this.form.querySelector(`input[name="${CSS.escape(name)}"]:checked`);
        const holder = checked?.closest('[data-flags]');
        return holder ? holder.dataset.flags.split(' ') : [];
    }

    /**
     * Evaluate a condition (already settled on the server except for answers).
     *
     * @param {Object|null} cond the condition
     * @returns {boolean}
     */
    holds(cond) {
        if (cond === null || cond === undefined || cond === true) {
            return true;
        }
        if (cond === false) {
            return false;
        }
        if (cond.all) {
            return cond.all.every(c => this.holds(c));
        }
        if (cond.any) {
            return cond.any.some(c => this.holds(c));
        }
        if ('not' in cond && !('answer' in cond)) {
            return !this.holds(cond.not);
        }
        if ('answer' in cond) {
            const value = this.value(cond.answer);
            if ('is' in cond) {
                return value !== null && value === String(cond.is);
            }
            if ('not' in cond) {
                return value === null || value !== String(cond.not);
            }
            if ('in' in cond) {
                return value !== null && cond.in.map(String).includes(value);
            }
            if ('has' in cond) {
                return this.flags(cond.answer).includes(cond.has);
            }
            if ('empty' in cond) {
                return ((value ?? '').trim() === '') === !!cond.empty;
            }
        }
        return false;
    }

    /**
     * Show or hide the questions and choices that depend on answers.
     */
    applyConditions() {
        this.steps.forEach(step => {
            const conds = data(step, 'itemwhen', {});
            Object.entries(conds).forEach(([key, cond]) => {
                const container = this.container(key);
                if (container) {
                    container.hidden = !this.holds(cond);
                }
            });
        });
        // Formslib puts a radio's attributes (here its condition) on its label.
        this.form.querySelectorAll('.tool_wizards-cardinput[data-when]').forEach(label => {
            const ok = this.holds(data(label, 'when', null));
            const input = label.querySelector('input');
            label.hidden = !ok;
            if (!ok && input?.checked) {
                input.checked = false;
            }
        });
        this.updateProgress();
    }

    /**
     * The element holding a question.
     *
     * @param {string} key the question key
     * @returns {HTMLElement|null}
     */
    container(key) {
        const group = this.form.querySelector(`[data-groupname="${CSS.escape(key)}group"]`);
        if (group) {
            return group;
        }
        const marked = this.form.querySelector(`[data-wizard-question="${CSS.escape(key)}"]`);
        if (marked) {
            return marked.closest('.fitem') || marked;
        }
        const control = this.form.querySelector(`[name="${CSS.escape(key)}"], [name^="${CSS.escape(key)}["]`);
        return control ? control.closest('.fitem') : null;
    }

    /**
     * Check the screen before leaving it forwards: its required answers.
     *
     * @returns {boolean} whether it may be left
     */
    checkStep() {
        const step = this.steps[this.current];
        // Rich text editors copy their content into the form when told it is being submitted.
        notifyFormSubmittedByJavascript(this.form);
        let ok = true;
        data(step, 'required', []).forEach(entry => {
            const key = typeof entry === 'string' ? entry : entry.key;
            if (typeof entry !== 'string' && !this.holds(entry.when)) {
                return;
            }
            const container = this.container(key);
            if (container?.hidden) {
                return;
            }
            const value = this.value(key);
            const text = (value ?? '').replace(/<(?!img|video|audio|iframe|object)[^>]*>/gi, '').replace(/&nbsp;/g, ' ');
            if (value === null || text.trim() === '') {
                const target = this.form.querySelector(`[name="${CSS.escape(key)}"], [name="${CSS.escape(key)}[text]"]`);
                if (target) {
                    this.markInvalid(target);
                }
                ok = false;
            }
        });
        if (!ok) {
            this.showStepError(step);
        } else {
            step.querySelector('.tool_wizards-steperror')?.remove();
        }
        return ok;
    }

    /**
     * Flag a field as needing an answer.
     *
     * @param {HTMLElement} input the field
     */
    markInvalid(input) {
        input.classList.add('is-invalid');
        input.setAttribute('aria-invalid', 'true');
        const clear = () => {
            this.form.querySelectorAll(`[name="${CSS.escape(input.name)}"]`).forEach(el => {
                el.classList.remove('is-invalid');
                el.removeAttribute('aria-invalid');
            });
        };
        input.addEventListener('input', clear, {once: true});
        input.addEventListener('change', clear, {once: true});
    }

    /**
     * Say what is missing on this screen, and put focus on the first field that needs an answer.
     *
     * @param {HTMLElement} step the screen
     */
    async showStepError(step) {
        let message = step.querySelector('.tool_wizards-steperror');
        if (!message) {
            message = document.createElement('div');
            message.className = 'tool_wizards-steperror alert alert-danger py-2';
            message.setAttribute('role', 'alert');
            step.querySelector('legend').after(message);
        }
        message.textContent = await getString('error_stepincomplete', 'tool_wizards');
        step.querySelector('[aria-invalid="true"]')?.focus();
    }

    /**
     * Fill later questions with the answers that suit a choice, except those the teacher changed.
     *
     * @param {Object} preset question key => value
     */
    applyPreset(preset) {
        this.filling = true;
        Object.entries(preset).forEach(([name, value]) => {
            if (this.touched.has(name)) {
                return;
            }
            this.form.querySelectorAll(`[name="${CSS.escape(name)}"]`).forEach(control => {
                if (control.type === 'radio') {
                    control.checked = control.value === String(value);
                } else if (control.type === 'checkbox') {
                    control.checked = String(value) === '1';
                } else if (control.type !== 'hidden') {
                    control.value = String(value);
                } else {
                    return;
                }
                // Let the form's own rules (hideIf, disabledIf) follow the new value.
                control.dispatchEvent(new Event('change', {bubbles: true}));
            });
        });
        this.filling = false;
    }

    /**
     * The review screen: every answer so far, each with a way back to change it.
     *
     * @param {HTMLElement} region the review region
     */
    async buildReview(region) {
        const [changeLabel, yes, no] = await getStrings([
            {key: 'change', component: 'tool_wizards'},
            {key: 'yes', component: 'core'},
            {key: 'no', component: 'core'},
        ]);
        const list = document.createElement('dl');
        list.className = 'row';
        this.steps.forEach((step, index) => {
            if (index >= this.current || !this.applies(step)) {
                return;
            }
            // Formslib also gives each radio's label the fitem class: take only the outer items.
            const items = Array.from(step.querySelectorAll('.fitem')).filter(item => !item.parentElement.closest('.fitem'));
            items.forEach(item => {
                if (item.hidden || item.closest('[hidden]') !== step && item.closest('[hidden]')) {
                    return;
                }
                const answer = this.describeItem(item, yes, no);
                if (answer === null) {
                    return;
                }
                const label = item.querySelector('.col-form-label label, .col-form-label p, .col-form-label')
                    ?.textContent.trim() || step.dataset.title;
                const dt = document.createElement('dt');
                dt.className = 'col-sm-4';
                dt.textContent = label;
                const dd = document.createElement('dd');
                dd.className = 'col-sm-8';
                dd.textContent = answer + ' ';
                const change = document.createElement('button');
                change.type = 'button';
                change.className = 'btn btn-link btn-sm p-0 align-baseline';
                change.dataset.wizardGoto = String(index);
                change.textContent = changeLabel;
                dd.append(change);
                list.append(dt, dd);
            });
        });
        region.replaceChildren(list);
    }

    /**
     * An answer as text, for the review screen.
     *
     * @param {HTMLElement} item the form item
     * @param {string} yes "Yes"
     * @param {string} no "No"
     * @returns {string|null} null for items that are not answers
     */
    describeItem(item, yes, no) {
        const checkedRadio = item.querySelector('input[type="radio"]:checked');
        if (item.querySelector('input[type="radio"]')) {
            return checkedRadio ? (checkedRadio.closest('label')?.querySelector('strong')?.textContent
                || checkedRadio.closest('label')?.textContent.trim() || checkedRadio.value) : '–';
        }
        const select = item.querySelector('select');
        if (select && !item.querySelector('[name$="[day]"]')) {
            return select.options[select.selectedIndex]?.textContent.trim() ?? '';
        }
        if (item.querySelector('[name$="[day]"]')) {
            const enabled = item.querySelector('input[name$="[enabled]"]');
            if (enabled && !enabled.checked) {
                return '–';
            }
            return Array.from(item.querySelectorAll('select')).map(s => s.options[s.selectedIndex]?.textContent.trim())
                .join(' ');
        }
        const checkbox = item.querySelector('input[type="checkbox"]');
        if (checkbox) {
            return checkbox.checked ? yes : no;
        }
        const text = item.querySelector('input[type="text"], textarea');
        if (text) {
            return text.value.trim() || '–';
        }
        return null;
    }

    /**
     * Suggest a free course short name from the full name, and check one typed in.
     */
    setupShortname() {
        const shortname = this.form.querySelector('[data-wizard-shortname]');
        if (!shortname) {
            return;
        }
        const source = shortname.dataset.wizardShortname
            ? this.form.querySelector(`[name="${CSS.escape(shortname.dataset.wizardShortname)}"]`) : null;
        let edited = shortname.value.trim() !== '';
        shortname.addEventListener('input', () => {
            edited = shortname.value.trim() !== '';
        });
        source?.addEventListener('input', () => {
            if (edited) {
                return;
            }
            clearTimeout(this.suggesttimer);
            this.suggesttimer = setTimeout(async() => {
                if (edited || source.value.trim() === '') {
                    return;
                }
                const result = await Ajax.call([{methodname: 'tool_wizards_suggest_shortname',
                    args: {fullname: source.value}}])[0];
                if (!edited) {
                    shortname.value = result.shortname;
                }
            }, SUGGEST_DELAY);
        });
        shortname.addEventListener('change', async() => {
            if (shortname.value.trim() === '') {
                return;
            }
            const result = await Ajax.call([{methodname: 'tool_wizards_check_shortname',
                args: {shortname: shortname.value}}])[0];
            const feedback = shortname.closest('.fitem')?.querySelector('.form-control-feedback');
            if (!result.available && feedback) {
                feedback.textContent = await getString('shortname_taken', 'tool_wizards', {suggestion: result.suggestion});
                feedback.style.display = 'block';
                shortname.classList.add('is-invalid');
            } else if (feedback) {
                feedback.textContent = '';
                shortname.classList.remove('is-invalid');
            }
        });
    }

    /**
     * The modal's own Save button would submit from any screen; the stepper's buttons replace it.
     */
    hideModalSave() {
        const save = this.form.closest('.modal')?.querySelector('.modal-footer [data-action="save"]');
        if (save) {
            save.hidden = true;
            save.classList.add('d-none');
        }
    }
}
