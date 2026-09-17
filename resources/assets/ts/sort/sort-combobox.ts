import { Contract } from '../shared/contract.ts'
import { ListboxKeys } from './listbox-keys.ts'
import { TypeAhead } from './type-ahead.ts'

import type { ListingState } from '../listing/listing-state.ts'
import type { ListboxMove } from './listbox-keys.ts'

const EXPANDED = 'aria-expanded'
const SELECTED = 'aria-selected'
const ACTIVE = 'aria-activedescendant'

/** What the theme styles to show where the keyboard is, before anything is chosen. */
const ACTIVE_OPTION = 'data-active'

const DEFAULT_VALUE = ''

type Choose = (sort: string | null) => void

/** The sort control, as the ARIA select-only combobox: focus never leaves the button. */
export class SortCombobox {
    #contract: Contract
    #choose: Choose
    #root: HTMLElement | null
    #trigger: HTMLElement | null
    #list: HTMLElement | null
    #options: HTMLElement[]

    /** Where the keyboard is, which is not yet what the visitor picked. */
    #active = 0
    #typeAhead = new TypeAhead()

    constructor(contract: Contract, choose: Choose) {
        this.#contract = contract
        this.#choose = choose
        this.#root = this.#element('sort')
        this.#trigger = this.#element('sort-trigger')
        this.#list = this.#element('sort-list')
        this.#options = contract.all('sort-option').filter((node) => node instanceof HTMLElement)

        // `aria-activedescendant` points at an id; an overridden view may carry none.
        this.#options.forEach((option, rank) => {
            option.id ||= `${this.#trigger?.id ?? 'meilifacets-sort'}-option-${rank}`
        })
    }

    /** A listing with a single order renders no control: there is nothing to wire. */
    start() {
        const trigger = this.#trigger
        const list = this.#list

        if (!trigger || !list) {
            return this
        }

        trigger.addEventListener('click', () => this.#toggle())
        trigger.addEventListener('keydown', (event) => this.#pressed(event))
        list.addEventListener('click', (event) => this.#clicked(event))
        trigger.ownerDocument.addEventListener('click', (event) => this.#clickedAway(event))

        return this
    }

    show(state: ListingState) {
        const current = state.sort ?? DEFAULT_VALUE

        for (const option of this.#options) {
            option.setAttribute(SELECTED, String(this.#valueOf(option) === current))
        }

        const selected = this.#options.find((option) => this.#valueOf(option) === current)

        if (this.#trigger && selected) {
            this.#trigger.textContent = selected.textContent
        }
    }

    get #isOpen() {
        return this.#list !== null && !this.#list.hidden
    }

    #toggle() {
        if (this.#isOpen) {
            this.#close()
        } else {
            this.#open(this.#selectedIndex())
        }
    }

    #open(index: number) {
        if (this.#list && this.#trigger) {
            this.#list.hidden = false
            this.#trigger.setAttribute(EXPANDED, 'true')
            this.#activate(index)
        }
    }

    #close() {
        if (this.#list && this.#trigger) {
            this.#list.hidden = true
            this.#trigger.setAttribute(EXPANDED, 'false')
            this.#trigger.removeAttribute(ACTIVE)
            this.#options.forEach((option) => option.removeAttribute(ACTIVE_OPTION))
        }
    }

    #activate(index: number) {
        this.#active = this.#within(index)

        const option = this.#options[this.#active]

        if (!option) {
            return
        }

        this.#trigger?.setAttribute(ACTIVE, option.id)
        this.#options.forEach((node, rank) => node.toggleAttribute(ACTIVE_OPTION, rank === this.#active))
        option.scrollIntoView?.({ block: 'nearest' })
    }

    #pick(index: number) {
        const value = this.#valueOf(this.#options[this.#within(index)])

        this.#close()
        this.#trigger?.focus()
        this.#choose(value === DEFAULT_VALUE ? null : value)
    }

    #pressed(event: KeyboardEvent) {
        const position = { active: this.#active, selected: this.#selectedIndex(), last: this.#options.length - 1 }
        const move = this.#isOpen
            ? ListboxKeys.whileOpen(event.key, position)
            : ListboxKeys.whileClosed(event.key, position)
        const handled = move === null ? this.#typedAhead(event) : this.#perform(move)

        if (handled) {
            event.preventDefault()
        }
    }

    #perform({ action, index, passesThrough = false }: ListboxMove) {
        const actions = {
            open: () => this.#open(index),
            activate: () => this.#activate(index),
            pick: () => this.#pick(index),
            close: () => this.#close(),
        }

        actions[action]()

        return !passesThrough
    }

    #typedAhead(event: KeyboardEvent) {
        if (!TypeAhead.accepts(event)) {
            return false
        }

        const found = this.#typeAhead.find(event.key, this.#options.map((option) => this.#labelOf(option)), this.#active)

        if (found !== undefined) {
            this.#perform({ action: this.#isOpen ? 'activate' : 'open', index: found })
        }

        return true
    }

    #within(index: number) {
        return Math.min(Math.max(index, 0), this.#options.length - 1)
    }

    #selectedIndex() {
        return Math.max(this.#options.findIndex((option) => option.getAttribute(SELECTED) === 'true'), 0)
    }

    #valueOf(option: HTMLElement | undefined) {
        return option?.dataset.value ?? DEFAULT_VALUE
    }

    #labelOf(option: HTMLElement | undefined) {
        return (option?.textContent ?? '').trim().toLowerCase()
    }

    #clicked(event: Event) {
        const option = event.target instanceof Element
            ? event.target.closest(Contract.selector('sort-option'))
            : null
        const index = option instanceof HTMLElement ? this.#options.indexOf(option) : -1

        if (index !== -1) {
            this.#pick(index)
        }
    }

    #clickedAway(event: Event) {
        if (this.#isOpen && event.target instanceof Node && this.#root?.contains(event.target) === false) {
            this.#close()
        }
    }

    #element(hook: string): HTMLElement | null {
        const node = this.#contract.one(hook)

        return node instanceof HTMLElement ? node : null
    }
}
