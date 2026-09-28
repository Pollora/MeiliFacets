import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { PageRoots } from '../../resources/assets/ts/shared/page-roots.ts'
import { RootComponent } from '../../resources/assets/ts/shared/root-component.ts'
import { CONTRACT, load } from './dom.ts'

import type { TestContext } from 'node:test'
import type { BoundRoot, RootBinder, ScriptModule } from '../../resources/assets/ts/shared/page-roots.ts'

const LISTING: ScriptModule = { id: '@meilifacets/listing', roots: 'listings' }
const SEARCH: ScriptModule = { id: '@meilifacets/site-search', roots: 'searches' }
const CONNECTION = { url: 'https://engine.test', key: 'search-only', index: 'posts' }

interface Described {
    label: string
}

class RecordingBinder implements RootBinder<Described> {
    readonly bound: { root: string, label: string, index: string }[] = []

    bind({ contract, description, connection }: BoundRoot<Described>) {
        this.bound.push({ root: contract.root.id, label: description.label, index: connection.index })
    }
}

const published = (module: ScriptModule, descriptions: Record<string, Described>) => `
    <script type="application/json" id="wp-script-module-data-${module.id}">${JSON.stringify({
        connection: CONNECTION,
        [module.roots]: descriptions,
    })}</script>`

const listing = (name: string, inner = '<div data-meili="results"></div><template data-meili="card-template"><a data-meili="card"></a><a data-meili="url"></a><img data-meili="image"><b data-meili="title"></b><i data-meili="price"></i></template><p data-meili="empty"></p>') =>
    `<div id="listing-${name}" data-listing="${name}" data-meili-contract="${CONTRACT}">${inner}</div>`

const search = (name: string) =>
    `<div id="search-${name}" data-meili="search" data-search="${name}" data-meili-contract="${CONTRACT}"></div>`

const started = (t: TestContext, markup: string, component: RootComponent, module: ScriptModule) => {
    const errors = t.mock.method(console, 'error', () => undefined)
    const binder = new RecordingBinder()

    new PageRoots<Described>(load(markup).document, component, module).start(binder)

    return { bound: binder.bound, errors: errors.mock.calls.map((call) => String(call.arguments[0])) }
}

describe('PageRoots', () => {
    it('binds each root by its name, with its own description and the shared connection', (t) => {
        const markup = listing('products') + listing('articles') + published(LISTING, {
            products: { label: 'Products' },
            articles: { label: 'Articles' },
        })

        assert.deepEqual(started(t, markup, RootComponent.LISTING, LISTING), {
            bound: [
                { root: 'listing-products', label: 'Products', index: 'posts' },
                { root: 'listing-articles', label: 'Articles', index: 'posts' },
            ],
            errors: [],
        })
    })

    it('binds the roots of its own component only, from the key its module publishes', (t) => {
        const markup = search('header') + listing('products') + published(SEARCH, { header: { label: 'Header' } })

        assert.deepEqual(started(t, markup, RootComponent.SEARCH, SEARCH), {
            bound: [{ root: 'search-header', label: 'Header', index: 'posts' }],
            errors: [],
        })
    })

    it('stays silent on a page with neither root nor data', (t) => {
        assert.deepEqual(started(t, '<main></main>', RootComponent.LISTING, LISTING), { bound: [], errors: [] })
    })

    it('names the module when a root is served and nothing was published under it', (t) => {
        assert.deepEqual(started(t, listing('products'), RootComponent.LISTING, LISTING), {
            bound: [],
            errors: ['[meilifacets] no data was published under @meilifacets/listing.'],
        })
    })

    it('names a root the page does not describe, and binds the others', (t) => {
        const markup = search('header') + search('footer') + published(SEARCH, { header: { label: 'Header' } })

        assert.deepEqual(started(t, markup, RootComponent.SEARCH, SEARCH), {
            bound: [{ root: 'search-header', label: 'Header', index: 'posts' }],
            errors: ['[meilifacets] the page describes no search named "footer".'],
        })
    })

    it('leaves a root that breaches the contract unbound, naming what to fix', (t) => {
        const markup = listing('products', '<div data-meili="results"></div>') + published(LISTING, { products: { label: 'Products' } })

        assert.deepEqual(started(t, markup, RootComponent.LISTING, LISTING), {
            bound: [],
            errors: ['[meilifacets] the markup does not meet the contract: card-template, empty'],
        })
    })

    it('names the hooks it owns left outside every root, and where they belong', (t) => {
        const markup = `<button data-meili="reset"></button><div data-meili="search-panel"></div>${search('header')}`
            + published(SEARCH, { header: { label: 'Header' } })

        assert.deepEqual(started(t, markup, RootComponent.SEARCH, SEARCH).errors, [
            '[meilifacets] the client binds inside [data-search] only. Move inside <x-meilifacets::search> : search-panel.',
        ])
    })
})
