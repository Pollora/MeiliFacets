import type { ListboxPosition } from './listbox-keys.ts'

/** The options a keyboard move walks through: those the last answer left visible. */
export class ShownOptions {
    #options: HTMLElement[]
    #shown: HTMLElement[]

    constructor(options: HTMLElement[]) {
        this.#options = options
        this.#shown = options.filter((option) => !option.hidden)
    }

    position(active: number, selected: number): ListboxPosition {
        return { active: this.#rankOf(active), selected: this.#rankOf(selected), last: this.#shown.length - 1 }
    }

    indexAt(rank: number) {
        const option = this.#shown[Math.min(Math.max(rank, 0), this.#shown.length - 1)]

        return option === undefined ? undefined : this.#options.indexOf(option)
    }

    labels(labelOf: (option: HTMLElement) => string) {
        return this.#shown.map(labelOf)
    }

    #rankOf(index: number) {
        const option = this.#options[index]

        return option === undefined ? 0 : Math.max(this.#shown.indexOf(option), 0)
    }
}
