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
 * "Add with a wizard" in the menu of each of core's "Add an activity or resource" buttons on the
 * course page (edit mode), the section's own and the ones between activities. It lists the wizards
 * the teacher may use and opens the chosen one for that place.
 *
 * The menu entries are server-rendered through core's before_activitychooserbutton_exported hook
 * ({@see \tool_wizards\hook_callbacks::before_activitychooserbutton()}), so they are there again
 * whenever the course editor re-renders a section; this only handles clicks on them. The list of
 * wizards comes rendered from the server (template tool_wizards/wizard_list) in a hidden template
 * element.
 *
 * @module     tool_wizards/section_link
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';
import Templates from 'core/templates';
import {openWizard} from 'tool_wizards/open_wizard';

/** @var {string} The menu entry added to core's add buttons. */
const ENTRY = '[data-action="tool_wizards-addwithwizard"]';

/**
 * Set up the entries.
 *
 * @param {string} selector the template element holding the list of wizards
 */
export const init = async(selector) => {
    const source = document.querySelector(selector);
    const wizards = source ? Array.from(source.content.querySelectorAll('[data-wizard]')) : [];
    if (!wizards.length) {
        return;
    }
    const options = {
        courseid: Number(source.dataset.courseid),
        choose: source.dataset.choose,
        list: source.innerHTML,
        // The magic wand, as a Moodle icon (Font Awesome, or the plugin's own picture in other icon systems).
        icon: await Templates.renderPix('wand', 'tool_wizards', ''),
        only: wizards.length === 1 ? {key: wizards[0].dataset.wizard, heading: wizards[0].dataset.heading} : null,
    };
    document.addEventListener('click', e => {
        const entry = e.target.closest(ENTRY);
        if (!entry) {
            return;
        }
        e.preventDefault();
        const place = {section: entry.dataset.sectionnum, beforemod: entry.dataset.beforemod};
        // The entry is in a dropdown that closes: focus goes back to its button.
        const returnFocus = entry.closest('.dropdown')?.querySelector('[data-bs-toggle="dropdown"]') || entry;
        if (options.only) {
            openWizard({courseid: options.courseid, wizard: options.only.key, title: options.only.heading, ...place,
                returnFocus});
        } else {
            chooseWizard(options, place, returnFocus);
        }
    });
};

/**
 * Let the teacher pick a wizard, then open it.
 *
 * @param {Object} options courseid, choose, list, icon
 * @param {Object} place section (number) and beforemod (the activity to add it before, if any)
 * @param {HTMLElement} returnFocus where focus goes back to
 */
const chooseWizard = async(options, place, returnFocus) => {
    // The list was rendered by the server from a template; the title's text is escaped here.
    const title = document.createElement('span');
    title.className = 'tool_wizards-modaltitle';
    title.innerHTML = options.icon;
    title.append(document.createTextNode(options.choose));
    const modal = await Modal.create({
        title: title.outerHTML,
        body: options.list,
        show: true,
        removeOnClose: true,
    });
    modal.getRoot()[0].addEventListener('click', e => {
        const item = e.target.closest('[data-wizard]');
        if (!item) {
            return;
        }
        modal.destroy();
        openWizard({courseid: options.courseid, wizard: item.dataset.wizard, title: item.dataset.heading, ...place,
            returnFocus});
    });
};
