import { LEAVING } from '../shared/attributes.ts'
import { CssTiming } from '../shared/css-timing.ts'
import { Entrance } from '../shared/entrance.ts'
import { SUBPIXEL } from '../shared/subpixel.ts'
import { Departure } from './departure.ts'
import { HEIGHT_UNREAD, PanelHeight } from './panel-height.ts'

import type { Contract } from '../shared/contract.ts'
import type { EntranceTiming } from '../shared/entrance.ts'
import type { Place } from './departure.ts'
import type { HeightBefore } from './panel-height.ts'

const MESSAGES = ['search-empty', 'search-unavailable'] as const
const SETTLE = '--meili-duration-settle'
const LEAVE = '--meili-duration-leave'
const MOVE = '--meili-duration-move'
const RESIZE = '--meili-duration-resize'
const CROSSFADE = '--meili-duration-fade'
const EASE = '--meili-ease'
const FADE_EASE = '--meili-ease-fade'
const RESIZE_EASE = '--meili-ease-resize'
const EXIT_OFFSET = 'translateY(-4px)'
const NO_OFFSET = 'none'

/** Slides on the module's ease-out, fades alone on `ease`. */
interface Timings {
    slide: EntranceTiming
    move: EntranceTiming
    appear: EntranceTiming
    leave: EntranceTiming
    crossfade: EntranceTiming
    resize: EntranceTiming
}

interface Offset {
    x: number
    y: number
}

interface Before {
    places: Map<Element, Place>
    counts: Map<Element, string>
    shownMessages: Element[]
    height: HeightBefore
}

const NOTHING_BEFORE: Before = { places: new Map(), counts: new Map(), shownMessages: [], height: HEIGHT_UNREAD }
const STAYED: Offset = { x: 0, y: 0 }

/**
 * The results between two answers: a card found again glides to its new rank, a new one fades in, a lost one
 * fades out off the flow, a section or a message fades as a whole. Every box is read before the answer is
 * written and once after it, never between two writes. Nothing but `transform` and `opacity` is animated, except
 * the panel's own height.
 */
export class ResultsMotion {
    #panel: HTMLElement
    #height: PanelHeight
    #contract: Contract
    #timing: CssTiming
    #row: Entrance
    #count: Entrance
    #fade: Entrance
    #timings: Timings | null = null
    #before = NOTHING_BEFORE
    #after = new Map<Element, DOMRect>()

    constructor(contract: Contract, panel: HTMLElement) {
        const document = panel.ownerDocument

        this.#contract = contract
        this.#panel = panel
        this.#timing = new CssTiming(document)
        this.#height = new PanelHeight(panel, this.#timing)
        this.#row = new Entrance(document, { from: 'translateY(4px)', duration: SETTLE, easing: EASE })
        this.#count = new Entrance(document, { from: 'translateY(2px)', duration: SETTLE, easing: EASE })
        this.#fade = new Entrance(document, { from: 'none', duration: SETTLE, easing: EASE })
    }

    around(change: () => void) {
        const timings = this.#measure()

        change()
        this.#play(timings)
        this.#before = NOTHING_BEFORE
        this.#after.clear()
    }

    #measure() {
        const timings = (this.#timings ??= this.#readTimings())

        this.#before = {
            places: new Map(this.#shown().map((node) => [node, this.#placeOf(node)])),
            counts: new Map(this.#sections().map((section) => [section, this.#countOf(section)?.textContent ?? ''])),
            shownMessages: this.#messages().filter((message) => !this.#isHidden(message)),
            height: this.#height.before(),
        }

        return timings
    }

    #play(timings: Timings) {
        const shown = this.#shown()
        const crossfading = this.#messagesChanged()
        const { frames, resize } = this.#readAfter(shown)
        const departure = new Departure(frames, crossfading ? timings.crossfade : timings.leave, this.#exitOffset())

        this.#leave(shown, departure)
        this.#glideAll(timings.move)

        if (!this.#before.height.panelEnteringOrLeaving) {
            shown.forEach((node) => this.#enter(node, crossfading ? timings.crossfade : timings.appear, timings.slide))
        }

        this.#before.counts.forEach((text, section) => this.#recount(section, text, timings.slide))
        resize.play(timings.resize)
    }

    #readAfter(shown: Element[]) {
        const resize = this.#height.after(this.#before.height)

        this.#after = new Map(shown.filter((node) => this.#before.places.has(node)).map((node) => [node, node.getBoundingClientRect()]))

        return { frames: Departure.read(this.#panel, this.#listsLeft(shown), resize.overhang()), resize }
    }

    #exitOffset() {
        return this.#timing.prefersReducedMotion() ? NO_OFFSET : EXIT_OFFSET
    }

    #leave(shown: Element[], departure: Departure) {
        this.#before.places.forEach((place, node) => {
            if (shown.includes(node)) {
                return
            }

            if (place.parent !== null) {
                departure.row(node, place)
            } else if (this.#isHidden(node)) {
                departure.copy(node, place, this.#panel)
            }
        })
    }

    #glideAll(timing: EntranceTiming) {
        if (!this.#timing.prefersReducedMotion()) {
            this.#after.forEach((rect, node) => this.#glide(node, this.#offset(node, rect), timing))
        }
    }

    /** Additive, so a glide caught mid-way carries on from where the eye sees the card rather than jumping. */
    #glide(node: Element, { x, y }: Offset, { duration, easing }: EntranceTiming) {
        if ((Math.abs(x) >= SUBPIXEL || Math.abs(y) >= SUBPIXEL) && node instanceof HTMLElement) {
            node.animate([{ transform: `translate(${x}px, ${y}px)` }, { transform: 'none' }], { duration, easing, composite: 'add' })
        }
    }

    /** A card moves by what its box moved less what its section moved: the section's glide carries it the rest. */
    #offset(node: Element, rect: DOMRect): Offset {
        const own = this.#shift(node, rect)
        const section = this.#sectionOf(node)
        const carried = section === null ? STAYED : this.#shift(section, this.#after.get(section))

        return { x: own.x - carried.x, y: own.y - carried.y }
    }

    #shift(node: Element, to: DOMRect | undefined): Offset {
        const from = this.#before.places.get(node)?.rect

        return from === undefined || to === undefined ? STAYED : { x: from.left - to.left, y: from.top - to.top }
    }

    /** One entrance per container: the cards of a section coming in arrive with it. */
    #enter(node: Element, block: EntranceTiming, row: EntranceTiming) {
        const section = this.#sectionOf(node)

        if (this.#before.places.has(node) || !(node instanceof HTMLElement)) {
            return
        }

        if (section === null) {
            this.#fade.play(node, block)
        } else if (this.#before.places.has(section)) {
            this.#row.play(node, row)
        }
    }

    /** Only in a section that stays: one coming in brings its count with it. */
    #recount(section: Element, before: string, timing: EntranceTiming) {
        const count = this.#countOf(section)

        if (this.#after.has(section) && count instanceof HTMLElement && before !== count.textContent) {
            this.#count.play(count, timing)
        }
    }

    #listsLeft(shown: Element[]) {
        return [...this.#before.places].flatMap(([node, { parent }]) => (parent === null || shown.includes(node) ? [] : [parent]))
    }

    #messagesChanged() {
        return this.#messages().some((message) => this.#before.shownMessages.includes(message) === this.#isHidden(message))
    }

    /** What the visitor sees: shown sections, the cards of their lists, and shown messages. */
    #shown() {
        const sections = this.#sections().filter((section) => !this.#isHidden(section))
        const rows = sections.flatMap((section) => [...(this.#contract.one('search-results', section)?.children ?? [])])

        return [...sections, ...rows.filter((row) => !row.hasAttribute(LEAVING)), ...this.#messages().filter((message) => !this.#isHidden(message))]
    }

    #placeOf(node: Element): Place {
        const parent = this.#sectionOf(node) === null ? null : node.parentElement

        return { rect: node.getBoundingClientRect(), opacity: this.#opacityOf(node), parent }
    }

    /** Mid-fade, where the fade stands: a copy that leaves starts from there rather than from full. */
    #opacityOf(node: Element) {
        const fading = node.getAnimations().length > 0
        const opacity = fading ? Number.parseFloat(this.#panel.ownerDocument.defaultView?.getComputedStyle(node).opacity ?? '') : Number.NaN

        return Number.isNaN(opacity) ? 1 : opacity
    }

    #readTimings(): Timings {
        const timing = (duration: string, easing: string) => ({
            duration: this.#timing.duration(this.#panel, duration) ?? 0,
            easing: this.#timing.easing(this.#panel, easing),
        })

        return {
            slide: timing(SETTLE, EASE),
            move: timing(MOVE, EASE),
            appear: timing(SETTLE, FADE_EASE),
            leave: timing(LEAVE, FADE_EASE),
            crossfade: timing(CROSSFADE, FADE_EASE),
            resize: timing(RESIZE, RESIZE_EASE),
        }
    }

    /** The section a card sits in; `null` for anything that is not a card. */
    #sectionOf(node: Element) {
        return this.#sections().find((section) => this.#contract.one('search-results', section) === node.parentElement) ?? null
    }

    #sections() {
        return this.#contract.all('search-section').filter((section) => !section.hasAttribute(LEAVING))
    }

    #messages() {
        return MESSAGES.flatMap((hook) => this.#contract.all(hook).filter((message) => !message.hasAttribute(LEAVING)))
    }

    #countOf(section: Element) {
        return this.#contract.one('search-count', section)
    }

    #isHidden(node: Element) {
        return node instanceof HTMLElement && node.hidden
    }
}
