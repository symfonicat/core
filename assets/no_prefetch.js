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
