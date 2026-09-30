export interface BusyListener {
    searching(): void
    settled(): void
}

/** Counted, not flagged: an overtaken search ends while the one that overtook it is still out. */
export class PendingSearches {
    #listener: BusyListener
    #count = 0

    constructor(listener: BusyListener) {
        this.#listener = listener
    }

    start() {
        this.#count += 1

        if (this.#count === 1) {
            this.#listener.searching()
        }
    }

    finish() {
        this.#count -= 1

        if (this.#count === 0) {
            this.#listener.settled()
        }
    }
}
