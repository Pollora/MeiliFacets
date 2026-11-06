import { CanonicalLinkView } from './canonical-link-view.ts'
import { DocumentTitleView } from './document-title-view.ts'
import { RobotsMetaView } from './robots-meta-view.ts'

import type { ListingDescription } from '../shared/description.ts'
import type { PageWindow } from '../pagination/page-window.ts'
import type { ListingState } from './listing-state.ts'

/** What the server prints in the head for the view shown; crawlers read it on each address, never here. */
export class PageHeadView {
    #title: DocumentTitleView
    #canonical: CanonicalLinkView
    #robots: RobotsMetaView

    constructor(description: ListingDescription, document: Document) {
        this.#title = new DocumentTitleView(description.pageTitles, document)
        this.#canonical = new CanonicalLinkView(description.pageCanonicals, document)
        this.#robots = new RobotsMetaView(description.pageRobots, document)
    }

    show(pageWindow: PageWindow, state: ListingState) {
        this.#title.show(pageWindow)
        this.#canonical.show(pageWindow)
        this.#robots.show(state)
    }
}
