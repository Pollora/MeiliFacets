import { createHash } from 'node:crypto'
import { readFileSync, writeFileSync } from 'node:fs'

import { build } from 'esbuild'

const SOURCES = 'resources/assets/ts'
const BUNDLES = 'resources/assets/dist'
const FINGERPRINT_LENGTH = 12

/** One entry, bundled on its own: nothing is shared between bundles at runtime. */
class Bundle {
    readonly output: string
    #entry: string

    constructor(entry: string, output: string) {
        this.#entry = `${SOURCES}/${entry}`
        this.output = `${BUNDLES}/${output}`
    }

    static fingerprint(built: string) {
        return createHash('sha256').update(built).digest('hex').slice(0, FINGERPRINT_LENGTH)
    }

    async build(define: Record<string, string> = {}) {
        const { outputFiles } = await build({
            entryPoints: [this.#entry],
            bundle: true,
            format: 'esm',
            minify: true,
            target: 'es2022',
            write: false,
            logLevel: 'warning',
            define,
        })

        return outputFiles[0]?.text ?? ''
    }

    deliver(built: string) {
        if (!process.argv.includes('--check')) {
            writeFileSync(this.output, built)
        } else if (readFileSync(this.output, 'utf8') !== built) {
            console.error(`${this.output} does not match the sources: run composer build.`)
            process.exitCode = 1
        }
    }
}

const listing = new Bundle('listing-page.ts', 'listing.js')
const searchClient = new Bundle('site-search-client.ts', 'site-search-client.js')
const searchLoader = new Bundle('site-search-page.ts', 'site-search.js')

const client = await searchClient.build()

listing.deliver(await listing.build())
searchClient.deliver(client)
searchLoader.deliver(await searchLoader.build({ CLIENT_VERSION: JSON.stringify(Bundle.fingerprint(client)) }))
