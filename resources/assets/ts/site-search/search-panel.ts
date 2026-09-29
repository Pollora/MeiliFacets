import { EXPANDED, INSTANT } from '../shared/attributes.ts'
import { InputSource } from '../shared/input-source.ts'
import { LightDismiss } from '../shared/light-dismiss.ts'
import { PanelRoom } from './panel-room.ts'

import type { Contract } from '../shared/contract.ts'

const INTENTS = ['pointerenter', 'focus'] as const

/**
 * The magnifier's disclosure, bound by the loader: it opens the panel and puts
 * the focus in the field at once, whether the client has arrived or not, and
 * calls for the client at the first sign the visitor is about to search.
 */
export class SearchPanel {
    #contract: Contract
    #toggle: HTMLElement | null
    #panel: HTMLElement | null
    #input: HTMLElement | null
    #room: PanelRoom | null
    #intent: () => void
    #intended = false

    constructor(contract: Contract, intent: () => void) {
        this.#contract = contract
        this.#toggle = this.#element('search-toggle')
        this.#panel = this.#element('search-panel')
        this.#input = this.#element('search-input')
        this.#room = this.#panel === null ? null : new PanelRoom(this.#panel)
        this.#intent = intent
    }

    start() {
        this.#toggle?.addEventListener('click', (click) => this.#toggled(click))

        for (const target of [this.#toggle, this.#input]) {
            INTENTS.forEach((intent) => target?.addEventListener(intent, () => this.#call(), { once: true }))
        }

        this.#dismissal().start()
        this.#contract.window.addEventListener('resize', () => this.#remeasure())

        return this
    }

    /** The client never arrived: the panel says so rather than stay silent. */
    unavailable() {
        const unavailable = this.#element('search-unavailable')

        if (unavailable !== null) {
            unavailable.hidden = false
        }
    }

    #dismissal() {
        return new LightDismiss(this.#contract.root, {
            open: () => (this.#toggle !== null && this.#isOpen() ? [this.#toggle] : []),
            panelOf: () => this.#panel,
            closeInstantly: () => this.#instantly(() => this.#close()),
            close: () => this.#close(),
        })
    }

    #toggled(click: MouseEvent) {
        const change = () => (this.#isOpen() ? this.#close() : this.#open())

        if (InputSource.isKeyboard(click)) {
            this.#instantly(change)
        } else {
            change()
        }
    }

    /** `getAnimations()` flushes the style while the stylesheet cuts the transitions: none starts once the attribute goes. */
    #instantly(change: () => void) {
        const root = this.#contract.root

        root.setAttribute(INSTANT, '')
        change()
        root.getAnimations()
        root.removeAttribute(INSTANT)
    }

    #open() {
        if (this.#panel === null) {
            return
        }

        this.#panel.hidden = false
        this.#toggle?.setAttribute(EXPANDED, 'true')
        this.#room?.measure()
        this.#input?.focus()
        this.#call()
    }

    #close() {
        if (this.#panel !== null) {
            this.#panel.hidden = true
        }

        this.#toggle?.setAttribute(EXPANDED, 'false')
        this.#room?.release()
    }

    #remeasure() {
        if (this.#isOpen()) {
            this.#room?.measure()
        }
    }

    #isOpen() {
        return this.#panel?.hidden === false
    }

    #call() {
        if (!this.#intended) {
            this.#intended = true
            this.#intent()
        }
    }

    #element(hook: string) {
        const element = this.#contract.one(hook)

        return element instanceof HTMLElement ? element : null
    }
}
