# MeiliFacets — chantier « barre de filtres » · architecture cible

Voir aussi : [chantier-filtres.md](chantier-filtres.md) · [decisions.md](decisions.md) · [revue.md](revue.md)

**Proposition v2 du 2026-09-24, validée le même jour** (Q-1 → Q-6 selon les ★, dossiers et crochets autorisés ; voir [chantier-filtres.md](chantier-filtres.md)). Les ★ sont des recommandations.

**Remis d'accord avec le code le 2026-09-25** (audit `R-48`, lot E, après les lots A `b1cc621` et B `0a4ba1d`). Les §1 à §5 décrivent désormais ce qui existe, pas ce qui était prévu ; les §6 et §7 restent le plan et les questions de départ, pour l'historique.

Changements depuis la v1 :
- plus de « disposition » : pas d'enum `Layout`, pas de `bar.blade.php`, pas d'attribut `layout=`. Le thème compose ;
- la présentation (`Control`/`Pill`) est déclarée sur la facette, indépendante de tout le reste ;
- plus de `<dialog>` ni de `display: contents` : le tiroir est un conteneur ordinaire, que le JS promeut en dialogue sur mobile ;
- une brique repliable unique, qui fait le panneau déroulant et la section d'accordéon ;
- plus de dossiers `bar/` : deux dossiers TS par fonctionnalité, à autoriser (C-8 à réviser).

## 1. Principe directeur

- **Des briques indépendantes.** Chaque composant fait une chose et ignore où il est posé. La rangée, la colonne ou la grille, c'est le thème qui les compose, comme il compose déjà la colonne dans `archive-product.blade.php`.
- **Un seul exemplaire DOM** de chaque facette et du tri (`R-95`). La garde `place()` s'étend au tri (`R-162`). Les éléments qui peuvent légitimement apparaître deux fois (`reset`, compteurs) sont peints par `contract.all()`.
- **Le contexte change le CSS, jamais le markup.** C'est la largeur qui décide, pas le tiroir : à partir de 48em (`@media (width >= 48em)`), la feuille de style pose `position: absolute` sur `[data-meili="panel"]` et le repliable flotte en panneau déroulant ; en dessous, il reste une section en ligne qui s'ouvre dans le flux (accordéon), dans le tiroir modal comme hors de lui. Le JS ne garde pas de second seuil : `DisclosureGroup` lit `getComputedStyle(panel).position` pour savoir si un panneau flotte.
- **La plateforme d'abord** : `inert`, le motif disclosure de l'APG, `matchMedia`, `:has()`, `@media (scripting)`. Aucune dépendance.

## 2. Catalogue des briques

| Brique | Composant, attributs | Crochets | TS | Rôle |
| --- | --- | --- | --- | --- |
| Facette | `<x-meilifacets::facet>` + `presentation="control\|pill"`, `collapsible` | `facet`, `toggle`, `panel`, `selected-count` | `FacetsView`, `DisclosureGroup`, `PanelMotion`, `SelectedCountView` | Le `<fieldset>`. En mode repliable, la `<legend>` contient le déclencheur `toggle.blade.php` (`aria-expanded`, `aria-controls`, `aria-describedby` vers le badge, chevron en CSS) et le panneau porte `panel`. `data-presentation` n'est écrit que pour `Pill`. |
| Groupe | `<x-meilifacets::facets>` + `collapsible`, `:with-apply` | `facets` | — | Transmet `collapsible` aux facettes restantes. `:with-apply="false"` retire son bouton `apply`. |
| Prix | `<x-meilifacets::price>` + `collapsible` | idem facette | `PriceControl` | Même repliable. Le badge vaut 1 si une plage est posée (`R-123`). |
| Tri | `<x-meilifacets::sort>` + `widget="listbox\|radios"`, `collapsible` | `sort-choices`, `sort-choice`, `sort-choice-row`, `sort-chosen` (+ `toggle`, `panel`) | `SortRadios` (`radios`), `SortCombobox` (`listbox`) | C-4 : `radios` rend un `<fieldset>` de radios avec le même repliable que la facette ; `listbox` (défaut) garde la liste déroulante. Le déclencheur du tri repliable lit `SortSummary` (`label`, puis `lead` · `choice` · `trail`, découpés dans « Sort by: :choice ») ; `sort-chosen` porte l'ordre en force et le JS le réécrit (`R-174`). **Pas de badge** : un tri n'est pas un filtre, son `Disclosure` n'a pas de `Badge`, donc ni `selected-count` ni `aria-describedby`. |
| Compteur | `<x-meilifacets::total>` | `total` | `TotalView` | « 88 articles », dans une région `aria-live="polite"`. |
| Pastilles actives | `<x-meilifacets::active-values>` | `active-values`, `active-value`, `active-value-template` | `ActiveValuesView`, `ActiveValueList`, `FocusLanding` | C-7 : `<button name value>` retirable, prix compris. Clonée depuis un `<template>`. Les libellés viennent d'`ActiveValuePatterns` (serveur) et sont publiés dans la description pour le client. |
| Annuler | `<x-meilifacets::reset>` + `shape="text\|pill\|icon"` | `reset` | `FilterSummaryView`, `ResetFocus` | `ResetShape` : `text` (défaut, markup historique, sans `data-shape`), `pill`, `icon` (`reset-icon.blade.php`, icône publiée ou slot `icon`). Une fois pressé il se cache : le focus va à « Appliquer » voisin, sinon au titre du tiroir, sinon au listing (`FocusLanding`), jamais à `body` (`R-175`). |
| Appliquer | `<x-meilifacets::apply>` + `visible-in-drawer` | `apply`, `active-count` | `SelectionCountView`, `CountEntry` | « Appliquer (X) ». En `submit`, lance la recherche. Avec `visible-in-drawer`, le bouton est aussi rendu en `immediate`, marqué `data-only="sheet"` : sans recherche en plus, visible seulement dans le tiroir en sheet. |
| Tiroir | `<x-meilifacets::drawer>` + `heading`, `media` (défaut `Drawer::MOBILE` = `(width < 48em)`), slot `footer` | `drawer`, `drawer-title`, `drawer-close`, `drawer-sheet`, `drawer-footer` | `ListingDrawers`, `Drawer`, `InertPage`, `DrawerGesture`, `SheetHeight`, `HeldPaint` | Conteneur ordinaire : en-tête (titre + ✕), poignée (`drawer-close` aussi), corps (slot), pied (`drawer-footer`), le tout dans la feuille (`drawer-sheet`). |
| Ouvreur | `<x-meilifacets::drawer-opener>` + slot `icon` | `drawer-open`, `active-count` | `Drawer`, `SelectionCountView` | « Filtres (n) ». Le CSS du module le masque hors mobile. |

**Badge** : la règle « vide et caché à zéro » (un nœud caché décrit toujours son contrôle) vit dans un seul objet de valeur, `View\Badge` (`id`, `holdsNothing()`, `text()`), utilisé par `Disclosure` (facette, prix), `Apply` et `DrawerOpener`. Son jumeau client est `shared/badge.ts`, utilisé par `SelectedCountView` et `SelectionCountView`.

**Présentation** (C-5) : l'enum `Presentation: string` a deux cas, `Control` et `Pill`, **sans `Radio`**. Le type d'input reste dérivé de `SelectionMode` (`R-10`). Elle est déclarée dans `Facet` (`ProductFacets`), et l'attribut `presentation` la surcharge ponctuellement. La vue ajoute `data-presentation="pill"` uniquement pour `Pill`, donc une facette `Control` sort à l'octet près le HTML actuel.

**Repliable** : un seul markup. Le serveur le rend fermé (`aria-expanded="false"`, panneau `hidden`).
- À partir de 48em (panneau flottant) : un seul panneau ouvert à la fois ; Échap, le clic extérieur et la sortie du focus le ferment, et Échap rend le focus au déclencheur. Passer d'une pill à l'autre ou fermer au clavier se fait sans animation (`data-instant`, `shared/attributes.ts`). Un panneau qui déborderait du viewport s'aligne sur la fin de son déclencheur (`data-align-end`).
- En dessous (section en ligne, dans le tiroir ou non) : les sections s'ouvrent indépendamment (Q-1). Quand le tiroir se ferme, ce qu'il contenait se replie sans animation (`DisclosureGroup::collapseWithin()`).
- Les durées et courbes viennent des variables `--meili-duration-*` / `--meili-ease-*` posées sur `[data-listing]`, lues côté client par `shared/css-timing.ts` (`s` ou `ms`) avec `prefers-reduced-motion`.

**Tiroir** (option A) :
- Desktop : rien de particulier. En-tête et bouton ✕ sont masqués, le pied reste dans le flux.
- Mobile : sous `@media (scripting: enabled)`, le CSS en fait un bottom sheet masqué tant qu'il est fermé.
- À l'ouverture, le JS pose `role="dialog"`, `aria-modal="true"` et `aria-labelledby` (qui pointe sur `drawer-title`), puis rend le reste de la page `inert`. Il gère Échap et le défilement est verrouillé en CSS par `:root:has([data-meili="drawer"][aria-modal="true"])`.
- À la fermeture (✕, poignée, voile, Échap, `apply`, ou glissé vers le bas : `DrawerGesture`, seuils de Vaul), le focus revient à l'ouvreur et la sortie joue la transition de la feuille de style avant de retirer `inert`. Si la page repasse au-delà du seuil (`matchMedia` sur `media`), le JS rétrograde le tiroir et nettoie.
- Tant que le tiroir couvre la page, la grille derrière n'est pas repeinte : `HeldPaint` garde la dernière réponse et la peint la frame suivant la fermeture (`R-173`). Les compteurs du tiroir, eux, suivent tout de suite.
- Sans JS, tout reste visible en ligne.

## 3. Composition par le thème Pluralia

```blade
{{-- themes/pluralia/resources/views/woocommerce/archive-product.blade.php, tel qu'il est --}}
<x-meilifacets::listing class="mx-auto max-w-site pt-6">
    @if (is_tax('product_cat'))
        <x-meilifacets::facet :facet="ShopFacet::Category" />
    @endif

    <div class="flex items-center justify-between gap-x-4">
        <x-meilifacets::drawer-opener>
            <x-slot:icon>@include('parts.icons.filters')</x-slot:icon>
        </x-meilifacets::drawer-opener>
        <x-meilifacets::drawer class="md:flex-1">
            <x-meilifacets::sort widget="radios" collapsible />
            <x-meilifacets::facets scroll collapsible :with-apply="false" />
            <x-slot:footer>
                <x-meilifacets::reset shape="icon" />
                <x-meilifacets::apply visible-in-drawer />
            </x-slot:footer>
        </x-meilifacets::drawer>
        <x-meilifacets::total class="ml-auto self-center" />
    </div>

    <div class="flex flex-wrap items-center gap-2 pt-4 pb-6">
        <x-meilifacets::active-values />
        <x-meilifacets::reset shape="pill" />
    </div>

    <x-meilifacets::results />
    <x-meilifacets::pagination scroll />
</x-meilifacets::listing>
```

La présentation `Pill` n'est pas passée en attribut : Pluralia la déclare sur la facette « Volume » dans `App\Cms\Products\CatalogueFacets`.

- **Desktop.** L'ouvreur est masqué. Le `drawer` est un simple `<div>` flex : pill « Trier », pills des facettes, puis le pied. Le thème masque le `reset` du pied quand le tiroir n'est pas modal. « 88 articles » est à droite. Dessous : pastilles et « Annuler ».
- **Mobile.** La première ligne contient « Filtre (n) » et le compteur : le tiroir fermé est masqué, puis ouvert en position fixe hors du flux. Dessous : pastilles et « Annuler ».
- **Tiroir ouvert.** En-tête « Filtres ✕ », puis les sections tri et facettes en accordéon, puis le pied : corbeille (`reset` `shape="icon"`, visible seulement quand un filtre est posé) et « Appliquer (X) », qui prend la place de la corbeille quand elle se cache.
- **Aucune duplication.** Le même `<div>` est la rangée en desktop et le tiroir en mobile, sans second rendu ni `display: contents`. Seuls `reset` (deux fois) et `active-count` (ouvreur et Appliquer) apparaissent deux fois, et ce sont des éléments d'état, pas des filtres.

## 4. Arborescence

État au 2026-09-25. Les dossiers `resources/assets/ts/drawer/` et `resources/assets/ts/collapsible/` ont été autorisés et créés ; côté Blade, tout reste à plat dans `components/` (sauf `price/`, antérieur).

**PHP — `app/View/`**
- Objets de valeur : `ActiveValue`, `ActiveValueList`, `ActiveValuePatterns`, `Badge`, `CardDocument`, `CardImage`, `CardSettings`, `CountLabel`, `Disclosure` (libellé, id du panneau, `?Badge`), `ElementId`, `Fill`, `RangeHandle`, `SortChoice`, `SortChoices`, `SortSummary`.
- Rendu de page : `ListingDescription` (publie `labels`, `totalPattern`, `activeValuePatterns`), `ListingScript`, `Preconnect`, `Stylesheet`.
- `Components/` : `ActiveFilters`, `ActiveValues`, `Apply`, `Card`, `ContractComponent`, `Drawer`, `DrawerOpener`, `Facet`, `Facets`, `Listing`, `ListingComponent`, `Pagination`, `Price`, `Reset`, `Results`, `Sort`, `Total`, `Unavailable`.
- Enums du chantier : `Presentation` (`Control`/`Pill`, implémente `Contracts\ValuePresentation`), `SortWidget` (`Listbox`/`Radios`), `ResetShape` (`Text`/`Pill`/`Icon`), `HeadingLevel` ; `Hook` porte les crochets du §5.
- `ElementId` sert `sortLabel`, `sortTrigger`, `sortList`, `sortOption`, `sortPanel`, `sortChoiceName`, `drawer`, `drawerTitle`, `drawerCount`, `applyCount`, `facetPanel`, `facetSelectedCount`, `facetCount`, `facetValueLabel`.

**Blade — `resources/views/components/`**
`active-filters`, `active-values`, `apply`, `card`, `drawer-opener`, `drawer`, `facet`, `facets`, `listing`, `pagination`, `price` (+ `price/fields`, `price/hidden`, `price/range`), `reset`, `reset-icon`, `results`, `sort-radios`, `sort`, `toggle` (déclencheur partagé par facette, prix et tri ; badge seulement si le `Disclosure` en porte un), `total`, `unavailable`.

**TypeScript — `resources/assets/ts/`**
- racine : `filter-queries.ts`, `listing-page.ts` (point d'entrée).
- `collapsible/` : `disclosure-group.ts` (motif disclosure APG, exclusivité des panneaux flottants), `panel-motion.ts` (entrée WAAPI, sortie CSS), `selected-count-view.ts` (badge de chaque déclencheur).
- `drawer/` : `listing-drawers.ts` (les tiroirs d'un listing et la repeinte retenue), `drawer.ts`, `drawer-gesture.ts`, `held-paint.ts`, `inert-page.ts`, `sheet-height.ts`.
- `facets/` : `facet-counts.ts`, `facet-query.ts`, `facets-view.ts`.
- `listing/` : `active-value-list.ts`, `active-values-view.ts`, `browser-history.ts`, `count-entry.ts`, `filter-summary-view.ts`, `focus-landing.ts`, `listing-binding.ts`, `listing-query.ts`, `listing-state.ts`, `listing-url.ts`, `listing.ts`, `reset-focus.ts`, `selection-count-view.ts`, `total-view.ts`.
- `pagination/` : `page-window.ts`, `pagination-view.ts`.
- `price/` : `drawn.ts`, `money.ts`, `price-bound.ts`, `price-control.ts`, `price-inputs.ts`, `price-query.ts`, `price-slider.ts`, `slider-drag.ts`, `slider-keys.ts`.
- `results/` : `card-view.ts`, `results-view.ts`.
- `shared/` : `attributes.ts` (`EXPANDED`, `INSTANT`), `badge.ts`, `contract.ts` (dont `Contract.window`), `count-label.ts`, `css-timing.ts`, `description.ts`, `filter-expression.ts`, `filter-query.ts`, `plan.ts`, `range.ts`, `search-client.ts`.
- `sort/` : `listbox-keys.ts`, `shown-options.ts`, `sort-combobox.ts`, `sort-query.ts`, `sort-radios.ts`, `type-ahead.ts`.
- `dist/listing.js` est reconstruit par `npm run build` et vérifié par `build:check`.

**CSS** — `meilifacets.css` : sections repliable, pastille et tiroir, accrochées à `data-meili` (`R-128`), mobile first, un seul seuil (`48em`).

**Prévu mais n'existe pas** — cités par la v2, absents du code :
- `listing/summary-binding.ts` : jamais créé ; `listing-binding.ts` démarre lui-même les vues de résumé (242 lignes au 2026-09-25) ;
- `selectedCount()` sur `Facet`/`Price` : remplacé par `disclosure()`, qui construit un `Disclosure` avec son `Badge` ;
- `ElementId::facetToggle()` : jamais créé (le déclencheur n'a pas d'id) ; `ElementId::sortSelectedCount()` a existé puis a été retiré avec le badge du tri (audit `R-48`, lot A).
## 5. Crochets `Hook` ajoutés

Tous sont additifs : `Contract::VERSION` reste à 1 (`R-116`). Il faut **demander avant de les ajouter** (`CLAUDE.md` §6).

| Crochet | Rôle | Règle |
| --- | --- | --- |
| `toggle`, `panel`, `selected-count` | Déclencheur, panneau, badge (facettes et prix seulement, jamais le tri) | `facet` et `sort-choices` qui contiennent un `toggle` exigent un `panel` (`whenHolding`) ; `selected-count` optionnel |
| `sort-choices`, `sort-choice`, `sort-choice-row` | Tri en radios ; `sort-choice` (la radio) publie son libellé en `data-label`, `sort-choice-row` est la rangée que le client masque (`R-178`) | l'hôte exige `sort-choice` ; la rangée est optionnelle |
| `sort-chosen` | Tri en force, dans le déclencheur du tri repliable (`R-174`) | optionnel |
| `total` | Compteur de résultats | optionnel |
| `active-values`, `active-value`, `active-value-template` | Pastilles | l'hôte exige le template |
| `active-count` | Nombre nu (n, X) | optionnel |
| `drawer`, `drawer-title`, `drawer-close`, `drawer-open` | Tiroir ; `drawer-close` est aussi posé sur la poignée | l'hôte exige le titre et le bouton de fermeture |
| `drawer-sheet`, `drawer-footer` | Feuille (hauteur, glisser) et pied du tiroir, visés par la feuille et le thème (`R-178`) | optionnels : sans eux, pas de hauteur animée ni de glisser |

Ces lignes reprennent les règles réelles de `RULES` (`shared/contract.ts`) ; la liste complète des crochets est l'enum `Hook`. Un thème qui a surchargé `facet.blade.php` n'a pas de `toggle` : il ne déclenche aucune infraction, sa facette n'est simplement pas repliable (`R-72`).

## 6. Points livrables

**2 · Briques transverses (en Sidebar)**
- 2a `Total`. Tests : Feature (pluriel, `aria-live`) ; ts `total-view`.
- 2b Pastilles (C-7). Tests : Unit `ActiveValueListTest` (valeur repliée, prix à une ou deux bornes) ; Feature (échappement) ; ts (retirer une valeur, retirer le prix).
- 2c `R-162` : `all()`, `active-count`, garde du tri. Tests : ts (deux `reset` justes) ; Unit (second tri → exception).

**3 · Présentation**
- 3a `Presentation`. Tests : Feature, **HTML Sidebar identique à l'octet** ; Unit, défaut `Control` ; garde `R-10` (`Single` + `Pill` refusé).
- 3b `R-151` : change le markup, à demander séparément.

**4 · Repliable (desktop)**
- 4a Déclencheur et `DisclosureGroup` sur facette et prix. Tests : Feature (ids, `aria-controls`) ; ts (exclusivité, Échap, clic extérieur, retour du focus, badge).
- 4b Tri `radios` repliable (C-4). Tests : Feature et ts.
- 4c Prix dans un panneau fermé : piste mesurée à l'ouverture (`R-121`, `R-142`). Tests : ts `price-control`.
- 4d « Voir plus » dans le panneau ; en zéro résultat, le tri et « Annuler » restent visibles (`R-49`). Tests : Feature.

**5 · Tiroir (mobile)**
- 5a `Drawer`, ouvreur, `InertPage`, `matchMedia`. Tests : ts (ARIA posé puis retiré, `inert` restauré, Échap, focus rendu à l'ouvreur, fermeture au passage en desktop) ; Feature (règles du contrat).
- 5b `Apply` autonome et `visible-in-drawer`, accordéon dans le tiroir (C-1, C-3). Tests : ts pour les deux modes (pas de recherche en `immediate`, fermeture) ; Feature (`withApply`).

## 7. Risques et questions ouvertes

**Questions**
- **Q-1** Dans le tiroir, plusieurs sections peuvent-elles être ouvertes en même temps ? ★ Oui.
- **Q-2** Sur quel périmètre poser `inert` ? ★ Sur les frères de chaque ancêtre du tiroir jusqu'à `<body>` : en-tête, pied de page, barre d'administration. Seul ce qui a été posé est restauré.
- **Q-3** Seuil mobile : une variable CSS ne peut pas servir dans une media query. ★ La valeur est écrite en dur dans le CSS, et l'attribut `media` du tiroir la reprend par défaut ; un test de parité vérifie l'accord. Un thème qui veut un autre seuil redéclare le bloc CSS **et** passe `media`.
- **Q-4** Panneaux fermés côté serveur : sans JS, les sections repliables sont fermées. Rien ne filtre de toute façon sans JS (pas de `<form>`), comme pour `sort-list` et « Voir plus ». ★ Fermés côté serveur. L'alternative, ouvrir côté serveur puis fermer en JS, fait flasher les panneaux.
- **Q-5** Où placer « Appliquer » en desktop avec `apply_mode=submit`, la configuration de Pluralia ? La maquette n'en montre pas. ★ À la fin de la rangée (le pied reste dans le flux).
- **Q-6** Le nom de la disposition `Bar` et la décision C-8 deviennent sans objet ; `decisions.md` et `chantier-filtres.md` sont à reprendre.

**Risques**
- **Sans couche supérieure** (option A) : `position: fixed` casse sous un ancêtre qui a `transform`, `filter`, `contain` ou `container-type`, et le `z-index` reste enfermé dans le contexte d'empilement du parent. À vérifier sur le gabarit Pluralia.
- **Si le client ne démarre pas** (infraction au contrat), l'ouvreur mobile ne fait rien, puisque `scripting: enabled` suffit à masquer le tiroir.
- **Panneau déroulant rogné** si la rangée a un `overflow` : le thème doit la faire passer à la ligne (`flex-wrap`) plutôt que défiler.
- **Nom du groupe** : la `<legend>` contient le bouton et son badge, donc le nom du fieldset devient « Marque, 2 sélectionnées ». À écouter à l'étape 6.
- **`[data-meili][hidden]{display:none !important}`** gêne les animations de l'étape 7 (`R-89`).
- **Poids de la description** avec `labels` : à mesurer (`D-08`).
- **C-5 renverse la lettre de `D-07`** : à consigner.
