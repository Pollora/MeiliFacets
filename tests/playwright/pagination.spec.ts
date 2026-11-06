import { expect, test } from '@playwright/test'
import { Environment } from './environment.ts'
import { VisitedListing } from './visited-listing.ts'

const LISTING_PATH = Environment.required('MEILIFACETS_PLAYWRIGHT_LISTING')

const PRICE_ASCENDING = 'price_asc'

const PAGED_PATH = /\/page\/\d+/

test.describe('pagination', () => {
    test('every page link leads to an address of its own, the current page marked', async ({ page }) => {
        const listing = new VisitedListing(page)
        await listing.open(LISTING_PATH)

        await expect(listing.pageLink(2)).toHaveAttribute('href', PAGED_PATH)
        await listing.goToPage(2)

        expect(new URL(page.url()).pathname).toMatch(PAGED_PATH)
        expect(await listing.cards().count()).toBeGreaterThan(0)
        await expect(listing.pastTheEnd()).toBeHidden()
        listing.expectHealthySearches()
    })

    test('a page address opened on its own is served whole, nothing searched in the browser', async ({ page }) => {
        const listing = new VisitedListing(page)
        await listing.open(LISTING_PATH)
        const href = await listing.pageLink(2).getAttribute('href')

        expect(href, 'page 2 has an address').not.toBeNull()
        const served = new VisitedListing(page)
        await served.open(href ?? '')

        await expect(served.pageLink(2)).toHaveAttribute('aria-current', 'page')
        expect(await served.cards().count()).toBeGreaterThan(0)
        served.expectServedWithoutSearching()
    })

    test('a sort chosen on the second page returns to the first, and every link keeps it', async ({ page }) => {
        const listing = new VisitedListing(page)
        await listing.open(LISTING_PATH)
        await listing.goToPage(2)

        await listing.sortBy(PRICE_ASCENDING)

        expect(new URL(page.url()).pathname).not.toMatch(PAGED_PATH)
        await expect(listing.pageLink(2)).toHaveAttribute('href', new RegExp(`/page/2\\?.*${PRICE_ASCENDING}`))

        await listing.goToPage(2)
        expect(await listing.cards().count()).toBeGreaterThan(0)
        await expect.poll(() => listing.pricesAscend()).toBe(true)
    })

    test('a filter set on the second page returns to the first, and the next page keeps it', async ({ page }) => {
        const listing = new VisitedListing(page)
        await listing.open(LISTING_PATH)
        await listing.goToPage(2)

        await listing.narrowPriceToHalf()
        const narrowed = await listing.total()

        expect(new URL(page.url()).pathname).not.toMatch(PAGED_PATH)
        test.skip(await listing.pageLink(2).isHidden(), 'The narrowed listing fits on one page.')

        await listing.goToPage(2)
        expect(await listing.total()).toBe(narrowed)
        expect(await listing.cards().count()).toBeGreaterThan(0)
        await expect(listing.pastTheEnd()).toBeHidden()
    })

    test('the back button returns from a later page to the one before', async ({ page }) => {
        const listing = new VisitedListing(page)
        await listing.open(LISTING_PATH)
        await listing.goToPage(2)
        const secondPage = await listing.firstCardUrl()

        await listing.goToPage(3)
        await page.goBack()

        await expect.poll(() => listing.firstCardUrl()).toBe(secondPage)
        await expect(listing.pageLink(2)).toHaveAttribute('aria-current', 'page')
    })
})
