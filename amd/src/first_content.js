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
 * The first-content suggestions card, and the unlock message: opens a mini-wizard for each
 * option, and hides the card for now, for this course, or for good.
 *
 * @module     tool_wizards/first_content
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import ModalForm from 'core_form/modalform';
import Notification from 'core/notification';
import Pending from 'core/pending';
import {add as addToast} from 'core/toast';
import {getString} from 'core/str';
import {setUserPreference} from 'core_user/repository';

/**
 * Initialise the card.
 *
 * @param {string} selector the card
 */
export const init = (selector) => {
    const card = document.querySelector(selector);
    if (!card || card.dataset.initialised) {
        return;
    }
    card.dataset.initialised = '1';

    // Straight after creating the course or adding something, take the reader to the
    // news: "Your course is ready" or "Nice, ... was added".
    if (card.dataset.focus) {
        card.querySelector('#tool_wizards-firstcontent-heading').focus();
    }

    card.addEventListener('click', e => {
        const button = e.target.closest('[data-action]');
        if (!button) {
            return;
        }
        e.preventDefault();
        const action = button.dataset.action;
        if (action === 'add') {
            openMiniWizard(card, button);
        } else if (action === 'done') {
            // A one-time message just closes; the suggestions card says it is hidden for now.
            hideCard(card, card.dataset.region === 'tool_wizards-unlock' ? null : 'prompt_hidden_now');
        } else if (action === 'dismisscourse') {
            dismissCourse(card);
        } else if (action === 'dismissall') {
            dismissAll(card);
        }
    });
};

/**
 * Open the mini-wizard for one option.
 *
 * @param {HTMLElement} card the card
 * @param {HTMLElement} button the option's button
 */
const openMiniWizard = (card, button) => {
    const form = new ModalForm({
        formClass: button.dataset.formclass,
        args: {courseid: card.dataset.courseid},
        modalConfig: {title: button.dataset.modaltitle},
        saveButtonText: getString('add', 'core'),
        returnFocus: button,
    });
    form.addEventListener(form.events.FORM_SUBMITTED, () => {
        // Reload so the new content appears in the course, with the confirmation on top.
        const pending = new Pending('tool_wizards/first_content:reload');
        window.location.reload();
        pending.resolve();
    });
    form.show();
};

/**
 * Remove the card, say so, and move focus somewhere sensible.
 *
 * @param {HTMLElement} card the card
 * @param {string|null} stringkey what to say, if anything
 */
const hideCard = async(card, stringkey) => {
    const message = stringkey ? await getString(stringkey, 'tool_wizards') : null;
    const main = document.getElementById('region-main') || document.body;
    card.remove();
    const heading = main.querySelector('h1, h2');
    if (heading) {
        heading.setAttribute('tabindex', '-1');
        heading.focus();
    }
    if (message) {
        addToast(message);
    }
};

/**
 * Hide the suggestions on this course for good.
 *
 * @param {HTMLElement} card the card
 */
const dismissCourse = async(card) => {
    const pending = new Pending('tool_wizards/first_content:dismisscourse');
    try {
        await Ajax.call([{
            methodname: 'tool_wizards_dismiss_course',
            args: {courseid: Number(card.dataset.courseid)},
        }])[0];
        await hideCard(card, 'prompt_hidden_course');
    } catch (error) {
        Notification.exception(error);
    }
    pending.resolve();
};

/**
 * Turn wizard suggestions off everywhere (they can be turned back on in Preferences).
 *
 * @param {HTMLElement} card the card
 */
const dismissAll = async(card) => {
    const pending = new Pending('tool_wizards/first_content:dismissall');
    try {
        await setUserPreference('tool_wizards_hidesuggestions', 1);
        await hideCard(card, 'prompt_hidden_all');
    } catch (error) {
        Notification.exception(error);
    }
    pending.resolve();
};
