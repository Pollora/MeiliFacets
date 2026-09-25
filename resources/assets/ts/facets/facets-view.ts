import { Contract } from '../shared/contract.ts'
import { CountLabel } from '../shared/count-label.ts'

import type { FacetDescription, ListingDescription } from '../shared/description.ts'
import type { FacetCounts } from './facet-counts.ts'
import type { ListingState } from '../listing/listing-state.ts'
import type { SelectionHolder } from '../collapsible/selected-count-view.ts'

const UNAVAILABLE = 'aria-disabled'

interface Box {
    host: HTMLElement
    input: HTMLInputElement
    label: Element | null
    taxonomy: string
}

export class FacetsView implements SelectionHolder {
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

    /** A box kept in place without results is announced unavailable, and a tick on it is refused. */
    refuses(input: HTMLInputElement) {
        return input.checked && input.getAttribute(UNAVAILABLE) === 'true'
    }

    taxonomyOf(input: HTMLInputElement): string | undefined {
        return this.#taxonomies.get(input.name)
    }

    heldIn(block: Element, state: ListingState): number | undefined {
        const taxonomy = this.#taxonomyIn(block)

        return taxonomy === undefined ? undefined : state.selected(taxonomy).length
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

    /** Once a panel has closed, the values it held in place may go. */
    refold() {
        this.#showFolds()
    }

    #showFolds() {
        this.#description.facets.forEach((facet) => this.#showFold(facet))
    }

    #showFold(facet: FacetDescription) {
        const expanded = this.#unfolded.has(facet.taxonomy)
        const values = this.#foldOf(facet)

        for (const { host, input, placed, pinned, folds } of values) {
            host.hidden = !input.checked && (!placed || (folds && !expanded))
            this.#showReach(input, pinned)
        }

        this.#showBlock(facet.taxonomy, values.some(({ host }) => !host.hidden))
        this.#showFoldButton(facet.taxonomy, expanded, values.some(({ folds }) => folds))
    }

    /** A value without results leaves its place, unless it is on screen in an open panel: there it stays, out of reach, rank included. */
    #foldOf(facet: FacetDescription) {
        const panelOpen = this.#isPanelOpen(facet.taxonomy)
        let rank = 0

        return this.#boxesOf(facet.taxonomy).map((box) => {
            const counted = this.#hasResults(facet, box)
            const pinned = !counted && panelOpen && this.#isOnScreenAndFree(box)
            const placed = counted || pinned
            const folds = placed && !box.input.checked && rank >= facet.visible

            rank += placed ? 1 : 0

            return { ...box, placed, pinned, folds }
        })
    }

    #showReach(input: HTMLInputElement, outOfReach: boolean) {
        if (input.hasAttribute(UNAVAILABLE) === outOfReach) {
            return
        }

        if (outOfReach) {
            input.setAttribute(UNAVAILABLE, 'true')
        } else {
            input.removeAttribute(UNAVAILABLE)
        }
    }

    #hasResults(facet: FacetDescription, { host, input }: Box) {
        return this.#hasHits.get(host) ?? (facet.counts[input.value] ?? 0) > 0
    }

    #isOnScreenAndFree({ host, input }: Box) {
        return !input.checked && !host.hidden
    }

    #isPanelOpen(taxonomy: string) {
        const block = this.#blocks().get(taxonomy)
        const toggle = block === undefined ? null : this.#contract.one('toggle', block)

        return toggle?.getAttribute('aria-expanded') === 'true'
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
            this.#showFoldLabel(button, expanded)
            button.setAttribute('aria-expanded', String(expanded))
        }
    }

    #showFoldLabel(button: HTMLElement, expanded: boolean) {
        const more = this.#contract.one('more-label', button)
        const less = this.#contract.one('less-label', button)

        if (more instanceof HTMLElement && less instanceof HTMLElement) {
            more.hidden = expanded
            less.hidden = !expanded
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
