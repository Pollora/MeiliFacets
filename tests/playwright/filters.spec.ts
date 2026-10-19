import { expect, test } from '@playwright/test'
import { Environment } from './environment.ts'
import { VisitedListing } from './visited-listing.ts'

const LISTING_PATH = Environment.required('MEILIFACETS_PLAYWRIGHT_LISTING')

const VARIANT_TAXONOMY = Environment.required('MEILIFACETS_PLAYWRIGHT_VARIANT_TAXONOMY')

const SORTS = { priceDescending: 'price_desc', newest: 'newest', onSale: 'on_sale' } as const

const NOTHING_MATCHES = 'zzqxwv'

test.describe('listing filters', () => {
    test('an active value removed from the pills, then « clear all », bring the listing back', async ({ page }) => {
        const listing = new VisitedListing(page)
        await listing.open(LISTING_PATH)
        const everything = await listing.total()
        const first = await listing.firstOfferedValue()
        await listing.check(first.input)
        await expect(listing.activeValues()).toHaveCount(1)

        await listing.removeActiveValue(listing.activeValues().first())
        await expect.poll(() => listing.total()).toBe(everything)

        await listing.check(first.input)
        await listing.resetAll()
        await expect.poll(() => listing.total()).toBe(everything)
        await expect(listing.activeValues()).toHaveCount(0)
        listing.expectHealthySearches()
    })

    test('the price range keeps only the products under its ceiling', async ({ page }) => {
        const listing = new VisitedListing(page)
        await listing.open(LISTING_PATH)
        const everything = await listing.total()

        const ceiling = await listing.narrowPriceToHalf()

        await expect.poll(() => listing.total()).toBeLessThan(everything)
        expect(Math.max(...await listing.cardPrices())).toBeLessThanOrEqual(ceiling)
        listing.expectHealthySearches()
    })

    test('a variant value narrows to the products offering it', async ({ page }) => {
        const listing = new VisitedListing(page)
        await listing.open(LISTING_PATH)
        const { input, count } = await listing.offeredValueOf(VARIANT_TAXONOMY)

        await listing.check(input)

        await expect.poll(() => listing.total()).toBe(count)
        listing.expectHealthySearches()
    })

    test('« show more » reveals the values past the first ones', async ({ page }) => {
        const listing = new VisitedListing(page)
        await listing.open(LISTING_PATH)
        await listing.revealFacetWithMore()

        const [before, after] = await listing.valuesShownBeforeAndAfterMore()

        expect(after).toBeGreaterThan(before)
    })

    test('the descending price sort orders the grid from the highest price', async ({ page }) => {
        const listing = new VisitedListing(page)
        await listing.open(LISTING_PATH)

        await listing.sortBy(SORTS.priceDescending)

        await expect.poll(() => listing.pricesDescend()).toBe(true)
        listing.expectHealthySearches()
    })

    test('the newest sort answers, and « on sale » keeps only discounted products', async ({ page }) => {
        const listing = new VisitedListing(page)
        await listing.open(LISTING_PATH)
        await listing.sortBy(SORTS.newest)
        await expect(listing.cards().first()).toBeVisible()

        await listing.sortBy(SORTS.onSale)

        await expect.poll(() => listing.cardsWithoutDiscount()).toBe(0)
        listing.expectHealthySearches()
    })

    test('the listing search narrows the results, and a term nothing matches shows the empty state', async ({ page }) => {
        const listing = new VisitedListing(page)
        await listing.open(LISTING_PATH)
        const everything = await listing.total()

        await listing.searchFor(Environment.required('MEILIFACETS_PLAYWRIGHT_TERM'))
        await expect.poll(() => listing.total()).toBeLessThan(everything)

        await listing.searchFor(NOTHING_MATCHES)
        await expect(listing.emptyState()).toBeVisible()
        listing.expectHealthySearches()
    })

    test('a category page lists its products', async ({ page }) => {
        const listing = new VisitedListing(page)

        await listing.open(Environment.required('MEILIFACETS_PLAYWRIGHT_CATEGORY'))

        expect(await listing.total()).toBeGreaterThan(0)
    })
})
