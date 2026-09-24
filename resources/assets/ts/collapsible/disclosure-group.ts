import { Contract } from '../shared/contract.ts'

const EXPANDED = 'aria-expanded'
const MODAL = '[aria-modal="true"]'
const ALIGNED_TO_END = 'data-align-end'

/** The APG disclosure pattern on every toggle of the listing. */
export class DisclosureGroup {
    #contract: Contract
    #document: Document

    constructor(contract: Contract) {
        this.#contract = contract
        this.#document = contract.root.ownerDocument
    }

    start() {
        const root = this.#contract.root

        root.addEventListener('click', (event) => this.#clicked(event))
        root.addEventListener('keydown', (event) => this.#pressed(event as KeyboardEvent))
        root.addEventListener('focusout', (event) => this.#left(event as FocusEvent))
        this.#document.addEventListener('click', (event) => this.#clickedAnywhere(event))

        return this
    }

    #clicked(event: Event) {
        const toggle = event.target instanceof Element ? event.target.closest(Contract.selector('toggle')) : null

        if (!(toggle instanceof HTMLElement)) {
            return
        }

        if (this.#isOpen(toggle)) {
            this.#close(toggle)

            return
        }

        this.#openFloating().forEach((other) => this.#close(other))
        this.#open(toggle)
    }

    #pressed(event: KeyboardEvent) {
        if (event.key !== 'Escape') {
            return
        }

        const toggle = this.#openFloating().find((open) => this.#holds(open, event.target))

        if (toggle !== undefined) {
            this.#close(toggle)
            toggle.focus()
        }
    }

    /** No `relatedTarget` is the window losing focus, or a node leaving the page: neither is the visitor moving on. */
    #left(event: FocusEvent) {
        const next = event.relatedTarget

        if (next instanceof Node) {
            this.#openFloating().filter((open) => !this.#holds(open, next)).forEach((open) => this.#close(open))
        }
    }

    /** The path, not `contains()`: the click may have replaced the node it landed on. */
    #clickedAnywhere(event: Event) {
        const path = event.composedPath()

        this.#openFloating().filter((open) => !this.#isOnPath(open, path)).forEach((open) => this.#close(open))
    }

    #isOnPath(toggle: HTMLElement, path: EventTarget[]) {
        const panel = this.#panelOf(toggle)

        return path.includes(toggle) || (panel !== null && path.includes(panel))
    }

    #open(toggle: HTMLElement) {
        const panel = this.#panelOf(toggle)

        if (panel === null) {
            return
        }

        toggle.setAttribute(EXPANDED, 'true')
        panel.hidden = false

        if (this.#floats(toggle)) {
            this.#align(panel)
        }
    }

    #close(toggle: HTMLElement) {
        toggle.setAttribute(EXPANDED, 'false')

        const panel = this.#panelOf(toggle)

        if (panel !== null) {
            panel.hidden = true
        }
    }

    /** Measured open and aligned on its start: where it would fall past the viewport, it hangs from its trigger's end. */
    #align(panel: HTMLElement) {
        panel.removeAttribute(ALIGNED_TO_END)

        if (panel.getBoundingClientRect().right > this.#document.documentElement.clientWidth) {
            panel.setAttribute(ALIGNED_TO_END, '')
        }
    }

    /** The one place that tells a dropdown from an accordion section: a modal drawer holds sections. */
    #floats(toggle: Element) {
        return toggle.closest(MODAL) === null
    }

    #openFloating() {
        return this.#contract.all('toggle')
            .filter((toggle): toggle is HTMLElement => toggle instanceof HTMLElement)
            .filter((toggle) => this.#isOpen(toggle) && this.#floats(toggle))
    }

    #isOpen(toggle: Element) {
        return toggle.getAttribute(EXPANDED) === 'true'
    }

    #holds(toggle: HTMLElement, node: EventTarget | null) {
        return node instanceof Node && (toggle.contains(node) || (this.#panelOf(toggle)?.contains(node) ?? false))
    }

    #panelOf(toggle: Element) {
        const panel = this.#document.getElementById(toggle.getAttribute('aria-controls') ?? '')

        return panel instanceof HTMLElement ? panel : null
    }
}
