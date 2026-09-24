/** Letters typed further apart start a new search rather than extending the last. */
const TYPING_PAUSE = 500

export class TypeAhead {
    #typed = ''
    #typedAt = 0

    static accepts(event: KeyboardEvent) {
        return event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey
    }

    find(key: string, labels: string[], active: number): number | undefined {
        const now = Date.now()

        this.#typed = now - this.#typedAt > TYPING_PAUSE ? key : this.#typed + key
        this.#typedAt = now

        // One letter walks the matches; a longer run stays on the name being spelled.
        const from = this.#typed.length === 1 ? active + 1 : active
        const typed = this.#typed.toLowerCase()

        return labels
            .map((_, rank) => (from + rank) % labels.length)
            .find((rank) => labels[rank]?.startsWith(typed))
    }
}
