import { PageRoots } from './shared/page-roots.ts'
import { RootComponent } from './shared/root-component.ts'
import { SearchPanel } from './site-search/search-panel.ts'

import type { SiteSearchDescription } from './shared/description.ts'
import type { BoundRoot, RootBinder, ScriptModule } from './shared/page-roots.ts'
import type * as Client from './site-search-client.ts'

/** Written by `bundle.ts`: the client's own fingerprint, which the loader's `?ver=` does not carry. */
declare const CLIENT_VERSION: string

const PRECONNECT = 'preconnect'

const MODULE: ScriptModule = { id: '@meilifacets/site-search', roots: 'searches' }
const CLIENT = new URL(`site-search-client.js?ver=${CLIENT_VERSION}`, import.meta.url).href

/** Binds every search root's panel at once, and fetches the client the first time a visitor reaches for one. */
class SiteSearchPage implements RootBinder<SiteSearchDescription> {
    #client: Promise<typeof Client> | null = null

    bind(root: BoundRoot<SiteSearchDescription>) {
        const panel: SearchPanel = new SearchPanel(root.contract, () => void this.#arrive(root, panel))

        panel.start()
    }

    async #arrive(root: BoundRoot<SiteSearchDescription>, panel: SearchPanel) {
        this.#warm(root.description.preconnect)

        try {
            const { SiteSearch } = await this.#load()

            new SiteSearch(root).start()
        } catch (failure) {
            console.error('[meilifacets] the search client could not be loaded:', failure)
            panel.unavailable()
        }
    }

    #load() {
        this.#client ??= import(CLIENT) as Promise<typeof Client>

        return this.#client
    }

    /** A listing page warms the same origin from its `<head>`. */
    #warm(origin: string) {
        const warmed = [...document.querySelectorAll<HTMLLinkElement>(`link[rel="${PRECONNECT}"]`)]
            .some((link) => new URL(link.href).origin === origin)

        if (origin === '' || warmed) {
            return
        }

        const link = document.createElement('link')

        link.rel = PRECONNECT
        link.href = origin
        link.crossOrigin = ''
        document.head.append(link)
    }
}

new PageRoots<SiteSearchDescription>(document, RootComponent.SEARCH, MODULE).start(new SiteSearchPage())
