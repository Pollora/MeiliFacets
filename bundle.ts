import { readFileSync, writeFileSync } from 'node:fs'

import { build } from 'esbuild'

const ENTRY = 'resources/assets/ts/listing-page.ts'
const BUNDLE = 'resources/assets/dist/listing.js'

const { outputFiles } = await build({
    entryPoints: [ENTRY],
    bundle: true,
    format: 'esm',
    minify: true,
    target: 'es2022',
    write: false,
    logLevel: 'warning',
})

const built = outputFiles[0]?.text ?? ''

if (!process.argv.includes('--check')) {
    writeFileSync(BUNDLE, built)
} else if (readFileSync(BUNDLE, 'utf8') !== built) {
    console.error(`${BUNDLE} does not match the sources: run composer build.`)
    process.exitCode = 1
}
