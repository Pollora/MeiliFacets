import { readFileSync } from 'node:fs'

import { Window as HappyWindow } from 'happy-dom'

import { Contract } from '../../resources/assets/ts/shared/contract.ts'

const STYLESHEET = new URL('../../resources/assets/css/meilifacets.css', import.meta.url)

/** Read from the client, so an increment never sends anyone editing fixtures. */
export const CONTRACT = Number(/const VERSION = (\d+)/.exec(
    readFileSync(new URL('../../resources/assets/ts/shared/contract.ts', import.meta.url), 'utf8')
)?.[1])

/** Events are left alone: the listing extends the runtime's own EventTarget, which refuses any other. */
const CLASSES = [
    'Element',
    'Node',
    'HTMLElement',
    'HTMLAnchorElement',
    'HTMLButtonElement',
    'HTMLImageElement',
    'HTMLInputElement',
    'HTMLTemplateElement',
    'SVGElement',
] as const

export type TestWindow = Window & typeof globalThis

export const find = <E extends Element = HTMLElement>(scope: ParentNode, selector: string) => {
    const node = scope.querySelector(selector)

    if (node === null) {
        throw new Error(`Nothing matches ${selector}.`)
    }

    return node as E
}

export const nth = (scope: ParentNode, selector: string, rank: number) => {
    const node = scope.querySelectorAll(selector)[rank]

    if (node === undefined) {
        throw new Error(`No match ${rank} for ${selector}.`)
    }

    return node as HTMLElement
}

export const closestHook = (node: Element, hook: string) => {
    const host = node.closest(Contract.selector(hook))

    if (host === null) {
        throw new Error(`No ${hook} holds this node.`)
    }

    return host as HTMLElement
}

export type StyleProperty = {
    [Key in keyof CSSStyleDeclaration]: CSSStyleDeclaration[Key] extends string ? Key : never
}[keyof CSSStyleDeclaration] & string

export const load = (markup: string, { styled = false } = {}) => {
    const window = new HappyWindow({ url: 'https://example.test/shop' }) as unknown as TestWindow

    if (styled) {
        window.document.head.innerHTML = `<style>${readFileSync(STYLESHEET, 'utf8')}</style>`
    }

    for (const name of CLASSES) {
        Object.assign(globalThis, { [name]: window[name] })
    }

    window.document.body.innerHTML = markup

    return window
}

export const open = (markup: string, options: { styled?: boolean } = {}) => {
    const window = load(markup, options)

    return { window, root: find(window.document, '[data-listing]') }
}

/** `detail` tells a real click from one the keyboard raised, and the client reads it. */
export const click = (window: TestWindow, node: Element, detail = 1) => {
    node.dispatchEvent(new window.MouseEvent('click', { bubbles: true, detail }))
}

export const clickFromKeyboard = (window: TestWindow, node: Element) => click(window, node, 0)

/** happy-dom has no layout, so the call is the only thing there is to observe. */
export const watchScrolling = (node: Element) => {
    const scrolled: (boolean | ScrollIntoViewOptions | undefined)[] = []

    node.scrollIntoView = (options) => scrolled.push(options)

    return scrolled
}

export const press = (window: TestWindow, node: Element, key: string) => {
    node.dispatchEvent(new window.KeyboardEvent('keydown', { key, bubbles: true, cancelable: true }))
}

export const release = (window: TestWindow, node: Element, key: string) => {
    node.dispatchEvent(new window.KeyboardEvent('keyup', { key, bubbles: true, cancelable: true }))
}

export const stroke = (window: TestWindow, node: Element, key: string) => {
    press(window, node, key)
    release(window, node, key)
}

export const tick = (window: TestWindow, input: HTMLInputElement) => {
    input.checked = !input.checked
    input.dispatchEvent(new window.Event('change', { bubbles: true }))
}

const facetValue = (name: string, value: string, label: string) => `
    <li class="meilifacetsFacetValue" data-meili="facet-value">
        <label>
            <input type="checkbox" name="${name}" value="${value}"
                   aria-describedby="count-${name}-${value}" data-meili="input">
            <span class="meilifacetsFacetName">${label}</span>
            <span class="meilifacetsFacetCount" id="count-${name}-${value}" data-meili="count">0 results</span>
        </label>
    </li>`

const facetBlock = (taxonomy: string, name: string, label: string, values: string) => `
        <fieldset class="meilifacetsFacet" data-taxonomy="${taxonomy}" data-meili="facet">
            <legend class="meilifacetsFacetLabel">${label}</legend>
            <div class="meilifacetsFacetPanel" id="panel-${name}">
                <div class="meilifacetsFacetPanelInner">
                    <ul class="meilifacetsFacetValues">${values}</ul>
                    <button type="button" class="meilifacetsFacetMore" aria-expanded="false"
                            hidden data-meili="more">
                        <span data-meili="more-label">Show more</span>
                        <span hidden data-meili="less-label">Show less</span>
                    </button>
                </div>
            </div>
        </fieldset>`

const sortOption = (value: string, label: string, selected: string) => `
    <li class="meilifacetsSortOption" id="sort-${value || 'default'}" role="option"
        data-value="${value}" aria-selected="${selected}" data-meili="sort-option">${label}</li>`

const pageButton = () => '<button type="button" value="" hidden data-meili="page"></button>'

/**
 * Mirrors the structure the Blade components render — hooks, classes and initial
 * hidden states. Identifiers are shortened: nothing here reads them.
 */
export const listingMarkup = ({ scroll = [] }: { scroll?: string[] } = {}) => {
    const mark = (component: string) => (scroll.includes(component) ? 'data-meili-scroll' : '')

    return `
<div data-listing="products" data-meili-contract="${CONTRACT}">
    <div class="meilifacetsFacets" data-apply="submit" data-meili="facets" ${mark('facets')}>
${facetBlock('product_brand', 'brand', 'Brand', `${facetValue('brand', 'acme', 'Acme')}${facetValue('brand', 'globex', 'Globex')}`)}
${facetBlock('product_cat', 'category', 'Category', facetValue('categorie', 'coats', 'Coats'))}
        <button type="button" data-meili="apply">Apply filters</button>
    </div>

    <button type="button" class="meilifacetsReset" hidden data-meili="reset" ${mark('reset')}>Clear all</button>
    <span class="meilifacetsActiveFilters" hidden data-meili="active-filters">0 active filters</span>
    <p class="meilifacetsTotal" aria-live="polite" aria-atomic="true" data-meili="total">0 items</p>

    <div class="meilifacetsSort" data-meili="sort" ${mark('sort')}>
        <label class="meilifacetsSortLabel" id="sort-label" for="sort-trigger">Sort by</label>
        <button type="button" class="meilifacetsSortTrigger" id="sort-trigger" role="combobox"
                aria-haspopup="listbox" aria-expanded="false" aria-controls="sort-list"
                aria-labelledby="sort-label sort-trigger"
                data-meili="sort-trigger">Relevance</button>
        <ul class="meilifacetsSortList" id="sort-list" role="listbox" hidden data-meili="sort-list">
            ${sortOption('', 'Relevance', 'true')}
            ${sortOption('price_asc', 'Price, low to high', 'false')}
            ${sortOption('newest', 'Newest first', 'false')}
        </ul>
    </div>

    <ul class="meilifacetsResults" data-meili="results"></ul>
    <p class="meilifacetsResultsEmpty" hidden data-meili="empty">
        <span data-meili="no-results">Nothing found</span>
        <span hidden data-meili="past-the-end">Nothing on this page</span>
    </p>
    <template data-meili="card-template">
        <li data-meili="card">
            <a href="" data-meili="url">
                <img alt="" data-meili="image">
                <span data-meili="title"></span>
            </a>
            <span data-meili="price"></span>
        </li>
    </template>

    <nav class="meilifacetsPagination" hidden data-meili="pagination" ${mark('pagination')}>
        <button type="button" value="1" hidden data-meili="previous">Previous</button>
        ${Array.from({ length: 7 }, pageButton).join('')}
        <button type="button" value="1" hidden data-meili="next">Next</button>
    </nav>
${facetBlock('pa_size', 'size', 'Size', facetValue('taille', 'm', 'Medium'))}
</div>`
}
