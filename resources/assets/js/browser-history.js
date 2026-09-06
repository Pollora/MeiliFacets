/**
 * The browser objects the listing needs, behind one seam: `window` is not
 * available in a test runner, and a listing that reads it directly cannot be
 * exercised at all.
 */
export class BrowserHistory {
    #window

    constructor(target = globalThis) {
        this.#window = target
    }

    search() {
        return this.#window.location.search
    }

    path() {
        return this.#window.location.pathname
    }

    // A filter replaces the current entry; only a page change adds one, so the
    // back button leaves the listing instead of undoing five ticked boxes.
    replace(state, search) {
        this.#window.history.replaceState(state, '', this.path() + search)
    }

    push(state, search) {
        this.#window.history.pushState(state, '', this.path() + search)
    }

    onPopState(listener) {
        this.#window.addEventListener('popstate', listener)
    }
}
