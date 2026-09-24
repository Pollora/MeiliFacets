export type Drawn = GlobalEventHandlers & Element & ElementCSSInlineStyle

export const isDrawn = (node: Element | null): node is Drawn => node instanceof HTMLElement || node instanceof SVGElement
