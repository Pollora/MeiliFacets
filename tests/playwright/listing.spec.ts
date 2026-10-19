import { expect, test } from '@playwright/test'
import { Environment } from './environment.ts'
import { VisitedListing } from './visited-listing.ts'

const LISTING_PATH = Environment.required('MEILIFACETS_PLAYWRIGHT_LISTING')

const PRICE_ASCENDING = 'price_asc'

test.describe('product listing', () => {
    test('a value checked narrows the results to its count, unchecked brings them back', async ({ page }) => {
        const listing = new VisitedListing(page)
        await listing.open(LISTING_PATH)
        const everything = await listing.total()
        const { input, count } = await listing.firstOfferedValue()

        await listing.check(input)
        await expect.poll(() => listing.total()).toBe(count)
        expect(page.url()).not.toBe(new URL(LISTING_PATH, page.url()).href)

        await listing.uncheck(input)
        await expect.poll(() => listing.total()).toBe(everything)
        listing.expectHealthySearches()
    })

    test('a link to a filtered listing renders the same results on the server as in the browser', async ({ page }) => {
        const listing = new VisitedListing(page)
        await listing.open(LISTING_PATH)
        const { input, count } = await listing.firstOfferedValue()
        await listing.check(input)
        const filtered = page.url()

        await listing.open(filtered)

        await expect(input).toBeChecked()
        expect(await listing.total()).toBe(count)
    })

    test('the price sort orders the grid from the lowest price', async ({ page }) => {
        const listing = new VisitedListing(page)
        await listing.open(LISTING_PATH)

        await listing.sortBy(PRICE_ASCENDING)

        await expect.poll(() => listing.pricesAscend()).toBe(true)
        expect((await listing.cardPrices()).length).toBeGreaterThan(1)
        listing.expectHealthySearches()
    })

    test('the second page shows other products, and the back button returns to the first', async ({ page }) => {
        const listing = new VisitedListing(page)
        await listing.open(LISTING_PATH)
        const firstOfPageOne = await listing.firstCardUrl()

        await listing.goToPage(2)
        await expect.poll(() => listing.firstCardUrl()).not.toBe(firstOfPageOne)

        await page.goBack()
        await expect.poll(() => listing.firstCardUrl()).toBe(firstOfPageOne)
        listing.expectHealthySearches()
    })
})
