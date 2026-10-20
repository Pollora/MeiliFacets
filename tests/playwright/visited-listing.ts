import { expect, type Locator, type Page, type Response } from '@playwright/test'
import { Engine } from './engine.ts'
import { Hook, Hooks } from './hooks.ts'

export class VisitedListing {
    readonly #page: Page

    readonly #searches: Response[] = []

    readonly #errors: string[] = []

    constructor(page: Page) {
        this.#page = page
        page.on('response', (response) => this.#recordSearch(response))
        page.on('pageerror', (error) => this.#errors.push(error.message))
    }

    async open(path: string): Promise<void> {
        const response = await this.#page.goto(path)

        expect(response?.status(), `${path} answers`).toBe(200)
        await expect(this.cards().first()).toBeVisible()
    }

    cards(): Locator {
        return this.#page.locator(Hooks.selector(Hook.card))
    }

    async total(): Promise<number> {
        return VisitedListing.#integerIn(await this.#page.locator(Hooks.selector(Hook.total)).first().innerText())
    }

    async firstCardUrl(): Promise<string | null> {
        return this.cards().first().locator(Hooks.selector(Hook.url)).first().getAttribute('href')
    }

    async firstOfferedValue(): Promise<{ input: Locator, count: number }> {
        return this.#offeredValueAmong(this.#page.locator(Hooks.selector(Hook.facetValue)))
    }

    async offeredValueOf(taxonomy: string): Promise<{ input: Locator, count: number }> {
        return this.#offeredValueAmong(this.#facet(taxonomy).locator(Hooks.selector(Hook.facetValue)))
    }

    /** The values a facet shows before « show more », then after. */
    async valuesShownBeforeAndAfterMore(): Promise<[number, number]> {
        const more = this.#page.locator(Hooks.selector(Hook.more)).filter({ visible: true }).first()
        const values = more.locator(Hooks.nearestAncestor(Hook.facet)).locator(Hooks.selector(Hook.facetValue))
        const before = await values.filter({ visible: true }).count()

        await more.click()

        return [before, await values.filter({ visible: true }).count()]
    }

    async revealFacetWithMore(): Promise<void> {
        for (const more of await this.#page.locator(Hooks.selector(Hook.more)).all()) {
            await this.#reveal(more.locator(Hooks.nearestAncestor(Hook.facet)).locator(Hooks.selector(Hook.facetValue)).first())

            if (await more.isVisible()) {
                return
            }
        }

        throw new Error('No facet offers « show more ».')
    }

    async check(input: Locator): Promise<void> {
        await this.#afterSearch(async () => (await this.#clickable(input)).click())
        await expect(input).toBeChecked()
    }

    async uncheck(input: Locator): Promise<void> {
        await this.#afterSearch(async () => (await this.#clickable(input)).click())
        await expect(input).not.toBeChecked()
    }

    async sortBy(value: string): Promise<void> {
        const choice = this.#page.locator(`${Hooks.selector(Hook.sortChoice)}[value="${value}"]`)

        await this.#reveal(choice)
        await this.#afterSearch(() => choice.check())
    }

    /** The ceiling set, in cents: half of the highest price the listing offers. */
    async narrowPriceToHalf(): Promise<number> {
        const max = this.#page.locator(Hooks.selector(Hook.priceMax)).first()

        await this.#reveal(max)
        const ceiling = Math.floor(Number(await max.getAttribute('max')) / 2)

        await this.#afterSearch(async () => {
            await max.fill(String(ceiling))
            await max.press('Enter')
        })

        return ceiling * 100
    }

    async searchFor(term: string): Promise<void> {
        const input = this.#page.locator(Hooks.selector(Hook.listingSearchInput)).first()

        await this.#afterSearch(async () => {
            await input.fill(term)
            await input.press('Enter')
        })
    }

    activeValues(): Locator {
        return this.#page.locator(Hooks.selector(Hook.activeValue)).filter({ visible: true })
    }

    async removeActiveValue(value: Locator): Promise<void> {
        await this.#afterSearch(() => this.#clickInView(value))
    }

    async resetAll(): Promise<void> {
        const reset = this.#page.locator(Hooks.selector(Hook.reset)).filter({ visible: true }).first()

        await this.#afterSearch(() => this.#clickInView(reset))
    }

    emptyState(): Locator {
        return this.#page.locator(Hooks.selector(Hook.empty))
    }

    async openDrawer(): Promise<void> {
        await this.#page.locator(Hooks.selector(Hook.drawerOpen)).click()
        await expect(this.#page.locator(Hooks.selector(Hook.apply))).toBeVisible()
    }

    async applyFilters(): Promise<void> {
        await this.#page.locator(Hooks.selector(Hook.apply)).click()
        await expect(this.#page.locator(Hooks.selector(Hook.apply))).toBeHidden()
    }

    async cardsWithoutDiscount(): Promise<number> {
        const prices = this.cards().locator(Hooks.selector(Hook.price))

        return prices.evaluateAll((nodes) => nodes.filter((node) => node.querySelector('del') === null).length)
    }

    async goToPage(number: number): Promise<void> {
        const button = this.#page.locator(`${Hooks.selector(Hook.page)}[value="${number}"]`).first()

        await this.#afterSearch(() => button.click())
        await expect(button).toHaveAttribute('aria-current', 'page')
    }

    /** The lowest price each card charges, in cents, whatever the shop's decimal separator. */
    async cardPrices(): Promise<number[]> {
        return (await this.#cardPriceRanges()).map((range) => Math.min(...range))
    }

    /** A range sorts by its low end going up and by its high end going down, as WooCommerce ranks it. */
    async pricesAscend(): Promise<boolean> {
        const prices = await this.cardPrices()

        return prices.every((price, index) => index === 0 || (prices[index - 1] ?? price) <= price)
    }

    async pricesDescend(): Promise<boolean> {
        const prices = (await this.#cardPriceRanges()).map((range) => Math.max(...range))

        return prices.every((price, index) => index === 0 || (prices[index - 1] ?? price) >= price)
    }

    expectHealthySearches(): void {
        expect(this.#errors, 'script errors').toEqual([])
        expect(this.#searches.length, 'searches sent by the browser').toBeGreaterThan(0)

        for (const search of this.#searches) {
            expect(search.status(), `${search.url()} status`).toBe(200)
        }

        const indexes = new Set(this.#searches.flatMap((search) => VisitedListing.#indexesOf(search)))

        expect(indexes.size, `indexes searched: ${[...indexes].join(', ')}`).toBe(1)
    }

    async #cardPriceRanges(): Promise<number[][]> {
        const prices = await this.cards().locator(Hooks.selector(Hook.price)).evaluateAll((nodes) =>
            // WooCommerce strikes the regular price of a product on sale in a <del>, before the price charged.
            nodes.map((node) => [...(node.querySelector('ins') ?? node).querySelectorAll('bdi')].map((bdi) => bdi.textContent)),
        )

        return prices.map((range) => range.map((price) => VisitedListing.#centsIn(price ?? '')))
    }

    /** A visitor clicks a value's label: a value drawn as a pill hides its box. */
    async #clickable(input: Locator): Promise<Locator> {
        const label = this.#page.locator(`#${await input.getAttribute('aria-labelledby')}`)

        await this.#reveal(label)

        return label
    }

    #facet(taxonomy: string): Locator {
        return this.#page.locator(`${Hooks.selector(Hook.facet)}[data-taxonomy="${taxonomy}"]`)
    }

    async #offeredValueAmong(values: Locator): Promise<{ input: Locator, count: number }> {
        for (const value of await values.all()) {
            await this.#reveal(value)
            const count = VisitedListing.#integerIn(await value.locator(Hooks.selector(Hook.count)).innerText())

            if (count > 0) {
                return { input: value.locator(Hooks.selector(Hook.input)), count }
            }
        }

        throw new Error('No facet value has results.')
    }

    /** A panel closing still shows its content: whether it is open is told by its opener, not by what is visible. */
    async #reveal(control: Locator): Promise<void> {
        const panel = control.locator(Hooks.nearestAncestor(Hook.panel))

        if (await panel.count() === 0) {
            await expect(control).toBeVisible()

            return
        }

        const opener = this.#page.locator(`[aria-controls="${await panel.getAttribute('id')}"]`).first()
        const moreFilters = this.#page.locator(Hooks.selector(Hook.drawerShowMore))

        // A filter past the desktop row waits behind « more filters ».
        if (await opener.isHidden() && await moreFilters.isVisible()) {
            await moreFilters.click()
        }

        if (await opener.getAttribute('aria-expanded') !== 'true') {
            await opener.click()
        }

        await expect(opener).toHaveAttribute('aria-expanded', 'true')
        await expect(control).toBeVisible()
    }

    /** Scrolled to the top edge, a control can sit under a theme's sticky header: a visitor brings it into the middle. */
    async #clickInView(control: Locator): Promise<void> {
        await control.evaluate((element) => element.scrollIntoView({ block: 'center' }))
        await control.click()
    }

    async #afterSearch(action: () => Promise<void>): Promise<void> {
        const answered = this.#page.waitForResponse((response) => Engine.answersSearch(response))

        await action()
        await answered
    }

    #recordSearch(response: Response): void {
        if (Engine.answersSearch(response) && response.request().method() === 'POST') {
            this.#searches.push(response)
        }
    }

    static #indexesOf(search: Response): string[] {
        const body = search.request().postDataJSON() as { queries?: { indexUid?: string }[] } | null

        return (body?.queries ?? []).map((query) => query.indexUid ?? '')
    }

    static #integerIn(text: string): number {
        return Number(VisitedListing.#digitsIn(text).replace(/\D/g, ''))
    }

    /** A price ends with two decimals after a comma or a point; any other separator groups thousands. */
    static #centsIn(text: string): number {
        const digits = VisitedListing.#digitsIn(text)
        const decimals = /[.,](\d{2})$/.exec(digits)
        const units = (decimals === null ? digits : digits.slice(0, -3)).replace(/\D/g, '')

        return Number(units) * 100 + Number(decimals?.[1] ?? 0)
    }

    static #digitsIn(text: string): string {
        const match = /\d[\d\s.,]*\d|\d/.exec(text)

        if (match === null) {
            throw new Error(`No number in "${text}".`)
        }

        return match[0]
    }
}
