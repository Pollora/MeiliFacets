import { CONTRACT, find, load } from './dom.ts'

import type { TestContext } from 'node:test'
import type { SearchTypeDescription, SiteSearchDescription } from '../../resources/assets/ts/shared/description.ts'
import type { SearchAnswer, SearchQuery } from '../../resources/assets/ts/shared/search-client.ts'
import type { TestWindow } from './dom.ts'

export const OPENING = ''
export const CLOSING = ''

const searchType = (postType: string, heading: string, searchOn: string[]): SearchTypeDescription => ({
    postType,
    heading,
    seeAllLabel: `All ${heading.toLowerCase()}`,
    baseFilter: [`post_type = "${postType}"`, 'post_status = "publish"'],
    searchOn,
    archive: `https://example.test/${postType}`,
})

/** Products then posts, two characters and 120 ms: what the module publishes by default. */
export const searchDescribed = (partial: Partial<SiteSearchDescription> = {}): SiteSearchDescription => ({
    name: 'search',
    minChars: 2,
    delay: 120,
    limit: 4,
    types: [searchType('product', 'Products', ['post_title', 'metas._sku']), searchType('post', 'Posts', ['post_title', 'excerpt'])],
    countPattern: ':count result|:count results',
    sectionPattern: ':heading: :count',
    locale: 'en',
    preconnect: 'https://engine.test',
    ...partial,
})

export const searchSection = (postType: string, limit: number | null = null) => `
        <section data-meili="search-section" data-type="${postType}"${limit === null ? '' : ` data-limit="${limit}"`} hidden>
            <h2 id="heading-${postType}">${postType} <span data-meili="search-count"></span></h2>
            <ul role="group" aria-labelledby="heading-${postType}" data-meili="search-results"></ul>
            <template data-meili="search-card-template">
                <li role="option" data-meili="card">
                    <a href="" data-meili="url"><img alt="" data-meili="image"><span data-meili="title"></span></a>
                    <p data-meili="summary"></p>
                    <span data-meili="price"></span>
                </li>
            </template>
        </section>`

/** Mirrors the bricks the plan composes (§ 2): the Blade views come at step 5. */
export const searchMarkup = (sections = searchSection('product') + searchSection('post')) => `
<header>
    <div data-meili="search" data-search="search" data-meili-contract="${CONTRACT}">
        <button type="button" aria-expanded="false" aria-controls="search-panel" data-meili="search-toggle">Search</button>
        <div id="search-panel" role="search" hidden data-meili="search-panel">
            <input id="search-input" type="search" role="combobox" aria-expanded="false" aria-controls="search-listbox"
                   aria-autocomplete="list" data-meili="search-input">
            <div id="search-listbox" role="listbox">${sections}
            </div>
            <p hidden data-meili="search-empty">Nothing matches   your search</p>
            <p hidden data-meili="search-unavailable">Search is unavailable</p>
            <p aria-live="polite" data-meili="search-status"></p>
        </div>
    </div>
</header>`

export const openSearch = (markup = searchMarkup()) => {
    const window = load(markup)

    return { window, root: find(window.document, '[data-search]') }
}

export const hit = (id: number, title: string, formatted: Record<string, unknown> = {}) => ({
    ID: id,
    card: { title, url: `https://example.test/${id}` },
    _formatted: { card: { title, ...formatted } },
})

interface Request {
    queries: (SearchQuery & { indexUid: string })[]
    signal: AbortSignal
    answer(results: SearchAnswer[]): void
    refuse(status: number, message: string): void
    drop(): void
}

/**
 * Stands in for `fetch`, one request at a time answered by the test. A request
 * the client aborts is rejected as the browser rejects it: with the signal's reason.
 */
export class FakeEngine {
    readonly requests: Request[] = []

    constructor(t: TestContext) {
        t.mock.method(globalThis, 'fetch', (_url: string, init: RequestInit) => this.#request(init))
    }

    get last() {
        const request = this.requests.at(-1)

        if (request === undefined) {
            throw new Error('No request reached the engine.')
        }

        return request
    }

    #request(init: RequestInit) {
        const { queries } = JSON.parse(init.body as string) as { queries: Request['queries'] }
        const signal = init.signal as AbortSignal

        return new Promise<Response>((resolve, reject) => {
            signal.addEventListener('abort', () => reject(signal.reason as Error))
            this.requests.push({
                queries,
                signal,
                answer: (results) => resolve({ ok: true, json: () => Promise.resolve({ results }) } as Response),
                refuse: (status, message) => resolve({ ok: false, status, statusText: '', json: () => Promise.resolve({ message }) } as Response),
                drop: () => reject(new TypeError('Failed to fetch')),
            })
        })
    }
}

/** Lets the promises a settled request started run to their end. */
export const settle = async () => {
    for (let turn = 0; turn < 5; turn += 1) {
        await new Promise((resolve) => setImmediate(resolve))
    }
}

export const typeInto = (window: TestWindow, input: HTMLInputElement, value: string) => {
    input.value = value
    input.dispatchEvent(new window.Event('input', { bubbles: true }))
}
