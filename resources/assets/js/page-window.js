/** The browser's copy of `Listing\Pagination`. */
export class PageWindow {
    #current

    #pages

    #slots

    /**
     * @param {number} current
     * @param {number} perPage
     * @param {number} total
     * @param {number} reachable  hits the engine will serve, past which no page exists
     * @param {number} slots       numbers the markup can hold, counted rather than assumed
     */
    constructor(current, perPage, total, reachable, slots) {
        this.#pages = perPage > 0 ? Math.ceil(Math.min(total, reachable) / perPage) : 1
        this.#current = Math.min(Math.max(current, 1), Math.max(this.#pages, 1))
        this.#slots = slots
    }

    get current() {
        return this.#current
    }

    get pages() {
        return this.#pages
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

    /**
     * @returns {(number | null)[]}
     */
    slots() {
        const last = Math.max(this.#pages, 1)
        const width = Math.min(this.#slots, last)
        const first = Math.min(Math.max(this.#current - Math.trunc(width / 2), 1), last - width + 1)
        const numbers = Array.from({ length: width }, (_, rank) => first + rank)

        return [...numbers, ...Array(this.#slots - width).fill(null)]
    }
}
