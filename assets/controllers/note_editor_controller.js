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
        this.textTarget.value = this.field('text').value;
        this.levelTarget.value = this.field('level').value;
        this.modal ??= Modal.getOrCreateInstance(this.dialogTarget);
        this.dialogTarget.addEventListener('shown.bs.modal', () => this.textTarget.focus(), { once: true });
        this.dialogTarget.addEventListener('hidden.bs.modal', () => this.field('preview').focus(), { once: true });
        this.modal.show();
    }

    apply() {
        this.field('text').value = this.textTarget.value;
        this.field('level').value = this.levelTarget.value;

        const firstLine = this.textTarget.value.split('\n').map((line) => line.trim()).find((line) => line) ?? '';
        const preview = this.field('preview');
        preview.querySelector('.upgrade-note-text').textContent = firstLine || 'Add note';
        preview.classList.remove('upgrade-note-success', 'upgrade-note-warning', 'upgrade-note-danger');
        if (this.levelTarget.value) {
            preview.classList.add(`upgrade-note-${this.levelTarget.value}`);
        }
        preview.classList.toggle('upgrade-note-empty', !firstLine);

        this.modal.hide();
    }

    field(name) {
        return this.cell.querySelector(`[data-note-field="${name}"]`);
    }
}
