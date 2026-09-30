export const ANNOUNCE_DELAY_MS = 1000

/** Announces in the live region once typing and answers have come to rest, never twice the same sentence. */
export class DebouncedAnnouncer {
    #region: HTMLElement | null
    #timer: ReturnType<typeof setTimeout> | undefined
    #pending: string | null = null
    #lastWritten = ''

    constructor(region: HTMLElement | null) {
        this.#region = region
    }

    announce(text: string) {
        this.#pending = text
        this.#restartDelay()
    }

    postpone() {
        if (this.#pending !== null) {
            this.#restartDelay()
        }
    }

    /** The region is hidden with the panel: what it held, or was about to, is no longer heard. */
    forget() {
        clearTimeout(this.#timer)
        this.#pending = null
        this.#lastWritten = ''

        if (this.#region !== null) {
            this.#region.textContent = ''
        }
    }

    #restartDelay() {
        clearTimeout(this.#timer)
        this.#timer = setTimeout(() => this.#write(), ANNOUNCE_DELAY_MS)
    }

    #write() {
        const text = this.#pending

        this.#pending = null

        if (this.#region !== null && this.#isNew(text)) {
            this.#region.textContent = text
            this.#lastWritten = text
        }
    }

    #isNew(text: string | null): text is string {
        return text !== null && text !== this.#lastWritten
    }
}
