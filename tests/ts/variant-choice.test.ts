import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { describe, it } from 'node:test'

import { VariantChoice } from '../../resources/assets/ts/results/variant-choice.ts'
import { CardView } from '../../resources/assets/ts/results/card-view.ts'
import { Range } from '../../resources/assets/ts/shared/range.ts'

interface VariantCase {
    case: string
    card: Record<string, unknown>
    selected: Record<string, string[]>
    price: { min: number | null, max: number | null }
    expected: Record<string, unknown>
}

/** The server half is `tests/Unit/VariantChoiceTest.php`: same cases, same rule. */
const { cases } = JSON.parse(readFileSync(new URL('../card-variant-cases.json', import.meta.url), 'utf8')) as { cases: VariantCase[] }

describe('VariantChoice', () => {
    for (const { case: name, card, selected, price, expected } of cases) {
        it(name, () => {
            assert.deepEqual(new VariantChoice(selected, new Range(price.min, price.max)).shown(card), expected)
        })
    }

    it('shows a hit through the variants it is given, and as projected when none is given', () => {
        const hit = {
            ID: 125,
            card: {
                volume: '400ml, 15ml',
                variants: [
                    { facets: { pa_volume: ['400ml'] }, price: 39, fields: { volume: '400ml' } },
                    { facets: { pa_volume: ['15ml'] }, price: 26, fields: { volume: '15ml' } },
                ],
            },
        }

        assert.deepEqual(CardView.fieldsOf(hit, new VariantChoice({ pa_volume: ['400ml'] })), { id: 125, volume: '400ml' })
        assert.deepEqual(CardView.fieldsOf(hit), { id: 125, volume: '400ml, 15ml' })
    })
})
