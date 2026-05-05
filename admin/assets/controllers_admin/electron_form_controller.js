import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['type', 'domainRow', 'projectRow', 'applicationRow']

    connect() {
        this.update()
    }

    update() {
        if (!this.hasTypeTarget) {
            return
        }

        const type = this.typeTarget.value

        this.toggleRow(this.domainRowTarget, type === 'domain' || type === 'project')
        this.toggleRow(this.projectRowTarget, type === 'project')
        this.toggleRow(this.applicationRowTarget, type === 'application')
    }

    toggleRow(row, visible) {
        row.hidden = !visible
        row.classList.toggle('d-none', !visible)

        row.querySelectorAll('input, select, textarea').forEach((element) => {
            element.disabled = !visible
        })
    }
}
