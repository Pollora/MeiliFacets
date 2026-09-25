const INERT = 'inert'

export class InertPage {
    #node: Element
    #sealed: Element[] = []

    constructor(node: Element) {
        this.#node = node
    }

    seal() {
        this.release()
        this.#sealed = this.#surroundings().filter((sibling) => !sibling.hasAttribute(INERT))
        this.#sealed.forEach((sibling) => sibling.setAttribute(INERT, ''))
    }

    release() {
        this.#sealed.forEach((sibling) => sibling.removeAttribute(INERT))
        this.#sealed = []
    }

    #surroundings() {
        const body = this.#node.ownerDocument.body
        const surroundings: Element[] = []

        for (let node: Element | null = this.#node; node !== null && node !== body; node = node.parentElement) {
            surroundings.push(...this.#siblingsOf(node))
        }

        return surroundings
    }

    #siblingsOf(node: Element) {
        return [...(node.parentElement?.children ?? [])].filter((sibling) => sibling !== node)
    }
}
