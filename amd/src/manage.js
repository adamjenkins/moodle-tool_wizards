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
 * The wizard list: switch wizards on and off without reloading the page, one at a time or a
 * whole group, and select all for the bulk actions.
 *
 * Every switch carries the ids it switches (data-ids). A wizard's own switch has one; a group's
 * has all of its wizards, and shows "mixed" when some are on and some off.
 *
 * @module     tool_wizards/manage
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import Notification from 'core/notification';
import Pending from 'core/pending';
import {add as addToast} from 'core/toast';
import {getString} from 'core/str';

/** @var {string} Every on/off switch. */
const SWITCH = '[data-action="tool_wizards-switch"]';

/**
 * Set up the page.
 */
export const init = () => {
    document.querySelectorAll(SWITCH).forEach(input => {
        input.indeterminate = Boolean(input.dataset.mixed);
    });
    document.addEventListener('change', e => {
        const input = e.target.closest(SWITCH);
        if (input) {
            toggle(input);
            return;
        }
        if (e.target.closest('[data-action="tool_wizards-selectall"]')) {
            document.querySelectorAll('.tool_wizards-select').forEach(box => {
                box.checked = e.target.checked;
            });
        }
    });
};

/**
 * Switch the wizards of one switch, and bring every switch up to date.
 *
 * @param {HTMLInputElement} input the switch
 */
const toggle = async(input) => {
    const pending = new Pending('tool_wizards/manage:toggle');
    const enabled = input.checked;
    const ids = input.dataset.ids.split(',').map(Number);
    const before = snapshot();
    try {
        const result = await Ajax.call([{methodname: 'tool_wizards_set_status', args: {ids, enabled}}])[0];
        const status = Object.fromEntries(before.map(([id, on]) => [id, on]));
        result.forEach(item => {
            status[item.id] = item.status === 1;
        });
        await refresh(status);
        addToast(await getString(enabled ? 'switched_on' : 'switched_off', 'tool_wizards', ids.length));
    } catch (error) {
        // Put everything back as it was.
        await refresh(Object.fromEntries(before));
        Notification.exception(error);
    }
    pending.resolve();
};

/**
 * Whether each wizard is on, read from its own switch.
 *
 * @return {Array} [id, on] pairs
 */
const snapshot = () => Array.from(document.querySelectorAll(SWITCH))
    .filter(input => !input.dataset.ids.includes(','))
    .map(input => [Number(input.dataset.ids), input.dataset.mixed ? false : input.checked]);

/**
 * Show the wizards' states on every switch and label.
 *
 * @param {Object} status id => on
 */
const refresh = async(status) => {
    const [on, off] = await Promise.all([getString('status_enabled', 'tool_wizards'),
        getString('status_disabled', 'tool_wizards')]);
    document.querySelectorAll(SWITCH).forEach(input => {
        const states = input.dataset.ids.split(',').map(Number).filter(id => id in status).map(id => status[id]);
        if (!states.length) {
            return;
        }
        const all = states.every(Boolean);
        const mixed = !all && states.some(Boolean);
        input.checked = all;
        input.indeterminate = mixed;
        if (mixed) {
            input.dataset.mixed = '1';
        } else {
            delete input.dataset.mixed;
        }
        const label = input.closest('td')?.querySelector('[data-region="tool_wizards-statuslabel"]');
        if (label) {
            label.textContent = all ? on : off;
        }
    });
};
