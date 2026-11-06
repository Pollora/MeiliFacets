import { expect, test } from '@playwright/test'
import { Environment } from './environment.ts'
import { VisitedListing } from './visited-listing.ts'
import { VisitedSiteSearch } from './visited-site-search.ts'

const LISTING_PATH = Environment.required('MEILIFACETS_PLAYWRIGHT_LISTING')

test('on a phone, a value checked in the drawer and applied narrows the listing', async ({ page }) => {
    const listing = new VisitedListing(page)
    await listing.open(LISTING_PATH)
    await listing.openDrawer()
    const { input, count } = await listing.firstOfferedValue()

    await listing.check(input)
    await listing.applyFilters()

    await expect.poll(() => listing.total()).toBe(count)
    listing.expectHealthySearches()
})

test('on a phone, on the second page, the drawer lists its filters and one applied narrows the listing', async ({ page }) => {
    const listing = new VisitedListing(page)
    await listing.open(LISTING_PATH)
    await listing.goToPage(2)
    await listing.openDrawer()

    expect(await listing.drawerFacets().count()).toBeGreaterThan(0)
    const { input, count } = await listing.firstOfferedValue()

    await listing.check(input)
    expect(await listing.drawerFacets().count()).toBeGreaterThan(0)
    await listing.applyFilters()

    await expect.poll(() => listing.total()).toBe(count)
    expect(new URL(page.url()).pathname).not.toMatch(/\/page\/\d+/)
    expect(await listing.cards().count()).toBeGreaterThan(0)
})

test('on a phone, the site search suggests products as the visitor types', async ({ page }) => {
    await page.goto('/')
    const search = new VisitedSiteSearch(page)

    expect(await search.answerStatusFor(Environment.required('MEILIFACETS_PLAYWRIGHT_TERM'))).toBe(200)

    await expect(search.results().first()).toBeVisible()
})
