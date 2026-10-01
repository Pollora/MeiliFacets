import { filterQueriesOf } from './filter-queries.ts'
import { BrowserHistory } from './listing/browser-history.ts'
import { Listing } from './listing/listing.ts'
import { ListingBinding } from './listing/listing-binding.ts'
import { PageRoots } from './shared/page-roots.ts'
import { RootComponent } from './shared/root-component.ts'

import type { ListingDescription } from './shared/description.ts'
import type { BoundRoot, RootBinder, ScriptModule } from './shared/page-roots.ts'

const MODULE: ScriptModule = { id: '@meilifacets/listing', roots: 'listings' }

/** Starts one listing per root, all sharing the browser's history. */
class ListingPage implements RootBinder<ListingDescription> {
    #history = new BrowserHistory()

    bind({ contract, description, connection }: BoundRoot<ListingDescription>) {
        const listing = new Listing(description, connection, {
            filterQueries: filterQueriesOf(description),
            history: this.#history,
        })

        new ListingBinding(contract, listing, description).start()
    }
}

new PageRoots<ListingDescription>(document, RootComponent.LISTING, MODULE).start(new ListingPage())
