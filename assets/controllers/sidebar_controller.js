import { Controller } from '@hotwired/stimulus';

const STORAGE_KEY = 'cms-observer:sidebar-collapsed';

/*
 * Persists the sidebar's collapsed/expanded state in localStorage, since
 * Turbo-driven navigation swaps the DOM on every page load - the whole
 * point of a collapsible sidebar is that it stays how you left it.
 */
export default class extends Controller {
    connect() {
        if (localStorage.getItem(STORAGE_KEY) === '1') {
            this.element.classList.add('sidebar-collapsed');
        }
    }

    toggle() {
        // phones: an overlay that opens per page, nothing to persist
        if (window.matchMedia('(max-width: 767.98px)').matches) {
            this.element.classList.toggle('sidebar-open');
            return;
        }

        this.element.classList.toggle('sidebar-collapsed');
        localStorage.setItem(STORAGE_KEY, this.element.classList.contains('sidebar-collapsed') ? '1' : '0');
    }

    close() {
        this.element.classList.remove('sidebar-open');
    }
}
