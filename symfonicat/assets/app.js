/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

import '@fortawesome/fontawesome-free/css/all.css'
import './style.scss'
import './bootstrap/index.esm.js'

import './module'
import './stimulus'
import './mercure'

const navigateWithoutPrefetch = (element) => {
    const url = element?.dataset?.noPrefetchUrl
    if (!url) {
        return
    }

    window.location.assign(url)
}

document.addEventListener('click', (event) => {
    const element = event.target.closest('[data-no-prefetch]')
    if (!element) {
        return
    }

    event.preventDefault()
    navigateWithoutPrefetch(element)
})

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' && event.key !== ' ') {
        return
    }

    const element = event.target.closest('[data-no-prefetch]')
    if (!element) {
        return
    }

    event.preventDefault()
    navigateWithoutPrefetch(element)
})
