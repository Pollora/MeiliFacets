import type { ByPage } from '../shared/description.ts'
import type { PageWindow } from '../pagination/page-window.ts'

/** `PageMetadata::PAGE_PLACEHOLDER` */
const PAGE_PLACEHOLDER = ':page'

const CANONICAL = 'link[rel="canonical"]'

export class CanonicalLinkView {
    #canonicals: ByPage | null
    #link: HTMLLinkElement | null

    constructor(canonicals: ByPage | null, document: Document) {
        this.#canonicals = canonicals
        this.#link = document.head.querySelector<HTMLLinkElement>(CANONICAL)
    }

    show(pageWindow: PageWindow) {
        if (this.#canonicals === null || this.#link === null) {
            return
        }

        this.#link.href = pageWindow.asked > 1
            ? this.#canonicals.paged.replaceAll(PAGE_PLACEHOLDER, String(pageWindow.asked))
            : this.#canonicals.first
    }
}
