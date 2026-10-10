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
 * "Add with a wizard" at the top of an activity's own page where its in-activity wizards add
 * content (the quiz's questions, the lesson's pages, ...), or of a course's question banks page
 * for the question bank wizard. One wizard opens straight away; several are offered in a list first.
 *
 * The list comes rendered from the server (template tool_wizards/wizard_list) in a hidden
 * template element.
 *
 * @module     tool_wizards/content_link
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';
import Templates from 'core/templates';
import {openContentWizard, openWizard} from 'tool_wizards/open_wizard';

/**
 * Place the button.
 *
 * @param {string} selector the template element holding the list of wizards
 */
export const init = async(selector) => {
    const source = document.querySelector(selector);
    const wizards = source ? Array.from(source.content.querySelectorAll('[data-wizard]')) : [];
    const main = document.querySelector('#region-main [role="main"]') || document.getElementById('region-main');
    if (!wizards.length || !main || main.querySelector('[data-region="tool_wizards-contentlink"]')) {
        return;
    }
    const cmid = Number(source.dataset.cmid);
    // On a course page (the question banks page) the wizards add an activity to the course instead.
    const courseid = Number(source.dataset.courseid || 0);
    const open = (wizard, title, returnFocus) => (courseid
        ? openWizard({courseid, wizard, title, returnFocus})
        : openContentWizard({cmid, wizard, title, returnFocus}));
    const icon = await Templates.renderPix('wand', 'tool_wizards', '');
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-outline-primary tool_wizards-contentlink';
    button.dataset.region = 'tool_wizards-contentlink';
    // The icon is Moodle's own markup (core/templates renderPix); the label is plain text.
    button.innerHTML = icon;
    button.append(document.createTextNode(source.dataset.label));
    const wrapper = document.createElement('div');
    wrapper.className = 'mb-3';
    wrapper.append(button);
    main.prepend(wrapper);

    button.addEventListener('click', async(e) => {
        e.preventDefault();
        if (wizards.length === 1) {
            open(wizards[0].dataset.wizard, wizards[0].dataset.heading, button);
            return;
        }
        const title = document.createElement('span');
        title.className = 'tool_wizards-modaltitle';
        title.innerHTML = icon;
        title.append(document.createTextNode(source.dataset.choose));
        const modal = await Modal.create({title: title.outerHTML, body: source.innerHTML, show: true, removeOnClose: true});
        modal.getRoot()[0].addEventListener('click', ev => {
            const item = ev.target.closest('[data-wizard]');
            if (!item) {
                return;
            }
            modal.destroy();
            open(item.dataset.wizard, item.dataset.heading, button);
        });
    });
};
