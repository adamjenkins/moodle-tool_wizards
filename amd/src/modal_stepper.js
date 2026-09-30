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
 * Shows a mini-wizard form one screen at a time inside its modal, with Back, Next,
 * "Enough questions, let's go" and Add, and re-fills the later screens with the
 * choices that suit the purpose the teacher picked.
 *
 * Started by the form itself, so it runs again whenever the modal re-renders the
 * form (for example after a server-side validation error).
 *
 * @module     tool_wizards/modal_stepper
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getString} from 'core/str';

/** @var {string} Anything the form shows as an error. */
const ERROR_SELECTOR = '.is-invalid, [aria-invalid="true"], .invalid-feedback:not(:empty), .error:not(:empty)';

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
 * One mini-wizard form, shown a screen at a time.
 */
class Stepper {
    /**
     * @param {HTMLFormElement} form the form
     */
    constructor(form) {
        this.form = form;
        this.steps = Array.from(form.querySelectorAll('fieldset.tool_wizards-step'));
        this.nav = form.querySelector('[data-region="tool_wizards-stepnav"]');
        this.presets = JSON.parse(this.nav?.dataset.presets || '{}');
        this.touched = new Set();
        this.filling = false;
        this.current = 0;
    }

    /**
     * Build the progress list, wire the buttons, and show the first screen (or the first with an error).
     */
    start() {
        if (!this.nav || !this.steps.length) {
            return;
        }
        this.hideModalSave();
        this.progress = this.buildProgress();

        this.nav.addEventListener('click', e => {
            const button = e.target.closest('[data-wizard]');
            if (!button) {
                return;
            }
            e.preventDefault();
            this.act(button.dataset.wizard);
        });

        // Enter in a one-line field moves on instead of submitting the whole form early.
        this.form.addEventListener('keydown', e => {
            if (e.key !== 'Enter' || e.target.tagName !== 'INPUT' || ['submit', 'button'].includes(e.target.type)) {
                return;
            }
            e.preventDefault();
            this.act(this.isLast() ? 'add' : 'next');
        });

        // Remember what the teacher chose themselves, so a new purpose does not overwrite it.
        this.form.addEventListener('change', e => {
            if (this.filling || !e.target.name) {
                return;
            }
            if (e.target.name === 'purpose') {
                this.applyPreset(e.target.value);
            } else {
                this.touched.add(e.target.name);
            }
        });

        const withError = this.steps.findIndex(step => step.querySelector(ERROR_SELECTOR));
        this.show(withError > 0 ? withError : 0, withError > 0);
    }

    /**
     * Handle a button.
     *
     * @param {string} action back, next, go or add
     */
    act(action) {
        if (action === 'back') {
            this.show(Math.max(0, this.current - 1), true);
        } else if (action === 'next') {
            if (this.checkStep()) {
                this.show(this.current + 1, true);
            }
        } else if (action === 'go' || action === 'add') {
            if (this.checkStep()) {
                this.form.requestSubmit();
            }
        }
    }

    /**
     * Whether this is the last screen.
     *
     * @returns {boolean}
     */
    isLast() {
        return this.current === this.steps.length - 1;
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
        button('back').hidden = index === 0;
        button('next').hidden = this.isLast();
        button('add').hidden = !this.isLast();
        // "Enough questions" once the essentials are answered, and while there is still something to skip.
        button('go').hidden = index === 0 || this.isLast();

        this.progress?.querySelectorAll('li').forEach((item, i) => {
            item.classList.toggle('active', i === index);
            item.classList.toggle('done', i < index);
            if (i === index) {
                item.setAttribute('aria-current', 'step');
            } else {
                item.removeAttribute('aria-current');
            }
        });
        if (focus) {
            this.steps[index].querySelector('legend')?.focus();
        }
    }

    /**
     * The theme names of the screens, as a list above them. Nothing when there is only one screen.
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
     * Check the screen before leaving it forwards: required answers, and a purpose if there is a choice.
     *
     * @returns {boolean} whether it may be left
     */
    checkStep() {
        const step = this.steps[this.current];
        let ok = true;
        step.querySelectorAll('[data-wizard-required]').forEach(input => {
            if (input.value.trim() === '') {
                this.markInvalid(input);
                ok = false;
            }
        });
        const purposes = step.querySelectorAll('input[name="purpose"]');
        if (purposes.length && !step.querySelector('input[name="purpose"]:checked')) {
            this.markInvalid(purposes[0]);
            ok = false;
        }
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
        input.addEventListener('input', () => {
            input.classList.remove('is-invalid');
            input.removeAttribute('aria-invalid');
        }, {once: true});
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
     * Fill the later screens with the choices that suit a purpose, except those the teacher changed.
     *
     * @param {string} purpose the purpose
     */
    applyPreset(purpose) {
        const preset = this.presets[purpose];
        if (!preset) {
            return;
        }
        this.filling = true;
        Object.entries(preset).forEach(([name, value]) => {
            if (this.touched.has(name)) {
                return;
            }
            const controls = this.form.querySelectorAll(`[name="${CSS.escape(name)}"]`);
            controls.forEach(control => {
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
