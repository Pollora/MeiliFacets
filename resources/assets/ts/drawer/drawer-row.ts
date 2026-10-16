import { Contract } from '../shared/contract.ts'

const BEYOND_ROW = 'data-beyond-row'
const ROW_CLASS = 'meilifacetsDrawerRow'

interface Lifted {
    node: Element
    parent: Node
    next: Node | null
}

export class DrawerRow {
    #drawer: HTMLElement
    #contract: Contract
    #row: HTMLElement | null = null
    #lifted: Lifted[] = []

    constructor(drawer: HTMLElement, contract: Contract) {
        this.#drawer = drawer
        this.#contract = contract
    }

    lift() {
        const body = this.#contract.one('drawer-body', this.#drawer)

        if (this.#row !== null || body === null) {
            return
        }

        const row = this.#drawer.ownerDocument.createElement('div')

        row.className = `${this.#drawer.className} ${ROW_CLASS}`.trim()
        row.style.maxWidth = `${body.getBoundingClientRect().width}px`
        this.#lifted = this.#staying(body).map((node) => ({ node, parent: node.parentNode as Node, next: node.nextSibling }))
        row.append(...this.#lifted.map(({ node }) => node))
        this.#drawer.before(row)
        this.#row = row
    }

    /** In reverse: each node goes back before the sibling it had, which is back already. */
    putBack() {
        for (const { node, parent, next } of this.#lifted.toReversed()) {
            parent.insertBefore(node, next?.parentNode === parent ? next : null)
        }

        this.#row?.remove()
        this.#row = null
        this.#lifted = []
    }

    #staying(body: Element) {
        return [...body.children]
            .flatMap((child) => child.matches(Contract.selector('facets')) ? this.#blocksOf(child) : [child])
            .filter((node) => !node.hasAttribute(BEYOND_ROW))
    }

    #blocksOf(facets: Element) {
        return [...facets.children].filter((block) => block.matches(Contract.selector('facet')))
    }
}
