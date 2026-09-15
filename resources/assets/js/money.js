/**
 * Writes a price the way the shop writes it, from the format the server published:
 * a figure the client replaces must not read differently from one it did not.
 */
export class Money {
    #format
    #symbol
    #decimals
    #decimal
    #thousand

    /**
     * @param {{ format: string, symbol: string, decimals: number, decimal: string, thousand: string } | null} money
     */
    constructor(money) {
        this.#format = money?.format ?? '%2$s %1$s'
        this.#symbol = money?.symbol ?? ''
        this.#decimals = money?.decimals ?? 2
        this.#decimal = money?.decimal ?? '.'
        this.#thousand = money?.thousand ?? ''
    }

    /**
     * @param {number} amount
     */
    of(amount) {
        return this.#format
            .replace('%1$s', this.#symbol)
            .replace('%2$s', this.#amount(amount))
            .trim()
    }

    #amount(amount) {
        const [whole, fraction] = amount.toFixed(this.#decimals).split('.')
        const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, this.#thousand)

        return fraction === undefined ? grouped : `${grouped}${this.#decimal}${fraction}`
    }
}
