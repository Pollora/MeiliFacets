/** The browser's copy of `Listing\Pagination`. */
export class PageWindow {
    #asked: number
    #total: number
    #current: number
    #pages: number

    constructor({ asked, perPage, total, reachable }: {
        asked: number
        perPage: number
        total: number
        /** hits the engine will serve, past which no page exists */
        reachable: number
    }) {
        this.#asked = asked
        this.#total = total
        this.#pages = perPage > 0 ? Math.ceil(Math.min(total, reachable) / perPage) : 1
        this.#current = Math.min(Math.max(asked, 1), Math.max(this.#pages, 1))
    }

    get current() {
        return this.#current
    }

    get pages() {
        return this.#pages
    }

    get isPastTheEnd() {
        return this.#total > 0 && this.#asked > this.#pages
    }

    get hasPages() {
        return this.#pages > 1
    }

    get hasPrevious() {
        return this.#current > 1
    }

    get hasNext() {
        return this.#current < this.#pages
    }

    get previous() {
        return Math.max(this.#current - 1, 1)
    }

    get next() {
        return Math.min(this.#current + 1, Math.max(this.#pages, 1))
    }

    slots(slotCount: number): (number | null)[] {
        const last = Math.max(this.#pages, 1)
        const width = Math.min(slotCount, last)
        const first = Math.min(Math.max(this.#current - Math.trunc(width / 2), 1), last - width + 1)
        const numbers = Array.from({ length: width }, (_, rank) => first + rank)

        return [...numbers, ...Array<null>(slotCount - width).fill(null)]
    }
}
