import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['panel', 'toggle'];
    static values = { open: Boolean };

    connect() {
        if (this.openValue) {
            this.open();
            return;
        }

        this.close();
    }

    toggle() {
        if (this.panelTarget.classList.contains('is-collapsed')) {
            this.open();
            return;
        }

        this.close();
    }

    open() {
        this.panelTarget.classList.remove('is-collapsed');
        this.panelTarget.setAttribute('aria-hidden', 'false');
        this.toggleTarget.setAttribute('aria-expanded', 'true');
    }

    close() {
        this.panelTarget.classList.add('is-collapsed');
        this.panelTarget.setAttribute('aria-hidden', 'true');
        this.toggleTarget.setAttribute('aria-expanded', 'false');
    }
}
