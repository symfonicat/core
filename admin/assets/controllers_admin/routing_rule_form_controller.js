import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = [
        'type',
        'applicationType',
        'argumentsRow',
        'argumentsCollection',
        'argumentsItems',
        'argumentsPrototype',
        'redirectType',
        'routeType',
        'redirectTarget',
        'applicationTypeRow',
        'redirectTypeRow',
        'routeTypeRow',
        'redirectTargetRow',
        'domainRow',
        'projectRow',
        'applicationRow',
        'redirectCard',
        'redirectDomainRow',
        'redirectProjectRow',
        'routeCard',
        'routeRow',
    ]

    connect() {
        this.update()
    }

    update() {
        if (!this.hasTypeTarget) {
            return
        }

        const type = this.typeTarget.value
        const applicationType = this.hasApplicationTypeTarget ? this.applicationTypeTarget.value : ''
        const redirectType = this.hasRedirectTypeTarget ? this.redirectTypeTarget.value : ''
        const routeType = this.hasRouteTypeTarget ? this.routeTypeTarget.value : ''
        const redirectTarget = this.hasRedirectTargetTarget ? this.redirectTargetTarget.value : ''

        const isDomain = type === 'domain'
        const isProject = type === 'project'
        const isApplication = type === 'application'
        const isRedirect = type === 'redirect'
        const isRoute = type === 'route'
        const isApplicationArguments = isApplication && applicationType === 'arguments'
        const isApplicationRoute = isApplication && applicationType === 'route'

        this.toggleRow(this.applicationTypeRowTarget, isApplication)
        this.toggleRow(this.argumentsRowTarget, isDomain || isProject || isApplicationArguments)
        this.toggleRow(this.redirectCardTarget, isRedirect)
        this.toggleRow(this.redirectTypeRowTarget, isRedirect)
        this.toggleRow(this.redirectTargetRowTarget, isRedirect)

        this.toggleRow(this.routeCardTarget, isRoute || isApplicationRoute)
        this.toggleRow(this.routeTypeRowTarget, isRoute)

        this.toggleRow(
            this.domainRowTarget,
            isDomain || (isRedirect && redirectType === 'domain') || (isRoute && routeType === 'domain')
        )
        this.toggleRow(
            this.projectRowTarget,
            isProject || (isRedirect && redirectType === 'project') || (isRoute && routeType === 'project')
        )
        this.toggleRow(this.applicationRowTarget, isApplication)
        this.toggleRow(
            this.redirectDomainRowTarget,
            isRedirect && (redirectTarget === 'domain' || redirectTarget === 'domain_project')
        )
        this.toggleRow(
            this.redirectProjectRowTarget,
            isRedirect && (redirectTarget === 'project' || redirectTarget === 'domain_project')
        )
        this.toggleRow(this.routeRowTarget, isRoute || isApplicationRoute)
    }

    addArgument(event) {
        event.preventDefault()
        event.stopImmediatePropagation()

        const collection = this.argumentsCollectionTarget
        const index = Number(collection.dataset.routingRuleFormIndex || 0)
        const markup = this.argumentsPrototypeTarget.innerHTML.replace(/__name__/g, String(index))

        collection.dataset.routingRuleFormIndex = String(index + 1)
        this.argumentsItemsTarget.insertAdjacentHTML('beforeend', markup)
    }

    removeArgument(event) {
        event.preventDefault()
        event.stopImmediatePropagation()

        event.currentTarget.closest('[data-routing-rule-form-argument-item]')?.remove()
    }

    toggleRow(row, visible) {
        row.hidden = !visible
        row.classList.toggle('d-none', !visible)

        row.querySelectorAll('input, select, textarea').forEach((element) => {
            element.disabled = !visible
        })
    }
}
