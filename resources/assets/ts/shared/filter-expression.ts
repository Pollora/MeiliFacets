// One pass over both characters: escaping them in sequence would let a value
// ending in a backslash close the string.
const ESCAPED = /[\\"]/g

const CONJUNCTION = ' AND '

/** The browser's copy of `Search\\FilterExpression`. */
export class FilterExpression {
    static all(clauses: readonly string[]) {
        return clauses.filter((clause) => clause !== '').join(CONJUNCTION)
    }

    static equals(field: string, value: string) {
        return `${field} = "${value.replace(ESCAPED, '\\$&')}"`
    }
}
