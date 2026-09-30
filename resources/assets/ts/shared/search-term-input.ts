/** A term the engine tokenises into nothing serves the whole index (`R-159`). */
const WORD = /[\p{L}\p{N}]/u

export interface SearchTermSettings {
    minChars: number
    delay: number
}

export interface TermListener {
    search(term: string): void
    clear(): void
}

/** Turns keystrokes into terms worth a search: long enough, holding a word, and typed at rest. */
export class SearchTermInput {
    #input: HTMLInputElement
    #settings: SearchTermSettings
    #timer: ReturnType<typeof setTimeout> | undefined
    #term = ''

    constructor(input: HTMLInputElement, settings: SearchTermSettings) {
        this.#input = input
        this.#settings = settings
    }

    /** `length` counts UTF-16 units, in which an emoji is two characters. */
    static isSearchable(term: string, minChars: number) {
        return [...term].length >= minChars && SearchTermInput.holdsAWord(term)
    }

    static holdsAWord(term: string) {
        return WORD.test(term)
    }

    show(term: string) {
        clearTimeout(this.#timer)
        this.#term = term
        this.#input.value = term
    }

    start(terms: TermListener) {
        this.#listen(terms)

        if (this.#input.value !== '') {
            this.#typed(terms)
        }

        return this
    }

    startFromServedTerm(terms: TermListener) {
        this.#term = this.#input.value.trim()
        this.#listen(terms)

        return this
    }

    #listen(terms: TermListener) {
        this.#input.addEventListener('input', () => this.#typed(terms))
    }

    #typed(terms: TermListener) {
        const term = this.#input.value.trim()

        if (term === this.#term) {
            return
        }

        clearTimeout(this.#timer)
        this.#term = term

        if (SearchTermInput.isSearchable(term, this.#settings.minChars)) {
            this.#timer = setTimeout(() => terms.search(term), this.#settings.delay)
        } else {
            terms.clear()
        }
    }
}
