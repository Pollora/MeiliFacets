import type { Range } from '../shared/range.ts'

/** A slider answers the arrows, Home and End — the keyboard pattern its role promises. */
export class SliderKeys {
    static targetOf(event: KeyboardEvent, now: number, bounds: Range): number | undefined {
        const step = event.shiftKey ? Math.max(bounds.span / 10, 1) : 1
        const targets: Partial<Record<string, number | null>> = {
            ArrowLeft: now - step,
            ArrowDown: now - step,
            ArrowRight: now + step,
            ArrowUp: now + step,
            Home: bounds.min,
            End: bounds.max,
        }

        return targets[event.key] ?? undefined
    }
}
