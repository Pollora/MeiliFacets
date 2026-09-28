export interface BusySpan {
    searching(): void
    settled(): void
}

/** Counted, not flagged: an overtaken search ends while the one that overtook it is still out. */
export class SearchesUnderWay {
    #span: BusySpan
    #count = 0

    constructor(span: BusySpan) {
        this.#span = span
    }

    leave() {
        this.#count += 1

        if (this.#count === 1) {
            this.#span.searching()
        }
    }

    end() {
        this.#count -= 1

        if (this.#count === 0) {
            this.#span.settled()
        }
    }
}
