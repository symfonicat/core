import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['toggle', 'routeNameRow']

    connect() {
        this.update()
    }

    update() {
        if (!this.hasToggleTarget || !this.hasRouteNameRowTarget) {
            return
        }

        const visible = this.toggleTarget.checked

        this.routeNameRowTarget.hidden = !visible
        this.routeNameRowTarget.classList.toggle('d-none', !visible)

        this.routeNameRowTarget.querySelectorAll('input, select, textarea').forEach((element) => {
            element.disabled = !visible
        })
    }
}
