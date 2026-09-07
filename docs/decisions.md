# MeiliFacets — décisions

Voir aussi : [installation.md](installation.md) · [architecture.md](architecture.md) · [configuration.md](configuration.md) · [lots.md](lots.md) · [pieges.md](pieges.md) · [revue.md](revue.md)

## Validées

| Sujet | Décision |
| --- | --- |
| Nom | **MeiliFacets**, module Pollora générique, réutilisable hors de Pluralia |
| Rôle de MeiliScout | indexation seule ; les facettes sont construites dans MeiliFacets |
| Rendu de la page | WordPress, coût habituel assumé |
| Listing et facettes | Meilisearch |
| Transport | le navigateur interroge Meilisearch en direct — **aucun proxy PHP, aucun repli** |
| Front | classes ES sans dépendance, livrées par le module, markup entièrement surchargeable par le thème |
| Langage | JavaScript, **pas de TypeScript** |
| Intégration | composants Blade ; bloc Gutenberg envisagé plus tard |
| Chargement | état de chargement dans le composant listing, seuils calibrés sur la mesure |
| URLs | chemins natifs WooCommerce conservés, filtres en query vars |
| Panne du moteur | vue Blade de repli annonçant un problème technique |
| Diagnostics | en anglais, avec cause et action ; libellés d'interface traduisibles |
| Code | pas de chaînes littérales : énumérations ou constantes |
| Multilingue | non implémenté, mais l'architecture doit le permettre sans refonte |
| Projection des facettes | un champ par taxonomie, via un indexable étendu substitué par `meiliscout/indexables` |
| Taxonomies filtrables | toutes celles indexées par MeiliScout — ce qui est indexé est filtrable |
| Unité de résultat | le produit — il n'apparaît **jamais deux fois** dans un listing |
| Produits variables | quand un filtre ne correspond qu'à certaines variations, on affiche le produit avec les informations de la variation correspondante |
| Carte produit | markup rendu par le serveur, mis à jour par liaison d'attributs |
| Repli | `503`, `Retry-After`, `Cache-Control: no-store` |
| Clé de recherche | clé fixe fournie par l'infrastructure (submodule Docker AmphiBee) — le module ne la crée pas |
| Variables d'environnement | conventions existantes des projets AmphiBee : `MEILI_HOST`, `MEILI_KEY`, `MEILI_INDEX_NAME`, `MEILI_MATCHING_STRATEGY`, `MEILI_PUBLIC_URL`, `MEILI_SEARCH_KEY` |
| Version du moteur | local sur `latest`, non épinglé ; la production en 1.10.3 **doit être montée de version** — prérequis de déploiement |
| Découpage | sept lots, décrits dans [lots.md](lots.md) ; les décisions ouvertes se prennent au début du lot concerné |
| Dépendance MeiliScout | épinglée sur `dev-feat/meilifacets` (commit `1c59a05`) — **dette : repasser à `dev-main` après merge de la PR** |
| Noms des paramètres d'URL | figés en configuration (`meilifacets.url_parameters`), jamais dérivés d'un libellé de taxonomie |
| Indexation des URLs de listing | `noindex, follow` sur toute URL portant **un paramètre de listing rempli** — facette, tri, recherche ou page — et **uniquement sur une page d'archive ou de recherche** ; **pas** de canonique vers le chemin nu. *Élargi le 2026-09-06 : un tri et une page étaient indexables, ce qui laissait entrer des doublons ; la garde de contexte a rendu l'élargissement sûr.* |
| Canonique d'une URL de listing | **supprimée** quand le module pose `noindex` — une canonique désigne deux URLs comme une seule page, et l'associer à un `noindex` revient à dire de cette page unique qu'elle ne doit pas être indexée. Filtre `wpseo_canonical` à la priorité 20, pour passer après l'intégration WooCommerce de Yoast. Inerte sans Yoast |
| Pagination native de WordPress | `/page/N` **sert la vraie page N** — le listing lit `paged` en repli de `pg` — et reste en `noindex`, sans canonique et sans `rel="next"`/`"prev"`. Les deux paginations coexistaient sans se connaître : `/page/2` à `/page/5` servaient quatre fois la première page, en `index`, avec une canonique auto-référente et une chaîne `rel="next"` qui les liait toutes. *Tranché le 2026-09-06 après analyse dédiée (R-60).* |
| Forme canonique d'une URL | valeurs triées et dédupliquées des deux côtés — sans quoi `?marque=a,b` et `?marque=b,a` sont deux entrées Varnish pour un même état |
| Entrées non validées | un tri absent de `sorts()` est ignoré, une facette est plafonnée à son `cap`, la recherche à 200 caractères — un paramètre libre est un vecteur de saturation du cache |
| Défense de `hidden` | contre-règle CSS imprimée par le module dans `wp_head`, restreinte à ses propres classes — un fichier ne serait pas servi, `Modules/` étant hors du docroot |
| Markup du prix | rendu tel que WooCommerce le produit, sans liste blanche : filtrer casse les promos et les fourchettes, et ne protège d'aucun scénario réaliste. **À réexaminer si le prix incorpore un jour une donnée saisie par un utilisateur non privilégié** — un champ de personnalisation, un message promotionnel éditorial. Une revue automatique a classé ce retrait « XSS stockée, HIGH » le 2026-09-04 ; le signalement a été écarté faute de vecteur, pas par principe |
| Mise à jour du DOM | découpage par élément selon le focus : cartes clonées depuis un `<template>`, valeurs de facettes en nœuds stables, pagination en fenêtre fixe |
| Documentation | **dans le module, `docs/`** — rapatriée depuis `docs/meilifacets/` du projet le 2026-09-07, avant le lot 7 : elle ne faisait que grossir, et chaque jour ajoutait des liens à réécrire |
| Données de carte | projetées dans le document (champ `card`), pas recomposées côté client |
| Point d'extension de la carte | contrat `CardProjector` avec une implémentation par défaut — jamais obligatoire |
| Ancêtres de catégorie | la chaîne complète est indexée, pas seulement le terme assigné |
| Compteurs de facettes | `multi-search` disjonctif, isolé derrière un point d'extension |
| Envoi des recherches | derrière le contrat `SearchEngine` ; le client Meilisearch est injecté, jamais résolu statiquement |
| Repli `503` | objet `Unavailable` lié en `scoped`, pas d'état statique |
| `displayedAttributes` | restreint à `ID` et `card` par défaut, ouvert par `meilifacets.displayed_attributes` ; `'*'` rouvre tout |
| Mode de sélection | déclaré par facette ; le disjonctif n'est produit que pour le multi-sélection |
| Pagination | pages numérotées en query var, sans rechargement ; pas de « charger plus » |
| Valeurs par facette | 10 visibles, le reste rendu et replié, plafond par défaut à 30 |
| Tri des valeurs de facette | le moteur compte (`sortFacetValuesBy`), la facette déclare l'ordre d'affichage (`DisplayOrder`) |
| Taille de page | dérivée du contexte au rendu, jamais recopiée en configuration |
| Rendu serveur | applique les filtres de l'URL ; Varnish cache chaque combinaison 180 s |
| Repli des paramètres d'URL | une taxonomie non mappée prend un préfixe, jamais son nom nu |
| Défauts des paramètres réservés | `sort`, `q`, `pg` — en anglais, le projet les habille |
| Facettes de l'archive produit | marque, contenance et catégorie, toutes en multi-sélection |
| Markup des facettes | cases à cocher rendues cochées par le serveur, jamais des liens par valeur |
| Déclenchement de la recherche | `meilifacets.apply_mode` : `submit` par défaut, `immediate` selon le volume ; l'attribut `data-apply` porte le choix jusqu'au JavaScript du lot 3c |
| Forme des valeurs multiples | une seule, `?marque=a,b` — un formulaire GET n'aurait produit que `marque[]=a&marque[]=b`, soit deux URLs et deux entrées Varnish pour un même état |
| Paramètres d'URL côté client | le JavaScript lit les mêmes noms que le serveur : `pg`, `sort`, `q`, préfixe `f_` |
| Déclaration d'un listing | classe implémentant `Listing`, découverte automatiquement |
| Listing produit | livré par le module quand WooCommerce est actif |
| Facette catégorie sur une archive de catégorie | **conservée et restreinte au niveau courant** (`ChildTermsFacet`) : elle propose les enfants directs du rayon, rien sur une feuille. *Renversé le 2026-09-06 — elle était retirée, la maquette cliente demande l'inverse.* |
| Surcharge du markup | le module ajoute le thème en tête de sa cascade de vues |
| Requête principale des archives | conservée : elle porte le routage et le SEO ; `posts_pre_query` reste banni |
| Taille de page | `apply_filters('loop_shop_per_page', …)`, jamais `wc_get_loop_prop('per_page')` |
| Carte de l'archive produit | bascule sur `<x-theme::product-card>`, changement d'apparence assumé |

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
reste à confirmer.

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
construction d'URL à tenir alignée avec `listing-url.js` ; et la réécriture des `href` après chaque
recherche, qui aurait été obligatoire sinon.

Ce que ça coûte : sans JavaScript le listing est inerte. Cohérent avec les facettes, sans
formulaire depuis le lot 3b.

### Contrat `data-meili` (2026-09-04)

Le client adresse des attributs `data-meili`, jamais des classes, et la racine porte une version
comparée à `Contract::VERSION`. Version différente ou crochet structurel manquant : le JavaScript
ne démarre pas, la console nomme ce qui manque, la page servie reste celle du serveur.

Le refus ne porte que sur ce que le thème contrôle. `facet-value`, `count`, `sort`, `apply` sont
optionnels : leur absence peut venir de la donnée (un listing sans résultat n'a aucune valeur de
facette) ou d'un choix de thème légitime. Exiger leur présence rendrait le module inutilisable sur
un thème qui n'affiche pas de compteurs.

Deux conséquences acceptées sur le rendu serveur : les éléments que le client met à jour sont
rendus dans tous les cas et masqués par `hidden` plutôt qu'omis (message vide, remise à zéro,
badge, nav de pagination, sept slots de page) ; et un `<template>` de carte est rendu à chaque
page, soit une carte vide en plus dans le HTML. C'est le coût de la règle « le markup appartient
au thème » : sans lui, le client porterait sa propre copie du markup.

**Limite assumée** : une valeur de facette absente du HTML parce que son compte est nul ne peut
pas réapparaître côté client. Le comptage disjonctif couvre le cas courant. Le cas croisé
demanderait un `<template>` par facette — à rouvrir si le catalogue réel le montre.

### Chaînes d'interface (2026-09-03)

Le code écrit ses chaînes en anglais, sans domaine de texte, et le français vit dans
`Modules/MeiliFacets/lang/fr.json`, chargé par `registerTranslations()` du provider nwidart. Un
`__('Brand')` non traduit sort en anglais, ce qui se voit ; il ne casse rien.

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
  après une mesure sur un catalogue réel.

- **Compteurs exacts sous variations** — le disjonctif ne corrige pas le surcomptage relevé
  dans [pieges.md](pieges.md) : `facetDistribution` n'est pas dédupliqué par produit. La seule
  voie connue est une sous-requête par **valeur**, dont on lit `estimatedTotalHits`. Le coût
  change d'ordre — cinq facettes de dix valeurs font cinquante sous-requêtes au lieu de cinq.
  Tranchable au lot 4, sur un catalogue qui contient enfin des variations.
- **`card.title` dans `searchableAttributes`** — `["*"]` rend le titre cherchable deux fois,
  dans `post_title` et dans `card.title`, ce qui dilue la pertinence. À traiter au lot 5.
- **Prix, stock et variations WooCommerce** — absents de `terms`, mode de projection non défini.
- **Schéma d'index persisté** — le principe d'une résolution en amont, hors requête, a été
  évoqué sans être arrêté.
- **Comportement après échecs répétés** — le client abandonne une requête au bout de cinq
  secondes et annule la précédente à chaque nouvelle ; ce qu'il fait après plusieurs échecs
  d'affilée n'est pas tranché.
- **Stratégie de cache HTTP** des pages de listing.
- **Structure de dossiers du module**, noms de commandes, format de configuration.

- **Statut `503` sur panne du moteur** — voir les dettes : Pollora écrase le statut.

## Dettes

- **`amphibee/meiliscout` pointe une branche non mergée.** Le module a besoin du correctif
  d'extensibilité de `feat/meilifacets` ; tant que la PR n'est pas intégrée, le projet dépend
  d'une branche qui peut diverger ou disparaître. Repasser à `dev-main` dès le merge.
- **`Modules/MeiliFacets` contient son propre dépôt git**, donc le projet ne peut pas versionner
  ses fichiers. À trancher : submodule déclaré, installation par Composer, ou dépôt imbriqué
  retiré.
- **Le plan de requête existe des deux côtés.** Le premier rendu le construit en PHP, le
  filtrage en JavaScript : une même règle, deux implémentations à tenir en phase. Inhérent au
  rendu serveur suivi d'un filtrage client. Bornée en couvrant les deux avec les mêmes cas.
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
  mais il rend `vendor/bin/phpunit` sans argument inutilisable comme signal.

## Historique

- **2026-09-02** — session de conception. Nom retenu. Documents réduits en fin de session aux
  seules décisions validées ; tout le reste a été reversé en questions ouvertes.
- **2026-09-02** — ouverture du lot 3. Quinze décisions prises, dont trois qui n'étaient pas
  prévues : la carte est projetée dans le document, les ancêtres de catégorie aussi, et le
  comptage des facettes passe par `multi-search`. Étape 3a livrée.
