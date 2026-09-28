/** A term the engine tokenises into nothing serves the whole index (`R-159`). */
const WORD = /[\p{L}\p{N}]/u

export interface TypingSettings {
    minChars: number
    delay: number
}

export interface TypedTerms {
    search(term: string): void
    clear(): void
}

/** Turns keystrokes into terms worth a search: long enough, holding a word, and typed at rest. */
export class Typing {
    #input: HTMLInputElement
    #settings: TypingSettings
    #timer: ReturnType<typeof setTimeout> | undefined
    #term = ''

    constructor(input: HTMLInputElement, settings: TypingSettings) {
        this.#input = input
        this.#settings = settings
    }

    /** `length` counts UTF-16 units, in which an emoji is two characters. */
    static isSearchable(term: string, minChars: number) {
        return [...term].length >= minChars && WORD.test(term)
    }

    start(terms: TypedTerms) {
        this.#input.addEventListener('input', () => this.#typed(terms))

        if (this.#input.value !== '') {
            this.#typed(terms)
        }

        return this
    }

    #typed(terms: TypedTerms) {
        const term = this.#input.value.trim()

        if (term === this.#term) {
            return
        }

        clearTimeout(this.#timer)
        this.#term = term

        if (Typing.isSearchable(term, this.#settings.minChars)) {
            this.#timer = setTimeout(() => terms.search(term), this.#settings.delay)
        } else {
            terms.clear()
        }
    }
}
