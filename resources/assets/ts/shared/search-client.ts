import type { Card, Connection } from './description.ts'

const DEFAULT_TIMEOUT = 5000

export interface SearchQuery {
    q: string
    filter: string
    facets: string[]
    hitsPerPage: number
    page: number
    attributesToRetrieve?: string[]
    sort?: string[]
}

export interface SearchAnswer {
    hits?: { card?: Card }[]
    totalHits?: number
    facetDistribution?: Partial<Record<string, Record<string, number>>>
    facetStats?: Partial<Record<string, { min: number, max: number }>>
}

export type Answers = Partial<Record<string, SearchAnswer>>

interface Refusal {
    message?: string
    code?: string
    type?: string
    link?: string
}

/** What the engine answered when it refused, `errorLink` included. */
export class SearchError extends Error {
    readonly status: number | null
    readonly code: string | null
    readonly type: string | null
    readonly link: string | null

    constructor(message: string, { status = null, code = null, type = null, link = null }: {
        status?: number | null, code?: string | null, type?: string | null, link?: string | null
    } = {}) {
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

const REASONS: Partial<Record<string, () => Error>> = {
    AbortError: () => new SearchSuperseded(),
    TimeoutError: () => new SearchError('The engine did not answer in time.'),
}

export class SearchClient {
    #endpoint: string
    #index: string
    #headers: Record<string, string>
    #timeout: number
    #pending: AbortController | null = null

    constructor({ url, key, index, timeout = DEFAULT_TIMEOUT }: Connection & { timeout?: number }) {
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
     */
    async search(queries: Record<string, SearchQuery>): Promise<Answers> {
        this.#pending?.abort()
        this.#pending = new AbortController()

        const signal = AbortSignal.any([this.#pending.signal, AbortSignal.timeout(this.#timeout)])

        try {
            return await this.#post(signal, queries)
        } catch (error) {
            throw this.#reasonFor(error)
        }
    }

    async #post(signal: AbortSignal, queries: Record<string, SearchQuery>): Promise<Answers> {
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

        const { results = [] } = (await response.json()) as { results?: SearchAnswer[] }

        // Answers come back in order: the caller's keys are put back on them.
        return Object.fromEntries(keys.map((key, rank) => [key, results[rank] ?? {}]))
    }

    #reasonFor(error: unknown) {
        if (!(error instanceof Error)) {
            return new SearchError(String(error))
        }

        return REASONS[error.name]?.() ?? error
    }

    async #refusal(response: Response) {
        const body = (await response.json().catch(() => ({}))) as Refusal

        return new SearchError(body.message ?? response.statusText, {
            status: response.status,
            code: body.code ?? null,
            type: body.type ?? null,
            link: body.link ?? null,
        })
    }
}
