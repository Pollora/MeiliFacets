import { ATTRIBUTE } from '../shared/contract.ts'

const NAMES = new Set(['href', 'src', 'srcset', 'sizes', 'alt', 'title', 'width', 'height', 'value', 'datetime'])
const URL_NAMES = new Set(['href', 'src'])
const URL_LIST_NAMES = new Set(['srcset'])
const DIMENSION_NAMES = new Set(['width', 'height'])
const PREFIXES = ['aria-', 'data-']
const NAME_PATTERN = /^[a-z][a-z0-9_-]*$/
const DIMENSION_PATTERN = /^[1-9][0-9]*$/
const SAFE_SCHEMES = new Set(['http', 'https'])
const SCHEME_PATTERN = /^([a-z][a-z0-9+.-]*):/i

const URL_LIST_PATTERN = /[\s,]+/

// ComponentAttributeBag trims every value it prints, with PHP's trim().
const TRIMMED_AROUND_VALUE = /^[ \t\n\r\0\v]+|[ \t\n\r\0\v]+$/g

// Browsers drop tabs and line breaks inside a URL, and C0 controls and spaces around it.
const IGNORED_IN_URL = /[\t\n\r]/g
// eslint-disable-next-line no-control-regex
const TRIMMED_AROUND_URL = /^[\u0000- ]+|[\u0000- ]+$/g

export class CardFieldAttribute {
    #name: string

    constructor(name: string) {
        this.#name = name
    }

    get isAllowed() {
        if (!NAME_PATTERN.test(this.#name) || this.#name.startsWith(ATTRIBUTE)) {
            return false
        }

        return NAMES.has(this.#name) || PREFIXES.some((prefix) => this.#name.startsWith(prefix))
    }

    static written(value: string) {
        return value.replace(TRIMMED_AROUND_VALUE, '')
    }

    /** An empty value is not written at all: `href=""` would link the current page. */
    accepts(value: string) {
        if (value === '') {
            return false
        }

        if (URL_NAMES.has(this.#name)) {
            return this.#isSafeUrl(value)
        }

        if (URL_LIST_NAMES.has(this.#name)) {
            return this.#isSafeUrlList(value)
        }

        return !DIMENSION_NAMES.has(this.#name) || DIMENSION_PATTERN.test(value)
    }

    #isSafeUrl(url: string) {
        const read = url.replace(IGNORED_IN_URL, '').replace(TRIMMED_AROUND_URL, '')
        const scheme = SCHEME_PATTERN.exec(read)?.[1]

        return scheme === undefined || SAFE_SCHEMES.has(scheme.toLowerCase())
    }

    #isSafeUrlList(list: string) {
        return list.split(URL_LIST_PATTERN).every((url) => url === '' || this.#isSafeUrl(url))
    }
}
