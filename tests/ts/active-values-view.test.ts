import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { filterQueriesOf } from '../../resources/assets/ts/filter-queries.ts'
import { ListingBinding } from '../../resources/assets/ts/listing/listing-binding.ts'
import { Listing } from '../../resources/assets/ts/listing/listing.ts'
import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { click, listingMarkup, open } from './dom.ts'
import { connection, described, FakeClient, FakeHistory, served } from './fixtures.ts'

import type { ListingDescription } from '../../resources/assets/ts/shared/description.ts'
import type { TestWindow } from './dom.ts'

const base = described({
    facets: [
        { taxonomy: 'product_brand', multiple: true, cap: 30, visible: 1, labels: { acme: 'Acme', globex: 'Globex' }, counts: {} },
        { taxonomy: 'product_cat', multiple: true, cap: 30, visible: 10, labels: { coats: 'Coats & <b>hats</b>' }, counts: {} },
    ],
    params: { product_brand: 'brand', product_cat: 'category' },
    priceFields: { min: 'price.min', max: 'price.max' },
    money: { format: '%2$s %1$s', symbol: '€', decimals: 2, decimal: ',', thousand: ' ' },
})

describe('ActiveValuesView', () => {
    let window: TestWindow
    let root: HTMLElement
    let contract: Contract
    let client: FakeClient
    let history: FakeHistory

    const start = (description: ListingDescription = base) => {
        const listing = new Listing(description, connection, { filterQueries: filterQueriesOf(description), client, history })

        new ListingBinding(contract, listing, description).start()

        return listing
    }

    beforeEach(() => {
        ({ window, root } = open(listingMarkup()))
        contract = new Contract(root)
        client = new FakeClient()
        history = new FakeHistory()
    })

    const pills = (host = contract.one('active-values')) => (host === null ? [] : contract.all('active-value', host))
    const labels = (host = contract.one('active-values')) => pills(host).map((pill) => pill.textContent)
    const pill = (rank: number) => pills()[rank] as HTMLElement
    const list = () => contract.one('active-values') as HTMLElement
    /** The fake engine answers on the next turn: the pills follow its answer. */
    const answered = () => new Promise((resolve) => setTimeout(resolve, 0))

    const applying = async (listing: Listing) => {
        await listing.apply()

        return listing
    }

    it('draws no pill for a value ticked and not applied yet', () => {
        start().toggle('product_brand', 'acme')

        assert.deepEqual(labels(), [])
        assert.equal(list().hidden, true)
    })

    it('draws the applied values, in facet order, and reveals the list', async () => {
        await applying(start().toggle('product_cat', 'coats').toggle('product_brand', 'globex').toggle('product_brand', 'acme'))

        assert.deepEqual(labels(), ['Acme✕', 'Globex✕', 'Coats & <b>hats</b>✕'])
        assert.equal(list().hidden, false)
        assert.equal(pill(2).querySelector('b'), null, 'a label is text, never markup')
    })

    it('draws a value as it is ticked when the listing searches at once', async () => {
        start({ ...base, apply: 'immediate' }).toggle('product_brand', 'acme')
        await answered()

        assert.deepEqual(labels(), ['Acme✕'])
        assert.equal(client.plans.length, 1)
    })

    it('names each pill after what it does, the label included', async () => {
        await applying(start().toggle('product_brand', 'acme'))

        assert.equal(pill(0).getAttribute('aria-label'), 'Remove the Acme filter')
        assert.equal(pill(0).getAttribute('name'), 'brand')
        assert.equal(pill(0).getAttribute('value'), 'acme')
        assert.equal(pill(0).getAttribute('data-kind'), 'term')
    })

    it('draws nothing for a value the page rendered no label for', async () => {
        await applying(start().toggle('product_brand', 'unknown'))

        assert.deepEqual(labels(), [])
        assert.equal(list().hidden, true)
    })

    it('writes the price range the way the shop writes a price, with one bound or two', async () => {
        const listing = start()

        await applying(listing.priceBetween(10, null))
        assert.deepEqual(labels(), ['From 10,00 €✕'])

        await applying(listing.priceBetween(null, 1250))
        assert.deepEqual(labels(), ['Up to 1 250,00 €✕'])

        await applying(listing.priceBetween(10, 50))
        assert.deepEqual(labels(), ['10,00 € – 50,00 €✕'])
        assert.equal(pill(0).getAttribute('data-kind'), 'price')
    })

    it('fills a pattern once, so a label cannot bring in a placeholder or a replacement token', async () => {
        const description = {
            ...base,
            facets: [{ taxonomy: 'product_brand', multiple: true, cap: 30, visible: 10, labels: { odd: ':max $& :label' }, counts: {} }],
        }

        await applying(start(description).toggle('product_brand', 'odd'))

        assert.equal(pill(0).getAttribute('aria-label'), 'Remove the :max $& :label filter')
    })

    it('withdraws the value it names and searches at once, taking the pending filters along', async () => {
        const listing = await applying(start().toggle('product_brand', 'acme'))
        listing.toggle('product_brand', 'globex')
        assert.deepEqual(labels(), ['Acme✕'], 'Globex is pending')

        click(window, pill(0))
        await answered()

        assert.deepEqual(listing.state.selected('product_brand'), ['globex'])
        assert.equal(client.plans.length, 2, 'an order, even in submit mode')
        assert.deepEqual(history.replaced.at(-1), '/shop?brand=globex')
        assert.deepEqual(labels(), ['Globex✕'])
        assert.equal((root.querySelector('input[value="acme"]') as HTMLInputElement).checked, false)
    })

    it('withdraws the whole range from its pill', async () => {
        const listing = await applying(start().priceBetween(10, 50).toggle('product_brand', 'acme'))

        click(window, pill(1))
        await answered()

        assert.equal(listing.state.price.isEmpty(), true)
        assert.deepEqual(listing.state.selected('product_brand'), ['acme'])
        assert.deepEqual(labels(), ['Acme✕'])
    })

    it('reads a pill the server rendered like one it drew itself', async () => {
        const description = { ...base, state: served({ facets: { product_brand: ['acme'] } }) }
        list().hidden = false
        list().insertAdjacentHTML('afterbegin', '<li><button type="button" name="brand" value="acme" data-kind="term" data-meili="active-value">Acme</button></li>')
        const listing = start(description)

        click(window, pill(0))
        await answered()

        assert.deepEqual(listing.state.selected('product_brand'), [])
        assert.deepEqual(labels(), [])
        assert.equal(list().hidden, true)
    })

    it('paints every list the theme placed', async () => {
        root.insertAdjacentHTML('beforeend', `<ul hidden data-meili="active-values">
            <template data-meili="active-value-template"><li><button type="button" data-meili="active-value"></button></li></template>
        </ul>`)
        const [first, second] = contract.all('active-values')

        await applying(start().toggle('product_brand', 'acme'))

        assert.deepEqual(labels(first), ['Acme✕'])
        assert.deepEqual(labels(second), ['Acme'])
    })

    it('keeps the focus on the pill until the answer redraws the list', async () => {
        await applying(start().toggle('product_brand', 'acme').toggle('product_brand', 'globex'))
        const withdrawn = pill(0)
        withdrawn.focus()

        click(window, withdrawn)
        assert.equal(window.document.activeElement, withdrawn, 'nothing moved before the answer')

        await answered()
        assert.equal(window.document.activeElement, pill(0))
        assert.equal(pill(0).textContent, 'Globex✕')
    })

    it('hands it to the pill before when the last one goes', async () => {
        await applying(start().toggle('product_brand', 'acme').toggle('product_brand', 'globex'))
        pill(1).focus()

        click(window, pill(1))
        await answered()

        assert.equal(window.document.activeElement, pill(0))
    })

    it('keeps it inside the listing when no pill is left, never on the body', async () => {
        await applying(start().toggle('product_brand', 'acme'))
        pill(0).focus()

        click(window, pill(0))
        await answered()

        assert.equal(window.document.activeElement, root)
        assert.equal(root.getAttribute('tabindex'), '-1')
    })

    it('leaves alone a focus the visitor moved while the answer was on its way', async () => {
        await applying(start().toggle('product_brand', 'acme').toggle('product_brand', 'globex'))
        const elsewhere = root.querySelector('input[value="coats"]') as HTMLInputElement

        click(window, pill(0))
        elsewhere.focus()
        await answered()

        assert.equal(window.document.activeElement, elsewhere)
    })

    it('leaves the list alone when an answer does not change its pills', async () => {
        const listing = await applying(start().toggle('product_brand', 'acme'))
        const drawn = pill(0)

        listing.sortBy('price_asc')
        await answered()

        assert.equal(pill(0), drawn)
    })

    it('takes the range off a pill the server marked as the price, whatever parameter it names', async () => {
        const description = { ...base, state: served({ price: { min: 10, max: 50 } }) }
        list().hidden = false
        list().insertAdjacentHTML('afterbegin', '<li><button type="button" name="prix" value="" data-kind="price" data-meili="active-value">10 – 50✕</button></li>')
        const listing = start(description)

        click(window, pill(0))
        await answered()

        assert.equal(listing.state.price.isEmpty(), true)
    })

    it('does nothing with a pill that names no filter', async () => {
        const listing = await applying(start().toggle('product_brand', 'acme'))
        pill(0).setAttribute('name', 'colour')

        click(window, pill(0))
        await answered()
        await applying(listing.sortBy('price_asc'))

        assert.deepEqual(listing.state.selected('product_brand'), ['acme'])
        assert.equal(window.document.activeElement, window.document.body, 'no focus is taken on a later answer')
    })
})

/** ANIM-9: only a filter just taken on comes in; nothing leaves animated, nothing the server drew moves. */
describe('the entrance of an active value', () => {
    let window: TestWindow
    let root: HTMLElement
    let contract: Contract
    let entered: { label: string | null, keyframes: Keyframe[] }[]
    let reduced: boolean

    const start = (description: ListingDescription = base) => {
        const listing = new Listing(description, connection, { filterQueries: filterQueriesOf(description), client: new FakeClient(), history: new FakeHistory() })

        new ListingBinding(contract, listing, description).start()

        return listing
    }
    const list = () => contract.one('active-values') as HTMLElement
    const pill = (rank: number) => contract.all('active-value', list())[rank] as HTMLElement
    const answered = () => new Promise((resolve) => setTimeout(resolve, 0))

    beforeEach(() => {
        ({ window, root } = open(listingMarkup(), { styled: true }))
        contract = new Contract(root)
        entered = []
        reduced = false
        window.matchMedia = (() => ({ matches: reduced })) as unknown as typeof window.matchMedia
        window.HTMLElement.prototype.animate = function (this: HTMLElement, keyframes: Keyframe[]) {
            entered.push({ label: this.getAttribute('value'), keyframes })

            return {} as Animation
        }
    })

    /** Its length is `Entrance`'s to read: happy-dom does not inherit custom properties down to the pill. */
    it('fades and scales in a pill the answer adds', async () => {
        await start().toggle('product_brand', 'acme').apply()

        assert.deepEqual(entered, [{ label: 'acme', keyframes: [{ opacity: 0, transform: 'scale(0.95)' }, { opacity: 1, transform: 'none' }] }])
    })

    it('brings in the new pill only, not the ones the redraw rebuilds', async () => {
        const listing = await start().toggle('product_brand', 'acme').apply()
        entered = []

        await listing.toggle('product_cat', 'coats').toggle('product_brand', 'globex').apply()

        assert.deepEqual(entered.map(({ label }) => label), ['globex', 'coats'])
    })

    it('leaves a pill the server rendered still when the client redraws it', async () => {
        list().hidden = false
        list().insertAdjacentHTML('afterbegin', '<li><button type="button" name="brand" value="acme" data-kind="term" data-meili="active-value">Acme</button></li>')
        const listing = start({ ...base, state: served({ facets: { product_brand: ['acme'] } }) })

        await listing.toggle('product_brand', 'globex').apply()

        assert.deepEqual(entered.map(({ label }) => label), ['globex'])
    })

    it('plays nothing when a pill goes, and lets the focus land on the one that took its place', async () => {
        const listing = await start().toggle('product_brand', 'acme').toggle('product_brand', 'globex').apply()
        entered = []
        pill(0).focus()

        click(window, pill(0))
        await answered()

        assert.deepEqual(entered, [])
        assert.deepEqual(listing.state.selected('product_brand'), ['globex'])
        assert.ok(window.document.activeElement === pill(0), 'the focus is on the pill left')
    })

    it('keeps a price range redrawn with other bounds still', async () => {
        const listing = await start().priceBetween(10, 50).apply()
        entered = []

        await listing.priceBetween(20, 50).apply()

        assert.deepEqual(entered, [])
    })

    it('only fades in under reduced motion', async () => {
        reduced = true

        await start().toggle('product_brand', 'acme').apply()

        assert.deepEqual(entered[0]?.keyframes, [{ opacity: 0 }, { opacity: 1 }])
    })
})
