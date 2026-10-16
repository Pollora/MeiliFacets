import { LEAVING } from '../shared/attributes.ts'

import type { Contract } from '../shared/contract.ts'

const MESSAGES = ['search-empty', 'search-unavailable'] as const

/** The sections and messages in place at one moment, copies on their way out left aside, each section with its list of results. */
export class ResultsInPlace {
    static readonly NOTHING = new ResultsInPlace(new Map(), [])

    readonly messages: Element[]
    #lists: Map<Element, Element | null>
    #sectionOfList = new Map<Element, Element>()

    constructor(lists: Map<Element, Element | null>, messages: Element[]) {
        this.#lists = lists
        this.messages = messages
        this.#lists.forEach((list, section) => this.#claim(list, section))
    }

    static read(contract: Contract) {
        const sections = contract.all('search-section').filter((section) => !section.hasAttribute(LEAVING))
        const messages = MESSAGES.flatMap((hook) => contract.all(hook).filter((message) => !message.hasAttribute(LEAVING)))

        return new ResultsInPlace(new Map(sections.map((section) => [section, contract.one('search-results', section)])), messages)
    }

    get sections() {
        return [...this.#lists.keys()]
    }

    rowsOf(section: Element) {
        return [...(this.#lists.get(section)?.children ?? [])]
    }

    /** The section a card sits in; `null` for anything that is not a card. */
    sectionOf(node: Element) {
        return node.parentElement === null ? null : this.#sectionOfList.get(node.parentElement) ?? null
    }

    #claim(list: Element | null, section: Element) {
        if (list !== null && !this.#sectionOfList.has(list)) {
            this.#sectionOfList.set(list, section)
        }
    }
}
