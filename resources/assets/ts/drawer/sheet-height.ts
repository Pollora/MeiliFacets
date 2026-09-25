export class SheetHeight {
    #sheet: HTMLElement
    #observer: ResizeObserver | null = null

    constructor(sheet: HTMLElement) {
        this.#sheet = sheet
    }

    follow() {
        const view = this.#sheet.ownerDocument.defaultView

        if (view === null || !('ResizeObserver' in view)) {
            return
        }

        this.#observer ??= new view.ResizeObserver(() => this.#measure())
        this.#watched().forEach((node) => this.#observer?.observe(node))
        this.#measure()
    }

    stop() {
        this.#observer?.disconnect()
    }

    release() {
        this.stop()
        this.#sheet.style.removeProperty('height')
    }

    /** The parts are laid out top to bottom: their sum is the height asked, the body counted by its content. */
    #measure() {
        const asked = this.#inFlow().reduce((sum, part) => sum + Math.max(part.offsetHeight, part.scrollHeight), 0)

        this.#sheet.style.height = `${asked}px`
    }

    /** A part changes size when what it holds does: its children are watched too. */
    #watched() {
        return this.#inFlow().flatMap((part) => [part, ...part.children])
    }

    #inFlow() {
        const view = this.#sheet.ownerDocument.defaultView

        return [...this.#sheet.children]
            .filter((part): part is HTMLElement => part instanceof HTMLElement)
            .filter((part) => view?.getComputedStyle(part).position !== 'absolute')
    }
}
