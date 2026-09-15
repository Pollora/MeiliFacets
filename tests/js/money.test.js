import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { Money } from '../../resources/assets/js/money.js'

const euro = { format: '%2$s %1$s', symbol: '€', decimals: 2, decimal: ',', thousand: ' ' }

describe('a price the client writes', () => {
    it('reads as the shop writes it', () => {
        assert.equal(new Money(euro).of(199), '199,00 €')
        assert.equal(new Money(euro).of(0), '0,00 €')
    })

    it('groups thousands the way the shop asked', () => {
        assert.equal(new Money(euro).of(1234.5), '1 234,50 €')
        assert.equal(new Money(euro).of(1234567), '1 234 567,00 €')
    })

    /** A dollar shop puts its symbol first and separates the other way round. */
    it('follows a format that puts the symbol first', () => {
        const dollar = { format: '%1$s%2$s', symbol: '$', decimals: 2, decimal: '.', thousand: ',' }

        assert.equal(new Money(dollar).of(1234.5), '$1,234.50')
    })

    it('honours a currency without decimals', () => {
        const yen = { format: '%1$s%2$s', symbol: '¥', decimals: 0, decimal: '.', thousand: ',' }

        assert.equal(new Money(yen).of(1234), '¥1,234')
    })

    /** Without WooCommerce the server publishes nothing, and a bare number is still a price. */
    it('falls back to a plain number when the shop published no format', () => {
        assert.equal(new Money(null).of(42), '42.00')
    })
})
