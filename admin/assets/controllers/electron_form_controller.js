import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['type', 'domainRow', 'subdomainRow', 'applicationRow']

    connect() {
        this.update()
    }

    update() {
        if (!this.hasTypeTarget) {
            return
        }

        const type = this.typeTarget.value

        this.toggleRow(this.domainRowTarget, type === 'domain' || type === 'subdomain')
        this.toggleRow(this.subdomainRowTarget, type === 'subdomain')
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
