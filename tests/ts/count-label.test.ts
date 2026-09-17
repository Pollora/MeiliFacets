import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { describe, it } from 'node:test'

import { CountLabel } from '../../resources/assets/ts/shared/count-label.ts'

interface PluralCase {
    locale: string
    count: number
    form: number
}

/** The same table `tests/Unit/CountLabelTest.php` reads: the browser must pick the form the server wrote. */
const cases = JSON.parse(readFileSync(new URL('../plural-cases.json', import.meta.url), 'utf8')) as PluralCase[]

describe('CountLabel', () => {
    const pattern = ':count result|:count results'

    for (const { locale, count, form } of cases) {
        it(`picks the form the rule of ${locale} names for ${count}`, () => {
            const expected = pattern.replaceAll(':count', String(count)).split('|')[form]

            assert.equal(new CountLabel(locale).of(pattern, count), expected)
        })
    }

    it('falls back to the one form a pattern carries', () => {
        assert.equal(new CountLabel('en').of(':count', 4), '4')
    })
})
