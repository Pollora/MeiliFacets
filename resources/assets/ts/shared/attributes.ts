/** The state attributes more than one component writes: read by the stylesheet and by assistive technologies. */
export const EXPANDED = 'aria-expanded'

export const CONTROLS = 'aria-controls'

/** A filter the server put past the drawer's row: read by the row and by « Show more ». */
export const BEYOND_ROW = 'data-beyond-row'

export const ACTIVE_DESCENDANT = 'aria-activedescendant'

export const BUSY = 'aria-busy'

/** What the theme styles to show where the keyboard is, before anything is chosen. */
export const ACTIVE_OPTION = 'data-active'

/** A search root whose panel is open: what the stylesheet draws the scrim from. */
export const OPEN = 'data-open'

/** Cuts the stylesheet's transition for the change it is set around. */
export const INSTANT = 'data-instant'

/** A node on its way out, drawn over the flow until its fade ends: nothing counts it any more. */
export const LEAVING = 'data-leaving'
