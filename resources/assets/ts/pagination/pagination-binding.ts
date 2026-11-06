import { PageLinks } from './page-links.ts'
import { PageWindow } from './page-window.ts'
import { PaginationView } from './pagination-view.ts'

import type { Contract } from '../shared/contract.ts'
import type { ListingDescription } from '../shared/description.ts'
import type { ListingState } from '../listing/listing-state.ts'
import type { ListingUrl } from '../listing/listing-url.ts'

export class PaginationBinding {
    #description: ListingDescription
    #url: ListingUrl
    #links: PageLinks
    #view: PaginationView

    constructor(contract: Contract, description: ListingDescription, url: ListingUrl) {
        this.#description = description
        this.#url = url
        this.#links = new PageLinks(url)
        this.#view = new PaginationView(contract)
    }

    windowOf(state: ListingState, total: number) {
        return new PageWindow({
            asked: state.page,
            perPage: this.#description.perPage,
            total,
            reachable: this.#description.reachableHits,
        })
    }

    show(pageWindow: PageWindow, state: ListingState) {
        this.#view.show(pageWindow, this.#url.linksFor(state))
    }

    pageOf(event: Event) {
        return this.#links.pageOf(event)
    }
}
