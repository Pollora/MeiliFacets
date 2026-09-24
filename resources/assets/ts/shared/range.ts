// `String(1e-7)` is `1e-7`, which the engine refuses; the server writes `%.4F` without trailing zeros.
const BOUND = new Intl.NumberFormat('en-US', { useGrouping: false, maximumFractionDigits: 4 })

/** The browser's copy of `Listing\Range`: an asked price range, or the bounds measured for one. */
export class Range {
    readonly min: number | null
    readonly max: number | null

    constructor(min: number | null = null, max: number | null = null) {
        this.min = min
        this.max = max
    }

    static boundTo(bound: number) {
        return BOUND.format(bound)
    }

    get span() {
        return (this.max ?? 0) - (this.min ?? 0)
    }

    isEmpty() {
        return this.min === null && this.max === null
    }

    equals(other: Range) {
        return this.min === other.min && this.max === other.max
    }

    clamp(value: number) {
        const floored = Math.max(value, this.min ?? value)

        return Math.min(floored, this.max ?? floored)
    }

    ratio(value: number | null) {
        if (value === null || this.span <= 0) {
            return 0
        }

        return Math.round(((this.clamp(value) - (this.min ?? 0)) / this.span) * 10_000) / 10_000
    }

    valueAt(ratio: number) {
        return (this.min ?? 0) + ratio * this.span
    }
}
