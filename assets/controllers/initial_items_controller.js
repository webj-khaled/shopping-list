import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['add', 'row', 'rows'];
    static values = {
        index: Number,
        max: Number,
    };

    connect() {
        if (!this.hasIndexValue) {
            this.indexValue = this.rowTargets.length;
        }

        this.updateControls();
    }

    addRow() {
        if (this.hasMaxValue && this.rowTargets.length >= this.maxValue) {
            return;
        }

        const row = this.rowTargets[0].cloneNode(true);

        this.prepareRow(row, this.indexValue);
        this.rowsTarget.append(row);
        this.indexValue += 1;
        this.updateControls();

        row.querySelector('input[name$="[name]"]')?.focus();
    }

    removeRow(event) {
        const row = event.target.closest('[data-initial-items-target~="row"]');

        if (!row || this.rowTargets.length <= 1) {
            return;
        }

        row.remove();
        this.updateControls();
    }

    prepareRow(row, index) {
        row.querySelectorAll('input').forEach((input) => {
            input.name = input.name.replace(/items\[\d+\]/, `items[${index}]`);

            if (input.name.endsWith('[quantity]')) {
                input.value = '1';
                return;
            }

            input.value = '';
        });
    }

    updateControls() {
        const canRemove = this.rowTargets.length > 1;

        this.rowTargets.forEach((row) => {
            const removeButton = row.querySelector('.initial-item-row__remove');

            if (removeButton) {
                removeButton.hidden = !canRemove;
            }
        });

        if (this.hasAddTarget && this.hasMaxValue) {
            this.addTarget.disabled = this.rowTargets.length >= this.maxValue;
        }
    }
}
