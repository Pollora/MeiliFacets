import { expect, test } from '@playwright/test'
import { Environment } from './environment.ts'
import { VisitedListing } from './visited-listing.ts'
import { VisitedSiteSearch } from './visited-site-search.ts'

const SEARCHED_TERM = Environment.required('MEILIFACETS_PLAYWRIGHT_TERM')

test.describe('site search', () => {
    test('it suggests products as the visitor types, in a section per type', async ({ page }) => {
        await page.goto('/')
        const search = new VisitedSiteSearch(page)

        expect(await search.answerStatusFor(SEARCHED_TERM)).toBe(200)

        await expect(search.results().first()).toBeVisible()
        expect(await search.sections().count()).toBeGreaterThan(0)
    })

    test('« see all » lands on a listing holding results', async ({ page }) => {
        await page.goto('/')
        const search = new VisitedSiteSearch(page)
        await search.answerStatusFor(SEARCHED_TERM)
        await expect(search.results().first()).toBeVisible()

        await search.seeAll()

        const listing = new VisitedListing(page)
        await expect(listing.cards().first()).toBeVisible()
        expect(await listing.total()).toBeGreaterThan(0)
    })

    test('the keyboard reaches a suggestion and opens it', async ({ page }) => {
        await page.goto('/')
        const search = new VisitedSiteSearch(page)
        await search.answerStatusFor(SEARCHED_TERM)
        await expect(search.results().first()).toBeVisible()

        const href = await search.openFirstResultWithTheKeyboard()

        await expect(page).toHaveURL(href ?? '')
    })

    test('an unreachable engine says the search is unavailable', async ({ page }) => {
        await page.goto('/')
        const search = new VisitedSiteSearch(page)
        await search.withEngineUnreachable()

        await search.typeWithoutAnswer(SEARCHED_TERM)

        await expect(search.unavailable()).toBeVisible()
    })
})
