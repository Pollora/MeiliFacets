import { Contract } from './contract.ts'

import type { Connection } from './description.ts'
import type { RootComponent } from './root-component.ts'

/** Mirrors the ScriptModule enum: the id a bundle's data is published under, and the key of its descriptions. */
export interface ScriptModule {
    id: string
    roots: string
}

export interface BoundRoot<Description> {
    contract: Contract
    description: Description
    connection: Connection
}

export interface RootBinder<Description> {
    bind(root: BoundRoot<Description>): void
}

interface Published<Description> {
    connection: Connection
    descriptions: Partial<Record<string, Description>>
}

/**
 * Reads what the server left for one root component and hands each root to its
 * binder. A root whose markup no longer matches the contract is left alone:
 * the page it was served with is complete, and half a client is worse than none.
 */
export class PageRoots<Description> {
    #document: Document
    #component: RootComponent
    #module: ScriptModule

    constructor(document: Document, component: RootComponent, module: ScriptModule) {
        this.#document = document
        this.#component = component
        this.#module = module
    }

    start(binder: RootBinder<Description>) {
        const roots = [...this.#document.querySelectorAll(this.#component.selector)]
        const published = this.#published(roots)

        if (published === null) {
            return
        }

        this.#reportOrphans()
        roots.forEach((root) => this.#bind(root, published, binder))
    }

    #published(roots: Element[]): Published<Description> | null {
        const data = this.#document.getElementById(`wp-script-module-data-${this.#module.id}`)?.textContent

        if (data) {
            const published = JSON.parse(data) as { connection: Connection } & Record<string, unknown>
            const descriptions = (published[this.#module.roots] ?? {}) as Partial<Record<string, Description>>

            return { connection: published.connection, descriptions }
        }

        // The module id is written on both sides of the boundary: a mismatch
        // would otherwise leave a root served and inert, saying nothing.
        if (roots.length > 0) {
            console.error(`[meilifacets] no data was published under ${this.#module.id}.`)
        }

        return null
    }

    #reportOrphans() {
        const orphans = Contract.orphans(this.#document, this.#component)

        if (orphans.length > 0) {
            const move = `Move inside ${this.#component.tag} : ${orphans.join(', ')}.`

            console.error(`[meilifacets] the client binds inside ${this.#component.selector} only. ${move}`)
        }
    }

    #bind(root: Element, { connection, descriptions }: Published<Description>, binder: RootBinder<Description>) {
        const name = this.#component.nameOf(root)
        const description = descriptions[name]
        const contract = new Contract(root)

        if (!description) {
            console.error(`[meilifacets] the page describes no ${this.#component.name} named "${name}".`)

            return
        }

        const breaches = contract.breaches()

        if (breaches.length > 0) {
            console.error(`[meilifacets] the markup does not meet the contract: ${breaches.join(', ')}`)

            return
        }

        binder.bind({ contract, description, connection })
    }
}
