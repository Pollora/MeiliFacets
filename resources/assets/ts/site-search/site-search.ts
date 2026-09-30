import { SearchClient, SearchSuperseded } from '../shared/search-client.ts'
import { PendingSearches } from '../shared/pending-searches.ts'
import { ComboboxKeys } from './combobox-keys.ts'
import { PanelClosing } from './panel-closing.ts'
import { ResultsMotion } from './results-motion.ts'
import { SectionView } from './section-view.ts'
import { SiteSearchQuery } from './site-search-query.ts'
import { StatusView } from './status-view.ts'
import { SearchTermInput } from './search-term-input.ts'

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
    #pendingSearches: PendingSearches
    #motion: ResultsMotion | null
    #closing: PanelClosing

    constructor({ contract, description, connection }: BoundRoot<SiteSearchDescription>) {
        const input = contract.one('search-input')
        const panel = contract.one('search-panel')

        this.#description = description
        this.#client = new SearchClient(connection)
        this.#sections = SectionView.allIn(contract, description)
        this.#query = new SiteSearchQuery(this.#sections.map((section) => section.searched))
        this.#status = new StatusView(contract, description)
        this.#pendingSearches = new PendingSearches(this.#status)
        this.#input = input instanceof HTMLInputElement ? input : null
        this.#keys = this.#input === null ? null : new ComboboxKeys(contract, this.#input)
        this.#motion = panel instanceof HTMLElement ? new ResultsMotion(contract, panel) : null
        this.#closing = new PanelClosing(contract.root)
    }

    start() {
        if (this.#input === null || this.#keys === null) {
            console.error(`[meilifacets] the search "${this.#description.name}" has no <input> to read.`)

            return this
        }

        this.#keys.start().control(this.#sections.flatMap((section) => section.listbox ?? []))
        this.#input.addEventListener('input', () => this.#status.typing())
        this.#closing.observe(() => this.#status.closed())
        new SearchTermInput(this.#input, this.#description).start({
            search: (term) => void this.#search(term),
            clear: () => this.#clear(),
        })

        return this
    }

    async #search(term: string) {
        this.#pendingSearches.start()
        performance.mark(SEARCH_SENT)

        try {
            this.#show(await this.#client.search(this.#query.plan(term)))
            performance.measure(SEARCH_SHOWN, SEARCH_SENT)
        } catch (failure) {
            if (!(failure instanceof SearchSuperseded)) {
                this.#fail(failure)
            }
        } finally {
            this.#pendingSearches.finish()
        }
    }

    #show(answers: Answers) {
        this.#paint(() => {
            this.#sections.forEach((section) => section.show(answers[section.searched.type.postType] ?? {}))
            this.#keys?.setOptions(this.#sections.flatMap((section) => section.options))
            this.#status.answered(this.#sections.map((section) => section.count))
        })
    }

    #fail(failure: unknown) {
        console.error('[meilifacets] the search failed:', failure)
        this.#paint(() => {
            this.#hideSections()
            this.#status.failed()
        })
    }

    #clear() {
        this.#client.abandon()
        this.#paint(() => {
            this.#hideSections()
            this.#status.cleared()
        })
    }

    #paint(change: () => void) {
        if (this.#motion === null) {
            change()
        } else {
            this.#motion.around(change)
        }
    }

    #hideSections() {
        this.#sections.forEach((section) => section.hide())
        this.#keys?.setOptions([])
    }
}
