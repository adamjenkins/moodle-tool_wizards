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
 * Puts a "Create a course with the wizard" link next to Moodle's own
 * "Add a new course" and "Create course" buttons.
 *
 * Core has no place for plugins to add such a link, so this looks for links and
 * buttons that open the new-course form and adds one beside each. When core's
 * markup changes it simply finds nothing; the wizard stays reachable from the
 * category menu and Site administration.
 *
 * @module     tool_wizards/entrylink
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/** @var {string} Marks core elements that already have a wizard link. */
const DONE = 'toolWizardsLinked';

/**
 * The category a new-course link or button opens, or null when it edits an existing course.
 *
 * @param {URL} url the target URL
 * @param {HTMLFormElement|null} form the form, for buttons that post their parameters
 * @returns {string|null} category id ('' when none is given)
 */
const newCourseCategory = (url, form) => {
    if (!url.pathname.endsWith('/course/edit.php')) {
        return null;
    }
    const params = new URLSearchParams(url.search);
    if (form) {
        new FormData(form).forEach((value, key) => params.set(key, value));
    }
    if (params.get('id')) {
        return null;
    }
    return params.get('category') || '';
};

/**
 * Build the wizard link to place next to a core element.
 *
 * @param {string} wizardurl the wizard page
 * @param {string} label the link text
 * @param {string} category the category id, or ''
 * @param {string} className classes to give the link
 * @returns {HTMLAnchorElement}
 */
const makeLink = (wizardurl, label, category, className) => {
    const url = new URL(wizardurl);
    if (category) {
        url.searchParams.set('category', category);
    }
    const link = document.createElement('a');
    link.href = url.toString();
    link.className = className;
    link.textContent = label;
    link.dataset.region = 'tool_wizards-entrylink';
    return link;
};

/**
 * Add links next to every new-course link or button under a node.
 *
 * @param {ParentNode} scope where to look
 * @param {string} wizardurl the wizard page
 * @param {string} label the link text
 */
const addLinks = (scope, wizardurl, label) => {
    // Plain links and menu items, e.g. the course management page and the category page menu.
    scope.querySelectorAll('a[href*="/course/edit.php"]').forEach(anchor => {
        if (anchor.dataset[DONE] || anchor.closest('[data-region="tool_wizards-entrylink"]')) {
            return;
        }
        anchor.dataset[DONE] = '1';
        const category = newCourseCategory(new URL(anchor.href, window.location.href), null);
        if (category === null) {
            return;
        }
        const isMenuItem = anchor.classList.contains('dropdown-item');
        let className = 'ms-2';
        if (isMenuItem) {
            className = 'dropdown-item';
        } else if (anchor.classList.contains('btn')) {
            className = 'btn btn-outline-primary ms-2';
        }
        const link = makeLink(wizardurl, label, category, className);
        if (isMenuItem) {
            link.setAttribute('role', anchor.getAttribute('role') || 'menuitem');
        }
        anchor.after(link);
    });

    // Single buttons, e.g. the front page and the Dashboard's "Create course" when there are no courses.
    scope.querySelectorAll('form[action*="/course/edit.php"]').forEach(form => {
        if (form.dataset[DONE]) {
            return;
        }
        form.dataset[DONE] = '1';
        const category = newCourseCategory(new URL(form.action, window.location.href), form);
        if (category === null) {
            return;
        }
        const container = form.closest('.singlebutton') || form;
        container.after(makeLink(wizardurl, label, category, 'btn btn-outline-primary ms-2 my-1'));
    });
};

/**
 * Start adding links, now and whenever the page adds more buttons (for example the
 * Dashboard's course overview, which renders after the page loads).
 *
 * @param {string} wizardurl the wizard page
 * @param {string} label the link text
 */
export const init = (wizardurl, label) => {
    addLinks(document, wizardurl, label);
    const region = document.getElementById('region-main') || document.body;
    let queued = false;
    const observer = new MutationObserver(() => {
        if (queued) {
            return;
        }
        queued = true;
        window.requestAnimationFrame(() => {
            queued = false;
            addLinks(region, wizardurl, label);
        });
    });
    observer.observe(region, {childList: true, subtree: true});
};
