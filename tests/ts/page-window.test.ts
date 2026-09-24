import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { describe, it } from 'node:test'

import { PageWindow } from '../../resources/assets/ts/pagination/page-window.ts'

interface PaginationCase {
    case: string
    asked: number
    perPage: number
    total: number
    reachable: number
    slotCount: number
    slots: (number | null)[]
    pages: number
    current: number
    isPastTheEnd: boolean
    hasPages: boolean
    hasPrevious: boolean
    hasNext: boolean
    previous: number
    next: number
}

/** The same table `tests/Unit/PaginationTest.php` reads: one mirror, one set of answers. */
const cases = JSON.parse(readFileSync(new URL('../pagination-cases.json', import.meta.url), 'utf8')) as PaginationCase[]

describe('PageWindow', () => {
    for (const expected of cases) {
        it(expected.case, () => {
            const pageWindow = new PageWindow({
                asked: expected.asked,
                perPage: expected.perPage,
                total: expected.total,
                reachable: expected.reachable,
            })

            assert.equal(pageWindow.pages, expected.pages, 'pages')
            assert.equal(pageWindow.current, expected.current, 'current')
            assert.equal(pageWindow.isPastTheEnd, expected.isPastTheEnd, 'isPastTheEnd')
            assert.equal(pageWindow.hasPages, expected.hasPages, 'hasPages')
            assert.equal(pageWindow.hasPrevious, expected.hasPrevious, 'hasPrevious')
            assert.equal(pageWindow.hasNext, expected.hasNext, 'hasNext')
            assert.equal(pageWindow.previous, expected.previous, 'previous')
            assert.equal(pageWindow.next, expected.next, 'next')
            assert.deepEqual(pageWindow.slots(expected.slotCount), expected.slots, 'slots')
        })
    }

    it('always offers as many slots as it was given', () => {
        for (const total of [0, 12, 20, 5000]) {
            assert.equal(new PageWindow({ asked: 1, perPage: 16, total, reachable: 1_000_000 }).slots(5).length, 5)
        }
    })

    it('holds the window against the last page', () => {
        assert.deepEqual(new PageWindow({ asked: 313, perPage: 16, total: 5000, reachable: 1_000_000 }).slots(7), [307, 308, 309, 310, 311, 312, 313])
    })
})
