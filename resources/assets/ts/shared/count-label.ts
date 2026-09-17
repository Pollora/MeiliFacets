export class CountLabel {
    #rules: Intl.PluralRules

    constructor(locale: string) {
        this.#rules = new Intl.PluralRules(locale)
    }

    of(pattern: string, count: number) {
        const forms = pattern.split('|')
        const form = this.#rules.select(count) === 'one' ? forms[0] : forms[1]

        return (form ?? forms[0] ?? '').replaceAll(':count', String(count))
    }
}
