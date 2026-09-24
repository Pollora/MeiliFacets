import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { BrowserHistory } from '../../resources/assets/ts/listing/browser-history.ts'
import { served } from './fixtures.ts'

import type { StateDescription } from '../../resources/assets/ts/shared/description.ts'

interface Entry {
    url: URL
    state: unknown
}

/** The history of one tab: entries, a cursor, and `popstate` on every move. */
class FakeTab {
    reloads = 0
    #entries: Entry[]
    #index = 0
    #listeners: ((event: PopStateEvent) => void)[] = []

    constructor(url: string, state: unknown = null) {
        this.#entries = [{ url: new URL(url, 'https://shop.test'), state }]
    }

    get #current(): Entry {
        const entry = this.#entries[this.#index]

        assert.ok(entry)

        return entry
    }

    location = {
        pathname: '',
        search: '',
        reload: () => {
            this.reloads++
        },
    }

    history = {
        state: null as unknown,
        pushState: (state: unknown, _title: string, url?: string | URL | null) => {
            this.#entries = [...this.#entries.slice(0, this.#index + 1), { url: this.#resolve(url), state: structuredClone(state) }]
            this.#index++
            this.#sync()
        },
        replaceState: (state: unknown, _title: string, url?: string | URL | null) => {
            this.#entries[this.#index] = { url: this.#resolve(url), state: structuredClone(state) }
            this.#sync()
        },
        scrollRestoration: 'auto' as ScrollRestoration,
    }

    addEventListener(_type: 'popstate', listener: (event: PopStateEvent) => void) {
        this.#listeners.push(listener)
    }

    get listenerCount() {
        return this.#listeners.length
    }

    followAnchor(fragment: string) {
        const url = new URL(this.#current.url)

        url.hash = fragment
        this.#entries = [...this.#entries.slice(0, this.#index + 1), { url, state: null }]
        this.#index++
        this.#pop()
    }

    back() {
        this.#index--
        this.#pop()
    }

    forward() {
        this.#index++
        this.#pop()
    }

    get address() {
        return this.#current.url.pathname + this.#current.url.search + this.#current.url.hash
    }

    get state() {
        return this.#current.state
    }

    start() {
        this.#sync()

        return this
    }

    #resolve(url: string | URL | null | undefined) {
        return url === undefined || url === null ? this.#current.url : new URL(url, this.#current.url)
    }

    #sync() {
        this.location.pathname = this.#current.url.pathname
        this.location.search = this.#current.url.search
        this.history.state = this.#current.state
    }

    #pop() {
        this.#sync()
        this.#listeners.forEach((listener) => listener({ state: this.#current.state } as PopStateEvent))
    }
}

describe('BrowserHistory', () => {
    let tab: FakeTab
    let history: BrowserHistory
    let restored: [string, StateDescription][]

    const listen = (name: string) => history.onPopState(name, (state) => restored.push([name, state]))

    beforeEach(() => {
        tab = new FakeTab('/shop/page/2?brand=acme', { theme: 'kept' }).start()
        history = new BrowserHistory(tab)
        restored = []
        listen('products')
    })

    it('records a listing on the entry it was served with, address and foreign state untouched', () => {
        history.record('products', served({ page: 2 }))

        assert.equal(tab.address, '/shop/page/2?brand=acme')
        assert.deepEqual(tab.state, { theme: 'kept', meilifacets: { products: served({ page: 2 }) } })
    })

    it('keeps the state of another script on the entry it replaces', () => {
        history.replace('products', served({ page: 3 }), '/shop?pg=3')

        assert.deepEqual(tab.state, { theme: 'kept', meilifacets: { products: served({ page: 3 }) } })
    })

    it('carries every listing of the page into the entry one of them adds, and nothing else', () => {
        history.record('products', served())
        history.record('journal', served({ page: 3 }))
        history.push('products', served({ page: 3 }), '/shop?pg=3')

        assert.deepEqual(tab.state, { meilifacets: { products: served({ page: 3 }), journal: served({ page: 3 }) } })
    })

    it('keeps the other listings when another script emptied the entry', () => {
        history.record('journal', served({ page: 3 }))
        tab.history.replaceState(null, '')

        history.replace('products', served(), '/shop')

        assert.deepEqual(tab.state, { meilifacets: { journal: served({ page: 3 }), products: served() } })
    })

    it('listens to the browser once, whatever the number of listings', () => {
        listen('journal')

        assert.equal(tab.listenerCount, 1)
    })

    it('hands each listing the state the entry holds for it', () => {
        listen('journal')
        history.record('products', served({ page: 2 }))
        history.push('products', served({ page: 3 }), '/shop?pg=3')

        tab.back()

        assert.deepEqual(restored, [['products', served({ page: 2 })]])
    })

    it('records an in-page anchor as the state it already shows, without reloading', () => {
        history.record('products', served({ page: 2 }))

        tab.followAnchor('#main')

        assert.deepEqual([tab.reloads, restored], [0, []])
        assert.deepEqual(tab.state, { meilifacets: { products: served({ page: 2 }) } })
    })

    it('restores an anchor it recorded', () => {
        history.record('products', served({ page: 2 }))
        history.push('products', served({ page: 3 }), '/shop?pg=3')
        tab.followAnchor('#main')

        tab.back()
        tab.forward()

        assert.deepEqual(restored, [['products', served({ page: 3 })], ['products', served({ page: 3 })]])
    })

    it('records an anchor with the state of the entry the visitor went back to', () => {
        history.record('products', served({ page: 2 }))
        history.push('products', served({ page: 3 }), '/shop?pg=3')
        tab.back()

        tab.followAnchor('#main')

        assert.equal(tab.reloads, 0)
        assert.deepEqual(tab.state, { meilifacets: { products: served({ page: 2 }) } })
    })

    it('reloads an entry no listing wrote at another address', () => {
        history.record('products', served({ page: 2 }))
        tab.history.pushState({}, '', '/shop?brand=globex')
        tab.back()

        tab.forward()

        assert.equal(tab.reloads, 1)
        assert.deepEqual(restored, [['products', served({ page: 2 })]])
    })
})
