# MeiliFacets — chantier « recherche du site »

Voir aussi : [chantier-filtres.md](chantier-filtres.md) · [chantier-filtres-architecture.md](chantier-filtres-architecture.md) · [revue.md](revue.md) · [decisions.md](decisions.md) · [lots.md](lots.md)

Suivi d'avancement du chantier ouvert le 2026-09-25 sur la branche **`feat/site-search`** (créée depuis
`main` = `f2303a8`). Correspond au **lot 5** de [lots.md](lots.md) (« Recherche et suggestions »). Ce
fichier est un tableau de bord : les constats vont dans `revue.md`, les décisions dans `decisions.md`,
ici on ne tient que l'état et l'architecture proposée.

**État au 2026-09-25 — étape 1 (architecture) rendue ; toutes les décisions prises par Louis le même jour, à
reporter dans le registre et `decisions.md` avant de fermer l'étape. Aucun code.** Forme retenue : **un panneau déroulant pleine largeur sous l'en-tête, non modal** (pas de
`<dialog>`), ouvert par la loupe. « Voir tous » = lien vers l'archive du type, sans query var. Pas encore de
maquette : on avance sans (D-9).

Conventions de ce document : **tranché** = décidé par Louis ; **proposé** = recommandation à confirmer.
Les questions nouvelles ouvertes par l'architecture sont numérotées `S-n` (les `D-n` du cadrage restent
tels quels ; ils ne sont pas les `D-xx` du registre).

---

## Cahier des charges (Pluralia)

> La recherche recherche les produits et les articles.
>
> Le moteur de recherche s'appuie sur Meilisearch afin de garantir une recherche rapide et pertinente
> dans le catalogue et dans les articles du journal.
>
> Au clic de la barre de recherche, l'utilisateur peut saisir la donnée de son choix. Après avoir saisi
> plusieurs caractères, la recherche s'active pour proposer des résultats pertinents.
>
> AmphiBee affichera (s'il le peut) les 4 produits les plus pertinents avec le contenu saisi ainsi que
> 4 articles de journal les plus pertinents avec le contenu saisi.
>
> Si le moteur juge qu'il y a moins de 4 résultats pertinents alors il en affiche moins. Il est possible
> de n'afficher aucun résultat. Dans ce cas, AmphiBee affichera un message informatif « Aucun élément ne
> correspond à votre recherche ».
>
> Pour les produits et les articles, le moteur de recherche va comptabiliser le nombre de résultat
> associé à la saisie. Le bouton « voir tous les produits » affichera une page de liste présentant les
> résultats. L'intégration de la page sera similaire à la page de liste « Catégorie ». AmphiBee retirera
> la section hero, les filtres et les blocs de contenus pour n'afficher que les produits résultats et la
> pagination éventuelle.

### Lecture du cahier des charges

| Exigence | Traduction technique |
| --- | --- |
| Recherche hors listing, depuis la loupe de l'en-tête | un **panneau déroulant** pleine largeur sous l'en-tête (motif disclosure), indépendant de tout `<x-meilifacets::listing>` ; champ en haut, résultats dessous |
| « après plusieurs caractères » | seuil minimal de saisie avant la première requête (D-2) |
| 4 produits + 4 articles « s'il le peut », moins si moins pertinents, aucun possible | une requête **multi-search** : une sous-requête par **type déclaré** (D-3), `hitsPerPage: 4` chacune |
| « comptabiliser le nombre de résultats » pour produits et articles | le **total par type**, affiché dans l'en-tête de chaque section (S-8) |
| « Aucun élément ne correspond à votre recherche » | message quand toutes les sections sont vides (libellé traduisible, surchargé par le thème) |
| « voir tous les produits » → page de liste façon « Catégorie », sans hero, filtres ni blocs de contenu | **tranché autrement par Louis (S-2)** : un simple lien vers la **racine de l'archive du type** — la Boutique pour les produits, la page des articles pour le journal —, sans aucune query var. Aucune page de résultats dans ce chantier |

Le lot 5 de `lots.md` annonçait une « page de résultats unifiée, filtrable par type » : la décision de Louis
(S-2, S-20) la **remplace** par les liens vers l'archive de chaque type. À corriger dans `lots.md` et à
consigner dans `decisions.md` à la clôture de l'étape 1.

---

## Ce qui existe déjà (vérifié le 2026-09-25)

| Élément | État | Source |
| --- | --- | --- |
| `/?s=terme&post_type=product` | **déjà servi par Meilisearch** via le listing produit : `ProductListing::baseQuery()` lit le `s` de WordPress quand `is_search()` | `app/Listing/ProductListing.php:102-104`, `R-158` |
| `/?s=terme` sans type de contenu | recherche **native** WordPress, gabarit de recherche du thème ; `ListingPage::isCurrent()` la compte pourtant comme listing | `R-161` |
| Paramètre `q` du module | lu, borné à 200 caractères et envoyé au moteur, mais **aucun champ ne le saisit ni ne l'affiche** | `R-44`, question `Q-09` |
| Pertinence | `searchableAttributes: ["*"]` : pas de hiérarchie des champs, et les métadonnées internes restent **interrogeables** par la clé publique | `R-27` |
| Exclusions WooCommerce | un produit `exclude-from-search` reste trouvable par le module | `R-160` |
| Latence serveur | `ClientFactory` de MeiliScout fait un `checkdnsrr` puis un `GET /health` devant la première recherche de chaque processus | `R-19` |
| Contenus indexés | option `meiliscout/indexed_post_types` = `post`, `product` (pas de pages) ; `meiliscout/indexed_taxonomies` **vide** depuis l'import de base du 2026-09-24 — *remplie depuis : relu le 2026-09-25 en fin de journée, voir le complément ci-dessous* | options WordPress |
| Interception de `WP_Query` par MeiliScout | `QueryIntegration` (`posts_pre_query`) n'agit que si la requête porte `use_meilisearch`, appelle `search('', …)` (**ignore le terme recherché**) et reconstruit les `WP_Post` depuis l'index, qui n'expose plus que `ID` et `card` : **inutilisable telle quelle** pour une recherche texte | `meiliscout/src/Query/QueryIntegration.php:50-111` |
| Rôle de MeiliScout | décision validée : **indexation seule** | `decisions.md` « Validées » |
| Briques réutilisables du module | client Meilisearch navigateur, clé de recherche seule publiée, annulation des requêtes obsolètes, description JSON, `CssTiming`, `Entrance`, tokens de mouvement, cartes produit (`card`) | chantier filtres |

### Complément vérifié pour l'architecture (2026-09-25)

Lu dans le code (sous-agents, lecture seule) et dans l'index local (API Meilisearch 1.53.1, lecture
seule, clé d'administration du `.env` hôte — jamais recopiée).

| Élément | État | Source |
| --- | --- | --- |
| Client navigateur | `SearchClient.search(Record<string, SearchQuery>)` poste toujours sur `/multi-search` (pas de fédération), rend les réponses sous les clés de l'appelant ; **un seul `AbortController`**, annulé à chaque appel, plus `AbortSignal.timeout(5000)` ; `SearchQuery` ne connaît que `q`, `filter`, `facets`, `hitsPerPage`, `page`, `attributesToRetrieve`, `sort`. **Aucune temporisation** nulle part dans le client | `ts/shared/search-client.ts:5-13,58-61,71-114` |
| Publication de la connexion | `ListingScript::require()` inscrit le module `@meilifacets/listing` (priorité basse, exclu du Delay JS de WP Rocket) et publie `connection {url,key,index}` par `script_module_data_@meilifacets/listing` — **seulement quand un `<x-meilifacets::listing>` est rendu** | `app/View/ListingScript.php:35-93`, `app/View/Components/Listing.php:23-24` |
| Clé du navigateur | `browser.url` / `browser.key` (config du projet, depuis `MEILI_PUBLIC_URL` / `MEILI_SEARCH_KEY`) ; la clé `pluralia-search` a pour actions `search` et pour index **`posts` seul**, sans expiration : l'index `taxonomies` lui est fermé | `SearchServiceProvider.php:40-47`, `GET /keys` |
| Carte | `card.blade.php` (crochets `url`, `image`, `title`, `price`), `<template data-meili="card-template">` cloné par `ResultsView`, rempli par `CardView` (`price` en `innerHTML`) ; le contrat exige `price` dans le template | `results.blade.php:18-22`, `results/card-view.ts:70-79`, `shared/contract.ts:16-30` |
| Projection `card` | posée sur **tous** les types indexés (`MeiliScoutBridge::addCard`) : `title`, `url`, `image_url/alt/width/height`, et `price` pour un produit seulement ; **ni extrait, ni date, ni rubrique** | `Indexing/MeiliScoutBridge.php:57-63`, `Enums/CardField.php` |
| Contrats déclarés | `ProductFacets`/`ProductSorts` liés en `scopedIf` (`ListingServiceProvider.php:37-38`), `CardProjector` en `bindIf` ; Pluralia lie `CatalogueFacets` en `scoped` | `AppServiceProvider.php:30` (hôte) |
| Registre des listings | `ListingRegistry::sole()` **lève dès qu'il existe deux listings** : un `<x-meilifacets::listing>` sans `name` casse alors | `app/Discovery/ListingRegistry.php:29-40` |
| Crochets hors listing | `Contract.orphans()` signale **tout** `[data-meili]` hors d'une racine `[data-listing]` | `ts/shared/contract.ts:62-69` |
| Fermeture des panneaux flottants | Échap (focus rendu au déclencheur), clic hors du chemin (`composedPath`), focus sorti hors appui (`InputSource`) : ~30 lignes **mêlées** à l'exclusivité du groupe et au flottement lu dans la feuille, sur un `Contract` de listing | `ts/collapsible/disclosure-group.ts` |
| En-tête du thème | une loupe nue, sans formulaire ni gestionnaire (`.pluralia-header__search-toggle`) ; un composant Alpine `productSearch` + route `/api/products/search` (SQL) **jamais utilisés** | `parts/header/row.blade.php:73-75`, `js/frontend/product-search.js`, `routes/api.php:18` (thème) |
| Gabarits de recherche | pas de `Route::wp('search')` : `/?s=` rend `search.blade.php` (titre, extrait, sans carte) ; `/?s=&post_type=product` rend `woocommerce/archive-product.blade.php`, **sans branche `is_search()`** (le `<h1>` reste celui de la boutique) | `routes/web.php:21-47`, thème |
| Cartes d'article du thème | `parts/posts/post-card.blade.php` (image `banner`, catégorie, `contenu`, titre, extrait, temps de lecture) nourrie par `App\Cms\Posts\PostCard` | thème |
| `exclude-from-search` | géré nulle part ; aucun produit ne porte ce terme en local (compte 0) | `ProductListing.php:77-88`, `tests/Feature/ProductSearchTest.php:60-65` |
| `indexed_taxonomies` | remplie : `category`, `contenu`, `product_brand`, `product_type`, `product_cat`, `mb-views-category` ; index `taxonomies` : **127 termes** | options WordPress, `GET /indexes/taxonomies` |

### Ce que contient l'index `posts` (lu le 2026-09-25)

- **67 documents** publiés : **62 produits, 5 articles**. Un seul index pour tous les types.
- Réglages : `displayedAttributes ["ID","card"]`, `searchableAttributes ["*"]`, `rankingRules` par défaut,
  `typoTolerance` par défaut (une faute dès 5 lettres, deux dès 9), `stopWords`/`synonyms` vides,
  `prefixSearch "indexingTime"`, `facetSearch true`, `maxTotalHits 1000`.
- **Document article** : toutes les colonnes de `wp_posts` (`post_title`, `post_excerpt`, `post_content`
  en balisage de blocs brut, `post_date`, `post_name`…), `url`, `terms[]` (`category`, `contenu`),
  `metas` (dont `_thumbnail_id`, `_yoast_*`, `_edit_lock`), `facets` (`category`, `contenu`), et `card` =
  `title`, `url`, `image_url` (taille `medium`, 300 px), `image_alt` (vide), `image_width`, `image_height`.
  **Pas d'extrait, de date ni de rubrique dans `card`** : une carte d'article « titre, extrait, image »
  demande donc de projeter l'extrait (S-7). L'article par défaut de WordPress, « Bonjour tout le
  monde ! », est publié et indexé (sans image) : il sortira dans les résultats tant qu'il existe.
- **Document produit** : `card` avec `price` (HTML de WooCommerce) ; `metas._sku` sur 49 produits sur 62,
  `post_excerpt` sur 12, `post_content` sur 50 ; noms de marque, de catégorie, de tag et des taxonomies
  Pluralia **tous mêlés dans `terms.name`** — aucun champ ne sépare la marque de la catégorie.
- **Mesures qui décident de la pertinence** (multi-search produits / articles, `limit 0`) :

  | Terme | Produits | Articles | Lecture |
  | --- | --- | --- | --- |
  | `pluralia` (et `_pluralia`) | 62 | 5 | **tout l'index** : le domaine est dans `url`, `guid`, `card.url` et `card.image_url` |
  | `spacer` | 3 | 1 | le **balisage des blocs** de `post_content` est cherchable |
  | `paragraph` | 0 | 4 | idem |
  | `lumen` (marque) | 5 | 0 | attendu |
  | `srum` | 0 | — | 4 lettres : aucune faute tolérée par défaut |
  | `serom` | 1 | — | 5 lettres : une faute tolérée |

- **Surlignage** : `attributesToHighlight: ["card.title"]` ne rend **aucun** `_formatted` ;
  `["card"]` surligne **tous** les sous-champs — l'URL comprise (`/produit/<em>serum</em>-eclat-…`) et le
  HTML du prix — et rend les nombres en chaînes. Le client ne doit donc lire que `_formatted.card.title`
  (et l'extrait), jamais l'URL ni le prix.
- **Seuil de pertinence** : `rankingScoreThreshold` retire les résultats **et les décompte du total**
  (`visage` : 8 au seuil 0,5, 0 au seuil 0,8). Utilisable, non retenu au premier livrable (S-14).

---


## Passe de conformité (`CLAUDE.md` § 1, 2026-09-25, revue après les décisions de Louis)

**En quatre lignes.**
- **Change** : ajoute au module une recherche multi-types dans un panneau sous l'en-tête (types déclarés par le projet, un lien « voir tous » par type vers son archive), et fixe la pertinence de l'index.
- **Ferme** : `R-27` (fuite par `searchableAttributes`), `R-160` (`exclude-from-search`), `R-159` (terme sans mot, côté panneau et listing), et la décision en attente « `card.title` dans `searchableAttributes` ». **Hors chantier** : `R-44`/`Q-09` (`q` inchangé), `R-161` (aucune page de recherche touchée), `R-19` (le panneau ne passe pas par PHP), `R-45`/`Q-10`.
- **Contredit** : plus aucune décision validée sur le fond. Restent **deux amendements de lettre, validés par Louis le 2026-09-25**, à écrire dans `decisions.md` : « Livraison du client — un seul module empaqueté » (un second paquet pour le panneau) et « la feuille du module ne se charge que sous un listing » (une seconde feuille, `site-search.css`, chargée par le panneau).
- **La plateforme offre déjà** : `get_post_type_archive_link()` (qui rend la Boutique pour `product` et la page des articles pour `post` quand l'accueil est une page — mesuré : `/boutique`, `/journal`), le `/multi-search` de Meilisearch (une sous-requête par type, `hitsPerPage` et total par sous-requête), `_formatted`/`attributesToHighlight`, `attributesToSearchOn` (≥ 1.3, la prod est en 1.10.3), `rankingScoreThreshold` (≥ 1.9), `searchableAttributes` ordonnés, `typoTolerance.disableOnAttributes` ; le motif APG disclosure + combobox ; l'API Popover (écartée, voir « Principe »).

**Déjà décidé, et touché** (`decisions.md` « Validées ») :

| Décision | Ce qu'elle dit | Effet du chantier |
| --- | --- | --- |
| Transport | le navigateur interroge Meilisearch en direct — aucun proxy PHP, aucun repli | **tenu, sans exception** : en panne, le panneau n'affiche qu'un message (D-7) |
| Requête principale des archives | conservée ; `posts_pre_query` reste banni | tenu : aucune interception, aucune page de résultats |
| Déclaration d'un listing / paramètres réservés | le module lit le `s` routé sans l'écrire ; ce que le visiteur tape dans un listing voyage sous `q` | **tenu, rien ne change** (D-4) : le panneau n'écrit rien dans l'URL |
| Livraison du client ES | un seul module empaqueté, commité | **amendée** (Louis) : second paquet pour le panneau |
| La feuille du module ne se charge que sous un listing | la feuille est demandée par `<x-meilifacets::listing>` | **amendée** (Louis) : `site-search.css`, demandée par le panneau |
| Rôle de MeiliScout | indexation seule | tenu |
| Données de carte / Point d'extension de la carte | `card` projeté, `CardProjector` décoré | tenu : l'extrait s'ajoute à `card` par décoration (S-7) |
| Clé de recherche | clé fixe fournie par l'infrastructure | tenue ; son périmètre réel (index `posts` seul) documenté sous `R-29` |
| Dossiers et crochets | avec l'accord de Louis | **autorisés** le 2026-09-25 (§ 8) |

**Entrées du registre** : à ouvrir — **`R-180` · 🟠 · recherche du site (lot 5)**, parapluie du
chantier ; les deux mesures « le domaine rend tout l'index trouvable » et « le balisage des blocs est
cherchable » s'ajoutent à `R-27` (même cause, même correctif). Sous le parapluie : `R-27`, `R-159`,
`R-160` ; `R-29` complété.

**Angles morts** relevés pour ce chantier :
- **WooCommerce absent** : pas de section produits, la section articles reste (`R-09`).
- **Type sans archive** : `get_post_type_archive_link()` rend `false` (type sans `has_archive`, ou `post` sans page des articles) — la section n'a alors pas de lien « voir tous », elle ne casse pas.
- **Le lien « voir tous » mène à l'archive entière, pas aux résultats** : le compte de la section (« 12 produits correspondent ») ne se retrouve pas sur la page d'arrivée. Choix de Louis, noté en risque.
- **Index vieilli** : nouveaux réglages et nouveaux champs — réindexation au moment voulu, avec l'accord de Louis.
- **Crochets du panneau hors listing** : signalés comme orphelins par le client du listing (`Contract.orphans()`), à exclure. *Fait le 2026-09-28 (`R-189`) : chaque client ne nomme que ses crochets, et aucun crochet posé dans une racine `search` n'est orphelin.*
- **Terme sans mot** (`?(`, espace insécable) : le moteur sert tout l'index (`R-159`).
- **Jetons de mouvement** déclarés sur `[data-listing]` : le panneau n'y est pas, il doit les redéclarer sur sa racine.
- **Article par défaut** « Bonjour tout le monde ! » indexé et publié : contenu à retirer par Louis, pas un défaut de code.
- **Sans JavaScript** : la loupe ne fait rien, comme l'ouvreur des filtres (aucun repli, D-7).

---

## Décisions

### Cadrage (2026-09-25)

| # | Question | État |
| --- | --- | --- |
| D-1 | Où vit le code | **tranché** — dans **MeiliFacets** (client, clé de recherche seule, annulation, description publiée, `shared/entrance.ts`, `shared/css-timing.ts`, `shared/input-source.ts`, cartes `card`, jetons de mouvement, `FocusLanding`) ; MeiliScout reste « indexation seule » |
| D-2 | Seuil de saisie | **proposé** — 2 caractères, comptés après `trim()` en points de code, **et** au moins une lettre ou un chiffre (`\p{L}`/`\p{N}`, `R-159`) |
| D-3 | Types | **tranché** (reformulé par Louis) — recherche **multi-types** : chaque type (produits, articles…) est **déclaré** par le projet et rendu dans **sa propre section**, avec en-tête et compte (« Produits · N produits correspondent ») et son lien « Voir tous les produits » / « Voir tous les articles ». Contrat + défaut `scopedIf`, sur le modèle de `ProductFacets` |
| D-4 | Paramètre d'URL du terme | **tranché** (Louis) — **rien ne change** : un paramètre du module ne peut pas porter le nom d'une query var publique (`s` ferait router WordPress vers la recherche et détournerait la page) ; le module lit le `s` routé sans l'écrire, ce que le visiteur tape dans un listing voyage sous `q`. Ni `q` ni la liste des paramètres réservés ne sont touchés ; `Q-09` reste hors chantier |
| D-5 | Catégories et marques dans le panneau | **proposé** — non au premier livrable, bien que l'index `taxonomies` soit rempli (127 termes) ; il faudrait aussi ouvrir la clé du navigateur à cet index |
| D-6 | Pages WordPress cherchables | **proposé** — non |
| D-7 | Moteur en panne | **tranché** (Louis) — **aucune exception** à « aucun repli » : le panneau affiche seulement un message « Recherche indisponible » (crochet `search-unavailable`), annoncé par la région d'état. Aucune bascule vers la recherche native, ni en panne ni sans connexion configurée |
| D-8 | Synonymes | **tranché** — pas de liste de synonymes ; tolérance aux fautes, accents et préfixes natifs de Meilisearch ; seul réglage envisagé : `typoTolerance.disableOnAttributes` sur le SKU |
| D-9 | Maquettes | **proposé** — à fournir par Louis ; en attendant, on avance sans (forme S-1). L'étape 8 attend les maquettes |

### Ouvertes par l'architecture

| # | Question | État |
| --- | --- | --- |
| S-1 | Forme | **tranché** (Louis) — panneau déroulant pleine largeur sous l'en-tête, non modal, champ en haut, sections dessous |
| S-2 | « Voir tous » | **tranché** (Louis) — **simple lien vers la racine de l'archive du type**, sans aucune query var. Chaque type déclare son lien ; défaut `get_post_type_archive_link()` (Boutique pour `product`, page des articles `page_for_posts` pour `post`). Le panneau n'écrit rien dans l'URL. Aucune page de résultats, aucun second listing, `posts_pre_query` n'est plus envisagé |
| S-3 | Nom du concept | **proposé** — **`SiteSearch`** pour le code interne (PHP `SiteSearch\`, TS `site-search/`, paquets et feuille `site-search.*`) : `Search\` et `search-client.ts` désignent déjà l'envoi au moteur. Ce qu'un thème écrit — composants et crochets — dit `search` (S-18) |
| S-4 | Livraison du client | **tranché** (Louis) — un chargeur minimal inscrit sur toutes les pages (`@meilifacets/site-search`, priorité basse, exclu du Delay JS) qui ouvre et ferme le panneau, et importe le client (`dist/site-search-client.js`) à la **première intention** (survol ou focus de la loupe, sinon ouverture) |
| S-5 | Feuille de style | **tranché** (Louis) — `site-search.css` à part, demandée par le composant du panneau |
| S-6 | Champs projetés pour la pertinence | **tranché** (Louis) — `labels.<taxonomie>` (noms des termes, ancêtres compris, sur le modèle de `facets`) et `content` (contenu en texte brut ; d'abord nommé `text`, renommé le 2026-09-25) ; `excerpt` (extrait nettoyé, ajouté le 2026-09-25) ; `post_content` et `post_excerpt` bruts restent dans le document mais ne sont plus cherchables. Réindexation au moment voulu, avec l'accord de Louis |
| S-7 | Extrait de la carte d'article | **tranché** (Louis, `card.summary`, renommé depuis `card.excerpt` le 2026-09-25) — projeté par le module (texte brut ; extrait de l'auteur entier, sinon début du contenu borné) ; date, rubrique et temps de lecture = décoration Pluralia (`extend` de `CardProjector`), selon la maquette |
| S-8 | Compte d'une section | **proposé** — `page: 1, hitsPerPage: 4` : le moteur rend `totalHits` exact (jusqu'à `maxTotalHits`) au lieu d'une estimation |
| S-9 | Entrée sans option active | **tranché** (Louis) — **ne fait rien** tant qu'aucune option n'est désignée (motif APG « liste sans sélection automatique ») ; les comptes ont déjà été annoncés, les flèches mènent aux résultats |
| S-10 | Mobile | **tranché** (Louis) — sous 48em, le panneau occupe la largeur et la hauteur restantes sous l'en-tête, défile en interne (`overscroll-behavior: contain`), et **le défilement de la page est verrouillé** tant qu'il est ouvert (`:root:has([data-meili="search-toggle"][aria-expanded="true"])`, comme le tiroir) ; au-delà de 48em, pas de verrou |
| S-11 | Sans JavaScript | **conséquence de D-7** — rien : la loupe est inerte, comme l'ouvreur des filtres |
| S-12 | Fermeture partagée | **proposé** — extraire de `DisclosureGroup` la fermeture « Échap / clic hors du chemin / focus sorti » dans `shared/light-dismiss.ts`, utilisée par les deux ; comportement des filtres inchangé, prouvé par leurs tests existants |
| S-13 | `R-19` | **proposé** — hors chantier : le panneau ne passe pas par PHP (lot 6) |
| S-14 | `rankingScoreThreshold` | **proposé** — non au premier livrable ; à mesurer à l'étape 2 si des résultats hors sujet remontent |
| S-15 | `matchingStrategy` | **proposé** — défaut (`last`), revu à l'étape 2 sur les termes de référence |
| S-16 | Code mort du thème | **proposé** — retirer à l'étape 8 le composant Alpine `productSearch`, la route `/api/products/search` et son service SQL |
| S-17 | Composition | **tranché** (Louis) — la modularité du chantier filtres reste la règle : racine `<x-meilifacets::search>` (contexte, description, contrat, connexion ; aucun paramètre `types`, S-21) et briques indépendantes composées par le thème (`search-toggle`, `search-panel`, `search-input`, `search-section type limit`, `search-empty`, `search-unavailable`) |
| S-18 | Nom des crochets | **tranché** (Louis) — crochets en **`search`** / **`search-*`**, alignés sur les composants ; l'autorisation donnée pour les noms `site-search-*` vaut pour ces noms. Aucun crochet `search*` n'existait, aucun ne voyage dans une URL |
| S-19 | Section d'un type absent | **tranché** (Louis, avec S-21) — une section dont le type n'est pas déclaré ou pas indexé lève une erreur de développement claire, `product` compris sans WooCommerce : le thème garde sa section produits derrière `WooCommerce::isActive()` ; écarté : ignorer en silence, qui masquerait une faute de frappe |
| S-21 | Déclaration des types | **tranché** (Louis, 2026-09-25) — **ce qu'est un type cherchable** = contrat de code `SearchableTypes` (défaut du module en `scopedIf` : produits si WooCommerce est actif, puis articles ; le projet lie le sien si besoin) ; **ce que cherche une recherche donnée** = la composition du gabarit : les `search-section type limit` posées décident des types interrogés, de leur ordre et de leur limite — **aucun paramètre `types` sur la racine** (doublon) ; **réglages scalaires** (seuil 2, temporisation ~120 ms, limite 4) = défauts portés par le module dans un objet de valeur `SearchSettings` lié dans le provider (surchargeable par le conteneur), surcharge ponctuelle par attribut (`min-chars` sur la racine, `limit` sur la section), sur le précédent de `scroll` ; **aucune clé de config** |
| S-20 | Page de résultats unifiée du lot 5 | **tranché** (Louis, par S-2) — **remplacée** par les liens « voir tous » vers la racine de l'archive de chaque type ; `lots.md` (lot 5, « page de résultats unifiée, filtrable par type ») est à corriger, et la décision à reporter dans `decisions.md` |

---

## Architecture cible (2026-09-25)

### 1. Principe directeur

- **La modularité du chantier filtres reste la règle** (Louis, 2026-09-25). Comme `<x-meilifacets::listing>`,
  un **composant racine `<x-meilifacets::search>`** porte le contexte de la recherche : la description publiée
  au client (connexion, types, libellés), la racine du contrat `data-meili` (`data-meili-contract`), la demande
  de la feuille et du script. À l'intérieur, des **briques indépendantes que le thème compose** : la loupe, le
  panneau, le champ, une section par type, le message vide, le message de panne. Chaque brique fait une chose et
  ignore où elle est posée.
- **Le contrat dit ce qu'est un type, le gabarit dit ce qu'on cherche** (S-21). Ce qui décrit un type
  (libellé, carte, filtre de base, champs cherchés, archive) est déclaré une fois dans le contrat
  `SearchableTypes` ; les `search-section` posées décident des types interrogés, de leur ordre et de leur
  limite. Aucun paramètre `types` sur la racine. Le client ne bâtit de sous-requête que pour les sections
  posées ; une section dont le type n'est pas déclaré **et** indexé (`indexed_post_types`) lève.
- **Réglages scalaires sans configuration** : seuil, temporisation et limite ont leurs défauts dans le module
  (`SearchSettings`, lié dans le provider), et se surchargent ponctuellement par attribut de composant.
- **Disclosure + combobox, sans `<dialog>`.** La loupe est le bouton d'un motif disclosure APG
  (`aria-expanded`, `aria-controls` vers le panneau) ; le panneau **n'est pas modal** : ni `inert`, ni
  piège du focus. Il contient un combobox (champ + résultats groupés par section). À l'ouverture le focus va
  dans le champ ; Échap ferme et rend le focus à la loupe ; un clic hors du panneau et de la loupe ferme ; Tab
  qui sort du panneau ferme (S-12, `InputSource` pour ne pas fermer sur un appui).
- **Positionné par le thème, empilé par un jeton.** Le thème place la racine (donc la loupe et le panneau)
  dans son en-tête ; le panneau, en `position: absolute` sous l'en-tête (ancre et conteneur `max-w-site`
  posés par le thème), est superposé au contenu par `z-index: var(--meili-layer-search)`, borné en hauteur
  par la place restante (mesurée à l'ouverture, écrite en `--meili-search-room`), défilement interne.
- **Le moteur en direct, sans repli** : clé de recherche seule, `SearchClient` réutilisé, une recherche en
  vol au plus, aucune route, aucun proxy ; en panne, un message et rien d'autre (D-7).
- **Le panneau n'écrit rien dans l'URL** : ni terme ni état ; ses seuls liens sont les cartes et les
  « voir tous » vers l'archive nue de chaque type.
- **Le serveur rend tout ce que le client révèle** (règle du module) : sections, compte, lien « voir
  tous », message vide et message de panne sont rendus `hidden` ; une carte par section vient d'un
  `<template>`.
- **L'API Popover est écartée** : son panneau vit dans la couche supérieure, en position fixe, détaché de
  la géométrie de l'en-tête (il faudrait l'ancrage CSS), et la fermeture sur Tab resterait du code.

### 2. Catalogue des briques

Noms : un concept = un nom — `search` pour la racine, préfixe `search-` pour les briques, composants et
crochets confondus (S-18).

| Brique | Composant, attributs | Crochets | TS | Rôle |
| --- | --- | --- | --- | --- |
| Racine | `<x-meilifacets::search>` + `min-chars`, `delay`, `name` (aucun `types`) | `search` (+ `data-meili-contract`) | `SiteSearch` | Publie la description (connexion, types des sections posées et leurs libellés, seuil, délai), demande `site-search.css` et le chargeur. Rend un simple conteneur ; tout le reste est composé dans son slot. Plusieurs racines sur une page : `name` distinct (même règle que `listing`). |
| Loupe | `<x-meilifacets::search-toggle>` + slot `icon` | `search-toggle` | `SearchPanel` (chargeur) | `<button aria-expanded="false" aria-controls="…">` vers le panneau de sa racine, nommé « Search » / « Rechercher » (`aria-label`). Icône par défaut `images/search.svg` en `<img alt="">` dans un `<span aria-hidden="true">`, remplacée par le slot `icon`, retirée par un slot vide — même mécanique que l'ouvreur « Filtres ». |
| Panneau | `<x-meilifacets::search-panel>` + slot | `search-panel`, `search-status` | `SearchPanel`, `StatusView`, `PanelRoom` | `hidden` au rendu ; conteneur `role="search"` (pas de `<form action>` : rien ne part vers WordPress) ; porte l'unique région `aria-live="polite"`. Ce qu'il contient, c'est le thème qui le pose. |
| Champ | `<x-meilifacets::search-input>` | `search-input` | `Typing`, `ComboboxKeys` | `<input type="search" role="combobox" aria-expanded aria-controls aria-autocomplete="list">`, libellé visuellement masqué, traduisible. |
| Section | `<x-meilifacets::search-section>` + `type` (requis), `limit` (défaut de `SearchSettings` : 4), `heading` | `search-section` (`data-type`), `search-count`, `search-results`, `search-card-template`, `search-see-all` | `SectionView` | En-tête (titre du type, `labels->name` de WordPress, + compte « 3 résultats » : clé existante `:count result|:count results`, CLDR par `CountLabel`), liste `role="group"` dans la listbox, template de la carte que le type déclare, lien « voir tous » (`labels->all_items`, « Tous les produits ») : `<a href>` **rendu par le serveur** vers l'archive du type, jamais réécrit ; absent si le type n'a pas d'archive. Masquée quand son type n'a rien. Un `type` non déclaré ou non indexé lève (`SearchTypeRefused`). Une section par type et par racine. |
| Vide | `<x-meilifacets::search-empty>` | `search-empty` | `StatusView` | « Aucun élément ne correspond à votre recherche », révélé quand toutes les sections posées sont vides. Libellé traduisible, slot pour le remplacer. |
| Indisponible | `<x-meilifacets::search-unavailable>` | `search-unavailable` | `StatusView` | « Recherche indisponible » (D-7), révélé en panne ; sections masquées. |
| Carte de recherche | `<x-meilifacets::search-card>` (template d'une section, **tous types**, distincte de la carte du listing `<x-meilifacets::card>`) | `card`, `url`, `image`, `title`, `summary`, `price` | `CardView` + `summary` | Titre, image, `summary` s'il est présent, prix s'il est présent. Nommée par `SearchableType::card` depuis l'étape 3a (`R-187`), vue écrite à l'étape 5. |
| Surlignage | — | — | `Highlight` | Balises en caractères privés (U+E000/U+E001), découpe en nœuds texte + `<mark>` : jamais d'`innerHTML` sur une chaîne du moteur. Ne lit que `_formatted.card.title` et `_formatted.card.summary`. |

**Réutilisé plutôt que réécrit** (Louis, 2026-09-28) : le panneau s'appuie sur les briques du listing, dont
certaines sont **à extraire** avant d'être partagées — voir § 4 et § 7. Côté PHP : `ListingScript` (à extraire en
une classe partagée, paramétrée par module et source de données, à la place d'un `SiteSearchScript`),
`Stylesheet` (à paramétrer par poignée et chemin, à la place d'un `SiteSearchStylesheet`),
`BrowserConnection::origin()` pour la préconnexion.

**Répartition module / thème.** Le module rend chaque brique et son comportement, habillés neutres
(`site-search.css` : ni couleur, ni police de marque). Le thème **compose** : il pose la racine dans son
en-tête, la loupe à la place de `.pluralia-header__search-toggle`, le panneau sous l'en-tête dans son
conteneur `max-w-site`, et choisit **quelles sections, dans quel ordre, avec quelle limite**. Il fournit
l'icône (slot), pose l'ancre, habille par les crochets `data-meili` avec `theme-vars.css`, et peut surcharger
chaque vue.

```blade
{{-- themes/pluralia/resources/views/parts/header.blade.php, proposé --}}
<header class="relative">
    <x-meilifacets::search>
        …
        <x-meilifacets::search-toggle>
            <x-slot:icon>@include('parts.header.icons.search')</x-slot:icon>
        </x-meilifacets::search-toggle>
        …
        <x-meilifacets::search-panel class="absolute inset-x-0 top-full mx-auto max-w-site px-3 2xl:px-0">
            <x-meilifacets::search-input />
            @if (WooCommerce::isActive())
                <x-meilifacets::search-section type="product" :limit="4" />
            @endif
            <x-meilifacets::search-section type="post" :limit="4" />
            <x-meilifacets::search-empty />
            <x-meilifacets::search-unavailable />
        </x-meilifacets::search-panel>
    </x-meilifacets::search>
</header>
```

**Règles de composition** (`RULES` et gardes serveur) : la racine exige `search-input`, `search-panel`, et les
messages vide et indisponible, rendus `hidden` ; une section exige résultats, template et compte ; la loupe
est optionnelle (un thème peut ouvrir le panneau autrement, ou le laisser toujours ouvert). Toute brique posée
hors d'une racine `search` est signalée au démarrage, comme hors d'un `listing`.

### 3. Déclaration des types cherchables

```php
interface SearchableTypes
{
    /** @return array<string, SearchableType> keyed by post type */
    public function all(): array;
}
```

`SearchableType` (objet de valeur, `final readonly`) porte ce qui ne change pas d'un gabarit à l'autre :
`postType`, `heading`, `seeAllLabel`, `baseFilter` (clauses), `searchOn` (`attributesToSearchOn`), `card`
(nom du composant de carte) et `archive` (`?string`, l'adresse du lien « voir tous »). La **limite** et
l'**ordre** ne sont pas dans la déclaration : ils appartiennent au gabarit (`limit`, ordre des sections).
`heading` se surcharge ponctuellement par l'attribut de la section. Aucun motif de compte par type : la section
réutilise `:count result|:count results` (Louis, 2026-09-28).

**Tout est dérivé de WordPress** (Louis, 2026-09-28, livré par `R-187`) : `SearchableTypeFactory::forPostType()`
lit `heading` = `labels->name`, `seeAllLabel` = `labels->all_items`, `archive` =
`get_post_type_archive_link()` (`null` sans archive), `baseFilter` = publié + type (`PublishedPosts`), `searchOn`
= titre, `labels.*` des taxonomies **du type**, `excerpt`, filtrés par l'ordre de recherche
(`AttributesToSearchOn::among()`) ; `card` = `meilifacets::search-card`. Un type à qui l'ordre ne laisse aucun
champ lève `NoFieldToSearch` ; un `searchOn` qui sort de l'ordre (posé par un projet) lève
`FieldsOutsideSearchOrder` à la validation. Aucune chaîne propre au module.

Défaut du module : `WordPressSearchableTypes` — les types **indexés**, **publics** et **non
`exclude_from_search`** (`SearchablePostTypes`), dans l'ordre de `indexed_post_types`, `product` excepté, mémoïsés
par requête — décoré par
`WooCommerceSearchableTypes`, lié par nom de classe en `scopedIf`. Sous WooCommerce actif (lu à chaque appel,
`R-171`), les produits passent **en tête** et surchargent la fabrique (`SearchableTypeFactory::make()`) : filtre
`VisibleProducts::inSearch()`, champs titre + `WooCommerceProductFields` ; sans WooCommerce, aucun `product`. Le
filtre de base des produits **reprend celui du catalogue** avec, sur une recherche, **`exclude-from-search` à la
place de `exclude-from-catalog`** (`R-160` ; WooCommerce échange le drapeau, `class-wc-query.php:929`), une seule
source partagée avec `ProductListing` sur une recherche.

**Surcharge** : un projet décore le défaut. Il corrige un type par `withHeading()`, `withSeeAllLabel()`,
`withCard()`, `withSearchOn()` (immuables), en ajoute ou en retire un en modifiant `all()`. Exemple :
`configuration.md`, « Types cherchables ».

**Validation d'une section**, à sa construction : son `type` doit figurer dans
`SearchableTypes::all()` **et** dans `indexed_post_types` (lu par la même frontière MeiliScout que
`FacetedPostIndexable`). Sinon `SearchTypeRefused` : « Post type "page" is not searchable: declare it in
SearchableTypes and index it in MeiliScout. Searchable here: product, post. » Sans WooCommerce, `product` n'est
pas déclaré : un gabarit qui pose sa section lève — le thème la garde derrière `WooCommerce::isActive()` (S-19).

**Réglages scalaires** (S-21) : `SearchSettings` (objet de valeur `final readonly`, constantes nommées pour
ses défauts : seuil 2 caractères, temporisation 120 ms, limite 4), lié dans `SiteSearchServiceProvider` ;
un projet le relie par le conteneur s'il veut d'autres défauts partout. Un gabarit surcharge ponctuellement
par attribut : `min-chars` et `delay` sur la racine, `limit` sur la section.

### 4. Client TypeScript

**Réutilisé plutôt que réécrit** (Louis, 2026-09-28) — rien n'est écrit avant les étapes 4 et 5 :
- `SearchClient` (`ts/shared/search-client.ts`) : étendu de façon additive au surlignage (`attributesToHighlight`,
  lecture de `_formatted`), **une instance par racine** — son `AbortController` unique annule alors la recherche
  précédente de sa seule racine ;
- `Contract` (`ts/shared/contract.ts`) : réutilisé, avec des règles **propres à chaque racine** (`listing`,
  `search`) — **extrait** (`R-189`) : `RootComponent` (`ts/shared/root-component.ts`) porte attribut, règles et propriété des
  crochets ; `orphans(document, owner)` connaît les deux racines ;
- chargeur : le squelette de `listing-page.ts` (lecture des données publiées sous un module, orphelins, une liaison
  par racine) **extrait** dans `PageRoots` (`ts/shared/page-roots.ts`, `R-189`), partagé par `listing-page.ts` et
  `site-search-page.ts` ;
- `ListboxKeys` (`ts/sort/listbox-keys.ts`), **en partie** : les déplacements de l'état ouvert (flèches,
  `Home`/`End`) ; l'ouverture et la sélection du tri n'ont pas d'équivalent dans le combobox du panneau ;
- côté PHP, la préconnexion lit `BrowserConnection::origin()`.

- **Chargeur** (`site-search-page.ts`, paquet `dist/site-search.js`) : lie chaque racine `search` (disclosure,
  S-12), ouvre le panneau et met le focus dans le champ **avant** que le client n'arrive ; au premier survol
  ou focus de la loupe, ajoute le `preconnect` vers le moteur et importe le client.
- **Client** (`dist/site-search-client.js`) : lit la description de sa racine
  (`script_module_data_@meilifacets/site-search`, indexée par `name` : connexion, types autorisés, seuil,
  délai, libellés), vérifie le contrat, lit les sections **posées** (`data-type`, limite) et ne cherche que
  celles-là, puis :
  - `Typing` : seuil (D-2) et temporisation (~120 ms) lus dans la description (`SearchSettings` ou attributs), garde `R-159` ;
  - `SiteSearchQuery` : une sous-requête par section — `q`, `filter` du type, `page: 1`, `hitsPerPage: limit`,
    `attributesToSearchOn`, `attributesToRetrieve: ["ID","card"]`, `attributesToHighlight: ["card"]`,
    balises privées ; `SearchQuery` étendu de façon additive ;
  - annulation : celle de `SearchClient` ; une réponse dépassée n'est jamais peinte ;
  - `SectionView` peint compte et cartes ; `StatusView` annonce **une fois la réponse posée**
    (« 4 produits et 2 articles », message vide, message de panne) ;
  - `ComboboxKeys` : flèches et `Home`/`End` repris de `ListboxKeys`, Entrée sur une option = suivre son lien, `aria-activedescendant`
    sur le champ ; Entrée sans option active ne fait rien (S-9) ;
  - occupé : `aria-busy` sur le panneau pendant une recherche, atténuation différée par la feuille (comme ANIM-10) ;
  - panne (refus, délai dépassé, connexion absente) : sections masquées, message indisponible révélé.
- Objectif de latence frappe → affichage (lot 5) : **proposé** 100 ms au 95ᵉ centile en local, mesuré par
  `performance.mark`/`measure`, hors temporisation.

### 5. Liens « voir tous »

- Un `<a href>` par section vers la racine de l'archive du type (`/boutique`, `/journal` sur Pluralia,
  mesuré), rendu par le serveur, jamais réécrit : aucune query var, aucun terme.
- Rien à changer dans `ProductListing`, `ListingPage`, les gabarits d'archive ou `search.blade.php` du thème.

### 6. Pertinence

- **`searchableAttributes` est un réglage d'index** (un seul pour `posts`) : il fixe l'**ensemble** des
  champs cherchables et leur **ordre d'importance**. **Livré (`R-181`, non commité)**, lu sur Pluralia :
  `post_title`, `labels.product_brand`, `labels.product_cat`, `metas._sku`, `labels.category`,
  `labels.post_tag`, `labels.post_format`, `labels.contenu`, `labels.pluralia_selection`, `labels.essentiel`,
  `labels.product_tag`, `excerpt`, `content`. `excerpt` et `content` sont projetés par le module et nettoyés de
  la même façon ; l'extrait se classe au-dessus du corps (choix de Louis, 2026-09-25). Hors de l'ensemble, donc **inciblables même par une requête forgée avec la clé publique** :
  `url`, `guid`, `card.*`, `post_excerpt` et `post_content` bruts,
  `post_name`, `terms.*`, toutes les autres `metas` — ferme `R-27`, les deux mesures `pluralia`/`spacer`, et
  la question « `card.title` » (proposé : `card` n'est jamais cherché).
- **Déclaré par un contrat surchargeable en entier** (Louis, 2026-09-25, `R-184`) : `SearchableAttributes::all()`
  rend la liste complète et ordonnée ; défaut `DefaultSearchableAttributes` lié en `scopedIf` : titre, sous
  WooCommerce marque, catégorie et SKU, `labels.*` des autres taxonomies **visibles** (`is_taxonomy_viewable()`),
  `excerpt`, `content`. La tolérance aux fautes reste à `IndexAttributes::exactlyMatched()`. Écrit par
  `FacetedPostIndexable::getIndexSettings()` (`IndexSetting::SearchableAttributes`, `IndexSetting::TypoTolerance`).
  Procédure et exemple : `configuration.md`, « Ordre de recherche ».
- **Chaque requête restreint, jamais ne réordonne** : `attributesToSearchOn` (≥ 1.3). Proposé :
  - panneau, produits : `post_title`, `labels.product_brand`, `labels.product_cat`, `metas._sku` ;
  - panneau, autres types : `post_title`, `labels.*` des taxonomies du type, `excerpt` — sur Pluralia, articles :
    `post_title`, `labels.category`, `labels.post_tag`, `labels.post_format`, `labels.contenu`, `excerpt`
    (lu le 2026-09-28 ; toujours un sous-ensemble de `searchableAttributes`, par construction) ;
  - listing produit (recherche routée) : tout l'ensemble (paramètre omis).
  Déclarés dans `SearchableType::searchOn`. Deux ordres différents exigeraient deux index : hors cahier des charges.
- **Projections** (S-6) posées par `meiliscout/post/document`, comme `facets` : `labels.<taxonomie>`, `excerpt`, `content`,
  `card.summary`. Nouveaux cas `DocumentField::Labels`, `DocumentField::Content`, `DocumentField::Title`,
  `DocumentField::Excerpt`, `CardField::Summary` (d'abord `card.excerpt`, renommé par `R-185`). `card.summary` n'est posé que sur les cartes qui ne sont pas
  des produits ; ni `content` ni
  extrait pour un article protégé par mot de passe.
- **Tolérance** : défauts de Meilisearch (D-8) ; `typoTolerance.disableOnAttributes: ["metas._sku"]`. Pas de
  `stopWords` (proposé) ; `rankingRules` non écrits (la liste diffère entre 1.10 et 1.53).
- **Exclusions** : `exclude-from-search` sur toute recherche produit (`R-160`) ; publié seulement.

### 7. Arborescence

**PHP**
- `app/Contracts/SearchableTypes.php` ; `app/Contracts/SearchableAttributes.php` (livré, `R-184`) ; `IndexAttributes` gagne `exactlyMatched()`.
- `app/SiteSearch/` (autorisé) : `SearchableType`, `SearchableTypeFactory`, `WordPressSearchableTypes`,
  `WooCommerceSearchableTypes`, `SearchablePostTypes`, `AttributesToSearchOn`, `AcceptedSearchTypes`, `NoFieldToSearch`,
  `FieldsOutsideSearchOrder` (livrés, `R-187`),
  `SearchSettings` (seuil, temporisation, limite par défaut), `SearchTypeRefused` (section d'un type non déclaré
  ou non indexé), `SearchRegistry` (les racines d'une page
  par `name`, et la garde « une section par type et par racine »), `SearchRoot` (une racine rendue : nom, réglages effectifs, types acceptés) ; `app/Support/NamedRegistry` (base de `ListingRegistry` et `SearchRegistry`, `R-188`).
- `app/Indexing/` : `LabelProjection`, `TermGrouping`, `PostText`, `PostDocument` ; `MeiliScoutBridge` n'a plus qu'un filtre de document, `addModuleFields()` (`R-182`) ;
  `SummaryCardProjector` (décorateur) ; `IndexedPostTypes` (seule lecture de `indexed_post_types`, `R-187`).
- `app/Search/` : `PublishedPosts`, `VisibleProducts` (filtres de base partagés par le listing et la recherche, `R-187`).
- `app/View/` : `SiteSearchDescription` ; `ClientScript` (extrait de `ListingScript` à l'étape 3b, paquet `Enums\ScriptModule`, description paresseuse ; remplace `SiteSearchScript`) ; `ClientStylesheet` + `Enums\Stylesheet` (`Stylesheet` paramétrée, extraite à l'étape 4, `R-189` ; remplace
  `SiteSearchStylesheet`) ; `Components/` : `Search`, `SearchToggle`, `SearchPanel`, `SearchInput`,
  `SearchSection`, `SearchEmpty`, `SearchUnavailable`, `SearchCard` (les briques héritent d'un `SearchComponent`
  qui résout leur racine, comme `ListingComponent`).
- `app/Enums/` : `Hook` (+ crochets du § 8), `DocumentField` (+ `Labels`, `Content`, `PostType`, `Status`), `CardField` (+ `Summary`),
  `IndexSetting` (+ `SearchableAttributes`, `TypoTolerance`) ; `ElementId` : `searchPanel`, `searchInput`,
  `searchListbox`, `searchSection`, `searchOption`.
- `app/Providers/SiteSearchServiceProvider.php` (liaison `scopedIf` du contrat, lecture des défauts).

**Blade** — à plat dans `components/` : `search`, `search-toggle`, `search-panel`, `search-input`,
`search-section`, `search-empty`, `search-unavailable`, `search-card`.

**TypeScript** — `resources/assets/ts/site-search/` (autorisé) : `search-panel.ts`, `site-search.ts`,
`typing.ts`, `site-search-query.ts`, `section-view.ts`, `status-view.ts`, `highlight.ts`,
`combobox-keys.ts`, `panel-room.ts` ; entrées `site-search-page.ts` et `site-search-client.ts` à la racine ;
**à extraire** dans `shared/` : `light-dismiss.ts` (de `DisclosureGroup`, S-12) ; **extraits** (`R-189`) :
`page-roots.ts` (squelette du chargeur), `root-component.ts` (règles de `Contract` par racine) ; **extrait** (`R-190`) : `searches-under-way.ts` (recherches en vol, partagé avec `Listing`) ; **réutilisés** : `SearchClient` (étendu au surlignage,
une instance par racine), `ListboxKeys` en partie, `CardView` ; `bundle.ts` construit et vérifie trois paquets.

**CSS** — `resources/assets/css/site-search.css`, mobile first, seuil `48em`, jetons redéclarés sur
`[data-meili="search"]` : `--meili-layer-search`, `--meili-search-room`, durées et courbes du module.

### 8. Autorisé (Louis, 2026-09-25)

**Crochets `Hook`**, tous additifs (`Contract::VERSION` reste à 1), autorisés sous le nom `site-search-*` puis
renommés `search-*` par Louis (S-18) : `search` (racine),
`search-toggle`, `search-panel`, `search-input`, `search-status`, `search-empty`,
`search-unavailable`, `search-section`, `search-count`, `search-results`,
`search-card-template`, `search-see-all`, `summary` (d'abord `excerpt`, suit `card.summary`). `search-panel` s'ajoute à la liste
autorisée avec la racine composable (le panneau n'est plus la racine). Règles proposées dans `RULES` : `search` → `search-panel`, `search-input`, `search-status`, `search-empty`,
`search-unavailable` ; `search-section` → `search-results`, `search-card-template`,
`search-count` ; `search-card-template` → `card`, `url`, `title` (ni `image` ni `price` exigés ;
`search-see-all` et `search-toggle` optionnels). `Contract.orphans()` doit connaître les deux
racines (`data-listing` et `search`).

**Clés de configuration** : **aucune** (S-21). Seuil, temporisation et limite : défauts dans
`SearchSettings`, surcharge par attribut ; types interrogés et ordre : sections posées ; libellés,
sous-ensembles et archive : déclaration des types.

**Dossiers** : `app/SiteSearch/`, `resources/assets/ts/site-search/`. **Fichiers publiés** :
`dist/site-search.js`, `dist/site-search-client.js`, `css/site-search.css`, `images/search.svg`.

**Composants** : `search-card` (carte de recherche, tous types, remplace `excerpt-card` — Louis, 2026-09-28).
**Réutilisation** : `ListingScript`, `Stylesheet`, le squelette de `listing-page.ts` et les règles de `Contract`
sont extraits en place plutôt que doublés (§ 4, § 7) ; aucun fichier ni dossier de plus que ceux listés ici.

**Champs de document** : `labels`, `content` (d'abord nommé `text`, renommé par Louis le 2026-09-25), `card.summary` (d'abord `card.excerpt`) ; **réindexation** au moment voulu, avec l'accord de Louis.

### 9. Risques

- **Compte et page d'arrivée divergent** : « 12 produits correspondent » puis « Voir tous les produits »
  mène à toute la Boutique ; le libellé ne doit pas répéter le compte (« Voir tous les produits (12) » serait faux).
- **Empilement** : un ancêtre du panneau avec `transform`, `filter` ou `contain` l'enferme ; un en-tête
  collant du thème doit rester le contexte d'empilement le plus haut.
- **Options = liens** dans une listbox : lu diversement selon les lecteurs d'écran ; à écouter à l'étape 6
  (repli : liste de liens sans combobox, flèches gardées).
- **Poids du document** : `content` double le contenu (67 documents, 311 Ko bruts aujourd'hui) ; à mesurer.
- **Données de script d'un module chargé dynamiquement** : la description est attachée au chargeur (inscrit),
  pas au client ; à vérifier avec `wp_register_script_module` à l'étape 4.
- **Écart de version** : `attributesToSearchOn` (1.3) et `rankingScoreThreshold` (1.9) existent en 1.10.3 ;
  le reste du chantier n'emploie rien de plus récent.

---

## Méthode

Celle du chantier filtres : **un point à la fois** (`D-03`) ; sous-agents pour la lecture, la conformité,
la revue en cinq passes et les comparaisons au navigateur ; une étape se ferme selon `CLAUDE.md` § 5
(cinq passes rapportées, `composer check` vert, `ddev exec vendor/bin/phpunit --testsuite Modules` vert,
page ouverte dans un navigateur à 393 et 1440 px, registre et décisions à jour). **Jamais sans demander** :
clé de config, dossier ou crochet non listé au § 8, décision validée contredite, réindexation, commit.

---

## Étapes

### 0 · Préparation — ✅ fermée le 2026-09-25

- [x] cahier des charges et existant consignés (`741a334`)
- [x] branche `feat/site-search` créée depuis `main` (`f2303a8`)
- [x] passe de conformité, index lu en lecture seule (réglages, un article, un produit, clés)

### 1 · Architecture cible — ⏳ rendue le 2026-09-25, décisions prises, reports à faire

- [x] catalogue des briques, déclaration des types, client, liens « voir tous », pertinence, arborescence
- [x] tranchés par Louis : D-1, D-3, D-4, D-7, D-8, S-1, S-2, S-4 → S-7, S-9, S-10, S-17 → S-21 ; crochets, dossiers, fichiers et champs du § 8 autorisés
- [ ] propositions restantes (D-2, D-5, D-6, D-9, S-3, S-8, S-12 → S-16) confirmées
- [ ] registre : `R-180` ouvert (parapluie), mesures ajoutées à `R-27`, `R-29` complété (clé limitée à `posts`)
- [ ] `decisions.md` : décisions reportées (dont S-17 et S-20), amendements écrits à leur place (un seul paquet, une seule feuille) ; `lots.md` corrigé (page unifiée remplacée par les liens vers l'archive)

**Recette —** un schéma validé par Louis, chaque fichier avec sa responsabilité en une phrase.

### 2 · Pertinence et index

Registre : `R-27`, `R-159`, `R-160`, décision en attente « `card.title` ». Décisions : D-8, S-6, S-14, S-15.

**État au 2026-09-25 — code livré dans l'arbre, non commité, non réindexé (`R-181`).**

- [x] `SearchableAttributes::all()` (`R-184`) / `IndexAttributes::exactlyMatched()` et leur écriture par `FacetedPostIndexable` (`IndexSetting::SearchableAttributes`, `IndexSetting::TypoTolerance`)
- [x] projections `labels.<taxonomie>`, `excerpt` et `content` ; `typoTolerance.disableOnAttributes` sur le SKU
- [x] `exclude-from-search` sur la recherche produit (`R-160`), à la place de `exclude-from-catalog` comme WooCommerce — **à valider par Louis**
- [x] avancé de l'étape 3 : `card.summary` (`SummaryCardProjector`, d'abord `card.excerpt`/`ExcerptCardProjector`)
- [ ] terme sans lettre ni chiffre = zéro résultat, serveur et client, mêmes cas partagés (`R-159`) — **hors de cette livraison**, reste ouvert
- [x] réindexation locale par Louis et mesures (ci-dessous), sur la version où l'extrait était `post_excerpt` brut
- [x] seconde réindexation par Louis pour le champ `excerpt` nettoyé, mesures conformes (ci-dessous)
- [ ] commits, après relecture de Louis

**Procédure de réindexation (à lancer par Louis).** Hors de toute suite de tests en cours.

1. `ddev exec php artisan discovery:clear` — les filtres de document du pont sont remplacés par `addModuleFields()` (`R-182`) ;
2. `ddev wp meiliscout index` (sans `--clear` : l'index garde sa clé primaire, les réglages sont repoussés
   avant les documents, 67 documents réécrits ; `--clear` n'est utile que si un document orphelin est soupçonné) ;
3. contrôles en lecture, clé d'administration du `.env` hôte :
   - `GET /indexes/posts/settings` : `searchableAttributes` = la liste du § 6, `typoTolerance.disableOnAttributes`
     = `["metas._sku"]`, `displayedAttributes` = `["ID","card"]` ;
   - un document article : `labels.category`, `excerpt` et `content` sans `wp:` ni `spacer`, `card.excerpt` ; un produit :
     `labels.product_brand`, pas de `card.excerpt` ;
   - multi-search produits / articles (`limit 0`, filtres `post_type` et `post_status`) — attendu : `pluralia`
     ne rend plus tout l'index (seuls les documents dont le titre, les libellés ou le texte le citent) ; `spacer` et `paragraph` → 0 ;
     `srum` → 0 (4 lettres, aucune faute tolérée par défaut, D-8) ; `serum`, `sérum` et `serom` → les sérums ;
     `lumen` → les 5 produits Lumen ; un SKU exact → son produit, le même SKU à un caractère près → rien ;
   - `attributesToSearchOn: ["url"]` puis `["metas._edit_lock"]` avec la clé publique : **refus** du moteur
     (`invalid_search_attributes_to_search_on`) ;
   - `attributesToHighlight: ["card"]` sur `serum` : `_formatted.card.title` porte encore les balises alors que
     `card` n'est plus cherchable — l'étape 4 en dépend ;
4. `/boutique`, `/?s=creme&post_type=product` et une archive de marque dans le navigateur : cartes, compteurs et
   filtres inchangés ; suite `Modules` verte.

**Mesuré après la réindexation de Louis (2026-09-25, lecture seule)** — version où l'extrait était encore
`post_excerpt` brut :
- réglages poussés conformes : ordre, `displayedAttributes` = `ID`, `card`, SKU sans tolérance ;
- `pluralia` → 0, `srum` → 0, `sérum` → 2, `serom` → 2, `lumen` → 5, `attributesToSearchOn: ["url"]` refusé ;
- `spacer` → 3 (#560, #555, #778), par tolérance aux fautes sur « space » / « spaces » du contenu : aucun
  balisage n'est plus indexé, comportement normal ;
- surlignage : `attributesToHighlight: ["card.title"]` ne rend plus de `_formatted`, `["card"]` et `["*"]`
  surlignent `card.title` — **l'étape 4 demandera `["card"]`** et ne lira que `_formatted.card.title`
  (et `_formatted.card.summary`).

**Mesuré après la seconde réindexation de Louis** (2026-09-25, lecture seule, `excerpt` nettoyé présent) :
`excerpt` et `content` sans balise ; `pluralia` → 0 ; `"spacer"` et `"strong"` en recherche exacte → 0 ;
`spacer` (3) et `strong` (4) sans guillemets ne remontent que par la tolérance aux fautes, dans `content`
seulement — aucun balisage n'est plus indexé.

**Réindexation après `R-185`** (à lancer par Louis) : `ddev exec php artisan discovery:clear`, puis
`ddev wp meiliscout index`. Contrôles en lecture :
- `GET /indexes/posts/settings` : `labels.pa_contenance` présent, entre les autres libellés et `excerpt` ;
  aucun `labels.product_visibility`, `labels.product_type`, `labels.product_shipping_class`,
  `labels.pos_product_visibility` ;
- un produit avec contenance : `labels.pa_contenance` renseigné ; un article : `card.summary` (plus de
  `card.excerpt`), l'extrait de l'auteur entier s'il en a un ;
- multi-search produits : `100ml` et `50ml` trouvent les produits de cette contenance (autant que la facette
  `pa_contenance` en compte) ; `featured`, `simple`, `exclude-from-search` → 0 ;
- non-régression : `pluralia` 0, `sérum` 2, `lumen` 5, `srum` 0, `attributesToSearchOn: ["url"]` refusé.

⚠️ Dès que ce code tourne, **toute sauvegarde** d'un article ou d'un produit repousse les nouveaux réglages
(`ensureIndexExists()`) avant que les documents aient `labels`, `excerpt` et `content` : réindexer aussitôt après le
déploiement, en local comme en préprod.

**Recette —** `pluralia` et `spacer` ne rendent plus tout l'index ; une recherche forgée sur
`metas._edit_lock` ou `url` est refusée par le moteur ; tests Unit (réglages écrits, ordre) et Feature
(filtre de recherche produit).

### 3 · Déclaration des types — livrée le 2026-09-28 (`R-187`, `R-188`), en attente de commit

Décisions : D-3, D-5, D-6, S-2, S-3, S-7, S-8, S-17, S-19, S-21.

- [x] contrat `SearchableTypes`, `SearchableType` (+ `with…()`), fabrique `SearchableTypeFactory` (libellés, archive, filtre et champs dérivés de WordPress), défauts WordPress / WooCommerce en `scopedIf` (`R-187`, refondu le 2026-09-28)
- [x] types retenus = indexés, publics, non `exclude_from_search` (`SearchablePostTypes`) ; produits en tête sous WooCommerce, jamais dérivés par le défaut WordPress ; `searchOn` vide lève (`NoFieldToSearch`), hors de l'ordre lève (`FieldsOutsideSearchOrder`) ; surcharge documentée (`configuration.md`) et testée
- [x] racine `<x-meilifacets::search>` et registre des racines par `name` (`SearchRegistry`, base `NamedRegistry` partagée avec `ListingRegistry`, doublon refusé) ; `SearchSettings` et attributs `min-chars`, `delay` ; `limit` reste un attribut de section (étape 5), défaut dans `SearchSettings` (`R-188`)
- [x] une section d'un type non déclaré ou non indexé lève (`SearchTypeRefused`) — validation livrée (`R-187`), portée par `SearchRoot::type()` à l'étape 5 (`R-191`, `AcceptedSearchTypes::get()` retiré)
- [x] adresse d'archive par type (`get_post_type_archive_link()`, `null` sans archive), lue par la fabrique (`R-187`)
- [x] extrait projeté (`CardField::Summary`, `SummaryCardProjector`) — avancé à l'étape 2 (`R-181`, renommé par `R-185`)
- [x] description publiée par la racine (`SiteSearchDescription`) : types acceptés, seuil, délai, limite, motif de compte, locale, origine à préchauffer ; connexion publiée à côté par `ClientScript` (extrait de `ListingScript`), inerte tant que `dist/site-search.js` n'existe pas (`R-188`)

**Recette —** `<x-meilifacets::search-section type="page" />` lève en nommant `page` et les types
acceptables ; sans WooCommerce, les articles seuls fonctionnent ; archives `/boutique` et `/journal` résolues ; tests Unit et Feature.

### 4 · Client de recherche

Décisions : D-2, D-7, S-4, S-8.

- [x] extractions d'abord : squelette du chargeur (`shared/page-roots.ts`, `PageRoots`), règles de `Contract` par racine (`shared/root-component.ts`, `RootComponent`), `Stylesheet` paramétrée (`Enums\Stylesheet` + `View\ClientStylesheet`) ; listing inchangé, prouvé par ses tests et dans le navigateur (`R-189`) — `ListingScript` partagé **fait à l'étape 3b** (`ClientScript`, `R-188`) ; `ListboxKeys` laissé à la partie suivante (`R-189`)
- [x] `SearchQuery` étendu (additif, surlignage) ; `SearchClient` réutilisé, une instance par racine ; `SiteSearchQuery`, `Typing`, `Highlight`, `SectionView`, `StatusView` ; `ComboboxKeys` avancé de l'étape 5 (`R-190`)
- [x] chargeur + client en deux paquets, `bundle.ts` et `build:check` étendus, exclusion du Delay JS (chargeur seul inscrit ; client importé sous l'empreinte de son contenu) (`R-190`)
- [x] latence frappe → affichage mesurée : 28 recherches en local, médiane 9,3 ms, **p95 21,9 ms** (objectif proposé : 100 ms) (`R-190`)
- [ ] commits, après relecture de Louis

**Recette —** tests TS (seuil, temporisation, annulation d'une réponse dépassée, surlignage sans HTML,
compte, section vide masquée, panne → message seul) ; un terme tapé vite ne peint que la dernière réponse.

### 5 · Panneau et sections — livrée le 2026-09-28 (`R-191`), en attente de commit

Décisions : S-1, S-5, S-9, S-10, S-11, S-12, D-7.

- [x] briques `search-toggle`, `search-panel`, `search-input`, `search-section` (`type`, `limit`), `search-empty`, `search-unavailable`, `search-card` (titre, image, `summary` et prix s'ils sont présents) ; composition par défaut d'une racine sans contenu ; feuille `site-search.css`, neutre, mobile first (`R-191`)
- [x] une section de type non autorisé lève ; seules les sections posées sont cherchées ; deux sections du même type dans une racine lèvent (`R-191`)
- [x] `ComboboxKeys` (livré à l'étape 4, sans rien partager avec `ListboxKeys`) ; préconnexion à la première intention (`R-190`)
- [x] disclosure : focus dans le champ, Échap rend le focus à la loupe, clic extérieur, Tab qui sort (`shared/light-dismiss.ts`, filtres inchangés, `R-191`)
- [x] place mesurée sous l'en-tête, défilement interne, verrou du défilement de la page sous 48em (S-10) ; Entrée sans option active inerte (S-9)
- [x] liens « voir tous » vers l'archive ; message vide ; message de panne (D-7) ; `Contract.orphans()` connaît la racine `search` (`R-189`)
- [x] loupe du thème Pluralia remplacée par `<x-meilifacets::search>` (icône du thème en slot, aucun CSS de thème)
- [ ] une listbox par section ou une brique `search-sections` : question pour Louis (`decisions.md`)
- [ ] commits, après relecture de Louis

**Recette —** Playwright 393 et 1440 px : « ser » montre au plus 4 produits et 4 articles avec leurs
comptes et leurs liens vers `/boutique` et `/journal` ; « zzzz » affiche « Aucun élément ne correspond à
votre recherche » ; moteur arrêté : « Recherche indisponible », l'URL ne change pas ; aucune erreur
console ; suite des filtres verte.

### 6 · Accessibilité

- [ ] combobox APG : `aria-activedescendant`, groupes nommés par leur en-tête, options = liens (risque § 9)
- [ ] une seule région d'état, annonce après la réponse, jamais à chaque frappe
- [ ] `forced-colors`, cibles tactiles (`--meili-control-min`), nom de la loupe
- [ ] audit par sous-agent, lecteur d'écran (VoiceOver macOS et iOS)

### 7 · Animations — livrée le 2026-09-28 (`R-192`), reprise le 2026-09-29 (`R-193`), en attente de commit

- [x] panneau : entrée/sortie par `@starting-style` + `allow-discrete`, instantanée au clavier (comme ANIM-4) ; voile en fondu calé sur lui (`R-192`)
- [x] aucune animation par frappe ; atténuation différée pendant une recherche (comme ANIM-10) ; fondu court des premières sections seulement (`R-192`)
- [x] mouvement réduit : fondus seuls (`R-192`)
- [x] résultats pendant la frappe : réconciliation par `ID`, entrée 120 ms + 4 px, sortie 80 ms hors flux, FLIP additif
  150 ms, compte en fondu + 2 px, vide ↔ résultats 150 ms sans flou, barre lente après 150 ms, rien au clavier ni
  pendant l'entrée du panneau (`ResultsMotion`, `Departure`, `R-193`)
- [x] reprises de `R-192` : voile sur la courbe du panneau, ligne active sans arête, soulignement du texte seul,
  vignette à taille fixe ; premiers résultats à ×4 : layout 1,4–2 ms, rien à réduire (`R-193`)
- [ ] commits, après relecture de Louis

### 8 · Habillage Pluralia — attend les maquettes (D-9)

- [ ] loupe dans la rangée d'en-tête, conteneur `max-w-site`, jetons de `theme-vars.css`, par les crochets
- [ ] cartes d'article décorées (date, rubrique, temps de lecture) selon la maquette
- [ ] code mort retiré (S-16)

---

## Questions ouvertes pour Louis

Plus aucune question d'architecture. Reste un point de **contenu**, côté Louis : retirer l'article
« Bonjour tout le monde ! », publié et indexé, qui sortirait dans les résultats.

Les propositions encore marquées **proposé** (D-2, D-5, D-6, D-9, S-3, S-8, S-12 → S-16) valent accord
sauf avis contraire, et sont confirmées à la clôture de l'étape 1.

---

## Journal

| Date | Étape | Fait |
| --- | --- | --- |
| 2026-09-29 | 7 (frappe) | `R-193` : les résultats ne sont plus reconstruits à chaque lettre (réconciliation par `ID`, option atteinte gardée, identifiants par nœud) ; `ResultsMotion` (FLIP additif, entrées, sorties hors flux, fondu enchaîné vers le vide, compte) et `Departure` ; barre de recherche lente ; `CardView::stamp()` partagé avec le listing ; voile, ligne active, vignette ; vérifié image par image (`typing-x4.gif`) ; par réponse 1–2,3 ms sans bridage, ~4,5 ms à ×4, 0 image perdue ; client 655/655, Unit 358, suite `Modules` 666 ; ni commit ni réindexation |
| 2026-09-28 | 7 (animations) | `R-192` : panneau en fondu + `translateY(-8px)` 200 ms / sortie `-4px` 150 ms, voile en fondu (`ease`), rien au clavier (`data-instant` sur la racine), premières sections en fondu 120 ms, atténuation différée, loupe `scale(0.97)`, flèche +2 px ; corrections de style (ombre retirée, halo 8 %, 1,5/2/3rem, vignette alignée, titre 400, voile noir) ; `PanelRoom` mesure depuis l'ancre ; loupe alignée dans l'en-tête ; vérifié image par image dans Chrome ; client 634/634, Unit 358, suite `Modules` 666 ; ni commit ni réindexation |
| 2026-09-28 | 5 (panneau) | `R-191` : briques Blade et composition par défaut (une section par type accepté, icône du thème en slot), `SearchRoot::type()` et une section par type (`AcceptedSearchTypes::get()` retiré), `LightDismiss` extrait de `DisclosureGroup` (Échap consommée), `PanelRoom`, `aria-controls` du champ, `site-search.css`, loupe de Pluralia remplacée ; recette Playwright 393/1440 conforme (« ser », « zzzz », Échap, clic extérieur, ↓ + Entrée, panne, `/boutique`) ; client 622/622, Unit 358, suite `Modules` 664 ; ni commit ni réindexation |
| 2026-09-28 | 4 (client) | `R-190` : chargeur `site-search.js` (disclosure, focus avant l'import, `preconnect`, `import()` à la première intention) et client `site-search-client.js` (`Typing` + garde `R-159`, `SiteSearchQuery`, `Highlight`, `SectionView`, `StatusView`, `ComboboxKeys`, `aria-busy`, panne), onze crochets `search-*`/`summary` additifs, `sectionPattern` ; doublons levés (`SearchesUnderWay`, `FilterExpression.all`, attributs partagés, `SearchSeam`) ; multi-search réel accepté, vérifié dans Chromium ; p95 21,9 ms ; client 606/606, Unit 345, suite `Modules` 635 ; ni commit ni réindexation |
| 2026-09-28 | 4 (extractions) | `R-189` : `PageRoots` (squelette du chargeur), `RootComponent` (règles et orphelins par racine, dette `R-188` sur `Contract.orphans()` levée), `Enums\Stylesheet` + `ClientStylesheet` (dette `R-188` sur `Stylesheet` levée) ; `ListboxKeys` laissé à la partie suivante ; client 553/553 (541 inchangés), Unit 340, suite `Modules` 628, `build:check` vert ; `/boutique` vérifié en `immediate` et `submit` (filtre, tri, tiroir 393 px, aucune erreur console), `config/meilifacets.php` restauré à l'identique ; ni commit ni réindexation |
| 2026-09-28 | 3b | Seconde moitié de l'étape 3 (`R-188`) : `SearchSettings` (2, 120 ms, 4), racine `<x-meilifacets::search>` (`name`, `min-chars`, `delay`), `SearchRegistry` sur une base `NamedRegistry` extraite de `ListingRegistry`, `SiteSearchDescription`, `ListingScript` → `ClientScript` partagé (paquet `ScriptModule`, description paresseuse), `Preconnect::origin()` ; pages de listing identiques à l'octet avant/après ; `composer check` et suite `Modules` (624) verts ; ni commit ni réindexation. Trois propositions en attente dans `decisions.md` |
| 2026-09-28 | 3a | Arbitrages de Louis sur la refonte (`R-187`) : `FieldsOutsideSearchOrder` à la validation, pas de `withBaseFilter()`, `product` d'un autre plugin écarté (validé), `product` jamais dérivé par le défaut WordPress (`SearchablePostTypes`) ; `composer check` vert, suite `Modules` 604 verte |
| 2026-09-28 | 3a | Refonte de la déclaration des types sur revue de Louis (`R-187`) : types dérivés de WordPress (`SearchableTypeFactory`, libellés natifs, plus aucune chaîne du module), types retenus = indexés + publics + non `exclude_from_search`, produits en tête, `with…()` pour la surcharge, `NoFieldToSearch`, carte `search-card`, `VisibleProducts` dans `Search\`, `PostTypeArchive` et l'expression des chemins `labels.*` dédoublonnés ; plan des étapes 4-5 réécrit sur la réutilisation ; `composer check` et suite `Modules` (601) verts ; ni commit ni réindexation |
| 2026-09-28 | 3a | Premier jet de la déclaration des types (`R-187`) : `SearchableTypes`/`SearchableType`, défauts WordPress puis WooCommerce (`scopedIf`), filtre produits partagé avec `ProductListing` (`VisibleProducts`), `searchOn` lu dans l'ordre de recherche, archives `/boutique` et `/journal` résolues, `SearchTypeRefused` à la validation (`AcceptedSearchTypes`) ; `composer check` et suite `Modules` (595) verts ; ni commit ni réindexation. Reste la seconde moitié (racine, `SearchSettings`, description) |
| 2026-09-25 | 0 | Cadrage : cahier des charges, existant, décisions D-1 → D-9 (`741a334`) |
| 2026-09-25 | 0 → 1 | Branche `feat/site-search` ; passe de conformité ; index lu (67 documents, champs d'un article, surlignage, tolérance, seuil) ; D-1, D-3, D-8 et S-1 tranchés par Louis ; architecture rendue, non commitée |
| 2026-09-25 | 1 | Louis tranche S-17 (racine `<x-meilifacets::search>` + briques composées par le thème) ; S-20 : la page unifiée du lot 5 est remplacée ; S-18 (nom des crochets) ouvert ; place de la déclaration des types mise en suspens (S-21, avec S-19) |
| 2026-09-25 | 1 | Louis tranche S-21 et S-19 : types = contrat `SearchableTypes` (défaut `scopedIf`), recherche = sections posées (aucun `types` sur la racine), réglages scalaires = `SearchSettings` + attributs, aucune clé de config |
| 2026-09-25 | 1 | Louis tranche D-4 (`q` inchangé), D-7 (message seul, aucun repli), S-2 (lien vers l'archive du type, sans query var : plus de page de résultats ni de second listing), S-4 → S-7 et le § 8 ; plan réduit à huit étapes ; restent S-9 et S-10 |
| 2026-09-25 | 1 | Louis tranche S-9 (Entrée inerte), S-10 (verrou mobile), S-18 (crochets `search-*`) ; relecture de cohérence du document |
| 2026-09-28 | 2 | Lot de revue (`R-185`) réindexé en local : `100ml` 3, `50ml` 1, `30ml` 1, `pluralia` 0, `featured` et `exclude-from-search` 0, `labels.product_type` refusé par le moteur, `card.summary` présent ; point 2 reporté à la facette de recherche du listing ; taxonomies techniques figées, laissé ouvert |
| 2026-09-25 | 2 | `R-27` fermé sur validation de Louis (`pluralia` 0, `url` et `metas._edit_lock` refusés par le moteur, aucun balisage indexé) ; étape commitée |
| 2026-09-25 | 2 | Corrections de la revue de `fd595a3` (`R-185`) : libellés de toutes les taxonomies hors techniques (`pa_*` compris), défaut de l'ordre décorable, extrait de l'auteur entier (`card.summary`), test figé, pont à deux dépendances ; point 2 (`q`) arrêté et rapporté ; `R-186` noté ; suite `Modules` 582 verte ; réindexation à faire |
| 2026-09-25 | 2 | Seconde réindexation par Louis, mesures conformes (`excerpt`/`content` sans balise, `pluralia` 0, `"spacer"`/`"strong"` exacts 0) ; ordre de recherche surchargeable en entier par le contrat `SearchableAttributes` (`R-184`), réglages identiques à l'octet et égaux à ceux du moteur ; suite `Modules` 575 verte |
| 2026-09-25 | 2 | Refonte du pont (`R-182`) : un seul filtre `meiliscout/post/document`, champs construits par `PostDocument`, une expansion des termes par document ; documents #176, #116, #560 identiques à l'octet avant/après ; suite `Modules` 577 verte |
| 2026-09-25 | 2 | Réindexation par Louis et mesures (conformes ; `spacer` = tolérance aux fautes ; surlignage par `["card"]`) ; puis `excerpt` projeté et nettoyé à la place de `post_excerpt` (suite `Modules` 574 verte), seconde réindexation à faire |
| 2026-09-25 | 2 | Pertinence et index livrés dans l'arbre (`R-181`) : `labels`, `content`, `card.excerpt`, `searchableAttributes` explicites (titre → libellés → SKU → extrait → `content`), SKU exact, `R-160` ; `text` renommé `content` sur décision de Louis ; `composer check` et suite `Modules` (572) verts ; ni commit ni réindexation |
