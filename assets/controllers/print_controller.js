import { Controller } from '@hotwired/stimulus';

/* Opens the browser's print dialog, e.g. to save a page as PDF */
export default class extends Controller {
    print() {
        window.print();
    }
}
