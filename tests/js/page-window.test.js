import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { describe, it } from 'node:test'

import { PageWindow } from '../../resources/assets/js/page-window.js'

/** The same table `tests/Unit/PaginationTest.php` reads: one mirror, one set of answers. */
const cases = JSON.parse(readFileSync(new URL('../pagination-cases.json', import.meta.url), 'utf8'))

describe('PageWindow', () => {
    for (const expected of cases) {
        it(expected.case, () => {
            const pages = new PageWindow(
                expected.current, expected.perPage, expected.total, expected.reachable, expected.slotCount
            )

            assert.equal(pages.pages, expected.pages, 'pages')
            assert.equal(pages.current, expected.resolvedCurrent, 'current')
            assert.equal(pages.hasPages, expected.hasPages, 'hasPages')
            assert.equal(pages.hasPrevious, expected.hasPrevious, 'hasPrevious')
            assert.equal(pages.hasNext, expected.hasNext, 'hasNext')
            assert.equal(pages.previous, expected.previous, 'previous')
            assert.equal(pages.next, expected.next, 'next')
            assert.deepEqual(pages.slots(), expected.slots, 'slots')
        })
    }

    it('always offers as many slots as it was given', () => {
        for (const total of [0, 12, 20, 5000]) {
            assert.equal(new PageWindow(1, 16, total, 1_000_000, 5).slots().length, 5)
        }
    })

    it('holds the window against the last page', () => {
        assert.deepEqual(new PageWindow(313, 16, 5000, 1_000_000, 7).slots(), [307, 308, 309, 310, 311, 312, 313])
    })
})
