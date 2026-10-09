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
 * "Add with a wizard" next to core's "Add an activity or resource" in each course section
 * (edit mode). It lists the wizards the teacher may use and opens the chosen one for that section.
 *
 * Core offers no way to add an entry beside its own button, so this places one next to each
 * button it finds, also after the course page re-renders a section. If core's markup changes,
 * the links simply do not appear; the first-content card still offers the wizards.
 *
 * The list of wizards comes rendered from the server (template tool_wizards/wizard_list) in a
 * hidden template element.
 *
 * @module     tool_wizards/section_link
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Modal from 'core/modal';
import Templates from 'core/templates';
import {openWizard} from 'tool_wizards/open_wizard';

/** @var {string} Core's section-level add button (not the "insert before an activity" ones). */
const BUTTON = '[data-action="open-chooser"]:not([data-beforemod])';

/**
 * Set up the links.
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
        label: source.dataset.label,
        choose: source.dataset.choose,
        list: source.innerHTML,
        // The magic wand, as a Moodle icon (Font Awesome, or the plugin's own picture in other icon systems).
        icon: await Templates.renderPix('wand', 'tool_wizards', ''),
        only: wizards.length === 1 ? {key: wizards[0].dataset.wizard, heading: wizards[0].dataset.heading} : null,
    };
    const place = () => document.querySelectorAll(BUTTON).forEach(button => addLink(button, options));
    place();
    const region = document.getElementById('region-main') || document.body;
    new MutationObserver(place).observe(region, {childList: true, subtree: true});
};

/**
 * Add the link beside one of core's buttons, once.
 *
 * @param {HTMLElement} button core's button
 * @param {Object} options courseid, label, choose, list, icon, only
 */
const addLink = (button, options) => {
    if (button.dataset.toolWizardsLinked) {
        return;
    }
    button.dataset.toolWizardsLinked = '1';
    const section = button.dataset.sectionnum;
    const link = document.createElement('button');
    link.type = 'button';
    link.dataset.region = 'tool_wizards-sectionlink';
    // The icon is Moodle's own markup (core/templates renderPix); the label is plain text.
    link.innerHTML = options.icon;
    link.append(document.createTextNode(options.label));
    if (button.classList.contains('dropdown-item')) {
        link.className = 'dropdown-item';
        button.after(link);
    } else {
        link.className = 'btn btn-link btn-sm tool_wizards-sectionlink';
        const wrapper = document.createElement('div');
        wrapper.className = 'text-center';
        wrapper.append(link);
        button.after(wrapper);
    }
    link.addEventListener('click', e => {
        e.preventDefault();
        if (options.only) {
            openWizard({courseid: options.courseid, wizard: options.only.key, title: options.only.heading, section,
                returnFocus: link});
        } else {
            chooseWizard(options, section, link);
        }
    });
};

/**
 * Let the teacher pick a wizard, then open it.
 *
 * @param {Object} options courseid, choose, list, icon
 * @param {string} section the section number
 * @param {HTMLElement} returnFocus where focus goes back to
 */
const chooseWizard = async(options, section, returnFocus) => {
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
        openWizard({courseid: options.courseid, wizard: item.dataset.wizard, title: item.dataset.heading, section,
            returnFocus});
    });
};
