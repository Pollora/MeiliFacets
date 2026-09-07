import { Contract } from './contract.js'

/**
 * @import { ListingState } from './listing-state.js'
 */

const EXPANDED = 'aria-expanded'
const SELECTED = 'aria-selected'
const ACTIVE = 'aria-activedescendant'

/** What the theme styles to show where the keyboard is, before anything is chosen. */
const ACTIVE_OPTION = 'data-active'

/** Letters typed further apart start a new search rather than extending the last. */
const TYPING_PAUSE = 500

const DEFAULT_VALUE = ''

/** The sort control, as the ARIA select-only combobox: focus never leaves the button. */
export class SortCombobox {
    /** @type {Contract} */
    #contract

    /** @type {(sort: string | null) => void} */
    #choose

    /** @type {HTMLElement | null} */
    #root

    /** @type {HTMLElement | null} */
    #trigger

    /** @type {HTMLElement | null} */
    #list

    /** @type {HTMLElement[]} */
    #options

    /** Where the keyboard is, which is not yet what the visitor picked. */
    #active = 0

    #typed = DEFAULT_VALUE

    #typedAt = 0

    /**
     * @param {Contract} contract
     * @param {(sort: string | null) => void} choose
     */
    constructor(contract, choose) {
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

    /**
     * @param {ListingState} state
     */
    show(state) {
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
        this.#isOpen ? this.#close() : this.#open(this.#selectedIndex())
    }

    /**
     * @param {number} index
     */
    #open(index) {
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

    /**
     * @param {number} index
     */
    #activate(index) {
        this.#active = this.#within(index)

        const option = this.#options[this.#active]

        if (!option) {
            return
        }

        this.#trigger?.setAttribute(ACTIVE, option.id)
        this.#options.forEach((node, rank) => node.toggleAttribute(ACTIVE_OPTION, rank === this.#active))
        option.scrollIntoView?.({ block: 'nearest' })
    }

    /**
     * @param {number} index
     */
    #pick(index) {
        const value = this.#valueOf(this.#options[this.#within(index)])

        this.#close()
        this.#trigger?.focus()
        this.#choose(value === DEFAULT_VALUE ? null : value)
    }

    /**
     * @param {KeyboardEvent} event
     */
    #pressed(event) {
        const handled = this.#isOpen ? this.#pressedOpen(event) : this.#pressedClosed(event)

        if (handled) {
            event.preventDefault()
        }
    }

    /**
     * @param {KeyboardEvent} event
     */
    #pressedClosed(event) {
        switch (event.key) {
            case 'ArrowDown':
            case 'ArrowUp':
            case 'Enter':
            case ' ':
                this.#open(this.#selectedIndex())

                return true
            case 'Home':
                this.#open(0)

                return true
            case 'End':
                this.#open(this.#options.length - 1)

                return true
            default:
                return this.#typeAhead(event)
        }
    }

    /**
     * Tab is the one key that must go through: it picks, then leaves.
     *
     * @param {KeyboardEvent} event
     */
    #pressedOpen(event) {
        switch (event.key) {
            case 'ArrowDown':
                this.#activate(this.#active + 1)

                return true
            case 'ArrowUp':
                this.#activate(this.#active - 1)

                return true
            case 'Home':
                this.#activate(0)

                return true
            case 'End':
                this.#activate(this.#options.length - 1)

                return true
            case 'Enter':
            case ' ':
                this.#pick(this.#active)

                return true
            case 'Escape':
                this.#close()

                return true
            case 'Tab':
                this.#pick(this.#active)

                return false
            default:
                return this.#typeAhead(event)
        }
    }

    /**
     * @param {KeyboardEvent} event
     */
    #typeAhead(event) {
        if (event.key.length !== 1 || event.ctrlKey || event.metaKey || event.altKey) {
            return false
        }

        const now = Date.now()

        this.#typed = now - this.#typedAt > TYPING_PAUSE ? event.key : this.#typed + event.key
        this.#typedAt = now

        // One letter walks the matches; a longer run stays on the name being spelled.
        const found = this.#search(this.#typed.length === 1 ? this.#active + 1 : this.#active)

        if (found !== undefined) {
            this.#isOpen ? this.#activate(found) : this.#open(found)
        }

        return true
    }

    /**
     * @param {number} from
     * @returns {number | undefined}
     */
    #search(from) {
        const typed = this.#typed.toLowerCase()

        return this.#options
            .map((_, rank) => (from + rank) % this.#options.length)
            .find((rank) => this.#labelOf(this.#options[rank]).startsWith(typed))
    }

    /**
     * @param {number} index
     */
    #within(index) {
        return Math.min(Math.max(index, 0), this.#options.length - 1)
    }

    #selectedIndex() {
        return Math.max(this.#options.findIndex((option) => option.getAttribute(SELECTED) === 'true'), 0)
    }

    /**
     * @param {HTMLElement | undefined} option
     */
    #valueOf(option) {
        return option?.dataset.value ?? DEFAULT_VALUE
    }

    /**
     * @param {HTMLElement | undefined} option
     */
    #labelOf(option) {
        return (option?.textContent ?? '').trim().toLowerCase()
    }

    /**
     * @param {Event} event
     */
    #clicked(event) {
        const option = event.target instanceof Element
            ? event.target.closest(Contract.selector('sort-option'))
            : null
        const index = option instanceof HTMLElement ? this.#options.indexOf(option) : -1

        if (index !== -1) {
            this.#pick(index)
        }
    }

    /**
     * @param {Event} event
     */
    #clickedAway(event) {
        if (this.#isOpen && event.target instanceof Node && this.#root?.contains(event.target) === false) {
            this.#close()
        }
    }

    /**
     * @param {string} hook
     * @returns {HTMLElement | null}
     */
    #element(hook) {
        const node = this.#contract.one(hook)

        return node instanceof HTMLElement ? node : null
    }
}
