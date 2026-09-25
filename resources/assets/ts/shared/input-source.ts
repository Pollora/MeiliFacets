/** Whether an interaction comes from the keyboard or from a pointer. */
export class InputSource {
    #document: Document
    #pointerDown = false

    constructor(document: Document) {
        this.#document = document
    }

    /** `detail` is 0 on a click the keyboard raised, and non-zero on a real one. */
    static isKeyboard(click: MouseEvent) {
        return click.detail === 0
    }

    start() {
        this.#document.addEventListener('pointerdown', () => {
            this.#pointerDown = true
        }, { capture: true })
        this.#document.addEventListener('keydown', () => {
            this.#pointerDown = false
        }, { capture: true })
        this.#document.addEventListener('click', () => {
            this.#pointerDown = false
        })

        return this
    }

    /** From a press until its click: a press moves the focus before its click lands. */
    isPointerDown() {
        return this.#pointerDown
    }
}
