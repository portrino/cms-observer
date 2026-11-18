import { Controller } from '@hotwired/stimulus';

const NEW = '__new';

/*
 * Adds "+ New organization…" to the organization select of the project form;
 * choosing it shows the name field for a new organization. The select itself
 * is then sent empty, the server creates the organization from the name.
 * Without JavaScript both fields simply show.
 */
export default class extends Controller {
    static targets = ['select', 'newField', 'newInput'];

    connect() {
        this.option = new Option('+ New organization…', NEW);
        this.selectTarget.add(this.option);
        // e.g. the form came back with a validation error
        if (this.newInputTarget.value !== '') {
            this.selectTarget.value = NEW;
        }
        this.toggle({ type: 'connect' });
    }

    disconnect() {
        this.option.remove();
    }

    toggle(event) {
        const creating = this.selectTarget.value === NEW;
        this.newFieldTarget.hidden = !creating;
        if (!creating) {
            this.newInputTarget.value = '';
        } else if (event.type !== 'connect') {
            this.newInputTarget.focus();
        }
    }

    submit() {
        if (this.selectTarget.value === NEW) {
            this.selectTarget.value = '';
        }
    }
}
