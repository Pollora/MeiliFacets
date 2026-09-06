export class SearchError extends Error {
    constructor(message, { status = null, code = null, type = null, link = null } = {}) {
        super(message)
        this.name = 'SearchError'
        this.status = status
        this.code = code
        this.type = type
        this.link = link
    }
}

const DEFAULT_TIMEOUT = 5000

export class SearchClient {
    #endpoint
    #index
    #headers
    #timeout
    #pending = null

    constructor({ url, key, index, timeout = DEFAULT_TIMEOUT }) {
        this.#endpoint = `${url.replace(/\/$/, '')}/multi-search`
        this.#index = index
        this.#headers = { 'Content-Type': 'application/json', Authorization: `Bearer ${key}` }
        this.#timeout = timeout
    }

    // A whole plan travels in one request: a second call would cancel the first,
    // since a slower answer must never overwrite a fresher one. Cancelling
    // resolves to null rather than throwing.
    async search(queries) {
        this.#pending?.abort()

        const controller = new AbortController()
        this.#pending = controller

        // An engine that never answers would hang the listing for good.
        const expiry = setTimeout(() => controller.abort(), this.#timeout)

        try {
            return await this.#post(controller.signal, queries)
        } catch (error) {
            if (error.name === 'AbortError') {
                return null
            }

            throw error
        } finally {
            clearTimeout(expiry)
        }
    }

    async #post(signal, queries) {
        const keys = Object.keys(queries)
        const response = await fetch(this.#endpoint, {
            method: 'POST',
            headers: this.#headers,
            body: JSON.stringify({
                queries: keys.map((key) => ({ indexUid: this.#index, ...queries[key] })),
            }),
            signal,
        })

        if (!response.ok) {
            throw await this.#toError(response)
        }

        const { results = [] } = await response.json()

        // Answers come back in order: the caller's keys are put back on them.
        return Object.fromEntries(keys.map((key, rank) => [key, results[rank] ?? {}]))
    }

    async #toError(response) {
        const body = await response.json().catch(() => ({}))

        return new SearchError(body.message ?? response.statusText, {
            status: response.status,
            code: body.code ?? null,
            type: body.type ?? null,
            link: body.link ?? null,
        })
    }
}
