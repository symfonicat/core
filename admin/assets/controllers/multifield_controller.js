import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['collection', 'items', 'prototype']

    add(event) {
        event.preventDefault()
        event.stopImmediatePropagation()

        const collection = this.collectionTarget
        const index = Number(collection.dataset.multifieldIndex || 0)
        const markup = this.prototypeTarget.innerHTML.replace(/__name__/g, String(index))

        collection.dataset.multifieldIndex = String(index + 1)
        this.itemsTarget.insertAdjacentHTML('beforeend', markup)
    }

    remove(event) {
        event.preventDefault()
        event.stopImmediatePropagation()

        event.currentTarget.closest('[data-multifield-item]')?.remove()
    }
}
