# MeiliFacets — chantier « barre de filtres » · architecture cible

Voir aussi : [chantier-filtres.md](chantier-filtres.md) · [decisions.md](decisions.md) · [revue.md](revue.md)

**Proposition v2 du 2026-09-24, validée le même jour** (Q-1 → Q-6 selon les ★, dossiers et crochets autorisés ; voir [chantier-filtres.md](chantier-filtres.md)). Aucun code. Les ★ sont des recommandations.

Changements depuis la v1 :
- plus de « disposition » : pas d'enum `Layout`, pas de `bar.blade.php`, pas d'attribut `layout=`. Le thème compose ;
- la présentation (`Control`/`Pill`) est déclarée sur la facette, indépendante de tout le reste ;
- plus de `<dialog>` ni de `display: contents` : le tiroir est un conteneur ordinaire, que le JS promeut en dialogue sur mobile ;
- une brique repliable unique, qui fait le panneau déroulant et la section d'accordéon ;
- plus de dossiers `bar/` : deux dossiers TS par fonctionnalité, à autoriser (C-8 à réviser).

## 1. Principe directeur

- **Des briques indépendantes.** Chaque composant fait une chose et ignore où il est posé. La rangée, la colonne ou la grille, c'est le thème qui les compose, comme il compose déjà la colonne dans `archive-product.blade.php`.
- **Un seul exemplaire DOM** de chaque facette et du tri (`R-95`). La garde `place()` s'étend au tri (`R-162`). Les éléments qui peuvent légitimement apparaître deux fois (`reset`, compteurs) sont peints par `contract.all()`.
- **Le contexte change le CSS, jamais le markup.** Un repliable posé dans le tiroir quand celui-ci n'est pas modal se comporte en panneau déroulant. Posé dans le tiroir modal ou hors du tiroir, il se comporte en accordéon.
- **La plateforme d'abord** : `inert`, le motif disclosure de l'APG, `matchMedia`, `:has()`, `@media (scripting)`. Aucune dépendance.

## 2. Catalogue des briques

| Brique | Composant, attributs | Crochets | TS | Rôle |
| --- | --- | --- | --- | --- |
| Facette | `<x-meilifacets::facet>` + `presentation="control\|pill"`, `collapsible` | `facet`, `toggle`, `panel`, `selected-count` | `FacetsView`, `DisclosureGroup`, `SelectedCountView` | Le `<fieldset>` existant. En mode repliable, la `<legend>` contient le bouton déclencheur (`aria-expanded`, `aria-controls`, badge, chevron) et le panneau porte `panel`. |
| Groupe | `<x-meilifacets::facets>` + `collapsible`, `:with-apply` | `facets` | — | Transmet `collapsible` aux facettes restantes. `:with-apply="false"` retire son bouton `apply`. |
| Prix | `<x-meilifacets::price>` + `collapsible` | idem facette | `PriceControl` | Même repliable. Le badge vaut 1 si une plage est posée (`R-123`). |
| Tri | `<x-meilifacets::sort>` + `widget="listbox\|radios"`, `collapsible` | `sort-choices`, `sort-choice` (+ `toggle`, `panel`) | `SortRadios` | C-4 : `radios` rend un `<fieldset>` de radios, avec le même repliable que la facette. `listbox` (défaut) garde la vue actuelle. |
| Compteur | `<x-meilifacets::total>` | `total` | `TotalView` | « 88 articles », dans une région `aria-live="polite"`. |
| Pastilles actives | `<x-meilifacets::active-values>` | `active-values`, `active-value`, `active-value-template` | `ActiveValuesView` | C-7 : `<button name value>` retirable, prix compris. Clonée depuis un `<template>`. |
| Annuler | `<x-meilifacets::reset>` | `reset` | `FilterSummaryView` | C-2 : inchangée. Le thème surcharge la vue et la traduction. |
| Appliquer | `<x-meilifacets::apply>` + `visible-in-drawer` | `apply`, `active-count` | `SelectionCountView` | « Appliquer (X) ». En `submit`, lance la recherche. Avec `visible-in-drawer`, le bouton est aussi rendu en `immediate`, sans recherche en plus, visible seulement dans le tiroir en sheet. |
| Tiroir | `<x-meilifacets::drawer>` + `heading`, `media`, slot `footer` | `drawer`, `drawer-title`, `drawer-close` | `Drawer`, `InertPage` | Conteneur ordinaire : en-tête « Filtre ✕ », corps (slot), pied. |
| Ouvreur | `<x-meilifacets::drawer-opener>` | `drawer-open`, `active-count` | `Drawer`, `SelectionCountView` | « Filtre (n) ». Le CSS du module le masque hors mobile. |

**Présentation** (C-5) : l'enum `Presentation: string` a deux cas, `Control` et `Pill`, **sans `Radio`**. Le type d'input reste dérivé de `SelectionMode` (`R-10`). Elle est déclarée dans `Facet` (`ProductFacets`), et l'attribut `presentation` la surcharge ponctuellement. La vue ajoute `data-presentation="pill"` uniquement pour `Pill`, donc une facette `Control` sort à l'octet près le HTML actuel.

**Repliable** : un seul markup. Le serveur le rend fermé (`aria-expanded="false"`, panneau `hidden`).
- En rangée : un seul panneau ouvert à la fois ; Échap et le clic extérieur le ferment, et le focus revient au déclencheur.
- Dans le tiroir modal : les sections s'ouvrent indépendamment (Q-1).

**Tiroir** (option A) :
- Desktop : rien de particulier. En-tête et bouton ✕ sont masqués, le pied reste dans le flux.
- Mobile : sous `@media (scripting: enabled)`, le CSS en fait un bottom sheet masqué tant qu'il est fermé.
- À l'ouverture, le JS pose `role="dialog"`, `aria-modal="true"` et `aria-labelledby` (qui pointe sur `drawer-title`), puis rend le reste de la page `inert`. Il gère Échap et verrouille le défilement par `:root:has([data-meili="drawer"][aria-modal])`.
- À la fermeture, le focus revient à l'ouvreur. Si la page repasse en desktop (`matchMedia` sur `media`), le JS ferme et nettoie.
- Un clic sur `apply` dans le tiroir ferme le tiroir.
- Sans JS, tout reste visible en ligne.

## 3. Composition par le thème Pluralia

```blade
<x-meilifacets::listing class="mx-auto max-w-site pt-6">
    @if (is_tax('product_cat'))
        <x-meilifacets::facet :facet="ShopFacet::Category" presentation="pill" />
    @endif

    <div class="flex items-center gap-x-4">
        <x-meilifacets::drawer-opener />
        <x-meilifacets::drawer class="flex flex-1 flex-wrap gap-2">
            <x-meilifacets::sort widget="radios" collapsible />
            <x-meilifacets::facets collapsible :with-apply="false" />
            <x-slot:footer>
                <x-meilifacets::reset />
                <x-meilifacets::apply visible-in-drawer />
            </x-slot:footer>
        </x-meilifacets::drawer>
        <x-meilifacets::total class="ml-auto" />
    </div>

    <div class="flex flex-wrap items-center gap-2 pt-4">
        <x-meilifacets::active-values />
        <x-meilifacets::reset />
    </div>

    <x-meilifacets::results />
    <x-meilifacets::pagination scroll />
</x-meilifacets::listing>
```

- **Desktop.** L'ouvreur est masqué. Le `drawer` est un simple `<div>` flex : pill « Trier », pills des facettes, puis le pied. Le thème masque le `reset` du pied quand le tiroir n'est pas modal. « 88 articles » est à droite. Dessous : pastilles et « Annuler ».
- **Mobile.** La première ligne contient « Filtre (n) » et le compteur : le tiroir fermé est masqué, puis ouvert en position fixe hors du flux. Dessous : pastilles et « Annuler ».
- **Tiroir ouvert.** En-tête « Filtre ✕ », puis les sections tri et facettes en accordéon, puis le pied « Annuler / Appliquer (X) ».
- **Aucune duplication.** Le même `<div>` est la rangée en desktop et le tiroir en mobile, sans second rendu ni `display: contents`. Seuls `reset` (deux fois) et `active-count` (ouvreur et Appliquer) apparaissent deux fois, et ce sont des éléments d'état, pas des filtres.

## 4. Arborescence

**Nouveaux dossiers, à autoriser** : `resources/assets/ts/drawer/` et `resources/assets/ts/collapsible/`. Côté Blade, les fichiers restent à plat dans `components/`, donc aucun dossier nouveau.

**PHP**
- `Enums/Presentation.php` [nouveau] : aspect d'une valeur.
- `Enums/SortWidget.php` [nouveau] : `Listbox`/`Radios`, lu par `from()` comme `HeadingLevel`.
- `Enums/Hook.php` [modifié] : crochets du §5.
- `Listing/Facet.php` [modifié] : paramètre `presentation`, `Control` par défaut (`ChildTermsFacet` le relaie).
- `Listing/ResolvedListing.php` [modifié] : `total()` ; la garde de placement couvre aussi le tri.
- `View/Components/Facet.php`, `Price.php` [modifié] : `collapsible`, `selectedCount()` ; `Facet` gère aussi `presentation`.
- `View/Components/Facets.php` [modifié] : `collapsible` et `withApply` transmis.
- `View/Components/Sort.php` [modifié] : `widget`, `collapsible`, garde ; choix de la vue.
- `View/Components/Total.php`, `ActiveValues.php`, `Apply.php`, `Drawer.php`, `DrawerOpener.php` [nouveau] : chacun prépare sa vue.
- `View/Disclosure.php` [nouveau] : objet de valeur du déclencheur (libellé, ids, nombre coché).
- `View/ActiveValue.php`, `ActiveValueList.php` [nouveau] : une pastille, puis la liste construite depuis l'état, les libellés et `Money`.
- `View/ElementId.php` [modifié] : `facetToggle`, `sortPanel`, `drawer`, `drawerTitle`.
- `View/ListingDescription.php` [modifié] : publie `labels` et `totalPattern`.
- Providers [inchangé] : aucune clé de config.

**Blade** (`resources/views/components/`)
- `facet.blade.php`, `price.blade.php` [modifié] : déclencheur et `panel` seulement si `collapsible`, `data-presentation` seulement si `Pill`.
- `facets.blade.php` [modifié] : transmet `collapsible` et rend `<x-meilifacets::apply>` si `withApply`. La sortie par défaut ne change pas.
- `toggle.blade.php` [nouveau] : le déclencheur partagé par facette, prix et tri.
- `sort-radios.blade.php`, `total.blade.php`, `active-values.blade.php`, `apply.blade.php`, `drawer.blade.php`, `drawer-opener.blade.php` [nouveau] : affichage seul.
- `sort`, `reset`, `active-filters`, `price/*` [inchangé].

**TypeScript** (`resources/assets/ts/`)
- `collapsible/disclosure-group.ts` [nouveau] : ouvre et ferme ; en rangée, exclusivité, Échap et clic extérieur.
- `collapsible/selected-count-view.ts` [nouveau] : badge de chaque déclencheur.
- `drawer/drawer.ts` [nouveau] : ouvrir, fermer, ARIA, Échap, focus, `matchMedia`.
- `drawer/inert-page.ts` [nouveau] : pose `inert` puis restaure exactement ce qu'il a posé.
- `listing/summary-binding.ts` [nouveau] : abonne les vues de résumé au listing, parce que `listing-binding.ts` fait déjà 163 lignes.
- `listing/total-view.ts`, `listing/active-values-view.ts`, `listing/selection-count-view.ts`, `sort/sort-radios.ts` [nouveau].
- `listing/filter-summary-view.ts` [modifié] : `one()` → `all()` (`R-162`).
- `listing/listing-binding.ts` [modifié] : démarre les briques ; `apply` ne lance pas de recherche en `immediate`.
- `shared/contract.ts`, `shared/description.ts` [modifié] ; `dist/listing.js` reconstruit.

**CSS** — `meilifacets.css` [modifié] : sections repliable, pastille et tiroir, accrochées à `data-meili` (`R-128`). Les règles actuelles restent intactes.

## 5. Crochets `Hook` ajoutés

Tous sont additifs : `Contract::VERSION` reste à 1 (`R-116`). Il faut **demander avant de les ajouter** (`CLAUDE.md` §6).

| Crochet | Rôle | Règle |
| --- | --- | --- |
| `toggle`, `panel`, `selected-count` | Déclencheur, panneau, badge | `facet` et `sort-choices` qui contiennent un `toggle` exigent un `panel` (`whenHolding`) |
| `sort-choices`, `sort-choice` | Tri en radios | l'hôte exige `sort-choice` |
| `sort-chosen` | Tri en force, dans le déclencheur du tri repliable (`R-174`) | optionnel |
| `total` | Compteur de résultats | optionnel |
| `active-values`, `active-value`, `active-value-template` | Pastilles | l'hôte exige le template |
| `active-count` | Nombre nu (n, X) | optionnel |
| `drawer`, `drawer-title`, `drawer-close`, `drawer-open` | Tiroir | l'hôte exige le titre et le bouton de fermeture |

Un thème qui a surchargé `facet.blade.php` n'a pas de `toggle` : il ne déclenche aucune infraction, sa facette n'est simplement pas repliable (`R-72`).

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
