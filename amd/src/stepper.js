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
 * Turns the course wizard's long form into one question at a time.
 *
 * Each step is a fieldset. Only the current one is shown; focus moves to its heading
 * and the change is announced in a live region. Steps that do not apply to the
 * chosen layout or category are skipped. Without this module the page still works
 * as a single form.
 *
 * @module     tool_wizards/stepper
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {getStrings} from 'core/str';
import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Pending from 'core/pending';

/** @var {number} How long to wait after typing before suggesting a short name, in ms. */
const SUGGEST_DELAY = 400;

/**
 * Initialise the stepper.
 *
 * @param {string} selector the wizard's root element
 */
export const init = async(selector) => {
    const root = document.querySelector(selector);
    if (!root || root.dataset.initialised) {
        return;
    }
    root.dataset.initialised = '1';
    const pending = new Pending('tool_wizards/stepper:init');
    try {
        const strings = await loadStrings();
        const wizard = new Stepper(root, strings);
        wizard.start(root.dataset.startstep || 'name');
    } catch (error) {
        // Without the stepper the page is still one working form.
        Notification.exception(error);
    } finally {
        pending.resolve();
    }
};

/**
 * Load the strings the stepper needs.
 *
 * @returns {Promise<Object>} strings by key
 */
const loadStrings = async() => {
    const keys = ['progresstext', 'stepannounce', 'error_fullname', 'error_numsections', 'error_startdate',
        'fullname', 'shortname', 'category', 'layout', 'numsections', 'startdate', 'visibility',
        'visible_now_summary', 'visible_later_summary', 'change', 'change_label', 'shortname_taken'];
    const values = await getStrings(keys.map(key => ({key, component: 'tool_wizards'})));
    const strings = {};
    keys.forEach((key, i) => {
        strings[key] = values[i];
    });
    return strings;
};

/**
 * Replace {$a->name} placeholders in a string.
 *
 * @param {string} text the string
 * @param {Object} values the values
 * @returns {string}
 */
const fill = (text, values) => text.replace(/\{\$a->(\w+)\}/g, (match, name) => values[name] ?? match);

/**
 * The step-by-step behaviour of one wizard form.
 */
class Stepper {
    /**
     * Constructor.
     *
     * @param {HTMLElement} root the wizard's root element
     * @param {Object} strings loaded strings
     */
    constructor(root, strings) {
        this.root = root;
        this.strings = strings;
        this.form = root.querySelector('[data-region="form"]');
        this.steps = Array.from(root.querySelectorAll('[data-step]'));
        this.announcer = root.querySelector('[data-region="announce"]');
        this.progress = root.querySelector('[data-region="progress"]');
        this.progresstext = root.querySelector('[data-region="progresstext"]');
        this.progressbar = root.querySelector('[data-region="progressbar"]');
        this.fullname = root.querySelector('[data-region="fullname"]');
        this.shortname = root.querySelector('[data-region="shortname"]');
        this.shortnameedited = this.shortname.value !== '';
        this.current = null;
        this.suggesttimer = null;
        this.shortnamecheck = null;
    }

    /**
     * Show the first step and wire up the buttons.
     *
     * @param {string} startstep the step to open first
     */
    start(startstep) {
        this.progress.classList.remove('d-none');
        // Enter is handled below, so the no-JavaScript default submit button is not needed.
        this.root.querySelector('[data-region="defaultsubmit"]')?.remove();
        this.root.querySelectorAll('[data-action="back"], [data-action="next"]').forEach(button => {
            button.classList.remove('d-none');
        });

        this.root.addEventListener('click', e => {
            const button = e.target.closest('[data-action]');
            if (!button) {
                return;
            }
            if (button.dataset.action === 'next') {
                e.preventDefault();
                this.next();
            } else if (button.dataset.action === 'back') {
                e.preventDefault();
                this.back();
            } else if (button.dataset.action === 'goto') {
                e.preventDefault();
                this.show(button.dataset.target, true);
            }
        });

        // Enter in a text field means "Next", never "submit", until the last step.
        this.form.addEventListener('keydown', e => {
            if (e.key !== 'Enter' || e.target.tagName !== 'INPUT' || e.target.type === 'submit') {
                return;
            }
            if (this.current !== 'review') {
                e.preventDefault();
                this.next();
            }
        });

        this.fullname.addEventListener('input', () => this.queueSuggestion());
        this.shortname.addEventListener('input', () => {
            this.shortnameedited = this.shortname.value.trim() !== '';
        });
        this.shortname.addEventListener('change', () => {
            this.shortnamecheck = this.checkShortname();
        });

        // Show the first step without moving focus: the page has only just loaded,
        // unless we came back with an error, when focus must go to it.
        const hasError = this.root.querySelector('.is-invalid, [data-region="othererrors"]') !== null;
        const first = this.activeSteps().includes(startstep) ? startstep : 'name';
        this.show(first, hasError);
        if (hasError) {
            const step = this.stepElement(first);
            const target = step.querySelector('.is-invalid, [data-region="othererrors"]');
            if (target) {
                // The field's aria-describedby reads out the error with it.
                target.focus();
            }
        }
    }

    /**
     * The names of the steps that apply to the current answers, in order.
     *
     * @returns {string[]}
     */
    activeSteps() {
        const format = this.form.querySelector('[data-region="format"]:checked');
        const canvisibility = this.canChooseVisibility();
        const later = this.form.querySelector('[data-region="visible"][value="0"]');
        // With core's "show courses on their start date" task on, "later" means "on the start date",
        // so the date is asked for every layout, not only for weekly sections.
        const showlater = this.root.dataset.showstartedtask === '1' && canvisibility && later && later.checked;
        const conditions = {
            usessections: format ? format.dataset.usessections === '1' : false,
            startdate: (format ? format.dataset.usesstartdate === '1' : false) || showlater,
            canvisibility: canvisibility,
        };
        return this.steps
            .filter(step => !step.dataset.when || conditions[step.dataset.when])
            .map(step => step.dataset.step);
    }

    /**
     * Whether the chosen category lets the creator decide the course visibility.
     *
     * @returns {boolean}
     */
    canChooseVisibility() {
        const select = this.form.querySelector('[data-region="category"]');
        if (select) {
            const option = select.options[select.selectedIndex];
            return option ? option.dataset.canvisibility === '1' : false;
        }
        const hidden = this.form.querySelector('[data-region="categoryhidden"]');
        return hidden ? hidden.dataset.canvisibility === '1' : false;
    }

    /**
     * Show one step.
     *
     * @param {string} name the step
     * @param {boolean} focus whether to move focus to the step heading
     */
    show(name, focus) {
        const active = this.activeSteps();
        this.steps.forEach(step => {
            step.hidden = step.dataset.step !== name;
        });
        this.current = name;

        const index = active.indexOf(name);
        const title = this.stepElement(name).querySelector('[data-region="steptitle"]');
        const values = {current: index + 1, total: active.length, title: title.textContent.trim()};
        this.progresstext.textContent = fill(this.strings.progresstext, values);
        this.progressbar.style.width = Math.round(100 * (index + 1) / active.length) + '%';

        // The first step has no Back.
        this.stepElement(name).querySelectorAll('[data-action="back"]').forEach(button => {
            button.classList.toggle('d-none', index === 0);
        });
        if (name === 'review') {
            this.buildSummary(active);
        }
        if (name === 'startdate') {
            const format = this.form.querySelector('[data-region="format"]:checked');
            const weeks = format && format.dataset.usesstartdate === '1';
            const weekshelp = this.root.querySelector('[data-region="startdatehelp-weeks"]');
            if (weekshelp) {
                weekshelp.hidden = !weeks;
            }
        }
        if (focus) {
            this.announcer.textContent = fill(this.strings.stepannounce, values);
            title.focus();
        }
    }

    /**
     * The fieldset of a step.
     *
     * @param {string} name the step
     * @returns {HTMLElement}
     */
    stepElement(name) {
        return this.steps.find(step => step.dataset.step === name);
    }

    /**
     * Go to the next step, if the current one is complete.
     */
    async next() {
        if (!this.validate(this.current)) {
            return;
        }
        if (this.current === 'name') {
            // Wait for the short name check, which leaving the field has just started.
            const available = await (this.shortnamecheck || this.checkShortname());
            this.shortnamecheck = null;
            if (available === false) {
                this.shortname.focus();
                return;
            }
        }
        const active = this.activeSteps();
        const index = active.indexOf(this.current);
        if (index < active.length - 1) {
            this.show(active[index + 1], true);
        }
    }

    /**
     * Go back one step.
     */
    back() {
        const active = this.activeSteps();
        const index = active.indexOf(this.current);
        if (index > 0) {
            this.show(active[index - 1], true);
        }
    }

    /**
     * Check a step's answers in the browser. The server checks everything again.
     *
     * @param {string} name the step
     * @returns {boolean} whether the step is complete
     */
    validate(name) {
        const step = this.stepElement(name);
        let problem = null;
        if (name === 'name' && this.fullname.value.trim() === '') {
            problem = [this.fullname, this.strings.error_fullname];
        } else if (name === 'sections') {
            const input = step.querySelector('[data-region="numsections"]');
            const value = Number(input.value);
            if (input.value === '' || !Number.isInteger(value) || value < 0 || value > Number(input.max)) {
                problem = [input, fill(this.strings.error_numsections, {max: input.max})];
            }
        } else if (name === 'startdate') {
            const input = step.querySelector('[data-region="startdate"]');
            if (input.value === '') {
                problem = [input, this.strings.error_startdate];
            }
        }
        step.querySelectorAll('[data-region="error"]').forEach(el => {
            el.textContent = '';
            el.classList.remove('d-block');
        });
        step.querySelectorAll('.is-invalid').forEach(el => {
            el.classList.remove('is-invalid');
            el.removeAttribute('aria-invalid');
        });
        if (problem) {
            const [input, message] = problem;
            const error = input.parentElement.querySelector('[data-region="error"]');
            error.textContent = message;
            error.classList.add('d-block');
            input.classList.add('is-invalid');
            input.setAttribute('aria-invalid', 'true');
            if (!input.getAttribute('aria-describedby').includes(error.id)) {
                input.setAttribute('aria-describedby', input.getAttribute('aria-describedby') + ' ' + error.id);
            }
            input.focus();
            return false;
        }
        // The layout decides the section limit.
        if (name === 'layout') {
            const format = this.form.querySelector('[data-region="format"]:checked');
            const sections = this.form.querySelector('[data-region="numsections"]');
            if (format && sections) {
                sections.max = format.dataset.maxsections;
            }
        }
        return true;
    }

    /**
     * Fill in the review step's summary of every answer, each with a "Change" button.
     *
     * @param {string[]} active the steps that apply
     */
    buildSummary(active) {
        const summary = this.root.querySelector('[data-region="summary"]');
        const rows = [];
        const add = (step, label, value) => {
            if (active.includes(step)) {
                rows.push({step, label, value});
            }
        };
        add('name', this.strings.fullname, this.fullname.value.trim());
        add('name', this.strings.shortname, this.shortname.value.trim());
        const category = this.form.querySelector('[data-region="category"]');
        if (category) {
            add('category', this.strings.category, category.options[category.selectedIndex].text);
        }
        const format = this.form.querySelector('[data-region="format"]:checked');
        if (format) {
            add('layout', this.strings.layout, format.dataset.label);
        }
        add('sections', this.strings.numsections, this.form.querySelector('[data-region="numsections"]').value);
        const startdate = this.form.querySelector('[data-region="startdate"]');
        if (startdate.value) {
            const date = new Date(startdate.value + 'T00:00:00');
            add('startdate', this.strings.startdate, date.toLocaleDateString(document.documentElement.lang || undefined,
                {year: 'numeric', month: 'long', day: 'numeric'}));
        }
        const visible = this.form.querySelector('[data-region="visible"]:checked');
        if (visible) {
            add('visibility', this.strings.visibility,
                visible.value === '1' ? this.strings.visible_now_summary : this.strings.visible_later_summary);
        }

        summary.replaceChildren();
        rows.forEach(row => {
            const dt = document.createElement('dt');
            dt.className = 'col-sm-4';
            dt.textContent = row.label;
            const dd = document.createElement('dd');
            dd.className = 'col-sm-8 d-flex flex-wrap gap-2 align-items-baseline';
            const value = document.createElement('span');
            value.textContent = row.value;
            const change = document.createElement('button');
            change.type = 'button';
            change.className = 'btn btn-link p-0 tool_wizards-change';
            change.dataset.action = 'goto';
            change.dataset.target = row.step;
            change.textContent = this.strings.change;
            change.setAttribute('aria-label', fill(this.strings.change_label, {name: row.label}));
            dd.append(value, change);
            summary.append(dt, dd);
        });
        summary.classList.remove('d-none');
    }

    /**
     * Suggest a short name shortly after the full name stops changing, unless the
     * teacher has typed their own.
     */
    queueSuggestion() {
        if (this.shortnameedited) {
            return;
        }
        clearTimeout(this.suggesttimer);
        this.suggesttimer = setTimeout(() => this.suggest(), SUGGEST_DELAY);
    }

    /**
     * Ask the server for a free short name based on the full name.
     */
    async suggest() {
        const fullname = this.fullname.value.trim();
        if (this.shortnameedited || fullname === '') {
            return;
        }
        const pending = new Pending('tool_wizards/stepper:suggest');
        try {
            const result = await Ajax.call([{
                methodname: 'tool_wizards_suggest_shortname',
                args: {fullname},
            }])[0];
            if (!this.shortnameedited && this.fullname.value.trim() === fullname) {
                this.shortname.value = result.shortname;
            }
        } catch (error) {
            // A suggestion is only a convenience: the teacher can type one, and the server makes one if needed.
            window.console.warn(error);
        }
        pending.resolve();
    }

    /**
     * Warn straight away when a typed short name is already taken.
     *
     * @returns {Promise<boolean|null>} whether it is free; null when unknown
     */
    async checkShortname() {
        const shortname = this.shortname.value.trim();
        const error = document.getElementById('tool_wizards_shortname_error');
        if (shortname === '') {
            return null;
        }
        let available = null;
        const pending = new Pending('tool_wizards/stepper:check');
        try {
            const result = await Ajax.call([{
                methodname: 'tool_wizards_check_shortname',
                args: {shortname},
            }])[0];
            if (this.shortname.value.trim() !== shortname) {
                pending.resolve();
                return null;
            }
            available = result.available;
            if (result.available) {
                error.textContent = '';
                error.classList.remove('d-block');
                this.shortname.classList.remove('is-invalid');
                this.shortname.removeAttribute('aria-invalid');
            } else {
                error.textContent = fill(this.strings.shortname_taken, {suggestion: result.suggestion});
                error.classList.add('d-block');
                this.shortname.classList.add('is-invalid');
                this.shortname.setAttribute('aria-invalid', 'true');
                if (!this.shortname.getAttribute('aria-describedby').includes(error.id)) {
                    this.shortname.setAttribute('aria-describedby',
                        this.shortname.getAttribute('aria-describedby') + ' ' + error.id);
                }
            }
        } catch (e) {
            // The server checks again when the course is created.
            window.console.warn(e);
        }
        pending.resolve();
        return available;
    }
}
