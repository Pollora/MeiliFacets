import { Contract } from '../shared/contract.ts'
import { CountLabel } from '../shared/count-label.ts'

import type { FacetDescription, ListingDescription } from '../shared/description.ts'
import type { FacetCounts } from './facet-counts.ts'
import type { ListingState } from '../listing/listing-state.ts'

interface Box {
    host: HTMLElement
    input: HTMLInputElement
    label: Element | null
    taxonomy: string
}

export class FacetsView {
    #contract: Contract
    #description: ListingDescription
    #countLabel: CountLabel
    #taxonomies: Map<string, string>

    #boxed: Box[] | null = null
    #grouped: Map<string, Box[]> | null = null
    #blocked: Map<string, Element> | null = null
    #buttoned: Map<string, Element> | null = null
    #unfolded = new Set<string>()
    #hasHits = new Map<Element, boolean>()

    constructor(contract: Contract, description: ListingDescription) {
        this.#contract = contract
        this.#description = description
        this.#countLabel = new CountLabel(description.locale)
        this.#taxonomies = new Map(Object.entries(description.params).map(([taxonomy, name]) => [name, taxonomy]))
    }

    taxonomyOf(input: HTMLInputElement): string | undefined {
        return this.#taxonomies.get(input.name)
    }

    showSelection(state: ListingState) {
        for (const { input, taxonomy } of this.#boxes()) {
            input.checked = state.selected(taxonomy).includes(input.value)
        }
    }

    showCounts(counts: FacetCounts) {
        for (const facet of this.#description.facets) {
            const distribution = counts.of(facet)

            for (const box of this.#boxesOf(facet.taxonomy)) {
                const hits = Object.hasOwn(distribution, box.input.value) ? distribution[box.input.value] ?? 0 : 0

                this.#showCount(box, hits)
                this.#hasHits.set(box.host, hits > 0)
            }
        }

        this.#showFolds()
    }

    toggleFold(button: Element) {
        const taxonomy = this.#taxonomyIn(button)

        if (taxonomy === undefined) {
            return
        }

        if (this.#unfolded.has(taxonomy)) {
            this.#unfolded.delete(taxonomy)
        } else {
            this.#unfolded.add(taxonomy)
        }

        this.#showFolds()
    }

    #showFolds() {
        this.#description.facets.forEach((facet) => this.#showFold(facet))
    }

    #showFold(facet: FacetDescription) {
        const expanded = this.#unfolded.has(facet.taxonomy)
        const values = this.#foldOf(facet)

        for (const { host, input, counted, folds } of values) {
            host.hidden = !input.checked && (!counted || (folds && !expanded))
        }

        this.#showBlock(facet.taxonomy, values.some(({ host }) => !host.hidden))
        this.#showFoldButton(facet.taxonomy, expanded, values.some(({ folds }) => folds))
    }

    #foldOf(facet: FacetDescription) {
        let rank = 0

        return this.#boxesOf(facet.taxonomy).map((box) => {
            const counted = this.#hasHits.get(box.host) ?? (facet.counts[box.input.value] ?? 0) > 0
            const folds = counted && !box.input.checked && rank >= facet.visible

            rank += counted ? 1 : 0

            return { ...box, counted, folds }
        })
    }

    #showBlock(taxonomy: string, readable: boolean) {
        const block = this.#blocks().get(taxonomy)

        if (block instanceof HTMLElement) {
            block.hidden = !readable
        }
    }

    #showFoldButton(taxonomy: string, expanded: boolean, foldable: boolean) {
        const button = this.#buttons().get(taxonomy)

        if (!(button instanceof HTMLElement)) {
            return
        }

        button.hidden = !foldable

        // Rewriting `aria-expanded` unchanged makes some screen readers announce the button again.
        if (button.getAttribute('aria-expanded') !== String(expanded)) {
            button.textContent = this.#description.foldLabels[expanded ? 'less' : 'more']
            button.setAttribute('aria-expanded', String(expanded))
        }
    }

    #boxesOf(taxonomy: string): Box[] {
        if (this.#grouped === null) {
            this.#grouped = new Map(this.#description.facets.map((facet) => [facet.taxonomy, [] as Box[]]))

            for (const box of this.#boxes()) {
                this.#grouped.get(box.taxonomy)?.push(box)
            }
        }

        return this.#grouped.get(taxonomy) ?? []
    }

    #blocks(): Map<string, Element> {
        return this.#blocked ??= new Map(this.#contract.all('facet').flatMap((block) => {
            const taxonomy = this.#taxonomyIn(block)

            return taxonomy === undefined ? [] : [[taxonomy, block]]
        }))
    }

    #buttons(): Map<string, Element> {
        return this.#buttoned ??= new Map([...this.#blocks()].flatMap(([taxonomy, block]) => {
            const button = this.#contract.one('more', block)

            return button === null ? [] : [[taxonomy, button]]
        }))
    }

    /** A block's taxonomy is no hook: the boxes it holds name it. */
    #taxonomyIn(node: Element | null): string | undefined {
        const block = node?.closest(Contract.selector('facet')) ?? null
        const input = block === null ? null : this.#contract.one('input', block)

        return input instanceof HTMLInputElement ? this.taxonomyOf(input) : undefined
    }

    #showCount({ label }: Box, hits: number) {
        if (label) {
            label.textContent = this.#countLabel.of(this.#description.countPattern, hits)
        }
    }

    #boxes(): Box[] {
        return this.#boxed ??= this.#contract.all('facet-value').flatMap((host) => this.#box(host))
    }

    #box(host: Element): Box[] {
        const input = this.#contract.one('input', host)

        if (!(host instanceof HTMLElement) || !(input instanceof HTMLInputElement)) {
            return []
        }

        const taxonomy = this.taxonomyOf(input)

        return taxonomy === undefined ? [] : [{ host, input, label: this.#contract.one('count', host), taxonomy }]
    }
}
