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
    #headers
    #timeout
    #pending = null

    constructor({ url, key, index, timeout = DEFAULT_TIMEOUT }) {
        this.#endpoint = `${url.replace(/\/$/, '')}/indexes/${index}/search`
        this.#headers = { 'Content-Type': 'application/json', Authorization: `Bearer ${key}` }
        this.#timeout = timeout
    }

    // Each call cancels the previous one: a slower answer must not overwrite a
    // fresher one. A cancelled call resolves to null rather than throwing.
    async search(request) {
        this.#pending?.abort()

        const controller = new AbortController()
        this.#pending = controller

        // An engine that never answers would otherwise hang the listing for good.
        const expiry = setTimeout(() => controller.abort(), this.#timeout)

        try {
            return await this.#post(controller.signal, request)
        } catch (error) {
            if (error.name === 'AbortError') {
                return null
            }

            throw error
        } finally {
            clearTimeout(expiry)
        }
    }

    async #post(signal, request) {
        const response = await fetch(this.#endpoint, {
            method: 'POST',
            headers: this.#headers,
            body: JSON.stringify(request),
            signal,
        })

        if (!response.ok) {
            throw await this.#toError(response)
        }

        return response.json()
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
