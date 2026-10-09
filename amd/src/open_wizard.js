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
 * Open an activity wizard in a modal: from the first-content card, from "Add with a wizard"
 * in a course section, or as "Try it" from the wizard list.
 *
 * @module     tool_wizards/open_wizard
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import ModalForm from 'core_form/modalform';
import Modal from 'core/modal';
import Pending from 'core/pending';
import {getString} from 'core/str';

/** @var {string} The form every activity wizard uses. */
const FORM_CLASS = 'tool_wizards\\form\\wizard_form';

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
        // Reload so the new content appears in the course, with the confirmation on top.
        const pending = new Pending('tool_wizards/open_wizard:reload');
        window.location.reload();
        pending.resolve();
    });
    form.show();
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
