export class SeeAllLink {
    #link: HTMLAnchorElement
    #archive: string
    #parameter: string

    constructor(link: HTMLAnchorElement, parameter: string) {
        this.#link = link
        this.#archive = link.getAttribute('href') ?? ''
        this.#parameter = parameter
    }

    pointAt(term: string) {
        const address = new URL(this.#archive, this.#link.baseURI)

        address.searchParams.set(this.#parameter, term)
        this.#link.href = address.href
    }

    pointAtArchive() {
        this.#link.href = this.#archive
    }
}
