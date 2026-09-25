import { ActiveValueList } from './active-value-list.ts'
import { Contract } from '../shared/contract.ts'
import { FocusLanding } from './focus-landing.ts'

import type { ListingDescription } from '../shared/description.ts'
import type { ActiveValue } from './active-value-list.ts'
import type { Listing } from './listing.ts'
import type { ListingState } from './listing-state.ts'

type WithdrawSeam = Pick<Listing, 'withdraw' | 'withdrawPrice'>

/** The pills of every list the theme placed: one per filter held, each taking its own filter off. */
export class ActiveValuesView {
    #contract: Contract
    #description: ListingDescription
    #listing: WithdrawSeam
    #list: ActiveValueList
    #landing: FocusLanding
    #taxonomies: Map<string, string>
    #drawn: string | null = null
    #refocusing: { host: Element, rank: number } | null = null

    constructor(contract: Contract, description: ListingDescription, listing: WithdrawSeam) {
        this.#contract = contract
        this.#description = description
        this.#listing = listing
        this.#list = new ActiveValueList(description)
        this.#landing = new FocusLanding(contract.root)
        this.#taxonomies = new Map(Object.entries(description.params).map(([taxonomy, name]) => [name, taxonomy]))
    }

    start() {
        this.#contract.root.addEventListener('click', (event) => this.#clicked(event))

        return this
    }

    /** Given the state the engine answered for: a value ticked and not applied yet has no pill. */
    show(applied: ListingState) {
        const hosts = this.#contract.all('active-values')
        const values = hosts.length === 0 ? [] : this.#list.of(applied)
        const drawn = JSON.stringify(values)

        // Redrawn only when the pills change: a rebuilt list would take the focus away.
        if (hosts.length > 0 && drawn !== this.#drawn) {
            this.#drawn = drawn
            hosts.forEach((host) => this.#paint(host, values))
        }

        this.#refocus()
    }

    #clicked(event: Event) {
        const pill = event.target instanceof Element ? event.target.closest(Contract.selector('active-value')) : null
        const host = pill?.closest(Contract.selector('active-values')) ?? null

        if (pill === null || host === null) {
            return
        }

        const rank = this.#contract.all('active-value', host).indexOf(pill)

        // The answer comes later: a pill that withdrew nothing must not steal a focus then.
        if (this.#withdraw(pill)) {
            this.#refocusing = { host, rank }
        }
    }

    #withdraw(pill: Element) {
        const name = pill.getAttribute('name') ?? ''
        const value = pill.getAttribute('value') ?? ''
        const { minPrice, maxPrice } = this.#description.reserved
        const taxonomy = this.#taxonomies.get(name)

        if (name === minPrice || name === maxPrice) {
            this.#listing.withdrawPrice()

            return true
        }

        if (taxonomy !== undefined) {
            this.#listing.withdraw(taxonomy, value)

            return true
        }

        return false
    }

    /**
     * After the redraw the answer brought: the pill that took its place, else the one
     * before it, else the listing — never `<body>`. Focus the visitor moved meanwhile stays put.
     */
    #refocus() {
        const asked = this.#refocusing
        this.#refocusing = null

        if (asked === null || !this.#landing.isLost(asked.host)) {
            return
        }

        const pills = this.#contract.all('active-value', asked.host)
        const target = pills[asked.rank] ?? pills[asked.rank - 1] ?? this.#landing.root()

        if (target instanceof HTMLElement) {
            target.focus({ preventScroll: true })
        }
    }

    #paint(host: Element, values: ActiveValue[]) {
        const template = this.#contract.one('active-value-template', host)

        if (!(template instanceof HTMLTemplateElement) || !(host instanceof HTMLElement)) {
            return
        }

        this.#contract.all('active-value', host).forEach((pill) => this.#itemOf(pill, host).remove())
        template.before(...values.flatMap((value) => this.#pill(template, value)))
        host.hidden = values.length === 0
    }

    #pill(template: HTMLTemplateElement, value: ActiveValue) {
        const item = template.content.firstElementChild?.cloneNode(true)
        const pill = item instanceof Element && !item.matches(Contract.selector('active-value'))
            ? this.#contract.one('active-value', item)
            : item

        if (!(item instanceof Element) || !(pill instanceof Element)) {
            return []
        }

        pill.setAttribute('name', value.name)
        pill.setAttribute('value', value.value)
        pill.setAttribute('aria-label', value.action)
        pill.prepend(value.label)

        return [item]
    }

    /** What the template cloned around the pill, so the whole item leaves with it. */
    #itemOf(pill: Element, host: Element) {
        let item = pill

        while (item.parentElement !== null && item.parentElement !== host) {
            item = item.parentElement
        }

        return item
    }
}
