import { ACTIVE_DESCENDANT, ACTIVE_OPTION, CONTROLS, EXPANDED } from '../shared/attributes.ts'

import type { Contract } from '../shared/contract.ts'

const NONE = -1
const OPTION_ID_PREFIX = 'meilifacets-search'

export interface ComboboxMove {
    action: 'activate' | 'follow' | 'hold' | 'release'
    index: number
}

export interface ComboboxPosition {
    active: number
    last: number
}

/**
 * The keyboard of an editable combobox whose options are links: the focus never
 * leaves the field, `aria-activedescendant` says which option the arrows reached.
 */
export class ComboboxKeys {
    #contract: Contract
    #input: HTMLInputElement
    #options: HTMLElement[] = []
    #active = NONE
    /** Ids are issued once per node, never by rank: a card kept from one answer to the next keeps its own. */
    #issued = 0

    constructor(contract: Contract, input: HTMLInputElement) {
        this.#contract = contract
        this.#input = input
    }

    static moveFor(key: string, { active, last }: ComboboxPosition): ComboboxMove | null {
        const editing: ComboboxMove = { action: 'release', index: NONE }
        const moves: Partial<Record<string, ComboboxMove>> = {
            ArrowDown: { action: 'activate', index: Math.min(active + 1, last) },
            ArrowUp: { action: 'activate', index: active === NONE ? last : Math.max(active - 1, 0) },
            Enter: active === NONE ? { action: 'hold', index: NONE } : { action: 'follow', index: active },
            Home: editing,
            End: editing,
            ArrowLeft: editing,
            ArrowRight: editing,
        }

        return last === NONE && key !== 'Enter' ? null : (moves[key] ?? null)
    }

    start() {
        this.#input.addEventListener('keydown', (event) => this.#pressed(event))

        return this
    }

    control(listboxes: Element[]) {
        const ids = listboxes.map((listbox) => listbox.id).filter((id) => id !== '')

        this.#input.setAttribute(CONTROLS, ids.join(' '))
    }

    /** The option the arrows reached stays reached while it is still offered, wherever it now stands. */
    offer(options: HTMLElement[]) {
        const reached = this.#options[this.#active]
        const stillOffered = reached !== undefined && options.includes(reached)

        if (!stillOffered) {
            this.#activate(NONE)
        }

        this.#options = options
        this.#options.forEach((option) => {
            option.id ||= `${this.#input.id || OPTION_ID_PREFIX}-option-${this.#issued++}`
        })
        this.#active = stillOffered ? options.indexOf(reached) : NONE
        this.#input.setAttribute(EXPANDED, String(options.length > 0))
    }

    // A key that confirms an input method's composition belongs to it.
    #pressed(event: KeyboardEvent) {
        const move = event.isComposing
            ? null
            : ComboboxKeys.moveFor(event.key, { active: this.#active, last: this.#options.length - 1 })

        if (move === null) {
            return
        }

        if (move.action !== 'release') {
            event.preventDefault()
        }

        this.#perform(move)
    }

    #perform({ action, index }: ComboboxMove) {
        const actions = {
            activate: () => this.#activate(index),
            follow: () => this.#follow(index),
            hold: () => undefined,
            release: () => this.#activate(NONE),
        }

        actions[action]()
    }

    #activate(index: number) {
        this.#active = index
        this.#options.forEach((option, rank) => option.toggleAttribute(ACTIVE_OPTION, rank === index))

        const option = this.#options[index]

        if (option === undefined) {
            this.#input.removeAttribute(ACTIVE_DESCENDANT)

            return
        }

        this.#input.setAttribute(ACTIVE_DESCENDANT, option.id)
        option.scrollIntoView?.({ block: 'nearest' })
    }

    #follow(index: number) {
        const option = this.#options[index]

        if (option === undefined) {
            return
        }

        const link = option instanceof HTMLAnchorElement ? option : this.#contract.one('url', option)

        if (link instanceof HTMLElement) {
            link.click()
        }
    }
}
