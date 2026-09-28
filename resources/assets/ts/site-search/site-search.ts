import { SearchClient, SearchSuperseded } from '../shared/search-client.ts'
import { SearchesUnderWay } from '../shared/searches-under-way.ts'
import { ComboboxKeys } from './combobox-keys.ts'
import { SectionView } from './section-view.ts'
import { SiteSearchQuery } from './site-search-query.ts'
import { StatusView } from './status-view.ts'
import { Typing } from './typing.ts'

import type { SiteSearchDescription } from '../shared/description.ts'
import type { BoundRoot } from '../shared/page-roots.ts'
import type { Answers } from '../shared/search-client.ts'

/** From the moment a search leaves, the typing delay past, until its answer is on screen. */
export const SEARCH_SENT = 'meilifacets:search-sent'
export const SEARCH_SHOWN = 'meilifacets:search-shown'

/** One search root: what is typed in its field, searched in the sections placed in its panel. */
export class SiteSearch {
    #description: SiteSearchDescription
    #client: SearchClient
    #sections: SectionView[]
    #query: SiteSearchQuery
    #status: StatusView
    #input: HTMLInputElement | null
    #keys: ComboboxKeys | null
    #searchesUnderWay: SearchesUnderWay

    constructor({ contract, description, connection }: BoundRoot<SiteSearchDescription>) {
        const input = contract.one('search-input')

        this.#description = description
        this.#client = new SearchClient(connection)
        this.#sections = SectionView.allIn(contract, description)
        this.#query = new SiteSearchQuery(this.#sections.map((section) => section.searched))
        this.#status = new StatusView(contract, description)
        this.#searchesUnderWay = new SearchesUnderWay(this.#status)
        this.#input = input instanceof HTMLInputElement ? input : null
        this.#keys = this.#input === null ? null : new ComboboxKeys(contract, this.#input)
    }

    start() {
        if (this.#input === null || this.#keys === null) {
            console.error(`[meilifacets] the search "${this.#description.name}" has no <input> to read.`)

            return this
        }

        this.#keys.start()
        new Typing(this.#input, this.#description).start({
            search: (term) => void this.#search(term),
            clear: () => this.#clear(),
        })

        return this
    }

    async #search(term: string) {
        this.#searchesUnderWay.leave()
        performance.mark(SEARCH_SENT)

        try {
            this.#show(await this.#client.search(this.#query.plan(term)))
            performance.measure(SEARCH_SHOWN, SEARCH_SENT)
        } catch (failure) {
            if (!(failure instanceof SearchSuperseded)) {
                this.#fail(failure)
            }
        } finally {
            this.#searchesUnderWay.end()
        }
    }

    #show(answers: Answers) {
        this.#sections.forEach((section) => section.show(answers[section.searched.type.postType] ?? {}))
        this.#keys?.offer(this.#sections.flatMap((section) => section.options))
        this.#status.answered(this.#sections.map((section) => section.count))
    }

    #fail(failure: unknown) {
        console.error('[meilifacets] the search failed:', failure)
        this.#hideSections()
        this.#status.failed()
    }

    #clear() {
        this.#client.abandon()
        this.#hideSections()
        this.#status.cleared()
    }

    #hideSections() {
        this.#sections.forEach((section) => section.hide())
        this.#keys?.offer([])
    }
}
