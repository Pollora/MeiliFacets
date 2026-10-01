const ENTERING_OR_LEAVING = ['opacity', 'transform']

/** The stylesheet's transitions that bring a panel in or take it out, whatever else plays on it. */
export class PanelTransitions {
    #panel: Element

    constructor(panel: Element) {
        this.#panel = panel
    }

    enteringOrLeaving() {
        return this.#panel.getAnimations().filter((animation) => this.#isEnteringOrLeaving(animation))
    }

    #isEnteringOrLeaving(animation: Animation) {
        return 'transitionProperty' in animation && ENTERING_OR_LEAVING.includes(String(animation.transitionProperty))
    }
}
