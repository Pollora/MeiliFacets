import assert from 'node:assert/strict'
import { createHash } from 'node:crypto'
import { readFileSync } from 'node:fs'
import { describe, it } from 'node:test'

const delivered = (file: string) => readFileSync(new URL(`../../resources/assets/dist/${file}`, import.meta.url), 'utf8')

describe('the delivered client', () => {
    // A relative import is fetched at its bare URL: the entry's `?ver=` would not reach it.
    for (const file of ['listing.js', 'site-search.js', 'site-search-client.js']) {
        it(`imports no file of its own from ${file}`, () => {
            assert.doesNotMatch(delivered(file), /\bfrom\s*["']\.{1,2}\/|\bimport\s*\(?\s*["']\.{1,2}\//)
        })
    }

    it('has the loader fetch the client under the client\'s own fingerprint', () => {
        const client = createHash('sha256').update(delivered('site-search-client.js')).digest('hex')
        const fetched = /site-search-client\.js\?ver=([0-9a-f]+)/.exec(delivered('site-search.js'))?.[1] ?? ''

        assert.notEqual(fetched, '')
        assert.equal(client.startsWith(fetched), true)
    })

    it('leaves the client out of the loader', () => {
        assert.doesNotMatch(delivered('site-search.js'), /highlightPreTag/)
        assert.match(delivered('site-search-client.js'), /highlightPreTag/)
    })
})
