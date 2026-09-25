import type { Contract } from '../shared/contract.ts'
import type { ListingState } from '../listing/listing-state.ts'

/** The engine's own order, the value the default choice carries. */
const DEFAULT_VALUE = ''

export class SortRadios {
    #contract: Contract
    #choose: (sort: string | null) => void

    constructor(contract: Contract, choose: (sort: string | null) => void) {
        this.#contract = contract
        this.#choose = choose
    }

    start() {
        this.#contract.root.addEventListener('change', (event) => this.#changed(event))

        return this
    }

    show(state: ListingState) {
        const current = state.sort ?? DEFAULT_VALUE

        for (const choice of this.#choices()) {
            choice.checked = choice.value === current
        }

        this.#showChosen(current)
    }

    showMatches(matches: Readonly<Record<string, number>>, state: ListingState) {
        const current = state.sort ?? DEFAULT_VALUE

        for (const choice of this.#choices()) {
            const row = choice.closest('li')

            if (row instanceof HTMLElement) {
                row.hidden = choice.value !== current && matches[choice.value] === 0
            }
        }
    }

    #changed(event: Event) {
        const choice = event.target

        if (choice instanceof HTMLInputElement && this.#choices().includes(choice) && choice.checked) {
            this.#choose(choice.value === DEFAULT_VALUE ? null : choice.value)
        }
    }

    #showChosen(current: string) {
        const chosen = this.#contract.one('sort-chosen')
        const label = this.#choices().find((choice) => choice.value === current)?.closest('label')?.textContent?.trim()

        if (chosen !== null && label !== undefined) {
            chosen.textContent = label
        }
    }

    #choices() {
        return this.#contract.all('sort-choice').filter((choice): choice is HTMLInputElement => choice instanceof HTMLInputElement)
    }
}
