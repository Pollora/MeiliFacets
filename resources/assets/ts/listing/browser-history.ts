import type { StateDescription } from '../shared/description.ts'

type HistoryEntry = Partial<Record<string, StateDescription>>

type Restore = (state: StateDescription) => void

interface HistoryWindow {
    location: Pick<Location, 'pathname' | 'search' | 'reload'>
    history: Pick<History, 'state' | 'pushState' | 'replaceState'>
    addEventListener(type: 'popstate', listener: (event: PopStateEvent) => void): void
}

const KEY = 'meilifacets'

export class BrowserHistory {
    #window: HistoryWindow
    #restores: [string, Restore][] = []
    #shownAddress: string
    #shownEntry: HistoryEntry = {}

    constructor(target: HistoryWindow = globalThis) {
        this.#window = target
        this.#shownAddress = this.#currentAddress()
    }

    record(name: string, state: StateDescription) {
        this.#shownEntry = this.#showing(name, state)
        this.#window.history.replaceState(this.#besideOthers(this.#shownEntry), '')
    }

    // A filter replaces the current entry; only a page change adds one, so the
    // back button leaves the listing instead of undoing five ticked boxes.
    replace(name: string, state: StateDescription, url: string) {
        this.#shownEntry = this.#showing(name, state)
        this.#window.history.replaceState(this.#besideOthers(this.#shownEntry), '', url)
        this.#shownAddress = this.#currentAddress()
    }

    push(name: string, state: StateDescription, url: string) {
        this.#shownEntry = this.#showing(name, state)
        this.#window.history.pushState({ [KEY]: this.#shownEntry }, '', url)
        this.#shownAddress = this.#currentAddress()
    }

    onPopState(name: string, restore: Restore) {
        if (this.#restores.length === 0) {
            this.#window.addEventListener('popstate', (event) => this.#returned(event.state))
        }

        this.#restores.push([name, restore])
    }

    #returned(state: unknown) {
        const entry = this.#entryIn(state)

        if (entry === null) {
            this.#unrecorded()

            return
        }

        this.#shownEntry = entry
        this.#shownAddress = this.#currentAddress()

        for (const [name, restore] of this.#restores) {
            const shown = entry[name]

            if (shown !== undefined) {
                restore(shown)
            }
        }
    }

    /** An entry no listing wrote: an in-page anchor keeps the address, another script may not. */
    #unrecorded() {
        if (this.#currentAddress() !== this.#shownAddress) {
            this.#window.location.reload()

            return
        }

        this.#window.history.replaceState(this.#besideOthers(this.#shownEntry), '')
    }

    #showing(name: string, state: StateDescription): HistoryEntry {
        return { ...this.#shownEntry, [name]: state }
    }

    #besideOthers(entry: HistoryEntry) {
        const current: unknown = this.#window.history.state

        return { ...(this.#isRecord(current) ? current : {}), [KEY]: entry }
    }

    #entryIn(state: unknown): HistoryEntry | null {
        const entry = this.#isRecord(state) ? state[KEY] : null

        return this.#isRecord(entry) ? entry as HistoryEntry : null
    }

    #isRecord(value: unknown): value is Record<string, unknown> {
        return typeof value === 'object' && value !== null && !Array.isArray(value)
    }

    #currentAddress() {
        return this.#window.location.pathname + this.#window.location.search
    }
}
