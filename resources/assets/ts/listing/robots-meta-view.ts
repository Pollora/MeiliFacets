import type { ByView } from '../shared/description.ts'
import type { ListingState } from './listing-state.ts'

const ROBOTS = 'meta[name="robots"]'

/** `IndexingPolicy::isSecondaryView()`: a filter, a sort, a search or a page past the first. */
export class RobotsMetaView {
    #robots: ByView | null
    #meta: HTMLMetaElement | null

    constructor(robots: ByView | null, document: Document) {
        this.#robots = robots
        this.#meta = document.head.querySelector<HTMLMetaElement>(ROBOTS)
    }

    show(state: ListingState) {
        if (this.#robots === null || this.#meta === null) {
            return
        }

        this.#meta.content = state.isPristine() ? this.#robots.bare : this.#robots.secondary
    }
}
