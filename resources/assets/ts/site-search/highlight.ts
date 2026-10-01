import type { Contract } from '../shared/contract.ts'
import type { Card } from '../shared/description.ts'

const OPENING = ''
const CLOSING = ''
const TAGS = new RegExp(`([${OPENING}${CLOSING}])`)

const MARK = 'mark'

/** The engine highlights a whole object or nothing: the card's URL and price markup come back tagged too. */
const HIGHLIGHTED = ['title', 'summary'] as const

/** Writes what the engine highlighted as text and `<mark>` nodes: a string it returned is never parsed as HTML. */
export class Highlight {
    static readonly OPENING = OPENING
    static readonly CLOSING = CLOSING

    #contract: Contract

    constructor(contract: Contract) {
        this.#contract = contract
    }

    show(node: Element, formatted: Card | undefined) {
        for (const field of HIGHLIGHTED) {
            const target = this.#contract.one(field, node)
            const value = formatted?.[field]

            if (target !== null && typeof value === 'string' && value !== '') {
                target.replaceChildren(...this.#pieces(target.ownerDocument, value))
            }
        }
    }

    #pieces(document: Document, value: string) {
        let marked = false

        return value.split(TAGS).flatMap((piece): Node[] => {
            if (piece === OPENING || piece === CLOSING) {
                marked = piece === OPENING

                return []
            }

            return piece === '' ? [] : [this.#piece(document, piece, marked)]
        })
    }

    #piece(document: Document, text: string, marked: boolean) {
        if (!marked) {
            return document.createTextNode(text)
        }

        const mark = document.createElement(MARK)
        mark.textContent = text

        return mark
    }
}
