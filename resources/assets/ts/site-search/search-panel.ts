import { EXPANDED } from '../shared/attributes.ts'

import type { Contract } from '../shared/contract.ts'

const INTENTS = ['pointerenter', 'focus'] as const

/**
 * The magnifier's disclosure, bound by the loader: it opens the panel and puts
 * the focus in the field at once, whether the client has arrived or not, and
 * calls for the client at the first sign the visitor is about to search.
 */
export class SearchPanel {
    #toggle: HTMLElement | null
    #panel: HTMLElement | null
    #input: HTMLElement | null
    #unavailable: HTMLElement | null
    #intent: () => void
    #intended = false

    constructor(contract: Contract, intent: () => void) {
        this.#toggle = SearchPanel.#element(contract, 'search-toggle')
        this.#panel = SearchPanel.#element(contract, 'search-panel')
        this.#input = SearchPanel.#element(contract, 'search-input')
        this.#unavailable = SearchPanel.#element(contract, 'search-unavailable')
        this.#intent = intent
    }

    static #element(contract: Contract, hook: string) {
        const element = contract.one(hook)

        return element instanceof HTMLElement ? element : null
    }

    start() {
        this.#toggle?.addEventListener('click', () => this.#toggled())

        for (const target of [this.#toggle, this.#input]) {
            INTENTS.forEach((intent) => target?.addEventListener(intent, () => this.#call(), { once: true }))
        }

        return this
    }

    /** The client never arrived: the panel says so rather than stay silent. */
    unavailable() {
        this.#reveal(this.#unavailable)
    }

    #toggled() {
        if (this.#panel?.hidden === false) {
            this.#close()
        } else {
            this.#open()
        }
    }

    #open() {
        this.#reveal(this.#panel)
        this.#toggle?.setAttribute(EXPANDED, 'true')
        this.#input?.focus()
        this.#call()
    }

    #close() {
        if (this.#panel !== null) {
            this.#panel.hidden = true
        }

        this.#toggle?.setAttribute(EXPANDED, 'false')
    }

    #reveal(element: HTMLElement | null) {
        if (element !== null) {
            element.hidden = false
        }
    }

    #call() {
        if (!this.#intended) {
            this.#intended = true
            this.#intent()
        }
    }
}
