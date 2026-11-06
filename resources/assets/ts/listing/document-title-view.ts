import type { ByPage } from '../shared/description.ts'
import type { PageWindow } from '../pagination/page-window.ts'

/** `PageMetadata::PAGE_PLACEHOLDER` and `PageMetadata::PAGE_COUNT_PLACEHOLDER` */
const PAGE_PLACEHOLDER = ':page'
const PAGE_COUNT_PLACEHOLDER = ':pages'

export class DocumentTitleView {
    #titles: ByPage | null
    #document: Document

    constructor(titles: ByPage | null, document: Document) {
        this.#titles = titles
        this.#document = document
    }

    /** `YoastPageWords::names()` */
    show(pageWindow: PageWindow) {
        if (this.#titles === null) {
            return
        }

        const isNamed = pageWindow.pages > 1 && pageWindow.asked > 1
        const pattern = isNamed ? this.#titles.paged : this.#titles.first

        this.#document.title = pattern
            .replaceAll(PAGE_COUNT_PLACEHOLDER, String(pageWindow.pages))
            .replaceAll(PAGE_PLACEHOLDER, String(pageWindow.asked))
    }
}
