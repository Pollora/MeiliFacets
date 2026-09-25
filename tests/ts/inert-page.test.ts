import assert from 'node:assert/strict'
import { beforeEach, describe, it } from 'node:test'

import { InertPage } from '../../resources/assets/ts/drawer/inert-page.ts'
import { find, load } from './dom.ts'

const PAGE = `
<div id="admin-bar"></div>
<header id="header"></header>
<main id="main">
    <h1 id="title">Shop</h1>
    <div id="listing">
        <button id="opener">Filter</button>
        <div id="drawer"><button id="inside">Brand</button></div>
        <ul id="results"></ul>
    </div>
</main>
<footer id="footer" inert></footer>`

describe('InertPage', () => {
    let document: Document
    let page: InertPage

    beforeEach(() => {
        document = load(PAGE).document
        page = new InertPage(find(document, '#drawer'))
    })

    const inert = () => [...document.querySelectorAll('[inert]')].map((node) => node.id).sort()

    it('makes inert the siblings of the node and of each of its ancestors, up to the body', () => {
        page.seal()

        assert.deepEqual(inert(), ['admin-bar', 'footer', 'header', 'opener', 'results', 'title'])
    })

    it('leaves the node, what it holds and its ancestors reachable', () => {
        page.seal()

        for (const id of ['drawer', 'inside', 'listing', 'main']) {
            assert.equal(find(document, `#${id}`).hasAttribute('inert'), false, id)
        }
    })

    /** Q-2: the page had made the footer inert on its own, and it keeps it. */
    it('releases what it made inert and nothing else', () => {
        page.seal()
        page.release()

        assert.deepEqual(inert(), ['footer'])
    })

    it('seals twice without recording a node twice', () => {
        page.seal()
        page.seal()
        page.release()

        assert.deepEqual(inert(), ['footer'])
    })

    it('releases nothing when it sealed nothing', () => {
        page.release()

        assert.deepEqual(inert(), ['footer'])
    })
})
