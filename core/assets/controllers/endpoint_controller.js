import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = [ 'domainWrapper', 'subdomainWrapper' ]

    initialize() {
        this.onChange = this.onChange.bind(this)
        this.onTurboLoad = this.updateVisibility.bind(this)
    }

    connect() {
        // initial visibility
        this.updateVisibility()
        // also ensure visibility after Turbo restores form values
        window.addEventListener('turbo:load', this.onTurboLoad)
        this.element.addEventListener('change', this.onChange)
        // run again next tick in case value was restored synchronously after connect
        setTimeout(() => this.updateVisibility(), 0)
    }

    disconnect() {
        window.removeEventListener('turbo:load', this.onTurboLoad)
        this.element.removeEventListener('change', this.onChange)
    }

    onChange(e) {
        if (!e.target) return
        if (e.target.name && e.target.name.endsWith('[enforce]')) {
            this.updateVisibility()
        }
    }

    updateVisibility() {
        const form = this.element
        const enforceField = form.querySelector('[name$="[enforce]"]')
        const domainWrapper = form.querySelector('[data-endpoint-target="domainWrapper"]')
        const subdomainWrapper = form.querySelector('[data-endpoint-target="subdomainWrapper"]')

        if (!domainWrapper || !subdomainWrapper) return

        if (!enforceField) {
            domainWrapper.classList.add('d-none')
            subdomainWrapper.classList.add('d-none')
            return
        }

        const value = (enforceField.value || '').toString()

        const showDomain = value === 'domain' || value === 'subdomain'
        const showSubdomain = value === 'subdomain'

        domainWrapper.classList.toggle('d-none', !showDomain)
        subdomainWrapper.classList.toggle('d-none', !showSubdomain)
    }
}
