# MeiliFacets — réglages et points d'extension

Voir aussi : [installation.md](installation.md) · [architecture.md](architecture.md) · [lots.md](lots.md) · [pieges.md](pieges.md) · [decisions.md](decisions.md) · [revue.md](revue.md)

Tout ce sur quoi un projet peut agir, et ce qu'il ne peut pas. Chaque ligne dit **qui lit**,
**quand**, et **si c'est surchargeable**.

## ⚠️ Un réglage déclaré dans le module n'est pas surchargeable

C'est le piège central de cette page, et il est contre-intuitif.

`ModuleServiceProvider::registerConfig()` de nwidart fusionne dans ce sens :

```php
config([$key => array_replace_recursive($existing, $moduleConfig)]);
```

`$existing` est le `config/meilifacets.php` du **projet**, `$moduleConfig` celui du **module** :
c'est donc le module qui gagne. Vérifié le 2026-09-02 — un `card.image_size` à `portrait` posé
côté projet ressort à `medium`, la valeur du module.

S'y ajoute un second effet : `merge_config_from()` **retourne immédiatement si la configuration
est cachée**. Sous `config:cache`, rien de ce que déclare le module n'existe — une clé qu'on
croyait avoir un défaut vaut `null` en production.

**La règle qui en découle** : un réglage surchargeable n'est **pas** déclaré dans
`Modules/MeiliFacets/config/config.php`. Il est lu avec son défaut dans le code —
`config('meilifacets.card.image_size', DefaultCardProjector::DEFAULT_IMAGE_SIZE)` — et documenté
ici. Le fichier de config du module ne porte que ce que le projet ne doit **pas** pouvoir
changer.

## Réglages

À poser dans `config/meilifacets.php`, à la racine du projet.

Toutes sont lues **dans le provider**, avec leur défaut, et injectées ensuite : c'est la règle du
module, et c'est ce qui les rend surchargeables sans que `config:cache` perde le fichier.

| Clé | Défaut | Sert à | Quand |
| --- | --- | --- | --- |
| `browser.url` | `''` | `BrowserConnection` — l'adresse que le navigateur joint | au rendu |
| `browser.key` | `''` | `BrowserConnection` — la clé de recherche seule | au rendu |
| `url_parameters` | `[]` | `UrlParameters` | au rendu et au filtrage |
| `query_parameters` | `sort`, `q`, `pg` | `UrlParameters` | au rendu et au filtrage |
| `card.image_size` | `medium` | `DefaultCardProjector` | à l'indexation |
| `displayed_attributes` | `[]` | `ConfiguredIndexAttributes` | à l'indexation |
| `apply_mode` | `submit` | `ProductListing` | au rendu |
| `card.eager` | `4` | `CardSettings` | au rendu |
| `engine.reachable_hits` | `1000` | `EngineLimits` | au rendu |

⚠️ **`browser.url` et `browser.key` sont les deux seules clés sans lesquelles rien ne fonctionne.**
Le module ne lit **jamais** `MEILI_PUBLIC_URL` ni `MEILI_SEARCH_KEY` : c'est au
`config/meilifacets.php` du projet de faire le pont. Absentes ou mal formées — un schéma manquant
suffit (R-65) — `BrowserConnection::isConfigured()` répond `false`, aucun JavaScript n'est chargé,
et rien ne le dit.

**`engine.reachable_hits` est le `maxTotalHits` du moteur** : le nombre de résultats au-delà duquel
Meilisearch répond `200` **sans aucun hit**, tout en continuant d'annoncer les pages qu'il refuse de
servir. `Pagination` et son miroir `PageWindow` s'en servent pour ne jamais proposer une de ces
pages — et **le module l'écrit sur l'index**, à chaque `ensureIndexExists()`, comme les quatre
autres réglages qu'il pose.

La clé est donc la seule source : la changer et réindexer suffit. Vérifié le 2026-09-07 —
`reachable_hits` à 2500, réindexation, l'index déclare `{"maxTotalHits":2500}` et la description
publiée au navigateur porte `"reachableHits":2500`.

⚠️ Corollaire : un `maxTotalHits` posé à la main sur l'index **sera écrasé** à la prochaine
indexation. C'est vrai de tous les réglages que le module pose, et c'est le prix de n'avoir qu'une
source.

Facettes, filtres et total restent exacts au-delà du plafond : seules les pages sont concernées
(mesuré, `R-42`).

**`url_parameters`** associe une taxonomie au nom qu'elle porte dans l'URL —
`'product_brand' => 'marque'`. Une taxonomie absente de ce tableau prend un nom préfixé, jamais
son nom nu : un nom de taxonomie est une query var publique de WordPress, et
`?product_cat=visage` fait déjà filtrer WordPress lui-même. Ces noms partent dans des URLs
indexées : les changer casse des liens.

**`card.image_size`** est la taille à laquelle l'URL d'image stockée dans le document est
résolue. Elle est consommée **une seule fois, à l'indexation** : le document porte l'URL finale,
le navigateur ne rappelle jamais WordPress. Sans effet dès que `CardProjector` est rebindé — un
projet qui projette sa propre carte choisit sa taille dans son projecteur.

**`card.eager`** est le nombre de cartes chargées en `loading="eager"` et
`fetchpriority="high"` en tête de page ; les suivantes passent en `lazy`. La valeur dépend du
nombre de colonnes que le thème pose au-dessus de la ligne de flottaison : trop basse, l'image du
LCP est différée par le chargement paresseux censé l'aider ; trop haute, elle se dispute la bande
passante avec des images que personne ne voit encore.

## Étendre la carte projetée

`CardProjector` se **décore**, il ne se remplace pas : chaque couche ajoute ses champs à ceux de
la précédente. C'est ce que fait déjà `WooCommerceCardProjector`, qui ajoute le prix formaté à la
carte du module sans rien connaître du reste.

```php
final readonly class BrandCardProjector implements CardProjector
{
    public function __construct(private CardProjector $card) {}

    public function project(WP_Post $post): array
    {
        return [
            ...$this->card->project($post),
            ...$this->brand($post),
        ];
    }
}
```

Empiler la décoration se fait avec `extend()`, jamais avec `bind()` — un `bind()` remplacerait la
pile entière, prix WooCommerce compris :

```php
$this->app->extend(
    CardProjector::class,
    fn (CardProjector $card): CardProjector => new BrandCardProjector($card)
);
```

⚠️ **`CardField` est une énumération fermée.** Un projet qui ajoute un champ déclare la sienne
plutôt qu'une chaîne littérale, pour que le nom du champ reste un contrat vérifié :

```php
enum BrandCardField: string
{
    case Brand = 'brand';
}
```

Un champ absent doit être **absent**, pas vide : `image()` et `price()` rendent `[]` plutôt
qu'une chaîne vide, pour qu'un document ne porte jamais une clé qui ne veut rien dire.

## Points d'extension

Des bindings du conteneur Laravel, à poser dans le `register()` d'un provider du projet.

| Contrat | Défaut | Rôle | Remplaçable |
| --- | --- | --- | --- |
| `CardProjector` | `DefaultCardProjector` | ce que le document porte pour peindre une carte | oui, `extend` |
| `TermHierarchy` | `WordPressTermHierarchy` | remontée d'un terme vers ses ancêtres | oui, mais interne |
| `IndexAttributes` | selon WooCommerce | attributs d'index qu'un plugin contribue | oui, `bind` |
| `FacetCounter` | `DisjunctiveFacetCounter` | comment les compteurs de facettes sont calculés | oui, `bind` |
| `SearchEngine` | `MeilisearchEngine` | l'envoi des recherches au moteur | oui, `bind` |
| `Listing` | `ProductListing` si WooCommerce | ce qu'un listing déclare | découverte automatique |
| `ProductFacets` | `WooCommerceFacets` — catégorie et marque | les taxonomies que la boutique parcourt | oui, `scoped` |
| `ProductSorts` | `WooCommerceSorts` — prix ↑↓, nouveautés, « Promotions » (`on_sale`, offert seulement si le listing déclare un prix) | les tris offerts | oui, `scoped` |

```php
$this->app->bind(CardProjector::class, ProductCardProjector::class);
```

`CardProjector` est lié par `bindIf` : **il n'est jamais obligatoire**. Un projet neuf obtient
une carte fonctionnelle — titre, lien, image, et prix formaté par WooCommerce quand il est actif
— sans écrire une ligne de PHP. Le remplacer sert à projeter la carte du thème, pas à faire
démarrer le module.

`TermHierarchy` existe pour rendre `TermAncestry` testable sans WordPress. Il est remplaçable,
mais rien ne le présente comme un point d'extension à destination des projets.

## Ce que la clé de recherche peut lire

`displayedAttributes` décide de ce qu'une réponse peut rendre — **pas** de ce qui est filtrable ni
facettable : restreindre n'enlève ni un filtre ni un compteur (vérifié). Le module déclare les deux
seuls champs qu'il lit, `ID` et `card` ; tout le reste est fermé.

Cela compte parce que la clé de recherche part dans le navigateur : sans restriction, n'importe
quel visiteur lit `post_content` et toutes les metas, `_edit_lock` comprise.

Un projet qui a besoin d'autres champs les déclare, il ne subit pas le défaut :

```php
// config/meilifacets.php
'displayed_attributes' => ['post_excerpt', 'metas._sku'],
```

`'*'` dans cette liste rouvre le document entier — nécessaire à un projet qui s'appuie sur la
recherche `WP_Query` de MeiliScout, qui reconstruit un `WP_Post` depuis le hit complet.

Un plugin contribue les siens par `IndexAttributes::displayed()`, comme il contribue déjà ses
attributs filtrables et triables.

## Ce qui se règle sur un composant, pas en configuration

Le retour du regard en haut du listing après un geste ne se déclare pas ici : il s'active
**composant par composant**, par l'attribut `scroll` (`<x-meilifacets::pagination scroll />`). Une
clé de configuration aurait imposé le même choix à la pagination, au tri et à la remise à zéro,
alors qu'un thème veut couramment l'un sans les autres. Voir `architecture.md`.

## Ce qui est indexable

Toute URL portant un paramètre du listing — facette, tri, page, recherche — sort en
`noindex, follow` : le contenu existe déjà sur le chemin nu, et les liens qu'elle porte restent
suivis. `IndexingPolicy` s'en charge par le filtre `wp_robots`, que Yoast respecte.

La règle **ne s'applique que sur une page d'archive ou de recherche** : le filtre `wp_robots` est
global, et les noms réservés le sont aussi, donc sans cette garde un lien de campagne portant `?q=`
sortirait l'accueil de l'index. Vérifié le 2026-09-06 : `/boutique` reste `index`,
`/boutique?categorie=cheveux`, `?sort=newest` et `?pg=2` passent en `noindex, follow`,
`/?q=bonjour` reste `index`.

Un paramètre étranger au module ne déclenche rien : `?utm_source=news` reste indexable.

**Pas de canonique** vers le chemin nu en plus : un `noindex` et une canonique pointant ailleurs
sont deux signaux contradictoires.

## ⚠️ Le cron doit tourner, sinon l'index diverge en silence

**Exigence de déploiement, à vérifier sur chaque environnement.** Rien dans le module ni dans
MeiliScout ne prévient si elle n'est pas remplie : l'index se contente de vieillir.

Pollora **désactive WP-Cron en dur**, sur tous les environnements :

```php
// vendor/pollora/framework/src/WordPress/Bootstrap.php:336
Constant::queue('DISABLE_WP_CRON', true);
```

Sans condition, et sans rien mettre à la place — l'attribut `#[Schedule]` de Pollora enregistre
pourtant ses tâches avec `wp_schedule_event()` (`ScheduleDiscovery.php:342`), c'est-à-dire le cron
qu'il vient d'éteindre. Il faut donc qu'un cron **système** pique `wp-cron.php`, ou que quelqu'un
lance les évènements dus à la main.

Deux files, deux conséquences distinctes :

| File | Ce qui s'arrête sans elle |
| --- | --- |
| **WP-Cron** | l'indexation différée de MeiliScout (`meiliscout_process_async_queue`, `meiliscout_process_indexation`) |
| **Action Scheduler** | les bascules de promotion WooCommerce (`wc_product_start_scheduled_sale`, `woocommerce_scheduled_sales`) — donc les prix, donc le filtre de prix |

Action Scheduler ne dépend pas de WP-Cron : il se déclenche aussi tout seul, mais **uniquement sur
une requête d'administration** (`is_admin()`, `ActionScheduler_QueueRunner.php:141`), par une requête
en boucle locale vers `admin-ajax.php`. Sur un environnement derrière une auth HTTP Basic, cette
boucle prend un `401` et la file se bloque — à vérifier en préprod.

**En local**, où aucun cron ne tourne, les deux se lancent à la main :

```bash
ddev wp cron event run --due-now                  # tout ce qui est dû
ddev wp cron event run meiliscout_process_async_queue
ddev wp action-scheduler run                       # les bascules de promo
```

**Le diagnostic qui dit tout de suite si le cron est mort** — si la colonne de droite est vieille de
plusieurs jours, rien ne tourne :

```bash
ddev wp cron event list --fields=hook,next_run_gmt --format=csv | sort -t, -k2 | head -5
```

## Indexation différée : ce qu'elle coûte et ce qu'elle rapporte

Réglage MeiliScout, **désactivé par défaut** : `meiliscout_async_indexing` (option
`meiliscout/meiliscout_async_indexing`, ou la variable d'environnement / constante
`MEILISCOUT_ASYNC_INDEXING`, qui gagnent sur la base).

Mesuré le 2026-09-15 sur ce projet, sur une modification de prix :

| | synchrone (défaut) | différé |
| --- | --- | --- |
| durée de la sauvegarde | **36 ms** | **3 ms** |
| appels HTTP vers Meilisearch | 2 par sauvegarde | 0 dans la requête |
| 5 sauvegardes d'affilée | 10 appels | **1 entrée de file** |
| fraîcheur de l'index | immédiate | jusqu'à 5 min (`meiliscout/async_indexing_delay`) |
| si le cron ne tourne pas | index à jour | **index jamais mis à jour** |

Deux poussées par sauvegarde parce que WooCommerce écrit `_regular_price` puis `_price`, et que les
crochets de MeiliScout ne filtrent pas par clé : les deux envoient le même document. Le différé
dédoublonne, c'est là qu'est l'essentiel du gain.

La dernière ligne est la raison pour laquelle ce réglage ne s'active pas sans avoir vérifié la
section précédente.

## ⚠️ Toute écriture de meta réindexe — ce qu'il faut surveiller

Depuis `R-112` (MeiliScout `a83fa4b`), plus rien n'exempte les requêtes AJAX de l'indexation. C'est
la correction voulue, mais elle rend visible un comportement qui ne l'était pas : les crochets de
MeiliScout sont branchés sur `updated_post_meta`, `added_post_meta` et `deleted_post_meta` **sans
filtrer par clé**. Une écriture de meta, quelle qu'elle soit, sur un contenu indexé, réindexe le
document entier.

Ce que ça coûte, mesuré le 2026-09-15 : **~16 ms par poussée**, et une modification de prix en écrit
deux (`_regular_price` puis `_price`).

**Le périmètre exact sur ce projet** — `meiliscout/indexed_post_types` vaut `post`, `page`,
`product`. Les pièces jointes n'y sont pas : Imagify et les optimisations d'images ne déclenchent
rien.

**Ce qui est à surveiller**, c'est un plugin qui écrit des metas en AJAX sur un contenu indexé. Le
cas présent ici : l'éditeur groupé de Yoast (`wp_ajax_wpseo_save_title`,
`wpseo_save_all_titles`, `wpseo_save_all_descriptions` — `wordpress-seo/admin/ajax.php:96`, `:246`,
`:261`) écrit des metas sur `post` et `page`. Chaque champ enregistré réindexe désormais son
document.

**Les deux contre-mesures**, dans l'ordre :

1. **l'indexation différée** — section précédente : ramène la sauvegarde à ~3 ms et dédoublonne ;
2. **`meiliscout/skip_indexing`** — le seul filtre que MeiliScout expose, pour couper l'indexation
   dans un contexte précis et identifié :

   ```php
   add_filter('meiliscout/skip_indexing', fn (bool $skip): bool => $skip
       || (wp_doing_ajax() && str_starts_with((string) ($_REQUEST['action'] ?? ''), 'wpseo_')));
   ```

   À n'écrire que sur un cas constaté, jamais par précaution : c'est exactement le raisonnement qui
   avait produit le garde `DOING_AJAX`, et dix-huit mois plus tard plus personne ne savait pourquoi
   les prix ne suivaient pas.

## WooCommerce ne filtre plus la requête principale en parallèle

**`min_price`, `max_price` et `filter_*` sont lus directement dans `$_GET` par
WooCommerce**, qui s'en sert pour filtrer la requête principale en SQL
(`class-wc-query.php:588`, puis `:604-607`). Sur une page rendue par le module, ce travail est
intégralement perdu — et il laisse `found_posts` rétréci par un filtre que la page n'affiche pas.
Mesuré le 2026-09-15 sur `/boutique?min_price=55&max_price=120` : **5 au lieu de 74**.

Le module désarme donc les deux clauses, par le filtre officiel que WooCommerce expose depuis 9.9 :

```php
// app/Search/NativeFiltering.php
#[Filter('woocommerce_enable_post_clause_filtering')]
public function leaveTheMainQueryAlone(): bool { return false; }
```

**Portée exacte.** Le crochet n'existe que pendant une requête produit — `add_filter('posts_clauses',
…)` est posé dans `WC_Query::product_query()`, appelée depuis `pre_get_posts` pour ces requêtes-là
seulement. Il est donc inerte sur tout autre listing, et sur un site sans WooCommerce.

| Ce qui est désarmé | Ce qui ne l'est pas |
| --- | --- |
| « Filtrer par prix » natif (`min_price`, `max_price`) | le filtre par note (`rating_filter`), qui est une `meta_query` |
| la navigation à facettes par attribut (`filter_*`) | le tri, la visibilité, le stock, les archives de catégorie, la recherche |
| | la requête principale elle-même, qui tourne toujours |

**Ce que ça coûte en performance : rien.** Mesuré, cache chaud : 0 requête SQL de différence,
0,19 ms contre 0,27 ms. Le gain est la cohérence de `found_posts`, pas la vitesse.

⚠️ **Désarmer la clause n'empêche pas WooCommerce de voir les noms.** Quatre autres lecteurs
subsistent, tous cosmétiques mais réels : `is_filtered()`
(`wc-conditional-functions.php:341`), les liens que les widgets recopient
(`abstract-wc-widget.php:339-344`), le widget « Filtrer par prix » qui se dessine depuis eux
(`class-wc-widget-price-filter.php:119-120`) et le widget « Filtres actifs »
(`class-wc-widget-layered-nav-filters.php:48-49`). Un projet qui affiche l'un de ces widgets à côté
d'un listing MeiliFacets les verra réagir. C'est ce que `meilifacets:check-parameters` continue de
signaler, et la seule façon de le supprimer entièrement reste de renommer les deux paramètres.

**Pour un projet qui veut garder le filtrage natif** — un thème qui rend encore la boucle
WooCommerce quelque part — il suffit de reprendre la main après le module :

```php
add_filter('woocommerce_enable_post_clause_filtering', '__return_true', 20);
```

## Une seule frontière avec MeiliScout

Le module ne touche MeiliScout qu'à deux endroits, et jamais depuis sa logique métier :

| Où | Ce qu'il y prend |
| --- | --- |
| `SearchServiceProvider::engine()` | le client de recherche et le nom de l'index |
| `MeiliScoutBridge` | les filtres d'indexation, qui sont sa raison d'être |

`MeilisearchEngine` reçoit son client par le constructeur : il ne connaît pas `ClientFactory`. Un
projet qui voudrait un moteur de secours, un cache ou un enregistreur relie `SearchEngine` sans
toucher au reste.

## Ce que le module lit ailleurs

| Source | Clé | Effet |
| --- | --- | --- |
| Réglages MeiliScout (base de données) | `indexed_post_types` | détermine les taxonomies projetées et déclarées filtrables |
| Environnement | `MEILI_HOST`, `MEILI_KEY`, `MEILI_INDEX_NAME`, `MEILI_MATCHING_STRATEGY` | connexion PHP, lue par MeiliScout |
| Environnement | `MEILI_PUBLIC_URL`, `MEILI_SEARCH_KEY` | connexion du navigateur |

⚠️ `indexed_post_types` vit **en base**, alimenté depuis l'écran d'administration de MeiliScout :
non versionné, à refaire sur chaque environnement. Les taxonomies filtrables en découlent
directement — ce qui est indexé est filtrable.

## Réglages d'index posés par le module

Écrits par `FacetedPostIndexable::getIndexSettings()`, à chaque `ensureIndexExists()`.

| Réglage | Valeur |
| --- | --- |
| `filterableAttributes` | ceux de MeiliScout, plus `facets.<taxonomie>` pour chaque taxonomie indexée, plus `metas._price` et `metas._stock_status` si WooCommerce est actif |
| `sortableAttributes` | ceux de MeiliScout, plus `metas._price` si WooCommerce est actif |
| `faceting.sortFacetValuesBy` | `count` pour toutes les facettes |
| `pagination.maxTotalHits` | ce que `engine.reachable_hits` déclare |
| `displayedAttributes` | ceux de MeiliScout, plus `card` et ce que `displayed_attributes` ajoute |
| `faceting.maxValuesPerFacet` | ce que `engine.max_facet_values` déclare |

Aucun point d'extension dédié : les changer demande d'étendre `FacetedPostIndexable` et de le
substituer par `meiliscout/indexables` à une priorité plus haute que celle du module.

## Trois plafonds, et lequel coupe quoi

Une facette qui ne montre pas toutes ses valeurs les a perdues à l'un de ces trois endroits. Ils
s'appliquent **dans cet ordre**, et aucun ne dépend de la page affichée : `facetDistribution` est
calculée par le moteur sur **tous** les documents qui correspondent au filtre, pas sur les résultats
renvoyés — mesuré le 2026-09-08, distribution identique à `hitsPerPage` 1, 16 et 200.

| Plafond | Défaut | Qui le pose | Ce qu'il coupe |
| --- | --- | --- | --- |
| `faceting.maxValuesPerFacet` | **1 000** | le module, par `engine.max_facet_values` | les valeurs distinctes que `facetDistribution` renvoie, les **mieux comptées** d'abord grâce à `sortFacetValuesBy` |
| `Facet::$cap` | 30 | la facette | ce que le module garde de ce qu'il a reçu — sur une page filtrée, jusqu'à deux fois `cap` (listing non filtré et sélection) plus les valeurs cochées |
| `Facet::$visible` | 10 | la facette | ce qui est **lu** avant dépliage — le reste est rendu, jamais perdu |

⚠️ **`cap` ne peut pas dépasser `maxValuesPerFacet`.** Une facette déclarée `cap: 2000` en obtiendra
mille, sans erreur : `array_slice()` sur mille éléments en rend mille. Sur une taxonomie de plusieurs
milliers de termes, c'est le plafond qui décide en dernier.

**Ne le calez pas sur `cap`.** Le moteur tronque par **compte global**, avant qu'une facette ne
restreigne aux enfants du rayon courant : une valeur peu comptée à l'échelle de la boutique peut
être la seule qui compte sur sa page. Le plafond suit la **taille de la taxonomie**, jamais `cap`.

Depuis le 2026-09-15 le module l'écrit et se plaint quand une distribution revient pile dessus —
`FacetTruncated`, dans le canal d'erreurs de l'application. Il se plaint aussi sur **100**, le défaut
du moteur : entre une mise en production et sa réindexation, l'index coupe encore là où le module
demande déjà plus.

`sortFacetValuesBy` à `count` n'est pas cosmétique — Meilisearch trie les valeurs
alphabétiquement par défaut, ce qui ferait afficher à une facette plafonnée à dix valeurs les dix
premières de l'alphabet plutôt que les dix plus représentées. C'est le réglage qui décide **quelles
valeurs remontent** ; l'ordre dans lequel elles s'affichent se déclare par facette
(`DisplayOrder::Count`, `DisplayOrder::Declared` ou un `ValueOrder` comme `NameOrder`), voir
[architecture.md](architecture.md).

## Filtres consommés chez MeiliScout

| Filtre | Ce que le module y fait |
| --- | --- |
| `meiliscout/post/document` | ajoute `facets` et `card` au document |
| `meiliscout/indexables` | substitue `FacetedPostIndexable` à `PostIndexable` |

Le premier est appliqué **à l'intérieur** de `formatForIndexing()`, donc sur tous les chemins
d'indexation. Un projet peut s'y brancher à son tour ; sa priorité décide de l'ordre.

## Priorité de chargement du client

Le module charge son script en `fetchpriority="low"` : la page est rendue par le serveur, le navigateur
télécharge d'abord ce qu'elle affiche. Mesuré sur `/boutique` en réseau lent : LCP 232 ms plus tôt, listing
lié 75 ms plus tard (`R-134`). Un projet choisit une autre valeur par filtre :

```php
add_filter('meilifacets/script_fetchpriority', fn (): string => 'auto');
```

Valeurs admises par WordPress : `high`, `low`, `auto`. Une autre valeur est refusée par WordPress
lui-même, qui le signale (`_doing_it_wrong`) et charge en `auto`.

## Quand la recherche part

`apply_mode` décide si cocher une case cherche aussitôt ou attend une validation :

| Valeur | Ce qui se passe | Pour qui |
| --- | --- | --- |
| `submit` (défaut) | un bouton « Appliquer les filtres », une recherche par validation | un gros catalogue, où chaque case cochée coûterait une recherche |
| `immediate` | pas de bouton, une recherche par case cochée | un catalogue modeste, où la réponse immédiate vaut le coût |

Le mode voyage **dans la description JSON** que le serveur publie (`'apply' => …`), lue par
`Listing.searchesAtOnce`. L'attribut `data-apply` du bloc de facettes est rendu pour le thème, qui
peut s'en servir pour styler ; **le client ne le lit pas**.

⚠️ En mode `submit`, cocher une case ne cherche rien mais pose l'état : trier, paginer ou remettre
à zéro emportent donc les filtres en attente (D-10).

Il n'y a délibérément pas de `<form method="get">` : un formulaire GET ne sait produire que
`marque[]=a&marque[]=b`, ce qui donnerait une seconde URL — et une seconde entrée de cache Varnish
— pour un état que `?marque=a,b` décrit déjà.

Un listing qui doit imposer son mode surcharge `applyMode()` au lieu de lire la configuration.

## Déclarer un listing

Une classe qui implémente `Listing`, découverte automatiquement dans `app/` et `Modules/*/app` :
il n'y a rien à enregistrer. Elle porte son filtre de base, qui se calcule au rendu — c'est ce qui
permet à `ProductListing` d'ajouter le rayon courant aux clauses sur une archive de catégorie, où
il est déjà porté par le chemin. Ses facettes et ses tris, en revanche, ne sont plus écrits dans la
classe : ils viennent des contrats `ProductFacets` et `ProductSorts` ci-dessus, qu'un projet
remplace sans toucher au module. La facette catégorie n'est pas retirée sur une archive de
catégorie : `ChildTermsFacet` la **conserve et la restreint** au niveau courant.

Un `Facet` déclare sa taxonomie, son libellé, son mode de sélection, sa limite visible, son
plafond et s'il est de cardinalité élevée. Un **`ChildTermsFacet`** en est une variante pour une
taxonomie hiérarchique : il ne propose que les termes situés **directement sous celui que le chemin
porte** — les rayons de premier niveau sur une archive nue, les sous-rayons sur une archive de
catégorie, rien du tout sur une feuille. C'est ce qui rend utilisable une taxonomie de cent termes
sur trois niveaux, qu'aucun plafond ni aucun ordre d'affichage ne peut sauver à plat.

Toute facette écarte par défaut le **terme de repli** de sa taxonomie — « Non classé »,
« Uncategorized » —, lu depuis `default_term_<taxonomie>` ou `default_<taxonomie>` selon la
convention en usage. Il dit qu'un contenu n'a été rangé nulle part, ce qui n'est pas une manière de
parcourir un catalogue. Une facette dont le repli est un terme réel, choisi par un éditeur, déclare
`defaultTerm: DefaultTerm::Shown`. Écarter la valeur ne retire pas le contenu du listing. Le mode de sélection n'est pas cosmétique : seule une
facette multi-sélection reçoit une recherche disjonctive, et seulement une fois qu'elle
contraint réellement les résultats.

### Personnaliser le menu « Trier par »

Aucun filtre WordPress : un projet lie sa propre implémentation de `ProductSorts`, comme Pluralia le
fait pour `ProductFacets` (`CatalogueFacets`, dans `AppServiceProvider`). Elle peut partir de la liste
du module plutôt que la réécrire — ici, sans « Nouveautés » :

```php
use Illuminate\Support\Arr;
use Modules\MeiliFacets\Contracts\ProductSorts;
use Modules\MeiliFacets\Listing\WooCommerceSorts;

final class CatalogueSorts implements ProductSorts
{
    public function __construct(private readonly WooCommerceSorts $defaults) {}

    public function all(): array
    {
        return Arr::except($this->defaults->all(), ['newest']);
    }
}
```

```php
// dans le provider du projet
$this->app->scoped(ProductSorts::class, CatalogueSorts::class);
```

Les clés sont les valeurs du paramètre `sort` dans l'URL : `price_asc`, `price_desc`, `newest`,
`on_sale`. Un tri ajouté est un `Sort`, comme ceux de `WooCommerceSorts`. Vérifié sur Pluralia le
2026-09-17 : la liste passe de `price_asc`, `price_desc`, `newest`, `on_sale` à `price_asc`,
`price_desc`, `on_sale`.

Trois choses restent hors de cette liste :

- **« Pertinence »** est ajoutée en tête par le module (`SortChoices`) : elle ne se retire ni ne se
  déplace, seul son libellé (`Relevance`) passe par les traductions ;
- **la liste vaut pour tout `ProductListing`**, pas pour une page en particulier ;
- **« Promotions » est retirée** d'un listing qui ne déclare pas de prix, quelle que soit la liste
  liée. Là où elle est offerte, elle est masquée quand aucun produit n'est en promotion, sauf si
  c'est le tri choisi (`R-131`).

## Placer les facettes dans un gabarit

Deux composants, comme pour le tri et la remise à zéro : un qui place **une** facette, un qui prend
**ce qui reste**.

```blade
@use('App\Cms\Products\ShopFacet')

<x-meilifacets::listing>
    <x-meilifacets::facet :facet="ShopFacet::Category" class="lg:col-span-2" scroll />
    <x-meilifacets::facets />
</x-meilifacets::listing>
```

⚠️ **Tout composant du module vit à l'intérieur de `<x-meilifacets::listing>`.** C'est lui qui rend
`[data-listing]`, et le client s'attache une fois par racine : ce qui est posé dehors est rendu,
stylé, cochable — et **inerte**. Cases sans effet, compteurs jamais rafraîchis, « Voir plus » qui ne
déplie rien. La règle vaut pour `facet`, `facets`, `sort`, `reset`, `pagination`, `active-filters`
et `results` sans exception. Un composant posé dehors est signalé au démarrage :

```
[meilifacets] the client binds inside [data-listing] only. Move inside <x-meilifacets::listing>: facet.
```

`<x-meilifacets::facets />` rend toutes les facettes qu'aucun `<x-meilifacets::facet>` n'a déjà
placées, dans l'ordre déclaré. Placer une facette **après** le groupe lève : le groupe l'a déjà
prise. Un groupe auquel il ne reste rien, et qui ne porte pas le bouton d'envoi, ne rend aucune
balise.

**Une facette n'est rendue qu'une fois par page.** Un second rendu lève une exception nommée plutôt
que de dupliquer silencieusement les entrées et les identifiants qu'elles portent.

### Nommer une facette

Le composant désigne une facette par un nom que la facette porte elle-même, jamais par sa taxonomie
— pour qu'aucun gabarit n'ait à connaître `product_cat` :

```php
new Facet('product_cat', __('Category'), name: ShopFacet::Category)
```

`name` accepte une chaîne ou un `BackedEnum`. Sans lui, la facette répond à sa taxonomie : rien
n'est à déclarer pour démarrer. Ranger les noms dans une énumération évite qu'un gabarit porte une
chaîne libre, et donne à l'analyse statique de quoi voir une faute de frappe. Au rendu, un nom
inconnu lève en nommant les facettes déclarées, dans les deux formes.

`<x-meilifacets::facet>` accepte aussi une déclaration directement (`:facet="$facet"`), ce dont se
sert `<x-meilifacets::facets>` en interne.

### Ce que le composant transmet

Le sac d'attributs arrive sur le `<fieldset>` et fusionne avec la classe du module
(`class="meilifacetsFacet lg:col-span-2"`), et `scroll` s'y déclare comme sur les autres composants.
Une facette placée à part se comporte donc comme celles du groupe.

### Surcharger le markup

`components/facet.blade.php` est une vue à part entière : un thème la surcharge **seule** — pour un
menu déroulant, une modale — sans figer le reste du markup du module ni se décrocher des versions
suivantes du contrat. Le crochet `data-meili="facet"` est sur l'élément le plus extérieur, parce que
c'est celui que le client masque : un thème qui enrobe doit déplacer le crochet avec lui.

### Les trois sous-vues du prix

Le composant prix assemble trois vues surchargeables une par une, sous le même chemin :

| Vue | Rendue quand | Ce qu'elle reçoit |
| --- | --- | --- |
| `components/price/range.blade.php` | `PricePart::Slider` est déclaré | `label`, `readout`, `fill`, `handles`, `bounds`, `money` |
| `components/price/fields.blade.php` | `PricePart::Fields` est déclaré | `handles`, `bounds`, `money` |
| `components/price/hidden.blade.php` | `PricePart::Fields` ne l'est pas | `handles` |

Ce sont des composants anonymes : chacun ouvre sur `@props` et ne reçoit **que** ce qui y est
déclaré — il n'hérite pas de la portée du composant parent. Une surcharge qui a besoin d'un crochet
le nomme par son cas d'énumération, `@use(Modules\MeiliFacets\Enums\Hook)` puis
`{{ Hook::PriceRange->attribute() }}`, et non par la fermeture `$hook()` du parent, hors de portée
ici.

### Les bornes de prix gardent les noms de WooCommerce

`min_price` et `max_price` sont les défauts du module **et** les noms que WooCommerce lit dans
`$_GET`. C'est voulu : un lien écrit pour WooCommerce pilote le module sans traduction.
`NativeFiltering` l'empêche de filtrer la requête principale en parallèle ; ses widgets et
`is_filtered()` voient encore ces noms.

`meilifacets:check-parameters` les signale donc en **avertissement**, sans échouer. Renommer une
borne sous `query_parameters` est permis : on y perd la compatibilité des liens, et la garde
redevient absolue sur le nom libéré.

## Vérifier les noms de paramètres

```bash
ddev exec php artisan meilifacets:check-parameters
```

Compare chaque paramètre aux query vars publiques de WordPress, filtre `query_vars` compris — 95 noms
sur Pluralia, dont ceux que WooCommerce déclare pour ses filtres de produits — **et** à la liste que
Varnish efface. La réponse change avec la configuration, mais aussi avec les attributs, les taxonomies
et les extensions actives : relancer la commande après en avoir ajouté.

## Ce qui n'est pas configurable

- **Les noms de champs du document** — `facets`, `card`, `terms`, `metas` : figés en énumérations.
  Ils sont un contrat entre l'indexation et le client de recherche.
- **Le nom du champ de facette** — toujours `facets.<taxonomie>`, jamais dérivé d'un libellé
  qu'un éditeur pourrait renommer.
- **L'indexation des ancêtres de catégorie** — toujours active. La désactiver viderait les
  archives de catégorie parentes.
- **`meilifacets.name`** — clé de nwidart, sans usage dans le module.
