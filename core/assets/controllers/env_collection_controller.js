import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['items', 'prototype']
    static values = {
        index: Number,
    }

    connect() {
        this.syncAll()
    }

    add(event) {
        event.preventDefault()

        const markup = this.prototypeTarget.innerHTML.replace(/__name__/g, String(this.indexValue))
        this.indexValue += 1

        this.itemsTarget.insertAdjacentHTML('beforeend', markup)

        const item = this.itemsTarget.lastElementChild
        if (item instanceof HTMLElement) {
            this.syncItem(item)
        }
    }

    remove(event) {
        event.preventDefault()

        event.currentTarget.closest('[data-env-collection-item]')?.remove()
    }

    syncFromParent(event) {
        const parentSelect = event.currentTarget
        const item = parentSelect.closest('[data-env-collection-item]')
        const envSelect = item?.querySelector('[data-env-select]')

        this.syncEnvOptions(parentSelect, envSelect)
    }

    syncFromEnv(event) {
        const envSelect = event.currentTarget
        const item = envSelect.closest('[data-env-collection-item]')
        const parentSelect = item?.querySelector('[data-env-parent-select]')

        this.syncParentFromEnv(parentSelect, envSelect)
        this.syncEnvOptions(parentSelect, envSelect)
    }

    syncAll() {
        this.element.querySelectorAll('[data-env-collection-item]').forEach((item) => {
            this.syncItem(item)
        })
    }

    syncItem(item) {
        if (!(item instanceof HTMLElement)) {
            return
        }

        const parentSelect = item.querySelector('[data-env-parent-select]')
        const envSelect = item.querySelector('[data-env-select]')

        if (!(parentSelect instanceof HTMLSelectElement) || !(envSelect instanceof HTMLSelectElement)) {
            return
        }

        this.syncParentFromEnv(parentSelect, envSelect)
        this.syncEnvOptions(parentSelect, envSelect)
    }

    syncEnvOptions(parentSelect, envSelect) {
        if (!(parentSelect instanceof HTMLSelectElement) || !(envSelect instanceof HTMLSelectElement)) {
            return
        }

        const selectedParent = parentSelect.value

        for (const option of envSelect.options) {
            if (option.value === '') {
                option.hidden = false
                option.disabled = false
                continue
            }

            const optionParent = option.dataset.envParent || ''
            const visible = selectedParent !== '' && optionParent === selectedParent

            option.hidden = !visible
            option.disabled = !visible
        }

        const selectedOption = envSelect.options[envSelect.selectedIndex] || null
        if (selectedOption && selectedOption.value !== '' && selectedOption.disabled) {
            envSelect.value = ''
        }
    }

    syncParentFromEnv(parentSelect, envSelect) {
        if (!(parentSelect instanceof HTMLSelectElement) || !(envSelect instanceof HTMLSelectElement)) {
            return
        }

        const selectedOption = envSelect.options[envSelect.selectedIndex] || null
        if (!selectedOption || selectedOption.value === '') {
            return
        }

        const optionParent = selectedOption.dataset.envParent || ''
        if (optionParent !== '') {
            parentSelect.value = optionParent
        }
    }
}
