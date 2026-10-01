import { RootComponent } from './root-component.ts'

import type { Rule } from './root-component.ts'

export const ATTRIBUTE = 'data-meili'
const VERSION_ATTRIBUTE = 'data-meili-contract'
const SCROLL_ATTRIBUTE = 'data-meili-scroll'
const VERSION = 1

/** Mirrors the Hook enum: the two lists diverging in silence is what VERSION guards against. */
export class Contract {
    #root: Element
    #component: RootComponent | null

    constructor(root: Element) {
        this.#root = root
        this.#component = RootComponent.of(root)
    }

    get root() {
        return this.#root
    }

    get window() {
        const view = this.#root.ownerDocument.defaultView

        if (view === null) {
            throw new Error('[meilifacets] the contract root is not in a window.')
        }

        return view
    }

    static selector(hook: string) {
        return `[${ATTRIBUTE}="${hook}"]`
    }

    /**
     * A client binds per root, so a hook outside every root renders, styles and
     * ticks while doing nothing at all. Each client names only the hooks it owns.
     */
    static orphans(document: Document, owner: RootComponent): string[] {
        const roots = [...document.querySelectorAll(RootComponent.anySelector)]
        const loose = [...document.querySelectorAll(`[${ATTRIBUTE}]`)]
            .filter((node) => roots.every((root) => !root.contains(node)))

        return [...new Set(loose
            .filter((node) => !loose.some((other) => other !== node && other.contains(node)))
            .flatMap((node) => node.getAttribute(ATTRIBUTE) ?? [])
            .filter((hook) => RootComponent.ownerOf(hook) === owner))]
    }

    /** Opt-in, per component: a control the theme did not mark leaves the page where it is. */
    static get SCROLL() {
        return `[${SCROLL_ATTRIBUTE}]`
    }

    // Empty means the client may start; anything else names what to fix.
    breaches() {
        const version = this.#root.getAttribute(VERSION_ATTRIBUTE)

        if (version !== String(VERSION)) {
            return [`contract ${version ?? 'absent'}, expected ${VERSION}`]
        }

        if (this.#component === null) {
            return [`no root: expected one of ${RootComponent.anySelector}`]
        }

        return this.#component.rules.flatMap((rule) => this.#breachesOf(rule))
    }

    one(hook: string, within: Element = this.#root) {
        return this.#scope(within).querySelector(Contract.selector(hook))
    }

    all(hook: string, within: Element = this.#root) {
        return [...this.#scope(within).querySelectorAll(Contract.selector(hook))]
    }

    // Every host, not just the first: a second one breaching would go unseen.
    #breachesOf({ host, hooks, whenHolding }: Rule) {
        const scopes = host === null ? [this.#root] : this.all(host)

        return scopes
            .filter((scope) => whenHolding === undefined || this.one(whenHolding, scope) !== null)
            .flatMap((scope) =>
                hooks
                    .filter((hook) => this.one(hook, scope) === null)
                    .map((hook) => (host === null ? hook : `${host} > ${hook}`))
            )
    }

    // A <template> keeps its markup in a fragment: querySelector on the tag finds nothing.
    #scope(node: Element): ParentNode {
        return node instanceof HTMLTemplateElement ? node.content : node
    }
}
