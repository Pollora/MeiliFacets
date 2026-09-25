const ATTRIBUTE = 'data-meili'
const VERSION_ATTRIBUTE = 'data-meili-contract'
const SCROLL_ATTRIBUTE = 'data-meili-scroll'
const VERSION = 1

interface Rule {
    host: string | null
    hooks: string[]
    whenHolding?: string
}

/**
 * A rule holds inside a host that is present. An absent host is markup the
 * theme chose not to render, or data that produced none — not a breach.
 */
const RULES: Rule[] = [
    { host: null, hooks: ['results', 'card-template', 'empty'] },
    { host: 'card-template', hooks: ['card', 'url', 'image', 'title', 'price'] },
    { host: 'facet-value', hooks: ['input'] },
    { host: 'facet', hooks: ['more'], whenHolding: 'facet-value' },
    { host: 'facet', hooks: ['panel'], whenHolding: 'toggle' },
    { host: 'pagination', hooks: ['page', 'previous', 'next'] },
    { host: 'sort', hooks: ['sort-trigger', 'sort-list', 'sort-option'] },
    { host: 'sort-choices', hooks: ['sort-choice'] },
    { host: 'sort-choices', hooks: ['panel'], whenHolding: 'toggle' },
    { host: 'price-range', hooks: ['price-track', 'price-handle'] },
    { host: 'active-values', hooks: ['active-value-template'] },
    { host: 'active-value-template', hooks: ['active-value'] },
    { host: 'drawer', hooks: ['drawer-title', 'drawer-close'] },
]

/** Mirrors the Hook enum: the two lists diverging in silence is what VERSION guards against. */
export class Contract {
    #root: Element

    constructor(root: Element) {
        this.#root = root
    }

    get root() {
        return this.#root
    }

    static selector(hook: string) {
        return `[${ATTRIBUTE}="${hook}"]`
    }

    /**
     * `ListingBinding` attaches per root, so a hook outside every root renders,
     * styles and ticks while doing nothing at all.
     */
    static orphans(document: Document, roots: Element[]): string[] {
        const loose = [...document.querySelectorAll(`[${ATTRIBUTE}]`)]
            .filter((node) => roots.every((root) => !root.contains(node)))

        return [...new Set(loose
            .filter((node) => !loose.some((other) => other !== node && other.contains(node)))
            .flatMap((node) => node.getAttribute(ATTRIBUTE) ?? []))]
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

        return RULES.flatMap((rule) => this.#breachesOf(rule))
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
