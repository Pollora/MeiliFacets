import { SearchTermInput } from '../shared/search-term-input.ts'

import type { Contract } from '../shared/contract.ts'
import type { SearchTermSettings } from '../shared/search-term-input.ts'
import type { Listing } from './listing.ts'
import type { ListingState } from './listing-state.ts'

type SearchedListing = Pick<Listing, 'state' | 'appliesImmediately' | 'search' | 'searchNow' | 'removeSearch'>

interface ListingSearchElements {
    form: HTMLFormElement
    input: HTMLInputElement
    clear: HTMLElement | null
}

interface ListingSearchSetup {
    listing: SearchedListing
    settings: SearchTermSettings
}

export class ListingSearch {
    #form: HTMLFormElement
    #input: HTMLInputElement
    #clear: HTMLElement | null
    #listing: SearchedListing
    #terms: SearchTermInput | null
    /** Set while this field moves the state: what it hears back is its own term, already on screen. */
    #moving = false
    #typing = false

    constructor({ form, input, clear }: ListingSearchElements, { listing, settings }: ListingSearchSetup) {
        this.#form = form
        this.#input = input
        this.#clear = clear
        this.#listing = listing
        this.#terms = listing.appliesImmediately ? new SearchTermInput(input, settings) : null
    }

    static allIn(contract: Contract, setup: ListingSearchSetup) {
        return contract.all('listing-search').flatMap((form) => {
            const elements = ListingSearch.#elementsIn(contract, form)

            return elements === null ? [] : [new ListingSearch(elements, setup).start()]
        })
    }

    static #elementsIn(contract: Contract, form: Element): ListingSearchElements | null {
        const input = contract.one('listing-search-input', form)
        const clear = contract.one('listing-search-clear', form)
        const isUsable = form instanceof HTMLFormElement && input instanceof HTMLInputElement

        return isUsable ? { form, input, clear: clear instanceof HTMLElement ? clear : null } : null
    }

    start() {
        this.#form.addEventListener('submit', (event) => this.#submitted(event))
        this.#input.addEventListener('input', () => this.#typed())
        this.#clear?.addEventListener('click', () => this.#cleared())
        this.#terms?.startFromServedTerm({
            search: (term) => this.#typedAtRest(term),
            clear: () => this.#typedAtRest(''),
        })

        return this
    }

    /** Not read from the focus: Safari leaves it in the field when a button is clicked. */
    isTyping() {
        return this.#typing
    }

    show(state: ListingState) {
        if (!this.#moving) {
            this.#typing = false
            this.#write(state.query)
        }
    }

    #typed() {
        this.#showClear()

        if (!this.#listing.appliesImmediately) {
            this.#move(() => this.#listing.search(this.#input.value))
        }
    }

    #submitted(event: Event) {
        event.preventDefault()
        this.#typing = false
        this.#move(() => this.#listing.searchNow(this.#input.value))
    }

    #cleared() {
        this.#typing = false
        this.#write('')
        this.#input.focus()
        this.#move(() => this.#listing.removeSearch())
    }

    #move(change: () => void) {
        this.#moving = true

        try {
            change()
        } finally {
            this.#moving = false
        }
    }

    #write(term: string) {
        if (this.#terms === null) {
            this.#input.value = term
        } else {
            this.#terms.show(term)
        }

        this.#showClear()
    }

    #typedAtRest(term: string) {
        if (term !== this.#listing.state.query) {
            this.#typing = true
            this.#move(() => this.#listing.search(term))
        }
    }

    #showClear() {
        if (this.#clear !== null) {
            this.#clear.hidden = this.#input.value === ''
        }
    }
}
