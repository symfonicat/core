import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['collection', 'items', 'prototype']

    connect() {
        this.syncAll()
    }

    add(event) {
        event.preventDefault()

        const index = Number(this.collectionTarget.dataset.fileUploadIndex || 0)
        const markup = this.prototypeTarget.innerHTML.replace(/__name__/g, String(index))

        this.collectionTarget.dataset.fileUploadIndex = String(index + 1)
        this.itemsTarget.insertAdjacentHTML('beforeend', markup)

        const item = this.itemsTarget.lastElementChild
        if (item instanceof HTMLElement) {
            this.syncItem(item)
        }
    }

    update(event) {
        const item = event.target.closest('[data-file-upload-item]')
        if (item instanceof HTMLElement) {
            this.syncItem(item)
        }
    }

    syncAll() {
        this.element.querySelectorAll('[data-file-upload-item]').forEach((item) => {
            if (item instanceof HTMLElement) {
                this.syncItem(item)
            }
        })
    }

    syncItem(item) {
        const type = item.querySelector('[data-file-upload-target~="type"]')
        const domainRow = item.querySelector('[data-file-upload-target~="domainRow"]')
        const projectRow = item.querySelector('[data-file-upload-target~="projectRow"]')

        if (!(type instanceof HTMLSelectElement) || !(domainRow instanceof HTMLElement) || !(projectRow instanceof HTMLElement)) {
            return
        }

        this.toggleRow(domainRow, type.value === 'domain')
        this.toggleRow(projectRow, type.value === 'project')
    }

    toggleRow(row, visible) {
        row.hidden = !visible
        row.classList.toggle('d-none', !visible)

        row.querySelectorAll('input, select, textarea').forEach((element) => {
            element.disabled = !visible
        })
    }
}
