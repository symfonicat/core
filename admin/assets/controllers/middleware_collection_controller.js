import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['items', 'prototype']
    static values = {
        index: Number,
    }

    add(event) {
        event.preventDefault()

        const markup = this.prototypeTarget.innerHTML.replace(/__name__/g, String(this.indexValue))
        this.indexValue += 1

        this.itemsTarget.insertAdjacentHTML('beforeend', markup)
    }

    remove(event) {
        event.preventDefault()

        event.currentTarget.closest('[data-middleware-collection-item]')?.remove()
    }
}
