/** A count that describes its control. The twin of `View\Badge`: hidden and emptied at zero, since a hidden node still describes. */
export class Badge {
    #node: HTMLElement

    constructor(node: HTMLElement) {
        this.#node = node
    }

    appearsWith(count: number) {
        return this.#node.hidden && count > 0
    }

    show(count: number) {
        this.#node.textContent = count === 0 ? '' : String(count)
        this.#node.hidden = count === 0
    }
}
