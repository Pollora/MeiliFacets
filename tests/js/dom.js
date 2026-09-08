import { readFileSync } from 'node:fs'

import { Window } from 'happy-dom'

const STYLESHEET = new URL('../../resources/assets/css/meilifacets.css', import.meta.url)

/** Read from the client, so an increment never sends anyone editing fixtures. */
const CONTRACT = /const VERSION = (\d+)/.exec(
    readFileSync(new URL('../../resources/assets/js/contract.js', import.meta.url), 'utf8')
)[1]

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
]

export const open = (markup, { styled = false } = {}) => {
    const window = new Window({ url: 'https://example.test/shop' })

    if (styled) {
        window.document.head.innerHTML = `<style>${readFileSync(STYLESHEET, 'utf8')}</style>`
    }

    for (const name of CLASSES) {
        globalThis[name] = window[name]
    }

    window.document.body.innerHTML = markup

    return { window, root: window.document.querySelector('[data-listing]') }
}

/** `detail` tells a real click from one the keyboard raised, and the client reads it. */
export const click = (window, node, detail = 1) => {
    node.dispatchEvent(new window.MouseEvent('click', { bubbles: true, detail }))
}

export const clickFromKeyboard = (window, node) => click(window, node, 0)

/** happy-dom has no layout, so the call is the only thing there is to observe. */
export const watchScrolling = (node) => {
    const scrolled = []

    node.scrollIntoView = (options) => scrolled.push(options)

    return scrolled
}

export const press = (window, node, key) => {
    node.dispatchEvent(new window.KeyboardEvent('keydown', { key, bubbles: true, cancelable: true }))
}

export const tick = (window, input) => {
    input.checked = !input.checked
    input.dispatchEvent(new window.Event('change', { bubbles: true }))
}

const facetValue = (name, value, label) => `
    <li class="meilifacetsFacetValue" data-meili="facet-value">
        <label>
            <input type="checkbox" name="${name}" value="${value}" data-meili="input">
            <span class="meilifacetsFacetName">${label}</span>
            <span class="meilifacetsFacetCount" data-meili="count">0 results</span>
        </label>
    </li>`

const sortOption = (value, label, selected) => `
    <li class="meilifacetsSortOption" id="sort-${value || 'default'}" role="option"
        data-value="${value}" aria-selected="${selected}" data-meili="sort-option">${label}</li>`

const pageButton = () => '<button type="button" value="" hidden data-meili="page"></button>'

/** Mirrors what the Blade components render, hooks and initial hidden states included. */
export const listingMarkup = ({ scroll = [] } = {}) => {
    const mark = (component) => (scroll.includes(component) ? 'data-meili-scroll' : '')

    return `
<div data-listing="products" data-meili-contract="${CONTRACT}">
    <div class="meilifacetsFacets" data-apply="submit" data-meili="facets" ${mark('facets')}>
        <fieldset data-taxonomy="product_brand" data-meili="facet">
            <ul>${facetValue('brand', 'acme', 'Acme')}${facetValue('brand', 'globex', 'Globex')}</ul>
            <button type="button" aria-expanded="false" hidden data-meili="more">Show more</button>
        </fieldset>
        <fieldset data-taxonomy="product_cat" data-meili="facet">
            <ul>${facetValue('categorie', 'coats', 'Coats')}</ul>
            <button type="button" aria-expanded="false" hidden data-meili="more">Show more</button>
        </fieldset>
        <button type="button" data-meili="apply">Apply filters</button>
    </div>

    <button type="button" class="meilifacetsReset" hidden data-meili="reset" ${mark('reset')}>Clear all</button>
    <span class="meilifacetsActiveFilters" hidden data-meili="active-filters">0 active filters</span>

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
    <p class="meilifacetsResultsEmpty" hidden data-meili="empty">Nothing found</p>
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
</div>`
}
