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
    sortFilter?: { field: string, value: string }
}

/** The server half is `tests/Unit/VariantChoiceTest.php`: same cases, same rule. */
const { variantTaxonomies, cases } = JSON.parse(readFileSync(new URL('../card-variant-cases.json', import.meta.url), 'utf8')) as { variantTaxonomies: string[], cases: VariantCase[] }

describe('VariantChoice', () => {
    for (const { case: name, card, selected, price, expected, sortFilter } of cases) {
        it(name, () => {
            const choice = new VariantChoice({ selected, price: new Range(price.min, price.max), variantTaxonomies, sortFilter })

            assert.deepEqual(choice.shown(card), expected)
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

        const choice = new VariantChoice({ selected: { pa_volume: ['400ml'] }, variantTaxonomies: ['pa_volume'] })

        assert.deepEqual(CardView.fieldsOf(hit, choice), { id: 125, volume: '400ml' })
        assert.deepEqual(CardView.fieldsOf(hit), { id: 125, volume: '400ml, 15ml' })
    })
})
