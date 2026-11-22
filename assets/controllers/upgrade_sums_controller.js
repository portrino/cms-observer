import { Controller } from '@hotwired/stimulus';

/*
 * Keeps the positions that stand for a package section (sum targets, data-sum-of and
 * data-bound) at the hours of that section, like the server sums them: a row with only
 * one effort counts it as both its minimum and maximum.
 */
export default class extends Controller {
    static targets = ['sum'];

    connect() {
        this.update();
    }

    update() {
        this.sumTargets.forEach((sum) => {
            let total = 0;
            this.element.querySelectorAll(`#upgrade-${sum.dataset.sumOf} tbody tr`).forEach((row) => {
                const value = (bound) => row.querySelector(`input[name$="[${bound}Hours]"]`)?.value ?? '';
                const own = value(sum.dataset.bound);
                const other = value(sum.dataset.bound === 'min' ? 'max' : 'min');
                total += parseFloat(own !== '' ? own : other) || 0;
            });
            sum.value = total.toFixed(2);
        });
    }
}
