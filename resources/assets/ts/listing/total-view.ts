import { CountLabel } from '../shared/count-label.ts'

import type { Contract } from '../shared/contract.ts'
import type { ListingDescription } from '../shared/description.ts'

/** How many results the listing holds, on every counter the theme placed. */
export class TotalView {
    #contract: Contract
    #pattern: string
    #countLabel: CountLabel

    constructor(contract: Contract, description: ListingDescription) {
        this.#contract = contract
        this.#pattern = description.totalPattern
        this.#countLabel = new CountLabel(description.locale)
    }

    show(total: number) {
        const label = this.#countLabel.of(this.#pattern, total)

        for (const counter of this.#contract.all('total')) {
            // A live region speaks again when its text is rewritten, even to the same words.
            if (counter.textContent !== label) {
                counter.textContent = label
            }
        }
    }
}
