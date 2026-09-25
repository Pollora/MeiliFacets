# MeiliFacets — décisions

Voir aussi : [installation.md](installation.md) · [architecture.md](architecture.md) · [configuration.md](configuration.md) · [lots.md](lots.md) · [pieges.md](pieges.md) · [prix.md](prix.md) · [revue.md](revue.md)

## Validées

| Sujet | Décision |
| --- | --- |
| Nom | **MeiliFacets**, module Pollora générique, réutilisable hors de Pluralia |
| Rôle de MeiliScout | indexation seule ; les facettes sont construites dans MeiliFacets |
| Rendu de la page | WordPress, coût habituel assumé |
| Listing et facettes | Meilisearch |
| Transport | le navigateur interroge Meilisearch en direct — **aucun proxy PHP, aucun repli** |
| Front | classes ES sans dépendance, livrées par le module, markup entièrement surchargeable par le thème |
| Langage | **TypeScript** pour le client navigateur, limité à la syntaxe effaçable. *Renversé le 2026-09-16 : c'était « JavaScript, pas de TypeScript », avec des types en JSDoc que rien ne vérifiait — 56 erreurs dans le code livré.* |
| Intégration | composants Blade ; bloc Gutenberg envisagé plus tard |
| Chargement | état de chargement dans le composant listing, seuils calibrés sur la mesure. *Non livré au 2026-09-22 : lot 3c-3 ouvert.* |
| URLs | chemins natifs WooCommerce conservés, filtres en query vars |
| Panne du moteur | vue Blade de repli annonçant un problème technique |
| Diagnostics | en anglais, avec cause et action ; libellés d'interface traduisibles |
| Code | pas de chaînes littérales : énumérations ou constantes. *Pas tenu partout au 2026-09-22 (`R-152`).* |
| Multilingue | non implémenté, mais l'architecture doit le permettre sans refonte |
| Projection des facettes | un champ par taxonomie, posé par le filtre `meiliscout/post/document` ; l'indexable substitué par `meiliscout/indexables` en déclare les réglages. *Précisé le 2026-09-22 : la ligne attribuait le champ à l'indexable.* |
| Taxonomies filtrables | toutes celles indexées par MeiliScout — ce qui est indexé est filtrable |
| Unité de résultat | le produit — il n'apparaît **jamais deux fois** dans un listing |
| Produits variables | quand un filtre ne correspond qu'à certaines variations, on affiche le produit avec les informations de la variation correspondante. *Non livré au 2026-09-22 : la carte porte la fourchette du produit ; lot 4.* |
| Carte produit | markup rendu par le serveur, mis à jour par liaison d'attributs |
| Repli | `503`, `Retry-After`, `Cache-Control: no-store`. *Le module pose les en-têtes ; Pollora écrase le statut, voir Dettes (`R-40`).* |
| Clé de recherche | clé fixe fournie par l'infrastructure (submodule Docker AmphiBee) — le module ne la crée pas |
| Variables d'environnement | conventions existantes des projets AmphiBee : `MEILI_HOST`, `MEILI_KEY`, `MEILI_INDEX_NAME`, `MEILI_MATCHING_STRATEGY`, `MEILI_PUBLIC_URL`, `MEILI_SEARCH_KEY`. *Au 2026-09-22, ni MeiliScout ni le module ne lisent `MEILI_INDEX_NAME` ni `MEILI_MATCHING_STRATEGY` : l'index s'appelle `posts` en dur.* |
| Version du moteur | local sur `latest`, non épinglé ; la production en 1.10.3 **doit être montée de version** — prérequis de déploiement |
| Découpage | sept lots, décrits dans [lots.md](lots.md) ; les décisions ouvertes se prennent au début du lot concerné |
| Dépendance MeiliScout | épinglée sur `dev-feat/meilifacets` (commit `a83fa4b` au lock depuis `R-112`, `1c59a05` à l'origine) — **dette : repasser à `dev-main` après merge de la PR** |
| Noms des paramètres d'URL | figés en configuration (`meilifacets.url_parameters`), jamais dérivés d'un libellé de taxonomie |
| Indexation des URLs de listing | `noindex, follow` sur toute URL portant **un paramètre de listing rempli** — facette, tri, recherche, borne de prix ou page — et **uniquement sur une page d'archive ou de recherche** ; **pas** de canonique vers le chemin nu. *Élargi le 2026-09-06 : un tri et une page étaient indexables, ce qui laissait entrer des doublons ; la garde de contexte a rendu l'élargissement sûr.* |
| Canonique d'une URL de listing | **supprimée** quand le module pose `noindex` — une canonique désigne deux URLs comme une seule page, et l'associer à un `noindex` revient à dire de cette page unique qu'elle ne doit pas être indexée. Filtre `wpseo_canonical` à la priorité 20, pour passer après l'intégration WooCommerce de Yoast. Inerte sans Yoast |
| Pagination native de WordPress | `/page/N` **sert la vraie page N** — le listing lit `paged` en repli de `pg` — et reste en `noindex`, sans canonique et sans `rel="next"`/`"prev"`. Les deux paginations coexistaient sans se connaître : `/page/2` à `/page/5` servaient quatre fois la première page, en `index`, avec une canonique auto-référente et une chaîne `rel="next"` qui les liait toutes. *Tranché le 2026-09-06 après analyse dédiée (R-60).* |
| Forme canonique d'une URL | valeurs triées octet par octet et dédupliquées des deux côtés — sans quoi `?marque=a,b` et `?marque=b,a` sont deux entrées Varnish pour un même état. *Octet par octet depuis le 2026-09-17 : le client n'a plus à reconnaître un nombre.* |
| Entrées non validées | un tri absent de `sorts()` est ignoré, une facette est plafonnée à son `cap`, la recherche à 200 caractères — un paramètre libre est un vecteur de saturation du cache |
| Défense de `hidden` | contre-règle CSS dans la feuille publiée, sur ses classes **et** sur `[data-meili]` — ce qu'une vue surchargée conserve |
| Feuille de style du module | livre l'apparence par défaut du **tri**, de la **colonne de facettes** et de ses **boutons** — neutre : ni police, ni taille absolue, ni couleur de marque ; seule la grille de résultats reste nue. *Amendé depuis : la feuille met aussi la grille en colonnes et habille le contrôle de prix, la pagination et le badge de filtres — voir « Ce que le module rend, il l'habille ».* Le thème surcharge les mêmes sélecteurs `data-meili` ou désinscrit la feuille |
| Markup du prix | rendu tel que WooCommerce le produit, sans liste blanche : filtrer casse les promos et les fourchettes, et ne protège d'aucun scénario réaliste. **À réexaminer si le prix incorpore un jour une donnée saisie par un utilisateur non privilégié** — un champ de personnalisation, un message promotionnel éditorial. Une revue automatique a classé ce retrait « XSS stockée, HIGH » le 2026-09-04 ; le signalement a été écarté faute de vecteur, pas par principe |
| Mise à jour du DOM | découpage par élément selon le focus : cartes clonées depuis un `<template>`, valeurs de facettes en nœuds stables, pagination en fenêtre fixe |
| Documentation | **dans le module, `docs/`** — rapatriée depuis `docs/meilifacets/` du projet le 2026-09-07, avant le lot 7 : elle ne faisait que grossir, et chaque jour ajoutait des liens à réécrire |
| Données de carte | projetées dans le document (champ `card`), pas recomposées côté client |
| Point d'extension de la carte | contrat `CardProjector` avec une implémentation par défaut — jamais obligatoire |
| Déclaration des facettes et des tris | contrats `ProductFacets` et `ProductSorts`, liés en `scopedIf` — le module donne un défaut WooCommerce, le projet le remplace sans patcher le module |
| Libellés des facettes et des tris | **figés au premier appel** — les implémentations mémoïsent, `__()` compris. Le gain (`T-28`) vaut la contrainte : rien ne change de langue en cours de requête, et le processus meurt avec elle. Un worker de file qui survivrait à un changement de locale garderait les libellés du premier job |
| Ancêtres de catégorie | la chaîne complète est indexée, pas seulement le terme assigné |
| Compteurs de facettes | `multi-search` disjonctif, isolé derrière un point d'extension (`FacetCounter`). *Au 2026-09-22, le point d'extension ne couvre que le premier rendu : le client écrit la règle en dur (`R-144`).* |
| Envoi des recherches | derrière le contrat `SearchEngine` ; le client Meilisearch est injecté, jamais résolu statiquement |
| Repli `503` | objet `Unavailable` lié en `scoped`, pas d'état statique |
| `displayedAttributes` | restreint à `ID` et `card` par défaut, ouvert par `meilifacets.displayed_attributes` ; `'*'` rouvre tout |
| Mode de sélection | déclaré par facette ; le disjonctif n'est produit que pour le multi-sélection |
| Pagination | pages numérotées en query var, sans rechargement ; pas de « charger plus » |
| Valeurs par facette | 10 visibles, le reste rendu et replié, plafond par défaut à 30 |
| Ce qui décide le plafond et le repli | le plafond sur le **compte** (c'est le moteur qui trie), le repli sur l'**ordre déclaré** — donc réordonner puis replier. *Renversé le 2026-09-08 : le repli se décidait sur le compte avant réordonnancement, ce que le client ne pouvait pas reproduire (`R-85`).* |
| Tri des valeurs de facette | le moteur compte (`sortFacetValuesBy`), la facette déclare l'ordre d'affichage — `DisplayOrder`, ou un `ValueOrder` que le projet fournit |
| Comparaison par nom (`NameOrder`) | `Collator` avec `NUMERIC_COLLATION`, construit sur `get_locale()` — donc l'ordre suit la langue de WordPress, celle qui a produit les libellés, et non celle de Laravel. Sans `ext-intl`, repli sur `strnatcasecmp` : documenté, jamais silencieux. *Au 2026-09-22, documenté (`suggest`, `architecture.md`) mais sans signal à l'exécution.* Conséquence assumée : `Name` devient sensible à la casse au niveau tertiaire, là où `strnatcasecmp` mettait `abc` et `ABC` à égalité |
| Facette qui mélange les grandeurs | **on lit l'ordre que WooCommerce porte déjà** (`DisplayOrder::Declared`), on ne le devine pas depuis le libellé. *Renversé le 2026-09-08 — `MeasureOrder` analysait `15ml` au rendu ; supprimé.* |
| Hauteur des contrôles | `--meili-control` en `rem` — **`3rem` (48 px) depuis le 2026-09-25**, dimension reprise de la maquette (pills h 48, nœuds Figma `17:643`, `17:514`, `17:523`, `17:98`), couleurs et polices restant neutres ; au pointeur grossier, un plancher `--meili-control-min: 2.75rem` (44 px, WCAG 2.5.5) que chaque hauteur lit par `max()`, pour qu'un thème qui baisse `--meili-control` ne descende jamais sous la cible tactile et que le pointeur grossier ne réduise jamais la hauteur. Padding latéral `--meili-control-inline: 1.5rem` sur `toggle`, `drawer-open`, `sort-trigger`, `active-value`, gap interne `0.5rem`. *Était `2.25rem`, `2.75rem` au pointeur grossier (2026-09-09), padding `0 0.85em`.* *Était `2.4em`, soit 33,6 px : un multiplicateur sur la propre `font-size` du bouton tombe sur une valeur bâtarde. Coût du `rem` : un thème qui grossit `--meili-ui` n'agrandit plus les contrôles — la hauteur d'un contrôle est une contrainte d'ergonomie, pas une conséquence de la taille du texte. Tranché le 2026-09-09.* |
| Bouton de dépliage | rendu même quand il n'y a rien à déplier : le client le révèle, il n'en crée aucun |
| Livraison du client ES | **un seul module empaqueté** par esbuild, minifié, **commité** dans `resources/assets/dist/` et versionné par `?ver=` comme aujourd'hui. *Renversé le 2026-09-16 : c'était un répertoire portant l'empreinte (`R-70`, 2026-09-08), écarté pour ne faire entrer aucune chaîne d'outils ; la performance du client est devenue une priorité.* |
| Taille de page | dérivée du contexte au rendu, jamais recopiée en configuration |
| Rendu serveur | applique les filtres de l'URL ; Varnish cache chaque combinaison 180 s |
| Repli des paramètres d'URL | une taxonomie non mappée prend un préfixe, jamais son nom nu |
| Défauts des paramètres réservés | `sort`, `q`, `pg` — en anglais, le projet les habille. *Étendu le 2026-09-15 : `min_price`, `max_price`, noms de WooCommerce (`D-h`, `prix.md`).* |
| Facettes de l'archive produit | marque, contenance et catégorie, toutes en multi-sélection. *Le module n'en livre que deux, catégorie et marque ; contenance et prix sont déclarés par Pluralia (`CatalogueFacets`).* |
| Markup des facettes | cases à cocher rendues cochées par le serveur, jamais des liens par valeur |
| Déclenchement de la recherche | `meilifacets.apply_mode` : `submit` par défaut, `immediate` selon le volume ; le choix voyage dans la description JSON, `data-apply` n'est rendu que pour le thème |
| Forme des valeurs multiples | une seule, `?marque=a,b` — un formulaire GET n'aurait produit que `marque[]=a&marque[]=b`, soit deux URLs et deux entrées Varnish pour un même état |
| Paramètres d'URL côté client | le JavaScript n'en connaît aucun : il lit les noms que la description publie |
| Déclaration d'un listing | classe implémentant `Listing`, découverte automatiquement. Elle porte ce que la page impose : son filtre de base, et son **terme de base** — la recherche que WordPress a routée (`s`), lue jamais nommée, puisqu'un paramètre du module ne peut pas s'appeler comme une query var publique. Ce que le visiteur tape reste dans l'état, sous `q`, et l'emporte. *Étendu le 2026-09-23 (`R-158`).* |
| Listing produit | livré par le module quand WooCommerce est actif |
| Facette catégorie sur une archive de catégorie | **conservée et restreinte au niveau courant** (`ChildTermsFacet`) : elle propose les enfants directs du rayon, rien sur une feuille. *Renversé le 2026-09-06 — elle était retirée, la maquette cliente demande l'inverse.* |
| Surcharge du markup | le module ajoute le thème en tête de sa cascade de vues |
| Requête principale des archives | conservée : elle porte le routage et le SEO ; `posts_pre_query` reste banni |
| Taille de page | `apply_filters('loop_shop_per_page', …)`, jamais `wc_get_loop_prop('per_page')` |
| Carte de l'archive produit | bascule sur `<x-theme::product-card>`, changement d'apparence assumé. *Non tenue au 2026-09-22 : l'archive rend `<x-meilifacets::card>` (mesuré sur `/boutique`) ; `R-45`/`Q-10` à trancher.* |
| Disposition des filtres | **composée par le thème** ; le module fournit des briques indépendantes (facette, tri, compteur, pastilles, `apply`, `reset`, tiroir), aucune disposition ni enum `Layout`. *Tranché le 2026-09-24 : la v1 (dispositions `Sidebar`/`Bar` dans le module) est abandonnée — [chantier-filtres-architecture.md](chantier-filtres-architecture.md).* |
| Présentation des valeurs | déclarée **par facette** dans `ProductFacets` ; ensemble **ouvert** : contrat `ValuePresentation` (`slug()`, `allowsSingleSelection()`), que l'enum `Presentation` du module (`Control`/`Pill`, **sans `Radio`**) implémente et qu'un thème implémente pour ses propres présentations, habillées par `[data-presentation]`. Le type d'input reste dérivé de `SelectionMode` (`R-10`) ; une présentation qui ne l'autorise pas est refusée sur une facette `Single`. L'attribut `presentation` du composant la surcharge ponctuellement (chaîne = cas du module, `:presentation` = objet) : **une même facette doit pouvoir se présenter différemment selon le gabarit** (confirmé par Louis le 2026-09-24). Pas de markup par présentation. *Tranché le 2026-09-24 (C-5, `R-164`) : renverse la lettre de `D-07`, qui voulait que le module ignore les pastilles. Ouvert le même jour par Louis ; coût : un contrat de plus à maintenir. La méthode s'appelle `slug()` et non `name()`, pour ne pas cohabiter avec la propriété native `->name` des enums (`Pill`).* |
| Activation d'une facette | la déclarer dans `ProductFacets` ; aucune clé de configuration. *Tranché le 2026-09-24 (C-6).* |
| Widget du tri | choisi par attribut, enum `SortWidget` : `listbox` (défaut, vue actuelle) ou `radios`. *Tranché le 2026-09-24 (C-4).* |
| `apply_mode` dans les briques | toutes le suivent : en `submit`, « Appliquer (X) » lance la recherche, **X = nombre de valeurs cochées**, connu sans recherche ; dans le tiroir, « Appliquer » reste affiché même en `immediate`, ne cherche rien de plus et ferme le tiroir. *Tranché le 2026-09-24 (C-1, C-3).* |
| « Annuler » | le `reset` existant, libellé et style surchargés par le thème (vue + catalogue) — aucun second état, `D-10` tient. *Tranché le 2026-09-24 (C-2).* |
| Filtres actifs | **pastilles retirables** une à une, prix compris, libellés publiés dans la description. *Tranché le 2026-09-24 (C-7, étape 2b) — `R-47`.* |
| Tiroir mobile | conteneur ordinaire promu en dialogue par le JS sur mobile : pas de `<dialog>`, jamais `display: contents`, aucun filtre rendu deux fois (`R-95`) ; `inert` posé sur l'entourage, puis seul ce qui a été posé est restauré. *Tranché le 2026-09-24 (option A, architecture Q-2).* |
| Repliable | une seule brique : panneau déroulant en rangée (un seul ouvert), accordéon dans le tiroir modal (sections indépendantes) ; **fermé côté serveur**. *Tranché le 2026-09-24 (architecture Q-1, Q-4).* |
| Seuil mobile | écrit en dur dans le CSS, repris par défaut par l'attribut `media` du tiroir, accord vérifié par un test de parité. *Tranché le 2026-09-24 (architecture Q-3).* |
| Dossiers et crochets du chantier | `ts/drawer/` et `ts/collapsible/` autorisés, Blade à plat dans `components/` ; crochets `Hook` additifs, `Contract::VERSION` inchangé (`R-116`). *Autorisé le 2026-09-24 (C-8 révisée, architecture § 4-5).* |
| `apply_mode` de Pluralia | reste `submit` **pour évaluer le rendu** (« Appliquer » en fin de rangée desktop) ; `immediate` envisagé après validation visuelle de Louis. *Tranché le 2026-09-24 (architecture Q-5) ; `R-51` et `Q-24` restent ouverts.* |
| Style par défaut d'une brique | toute nouvelle brique rejoint la règle de base de ses sœurs dans `meilifacets.css` (`font-size: var(--meili-ui)`, marges neutralisées) : le rendu brut du module est homogène avant que le thème ne l'habille. *Tranché le 2026-09-24 (étape 2a, relevé par Louis sur le compteur).* |
| Compteur d'une valeur de facette | **dans** le `<label>`, visible, jamais `aria-hidden` : un clic dessus coche la case nativement et son texte reste dans l'arbre. Le nom vient d'`aria-labelledby`, qui pointe le `<span>` du libellé (id `ElementId::facetValueLabel()`) ; le compteur décrit (`aria-describedby`). Chiffres tabulaires. **En pastille, le module masque le compteur visuellement** (maquette « 15 ML »), présent dans l'arbre ; le thème le réaffiche par `[data-presentation="pill"] [data-meili="count"]`. *Tranché le 2026-09-24 par Louis (étape 3b, `R-151`) : écartés, le compteur hors du `<label>` avec un libellé étiré en CSS (couche fragile) et le compteur en `aria-hidden` (retiré du mode lecture et de VoiceOver iOS sans indications). Coût : une vue surchargée qui ne reprend pas `aria-labelledby` garde le compteur dans le nom.* |
| Pastilles actives | elles montrent l'**état appliqué** (en `submit`, rien tant que la sélection n'est pas validée) ; retirer une pastille est un **ordre** au sens de `D-10` : la recherche part tout de suite et emporte les filtres en attente. Libellés publiés dans la description (+829 o mesurés sur `/boutique`), gardés pour l'instant. *Tranché le 2026-09-24 (étape 2b, `R-47`).* |
| Compteur `active-count` | nombre nu de la **sélection courante, en attente comprise** (même état que le badge `active-filters`, C-3), plage de prix comptée une fois, masqué à zéro ; les mots autour (« Filtre », « Appliquer », parenthèses) appartiennent à la vue qui le porte. Un élément d'état peut être rendu plusieurs fois, un contrôle (facette, tri) jamais. *Tranché le 2026-09-24 (étape 2c, `R-162`).* |

### Pourquoi la production doit monter de version

Quatre apports postérieurs à la 1.10.3 servent directement cette architecture, et aucun n'a
d'équivalent dans la version actuellement en production :

- **v1.50** — les motifs partiels sont acceptés dans le paramètre `facets`. Une seule requête
  `facets: ["facets.*"]` ramène la distribution de toutes les taxonomies, sans avoir à en
  connaître la liste. Auparavant seul le `*` complet était admis.
- **v1.14** — réglages granulaires des attributs filtrables, par motif : on active l'égalité sans
  payer la recherche dans les valeurs ni les comparaisons. C'est la réponse au coût d'index de la
  règle « ce qui est indexé est filtrable ».
- **v1.12** — `facetSearch` et `prefixSearch` désactivables par index : le moteur cesse de
  construire les structures inutilisées, ce qui allège l'indexation, la base et donc les
  snapshots.
- **v1.40** — `distinct` en recherche fédérée avec distribution de facettes, pour la recherche
  multi-types produits et articles.

S'y ajoutent plusieurs versions consacrées à la performance d'indexation des facettes (1.23,
1.43, 1.45) et un correctif sur les filtres de comparaison appliqués à des valeurs texte (1.43).

Deux conséquences : le module ne peut pas partir en production avant cette montée, et le SDK PHP
embarqué par MeiliScout étant en 1.12.0, sa compatibilité avec les réglages introduits ensuite
reste à confirmer. *Au 2026-09-22, le code n'emploie aucun des quatre apports : facettes demandées par nom,
attributs filtrables en liste simple, ni `facetSearch`, ni `prefixSearch`, ni recherche fédérée. Ce que la
1.10.3 empêche réellement reste à mesurer (`R-41`).*

### Le module livre ses assets, il ne les injecte pas (2026-09-04)

La règle `[hidden]` était imprimée dans `wp_head` par une classe PHP. Elle vit désormais dans
`resources/assets/css/meilifacets.css`, publiée dans `public/modules/meilifacets/` par
`module:publish` et inscrite avec `wp_enqueue_style` — donc mise en cache par le navigateur,
minifiée par WP Rocket, et **déinscriptible par le thème** (`wp_dequeue_style('meilifacets')`),
ce que l'injection interdisait.

Contrepartie assumée : une étape de déploiement de plus. Elle ne casse rien en silence — sans
publication, les nœuds `hidden` réapparaissent à l'écran.

### Les commandes sont des boutons (2026-09-06)

Tri, pagination et remise à zéro étaient des `<a href>` — le repli sans JavaScript. La règle du
projet est « Meilisearch full AJAX », et un lien qu'aucune navigation ne suit est une promesse
fausse. La pagination et la remise à zéro deviennent des `<button type="button">`. Seule la carte
produit reste un lien.

Le tri est une liste déroulante du module — motif combobox de l'ARIA APG en `ul`/`li` — et non un
`<select>` natif, qui ne se style pas au-delà de sa boîte et sortirait ses options du design
system. Le module en rend la sémantique et les crochets ; l'apparence et le clavier restent
respectivement au thème et au client.

Ce que ça supprime : `ListingUrls` et ses tests, donc une deuxième implémentation de la
construction d'URL à tenir alignée avec `listing-url.ts` ; et la réécriture des `href` après chaque
recherche, qui aurait été obligatoire sinon.

Ce que ça coûte : sans JavaScript le listing est inerte. Cohérent avec les facettes, sans
formulaire depuis le lot 3b.

### Deux familles de gestes, pas cinq (2026-09-07)

Un filtre **se rassemble** : en mode `submit` il ne cherche rien, en mode `immediate` il part
aussitôt. Trier, paginer et remettre à zéro **ne se rassemblent pas** : ce sont des ordres, ils
s'appliquent sur place dans les deux modes — **et ils emportent avec eux les filtres en attente**.

Cette dernière clause a été ajoutée le 2026-09-07, après qu'une revue a mesuré que le code faisait
déjà cela et que le texte disait le contraire (`R-77`). Trier vaut donc validation. L'alternative —
repartir du dernier état appliqué — imposerait de tenir deux états et laisserait le visiteur devant
une grille qui ignore les cases qu'il vient de cocher : le bouton « Appliquer » resterait la seule
façon de faire coïncider ce qu'il voit et ce qu'il a demandé. `Listing` a donc deux chemins privés au lieu d'un seul :
`#byMode()`, qui suit le mode du listing, et `#atOnce()`, qui ne le consulte pas.

Sans cette distinction, un visiteur en mode `submit` qui clique « page 2 » verrait sa demande mise
en attente d'un bouton « Appliquer » qui parle de filtres. La règle vaut aussi pour « Tout
effacer » : un effacement qui n'efface rien tant qu'on n'a pas validé n'efface pas.

Ce que ça coûte : `Listing` déclenche lui-même la recherche pour trois gestes, donc un appelant qui
enchaîne `.goToPage(2).apply()` en lance deux — l'écriture de l'URL comprise. La forme correcte est
`.goToPage(2)` seul ; `apply()` ne reste public que pour le bouton « Appliquer ».

### Le module rend ce que ses propres choix ont retiré (2026-09-07)

Amende la dernière phrase de la décision précédente. La feuille de style du module ne porte aucune
apparence — elle rembourse ce que deux décisions de conception ont enlevé au navigateur.

Le `ul`/`li` à la place d'un `<select>` fait perdre une liste qui se superpose au lieu de pousser
la page, un fond opaque, et une marque sur l'option que le clavier désigne. Rendus : `position` et
`z-index`, `Canvas`/`CanvasText`, une marque sur `[data-active]` — fond aujourd'hui, `outline` sous `forced-colors`.

`Canvas` et `CanvasText` sont les couleurs système de CSS Color Level 4 — la surface de page du
navigateur et son texte. Choisies parce qu'elles nomment un **rôle** au lieu d'une couleur : le
module doit rendre la liste lisible sans rien savoir de la palette du thème, et un `#fff` en dur
serait une décision d'apparence qu'il s'interdit.

⚠️ **Elles ne suivent pas `prefers-color-scheme` toutes seules.** Mesuré le 2026-09-07 : sous
`prefers-color-scheme: dark` émulé, `Canvas` reste blanc, parce que la page ne déclare pas
`color-scheme`. Elles suivent en revanche `forced-colors` (contraste élevé Windows) : noir sur
blanc devient blanc sur noir, mesuré. Une première rédaction de cette décision affirmait le
contraire ; c'était faux.

Conséquence visible sur Pluralia : la liste est blanche sur une page crème (`rgb(255, 253, 245)`).
C'est le cas prévu — le thème habille, le module rend seulement utilisable.

Le `<button>` à la place du `<a href>` fait perdre le curseur en main — la seule affordance qu'un
lien donnait gratuitement. Rendu : `cursor: pointer` sur les huit crochets qui se cliquent, et sur
eux seuls — le prix (2026-09-16) y ajoute la piste et les poignées (`grab`). Mesuré avant correction : tout le module était en `cursor: default`, seule la carte
produit avait la main, parce qu'elle est restée un lien.

Tout le reste — dimensions, typographie, espacements, couleurs de marque — reste au thème et n'est
pas déclaré. La règle s'accroche à `data-meili`, jamais aux classes : ce sont les crochets qu'un
thème garde en surchargeant une vue, les classes sont ce qu'il peut remplacer.

`data-active` est écrit par le client sur l'option active, faute d'équivalent ARIA sur l'option
elle-même : `aria-activedescendant` est porté par le bouton, et le CSS ne sait pas suivre une
référence. Il n'entre pas dans le contrat — le thème ne le rend pas, il le style s'il veut.

### Ce que le module rend, il l'habille ; le thème remplace (2026-09-07)

Amende la décision précédente, et répond à Q-28 pour ce seul contrôle. La liste déroulante de tri
est le composant que le module a fabriqué de toutes pièces pour remplacer un `<select>` : c'est
aussi le seul qui, livré nu, n'a l'air de rien tant qu'un thème ne l'a pas dessiné — un `ul`/`li`
à puces posé sous un bouton sans bordure. Le module en livre donc l'apparence par défaut : bordure,
panneau solidaire du déclencheur, séparateurs, coche sur l'ordre choisi, teinte au survol,
ouverture animée. Le libellé « Trier par » sort de la vue sans sortir du nom lu — le déclencheur
répète déjà la valeur, l'afficher deux fois était la remarque d'origine.

Ce que cette apparence ne décide pas : aucune famille de police — `font: inherit`, dimensions en
`em` — ni aucune couleur de marque : `currentColor`, `Canvas`/`CanvasText`, et trois teintes dérivées en `color-mix` exposées
en variables (`--meili-edge`, `--meili-rule`, `--meili-tint`), qu'un thème retint d'une ligne —
déclarées aujourd'hui sur `[data-listing]`, et non plus sur `[data-meili="sort"]`.

La ligne de facette **centre ses trois éléments** (`align-items: center`) : sous `flex-start`, le
compteur, dont la boîte est plus courte de deux pixels, était calé en haut et son texte flottait
1,5px au-dessus de la ligne de base du libellé. Seule la case garde `align-self: flex-start`, avec
`margin-top: calc((1lh - 1em) / 2 - 0.14em)` : sur une seule ligne cela revient au centrage, et sur
un libellé qui passe à la ligne elle reste sur la première au lieu de descendre au milieu du bloc.

La case prend `font-size: var(--meili-ui)`
avant sa taille en `em`, parce qu'un `<input>` n'hérite pas de la police : sans ça elle mesurait
13,3px sur un thème sans reset de formulaire, et 14 seulement sur ceux qui en ont un. `accent-color`
suit `currentColor`, comme le reste.

Les six commandes partagent une seule primitive : `inline-flex` centré, une hauteur commune
(`--meili-control`, `2.4em` à l'époque, `2.25rem` depuis le 2026-09-09 — voir « Hauteur des contrôles »), un padding unique et la même échelle. Le centrage par `text-align` et
`line-height` dépendait des métriques de la police ; les numéros de page, eux, prennent `min-width`
et `tabular-nums`, sans quoi « 1 » et « 2 » n'ont pas la même largeur et la rangée est irrégulière.
Corollaire à ne pas oublier : dans ce bloc, `font-size` doit précéder toute longueur en `em`, sinon
`happy-dom` les calcule sur la taille héritée et les tests divergent du navigateur.

Elles partagent aussi **le même signal d'appui — un fond, jamais un recul**. Le recul, essayé
d'abord, faisait rentrer les bords de 1 à 3px : sur le déclencheur de tri, le panneau soudé arrivait
à pleine largeur pendant que lui était rétréci, et le joint sautait d'un pixel ; sur « Tout effacer »
et « Appliquer », alignés sur une arête de colonne, ils s'en détachaient le temps de la pression.
Ici toutes les commandes sont soit soudées, soit alignées sur une arête : la géométrie ne bouge pas,
le fond suffit. Corollaire de cascade : les états d'appui se déclarent **après** la requête
`(hover: hover)`, sinon `:hover` l'emporte à spécificité égale et le fond reste celui du survol.
Au doigt, ni l'un ni l'autre ne se voit — `-webkit-tap-highlight-color` est éteint sur bien des
thèmes et iOS ne déclenche pas `:active` sans écouteur tactile —, donc le module redéclare ce flash
natif à la teinte d'appui sous `(pointer: coarse)`, où les cibles passent aussi à `2.9em` (`2.75rem` depuis le 2026-09-09).

Une taille est décidée, et c'est la seule : les **commandes** (tri, facettes,
pagination, remise à zéro, compteur de filtres) prennent `var(--meili-ui)`, `0.875rem` par défaut.
Hériter du texte de l'hôte donnait sur Pluralia une colonne de facettes en 18px, plus grosse que ce
qu'elle filtre. Les résultats en sont exclus : la carte garde la typographie du thème. Un thème
retaille tout d'une ligne, comme les teintes.

Ce que ça coûte : le module a désormais un avis sur l'écran, donc une surface de conflit avec le
thème qu'il n'avait pas. Le recours reste le même qu'avant — surcharger les mêmes sélecteurs, tous
accrochés à `data-meili`, ou désinscrire la feuille (`wp_dequeue_style('meilifacets')`). Et le
libellé masqué est une décision d'apparence prise pour tout le monde : un thème qui le veut visible
pose une règle sur `[data-meili="sort"] > label`.

La colonne de facettes suit, pour la même raison : livrée nue, elle sort en liste à puces indentée,
compteur collé au libellé, groupes sans respiration — un `<fieldset>`/`<legend>` que personne ne
lit tel quel. Le module pose donc ses espacements, retire puces et indentation de ses propres
listes, aligne case et libellé, et pousse le compteur en fin de ligne à `0.9em` et `opacity: 0.6`.
Deux valeurs de graisse et de taille seulement, toutes deux relatives : `font-weight: 600` sur la
légende, `0.9em` sur le compteur.

Les cinq boutons que le module rend — « Appliquer », « Tout effacer », les pages, précédent et
suivant — partagent une seule règle : contour, rayon et espacement du déclencheur de tri, teinte au
survol, et la page courante distinguée par son `aria-current`. Sans elle, ils sortaient en boutons
natifs gris, chacun avec la police du système au lieu de celle de la page.

« Appliquer » fait exception, parce que c'est le seul geste qui engage : plein `CanvasText` sur
texte `Canvas`, largeur de sa colonne. Le couple de couleurs système suffit à faire un plein sans
choisir de teinte, et s'inverse avec l'hôte ; son `outline` de focus repasse en `CanvasText`, sans
quoi il serait invisible sur le fond clair de la page.

La grille de résultats suit : `repeat(auto-fill, minmax(var(--meili-card), 1fr))`, le seuil exposé
en variable. Sans elle, les cartes s'empilaient en une colonne sur toute la largeur — mesuré sur
`/boutique` avant correction : 6674px de page, 4547px de liste. Après : trois colonnes, 3270px.
L'intérieur de la carte reste au thème ; seule sa mise en colonnes est ici.

Les états, eux, sont ceux qu'un composant doit avoir : un appui marqué (d'abord `:active` en
`scale(0.97)`, abandonné pour un fond, `--meili-press` — voir « un fond, jamais un recul » plus
haut), survol conditionné à `(hover: hover) and (pointer: fine)` — au doigt il restait collé après le tap —
et cibles élargies sous `(pointer: coarse)`. La liste de tri passe du `@keyframes` à une transition
avec `@starting-style` et `transition-behavior: allow-discrete` : un keyframe repart de zéro quand
on ouvre et ferme vite, et surtout la fermeture n'avait aucune animation. Vérifié à l'écran :
`display` reste `block` pendant les 160ms de sortie, puis bascule.

### Remplacer la grille ramène le regard, déplacer le focus non (2026-09-07)

> *Renversé le 2026-09-08 (commit `85b3097`) : le défilement est désactivé par défaut et demandé
> composant par composant, par l'attribut `scroll` (`data-meili-scroll`) — voir
> `architecture.md`. La règle pointeur/clavier ci-dessous reste. Le registre ne dit pas qui l'a
> décidé.*

Pagination, tri, remise à zéro et « Appliquer » **remplacent** ce qu'on lisait : ils ramènent le
haut du listing dans l'écran. Cocher une facette **rétrécit** ce qu'on regarde déjà : rien ne bouge
— en mode `immediate`, défiler à chaque case serait intenable.

Le défilement ne vaut que pour un pointeur. Au clavier, le focus tient déjà la place, et déplacer
la page sortirait de l'écran le bouton qu'on vient de presser. `event.detail` sépare les deux sans
heuristique : `0` sur un clic levé par le clavier, non nul sur un vrai.

`scrollIntoView` est appelé **sans `behavior`** : animer ou non est une décision de thème, prise
dans son CSS, avec la garde `prefers-reduced-motion` au même endroit. Le module ne porte aucune
apparence, ici comme ailleurs.

Ce que ça coûte : `scroll-behavior` s'applique à la boîte de défilement, donc à `html` — un thème
qui l'active anime **tout son site**, il ne peut pas viser ce seul geste. Et la règle
pointeur/clavier reste invisible à la lecture du code appelant : c'est une propriété de l'événement,
pas du geste. Elle est écrite dans `ListingBinding#reveal()` et testée des deux côtés.

Ce que ça ne fait pas : déplacer le focus en haut des résultats. Ce serait l'usage annoncé aux
lecteurs d'écran, mais paginer plusieurs fois de suite imposerait de retraverser la grille. L'annonce
du changement relève de l'état d'attente, au lot 3c-3.

### `happy-dom` en dépendance de développement (2026-09-07)

Validé sous `R-69`, installé au lot 3c-2. La couche DOM était recettée à la main dans un
navigateur : cela prouvait qu'elle marchait ce jour-là et ne protégeait de rien. `tests/ts/dom.ts`
monte un document et reproduit le balisage des composants Blade, crochets et états `hidden`
compris.

Ce que ça coûte : un balisage de test à tenir aligné sur les vues Blade. Le contrat `data-meili`
en couvre la structure, les tests `Feature` en couvrent le rendu réel — la dérive possible porte
sur les états initiaux, pas sur les crochets.

### Contrat `data-meili` (2026-09-04)

Le client adresse des attributs `data-meili`, jamais des classes, et la racine porte une version
comparée à `Contract::VERSION`. Version différente ou crochet structurel manquant : le JavaScript
ne démarre pas, la console nomme ce qui manque, la page servie reste celle du serveur.

Le refus ne porte que sur ce que le thème contrôle. `facet-value`, `count`, `sort`, `apply` sont
optionnels : leur absence peut venir de la donnée (une catégorie feuille n'a aucune valeur de
facette) ou d'un choix de thème légitime. Exiger leur présence rendrait le module inutilisable sur
un thème qui n'affiche pas de compteurs.

Deux conséquences acceptées sur le rendu serveur : les éléments que le client met à jour sont
rendus dans tous les cas et masqués par `hidden` plutôt qu'omis (message vide, remise à zéro,
badge, nav de pagination, sept slots de page) ; et un `<template>` de carte est rendu à chaque
page, soit une carte vide en plus dans le HTML. C'est le coût de la règle « le markup appartient
au thème » : sans lui, le client porterait sa propre copie du markup.

**Une valeur de facette est rendue dès que le listing non filtré la propose** (2026-09-17, option C
de `R-137` #2, choisie par Louis). Sur une page filtrée, le serveur compte en plus chaque facette sous
le seul filtre de base, dans le même multi-search, et rend ces valeurs en plus des siennes, chaque liste
sous le plafond de la facette ; celles qui n'ont plus
de résultat sont masquées par `hidden` et ne prennent pas de place dans le repli, des deux côtés. La
description publie les comptes rendus, pour que le client sache avant sa première recherche quelles
valeurs sont vides. Une valeur que le visiteur tient reste affichée, même à 0 (`R-137` #4, tranché par
Louis le même jour) : « 0 résultat », au singulier en français. Serveur et client choisissent la forme
d'un compte par la règle CLDR de la langue (tranché par Louis le même jour) : ICU côté PHP
(`View\CountLabel`, sur la locale de WordPress que `__()` traduit, lue au moment où elle sert pour suivre Polylang ; sans `ext-intl`, repli sur la table de Laravel, identique pour le français et l'anglais), `Intl.PluralRules` côté navigateur,
vérifiés sur la même table (`tests/plural-cases.json`). Rien ne change en français ni en anglais ; dans
d'autres langues le serveur n'écrit plus comme `trans_choice()` (portugais à 0, russe, japonais…), mais
toujours comme le client. Deux formes seulement, des deux côtés : le russe écrit 2 à 4 comme 5, là où `trans_choice()`
en offrait trois. *Remplace la « limite assumée » : une valeur absente du premier rendu ne pouvait
pas revenir, et « Tout effacer » depuis `/boutique?marque=aeris` laissait 4 catégories sur 5 et 7
contenances sur 24.* Coût mesuré : une recherche de plus seulement quand le visiteur a filtré ou cherché, +1,4 ms
côté moteur ; +356 octets compressés sur `/boutique?marque=aeris`. Une page filtrée porte au plus deux fois
le plafond d'une facette, plus les valeurs que le visiteur tient. En ordre `Count`, les valeurs d'une page
filtrée suivent le compte du listing non filtré. Une vue de facette surchargée garde ses valeurs sans
résultat masquées — elles arrivent `folded`, le drapeau qui pose déjà `hidden` —, mais doit masquer son
bloc sur `$hasReadableValues()` plutôt que sur `$values === []`. Non mis en cache : la recherche non
filtrée est refaite à chaque page restreinte, et son coût suit la taille du catalogue, pas celle de la
page.

### Chaînes d'interface (2026-09-03)

Le code écrit ses chaînes en anglais, sans domaine de texte, et le français vit dans
`Modules/MeiliFacets/lang/fr.json`, chargé par `registerTranslations()` du provider nwidart. Un
`__('Brand')` non traduit sort en anglais, ce qui se voit ; il ne casse rien.

### La paire de bornes voyage toujours, dessinée ou non (2026-09-15)

Quelles que soient les parties déclarées — curseur, champs, aucune des deux — le composant prix rend
ses deux `input`, visibles ou en `type="hidden"`. Ils portent le nom de paramètre d'URL courant et la
borne en cours.

Ce que ça achète : `price-control.ts` n'a qu'un endroit où lire les noms et les valeurs, quelle que
soit l'apparence choisie, et les trois vues du prix restent interchangeables sans que le client le
sache.

Ce que ça **n'achète pas**, et qu'il ne faut pas croire : ces `input` ne filtrent rien quand le
script ne tourne pas. Le module ne rend aucun `<form>` depuis le lot 3b — sans JavaScript le listing
est inerte, prix compris. L'affirmation inverse a circulé trois fois dans le code du lot prix ; elle
est fausse.

### Une borne posée au bord de la piste n'est pas un filtre (2026-09-16)

Tranché par Louis, sur la plateforme : le bloc de filtre de prix de WooCommerce retire une borne qui
repose sur l'extrémité mesurée (`price-filter-frontend.js`). Le module fait de même au moment de
valider — poignée, clavier ou saisie : une borne égale au bord n'est ni écrite dans l'URL ni envoyée
au moteur (`R-122`).

Ce que ça achète : « pas de minimum » n'a plus qu'une URL, une piste laissée entière ne pose ni
`noindex` ni entrée de cache pour rien, et le compteur de filtres actifs peut compter une plage sans
compter une plage qui ne filtre rien.

Ce que ça coûte, assumé :

- **le bord bouge avec les autres facettes.** Une poignée poussée au bout sous « Aeris » (bord 47 €) ne
  retient rien : retirer la marque rend la piste entière, pas 0–47 €. C'est aussi le comportement de
  WooCommerce ;
- **une borne tenue hors des nouvelles bornes** s'affiche ramenée au bord ; le geste suivant la valide
  donc comme « pas de borne ». Le serveur rend déjà la même valeur ;
- **seules les URL que le module écrit sont canoniques.** Un lien extérieur `?min_price=0` reste servi
  tel quel : le serveur lit l'état avant de connaître les bornes, il ne peut pas savoir que 0 est le
  bord.

### Le contrôle de prix s'habille par ses crochets, sa mise en page par ses classes (2026-09-16)

Tranché par Louis. La règle « le style s'accroche à `data-meili`, jamais aux classes » vaut pour le prix
comme pour la liste de tri : piste, poignées, bulle, lecture, extrémités et champs sont stylés par
leurs crochets, qu'un thème garde en surchargeant la vue. Le remplissage de la piste est dessiné par un
dégradé sur `price-track`, piloté par `--from`/`--to`, ce qui supprime un élément sans crochet.

**Exception écrite** : les rangées qui disposent le contrôle — en-tête, ligne des bornes, rangée des
champs, colonne « libellé + champ », boîte « champ + symbole », tiret — gardent leurs classes, comme
la légende masquée visuellement (`.meilifacetsHidden`) quand le contrôle se nomme lui-même. Ce sont des choix de mise en page,
qu'un thème qui surcharge la vue reprend. Leur donner des crochets aurait ajouté six entrées au contrat
pour de la seule disposition.

Une conséquence voulue : la remise à zéro des champs (ni bordure ni contour de focus) ne s'applique
**qu'à l'intérieur** de la boîte qui dessine le focus. Un thème qui retire cette boîte garde des champs
visibles, avec leur contour natif.

Coût : une vue surchargée garde un curseur qui fonctionne et se voit, mais repart de rangées nues.

### Une borne se valide au relâchement de la touche (2026-09-16)

Tranché par Louis, comme le curseur de prix de WooCommerce, qui ne filtre qu'au `keyup`
(`ProductFilterPriceSlider.php:131,143`). Au clavier, la poignée bouge à chaque `keydown` ; la plage n'est
validée qu'au `keyup`, et seulement pour une touche qui déplace une poignée (`R-129`).

Ce que ça achète : en mode `immediate`, une recherche et une réécriture d'URL par geste, au lieu d'une par
répétition d'une flèche maintenue.

Ce que ça coûte : en mode `immediate`, la grille ne suit une flèche maintenue qu'une fois la touche
relâchée ; en mode `submit`, « Appliquer » pressé avant le relâchement n'écrit rien.

### « Promotions » est une option du tri qui filtre (2026-09-16)

Tranché par Louis. Amende `D-d` (`prix.md`), qui prévoyait une facette « en promotion » en drapeau,
dérivée de `is_on_sale()`.

- **Le sens** : le prix réellement facturé — le drapeau figé de WooCommerce, prix promo renseigné et
  égal au prix courant (`class-wc-product-data-store-cpt.php`), calculé variation par variation pour un
  produit variable. Un produit ne remonte donc que si le panier le facturera en promotion, et le filtre
  dit la même chose que le filtre de prix, qui lit lui aussi le prix courant. `is_on_sale()`, qui lit
  les dates à la volée, suivrait le badge au lieu du panier pendant la fenêtre décrite par `R-112`.
- **La place** : une option **« Promotions »** du menu de tri, pas une case ni une facette.
- **Le comportement** : la choisir n'affiche que les produits en promotion et remplace le tri en cours
  (ordre de pertinence). Exclusive comme toute option de tri : elle ne compte pas parmi les filtres
  actifs, et choisir un autre tri la retire. On ne peut pas trier les promotions par prix.
- **Aucun compte** affiché.
- **Affichée seulement si** le listing déclare un prix, WooCommerce est actif, et au moins un produit
  de la sélection courante est en promotion.
- **Un lot groupé n'est jamais en promotion**, même quand un de ses produits l'est — comme dans les listes
  de WooCommerce ; le produit remisé remonte lui-même. Un produit variable l'est dès qu'une de ses
  variations visibles l'est (20 produits sur Pluralia le 2026-09-16, contre 21 selon `is_on_sale()`).
- **Placée en dernier** dans le menu, après les tris qui ne filtrent pas.
- **Les comptes et les bornes suivent la grille** (tranché par Louis le 2026-09-17) : sous « Promotions »,
  les compteurs des facettes et les bornes du prix ne comptent que les produits en promotion ; une valeur
  sans promotion passe à 0 et se masque. Le filtre entre dans toutes les recherches, principale, comptes à
  part et bornes (`Search\SortQuery`, `sort/sort-query.ts`).
- **Visible tant qu'elle est choisie** (tranché par Louis le même jour), même quand plus aucun produit de
  la sélection n'est en promotion : le menu nomme le tri en cours, la grille dit « Aucun résultat ».
- **Clé d'URL `on_sale`**, comme `price_asc` ou `newest`. Un tri qui filtre restreint le listing : la page
  compte aussi le listing non filtré, pour que revenir à un autre tri ramène toutes les valeurs.

Coûts : une projection à réécrire et une réindexation ; un tri qui porte un filtre, ce que le modèle ne
savait pas faire ; et une option que le visiteur ne peut pas combiner avec un tri par prix.

### Le client est écrit en TypeScript et livré empaqueté (2026-09-16)

Tranché par Louis. Renverse deux décisions : « JavaScript, pas de TypeScript » et le répertoire portant
l'empreinte de `R-70`. Les deux vont ensemble : le navigateur ne lit pas le TypeScript, et un fichier
unique n'a plus d'imports relatifs à versionner.

- **Sources** : `resources/assets/ts/`, tests dans `tests/ts/`. Vérification stricte des types par
  TypeScript 7 dans `composer check`.
- **Syntaxe effaçable seulement** (`erasableSyntaxOnly`) : ni `enum`, ni `namespace`, ni propriété
  déclarée dans le constructeur. Node retire les types sans compiler, donc les tests s'exécutent sur
  les sources, sans étape de construction.
- **Livraison** : esbuild produit `resources/assets/dist/listing.js`, un seul module ES minifié, commité.
  Aucun Node n'est nécessaire pour déployer le module, ni pour un projet qui le réutilise.
  `composer check` reconstruit le paquet à côté et échoue si le fichier commité diffère.
- **Version** : `?ver=` issu de `filemtime()`, comme avant. Le défaut de `R-70` venait des imports
  relatifs, qu'un fichier unique n'a plus. Ni nom haché ni manifeste : rien à lire à chaque requête, et
  une page gardée en cache qui cite l'ancienne URL reçoit le nouveau fichier au lieu d'un 404.

Ce que ça achète : les types font partie du code et sont vérifiés ; un téléchargement au lieu de
vingt et un ; 8,5 Ko compressés au lieu de 25,1 ; `R-70` fermé.

Ce que ça coûte :

- **une étape de construction** : toute modification des sources demande `composer build` avant
  publication. Un oubli fait échouer `composer check`, jamais le site en silence ;
- **un fichier généré dans le dépôt**, dont le diff n'est pas relisible et qui se reconstruit en cas
  de conflit plutôt que de se fusionner ;
- **cinq dépendances de développement** : `esbuild`, `typescript-eslint` (sans lui, ESLint ne lit pas
  le TypeScript), `@types/node` (les tests importent `node:test`), et **deux TypeScript** — la 7 sous
  `@typescript/native`, qui vérifie les types, et la 6 sous `typescript` (paquet officiel
  `@typescript/typescript6`) pour `typescript-eslint`, qui n'accepte pas encore la 7 et lit l'API
  JavaScript que la 7 ne fournit plus. Tranché par Louis le même jour, sur la dernière version stable ;
  noms alignés le 2026-09-16 sur ceux que l'équipe TypeScript publie (`R-133`). La 7 est appelée par
  son chemin : `node_modules/.bin/tsc` pointe vers `@typescript/old`, le vrai compilateur 6 que
  l'enveloppe `@typescript/typescript6` charge et que npm remonte à la racine. `~6.0.2` fixe donc
  l'enveloppe, et seul le lockfile fixe le compilateur. La 6 disparaît
  quand `typescript-eslint` prend la 7 (7.1 attendue en novembre 2026). `esbuild` est épinglé à la
  version exacte, sans quoi une mise à jour changerait le fichier construit ;
- **Node 24.12 au moins** (`engines`), la version où le retrait des types devient stable ; 24.21.0 sur
  la machine et dans ddev. **Dans ddev la version est figée** (`nodejs_version: "24.21.0"`) : `"24"`
  restait sur celle de l'image. Chaque correctif de Node demande donc de monter ce numéro à la main,
  sans quoi ddev et la machine divergent de nouveau ;
- **un `node_modules` ne sert qu'un système** : esbuild et TypeScript 7 déposent un binaire par
  système, et npm 11.19 ne garde que celui du système qui installe. **Tranché par Louis le 2026-09-16 :
  pas de second `node_modules`** — l'outillage Node du module s'installe et tourne sur la machine,
  `composer check` compris ; ddev ne sert qu'au PHP (`--testsuite Modules`). `bundle.ts` construit et vérifie le
  paquet avec les mêmes options, sans les répéter dans deux commandes ;
- **les tests Node lisent les sources, pas le fichier livré** : un défaut propre à l'empaquetage ne se
  voit qu'en navigateur, ce que la définition de « fini » exige déjà ;
- `module:publish` copie tout `resources/assets`, sources TypeScript comprises : elles sont servies
  dans `public/` sans être chargées.
- **un lint qui lit les types** (`recommendedTypeChecked`, `projectService`), tranché par Louis le
  2026-09-16 avec `exactOptionalPropertyTypes` et `noImplicitOverride` dans `tsconfig.json` (`R-133`). Il
  a trouvé un défaut de `CardPainter` (renommée `CardView` le même jour) et trois lectures JSON non typées ; en échange, ESLint construit le
  programme TypeScript à chaque passage, et les appels de `node:test` y sont déclarés sûrs
  (`allowForKnownSafeCalls`) plutôt que signalés 236 fois.

### Le client se charge en priorité basse, surchargeable par filtre (2026-09-16)

Tranché par Louis : « la meilleure configuration possible, avec la possibilité de la surcharger par un
filtre ». Le script est inscrit en `fetchpriority="low"`, comme WordPress inscrit ses propres modules
depuis 6.9 : le listing est rendu par le serveur, le script ne fait que l'enrichir. Le filtre
`meilifacets/script_fetchpriority` (`ListingScript::PRIORITY_FILTER`) rend la main au projet ; la valeur
n'est pas validée par le module, WordPress le fait et retombe sur `auto`.

Ce que ça achète : 232 ms de LCP en « 4G lente » sur `/boutique` (`R-134`). Ce que ça coûte : le listing
se lie 75 ms plus tard sur le même réseau, rien en local. Premier filtre que le module déclare : les autres
points d'extension sont des contrats du conteneur.

### Le client échappe au Delay JS de WP Rocket (2026-09-16)

Tranché par Louis (`R-134`). Activé, le Delay JS de WP Rocket réécrit un `type="module"` en
`text/rocketlazyloadscript` et ne l'exécute qu'au premier geste du visiteur (`DelayJS/HTML.php:219-263`) :
le listing resterait inerte jusque-là. `ListingScript::excludeFromDelayedScripts()` ajoute le chemin du
paquet à `rocket_delay_js_exclusions`, brut : WP Rocket y échappe lui-même `+`, `?ver` et `#`, et un chemin
passé par `preg_quote` casserait sa regex.

Ce que ça coûte : le paquet s'exécute au chargement, y compris pour un visiteur qui ne touche à rien — la
priorité basse ci-dessus limite ce qu'il prend au rendu. Le module connaît le nom d'un filtre de WP Rocket,
sans effet quand l'extension est absente.

### Le client se range par fonctionnalité, sous des règles de taille (2026-09-16)

Demandé par Louis (chantier « qualité du JavaScript », `R-136`), valeurs validées par lui dans la
proposition du chantier. Les sources du client sont rangées par fonctionnalité — `shared`, `listing`,
`facets`, `price`, `sort`, `results`, `pagination` — et ESLint y impose 200 lignes par fichier, 20 par
fonction, une complexité de 8, trois paramètres et aucun ternaire imbriqué, lignes vides et commentaires
non comptés. Chaque filtre apporte sa part de la requête sous le même nom des deux côtés (`FilterQuery`,
`FacetQuery`, `PriceQuery`) ; `FacetCounter` reste le point d'extension du comptage.

Ce que ça achète : une fonctionnalité nouvelle ajoute ses fichiers et sa `FilterQuery` au lieu de modifier
les mêmes fichiers centraux, et aucun fichier du client ne grossit au-delà des règles sans que
`composer check` échoue.

Ce que ça coûte :

- **20 lignes par fonction, quand `CLAUDE.md` en demande « ~15 »** : la passe de lisibilité continue de
  relever l'écart ;
- **une API retirée** : `QueryPlan::counting()`, `priceBounds()`, `isCountedApart()` et
  `measuresPriceApart()` n'existent plus. Un projet qui remplace `FacetCounter` écrit
  `QueryPlan::apart($listing, $state, new FacetQuery($facet))`.

### Le client part de l'état que le serveur a lu (2026-09-17)

Tranché par Louis : « je te fais confiance pour retirer url texte comme tu l'as proposé ». Le client
ne tire plus aucun état d'une URL :

- au chargement, il part de l'état que `StateReader` a lu, publié dans la description (`state`) ;
- chaque entrée d'historique qu'un listing écrit porte son état, et l'entrée servie reçoit le sien au
  démarrage. Retour et Suivant le restaurent sans réécrire l'entrée ;
- une entrée qu'aucun listing n'a écrite reçoit l'état affiché si l'adresse est la même, et recharge
  la page sinon ;
- les valeurs sont triées par octet côté serveur (`sort($values, SORT_STRING)`) et par unité UTF-16
  côté client (`sort()`). Même ordre pour tout slug WordPress, que `sanitize_title_with_dashes` réduit à
  `[%a-z0-9_-]` ; les deux ne divergent que sur une valeur tapée à la main mêlant un caractère au-delà de
  U+FFFF et un caractère entre U+E000 et U+FFFF, qui ne désigne aucun terme.

Ce que ça supprime : `url-text.ts`, copie en TypeScript de `trim()`, `is_numeric()`, `(int)` et
`sort()` de PHP, qui aurait divergé en silence au premier changement de ces règles, et toute la lecture
d'URL du client. Les cas partagés portent désormais sur ce que le client écrit et que le serveur relit.

Ce que ça coûte :

- deux valeurs purement numériques changent d'ordre dans l'URL (`10,9` au lieu de `9,10`), et une facette
  à valeur unique forgée avec deux valeurs garde l'autre. Aucun slug numérique sur Pluralia (mesuré) ;
- un Retour vers une entrée écrite par un autre script, à une autre adresse, recharge la page ;

La sécurité ne change pas : la lecture d'URL du client ne protégeait rien, le verrou reste côté moteur
(`R-28`, `Q-03`).

### Le client réécrit l'adresse que WordPress a lue, jamais la sienne (2026-09-17)

Tranché par Louis : ne garder que les paramètres que WordPress lit lui-même pour construire la page.
Le serveur publie, octet pour octet, les paires de la query string brute dont le nom figure dans
`$wp->public_query_vars` — la liste de la plateforme, filtre `query_vars` compris —, hors les noms du
listing et hors `paged`, que le listing lit quand son propre paramètre de page manque. Le client les
réécrit après les siens, dans l'ordre de la requête.

Le chemin suit la même règle : le serveur publie celui de la première page, `get_pagenum_link(1)` — la
fonction de la pagination de WordPress et de WooCommerce —, qui retire le segment de pagination sous son
vrai nom et les barres de tête. Le client n'interprète plus aucun chemin : le numéro de page passe du
chemin à `pg`, `//boutique` devient `/boutique`.

Ce que ça corrige : `add-to-cart` ne survit plus à un geste — chaque rechargement remettait le produit
au panier —, ni `utm_*`, que Varnish n'efface qu'en tête d'URL. Une recherche produit garde `s` et
`post_type`.

Ce que ça coûte :

- lecture littérale : `orderby`, `order`, `filter_*` ou `query_type_*` restent quand la requête les
  porte, puisque WordPress ou WooCommerce les lisent. Aucun n'agit sur la grille, servie par le moteur ;
- deux listings d'une même page : l'un efface de l'URL les facettes de l'autre, comme avant `R-137` #6.
  Les noms réservés (`pg`, `sort`…) étant communs, une URL partagée ne portait déjà qu'un état.
- le chemin dépend de ce que les filtres `get_pagenum_link` et `user_trailingslashit` en font, et hérite
  d'un défaut du cœur : le segment n'est pas ancré, une page `/shop-page` découpée par `<!--nextpage-->`
  deviendrait `/shop-`. Aucun cas sur Pluralia.

### Le filtrage natif reste désarmé sur toutes les archives produit (2026-09-22)

Tranché par Louis (`R-147`). *Renverse la décision du 2026-09-17 — « chaque listing déclare les pages
WordPress qu'il sert » —, jamais codée.* WooCommerce ne filtre que la requête principale des archives produit
(boutique, recherche produit, taxonomies produit) ; un listing posé sur une autre page n'a rien à désarmer. Et
sur les archives produit, `ProductListing` aurait dû toutes les déclarer, puisque Pluralia y rend le listing
partout : une méthode de plus dans le contrat `Listing`, pour une portée identique. `NativeFiltering` est donc
inchangé ; sa documentation dit sa vraie portée, et le retour arrière documenté ne vise qu'une archive.

Ce que ça coûte : une archive produit qui garde la boucle native de WooCommerce reste désarmée tant que le
projet ne la réarme pas lui-même, par le filtre documenté dans `configuration.md` — le module ne peut pas
savoir ce que la vue rendra, Pollora lançant la requête principale (`wp()`) avant de choisir la route.

### Contre-exemple

`AmphiBee/MeiliSearchFacets` sert uniquement à cartographier le périmètre fonctionnel attendu.
Ce n'est pas un modèle de code, de découpage ni de conventions.

## En attente de validation

Rien de ce qui suit n'est acquis.

- **Alléger la requête principale des archives.** Le listing ne vient jamais de WordPress, mais
  sa requête principale s'exécute quand même — elle porte le routage, le contexte et le SEO, donc
  elle ne peut pas disparaître. On pourrait la réduire par `pre_get_posts` (configuration, pas
  interception : `posts_pre_query` reste banni) avec `no_found_rows` et un champ réduit. Deux
  réserves : le gain n'est pas mesuré, et une archive sans post peut basculer en 404. À traiter
  après une mesure sur un catalogue réel — suspendu d'ici là par `D-08` (`Q-30`).

- **Compteurs exacts sous variations** — le disjonctif ne corrige pas le surcomptage relevé
  dans [pieges.md](pieges.md) : `facetDistribution` n'est pas dédupliqué par produit. La seule
  voie connue est une sous-requête par **valeur**, dont on lit `estimatedTotalHits`. Le coût
  change d'ordre — cinq facettes de dix valeurs font cinquante sous-requêtes au lieu de cinq.
  Tranchable au lot 4, sur un catalogue qui contient enfin des variations.
- **`card.title` dans `searchableAttributes`** — `["*"]` rend le titre cherchable deux fois,
  dans `post_title` et dans `card.title`, ce qui dilue la pertinence. À traiter au lot 5.
- **Stock et variations WooCommerce.** Le prix est tranché : projection `price` du module (`D-b`,
  `D-i`, `D-e`, [prix.md](prix.md)). Le stock est différé (`D-f`, `D-g`) ; les variations relèvent
  du lot 4.
- **Schéma d'index persisté** — le principe d'une résolution en amont, hors requête, a été
  évoqué sans être arrêté.
- **Comportement après échecs répétés** — le client abandonne une requête au bout de cinq
  secondes et annule la précédente à chaque nouvelle ; ce qu'il fait après plusieurs échecs
  d'affilée n'est pas tranché.
- **Stratégie de cache HTTP** des pages de listing.
- **Tiroir, barre de filtres et repliables : choix des étapes 5a, 4b et 5b** (`R-173`, `R-174`,
  `R-175`, 2026-09-25, à valider par Louis ; les points marqués « Louis » sont ses consignes).
  - *État ouvert/fermé* : porté par `aria-modal`, que la feuille lit ; fermé, le tiroir mobile est
    `visibility: hidden`, hors de la tabulation et de l'arbre d'accessibilité. **Au repos, aucune
    transition** (2026-09-25, bug de Louis « le tiroir descend au chargement ») : les transitions de
    sortie ne vivent que sous `[data-closing]`, posé par `Drawer` à la fermeture et retiré quand les
    animations du tiroir (`getAnimations({ subtree: true })`) sont finies ; une réouverture ou une
    seconde fermeture entre-temps l'emporte (compteur de fermetures). Coût : un attribut d'état en plus
    de l'ARIA — l'ARIA dit « ouvert », `data-closing` dit « en train de partir », deux faits
    distincts. Sans lui, une transition portée par l'état fermé joue dès qu'un style arrive après le
    premier rendu et à chaque passage du seuil `48em`. *Précision* : une transition l'emporte sur une
    déclaration `!important` dans la cascade ; `hidden` + `display … allow-discrete` fonctionne donc
    malgré `[data-meili][hidden]{display:none !important}` (panneaux, poubelle — mesuré). La phrase
    précédente de cette décision affirmait le contraire.
  - *Sections refermées « a posteriori »* (Louis, 2026-09-25) : à la fin de la sortie du tiroir,
    `Drawer` rappelle `DisclosureGroup::collapseWithin()`, qui ferme ses sections sans animation
    (`PanelMotion::drop()`). En desktop, « Appliquer » ferme le panneau flottant comme tout clic
    extérieur (déjà le cas, test ajouté).
  - *Valeurs gardées en place* (Louis, 2026-09-25) : amende « Une valeur de facette est rendue dès
    que le listing non filtré la propose » — une valeur à 0 visible dans un panneau **ouvert** reste à
    sa place, **`aria-disabled="true"`** (sauf cochée) et atténuée (`opacity: 0.4`, neutre), et garde
    son rang pour le repli ; elle part à la fermeture du panneau (`DisclosureGroup` →
    `FacetsView::refold()`, après la sortie). Une valeur à 0 absente ne revient pas. *Amendé le
    2026-09-25 (Louis)* : `aria-disabled` plutôt que `disabled`, pour que la case garde le focus
    qu'elle tenait et reste annoncée « indisponible » ; le client annule le clic (donc Espace) sur une
    case `aria-disabled` non cochée (`FacetsView::refuses()`, `preventDefault()` avant tout `change`)
    et refuse un `change` qui la cocherait. Une case cochée n'est jamais `aria-disabled` : elle reste
    décochable. Coût : le refus est du code client, là où `disabled` était natif.
  - *Morph poubelle ↔ « Appliquer »* (Louis, 2026-09-25) : dans le pied du tiroir, la poubelle
    (`reset` `data-shape="icon"`) est hors flux, au bord de début ; « Appliquer » a une largeur
    explicite (`100 %` ou `100 % − poubelle − 16 px`), seule propriété de mise en page animée, et
    seulement tiroir ouvert — jamais au chargement ni au passage du seuil. Pas d'`interpolate-size` :
    les deux bornes sont des longueurs. Sortie de la poubelle par `hidden` + `display … allow-discrete`
    (même mécanisme que les panneaux), `interactivity: inert` et `pointer-events: none` pendant ses
    150 ms. Écarté : un attribut d'état + `visibility` (un état de plus pour ce que `hidden` dit déjà).
  - *Badge `active-count`* : entrée par WAAPI au seul passage 0 → 1 (`CountEntry`), pas par
    `@starting-style`, qui jouerait au premier rendu et quand un seuil réaffiche l'ouvreur.
  - *« Appliquer » en `immediate`* (Louis, 2026-09-25) : `visible-in-drawer` le rend avec `data-only="sheet"`, masqué partout par la feuille sauf
    dans le tiroir en sheet (JS actif, `< 48em`), où il ferme sans rechercher ; absent en desktop ; en
    `submit`, visible partout. Pas de seuil en TypeScript.
  - *Focus après « Tout effacer »* (Louis, 2026-09-25) : une remise à zéro qui se masque rend le focus
    à « Appliquer » du même tiroir (ou voisin hors tiroir), sinon au titre du tiroir en sheet, sinon à
    la racine du listing, qui reçoit `tabindex="-1"` à ce moment-là (`ResetFocus`) ; jamais `body`. Un
    focus déjà ailleurs n'est pas déplacé. Coût : un attribut posé par le client sur la racine.
  - *Grille différée derrière le sheet* (Louis, 2026-09-25) : tant qu'un tiroir couvre la page
    (ouvert, ou en train de sortir), la grille et la pagination ne sont pas repeintes ; la dernière
    réponse seulement l'est, l'image qui suit la fin de la sortie ou le passage du seuil
    (`HeldPaint`, `ListingDrawers`). Compteurs du tiroir, total et pastilles actives restent immédiats.
    Ne touche pas `D-10` (l'état et la recherche partent comme avant ; seul le dessin attend). Coût :
    derrière le voile, grille et total peuvent diverger le temps de la sortie.
  - *Styles en ligne du sheet* (Louis, 2026-09-25) : la hauteur en ligne n'existe que tiroir ouvert en
    sheet ; retirée à la fin de la sortie et au passage du seuil, remesurée depuis zéro à chaque
    ouverture ; la position et le voile d'un glisser sont retirés si le tiroir ferme ou dépasse le
    seuil en cours de geste (`DrawerGesture::cancel()`).
  - *Variantes plutôt que remplacement* (Louis, 2026-09-25) : une brique déjà rendue en `25a5aa3`
    garde son dessin sans attribut ; le nouveau vit derrière une variante explicite. `reset` :
    `shape="pill"` et `shape="icon"` (`ResetShape`, `data-shape`) ; pastilles de valeur :
    style « barre » (bordure `currentColor`, cochée inversée `CanvasText`/`Canvas`, appui
    `scale(0.96)`) sous `:has([data-meili="toggle"])`, c'est-à-dire `collapsible` ; appui
    `scale(0.97)` d'« Appliquer » réservé au composant `apply` (`:has(active-count)`) ; survol 150 ms
    des rangées réservé aux repliables — levé le 2026-09-25 : tous les survols lisent
    `--meili-duration-hover` (150 ms, `--meili-ease`), verdict Emil « cohesion matters » (coût : les
    rangées de la colonne et la poignée du prix passent de 120 à 150 ms). Rendus à l'ancien dessin : `active-value` (padding `0.85em`,
    gap `0.5em` — le thème Pluralia repose les 24 px), `sort-trigger` (padding `0.85em`), `reset`
    texte (rayon `0.25em`). Seule la hauteur (`--meili-control`) change pour tous.
  - *Panneaux flottants desktop* (Louis, 2026-09-25) : `width: max-content` entre `--meili-panel-min`
    (18rem) et `--meili-panel-max` (28rem, borné au viewport), prix fixe `--meili-panel-price`
    (20rem, piste ≥ 240 px).
  - *Mobile first* (Louis) : la base d'un repliable est une section d'accordéon en ligne ; le panneau
    flottant n'existe qu'à partir de `48em`. `DisclosureGroup` décide « flotte / en ligne » par
    `getComputedStyle(panel).position`, pour ne pas avoir de second seuil en TypeScript. Coût : une
    lecture de style par ouverture.
  - *Événement consommé* : Échap marqué `preventDefault()` par qui l'a traité (liste du tri, tiroir) ;
    `DisclosureGroup` et le tiroir ignorent un Échap déjà consommé. Le marquage des clics, essayé
    d'abord, est devenu inutile avec le mobile first (les sections du tiroir ne flottent jamais) : il
    a été retiré.
  - *Poignée* : un vrai élément `aria-hidden` qui porte le crochet `drawer-close` (un tap ferme), pour
    ne pas ajouter de crochet au contrat ; les styles du bouton ✕ sont donc limités à
    `button[data-meili="drawer-close"]`.
  - *Hauteur du sheet* : `SheetHeight` somme les hauteurs de ses parties en flux (le corps compté par
    son contenu) et l'écrit à chaque changement signalé par `ResizeObserver` ; la feuille interpole.
    C'est la seule propriété de mise en page animée, sur un seul conteneur. Le client prend le sheet
    comme **le premier enfant du tiroir** : c'est une règle de structure, pas un crochet (en
    ajouter un demandait l'accord de Louis). *Levé le 2026-09-25 (`R-178`, lot C, accord de Louis)* :
    le sheet et le pied portent les crochets `drawer-sheet` et `drawer-footer`, la rangée du tri en
    radios `sort-choice-row` ; feuille du module, thème et client les visent au lieu des classes et
    des balises. Additifs et hors de `RULES` : un tiroir surchargé sans eux démarre encore (sans
    hauteur animée ni glisser), donc `Contract::VERSION` reste à 1.
  - *Sections* : entrée par WAAPI, dont la durée se calcule une fois la hauteur connue ; sortie par la
    transition `[hidden]` de la feuille, chronométrée avant de masquer. Un `@starting-style` ne
    pouvait pas recevoir une durée mesurée après l'affichage.
  - *Glisser* : seuils de Vaul (un quart de la hauteur, 0,11 px/ms), vitesse mesurée sur les 100
    dernières ms. Le voile suit le geste par une propriété enregistrée **non héritée**
    (`@property --meili-scrim-shown`), que seul `::before` hérite explicitement : le reste du tiroir
    ne recalcule rien. La sortie passe par la transition CSS du tiroir, pas par un ressort (aucune
    dépendance). *Complété le 2026-09-25 (`R-176`)* : les zones qui gèrent leur propre geste — champs,
    `price-track`, `price-handle`, bouton ✕ — sont exclues **par sélecteur**, plus par
    `hasPointerCapture()` : au doigt, Chrome capture implicitement le pointeur sur la cible du
    `pointerdown`, si bien que le test était toujours vrai et que le glisser ne démarrait jamais.
    Coût : une liste de crochets à tenir à jour si une brique neuve gère son propre glisser.
  - *Tiroir `inert` pendant la sortie* (`R-176`, 2026-09-25) : posé dès `data-closing`, **après**
    que le focus a été rendu à l'ouvreur (un nœud `inert` qui tient le focus le lâche sur `body`),
    retiré à l'ouverture, à la fin de la sortie et au passage du seuil.
  - *Panneaux flottants* (ANIM-3/ANIM-4, `R-176`, Louis, 2026-09-25) : entrée **180 ms** (WAAPI,
    `--meili-duration-panel-in`, lu par `PanelMotion::pop()`), sortie **120 ms**
    (`--meili-duration-panel-out`) — durées fixes, le panneau ne pousse rien autour de lui ; les
    sections gardent la durée selon la hauteur. **Aucune animation** à la fermeture par Échap, par
    Tab qui quitte le panneau, ni au passage d'une pill à l'autre : `DisclosureGroup` pose
    `data-instant` sur le panneau, change son état, vide le style (`getAnimations()`) puis retire
    l'attribut. Le clic souris d'ouverture et de fermeture garde son animation. Coût : un drapeau
    « pointeur enfoncé » dans `DisclosureGroup`, parce qu'un appui déplace le focus **avant** son
    clic — sans lui, un clic sur une autre pill passerait par la sortie « Tab » et ouvrirait la
    suivante animée.
  - *Bouton ✕* (Louis, 2026-09-25) : plus de `scale(0.95)` au `:focus-visible` (une action clavier
    n'anime pas, et le bouton restait rétréci) ; `:active` garde `scale(0.75)` (Family drawer).
  - *Mouvement réduit* (`R-176`) : la sortie d'une section passe à `--meili-duration-fade` (150 ms,
    120 ms pour un panneau flottant) ; la liste du tri garde un fondu `opacity` de 160 ms au lieu de
    rien.
  - *Dimensions* : `--meili-control` passé à 3rem, padding latéral 24 px des pills (maquette,
    Louis). Espacements du tiroir **exacts de la maquette** (Figma `17:754`, corrigé le 2026-09-25 :
    le plafond à 24 px venait d'un malentendu, les « 24 px » de Louis visaient les pills) :
    `--meili-drawer-gutter` 2rem, `--meili-drawer-block` 2.5rem. **Aucune couleur du thème dans le
    module** : l'encre `#2d2b23` des pastilles cochées et de `filters.svg` est retirée (défaut neutre
    `CanvasText`/`Canvas` ; trait de `filters.svg` en `#000` explicite, comme `trash.svg`, puisque
    `currentColor` est sans effet dans un `<img>` — Louis, 2026-09-25) ; Pluralia la pose dans son
    thème par les crochets (et garde son icône en ligne, en `currentColor`). « Tout effacer » en mots
    en pastille (rayon 999px, padding des pills — Louis) : **variante** `shape="pill"`, le bouton texte
    par défaut garde son dessin (voir « Variantes plutôt que remplacement »).
  - *« Appliquer »* : plusieurs sur une page sont légitimes (une commande, pas un contrôle, comme
    `reset`). Pas de garde. `active-count` est désormais **vidé** à zéro en plus d'être masqué : il
    décrit un bouton, et un nœud masqué décrit quand même.
  - *Tri en radios* : jamais de badge sur son déclencheur ; un tri est un ordre, pas un filtre, et
    `active-count` ne le compte pas.
- **Structure de dossiers du module**, noms de commandes, format de configuration. *Dossiers
  tranchés en partie le 2026-09-24 : `ts/drawer/` et `ts/collapsible/` autorisés (voir « Validées »,
  [chantier-filtres.md](chantier-filtres.md) C-8) ; le reste demeure ouvert.*

## Dettes

- **`amphibee/meiliscout` pointe une branche non mergée.** Le module a besoin du correctif
  d'extensibilité de `feat/meilifacets` ; tant que la PR n'est pas intégrée, le projet dépend
  d'une branche qui peut diverger ou disparaître. Repasser à `dev-main` dès le merge.
- **`Modules/MeiliFacets` contient son propre dépôt git**, donc le projet ne peut pas versionner
  ses fichiers. **Voulu** (D-02) : le module est un produit à part. À rouvrir avant la première mise
  en production, pour choisir la forme de distribution — submodule déclaré ou paquet Composer.
- **Le plan de requête existe des deux côtés.** Le premier rendu le construit en PHP, le
  filtrage en JavaScript : une même règle, deux implémentations à tenir en phase. Inhérent au
  rendu serveur suivi d'un filtrage client. Bornée en couvrant les deux avec les mêmes cas.
  *Au 2026-09-22, rien ne la borne pour le plan : aucun jeu de cas commun entre `QueryPlan` et
  `listing-query.ts` (`R-32`).*
- **Prix par rôle client ou par géolocalisation devient impossible**, le prix étant formaté à
  l'indexation. Limite assumée, pas un travail à prévoir.
- **⚠️ Le `503` ne sort pas : Pollora écrase le statut HTTP de WordPress.**
  `WordPressHeaders::handle()` ne reporte jamais `http_response_code()` sur la réponse Symfony, qui
  part donc en `200` — et `shouldApplyPublicCache()` ne regarde pas le statut, si bien qu'un
  `Cache-Control: public, max-age=3600` est ajouté par-dessus notre `no-store`. Mesuré : moteur
  arrêté, la page répond `200` avec deux en-têtes `Cache-Control` contradictoires ; seul
  `Retry-After` survit. Correctif à proposer en amont, dans `WordPressHeaders::handle()` :

  ```php
  $status = http_response_code();

  if (is_int($status) && $status !== $response->getStatusCode()) {
      $response->setStatusCode($status);
  }
  ```

  et un `$response->isServerError()` en garde de `shouldApplyPublicCache()`. Tant que ce n'est pas
  corrigé, la vue de repli s'affiche mais la recette « 503 non mis en cache » n'est pas tenue.

- **La suite `Plugin` du projet est cassée** — 43 erreurs, `Plugin\PluraliaFulfillments\Console\
  ReplayOrderCommand` introuvable, plus un échec sur la structure des permaliens. Antérieur à
  MeiliFacets, vérifié en revenant au `phpunit.xml` d'origine. Sans rapport avec le module,
  mais il rend `vendor/bin/phpunit` sans argument inutilisable comme signal. *Au 2026-09-22, la
  classe citée existe (`pluralia-fulfillments/app/Console/ReplayOrderCommand.php`) ; l'état de la
  suite n'a pas été remesuré — la lancer écrit en base.*

## Historique

- **2026-09-02** — session de conception. Nom retenu. Documents réduits en fin de session aux
  seules décisions validées ; tout le reste a été reversé en questions ouvertes.
- **2026-09-02** — ouverture du lot 3. Quinze décisions prises, dont trois qui n'étaient pas
  prévues : la carte est projetée dans le document, les ancêtres de catégorie aussi, et le
  comptage des facettes passe par `multi-search`. Étape 3a livrée.
