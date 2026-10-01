import { CardFieldAttribute } from './card-field-attribute.ts'
import { CardFieldValue } from './card-field-value.ts'

import type { Card } from '../shared/description.ts'

const TEXT_BINDING = 'data-meili-text'
const ATTRIBUTE_BINDING = 'data-meili-attr'
const CLASS_BINDING = 'data-meili-class'
const CONDITION_BINDING = 'data-meili-if'
const PAIR_SEPARATOR = ':'
const LIST_SEPARATOR = ' '
const FALLBACK_SEPARATOR = '|'
const NEGATION = '!'
const FIELD_PATTERN = /^[A-Za-z0-9_]+$/

const SELECTOR = [TEXT_BINDING, ATTRIBUTE_BINDING, CLASS_BINDING, CONDITION_BINDING]
    .map((attribute) => `[${attribute}]`)
    .join(', ')

interface Pair {
    target: string
    fields: string[]
}

export class CardBinding {
    #card: Card

    constructor(card: Card) {
        this.#card = card
    }

    apply(node: Element) {
        const bound = [...node.querySelectorAll(SELECTOR)]
        const elements = node.matches(SELECTOR) ? [node, ...bound] : bound

        for (const element of elements) {
            this.#text(element)
            this.#attributes(element)
            this.#classes(element)
        }

        elements.filter((element) => this.#hasNothingToShow(element)).forEach((element) => element.remove())
    }

    #text(element: Element) {
        const field = CardBinding.#textField(element)

        if (field !== null) {
            element.textContent = this.#textOf([field])
        }
    }

    #hasNothingToShow(element: Element) {
        const hasEmptyText = CardBinding.#textField(element) !== null && element.textContent === ''

        return hasEmptyText || !this.#meetsCondition(element)
    }

    #attributes(element: Element) {
        for (const { target, fields } of CardBinding.#pairs(element.getAttribute(ATTRIBUTE_BINDING))) {
            const attribute = new CardFieldAttribute(target)

            if (!attribute.isAllowed) {
                continue
            }

            const value = CardFieldAttribute.written(this.#textOf(fields))

            if (attribute.accepts(value)) {
                element.setAttribute(target, value)
            } else {
                element.removeAttribute(target)
            }
        }
    }

    #classes(element: Element) {
        const pairs = CardBinding.#pairs(element.getAttribute(CLASS_BINDING))

        for (const { target, fields: [field, ...fallbacks] } of pairs) {
            if (field !== undefined && fallbacks.length === 0) {
                element.classList.toggle(target, this.#read(field).isTrue)
            }
        }
    }

    #meetsCondition(element: Element) {
        const condition = element.getAttribute(CONDITION_BINDING) ?? ''
        const isNegated = condition.startsWith(NEGATION)
        const field = isNegated ? condition.slice(NEGATION.length) : condition

        return !FIELD_PATTERN.test(field) || this.#read(field).isTrue !== isNegated
    }

    #textOf(fields: string[]) {
        return fields.map((field) => this.#read(field).text).find((text) => text !== '') ?? ''
    }

    #read(field: string) {
        return new CardFieldValue(Object.hasOwn(this.#card, field) ? this.#card[field] : undefined)
    }

    static #textField(element: Element) {
        const field = element.getAttribute(TEXT_BINDING)

        return field !== null && FIELD_PATTERN.test(field) ? field : null
    }

    /** A class may hold the separator itself (`md:hidden`): the fields are what follows the last one. */
    static #pairs(list: string | null): Pair[] {
        return (list ?? '').split(LIST_SEPARATOR).flatMap((pair) => {
            const at = pair.lastIndexOf(PAIR_SEPARATOR)
            const target = pair.slice(0, at)
            const fields = pair.slice(at + PAIR_SEPARATOR.length).split(FALLBACK_SEPARATOR)

            return at > 0 && fields.every((field) => FIELD_PATTERN.test(field)) ? [{ target, fields }] : []
        })
    }
}
