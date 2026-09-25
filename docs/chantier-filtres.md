# MeiliFacets — chantier « barre de filtres »

Voir aussi : [revue.md](revue.md) · [decisions.md](decisions.md) · [architecture.md](architecture.md) · [configuration.md](configuration.md) · [lots.md](lots.md)

Suivi d'avancement du chantier ouvert le 2026-09-24 sur la branche `feat/filter-bar`, sous
l'entrée parapluie **`R-48`** (« rien pour le mobile »). Ce fichier est un tableau de bord : les
constats vont dans `revue.md`, les décisions dans `decisions.md`, ici on ne tient que l'état.

Un travail antérieur sur le même sujet a été mis de côté sans être relu ni validé
(`git stash list` : « wip R-48 session perdue (2026-09-24) »). On ne le rejoue pas ; on peut y
piocher une idée, une fois la décision correspondante prise ici.

---

## Objet

Donner au module une **seconde disposition** des filtres, sans toucher à la première :

| Disposition | Rendu | Statut |
| --- | --- | --- |
| `Sidebar` | colonne de cases à cocher, listing mis à jour en direct | existante, **par défaut, inchangée** |
| `Bar` | desktop : rangée de pills, un panneau par filtre · mobile : une pill « Filtre (n) » et un tiroir | à construire |

S'y ajoutent des briques communes aux deux dispositions : compteur de résultats, pastilles des
filtres actifs, lien « Annuler ». Et un choix de **présentation des valeurs** (cases, radios,
pastilles type « 15 ML ») et d'**activation** propre à chaque facette.

Le module rend un markup brut et accessible ; le thème Pluralia en porte l'apparence (`D-01`).
Aucune logique dans les vues : les classes de composant préparent, le Blade affiche.

## Sources de design

Fichier Figma `URXURsgp0hWOmDeDVA37qs`, lu **exclusivement par le MCP Figma** —
`get_design_context` puis `get_variable_defs` pour toutes les valeurs ; `get_screenshot` ne sert que
de référence d’ensemble, jamais à relever un détail. Une valeur absente se demande.

| Écran | Nœud |
| --- | --- |
| Barre desktop | `19:1895` |
| Barre mobile | `17:641` |
| Tiroir mobile | `17:754` |

Pas de maquette du panneau ouvert en desktop : son contenu reprend celui des sections du tiroir.
Les lignes « Lorem ipsum › » du tiroir sont des exemples de filtres, pas un motif à reproduire.

## Méthode

- **Un point à la fois** (`D-03`) : une étape n'est ouverte que lorsque la précédente est fermée.
- **Sous-agents** pour la lecture, la conformité, la revue en cinq passes et les comparaisons
  Figma ↔ Playwright ; le fil principal garde les conclusions.
- Une étape est fermée selon la définition de `CLAUDE.md` § 5 : cinq passes rapportées,
  `composer check` vert, `ddev exec vendor/bin/phpunit --testsuite Modules` vert, page ouverte dans
  un navigateur, registre et décisions à jour.
- **Jamais sans demander** : nouvelle clé de config, nouveau répertoire, nouveau crochet `Hook`,
  décision validée contredite.

---

## Décisions à prendre

Issues de la passe de conformité du 2026-09-24. Chacune se tranche **au début de l'étape qui en a
besoin**, pas avant.

| # | Question | Touche | Étape | État |
| --- | --- | --- | --- | --- |
| C-1 | Desktop en `immediate`, tiroir en `submit` : un mode par disposition alors que `apply_mode` est unique par listing et le DOM unique (`R-95`) | « Déclenchement de la recherche », `Q-24`, `R-51` | 5 | **tranché 2026-09-24** — on suit `apply_mode` comme aujourd'hui : `submit` → bouton « Appliquer (X) », `immediate` → chaque clic cherche. Dans le tiroir, « Appliquer » reste affiché même en `immediate` et ferme le tiroir (repère UX) |
| C-2 | « Annuler » du tiroir : remise à zéro (comme `Reset`) ou abandon des cases en attente (deux états, écarté par `D-10`) | `D-10`, « Deux familles de gestes » | 5 | **tranché 2026-09-24** — « Annuler » est le `Reset` (« Tout effacer ») existant ; composant à part, libellé et style surchargés par le thème (vue + catalogue de traductions) |
| C-3 | « Appliquer (N) » : obtenir N impose une recherche par case cochée, ce qui retire au mode `submit` sa raison d'être | `configuration.md` « Quand la recherche part », « Comportement après échecs répétés » | 5 | **tranché 2026-09-24** — X = nombre de valeurs cochées, connu sans recherche supplémentaire |
| C-4 | Tri en pill (listbox) en desktop, en radios dans le tiroir : deux widgets pour un réglage | `R-95`, `R-162`, décision listbox du tri | 4 | **tranché 2026-09-24** — tri en radios dans toute la disposition `Bar` (pill et tiroir) ; `Sidebar` garde la liste déroulante |
| C-5 | Présentation par facette dans le module : `D-07` dit que le module ignore que ce sont des pastilles ; `Radio` concurrence `SelectionMode` | `D-07`, `D-01`, `R-10`, `R-05` | 3 | **tranché 2026-09-24** — `Presentation` déclarée sur la facette dans `ProductFacets` (+ attribut de composant pour une surcharge ponctuelle) ; renverse la lettre de `D-07`, à consigner dans `decisions.md` |
| C-6 | Activation par config : concurrence `ProductFacets` ; une facette présente dans l'URL doit continuer de filtrer | `R-89`, `T-41` | 3 | **tranché 2026-09-24** — activer = déclarer dans `ProductFacets` ; aucune clé de config |
| C-7 | Pastilles des filtres actifs retirables une à une | `R-47`, `T-08`, `D-07` | 2 | **tranché 2026-09-24** — pastilles retirables construites, libellés publiés dans la description ; ferme `R-47` |
| C-8 | Nouveaux répertoires (`views/components/bar/`, `ts/bar/` ou `ts/drawer/`) et nouveaux crochets `Hook` | « Structure de dossiers », `R-116` | 1 | **tranché 2026-09-24** — dossiers `bar/` autorisés, **caducs** depuis l'architecture v2 (plus de disposition dans le module) ; nouveaux dossiers `ts/drawer/` et `ts/collapsible/` autorisés le 2026-09-24 |

---

## Étapes

### 0 · Préparation — ✅ fermée le 2026-09-24

- [x] Travail non validé mis de côté (stash dans le module et dans Pluralia)
- [x] Branche `feat/filter-bar` créée depuis `main`
- [x] Maquettes lues par le MCP Figma (trois nœuds ci-dessus)
- [x] Passe de conformité : contradictions listées en C-1 → C-8
- [x] Ce fichier

### 1 · Architecture cible — ✅ fermée le 2026-09-24

Aucun code. Proposition v2 rendue le 2026-09-24 : [chantier-filtres-architecture.md](chantier-filtres-architecture.md). La v1 (dispositions `Sidebar`/`Bar` dans le module) est abandonnée : la disposition revient au thème, le module fournit des briques ; tiroir = conteneur ordinaire promu en dialogue sur mobile (option A), jamais `display: contents`, jamais de filtre dupliqué.

- [x] arborescence PHP / Blade / TS, un fichier par responsabilité
- [x] liste des crochets `Hook` ajoutés (additifs, `Contract::VERSION` inchangé, `R-116`)
- [x] clés de config : aucune recommandée (disposition et présentation en attributs / déclaration)
- [x] enums `Presentation` (sans cas `Radio`, `R-10`) et `SortWidget` ; plus d'enum `Layout` (v2)
- [x] C-1 à C-8 tranchés par Louis (2026-09-24)
- [x] Q-5 tranchée 2026-09-24 : Pluralia **reste en `apply_mode=submit` pour évaluer le rendu** (bouton « Appliquer » en fin de rangée desktop) ; bascule en `immediate` envisagée après validation visuelle de Louis
- [x] Q-1 → Q-4 et Q-6 validées 2026-09-24 selon les ★ de l'architecture v2 (sections multiples dans le tiroir, `inert` sur l'entourage, seuil mobile en dur + attribut `media`, repliables fermés côté serveur, `Bar` retiré)
- [x] autorisés 2026-09-24 : dossiers `ts/drawer/` et `ts/collapsible/`, crochets `Hook` du § 5 de l'architecture
- [x] recommandations UI/animations toutes retenues (2026-09-24), réparties en UX-1 → UX-4 et ANIM-1 → ANIM-13
- [x] décisions C-1 → C-8 et Q-1 → Q-6 reportées dans `decisions.md` (renversement de la lettre de `D-07` noté à sa place) ; `revue.md` : `R-48`, `R-47`, `T-08` mis à jour, `R-163` (total) et `R-164` (présentation) ouverts, file d'attente réordonnée

**Recette —** un schéma validé par Louis, où chaque fichier a une responsabilité en une phrase.

### 2 · Briques transverses — ✅ fermée le 2026-09-24

Utiles aux deux dispositions, livrées d'abord dans `Sidebar`.

- [x] 2a · compteur de résultats (« 88 articles »), annoncé par `aria-live` — `R-163` fermé le 2026-09-24
- [x] 2b · pastilles des filtres actifs, y compris la borne de prix (`R-123`) ; libellés lus dans les
      valeurs rendues, repliées comprises (`R-57`) — C-7, `R-47` fermé le 2026-09-24
- [x] lien « Annuler » : le `Reset` existant (C-2), posé par le thème à côté des pastilles —
      `<x-meilifacets::reset shape="pill" />` après `<x-meilifacets::active-values />` dans
      `archive-product.blade.php` (Pluralia `2f7a473`)
- [x] 2c · doublons légitimes peints partout (`reset`, `active-filters`), crochet `active-count`, garde du
      tri — `R-162` fermé le 2026-09-24 ; deux listings du même nom détachés dans `R-166` (hors chantier)

**Recette —** sur `/boutique` en `Sidebar`, cocher deux valeurs fait apparaître deux pastilles et
met le compteur à jour ; retirer une pastille décoche la valeur ; « Annuler » vide tout.

### 3 · Présentations et activation par facette — ✅ fermée le 2026-09-24

- [x] C-5 et C-6 tranchés
- [x] 3a · présentation par facette (`R-164`, fermé le 2026-09-24) : contrat ouvert `ValuePresentation`, enum `Control`/`Pill`, garde `R-10` à la déclaration, `data-presentation` sauf `Control`, style pastille du module ; Contenance en `Pill` dans Pluralia (`CatalogueFacets`, Pluralia `b2c84db`)
- [x] 3b · compteur de valeur hors du nom accessible (`R-151`, fermé le 2026-09-24) : compteur gardé dans le `<label>`, nom par `aria-labelledby` vers le `<span>` du libellé, description par `aria-describedby` ; chiffres tabulaires ; compteur masqué visuellement en pastille (validé par Louis) ; crochets inchangés, `Contract::VERSION` intact. Bouton « Voir plus » habillé en chemin (`R-168`)
- [x] cible de 2.75rem au pointeur grossier pour la pastille (44 px mesurés en 3a et 3b) ; les rangées de cases restent à 37,8 px au pointeur grossier, comme avant le chantier — au-dessus du minimum AA (24 px, WCAG 2.5.8), sous la cible AAA de la décision « Hauteur des contrôles », qui ne vise que les boutons : à revoir à l'étape 6
- [x] colonne actuelle rendue à l'identique quand rien n'est configuré (3a : HTML identique ; 3b : seuls `aria-labelledby` et l'id du libellé s'ajoutent, positions à 0,1 px)

### 4 · Facette repliable (rangée desktop) — ⏳ en cours (4a, 4b et 4c livrées et commitées ; reste 4d)

- [x] C-4 tranché (tri en radios par attribut, `SortWidget`)
- [x] 4a · pill déclencheur + badge du nombre de valeurs cochées (`R-170`, 2026-09-24, `2cf8acf`) ; prix repris en 4c
- [x] 4a · panneau déroulant, un seul ouvert à la fois, fermeture par Échap, clic extérieur et focus parti ; sections indépendantes sous `[aria-modal="true"]` (point d'extension de l'étape 5)
- [x] 4b · tri en radios repliable (`R-174`, 2026-09-25, `7b971d8`) : `SortWidget`, crochets `sort-choices`/`sort-choice`, badge jamais rempli, tri immédiat dans les deux modes (`D-10`), garde `placeSort()` pour les deux widgets
- [x] mobile first (2026-09-25, Louis) : section d'accordéon en ligne en base, panneau flottant ≥ 48em, `DisclosureGroup` lit la feuille
- [x] 4c · prix dans un panneau fermé (`R-172`, 2026-09-24, `25a5aa3`) : poignées en fraction CSS, piste mesurée à chaque mouvement — rien n'était mis en cache ; badge à 1 si une plage est tenue (`priceFilterCount()`, `R-123`)
- [ ] 4d-1 · zéro résultat : la barre ne disparaît pas entièrement (`R-49`)
- [ ] 4d-2 · « Voir plus » dans un panneau (`R-46`, `R-82`–`R-86`)
- [x] UX-1 · le panneau reste ouvert pendant une multi-sélection ; le focus ne bouge pas quand la grille est repeinte (4a, test ts en `immediate`)
- [x] UX-2 · un panneau qui déborderait à droite s'aligne sur le bord droit de sa pill (4a, `data-align-end` ; revérifié sur « Prix » en 4c : 390 px, panneau 159–355, bord droit sur la pill)

### 5 · Tiroir mobile — ✅ fermée le 2026-09-25

Commitée par fonctionnalité : `7b971d8` (tri en radios, 4b), `40822fd` (panneaux animés, valeurs
gardées en place, badge), `860d58c` (`apply` autonome, formes de `reset`, `ResetFocus`), `d62e8d9`
(tiroir, geste, hauteur, feuille, docs). Composition du thème : Pluralia `2f7a473`. Chaque case
ci-dessous relue dans le code le 2026-09-25. Les choix du lot restent listés « en attente de
validation » dans `decisions.md` (bloc « Tiroir, barre de filtres et repliables »).

- [x] C-1, C-2 et C-3 tranchés
- [x] 5a (`R-173`) · ouvreur « Filters » (pastille `active-count`, icône `filters.svg`) promouvant le conteneur des filtres en bottom sheet (option A : pas de `<dialog>`, `role="dialog"` + `aria-modal` + `inert` sur l'entourage, jamais `display: contents`)
- [x] même DOM que la rangée desktop (`R-95`) : aucun filtre dupliqué ; tri et facettes dans le tiroir, qui est la rangée en desktop
- [x] sections en accordéon indépendantes ; 5b (`R-175`) · pied « Tout effacer » (icône) / « Appliquer (X) » (`apply visible-in-drawer`, `:with-apply="false"`)
- [x] morph poubelle ↔ « Appliquer » (2026-09-25, `R-175`) : seule la largeur d'« Appliquer » est animée (270 ms `--meili-ease-resize`), poubelle hors flux, entrée 200 ms par `@starting-style`, sortie 150 ms par `display … allow-discrete` ; mouvement réduit : fondu seul, largeur instantanée
- [x] valeurs gardées en place dans un panneau ouvert (2026-09-25, `R-173`) ; sections refermées après la sortie du tiroir ; « Appliquer » sheet-only en `immediate`
- [x] UX-3 · défilement de la page verrouillé (`:root:has(...)`), `overscroll-behavior: contain`, en-tête et pied hors du défilement (seul le corps défile), hauteur max `100dvh - 4.5rem`, `env(safe-area-inset-bottom)` sous le pied
- [x] glisser pour fermer (ANIM-12, avancé à la demande de Louis), poignée, hauteur qui suit le contenu
- [x] finitions (2026-09-25, `R-173`/`R-175`) : `apply visible-in-drawer`, focus rendu après la poubelle (`ResetFocus`), valeurs à 0 en `aria-disabled` (focus gardé, coche refusée), grille différée derrière le sheet (`HeldPaint`), styles en ligne du sheet retirés hors ouverture, `filters.svg` en `#000`, `reset shape="pill"` et dessin de la colonne rendu à `25a5aa3` hors hauteur

### 6 · Accessibilité — à venir

Audit séparé, par sous-agent : motif APG de chaque widget, piège et retour du focus, Échap,
`aria-expanded`/`aria-controls`, annonces, `forced-colors`, défense de `hidden` (`R-72`).

- [ ] UX-4 · nom accessible explicite du bouton : « Appliquer 2 filtres » (libellé visuel inchangé)
- [ ] A11Y-1 · deux `<x-meilifacets::total>` sur une page font deux régions `aria-live`, donc deux annonces (`R-163`)
- [ ] rangées de cases à 37,8 px au pointeur grossier (relevé à l'étape 3) : au-dessus du minimum AA
      (24 px, WCAG 2.5.8), sous la cible de 44 px des pastilles — à trancher
- [ ] contraste du Vert `#A7C5B7` de la maquette (≈ 1,8:1, sous les 3:1 de WCAG 1.4.11
      pour un composant d'interface) : à arbitrer avant l'étape 8

### 7 · Animations — à venir

CSS natif, sans dépendance : `@starting-style`, `transition-behavior: allow-discrete`,
`transform`/`opacity` uniquement, variante `prefers-reduced-motion` pour chaque mouvement. Choix
confirmés avec les skills `pick-ui-library`, `css-animations`, `animation-accessibility`.

Recommandations retenues le 2026-09-24 (revue `emil-design-eng`) :

Relu contre le code le 2026-09-25 (`meilifacets.css` = la feuille du module, numéros de ligne à `d62e8d9`) :

- [ ] ANIM-1 · prérequis : `[data-meili][hidden]` sans `!important` sur les briques animées, sorties par `transition-behavior: allow-discrete` + `@starting-style` (`R-89`). **Écart** : la règle est inchangée (`meilifacets.css` l. 7–10, `display: none !important`). L'objectif est pourtant atteint là où une sortie existe — panneaux (`[data-meili="panel"][hidden]`, `display … allow-discrete`), liste du tri, poubelle du pied — parce qu'une transition l'emporte sur `!important` dans la cascade (précision consignée dans `decisions.md`, bloc « Tiroir… », *État ouvert/fermé*). Le tiroir passe par `aria-modal` + `visibility` différée, sans `hidden` (`R-173`). Prérequis sans objet à la lettre : **à clore comme caduc, sur accord de Louis**
- [ ] ANIM-2 · tokens sur `[data-listing]`, surchargeables par le thème : posés (l. 35–46 : `--meili-ease` = `cubic-bezier(0.23, 1, 0.32, 1)`, `--meili-ease-drawer`, `--meili-ease-resize`, `--meili-ease-content`, `--meili-duration-resize/content/drawer-in/drawer-out/fade/panel-in/panel-out/hover`). **Écarts** : le jeton s'appelle `--meili-ease`, pas `--meili-ease-out` ; des durées restent écrites en dur — appuis `120ms` (l. 194) et `150ms` (l. 306, 386, 657, 679, 1185), chevrons `160ms` (l. 329, 741), liste du tri `160ms` (l. 776–778, 992–993), entrée de la poubelle `200ms` (l. 1191–1194, 1259–1260), mouvement réduit du pied `150ms` (l. 1200, 1209)
- [ ] ANIM-3 · panneau desktop : entrée 180 ms (`PanelMotion::pop()`, `--meili-duration-panel-in`), sortie 120 ms (l. 517–523), `transform-origin` côté pill (l. 518, 532) — mesuré (`R-176`). **Écart** : les keyframes `translateY(-4px) scale(0.97)` ne sont pas reprises ; `PanelMotion::#entry()` et `[data-meili="panel"][hidden]` (l. 422–425) font `scale(0.96)` sans translation
- [x] ANIM-4 · pas d'animation au passage d'une pill à l'autre ni à la fermeture par Échap ou par Tab : `data-instant` (l. 1037–1040), posé puis retiré par `disclosure-group.ts` ; mesuré le 2026-09-25 (`R-176`)
- [x] ANIM-5 · tiroir : 350/250 ms (`--meili-duration-drawer-in/out`), `--meili-ease-drawer`, voile `--meili-scrim` à 50 % en opacité, sans `backdrop-filter` — `R-173`
- [ ] ANIM-6 · accordéon. **Livré autrement** (`R-173`, décision *Sections*) : fondu + `scale(0.96)` par WAAPI (`panel-motion.ts`), durée selon la hauteur (150–270 ms), sortie ×0,75, chevron 160 ms (l. 329, 741). **Écarts** avec la recommandation : chevron 160 ms au lieu de 200 ; `scale(0.96)` au lieu de `translateY(4px)` ; pas d'`interpolate-size` — la hauteur du sheet est mesurée par `SheetHeight` et écrite en ligne, la feuille interpole `height` (l. 1166), ce que la recommandation écartait (« jamais `height` animée à la main »). À valider tel quel ou à reprendre
- [x] ANIM-7 · appui : « Appliquer » (l. 875–877) et ouvreur (l. 863–865) `scale(0.97)` 150 ms, poubelle `scale(0.94)` (l. 867–869), pastilles de valeur `scale(0.96)` 120 ms (l. 888–890), ✕ `scale(0.75)` ; neutralisés en mouvement réduit (l. 1022–1027) ; survol sous `(hover: hover) and (pointer: fine)` — `R-173`, `R-176`
- [x] ANIM-8 · badge `active-count` : `opacity` 0 + `scale(0.9)`, `--meili-duration-fade`, `--meili-ease`, au seul passage 0 → 1, par WAAPI (`listing/count-entry.ts`, appelé par `selection-count-view.ts`) ; fondu seul en mouvement réduit — `R-173`
- [ ] ANIM-9 · pastilles actives : entrée `opacity` + `scale(0.95)` 150 ms, pas d'animation de sortie. **Non fait** : aucune transition ni `animate()` sur `active-value` (seul le survol et l'appui)
- [ ] ANIM-10 · grille : `aria-busy`, atténuation seulement au-delà de ~150 ms de recherche. **Non fait** : aucun `aria-busy` dans `resources/assets/ts`
- [x] ANIM-11 · `prefers-reduced-motion` : plus de `translate`/`scale`, fondus conservés — panneaux (l. 974–979, 150 ms, 120 ms flottants l. 1029–1034), liste du tri (l. 985–1000), appuis et chevrons sans transition (l. 1002–1027), tiroir en fondu 150 ms (l. 1229–1250), poubelle en fondu (l. 1257–1273), WAAPI en opacité seule (`PanelMotion::#entry()`, `CountEntry::#keyframes()`), « Appliquer » sans largeur animée (l. 1253–1255), poignée du prix sans transition (l. 1495–1501 ; son `scale(1.12)` au focus reste, instantané)
- [x] ANIM-12 · glisser pour fermer (`drawer-gesture.ts` : vitesse sur 100 ms, `FLICK_SPEED` 0,11 px/ms, seuil d'un quart, étirement vers le haut, voile lié par `--meili-scrim-shown`) ; **au doigt** depuis `R-176`, validé en tactile CDP le 2026-09-25
- [ ] ANIM-13 · question au graphiste : intention d'animation du composant Figma « Animation filtrage » (le MCP n'y trouve aucune donnée de mouvement)

### 8 · Habillage Pluralia — à venir

Dans le thème, par les crochets `data-meili` et non par les classes (`R-128`), avec les tokens de
`theme-vars.css`. Validation par sous-agents, desktop et mobile : l'ensemble comparé à
`get_screenshot`, **chaque détail** comparé aux valeurs de `get_design_context` (styles calculés
relevés dans Playwright).

Déjà décidé :

- [x] bordure encre des pills desktop, dans le thème : `[data-meili="toggle"]` en `--color-ink` à partir
      de `48em` (`components/listing.css`, Pluralia `2f7a473`) ; pastilles de valeur et ouvreur idem
- [x] cases à cocher et radios laissées **natives** pour l'instant (aucun habillage dans le thème)

À trancher :

- [ ] compteur de valeur « (5) » de la maquette : l'afficher ou non (masqué visuellement en pastille
      depuis 3b, `R-151`)
- [ ] token du Vert `#A7C5B7` : nom et usage dans `theme-vars.css`, après l'arbitrage de contraste de
      l'étape 6

---

## Journal

| Date | Étape | Fait |
| --- | --- | --- |
| 2026-09-24 | 0 | Stash, branche, lecture Figma, passe de conformité, ce fichier |
| 2026-09-24 | 1 | Architecture v1 rendue, C-1 → C-8 tranchés ; v1 abandonnée (disposition = thème), v2 validée ; UX-1 → 4 et ANIM-1 → 13 retenus ; registres à jour (`R-163`, `R-164`) — étape fermée |
| 2026-09-24 | 2a | Compteur livré (`R-163`), faux moteur de test corrigé (`R-165`), style par défaut aligné sur les sœurs ; `<x-meilifacets::total />` posé sous le tri dans le thème, non commité, à replacer à l'étape 8 |
| 2026-09-24 | 2b | Pastilles actives livrées (`R-47`, `T-08`) : état appliqué seulement en `submit`, retrait = ordre (`D-10`), focus jamais sur `<body>` ; `labels` gardés dans la description (+829 o, mesuré, sans risque : encodage `JSON_HEX_TAG`, écriture en texte) ; `<x-meilifacets::active-values />` posé dans le thème, non commité |
| 2026-09-24 | 2b | Pastilles livrées (`R-47`, `T-08`) : état appliqué seulement (Louis), retrait = ordre (`D-10`, validé) ; description +829 o ; `<x-meilifacets::active-values />` posé sous le compteur dans le thème, non commité |
| 2026-09-24 | 2c | `R-162` : `reset`/`active-filters` par `contract.all()`, crochet `active-count` (sélection en attente comprise, masqué à zéro, aucune vue avant 5a/5b), garde du tri (`placeSort()`) ; doublons temporaires posés puis retirés du thème ; deux listings du même nom proposés en entrée à part |
| 2026-09-24 | 2c→3 | Refactor `R-167` : registre de placement sorti de `ResolvedListing` dans `PagePlacement`, tri et facettes dans un seul registre indexé par `PlacedControl`, message du tri corrigé ; aucun changement de comportement |
| 2026-09-24 | 3a | `R-164` : `ValuePresentation` (ensemble ouvert, Louis), `Presentation` `Control`/`Pill`, garde `R-10` dans `Facet::presentedAs()`, HTML `Control` identique à l'octet (test sur la vue d'avant), CSS pastille ; Contenance en `Pill` dans `CatalogueFacets`, non commité |
| 2026-09-24 | 3 | 3a présentation par facette (`R-164`, contrat ouvert `ValuePresentation`, surcharge par gabarit confirmée) ; 3b compteur hors du nom (`R-151`, option C `aria-labelledby`), vue `facet` sans calcul, bouton « Voir plus » habillé (`R-168`), `R-169` ouvert — étape fermée ; titres des étapes 4 et 5 remis au vocabulaire de l'architecture v2 |
| 2026-09-24 | 4a | `R-170` : `collapsible` sur `facet`/`facets` (prix exclu), déclencheur dans la `<legend>`, badge décrit (`aria-describedby`, `aria-hidden`, vidé à zéro), panneau fermé serveur, `DisclosureGroup` + `SelectedCountView`, UX-1/UX-2 ; HTML sans `collapsible` identique à l'octet ; `collapsible` posé sur `<x-meilifacets::facets>` dans le thème, non commité ; `R-171` ouvert (réglages d'index sans le prix, suite `Modules` rouge avant comme après) |
| 2026-09-24 | 4a (à côté) | `R-171` fermé : réglages d'index sans le prix, cause = pont MeiliScout construit avant WooCommerce ; `DeferredIndexAttributes`/`DeferredCardProjector`, trait `KeepsTheIndexOut` ; suite `Modules` 472 verte (`1166fd6`) |
| 2026-09-24 | 4c | `R-172` : `collapsible` sur `price`, exclusion de `Facets::collapses()` retirée ; aucune mesure en cache (poignées en `--at`, `ratioAt()` relu à chaque mouvement), donc aucun code de mesure changé ; badge par `SelectionHolder` (`FacetsView`, `PriceControl`) ; HTML sans `collapsible` identique à l'octet ; vérifié en `immediate` (config de Pluralia non commitée), `submit` couvert par les tests TS |
| 2026-09-25 | 5a, 4b, 5b | `R-173` tiroir (ouvreur, dialog, `inert`, verrou, poignée, glisser, hauteur suivie, sections animées), refonte mobile first des repliables, `R-174` tri en radios, `R-175` pied « Tout effacer / Appliquer » ; `--meili-control` 3rem ; thème recomposé en rangée pleine largeur, non commité ; `ActiveValuesComponentTest` lit le catalogue ; suite `Modules` 508 verte |
| 2026-09-25 | 5a, 5b (suite) | `R-173` : espacements Figma exacts (40 / 24 × 32 / 32 / 16 × 32), encre Pluralia sortie du module, repos du tiroir sans transition (`data-closing`), sections refermées après la sortie, valeurs à 0 gardées en place panneau ouvert, badge 0 → 1, appuis, panneaux desktop élargis (`--meili-panel-*`) ; `R-175` : morph poubelle ↔ « Appliquer », « Appliquer » sheet-only en `immediate` ; « Tout effacer » texte en pastille (Louis) ; suite `Modules` 511 verte, non commité |
| 2026-09-25 | 5a, 5b (finitions) | `visible-in-drawer`, focus après la poubelle, `aria-disabled`, grille différée derrière le sheet (INP 64–112 ms, ×4), hauteur en ligne retirée hors sheet ouvert, `filters.svg` `#000`, `reset shape="pill"` ; audit colonne contre `25a5aa3` : 0 écart calculé hors hauteur ; `composer check` vert, suite `Modules` 512 verte, non commité |
| 2026-09-25 | 5a (revue animations) | `R-176` : glisser au doigt (exclusions par sélecteur au lieu de `hasPointerCapture`), tiroir `inert` pendant la sortie, ✕ sans `scale` au focus, panneaux 180/120 ms et instantanés au clavier et de pill à pill (ANIM-3, ANIM-4), mouvement réduit (sortie de section 150 ms, fondu du tri 160 ms) ; client 484 verts, suite `Modules` 512 verte, non commité |
| 2026-09-25 | 4b, 5 | Commits par fonctionnalité : `7b971d8` (tri en radios), `40822fd` (panneaux animés, valeurs gardées en place), `860d58c` (`apply` autonome, formes de `reset`), `d62e8d9` (tiroir) ; thème Pluralia `b2c84db` (Contenance en pastilles) et `2f7a473` (rangée et tiroir) |
| 2026-09-25 | 5, 7 | Plan relu contre le code : étape 5 fermée, `R-173` → `R-176` fermés ; ANIM-4, 5, 7, 8, 11, 12 prouvés ligne à ligne, écarts précis écrits pour ANIM-1, 2, 3, 6 ; 4d découpée (4d-1 zéro résultat, 4d-2 « Voir plus » en panneau) ; étapes 6 et 8 complétées |
