# MeiliFacets — découpage en lots

Voir aussi : [installation.md](installation.md) · [architecture.md](architecture.md) · [configuration.md](configuration.md) · [pieges.md](pieges.md) · [decisions.md](decisions.md) · [revue.md](revue.md)

Sept lots. Chacun se termine par un critère de recette vérifiable — pas « c'est fait », mais
« voilà ce qu'on observe ».

**Les décisions encore ouvertes ne bloquent pas le découpage : elles se prennent au début du lot
qui les concerne.** Deux d'entre elles ne sont d'ailleurs pas tranchables aujourd'hui, faute de
catalogue contenant des produits variables.

La documentation n'est pas un lot final : chaque lot documente ce qu'il livre, sans quoi elle ne
sera jamais écrite.

| Lot | Objet | Dépend de | Décision qu'il tranche |
| --- | --- | --- | --- |
| 1 | Indexation et schéma | — | aucune |
| 2 | Client de recherche navigateur | — | aucune |
| 3 | Composants Blade et rendu | 1, 2 | taille de page, pagination |
| 4 | Prix, stock et variations | 1 | projection des variables, compteurs |
| 5 | Recherche et suggestions | 2, 3 | aucune |
| 6 | Diagnostics et robustesse | 1, 3 | aucune |
| 7 | Réutilisabilité | tous | extraction en paquet |

Les lots 1 et 2 sont indépendants l'un de l'autre et ne dépendent d'aucune décision ouverte : le
travail peut commencer par l'un ou l'autre.

---

## Lot 1 — Indexation et schéma

Donner à l'index une forme sur laquelle des facettes peuvent être construites.

- `FacetedPostIndexable`, substitué à celui de MeiliScout par le filtre `meiliscout/indexables`.
- Projection d'un champ par taxonomie, obtenue en regroupant le `terms` déjà construit par le
  parent — aucune requête WordPress supplémentaire.
- Déclaration des attributs filtrables : les taxonomies, `metas._price`, `metas._stock_status`.
- Énumérations de base : champs de document, types de facette, paramètres de requête.

**Recette —** un document produit porte `facets.product_brand` ; une requête de facette renvoie
une distribution par taxonomie et non une liste mélangée.

> Recette corrigée le 2026-09-02. Elle demandait de faire descendre le poids moyen d'un document
> sous 4,3 Ko en restreignant `displayedAttributes` — deux erreurs en une. Ce réglage ne touche
> ni au poids stocké (`avgDocumentSize` est resté à 4 519 o) ni à ce qui transite, que règle
> `attributesToRetrieve` par requête ; il décide de ce que la clé de recherche **autorise** à
> lire, ce qui est une question de sécurité restée ouverte ([decisions.md](decisions.md)).

## Lot 2 — Client de recherche navigateur

Le JavaScript qui interroge Meilisearch. Il ne produit aucun HTML : il traduit, dans les deux
sens.

- De l'état vers la requête : facettes cochées, tri et page deviennent `filter`, `sort`,
  `offset`, `limit` et `facets`.
- De l'URL vers l'état et retour : lecture des query vars au chargement, mise à jour de l'URL à
  chaque changement, historique du navigateur fonctionnel.
- Connexion par `MEILI_PUBLIC_URL` et `MEILI_SEARCH_KEY`, sans passer par PHP.
- Annulation des requêtes obsolètes : une réponse lente ne doit jamais écraser une plus récente.
- Remontée propre des erreurs au composant appelant.

**Recette —** depuis une page de test, cocher une facette met l'URL à jour et renvoie les bons
résultats, sans qu'aucun PHP ne s'exécute. Le retour arrière restaure l'état exact.

## Lot 3 — Composants Blade et rendu

Le premier lot visible. L'archive produit change de source de données — et de carte, la bascule
sur `<x-theme::product-card>` étant assumée : elle rend aujourd'hui la carte WooCommerce générique
d'Apiary, destinée à disparaître.

Livré en trois étapes. Après 3b, l'archive **affiche** le bon contenu pour n'importe quelle URL,
filtrée ou non — ce qui est ce que le référencement demande.

> Corrigé le 2026-09-04. Ce document affirmait qu'après 3b « l'archive fonctionne sans
> JavaScript ». C'est faux : il n'y a pas de `<form>` — choix assumé, un formulaire GET aurait
> produit `?marque[]=a&marque[]=b`, soit une seconde URL pour le même état — et le bouton est un
> `<button type="button">`. **Sans JavaScript, cocher une case ne fait rien.** À cette date, le
> tri, la pagination et la remise à zéro étaient encore de vrais liens et fonctionnaient seuls.
>
> > Corrigé le 2026-09-07. Ils sont devenus des `<button type="button">` le 2026-09-06
> > (« Les commandes sont des boutons »), donc **sans JavaScript le listing entier est inerte** :
> > seul le premier rendu est servi, ce qui reste la promesse tenue.

| Étape | Objet | État |
| --- | --- | --- |
| 3a | Projection de carte, ancêtres de catégorie, tri des valeurs de facette, tests des unités pures | **livrée** |
| 3b | Recherche serveur, déclaration de listing, composants Blade, repli `503`, noms de paramètres d'URL | **livrée** |
| 3c | Contrat de liaison, `multi-search` côté client, pagination, états de chargement | **3c-1 et 3c-2 livrés, 3c-3 en cours** |

3a porte les ancêtres parce qu'ils relèvent de l'indexation et qu'ils **bloquent 3b** : sans eux,
une archive de catégorie se vide dès qu'un produit est rangé dans un sous-rayon. La correction des
noms de paramètres est en 3b parce qu'elle touche `ListingUrl`, que 3c manipule — la faire après
reviendrait à réécrire ce qu'on vient d'écrire, et tout test manuel sur une URL filtrée subirait
d'ici là le double filtrage silencieux.

- `<x-meilifacets::listing>` et ses sous-composants : facette, résultats, pagination, tri, remise à
  zéro, compteur de filtres actifs. Libres de placement dans le gabarit.
- Rendu serveur de la première page, produits présents dans le HTML source.
- Mise à jour par liaison d'attributs, via les crochets `data-meili`.
- État de chargement : la grille servie est juste même filtrée, elle est conservée et atténuée
  pendant une recherche, jamais masquée.
- Vue de repli en `503`, avec `Retry-After` et `Cache-Control: no-store`.
- Branchement de `meilifacets.url_parameters` : le composant transmet au JavaScript le mapping
  taxonomie vers paramètre d'URL, que le client de recherche lit déjà.
- Intégration sur l'archive produit de `themes/pluralia`.

**Recette —** les produits sont dans le HTML source, un filtre les remplace sans rechargement, et
moteur arrêté la page répond `503` sans être mise en cache.

> Corrigé le 2026-09-07. Ce passage affirmait qu'« aucune `WP_Query` de produits n'est exécutée ».
> C'est faux, et R-36 le relevait déjà : la requête principale de l'archive est conservée — elle
> porte le routage et le SEO — et c'est seulement son résultat que le listing n'utilise pas.

**Aligné après coup —** le client JavaScript du lot 2 paginait encore sur `page`, la query var
WordPress que 3b venait d'écarter. Corrigé et couvert : les noms réservés se surchargent depuis
le serveur. Le formulaire de facettes, lui, a été retiré : une seule forme d'URL canonique.

**Corrections du 2026-09-04, avant d'ouvrir 3c —** quatre défauts trouvés par un audit externe et
vérifiés : le `noindex` débordait sur toute page portant `?q=` ou `?sort=` (l'accueil comprise) et
sortait à tort les pages paginées de l'index ; un tri inventé produisait un rendu et une entrée de
cache, sur un espace de clés infini ; une facette n'avait aucun plafond de valeurs ; et l'ordre
des valeurs n'était pas canonique, donc une même sélection occupait jusqu'à six entrées Varnish.

**Recette de 3b, vérifiée le 2026-09-03 —** `/boutique` rend les 12 produits dans le HTML source,
avec 19 valeurs de facettes et leurs compteurs ; `?marque=lumen` rend 2 produits, coche la seule
case correspondante, et **laisse les autres marques comptées** (3, 3, 2, 2) grâce au disjonctif ;
aucune requête SQL de produits n'est déclenchée par le listing (mesuré par `posts_request`) ;
moteur arrêté, la vue de repli s'affiche avec `Retry-After: 120`. **Réserve : le statut reste
`200`** — Pollora écrase celui de WordPress, voir les dettes de [decisions.md](decisions.md).

Ce que 3b livre : un contrat `Listing` découvert automatiquement, un `ProductListing` fourni par
le module, un plan de requête `multi-search` avec comptage disjonctif isolé derrière
`FacetCounter`, huit composants Blade libres de placement, la cascade de vues rétablie vers le
thème, et la commande `meilifacets:check-parameters` — qui a immédiatement trouvé un conflit réel
sur `product_tag => tag`.

**Recette de 3a, vérifiée le 2026-09-02 —** un document produit porte `card.price` formaté par
WooCommerce ; un produit rangé trois niveaux sous « Cheveux » remonte sur
`facets.product_cat = cheveux` ; la distribution revient triée par compte ; 33,7 Ko de JSON
tombent à 5,1 Ko par page une fois la carte seule demandée ; 11 tests PHPUnit et 23 tests Node
passent, dont la non-régression d'échappement de `ListingQuery`.

### Ce que 3c doit régler en premier

Dans cet ordre, tous relevés par l'audit du 2026-09-04 :

1. **Le client ne sait pas compter en disjonctif.** Il tape l'endpoint mono-requête
   `/indexes/{index}/search` ; le serveur, lui, envoie un `multi-search` avec une sous-requête par
   facette contrainte. Dès le premier clic, cocher une marque ferait tomber les autres à zéro —
   la page se contredirait entre son rendu et sa première interaction. `SearchClient` doit passer
   à `/multi-search`, et `ListingQuery` porter le plan complet, en miroir de `QueryPlan`.
2. **La pagination est fausse dès un vrai catalogue** : `estimatedTotalHits` est une estimation,
   le moteur plafonne à `maxTotalHits`, et `numbers()` rend tous les numéros — trois cents liens
   par page sur cinq mille produits, et au-delà du plafond une page vide servie en `200` sans
   message.
3. **Le contrat de liaison**, avant toute autre ligne : `data-*` documentés, version dans le
   markup, échec bruyant en console quand un thème surchargé ne le respecte pas.
4. **`popstate` n'est écouté nulle part.** `restoreFrom()` existe et n'est branché à rien : le
   retour arrière change l'URL sans repeindre. Et en mode `immediate`, une entrée d'historique par
   case cochée rendrait le bouton « Précédent » inutilisable.

### État au 2026-09-06

| Point de l'audit | État |
| --- | --- |
| 1. `multi-search` et comptage disjonctif côté client | **livré** — recetté en navigateur au lot 3c-1, 131 tests Node |
| 2. Pagination fausse sur un vrai catalogue | **livré** — `totalHits` remplace l'estimation, et la fenêtre ne propose jamais une page que le moteur refuse (`engine.reachable_hits`, R-42) |
| 3. Contrat de liaison | **livré** — 23 crochets dans `Enums\Hook`, `data-meili-contract="1"`, refus au démarrage, parité testée |
| 4. `popstate` | **branché** — `Listing.listenToHistory()`, appelé par `ListingBinding.start()` ; recette au lot 3c-3 |
| a11y : compteur dans le nom accessible | **corrigé** — `aria-describedby`, le nom de la case ne change plus au filtrage |
| a11y : règle `[hidden]` | **corrigée** — `resources/assets/css/meilifacets.css`, publiée et inscrite par le module |

Le contrat a entraîné trois changements de rendu que le client rendait obligatoires : ce qu'il met
à jour est rendu dans tous les cas et masqué par `hidden` (message vide, remise à zéro, badge,
nav de pagination, sept slots) ; un `<template>` de carte est rendu à chaque page ; et les
commandes sont devenues des `<button>` et une liste déroulante ARIA, ce qui a supprimé
`ListingUrls`.

**3c-1, livré le 2026-09-07** : inscription du script, démarrage sur vérification du contrat,
cocher et appliquer, mise à jour de l'affichage, `popstate` branché.

**3c-2, livré le 2026-09-07** : synchronisation des cases avec l'état, tri au clavier (motif ARIA
« combobox select-only »), pagination — plafonnée à ce que le moteur sert réellement — et remise à
zéro. `ListingBinding` n'est plus qu'un câblage : `ResultsView`, `FacetsView`, `PaginationView` et
`SortCombobox` portent le rendu.

**Ce qui reste à 3c-3** : état d'attente (`data-meili-busy`), comportement après échecs répétés,
recette de `popstate` et du mode `immediate`. Retirés de cette liste parce que livrés le
2026-09-07 : le `preconnect` (R-52) et le retour du regard en haut du listing (R-73).
`maxTotalHits` est mesuré et volontairement laissé à sa valeur par défaut (R-42).

## Lot 4 — Prix, stock et variations

Le morceau techniquement le plus difficile, isolé pour cette raison. Un produit variable n'a ni
prix ni stock propres : il a ceux de ses variations.

- Indexation à la variation, avec déduplication par produit à la recherche.
- Remontée d'une variation vers son parent pour la réindexation — sans quoi le prix affiché reste
  périmé.
- Hooks WooCommerce de stock, que les hooks de meta ne couvrent pas.
- Choix du calcul des compteurs de facettes, à la lumière du catalogue réel.

**Recette —** filtrer par prix renvoie le produit avec la variation correspondante et non la
fourchette entière ; un filtre croisé contenance et prix ne produit aucun faux positif ; une
commande qui décrémente un stock met l'index à jour.

## Lot 5 — Recherche et suggestions

L'usage le plus sensible à la latence, et donc la meilleure démonstration de l'architecture.

- Autocomplétion dans l'en-tête, multi-types : produits et articles.
- Page de résultats unifiée, filtrable par type.
- Réglages de pertinence : mots vides, synonymes, tolérance aux fautes.

**Recette —** la latence perçue entre la frappe et l'affichage est mesurée et tenue sous un seuil
arrêté à ce moment-là.

## Lot 6 — Diagnostics et robustesse

Rendre visible ce qui échoue en silence — trois maillons sur quatre ne lèvent aucune exception.

- Commande `doctor` : schéma désynchronisé, facette sans document porteur, attribut absent de
  l'index, index vide.
- Canal de log dédié dans la stack Laravel, pas un fichier maison.
- Timeout et repli côté rendu serveur.
- Messages en anglais énonçant la cause **et** l'action corrective, relayant `errorCode`,
  `errorType` et `errorLink` du moteur.

**Recette —** on casse volontairement une facette : `doctor` la nomme et donne la commande à
lancer.

## Lot 7 — Réutilisabilité

Ce qui transforme un module qui marche ici en module installable ailleurs.

- Commande d'installation enchaînant vérification, synchronisation et première indexation.
- Documentation d'installation, d'extension et de dépannage. **Fait le 2026-09-07**, par
  anticipation : les six documents vivent désormais dans `docs/` du module.
- Bloc Gutenberg de pose : il désigne un listing existant, il n'en configure aucun.
- Extraction en paquet Composer, si la décision est prise.

**Recette —** sur un projet Pollora neuf avec WooCommerce, une seule commande d'installation
suffit pour obtenir un listing à facettes fonctionnel, sans écrire de PHP.
