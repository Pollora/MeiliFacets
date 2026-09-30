export type StylableElement = GlobalEventHandlers & Element & ElementCSSInlineStyle

export const isStylable = (node: Element | null): node is StylableElement => node instanceof HTMLElement || node instanceof SVGElement
