import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['type', 'domainRow', 'projectRow']

    connect() {
        this.update()
    }

    update() {
        if (!this.hasTypeTarget) {
            return
        }

        const showDomain = this.typeTarget.value === 'domain'

        this.toggleRow(this.domainRowTarget, showDomain)
        this.toggleRow(this.projectRowTarget, !showDomain)
    }

    toggleRow(row, visible) {
        row.hidden = !visible
        row.classList.toggle('d-none', !visible)

        row.querySelectorAll('input, select, textarea').forEach((element) => {
            element.disabled = !visible
        })
    }
}
