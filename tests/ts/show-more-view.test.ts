import assert from 'node:assert/strict'
import { describe, it } from 'node:test'

import { ShowMoreView } from '../../resources/assets/ts/drawer/show-more-view.ts'
import { Contract } from '../../resources/assets/ts/shared/contract.ts'
import { find, open } from './dom.ts'

const markup = (beyond: string) => `<div data-listing="products">
    <div id="drawer" data-overflow="show-more" data-meili="drawer">
        <div data-meili="drawer-body">
            <fieldset data-meili="facet"></fieldset>
            ${beyond}
            <button type="button" aria-controls="drawer" data-meili="drawer-show-more">Show more</button>
        </div>
    </div>
    <button type="button" aria-controls="another" data-meili="drawer-show-more">Show more</button>
</div>`

const visibleButtonsWith = (beyond: string) => {
    const { root } = open(markup(beyond))
    const contract = new Contract(root)

    new ShowMoreView(contract, find(root, '#drawer')).show()

    return contract.elements('drawer-show-more').map((button) => !button.hidden)
}

describe('ShowMoreView', () => {
    it('shows « Show more » while a filter past the row has something to offer', () => {
        assert.deepEqual(visibleButtonsWith('<fieldset data-beyond-row hidden data-meili="facet"></fieldset><fieldset data-beyond-row data-meili="facet"></fieldset>'), [true, true])
    })

    it('hides it once every filter past the row is empty, and leaves another drawer\'s alone', () => {
        assert.deepEqual(visibleButtonsWith('<fieldset data-beyond-row hidden data-meili="facet"></fieldset>'), [false, true])
    })

    it('shows it again when a filter past the row comes back', () => {
        const { root } = open(markup('<fieldset data-beyond-row hidden data-meili="facet"></fieldset>'))
        const view = new ShowMoreView(new Contract(root), find(root, '#drawer'))

        view.show()
        find(root, '[data-beyond-row]').hidden = false
        view.show()

        assert.equal(find(root, '#drawer [data-meili="drawer-show-more"]').hidden, false)
    })
})
