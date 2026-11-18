import { Controller } from '@hotwired/stimulus';
import { Tooltip } from 'bootstrap';

/*
 * Bootstrap tooltips aren't self-initializing - each [data-bs-toggle=tooltip]
 * element needs `new bootstrap.Tooltip(el)` called on it once. This controller
 * does that for whichever element it's attached to, and disposes it again on
 * disconnect (e.g. a Turbo page swap) to avoid leaking the tooltip instance.
 */
export default class extends Controller {
    connect() {
        this.tooltip = new Tooltip(this.element);
    }

    disconnect() {
        this.tooltip.dispose();
    }
}
