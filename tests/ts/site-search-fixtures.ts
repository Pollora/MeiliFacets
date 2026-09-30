import { CONTRACT, SEARCH_STYLESHEET, find, load, nextTurn } from './dom.ts'

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
    seeAllParameter: 'q',
    ...partial,
})

export const searchSection = (postType: string, limit: number | null = null) => `
        <div data-meili="search-section" data-type="${postType}"${limit === null ? '' : ` data-limit="${limit}"`} hidden>
            <h2 id="heading-${postType}">${postType} <span data-meili="search-count"></span></h2>
            <a href="https://example.test/${postType}" data-meili="search-see-all">All</a>
            <ul id="results-${postType}" role="listbox" aria-labelledby="heading-${postType}" data-meili="search-results"></ul>
            <template data-meili="search-card-template">
                <li role="option" data-meili="card">
                    <a href="" data-meili="url">
                        <img alt="" hidden data-meili="image"><span data-meili="title"></span>
                        <span hidden data-meili="summary"></span><span hidden data-meili="price"></span>
                    </a>
                </li>
            </template>
        </div>`

/** Mirrors the default composition the module renders (`components/search.blade.php`). */
export const searchMarkup = (sections = searchSection('product') + searchSection('post')) => `
<header>
    <div data-meili="search" data-search="search" data-meili-contract="${CONTRACT}">
        <button type="button" aria-expanded="false" aria-controls="search-panel" aria-label="Search" data-meili="search-toggle"></button>
        <div id="search-panel" role="search" hidden data-meili="search-panel">
            <div data-meili="search-field">
                <input id="search-input" type="search" role="combobox" aria-expanded="false" aria-autocomplete="list"
                       aria-label="Search" data-meili="search-input">
            </div>${sections}
            <p hidden data-meili="search-empty">Nothing matches   your search</p>
            <p hidden data-meili="search-unavailable">Search is unavailable</p>
            <p aria-live="polite" data-meili="search-status"></p>
        </div>
    </div>
</header>
<main><a href="/elsewhere" id="elsewhere">Elsewhere</a></main>`

export const openSearch = (markup = searchMarkup(), { styled = false } = {}) => {
    const window = load(markup, { styled, stylesheet: SEARCH_STYLESHEET })

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
        await nextTurn()
    }
}

export const typeInto = (window: TestWindow, input: HTMLInputElement, value: string) => {
    input.value = value
    input.dispatchEvent(new window.Event('input', { bubbles: true }))
}

/** A card a theme rearranged: price above the title, the title nested, the image last, the link inside. */
const rearrangedSection = (postType: string, limit: number) => `
        <section data-meili="search-section" data-type="${postType}" data-limit="${limit}" hidden>
            <ul id="results-${postType}" role="listbox" aria-labelledby="heading-${postType}" data-meili="search-results"></ul>
            <footer>
                <a href="https://example.test/${postType}" data-meili="search-see-all">All</a>
                <h3 id="heading-${postType}"><span data-meili="search-count"></span> ${postType}</h3>
            </footer>
            <template data-meili="search-card-template">
                <li role="option" data-meili="card">
                    <span hidden data-meili="price"></span>
                    <div><a href="" data-meili="url"><strong data-meili="title"></strong></a></div>
                    <span hidden data-meili="summary"></span>
                    <img alt="" hidden data-meili="image">
                </li>
            </template>
        </section>`

/** A composition a theme reordered: posts before products, their own limits, the message first, the magnifier last. */
export const rearrangedSearchMarkup = () => `
<header>
    <div data-meili="search" data-search="search" data-meili-contract="${CONTRACT}">
        <div id="search-panel" role="search" hidden data-meili="search-panel">
            <p hidden data-meili="search-empty">Nothing matches your search</p>
            <div class="columns">${rearrangedSection('post', 2)}${rearrangedSection('product', 3)}</div>
            <div data-meili="search-field">
                <input id="search-input" type="search" role="combobox" aria-expanded="false" aria-autocomplete="list"
                       aria-label="Search" data-meili="search-input">
            </div>
            <p hidden data-meili="search-unavailable">Search is unavailable</p>
            <p aria-live="polite" data-meili="search-status"></p>
        </div>
        <nav><button type="button" aria-expanded="false" aria-controls="search-panel" aria-label="Search" data-meili="search-toggle"></button></nav>
    </div>
</header>
<main><a href="/elsewhere" id="elsewhere">Elsewhere</a></main>`
