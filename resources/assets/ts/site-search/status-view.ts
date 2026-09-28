import { BUSY } from '../shared/attributes.ts'
import { CountLabel } from '../shared/count-label.ts'

import type { Contract } from '../shared/contract.ts'
import type { SiteSearchDescription } from '../shared/description.ts'
import type { SectionCount } from './section-view.ts'

const PLACEHOLDERS = /:heading|:count/g

/**
 * The panel's one live region, written once an answer is on screen, never per
 * keystroke; and the two messages that stand in for the sections.
 */
export class StatusView {
    #contract: Contract
    #description: SiteSearchDescription
    #countLabel: CountLabel
    #sections: Intl.ListFormat

    constructor(contract: Contract, description: SiteSearchDescription) {
        this.#contract = contract
        this.#description = description
        this.#countLabel = new CountLabel(description.locale)
        this.#sections = new Intl.ListFormat(description.locale, { type: 'conjunction' })
    }

    searching() {
        this.#element('search-panel')?.setAttribute(BUSY, 'true')
    }

    settled() {
        this.#element('search-panel')?.removeAttribute(BUSY)
    }

    answered(counts: SectionCount[]) {
        const found = counts.filter(({ total }) => total > 0)

        this.#reveal(found.length === 0 ? 'search-empty' : null)
        this.#announce(found.length === 0 ? this.#textOf('search-empty') : this.#sections.format(found.map((count) => this.#phrase(count))))
    }

    failed() {
        this.#reveal('search-unavailable')
        this.#announce(this.#textOf('search-unavailable'))
    }

    cleared() {
        this.#reveal(null)
        this.#announce('')
    }

    #phrase({ heading, total }: SectionCount) {
        const values: Record<string, string> = {
            ':heading': heading,
            ':count': this.#countLabel.of(this.#description.countPattern, total),
        }

        return this.#description.sectionPattern.replace(PLACEHOLDERS, (placeholder) => values[placeholder] ?? placeholder)
    }

    #reveal(message: 'search-empty' | 'search-unavailable' | null) {
        for (const hook of ['search-empty', 'search-unavailable'] as const) {
            const element = this.#element(hook)

            if (element !== null) {
                element.hidden = hook !== message
            }
        }
    }

    #announce(text: string) {
        const status = this.#element('search-status')

        if (status !== null) {
            status.textContent = text
        }
    }

    #textOf(hook: string) {
        return (this.#element(hook)?.textContent ?? '').replace(/\s+/g, ' ').trim()
    }

    #element(hook: string) {
        const element = this.#contract.one(hook)

        return element instanceof HTMLElement ? element : null
    }
}
