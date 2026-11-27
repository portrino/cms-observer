import { Controller } from '@hotwired/stimulus';

/*
 * The switch of a project page section (Core, Packages) that shows the versions which need a
 * newer core (.row-newer-core): off by default, remembered per section in this browser.
 */
export default class extends Controller {
    static targets = ['switch'];
    static values = { key: String };

    connect() {
        this.switchTarget.checked = this.read();
        this.apply();
    }

    toggle() {
        try {
            localStorage.setItem(this.storageKey, this.switchTarget.checked ? '1' : '0');
        } catch {
            // e.g. storage blocked: the switch still works on this page
        }
        this.apply();
    }

    apply() {
        this.element.classList.toggle('show-newer-core', this.switchTarget.checked);
    }

    read() {
        try {
            return localStorage.getItem(this.storageKey) === '1';
        } catch {
            return false; // storage blocked: off, as by default
        }
    }

    get storageKey() {
        return `cms-observer:newer-core:${this.keyValue}`;
    }
}
