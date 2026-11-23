import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

/*
 * Edits the notes of a calculation row in one shared dialog. A row keeps its
 * notes and their color in hidden fields and shows the first line as preview;
 * fields and preview are found by their data-note-field within the row's cell.
 */
export default class extends Controller {
    static targets = ['dialog', 'text', 'level'];

    open(event) {
        this.cell = event.currentTarget.closest('td');
        this.setText(this.field('text').value);
        this.levelTarget.value = this.field('level').value;
        this.modal ??= Modal.getOrCreateInstance(this.dialogTarget);
        this.dialogTarget.addEventListener('shown.bs.modal', () => {
            // the Markdown editor can only measure itself once the dialog is visible
            const editor = this.textTarget.easymde;
            editor ? (editor.codemirror.refresh(), editor.codemirror.focus()) : this.textTarget.focus();
        }, { once: true });
        this.dialogTarget.addEventListener('hidden.bs.modal', () => this.field('preview').focus(), { once: true });
        this.modal.show();
    }

    apply() {
        const text = this.getText();
        this.field('text').value = text;
        this.field('level').value = this.levelTarget.value;

        // first line without Markdown marks, like the server renders the preview
        const firstLine = (text.split('\n').map((line) => line.trim()).find((line) => line) ?? '')
            .replace(/^([-*+]|\d+\.|#+|>)\s+/, '').replace(/[*_`~]/g, '');
        const preview = this.field('preview');
        preview.querySelector('.upgrade-note-text').textContent = firstLine || preview.dataset.emptyText;
        preview.classList.remove('upgrade-note-success', 'upgrade-note-warning', 'upgrade-note-danger');
        if (this.levelTarget.value) {
            preview.classList.add(`upgrade-note-${this.levelTarget.value}`);
        }
        preview.classList.toggle('upgrade-note-empty', !firstLine);

        this.modal.hide();
    }

    getText() {
        return this.textTarget.easymde?.value() ?? this.textTarget.value;
    }

    setText(text) {
        this.textTarget.easymde ? this.textTarget.easymde.value(text) : (this.textTarget.value = text);
    }

    field(name) {
        return this.cell.querySelector(`[data-note-field="${name}"]`);
    }
}
