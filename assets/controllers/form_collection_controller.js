import { Controller } from '@hotwired/stimulus';

/*
 * Adds, removes and moves rows of a Symfony CollectionType. New rows come from
 * the prototype in a <template>, with "__name__" replaced by a fresh index.
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

    moveUp(event) {
        const row = this.row(event);
        row.previousElementSibling?.before(row);
        this.renumber();
        event.currentTarget.focus();
    }

    moveDown(event) {
        const row = this.row(event);
        row.nextElementSibling?.after(row);
        this.renumber();
        event.currentTarget.focus();
    }

    row(event) {
        return event.target.closest('[data-form-collection-row]');
    }

    renumber() {
        this.positionTargets.forEach((input, position) => { input.value = position; });
    }
}
