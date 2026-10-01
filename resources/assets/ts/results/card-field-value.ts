export class CardFieldValue {
    #value: unknown

    constructor(value: unknown) {
        this.#value = value
    }

    get text() {
        if (typeof this.#value === 'string') {
            return this.#value
        }

        return typeof this.#value === 'number' && Number.isFinite(this.#value) ? String(this.#value) : ''
    }

    get isTrue() {
        const value = this.#value

        if (typeof value === 'boolean') {
            return value
        }

        if (typeof value === 'number') {
            return value !== 0 && !Number.isNaN(value)
        }

        if (typeof value === 'string' || Array.isArray(value)) {
            return value.length > 0
        }

        return typeof value === 'object' && value !== null && Object.keys(value).length > 0
    }
}
