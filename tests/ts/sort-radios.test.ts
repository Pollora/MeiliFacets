import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { filterQueriesOf } from '../../resources/assets/ts/filter-queries.ts'
import { ListingBinding } from '../../resources/assets/ts/listing/listing-binding.ts'
import { Listing } from '../../resources/assets/ts/listing/listing.ts'
import { ListingState } from '../../resources/assets/ts/listing/listing-state.ts'
import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { SortRadios } from '../../resources/assets/ts/sort/sort-radios.ts'
import { click, find, listingMarkup, open, tick } from './dom.ts'
import { connection, described, FakeClient, FakeHistory } from './fixtures.ts'

import type { ListingDescription } from '../../resources/assets/ts/shared/description.ts'

const choice = (value: string, label: string, checked = false) => `
                <li class="meilifacetsFacetValue">
                    <label>
                        <input type="radio" name="sort" value="${value}"${checked ? ' checked' : ''} data-meili="sort-choice">
                        <span class="meilifacetsFacetName">${label}</span>
                    </label>
                </li>`

const PLAIN = { legend: 'Sort by', panel: '' }

/** Mirrors `toggle.blade.php` holding the sort summary: no badge, the order in force inside the name. */
const COLLAPSIBLE = {
    legend: `<button type="button" class="meilifacetsFacetToggle" aria-expanded="false" aria-controls="sort-panel" data-meili="toggle">
            <span class="meilifacetsFacetToggleName"><span class="meilifacetsFacetToggleLabel">Sort by</span><span class="meilifacetsSortSummary">: <span class="meilifacetsSortChoice" data-meili="sort-chosen">Relevance</span></span></span>
        </button>`,
    panel: ' hidden data-meili="panel"',
}

/** Mirrors `sort-radios.blade.php`, in place of the listbox. */
const radiosMarkup = (folding = PLAIN) => listingMarkup().replace(/<div class="meilifacetsSort"[\s\S]*?<\/ul>\s*<\/div>/, `
    <fieldset class="meilifacetsFacet meilifacetsSortChoices" data-meili="sort-choices">
        <legend class="meilifacetsFacetLabel">${folding.legend}</legend>
        <div class="meilifacetsFacetPanel" id="sort-panel"${folding.panel}>
            <ul class="meilifacetsFacetValues">${choice('', 'Relevance', true)}${choice('price_asc', 'Price, low to high')}${choice('on_sale', 'On sale')}
            </ul>
        </div>
    </fieldset>`)

const description = (apply: ListingDescription['apply']) => described({
    apply,
    facets: [{ taxonomy: 'product_brand', multiple: true, cap: 5, visible: 10, labels: {}, counts: {} }],
    params: { product_brand: 'brand' },
})

const bound = (apply: ListingDescription['apply'], folding = PLAIN) => {
    const { window, root } = open(radiosMarkup(folding))
    const client = new FakeClient()
    const history = new FakeHistory()
    const listed = description(apply)
    const listing = new Listing(listed, connection, { filterQueries: filterQueriesOf(listed), client, history })
    new ListingBinding(new Contract(root), listing, listed).start()

    return { window, root, client, history, listing }
}

describe('SortRadios', () => {
    /** D-10: a sort is an order, searched at once even where filters wait for « Apply ». */
    it('sorts at once when a radio is picked, in submit too', () => {
        const { window, root, client, listing } = bound('submit')

        tick(window, find<HTMLInputElement>(root, 'input[value="price_asc"]'))

        assert.equal(listing.state.sort, 'price_asc')
        assert.equal(client.plans.length, 1)
    })

    it('goes back to the engine order on the default radio', () => {
        const { window, root, listing } = bound('immediate')

        tick(window, find<HTMLInputElement>(root, 'input[value="price_asc"]'))
        tick(window, find<HTMLInputElement>(root, 'input[value=""]'))

        assert.equal(listing.state.sort, null)
    })

    it('checks the radio of the sort in force', () => {
        const { root } = open(radiosMarkup())
        const radios = new SortRadios(new Contract(root), () => undefined)

        radios.show(new ListingState({ sort: 'on_sale' }))

        assert.equal(find<HTMLInputElement>(root, 'input[value="on_sale"]').checked, true)
        assert.equal(find<HTMLInputElement>(root, 'input[value=""]').checked, false)
    })

    it('hides a sort that would keep nothing, unless it is the one in force', () => {
        const { root } = open(radiosMarkup())
        const radios = new SortRadios(new Contract(root), () => undefined)
        const row = (value: string) => find(root, `input[value="${value}"]`).closest('li') as HTMLElement

        radios.showMatches({ on_sale: 0 }, new ListingState())
        assert.equal(row('on_sale').hidden, true)

        radios.showMatches({ on_sale: 0 }, new ListingState({ sort: 'on_sale' }))
        assert.equal(row('on_sale').hidden, false)
    })

    describe('in a collapsible section', () => {
        const trigger = (root: Element) => find(root, Contract.selector('toggle'))

        it('names the order in force in its trigger once another is picked', () => {
            const { window, root } = bound('submit', COLLAPSIBLE)

            tick(window, find<HTMLInputElement>(root, 'input[value="price_asc"]'))

            assert.equal(find(root, Contract.selector('sort-chosen')).textContent, 'Price, low to high')
            assert.equal(trigger(root).textContent?.trim(), 'Sort by: Price, low to high')
        })

        it('names the order the history goes back to', () => {
            const { window, root, history } = bound('immediate', COLLAPSIBLE)

            tick(window, find<HTMLInputElement>(root, 'input[value="on_sale"]'))
            history.goBackTo('products', new ListingState({ sort: 'price_asc' }).toDescription())

            assert.equal(trigger(root).textContent?.trim(), 'Sort by: Price, low to high')
        })

        /** Mobile first: the section reads its label alone, the pill its label and the order in force. */
        const summaryAt = (width: number) => {
            const { window, root } = open(radiosMarkup(COLLAPSIBLE), { styled: true })
            window.happyDOM.setViewport({ width, height: 800 })

            return window.getComputedStyle(find(root, Contract.selector('sort-chosen')).parentElement as HTMLElement)
        }

        it('keeps the order in force out of sight in the section, but in its name', () => {
            const summary = summaryAt(390)

            assert.equal(summary.clipPath, 'inset(50%)')
            assert.notEqual(summary.display, 'none')
        })

        it('shows the order in force in the pill', () => {
            assert.equal(summaryAt(1440).clipPath, 'none')
        })
    })

    it('leaves a facet box to the facets', () => {
        const { window, root, listing } = bound('immediate')

        tick(window, find<HTMLInputElement>(root, 'input[value="acme"]'))

        assert.equal(listing.state.sort, null)
    })
})

describe('the sort choices contract', () => {
    it('requires a choice in the group, and a panel once it holds a trigger', () => {
        const { root } = open(radiosMarkup().replace(/data-meili="sort-choice"/g, ''))
        find(root, '[data-meili="sort-choices"] legend').insertAdjacentHTML('afterbegin', '<button data-meili="toggle">Sort by</button>')

        assert.deepEqual(new Contract(root).breaches(), ['sort-choices > sort-choice', 'sort-choices > panel'])
    })
})

/** C-1: « Apply » searches what waits in `submit`, and nothing in `immediate`, where every tick has searched. */
describe('the apply button', () => {
    it('searches the pending selection in submit', async () => {
        const { window, root, client } = bound('submit')

        tick(window, find<HTMLInputElement>(root, 'input[value="acme"]'))
        click(window, find(root, Contract.selector('apply')))
        await new Promise((resolve) => setTimeout(resolve, 0))

        assert.equal(client.plans.length, 1)
    })

    it('searches nothing more in immediate', async () => {
        const { window, root, client } = bound('immediate')

        tick(window, find<HTMLInputElement>(root, 'input[value="acme"]'))
        click(window, find(root, Contract.selector('apply')))
        await new Promise((resolve) => setTimeout(resolve, 0))

        assert.equal(client.plans.length, 1)
    })
})
