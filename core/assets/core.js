import { Application } from '@hotwired/stimulus'
import { definitionsFromContext } from '@hotwired/stimulus-webpack-helpers'
import TurboController from '@symfony/ux-turbo'
import MercureTurboStreamController from '@symfony/ux-turbo/dist/turbo_stream_controller.js'

import '../../assets/bootstrap/js/index.esm.js'
import coreControllers from './controllers.json'

const context = require.context('./controllers', true, /\.[jt]sx?$/)

export const app = Application.start()

if (process.env.NODE_ENV === 'development') {
    app.debug = true
}

app.load(definitionsFromContext(context))

const knownUxControllers = {
    '@symfony/ux-turbo': {
        'turbo-core': TurboController,
        'mercure-turbo-stream': MercureTurboStreamController,
    },
}

const toStimulusIdentifier = (packageName, controllerName) => {
    return `${packageName.replace(/^@/, '').replace(/\//g, '--')}--${controllerName}`
}

Object.entries(coreControllers.controllers ?? {}).forEach(([packageName, controllers]) => {
    Object.entries(controllers ?? {}).forEach(([controllerName, config]) => {
        if (!config?.enabled) {
            return
        }

        const controller = knownUxControllers[packageName]?.[controllerName]
        if (!controller) {
            if (process.env.NODE_ENV === 'development') {
                console.warn(
                    `[core] Unsupported core UX controller "${packageName}/${controllerName}" is configured in controllers.json.`
                )
            }

            return
        }

        app.register(toStimulusIdentifier(packageName, controllerName), controller)
    })
})
