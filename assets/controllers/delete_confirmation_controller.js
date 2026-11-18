import { Controller } from '@hotwired/stimulus';

/*
 * Keeps a destructive form's submit button disabled until the user types the
 * exact confirmation string (organization-name/site-name or just site-name),
 * mirroring GitHub's "type the repo name to delete it" pattern. Reads the
 * expected string from a plain data-expected attribute (rather than
 * Stimulus's Values API) and trims the input before comparing, so accidental
 * leading/trailing whitespace from typing or pasting doesn't block a
 * correct match.
 */
export default class extends Controller {
    static targets = ['input', 'submit'];

    connect() {
        this.check();
    }

    check() {
        this.submitTarget.disabled = this.inputTarget.value.trim() !== this.element.dataset.expected;
    }
}
