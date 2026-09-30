import { CountLabel } from '../shared/count-label.ts'
import { DebouncedAnnouncer } from '../shared/debounced-announcer.ts'

import type { Contract } from '../shared/contract.ts'
import type { ListingDescription } from '../shared/description.ts'

export class TotalView {
    #pattern: string
    #countLabel: CountLabel
    #counters: HTMLElement[]
    #announcers: DebouncedAnnouncer[]

    constructor(contract: Contract, description: ListingDescription) {
        this.#pattern = description.totalPattern
        this.#countLabel = new CountLabel(description.locale)
        this.#counters = TotalView.#elementsOf(contract, 'total')

        const served = this.#counters[0]?.textContent ?? ''

        this.#announcers = TotalView.#elementsOf(contract, 'total-status')
            .map((region) => new DebouncedAnnouncer(region, served))
    }

    static #elementsOf(contract: Contract, hook: string) {
        return contract.all(hook).filter((element) => element instanceof HTMLElement)
    }

    show(total: number) {
        const label = this.#labelOf(total)

        this.#write(label)
        this.#announcers.forEach((announcer) => announcer.writeNow(label))
    }

    showWhileTyping(total: number) {
        const label = this.#labelOf(total)

        this.#write(label)
        this.#announcers.forEach((announcer) => announcer.announce(label))
    }

    #labelOf(total: number) {
        return this.#countLabel.of(this.#pattern, total)
    }

    #write(label: string) {
        this.#counters.forEach((counter) => {
            counter.textContent = label
        })
    }
}
