import { EXPANDED, INSTANT } from '../shared/attributes.ts'
import { Contract } from '../shared/contract.ts'
import { InputSource } from '../shared/input-source.ts'
import { PanelMotion } from './panel-motion.ts'

const ALIGNED_TO_END = 'data-align-end'

/** The APG disclosure pattern on every toggle of the listing. */
export class DisclosureGroup {
    #contract: Contract
    #document: Document
    #motion: PanelMotion
    #input: InputSource
    #closed: () => void

    /** `closed` runs once a panel is out of sight. */
    constructor(contract: Contract, closed: () => void = () => {}) {
        this.#contract = contract
        this.#document = contract.root.ownerDocument
        this.#motion = new PanelMotion(this.#document)
        this.#input = new InputSource(this.#document)
        this.#closed = closed
    }

    start() {
        const root = this.#contract.root

        root.addEventListener('click', (event) => this.#clicked(event))
        root.addEventListener('keydown', (event) => this.#pressed(event as KeyboardEvent))
        root.addEventListener('focusout', (event) => this.#left(event as FocusEvent))
        this.#input.start()
        this.#document.addEventListener('click', (event) => this.#clickedAnywhere(event))

        return this
    }

    /** For a container already out of sight, such as a drawer that has just left: no exit plays. */
    collapseWithin(container: Element) {
        const open = this.#toggles().filter((toggle) => container.contains(toggle) && this.#isOpen(toggle))

        open.forEach((toggle) => {
            toggle.setAttribute(EXPANDED, 'false')

            const panel = this.#panelOf(toggle)

            if (panel !== null) {
                this.#motion.drop(panel)
            }
        })

        if (open.length > 0) {
            this.#closed()
        }
    }

    #clicked(event: Event) {
        const toggle = event.target instanceof Element ? event.target.closest(Contract.selector('toggle')) : null

        if (!(toggle instanceof HTMLElement)) {
            return
        }

        if (this.#isOpen(toggle)) {
            this.#closeFromClick(toggle, event as MouseEvent)

            return
        }

        this.#openFromClick(toggle, event as MouseEvent)
    }

    #closeFromClick(toggle: HTMLElement, click: MouseEvent) {
        if (this.#isKeyboardOnFloatingPanel(toggle, click)) {
            this.#closeInstantly(toggle)

            return
        }

        this.#close(toggle)
    }

    #openFromClick(toggle: HTMLElement, click: MouseEvent) {
        const others = this.#openFloating()

        if (others.length > 0 || this.#isKeyboardOnFloatingPanel(toggle, click)) {
            others.forEach((other) => this.#closeInstantly(other))
            this.#openInstantly(toggle)

            return
        }

        this.#open(toggle)
    }

    #isKeyboardOnFloatingPanel(toggle: Element, click: MouseEvent) {
        return InputSource.isKeyboard(click) && this.#floats(toggle)
    }

    #pressed(event: KeyboardEvent) {
        if (event.key !== 'Escape' || event.defaultPrevented) {
            return
        }

        const toggle = this.#openFloating().find((open) => this.#holds(open, event.target))

        if (toggle !== undefined) {
            this.#closeInstantly(toggle)
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
            this.#openFloating().filter((open) => !this.#holds(open, next)).forEach((open) => this.#closeInstantly(open))
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

        if (this.#floats(toggle)) {
            this.#motion.pop(panel)
            this.#align(panel)

            return
        }

        this.#motion.show(panel)
    }

    #openInstantly(toggle: HTMLElement) {
        const panel = this.#panelOf(toggle)

        if (panel === null) {
            return
        }

        toggle.setAttribute(EXPANDED, 'true')
        this.#instantly(panel, () => panel.hidden = false)
        this.#align(panel)
    }

    #close(toggle: HTMLElement) {
        toggle.setAttribute(EXPANDED, 'false')

        const panel = this.#panelOf(toggle)

        if (panel !== null && !panel.hidden) {
            void this.#motion.hide(panel).then(this.#closed)
        }
    }

    #closeInstantly(toggle: HTMLElement) {
        toggle.setAttribute(EXPANDED, 'false')

        const panel = this.#panelOf(toggle)

        if (panel !== null && !panel.hidden) {
            this.#instantly(panel, () => this.#motion.drop(panel))
            this.#closed()
        }
    }

    /** `getAnimations()` flushes the style while the stylesheet cuts the transition: none starts once the attribute goes. */
    #instantly(panel: HTMLElement, change: () => void) {
        panel.setAttribute(INSTANT, '')
        change()
        panel.getAnimations()
        panel.removeAttribute(INSTANT)
    }

    /** Measured open and aligned on its start: where it would fall past the viewport, it hangs from its trigger's end. */
    #align(panel: HTMLElement) {
        panel.removeAttribute(ALIGNED_TO_END)

        if (panel.getBoundingClientRect().right > this.#document.documentElement.clientWidth) {
            panel.setAttribute(ALIGNED_TO_END, '')
        }
    }

    /** The stylesheet decides whether a panel floats: no second threshold here. */
    #floats(toggle: Element) {
        const panel = this.#panelOf(toggle)

        return panel !== null && this.#document.defaultView?.getComputedStyle(panel).position === 'absolute'
    }

    #openFloating() {
        return this.#toggles().filter((toggle) => this.#isOpen(toggle) && this.#floats(toggle))
    }

    #toggles() {
        return this.#contract.all('toggle').filter((toggle): toggle is HTMLElement => toggle instanceof HTMLElement)
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
