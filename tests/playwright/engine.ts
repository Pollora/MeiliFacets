import type { Response } from '@playwright/test'

export class Engine {
    static readonly #MULTI_SEARCH = '/multi-search'

    static answersSearch(response: Response): boolean {
        return response.url().endsWith(Engine.#MULTI_SEARCH)
    }
}
