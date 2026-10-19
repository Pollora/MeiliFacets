import { expect, type Locator, type Page } from '@playwright/test'
import { Engine } from './engine.ts'
import { Hook, Hooks } from './hooks.ts'

export class VisitedSiteSearch {
    readonly #page: Page

    constructor(page: Page) {
        this.#page = page
    }

    async answerStatusFor(term: string): Promise<number> {
        await this.#openField()

        const answered = this.#page.waitForResponse((response) => Engine.answersSearch(response))
        await this.#page.locator(Hooks.selector(Hook.searchInput)).pressSequentially(term)

        return (await answered).status()
    }

    /** What a visitor sees when the engine cannot be reached from the browser. */
    async withEngineUnreachable(): Promise<void> {
        await this.#page.route((url) => url.pathname.endsWith('/multi-search'), (route) => route.abort())
    }

    async typeWithoutAnswer(term: string): Promise<void> {
        await this.#openField()
        await this.#page.locator(Hooks.selector(Hook.searchInput)).pressSequentially(term)
    }

    unavailable(): Locator {
        return this.#page.locator(Hooks.selector(Hook.searchUnavailable))
    }

    sections(): Locator {
        return this.#page.locator(Hooks.selector(Hook.searchSection)).filter({ visible: true })
    }

    async seeAll(): Promise<void> {
        await this.#page.locator(Hooks.selector(Hook.searchSeeAll)).filter({ visible: true }).first().click()
    }

    async openFirstResultWithTheKeyboard(): Promise<string | null> {
        const input = this.#page.locator(Hooks.selector(Hook.searchInput))
        const first = this.results().first().locator('a').first()
        const href = await first.getAttribute('href')

        await input.press('ArrowDown')
        await expect(input).toHaveAttribute('aria-activedescendant', /.+/)
        await input.press('Enter')

        return href
    }

    async #openField(): Promise<void> {
        const toggle = this.#page.locator(Hooks.selector(Hook.searchToggle))

        if (await toggle.isVisible()) {
            await toggle.click()
        }
    }

    results(): Locator {
        return this.#page.locator(`${Hooks.selector(Hook.searchResults)} ${Hooks.selector(Hook.card)}`).filter({ visible: true })
    }
}
