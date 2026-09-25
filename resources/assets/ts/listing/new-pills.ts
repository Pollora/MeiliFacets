import { KIND } from './active-value-list.ts'

/** Which pills a redraw brought in, against those shown before it: a pill redrawn in place is not new. */
export class NewPills {
    #shown: Set<string>

    constructor(shown: Element[]) {
        this.#shown = new Set(shown.map((pill) => this.#filterOf(pill)))
    }

    among(pills: Element[]) {
        return pills.filter((pill): pill is HTMLElement => pill instanceof HTMLElement && !this.#shown.has(this.#filterOf(pill)))
    }

    /** Whatever its label says: a price range redrawn is the same pill. */
    #filterOf(pill: Element) {
        return [pill.getAttribute(KIND), pill.getAttribute('name'), pill.getAttribute('value')].join(' ')
    }
}
