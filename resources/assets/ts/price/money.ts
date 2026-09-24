import type { MoneyFormat } from '../shared/description.ts'

const PLAIN: MoneyFormat = { format: '%2$s %1$s', symbol: '', decimals: 2, decimal: '.', thousand: '' }

/**
 * Writes a price the way the shop writes it, from the format the server published:
 * a figure the client replaces must not read differently from one it did not.
 */
export class Money {
    #money: MoneyFormat
    #number: Intl.NumberFormat
    #separators: Partial<Record<Intl.NumberFormatPartTypes, string>>

    constructor(money: MoneyFormat | null) {
        this.#money = { ...PLAIN, ...money }
        // The locale only cuts the figure into parts: the shop's own separators replace its.
        this.#number = new Intl.NumberFormat('en-US', {
            minimumFractionDigits: this.#money.decimals,
            maximumFractionDigits: this.#money.decimals,
        })
        this.#separators = { group: this.#money.thousand, decimal: this.#money.decimal }
    }

    of(amount: number) {
        return this.#money.format
            .replace('%1$s', this.#money.symbol)
            .replace('%2$s', this.#amount(amount))
            .trim()
    }

    #amount(amount: number) {
        return this.#number.formatToParts(amount).map((part) => this.#separators[part.type] ?? part.value).join('')
    }
}
