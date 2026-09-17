export interface ListboxMove {
    action: 'open' | 'activate' | 'pick' | 'close'
    index: number
    /** Tab picks, then must still leave the control. */
    passesThrough?: boolean
}

export interface ListboxPosition {
    active: number
    selected: number
    last: number
}

export class ListboxKeys {
    static whileClosed(key: string, { selected, last }: ListboxPosition): ListboxMove | null {
        const moves: Partial<Record<string, ListboxMove>> = {
            ArrowDown: { action: 'open', index: selected },
            ArrowUp: { action: 'open', index: selected },
            Enter: { action: 'open', index: selected },
            ' ': { action: 'open', index: selected },
            Home: { action: 'open', index: 0 },
            End: { action: 'open', index: last },
        }

        return moves[key] ?? null
    }

    static whileOpen(key: string, { active, last }: ListboxPosition): ListboxMove | null {
        const moves: Partial<Record<string, ListboxMove>> = {
            ArrowDown: { action: 'activate', index: active + 1 },
            ArrowUp: { action: 'activate', index: active - 1 },
            Home: { action: 'activate', index: 0 },
            End: { action: 'activate', index: last },
            Enter: { action: 'pick', index: active },
            ' ': { action: 'pick', index: active },
            Escape: { action: 'close', index: active },
            Tab: { action: 'pick', index: active, passesThrough: true },
        }

        return moves[key] ?? null
    }
}
