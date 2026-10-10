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
 * Open a wizard in a modal: an activity wizard from the first-content card, from "Add with a
 * wizard" in a course section, or as "Try it" from the wizard list; or an in-activity wizard
 * (a question, a lesson page, ...) from the activity's own pages, which then offers "Add another".
 *
 * @module     tool_wizards/open_wizard
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import Modal from 'core/modal';
import ModalEvents from 'core/modal_events';
import ModalSaveCancel from 'core/modal_save_cancel';
import Pending from 'core/pending';
import {getString} from 'core/str';

/** @var {string} The form every activity wizard uses. */
const FORM_CLASS = 'tool_wizards\\form\\wizard_form';

/** @var {string} The form every in-activity wizard uses. */
const CONTENT_FORM_CLASS = 'tool_wizards\\form\\content_wizard_form';

/**
 * Open a wizard.
 *
 * @param {Object} options
 * @param {number} options.courseid the course
 * @param {string} options.wizard the wizard key
 * @param {string} options.title the modal title
 * @param {number} [options.section] the section to add to
 * @param {boolean} [options.preview] "Try it": show what would be set instead of creating
 * @param {HTMLElement} [options.returnFocus] where focus goes back to
 */
export const openWizard = ({courseid, wizard, title, section, preview, returnFocus}) => {
    const args = {courseid, wizard};
    if (section !== undefined && section !== null && section !== '') {
        args.section = Number(section);
    }
    if (preview) {
        args.preview = 1;
    }
    const form = new ModalForm({
        formClass: FORM_CLASS,
        args,
        modalConfig: {title},
        saveButtonText: getString('add', 'core'),
        returnFocus,
    });
    form.addEventListener(form.events.FORM_SUBMITTED, async(e) => {
        if (e.detail && e.detail.preview) {
            await showPreview(e.detail.lines || []);
            return;
        }
        // Reload so the new content appears in the course, with the confirmation on top; an activity that is
        // not on the course page (a question bank) opens instead.
        const pending = new Pending('tool_wizards/open_wizard:reload');
        if (e.detail && e.detail.url) {
            window.location.href = e.detail.url;
        } else {
            window.location.reload();
        }
        pending.resolve();
    });
    form.show();
};

/**
 * Open an in-activity wizard. After each save the teacher may add another, until they are done;
 * then the page reloads (or, from the course page, goes to the activity) to show what was added.
 *
 * @param {Object} options
 * @param {number} options.cmid the activity
 * @param {string} options.wizard the wizard key
 * @param {string} options.title the modal title
 * @param {boolean} [options.preview] "Try it": show what would be set instead of saving
 * @param {boolean} [options.gotoactivity] when done, go to the activity instead of reloading
 * @param {HTMLElement} [options.returnFocus] where focus goes back to
 * @param {string[]} [added] what was added so far in this run
 */
export const openContentWizard = ({cmid, wizard, title, preview, gotoactivity, returnFocus}, added = []) => {
    const args = {cmid, wizard};
    if (preview) {
        args.preview = 1;
    }
    const form = new ModalForm({
        formClass: CONTENT_FORM_CLASS,
        args,
        modalConfig: {title},
        saveButtonText: getString('add', 'core'),
        returnFocus,
    });
    form.addEventListener(form.events.FORM_SUBMITTED, async(e) => {
        const result = e.detail || {};
        if (result.preview) {
            await showPreview(result.lines || []);
            return;
        }
        const done = added.concat([result.added]);
        const finish = () => {
            const pending = new Pending('tool_wizards/open_wizard:finish');
            if (gotoactivity && result.url) {
                window.location.href = result.url;
            } else {
                window.location.reload();
            }
            pending.resolve();
        };
        if (!result.repeat) {
            finish();
            return;
        }
        await askAnother(done, () => openContentWizard({cmid, wizard, title, gotoactivity, returnFocus}, done), finish);
    });
    form.show();
};

/**
 * Say what was added and offer to add another.
 *
 * @param {string[]} added what was added so far
 * @param {Function} another opens the wizard again
 * @param {Function} finish ends the run
 */
const askAnother = async(added, another, finish) => {
    const body = document.createElement('div');
    const intro = document.createElement('p');
    intro.textContent = await getString('content_added', 'tool_wizards', added.length);
    const list = document.createElement('ul');
    list.className = 'tool_wizards-added';
    added.forEach(text => {
        const item = document.createElement('li');
        item.textContent = text;
        list.append(item);
    });
    body.append(intro, list);
    const modal = await ModalSaveCancel.create({
        title: await getString('content_addanother_title', 'tool_wizards'),
        body: body.outerHTML,
        buttons: {
            save: await getString('content_addanother', 'tool_wizards'),
            cancel: await getString('content_finished', 'tool_wizards'),
        },
        removeOnClose: true,
    });
    let again = false;
    modal.getRoot().on(ModalEvents.save, () => {
        again = true;
    });
    modal.getRoot().on(ModalEvents.hidden, () => (again ? another() : finish()));
    modal.show();
};

/**
 * Show what "Try it" would have set.
 *
 * @param {string[]} lines field = value lines
 */
const showPreview = async(lines) => {
    const list = document.createElement('ul');
    list.className = 'tool_wizards-preview small';
    if (!lines.length) {
        const item = document.createElement('li');
        item.textContent = await getString('preview_nothing', 'tool_wizards');
        list.append(item);
    }
    lines.forEach(line => {
        const item = document.createElement('li');
        item.textContent = line;
        list.append(item);
    });
    const intro = document.createElement('p');
    intro.textContent = await getString('preview_result', 'tool_wizards');
    const body = document.createElement('div');
    body.append(intro, list);
    const modal = await Modal.create({
        title: await getString('preview_title', 'tool_wizards'),
        body: body.outerHTML,
        show: true,
        removeOnClose: true,
    });
    return modal;
};
