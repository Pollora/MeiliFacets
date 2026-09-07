/**
 * @import { Connection } from './description.js'
 */

const DEFAULT_TIMEOUT = 5000

/** What the engine answered when it refused, `errorLink` included. */
export class SearchError extends Error {
    /**
     * @param {string} message
     * @param {{ status?: number | null, code?: string | null, type?: string | null, link?: string | null }} [detail]
     */
    constructor(message, { status = null, code = null, type = null, link = null } = {}) {
        super(message)
        this.name = 'SearchError'
        this.status = status
        this.code = code
        this.type = type
        this.link = link
    }
}

/** A search the visitor overtook by asking for something else. */
export class SearchSuperseded extends Error {
    constructor() {
        super('A newer search replaced this one.')
        this.name = 'SearchSuperseded'
    }
}

export class SearchClient {
    /** @type {string} */
    #endpoint

    /** @type {string} */
    #index

    /** @type {Record<string, string>} */
    #headers

    /** @type {number} */
    #timeout

    /** @type {AbortController | null} */
    #pending = null

    /**
     * @param {Connection & { timeout?: number }} connection
     */
    constructor({ url, key, index, timeout = DEFAULT_TIMEOUT }) {
        this.#endpoint = `${url.replace(/\/$/, '')}/multi-search`
        this.#index = index
        this.#headers = { 'Content-Type': 'application/json', Authorization: `Bearer ${key}` }
        this.#timeout = timeout
    }

    /**
     * A whole plan travels in one request. A second call abandons the first,
     * since a slower answer must never overwrite a fresher one — and the two
     * ways a request can end are told apart: overtaken raises
     * SearchSuperseded, an engine that never answers raises SearchError.
     *
     * @param {Record<string, object>} queries
     * @returns {Promise<Record<string, any>>}
     */
    async search(queries) {
        this.#pending?.abort()
        this.#pending = new AbortController()

        const signal = AbortSignal.any([this.#pending.signal, AbortSignal.timeout(this.#timeout)])

        try {
            return await this.#post(signal, queries)
        } catch (error) {
            throw this.#reasonFor(error)
        }
    }

    /**
     * @param {AbortSignal} signal
     * @param {Record<string, object>} queries
     */
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
            throw await this.#refusal(response)
        }

        const { results = [] } = await response.json()

        // Answers come back in order: the caller's keys are put back on them.
        return Object.fromEntries(keys.map((key, rank) => [key, results[rank] ?? {}]))
    }

    /**
     * @param {unknown} error
     */
    #reasonFor(error) {
        if (!(error instanceof Error)) {
            return new SearchError(String(error))
        }

        return {
            AbortError: () => new SearchSuperseded(),
            TimeoutError: () => new SearchError('The engine did not answer in time.'),
        }[error.name]?.() ?? error
    }

    /**
     * @param {Response} response
     */
    async #refusal(response) {
        const body = await response.json().catch(() => ({}))

        return new SearchError(body.message ?? response.statusText, {
            status: response.status,
            code: body.code ?? null,
            type: body.type ?? null,
            link: body.link ?? null,
        })
    }
}
