import { Contract } from './contract.js'
import { Listing } from './listing.js'
import { ListingBinding } from './listing-binding.js'

const MODULE = '@meilifacets/listing'
const LISTING_ATTRIBUTE = 'data-listing'

/**
 * Reads what the server left for us and starts one listing per root. A root
 * whose markup no longer matches the contract is left alone: the page it was
 * served with is complete, and half a client is worse than none.
 */
const start = () => {
    const roots = [...document.querySelectorAll(`[${LISTING_ATTRIBUTE}]`)]
    const data = document.getElementById(`wp-script-module-data-${MODULE}`)?.textContent

    if (!data) {
        // The module id is written on both sides of the boundary: a mismatch
        // would otherwise leave a listing served and inert, saying nothing.
        roots.length > 0 && console.error(`[meilifacets] no data was published under ${MODULE}.`)

        return
    }

    const orphans = Contract.orphans(document, roots)

    if (orphans.length > 0) {
        const move = `Move inside <x-meilifacets::listing> : ${orphans.join(', ')}.`

        console.error(`[meilifacets] the client binds inside [${LISTING_ATTRIBUTE}] only. ${move}`)
    }

    const { connection, listings } = JSON.parse(data)

    for (const root of roots) {
        const name = root.getAttribute(LISTING_ATTRIBUTE) ?? ''
        const description = listings[name]
        const contract = new Contract(root)
        const breaches = contract.breaches()

        if (!description) {
            console.error(`[meilifacets] the page describes no listing named "${name}".`)

            continue
        }

        if (breaches.length > 0) {
            console.error(`[meilifacets] the markup does not meet the contract: ${breaches.join(', ')}`)

            continue
        }

        new ListingBinding(root, contract, new Listing(description, connection), description).start()
    }
}

start()
