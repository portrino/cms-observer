import { Controller } from '@hotwired/stimulus';

/*
 * Adds, removes and reorders rows of a Symfony CollectionType. New rows come
 * from the prototype in a <template>, with "__name__" replaced by a fresh index.
 * Rows are moved by dragging their handle, or with the arrow keys on it.
 * Moving only changes the DOM, so every row's hidden position field carries
 * the new order; the server sorts by it.
 */
export default class extends Controller {
    static targets = ['rows', 'prototype', 'position'];
    static values = { index: Number };

    connect() {
        this.renumber();
    }

    add() {
        this.rowsTarget.insertAdjacentHTML('beforeend', this.prototypeTarget.innerHTML.replace(/__name__/g, this.indexValue++));
        this.renumber();
        this.rowsTarget.lastElementChild.querySelector('input:not([type=hidden])')?.focus();
    }

    remove(event) {
        this.row(event).remove();
        this.renumber();
    }

    // only the handle makes a row draggable, so text in its inputs can still be selected
    grab(event) {
        this.row(event).draggable = true;
    }

    dragStart(event) {
        this.dragged = this.row(event);
        this.dragged.classList.add('upgrade-dragging');
        event.dataTransfer.effectAllowed = 'move';
    }

    dragOver(event) {
        const over = this.row(event);
        if (!this.dragged || !over || over.parentElement !== this.rowsTarget) {
            return;
        }
        event.preventDefault();
        if (over !== this.dragged) {
            const { top, height } = over.getBoundingClientRect();
            event.clientY < top + height / 2 ? over.before(this.dragged) : over.after(this.dragged);
        }
    }

    drop(event) {
        event.preventDefault();
    }

    dragEnd() {
        if (!this.dragged) {
            return;
        }
        this.dragged.classList.remove('upgrade-dragging');
        this.dragged.draggable = false;
        this.dragged = null;
        this.renumber();
    }

    key(event) {
        const row = this.row(event);
        if (event.key === 'ArrowUp' && row.previousElementSibling) {
            row.previousElementSibling.before(row);
        } else if (event.key === 'ArrowDown' && row.nextElementSibling) {
            row.nextElementSibling.after(row);
        } else {
            return;
        }
        event.preventDefault();
        this.renumber();
        event.currentTarget.focus();
    }

    row(event) {
        return event.target.closest?.('[data-form-collection-row]');
    }

    renumber() {
        this.positionTargets.forEach((input, position) => { input.value = position; });
        // rows were added, removed or moved, e.g. for the section sums
        this.dispatch('changed');
    }
}
