import { InputSource } from './input-source.ts'

/** The disclosures a light dismissal watches, and how each closes. */
export interface Dismissible {
    /** The toggles whose panel is open and dismissed lightly. */
    open(): HTMLElement[]
    panelOf(toggle: HTMLElement): HTMLElement | null
    /** Escape, or the focus moving on: nothing plays. */
    closeInstantly(toggle: HTMLElement): void
    /** A click elsewhere on the page. */
    close(toggle: HTMLElement): void
}

/**
 * How a non-modal disclosure is left: Escape closes it and hands the focus back
 * to its toggle, a click outside it or the focus moving past it closes it.
 */
export class LightDismiss {
    #root: Element
    #document: Document
    #input: InputSource
    #disclosures: Dismissible

    constructor(root: Element, disclosures: Dismissible) {
        this.#root = root
        this.#document = root.ownerDocument
        this.#input = new InputSource(this.#document)
        this.#disclosures = disclosures
    }

    start() {
        this.#root.addEventListener('keydown', (event) => this.#pressed(event as KeyboardEvent))
        this.#root.addEventListener('focusout', (event) => this.#left(event as FocusEvent))
        this.#input.start()
        this.#document.addEventListener('click', (event) => this.#clickedAnywhere(event))

        return this
    }

    /** A search field clears itself on an Escape nobody consumed. */
    #pressed(event: KeyboardEvent) {
        if (event.key !== 'Escape' || event.defaultPrevented) {
            return
        }

        const toggle = this.#disclosures.open().find((open) => this.#holds(open, event.target))

        if (toggle !== undefined) {
            event.preventDefault()
            this.#disclosures.closeInstantly(toggle)
            toggle.focus()
        }
    }

    /**
     * No `relatedTarget` is the window losing focus, or a node leaving the page: neither is the visitor moving on.
     * A press moves the focus before its click: the click decides, with its own motion.
     */
    #left(event: FocusEvent) {
        const next = event.relatedTarget

        if (next instanceof Node && !this.#input.isPointerDown()) {
            this.#disclosures.open()
                .filter((open) => !this.#holds(open, next))
                .forEach((open) => this.#disclosures.closeInstantly(open))
        }
    }

    /** The path, not `contains()`: the click may have replaced the node it landed on. */
    #clickedAnywhere(event: Event) {
        const path = event.composedPath()

        this.#disclosures.open()
            .filter((open) => !this.#isOnPath(open, path))
            .forEach((open) => this.#disclosures.close(open))
    }

    #isOnPath(toggle: HTMLElement, path: EventTarget[]) {
        const panel = this.#disclosures.panelOf(toggle)

        return path.includes(toggle) || (panel !== null && path.includes(panel))
    }

    #holds(toggle: HTMLElement, node: EventTarget | null) {
        return node instanceof Node && (toggle.contains(node) || (this.#disclosures.panelOf(toggle)?.contains(node) ?? false))
    }
}
