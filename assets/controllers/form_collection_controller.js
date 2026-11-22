import { Controller } from '@hotwired/stimulus';

/*
 * Adds and removes rows of a Symfony CollectionType. New rows come from the
 * prototype in a <template>, with "__name__" replaced by a fresh index.
 */
export default class extends Controller {
    static targets = ['rows', 'prototype'];
    static values = { index: Number };

    add() {
        this.rowsTarget.insertAdjacentHTML('beforeend', this.prototypeTarget.innerHTML.replace(/__name__/g, this.indexValue++));
        this.rowsTarget.lastElementChild.querySelector('input:not([type=hidden])')?.focus();
    }

    remove(event) {
        event.target.closest('[data-form-collection-row]').remove();
    }
}
