# MeiliFacets — constats techniques

Voir aussi : [installation.md](installation.md) · [architecture.md](architecture.md) · [configuration.md](configuration.md) · [lots.md](lots.md) · [decisions.md](decisions.md) · [revue.md](revue.md)

Faits relevés en lisant le code de MeiliScout, de Pollora et du projet, ou consignés sur le
projet blaupunkt. Ce sont des constats, pas des décisions.

## MeiliScout

**`Indexer::configureIndices()` est du code mort.** Elle déclare des attributs `taxonomies.*`
alors que le chemin réellement exécuté — `PostIndexable::getIndexSettings()` — produit
`terms.*`. Deux conventions contradictoires dans le même fichier, dont une n'est jamais
appelée.

**Les metas filtrables sont choisies dans un écran d'administration.**
`Settings::get('indexed_meta_keys')` est alimenté depuis le back-office, donc stocké en base :
non versionné, non reproductible d'un environnement à l'autre.

**Le journal d'indexation est un fichier maison.** `IndexingLogger` écrit du JSON dans
`wp_upload_dir()` via `file_put_contents` — hors de `storage/logs`, hors du monitoring,
invisible de `artisan pail`.

**Le code de facettes existe mais est inopérant.** `QueryIntegration::interceptQuery()` envoie
bien `facets` et lit bien `getFacetDistribution()` — mais il demande `terms.*` et cherche
ensuite des clés commençant par `taxonomies.` dans la réponse. La boucle de reformatage ne
matche jamais : `$query->facet_distribution` est toujours vide. Même contradiction de convention
que `configureIndices()`. Il ne se déclenche par ailleurs que si `use_meilisearch` est posé dans
les query vars, ce que rien ne fait sur ce projet.

⚠️ Formulation corrigée le 2026-09-02 : ce document affirmait que « `facets` n'est jamais
envoyé, `facetDistribution` jamais lu ». La conclusion tenait, la lettre était fausse.

**Chaque enregistrement d'un post écrasait les réglages d'index.**
`AbstractSingleIndexer::indexItem()` appelle `ensureIndexExists()`, qui repousse
`$this->indexable->getIndexSettings()` — avec un indexable construit en dur par
`createIndexable()`. Les attributs déclarés par un indexable substitué étaient donc rétablis à
leur valeur par défaut à chaque sauvegarde, sans erreur ni trace. Observé dans les tâches du
moteur : un `settingsUpdate` à 23 attributs filtrables, suivi immédiatement d'un autre à 7.

Corrigé en amont dans MeiliScout — branche `feat/meilifacets`, commit `1c59a05` — par une
méthode `resolveIndexable()` qui fait passer les deux indexeurs temps réel par le filtre
`meiliscout/indexables`. Sans ce correctif, les facettes fonctionnent après une indexation
complète puis cassent dès la première mise à jour d'un contenu.

**Surcharger `formatForIndexing()` dans une sous-classe de `PostIndexable` est sans effet.**
`Indexer::indexItemsBatch()` teste `$indexable instanceof PostIndexable` et délègue alors le
formatage à `PostSingleIndexer`, qui construit son propre `PostIndexable` en dur. Une sous-classe
passe ce test, donc sa méthode n'est jamais appelée. Côté temps réel,
`AbstractSingleIndexer::createIndexable()` retourne également `new PostIndexable()` sans filtre,
et `SingleIndexingServiceProvider::register()` instancie `PostSingleIndexer` sans filtre non plus.

Conséquence pratique : seul le filtre `meiliscout/post/document`, appliqué **à l'intérieur** de
`formatForIndexing()`, permet d'ajouter des champs sur tous les chemins d'indexation. La
sous-classe ne sert qu'aux réglages d'index, que `Indexer` lit bien depuis l'indexable filtré.

Le piège est silencieux : les réglages seraient corrects, les documents dépourvus des champs
attendus, et les facettes vides sans qu'aucune erreur soit levée.

**Le réglage « taxonomies indexées » ne s'applique pas au champ `terms` des posts.**
`indexed_taxonomies` n'est lu que par `TaxonomyIndexable` et `TaxonomySingleIndexer`, soit
l'index `taxonomies` qui traite les termes comme des documents. Le champ `terms` d'un produit est
construit par `buildTermsFromCache()`, qui appelle `get_object_taxonomies($post->post_type)` sans
consulter ce réglage : **toutes** les taxonomies du post type s'y retrouvent, cochées ou non.
`PostIndexable` ne lit que `indexed_post_types` et `indexed_meta_keys`.

## Vérifié sur l'index réel

Première indexation du 2 septembre 2026 : 38 documents dans `posts`, dont 12 produits.

**Les réglages appliqués sont bien ceux de `PostIndexable::getIndexSettings()`**, pas ceux du
code mort :

```
filterableAttributes : post_type, post_status,
                       terms.term_id, terms.slug, terms.name,
                       terms.taxonomy, terms.term_taxonomy_id
sortableAttributes   : post_date, post_title
searchableAttributes : ["*"]
displayedAttributes  : ["*"]
```

**`terms` mélange bien toutes les taxonomies dans un tableau plat** — `product_brand`,
`product_type`, `product_cat` et `pa_contenance` côte à côte sur le même produit. La projection
par taxonomie reste donc nécessaire.

**Le prix et le stock sont déjà indexés**, dans `metas` : `_price`, `_regular_price`,
`_stock_status`, `_manage_stock`, `_backorders`, `_thumbnail_id`. Rien à ajouter au document
pour eux, seulement à les déclarer filtrables — ce qu'ils ne sont pas.

**`_price` est stocké en numérique** (`42`, `28.5`, `18`), pas en chaîne : un filtre par plage et
un tri numérique fonctionneront nativement. `_stock_status` est la chaîne `instock`, filtrable en
égalité. Reste le cas des produits variables, où le parent porte une fourchette et non un prix —
invérifiable tant que le catalogue n'en contient aucun.

**Des metas parasites sont indexées** : `_edit_lock`, `_edit_last`, `_yoast_wpseo_content_score`,
`_pluralia_test_fixture`. Elles gonflent le document et confirment que n'importe quelle meta
déclenche une réindexation.

**`displayedAttributes` vaut `["*"]`, et c'est ce qui transite qui coûte.** Mesuré le
2026-09-02 sur une page de douze produits : 33,7 Ko de JSON sans restriction, 5,1 Ko avec
`attributesToRetrieve: ["ID", "card"]`. Facteur 6,6 sur ce qui part à **chaque filtre**.

Deux points distincts, qu'il ne faut pas confondre : `attributesToRetrieve` règle le transfert,
`displayedAttributes` règle ce que la clé de recherche **autorise** à lire. Le second reste à
`["*"]`, donc la clé publique du navigateur donne accès à `post_content` et à toutes les metas.
`displayedAttributes` n'agit pas non plus sur le poids stocké : `avgDocumentSize` est resté à
4 519 octets avant comme après l'ajout de la carte.

## WordPress et WooCommerce

**Un nom de taxonomie est une query var publique.** Vérifié sur ce projet :
`/boutique?product_cat=visage` fait passer la grille servie de 12 à 8 produits — WordPress
filtre déjà sa propre requête. `product_brand`, `product_cat`, `product_tag`, `contenu`,
`essentiel` et `pluralia_selection` sont tous dans les 74 entrées de `$wp->public_query_vars`,
avec `page`, `paged`, `p`, `order` et `orderby`. Un paramètre de filtre portant l'un de ces noms
produit un double filtrage silencieux, WordPress d'un côté et Meilisearch de l'autre. Vérifier
un nom contre `$wp->public_query_vars`, jamais en testant une URL : `?page=2` répond `200`
aujourd'hui sans que rien ne garantisse qu'il le fera demain.

**L'archive produit affiche 16 produits, pas `posts_per_page`.** WooCommerce dérive
`loop_shop_per_page` de 4 colonnes × 4 lignes ; l'option WordPress vaut 10 et ne sert pas ici.

**WooCommerce avale les exceptions levées dans ses hooks de transition** (`CLAUDE.md`, gotcha
23) : une erreur d'indexation temps réel peut disparaître sans trace ailleurs que dans
`wc-logs/`. Journaliser au point de détection, ne pas compter sur une exception qui remonte.

**WP Rocket ne met pas en cache une URL portant une query string non déclarée** : elle est
régénérée par PHP à chaque appel. Déclarer ces paramètres comme cachables produirait une entrée
de cache par combinaison de filtres.

**Mais en production, c'est Varnish qui décide** — `clevercloud/_varnish.vcl`, versionné.
Constats de lecture, le 2026-09-02 :

- il **ne normalise pas** les query strings de filtres : une URL filtrée est sa propre entrée de
  cache, donc la grille pré-rendue qui lui correspond est bien celle qui sera servie ;
- `vcl_backend_response` force `Cache-Control: public, max-age=180` sur toute réponse qui n'en
  porte pas : **chaque combinaison de filtres devient une entrée de cache**, sans qu'on l'ait
  déclarée ;
- les réponses `503` ne sont **jamais** cachées (`return (pass)`), indépendamment de
  `Cache-Control: no-store` ;
- un visiteur portant un cookie `woocommerce_cart`/`wp_woocommerce_session` passe en `pass` :
  une part du trafic e-commerce ne touche jamais ce cache ;
- ⚠️ `vcl_recv` **supprime toute la query string** si le premier paramètre est `utm_*`, `ref`,
  `refid`, `refsrc`, `client`, `cx`, `eid`, `fbid`, `feed`, `ver`, `view` ou `adParams`. Aucun
  paramètre du module ne doit porter l'un de ces noms.

**`admin-ajax.php` est le point d'entrée le plus lourd de WordPress** : `wp-load.php` charge le
cœur, tous les plugins actifs et le thème, puis `admin.php` ajoute la couche d'administration.

**Et `wc_get_loop_prop('per_page')` dépend de la requête principale.** `wc_setup_loop()` pose
`per_page` à zéro ; la vraie valeur est écrite par `WC_Query::product_query()` sur la requête
WordPress principale. Y recourir imposerait donc la `WP_Query` qu'on bannit. La source sans
dépendance est le filtre lui-même :
`apply_filters('loop_shop_per_page', wc_get_default_products_per_row() * wc_get_default_product_rows_per_page())`.

## Pollora

**Le cœur de WordPress est chargé sur toute requête HTTP.** `PolloraServiceProvider` enregistre
`WordPressServiceProvider` inconditionnellement, et `Bootstrap::boot()` fait
`require_once ABSPATH.'wp-settings.php'`. Le mode allégé de Pollora retire les plugins, pas
WordPress.

**Une route REST déclarée par attribut est intestable via `wp eval` + `rest_do_request`**
(`CLAUDE.md`, gotcha 25) : `WpGlobals::wrap()` capture `$GLOBALS['current_user']` au moment de
l'enregistrement de la route.

**Le module Wishlist expose son API par `#[WpRestRoute(namespace: 'app/v1')]`**, donc par
`/wp-json/` : chaque appel démarre WooCommerce en entier. Son `routes/api.php` déclare par
ailleurs un préfixe `wp-json/app/v1` auquel le `RouteServiceProvider` ajoute `api`, produisant
`/api/wp-json/app/v1/wishlist` — une seconde route qui fait double emploi.

**Un réglage déclaré par un module ne peut pas être surchargé par le projet.**
`ModuleServiceProvider::registerConfig()` de nwidart fusionne par
`array_replace_recursive($existing, $moduleConfig)`, où `$existing` est le `config/<module>.php`
du projet : **c'est le module qui gagne**. Vérifié le 2026-09-02 — un `card.image_size` posé à
`portrait` côté projet ressort à `medium`. Et `merge_config_from()` retourne immédiatement si la
configuration est cachée, donc sous `config:cache` rien de ce que déclare le module n'existe.

Conséquence pour tout module Pollora : un réglage surchargeable se lit avec son défaut dans le
code, jamais depuis le `config/config.php` du module. Voir [configuration.md](configuration.md).

## Meilisearch, d'après le projet blaupunkt

**Les instances persistent par snapshots poussés environ une fois par heure.** Tout ce qui est
écrit entre deux snapshots disparaît au redémarrage — documents indexés **comme clés API**.
Deux clés ont été perdues ainsi le 20 août 2026.

**Une clé est dérivée de la clé maître et de son uid.** La recréer avec le même uid redonne
exactement la même valeur, ce qui permet à une clé de recherche de survivre à un redémarrage
sans redéploiement.

**Une panne du moteur casse le back-office.** `ClientFactory::getClient()` de MeiliScout renvoie
`null`, puis `AbstractSingleIndexer::indexExists()` lève une `Error` que le `catch (Exception)`
ne rattrape pas — 500 sur toute page d'édition. Une `Error` n'est pas une `Exception`.

**Un moteur vide répond `200 OK`.** Déploiement vert, recherche morte.

**Le plafond `maxTotalHits` promet des pages qu'il ne sert pas.** Mesuré le 2026-09-07 sur une
instance 1.53.1, avec des index fabriqués pour l'occasion.

Le réglage vaut **1 000 par défaut**, sur un index neuf comme sur celui du projet — personne ne l'a
choisi. Ce qu'il borne est la **profondeur de pagination**, pas la recherche :

| Ce qui est mesuré | Résultat |
| --- | --- |
| `facetDistribution` sur 2 500 documents | `{a: 1500, b: 1000}` — **exact**, bien au-delà du plafond |
| Filtre `brand = a` sur 1 500 documents | `totalHits: 1500` — **exact** |
| `totalHits` / `totalPages` sur 1 570 documents | 1 570 et 157 — **non plafonnés** |
| Pages réellement servies, 10 par page | **1 à 100**. De 101 à 157 : `200` avec **zéro résultat** |

**Facettes et filtres ne sont donc jamais faux ; la pagination l'est.** Le moteur annonce 157 pages
et en refuse 57 — il ne se tait pas, il se contredit. Un catalogue de 1 570 produits a un tiers de
ses fiches hors d'atteinte par la pagination.

⚠️ Le constat de l'audit du 2026-09-04 disait l'inverse : « `estimatedTotalHits` n'est pas un total,
et le moteur plafonne à 1 000 ». Sur cette version, `totalHits` est exact et c'est la pagination qui
ment.

**Relever le plafond ne coûte rien.** Mesuré sur 20 000 documents, moyenne de 10 requêtes :

| | plafond 1 000 | plafond 20 000 |
| --- | --- | --- |
| Page 1 | 1 ms | **0 ms** |
| Page 62 (990ᵉ résultat) | 10 ms | **11 ms** |
| Page 1200 (19 200ᵉ résultat) | interdite | **181 ms** |

Le réglage **autorise** un travail coûteux, il ne le provoque pas : à profondeur égale le prix est
identique. Ce qui coûte, c'est d'aller loin — 181 ms au 19 200ᵉ résultat contre 0 ms au premier. Et
c'est un réglage d'index, donc **aucun aller-retour supplémentaire** dans aucun cas.

**`distinct` ne déduplique pas les distributions de facettes.** Vérifié le 2 septembre 2026 sur
une instance 1.53.1, avec cinq documents fictifs représentant des variations :

| Produit | Variations |
| --- | --- |
| 100 | 50 ml à 10 €, 100 ml à 20 € |
| 200 | 50 ml à 12 €, 50 ml à 14 € |
| 300 | 30 ml à 25 € |

```
facets sans distinct :  { "100ml": 1, "30ml": 1, "50ml": 3 }
facets avec distinct :  { "100ml": 1, "30ml": 1, "50ml": 3 }   ← identique
```

Le produit 200 a deux variations en 50 ml, il est donc compté deux fois : la facette affiche
« 50 ml (3) » pour deux produits seulement. **Le compteur est faux dès qu'un produit possède
plusieurs variations partageant la même valeur de l'attribut facetté** — typiquement plusieurs
teintes au même format. Rien d'autre n'est faux : la liste des produits derrière ce filtre est
correcte.

Ce que le même test confirme, en revanche :

| Requête | Résultat | Lecture |
| --- | --- | --- |
| `distinct: product_id` | `100-A`, `200-C`, `300-E` | un document par produit |
| `prix < 15` + distinct | `100-A` (10 €), `200-C` (12 €) | le produit sort avec **la variation qui correspond** |
| `contenance = 100ml ET prix < 15` | aucun résultat | pas de faux positif |
| `estimatedTotalHits` sous distinct | 3 au lieu de 5 | le **total** est dédupliqué, lui |

La dernière ligne ouvre la seule voie connue vers des compteurs exacts : une requête
`multi-search` portant une sous-requête par valeur de facette, chacune sous `distinct`, dont on
lit le total. Un aller-retour HTTP, mais N recherches côté moteur.

L'avant-dernière ligne est décisive pour la forme de l'index : indexer au produit avec des
tableaux de valeurs aurait renvoyé ce produit à tort, puisque les deux conditions auraient été
satisfaites par deux variations différentes. **Indexer à la variation préserve la corrélation
entre attributs d'une même déclinaison.**

Mesure impossible à ce jour : la base locale contient 12 produits, tous simples, sans aucune
variation, et le dump `staging-20260102.sql` n'en contient aucune non plus.

## Relevés de l'audit du 2026-09-04

Points rapportés par une revue externe du lot 3b, vérifiés ou marqués comme non vérifiés.

**Varnish protège la mauvaise moitié de l'audience.** `vcl_recv` fait `return (pass)` dès qu'un
cookie `wordpress_`, `wp-settings-`, `woocommerce_(cart|session)` ou `wp_woocommerce_session` est
présent. Sur un site marchand, **tout visiteur ayant touché au panier paie un rendu PHP complet à
chaque URL filtrée** : « Varnish cache 180 s » ne couvre pas le public qui convertit.

**Le premier appel du navigateur au moteur coûte un préflight.** `MEILI_PUBLIC_URL` est une
seconde origine (port distinct), donc pas de mutualisation de connexion avec la page ; et le
client envoie `Content-Type: application/json` **et** `Authorization`, ce qui rend la requête non
simple au sens CORS. Sur mobile, le premier filtre paie DNS + TCP + TLS + `OPTIONS`, soit 500 ms
à 1 s, contre 1 à 10 ms de travail moteur. Le levier — `preconnect` et une requête de chauffe à
l'`idle` — vaut plus que tout arbitrage sur le poids du markup.

**`estimatedTotalHits` n'est pas un total, et le moteur plafonne à 1000.** `pagination.maxTotalHits`
vaut 1000 par défaut : au-delà, la pagination s'arrête sans le dire. Passer à `page`/`hitsPerPage`
donne `totalHits` et `totalPages` exhaustifs.

**Une promotion qui expire ne réindexe rien.** Le cron `wc_scheduled_sales` applique la fin d'une
promotion **sans sauvegarder le produit** : aucun hook de meta ne se déclenche, l'index garde
l'ancien prix, et la grille contredit la fiche produit — durablement, purge de cache comprise. À
traiter avec le lot 4.

**Non vérifié, contrairement à ce que l'audit avançait : les brouillons ne fuient pas.**
`PostIndexable::getItems()` demande bien `'post_status' => 'any'`, mais le test a montré
l'inverse : quatre produits créés en `draft`, `pending`, `private` et `future`, réindexés
complètement puis sauvegardés pour déclencher l'indexation temps réel, **n'entrent dans l'index ni
par l'un ni par l'autre chemin** — l'index ne contient que les 38 documents publiés. Le risque
théorique demeure, puisque rien dans le module ne le garantit ; un tenant token Meilisearch
(`searchRules` imposant `post_status = "publish"`) le fermerait pour de bon.

**Le markup d'un prix change selon le type de produit, et une liste blanche établie sur un seul
cas en supprime une partie en silence.** Mesuré le 2026-09-04 sur quatre produits fabriqués pour
l'occasion, le catalogue local n'ayant ni variable ni promo :

| Produit | `get_price_html()` ajoute |
| --- | --- |
| simple | `class`, `translate="no"` sur le symbole |
| **promo** | `<del aria-hidden="true">`, `<ins aria-hidden="true">`, un `<span class="screen-reader-text">` |
| **variable** | des `<span aria-hidden="true">` autour de la fourchette, plus le `screen-reader-text` |
| gratuit | comme le simple |

`aria-hidden` n'est pas décoratif : WooCommerce marque ainsi le prix **visuel** qu'il double d'un
texte pour lecteur d'écran. Le retirer fait annoncer le prix deux fois. Une première version de
`PriceMarkup::ALLOWED` l'omettait — les deux seuls cas cassés étaient précisément promo et
variable, ceux que le catalogue ne contient pas.

Conséquence retenue le 2026-09-04 : **le prix n'est pas filtré du tout.** Une liste blanche
défendrait contre un scénario où l'adversaire écrit déjà dans l'index — ce qui exige la clé
maître, jamais exposée, et lui permet bien pire. En échange elle casse du markup légitime, comme
ci-dessus, et recassera à la prochaine version de WooCommerce ou au premier plugin branché sur
`woocommerce_get_price_html`. Cela contredisait aussi la décision du lot 3a : on laisse à
WooCommerce toute la logique du prix, formatage compris.

Le tableau ci-dessus reste utile à qui serait tenté de refiltrer : la liste devrait alors
comporter `aria-hidden`, et être vérifiée sur les quatre types.

## Chaîne de diagnostic

Trois maillons sur quatre échouent sans lever d'exception.

| Maillon | Symptôme | Exception levée |
| --- | --- | --- |
| Schéma → settings d'index | facette absente, `400 invalid_search_filter` | oui, mais souvent avalée |
| Settings → documents | facette affichée, toujours vide | non |
| MeiliScout → contenu indexé | zéro résultat, page vide | non (`200 OK`) |
| Requête → rendu | résultats incohérents avec le catalogue | non |

L'`ApiException` du SDK porte `errorCode`, `errorType` et `errorLink` — ce dernier pointe vers
la page de documentation exacte. Les écraser détruit l'information la plus utile.

## Rendu des cartes

**`hidden` n'a aucune spécificité, le module la défend lui-même.** Les images absentes, les prix
absents et les valeurs de facettes repliées sont masqués par cet attribut : une seule règle
`.meilifacetsCardImage { display: block }` dans une feuille du thème les ferait tous réapparaître.

La contre-règle vit dans la feuille du module,
`resources/assets/css/meilifacets.css`, restreinte à ses propres classes :

```css
[class^="meilifacets"][hidden],
[class*=" meilifacets"][hidden] { display: none !important; }
```

Elle est plus spécifique (0,2,0) qu'une règle de classe (0,1,0), donc elle gagne même face à un
`!important` du thème — vérifié en navigateur : un nœud masqué reste à `display: none` après
injection de `.meilifacetsCardPrice { display: block !important }`. Et son sélecteur ne touche pas
les `[hidden]` du thème, qui a les siens (`.pluralia-panel[hidden]`, `.pluralia-drawer[hidden]`).

⚠️ **`Modules/` est hors du docroot** : la feuille n'est servie qu'une fois publiée dans
`public/modules/meilifacets/` (voir [installation.md](installation.md)). `Stylesheet` ne l'inscrit
que si le fichier existe, donc une publication oubliée ne produit pas de 404 — elle produit des
nœuds `hidden` visibles à l'écran, ce qui se voit tout de suite. Le module n'écrit jamais dans
`public/` à l'exécution.

**Une image sans URL garde son nœud, avec un GIF transparent en `src`.** Un `<img>` sans `src`
est invalide, et plusieurs navigateurs demandent alors l'URL de la page courante. Le data-URI de
`CardImage::BLANK` coûte 62 octets et aucune requête. Les douze produits du catalogue ont tous une
miniature, donc ce chemin n'est pas exercé aujourd'hui.

> Corrigé le 2026-09-04. Ce passage justifiait ces règles par `ResolvedListing::slots()`, qui
> rendait une page pleine d'emplacements vides pour qu'Alpine n'ait jamais à créer un nœud. Ce
> pool a été retiré : la grille ne rend que ses résultats, et les cartes sont clonées depuis un
> `<template>` rendu par le même composant Blade. Les deux règles restent nécessaires pour les images et les prix absents.

**Les dimensions viennent de l'index, pas du rendu.** `DefaultCardProjector` stocke
`image_width`/`image_height` avec l'URL, pour que la carte réserve sa place avant même que la
feuille de style soit chargée. Un document indexé avant ce changement ne les porte pas : les
attributs sont alors omis plutôt que devinés, et le CLS revient jusqu'à la réindexation
(`ddev wp meiliscout index --clear`). Le repli n'est pas une valeur par défaut arbitraire —
mentir au navigateur sur la taille provoque exactement le décalage qu'on veut éviter.

**Le prix n'est filtré nulle part.** `Card` fait `new HtmlString($document->text(CardField::Price))` :
ce que WooCommerce a formaté à l'indexation ressort tel quel dans la page. Le champ vient de
`wc_price()`, donc du markup maison — mais rien ne le vérifie au rendu, et un projet qui projette
un prix depuis une autre source injecterait ce qu'il veut. C'est R-26, ouvert.

**Les classes CSS du module sont en camelCase** (`meilifacetsCardImage`), pas en BEM kebab-case
comme celles du thème `pluralia` (`pluralia-product-card`). Choix assumé du projet, appliqué à
tous les composants du module : ne pas réintroduire de tiret en ajoutant une vue.
