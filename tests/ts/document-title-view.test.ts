import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { DocumentTitleView } from '../../resources/assets/ts/listing/document-title-view.ts'
import { PageWindow } from '../../resources/assets/ts/pagination/page-window.ts'

const titles = { first: 'Shop - Site', paged: 'Shop - Page :page of :pages - Site' }

/** 16 cards a page. */
const titleOn = (asked: number, total: number, given: typeof titles | null = titles) => {
    const document = { title: 'Served' } as Document

    new DocumentTitleView(given, document).show(new PageWindow({ asked, perPage: 16, total, reachable: 1000 }))

    return document.title
}

describe('DocumentTitleView', () => {
    it('writes the page and the page count of the window', () => {
        assert.equal(titleOn(2, 40), 'Shop - Page 2 of 3 - Site')
    })

    it('writes the first page without a page number', () => {
        assert.equal(titleOn(1, 40), 'Shop - Site')
    })

    it('names no page when a single one is left, as the server does', () => {
        assert.equal(titleOn(2, 12), 'Shop - Site')
    })

    it('numbers a page past the end as it was asked for', () => {
        assert.equal(titleOn(5, 40), 'Shop - Page 5 of 3 - Site')
    })

    it('leaves the title alone when the server could not tell the titles', () => {
        assert.equal(titleOn(2, 40, null), 'Served')
    })
})
