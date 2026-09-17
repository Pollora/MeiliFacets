import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { describe, it } from 'node:test'

describe('the delivered client', () => {
    // A relative import is fetched at its bare URL: the entry's `?ver=` would not reach it.
    it('imports no file of its own', () => {
        const bundle = readFileSync(new URL('../../resources/assets/dist/listing.js', import.meta.url), 'utf8')

        assert.doesNotMatch(bundle, /\bfrom\s*["']\.{1,2}\/|\bimport\s*\(?\s*["']\.{1,2}\//)
    })
})
