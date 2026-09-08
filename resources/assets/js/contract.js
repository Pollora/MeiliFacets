const ATTRIBUTE = 'data-meili'
const VERSION_ATTRIBUTE = 'data-meili-contract'
const SCROLL_ATTRIBUTE = 'data-meili-scroll'
const VERSION = 1

/**
 * A rule holds inside a host that is present. An absent host is markup the
 * theme chose not to render, or data that produced none — not a breach.
 */
const RULES = [
    { host: null, hooks: ['results', 'card-template', 'empty'] },
    { host: 'card-template', hooks: ['card', 'url', 'image', 'title', 'price'] },
    { host: 'facet-value', hooks: ['input'] },
    { host: 'facet', hooks: ['more'] },
    { host: 'pagination', hooks: ['page', 'previous', 'next'] },
    { host: 'sort', hooks: ['sort-trigger', 'sort-list', 'sort-option'] },
]

/** Mirrors the Hook enum: the two lists diverging in silence is what VERSION guards against. */
export class Contract {
    #root

    constructor(root) {
        this.#root = root
    }

    static selector(hook) {
        return `[${ATTRIBUTE}="${hook}"]`
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

    one(hook, within = this.#root) {
        return this.#scope(within).querySelector(Contract.selector(hook))
    }

    all(hook, within = this.#root) {
        return [...this.#scope(within).querySelectorAll(Contract.selector(hook))]
    }

    #breachesOf({ host, hooks }) {
        const scope = host === null ? this.#root : this.one(host)

        if (!scope) {
            return []
        }

        return hooks
            .filter((hook) => this.one(hook, scope) === null)
            .map((hook) => (host === null ? hook : `${host} > ${hook}`))
    }

    // A <template> keeps its markup in a fragment: querySelector on the tag finds nothing.
    #scope(node) {
        return node.content ?? node
    }
}
