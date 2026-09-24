import { filterQueriesOf } from './filter-queries.ts'
import { BrowserHistory } from './listing/browser-history.ts'
import { Listing } from './listing/listing.ts'
import { ListingBinding } from './listing/listing-binding.ts'
import { Contract } from './shared/contract.ts'

import type { Connection, ListingDescription } from './shared/description.ts'

const MODULE = '@meilifacets/listing'
const LISTING_ATTRIBUTE = 'data-listing'

interface PublishedData {
    connection: Connection
    listings: Partial<Record<string, ListingDescription>>
}

/**
 * Reads what the server left for us and starts one listing per root. A root
 * whose markup no longer matches the contract is left alone: the page it was
 * served with is complete, and half a client is worse than none.
 */
class ListingPage {
    #document: Document
    #history = new BrowserHistory()

    constructor(document: Document) {
        this.#document = document
    }

    start() {
        const roots = [...this.#document.querySelectorAll(`[${LISTING_ATTRIBUTE}]`)]
        const published = this.#published(roots)

        if (published === null) {
            return
        }

        this.#reportOrphans(roots)
        roots.forEach((root) => this.#bind(root, published))
    }

    #published(roots: Element[]) {
        const data = this.#document.getElementById(`wp-script-module-data-${MODULE}`)?.textContent

        if (data) {
            return JSON.parse(data) as PublishedData
        }

        // The module id is written on both sides of the boundary: a mismatch
        // would otherwise leave a listing served and inert, saying nothing.
        if (roots.length > 0) {
            console.error(`[meilifacets] no data was published under ${MODULE}.`)
        }

        return null
    }

    #reportOrphans(roots: Element[]) {
        const orphans = Contract.orphans(this.#document, roots)

        if (orphans.length > 0) {
            const move = `Move inside <x-meilifacets::listing> : ${orphans.join(', ')}.`

            console.error(`[meilifacets] the client binds inside [${LISTING_ATTRIBUTE}] only. ${move}`)
        }
    }

    #bind(root: Element, { connection, listings }: PublishedData) {
        const name = root.getAttribute(LISTING_ATTRIBUTE) ?? ''
        const description = listings[name]
        const contract = new Contract(root)

        if (!description) {
            console.error(`[meilifacets] the page describes no listing named "${name}".`)

            return
        }

        const breaches = contract.breaches()

        if (breaches.length > 0) {
            console.error(`[meilifacets] the markup does not meet the contract: ${breaches.join(', ')}`)

            return
        }

        const listing = new Listing(description, connection, {
            filterQueries: filterQueriesOf(description),
            history: this.#history,
        })

        new ListingBinding(contract, listing, description).start()
    }
}

new ListingPage(document).start()
