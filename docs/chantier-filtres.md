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
- [ ] lien « Annuler » : le `Reset` existant, placé à côté des pastilles
- [x] 2c · doublons légitimes peints partout (`reset`, `active-filters`), crochet `active-count`, garde du
      tri — `R-162` fermé le 2026-09-24 ; deux listings du même nom détachés dans `R-166` (hors chantier)

**Recette —** sur `/boutique` en `Sidebar`, cocher deux valeurs fait apparaître deux pastilles et
met le compteur à jour ; retirer une pastille décoche la valeur ; « Annuler » vide tout.

### 3 · Présentations et activation par facette — ⏳ en cours (3a, 3b fermées)

- [x] C-5 et C-6 tranchés
- [x] 3a · présentation par facette (`R-164`, fermé le 2026-09-24) : contrat ouvert `ValuePresentation`, enum `Control`/`Pill`, garde `R-10` à la déclaration, `data-presentation` sauf `Control`, style pastille du module ; Contenance en `Pill` dans Pluralia (non commité)
- [x] 3b · compteur de valeur hors du nom accessible (`R-151`, fermé le 2026-09-24) : compteur gardé dans le `<label>`, nom par `aria-labelledby` vers le `<span>` du libellé, description par `aria-describedby` ; chiffres tabulaires ; compteur masqué visuellement en pastille (validé par Louis) ; crochets inchangés, `Contract::VERSION` intact. Bouton « Voir plus » habillé en chemin (`R-168`)
- [ ] cible de 2.75rem au pointeur grossier (« Hauteur des contrôles »)
- [ ] `Sidebar` rendu à l'identique quand rien n'est configuré

### 4 · Disposition `Bar` — desktop — à venir

- [ ] C-4 tranché
- [ ] pill déclencheur + badge du nombre de valeurs cochées
- [ ] panneau déroulant, un seul ouvert à la fois, fermeture par Échap et clic extérieur
- [ ] prix dans un panneau fermé : piste mesurée à l'ouverture (`R-121`, `R-142`)
- [ ] « Voir plus » dans un panneau (`R-46`, `R-82`–`R-86`)
- [ ] zéro résultat : la barre ne disparaît pas entièrement (`R-49`)
- [ ] UX-1 · le panneau reste ouvert pendant une multi-sélection ; le focus ne bouge pas quand la grille est repeinte
- [ ] UX-2 · un panneau qui déborderait à droite (dernière pill, « Prix ») s'aligne sur le bord droit de sa pill

### 5 · Disposition `Bar` — tiroir mobile — à venir

- [ ] C-1, C-2 et C-3 tranchés
- [ ] pill « Filtre (n) » ouvrant un `<dialog>` en bottom sheet
- [ ] même DOM que la barre desktop (`R-95`) sans que `display:none` du dialog fermé ne la masque
- [ ] sections en accordéon, pied « Annuler » / « Appliquer (N) »
- [ ] UX-3 · défilement de la page verrouillé, `overscroll-behavior: contain`, en-tête et pied collants, hauteur max en `dvh`, `env(safe-area-inset-bottom)` sous le pied

### 6 · Accessibilité — à venir

Audit séparé, par sous-agent : motif APG de chaque widget, piège et retour du focus, Échap,
`aria-expanded`/`aria-controls`, annonces, `forced-colors`, défense de `hidden` (`R-72`).

- [ ] UX-4 · nom accessible explicite du bouton : « Appliquer 2 filtres » (libellé visuel inchangé)
- [ ] A11Y-1 · deux `<x-meilifacets::total>` sur une page font deux régions `aria-live`, donc deux annonces (`R-163`)

### 7 · Animations — à venir

CSS natif, sans dépendance : `@starting-style`, `transition-behavior: allow-discrete`,
`transform`/`opacity` uniquement, variante `prefers-reduced-motion` pour chaque mouvement. Choix
confirmés avec les skills `pick-ui-library`, `css-animations`, `animation-accessibility`.

Recommandations retenues le 2026-09-24 (revue `emil-design-eng`) :

- [ ] ANIM-1 · prérequis : `[data-meili][hidden]` sans `!important` sur les briques animées, sorties par `transition-behavior: allow-discrete` + `@starting-style` (`R-89`)
- [ ] ANIM-2 · tokens `--meili-ease-out` (`cubic-bezier(0.23, 1, 0.32, 1)`), `--meili-ease-drawer` (`cubic-bezier(0.32, 0.72, 0, 1)`), `--meili-duration-*` sur `[data-listing]`, surchargeables par le thème
- [ ] ANIM-3 · panneau desktop : `opacity` + `translateY(-4px) scale(0.97)`, `transform-origin` côté pill ; entrée 180 ms, sortie 120 ms
- [ ] ANIM-4 · pas d'animation au passage d'une pill à l'autre (`data-instant`) ni à la fermeture par Échap
- [ ] ANIM-5 · tiroir : `translateY(100%)` → `0`, `--meili-ease-drawer`, ≈ 350 ms entrée / 250 ms sortie ; voile en `opacity` seule, pas de `backdrop-filter`
- [ ] ANIM-6 · accordéon : rotation du chevron 200 ms, contenu en `opacity` + `translateY(4px)` ; hauteur par `interpolate-size: allow-keywords` en amélioration progressive, jamais `height` animée à la main
- [ ] ANIM-7 · pills : `:active { transform: scale(0.97) }` 160 ms ; survol sous `@media (hover: hover) and (pointer: fine)`
- [ ] ANIM-8 · badge : entrée `scale(0.9)` + `opacity` par `@starting-style` au passage 0 → 1 ; un changement de chiffre ne s'anime pas
- [ ] ANIM-9 · pastilles actives : entrée `opacity` + `scale(0.95)` 150 ms, pas d'animation de sortie
- [ ] ANIM-10 · grille : `aria-busy`, atténuation seulement au-delà de ~150 ms de recherche
- [ ] ANIM-11 · `prefers-reduced-motion` : plus de `translate`/`scale`, fondus conservés (tiroir en fondu 150 ms)
- [ ] ANIM-12 · option, plus tard : glisser pour fermer le tiroir, avec vitesse du geste (skill `gesture-ui`)
- [ ] ANIM-13 · question au graphiste : intention d'animation du composant Figma « Animation filtrage » (le MCP n'y trouve aucune donnée de mouvement)

### 8 · Habillage Pluralia — à venir

Dans le thème, par les crochets `data-meili` et non par les classes (`R-128`), avec les tokens de
`theme-vars.css`. Validation par sous-agents, desktop et mobile : l'ensemble comparé à
`get_screenshot`, **chaque détail** comparé aux valeurs de `get_design_context` (styles calculés
relevés dans Playwright).

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
