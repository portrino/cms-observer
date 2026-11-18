import { Controller } from '@hotwired/stimulus';

/*
 * Submits a filter form as soon as a filter changes, so it needs no submit
 * button. Text input waits for a short pause in typing, so not every keystroke
 * sends a request. With data-turbo-frame on the form, Turbo then only swaps
 * the results frame and the input keeps its focus.
 */
export default class extends Controller {
    static values = { delay: { type: Number, default: 300 } };

    submit() {
        clearTimeout(this.timeout);
        this.element.requestSubmit();
    }

    submitAfterTyping() {
        clearTimeout(this.timeout);
        this.timeout = setTimeout(() => this.element.requestSubmit(), this.delayValue);
    }

    disconnect() {
        clearTimeout(this.timeout);
    }
}
