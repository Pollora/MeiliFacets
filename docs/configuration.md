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

`merge_config_from()` retourne immédiatement quand la configuration est cachée — sans rien perdre, d'après la
source : `config:cache` sérialise une application déjà amorcée, fusion du module comprise
(`ConfigCacheCommand.php:92-100`, non mesuré — la commande écrirait `bootstrap/cache/config.php`). Le piège
reste le premier : le module gagne.

**La règle qui en découle** : un réglage surchargeable n'est **pas** déclaré dans
`Modules/MeiliFacets/config/config.php`. Il est lu avec son défaut dans le code —
`config('meilifacets.card.image_size', DefaultCardProjector::DEFAULT_IMAGE_SIZE)` — et documenté
ici. Le fichier de config du module ne porte que ce que le projet ne doit **pas** pouvoir
changer.

## Réglages

À poser dans `config/meilifacets.php`, à la racine du projet.

La règle du module veut qu'elles soient lues **dans un provider**, avec leur défaut, et injectées ensuite.
Sept le sont ; trois pas encore — `apply_mode` (`ApplyMode::fromConfig()`), `url_parameters` et
`query_parameters` (`UrlParameters::fromConfig()`) —, ce que `R-05` suit.

| Clé | Défaut | Sert à | Quand |
| --- | --- | --- | --- |
| `browser.url` | `''` | `BrowserConnection` — l'adresse que le navigateur joint | au rendu |
| `browser.key` | `''` | `BrowserConnection` — la clé de recherche seule | au rendu |
| `url_parameters` | `[]` | `UrlParameters` | au rendu et au filtrage |
| `query_parameters` | `sort`, `q`, `pg`, `min_price`, `max_price` | `UrlParameters` | au rendu et au filtrage |
| `card.image_size` | `medium` | `DefaultCardProjector` | à l'indexation |
| `displayed_attributes` | `[]` | `ConfiguredIndexAttributes` | à l'indexation |
| `apply_mode` | `submit` | `ProductListing` | au rendu |
| `card.eager` | `4` | `CardSettings` | au rendu |
| `engine.reachable_hits` | `1000` | `EngineLimits` | à l'indexation et au rendu |
| `engine.max_facet_values` | `1000` | `EngineLimits` | à l'indexation et au rendu (`FacetTruncated`) |

⚠️ **`browser.url` et `browser.key` sont les deux seules clés du module sans lesquelles le client ne démarre
pas.**
Le module ne lit **jamais** `MEILI_PUBLIC_URL` ni `MEILI_SEARCH_KEY` : c'est au
`config/meilifacets.php` du projet de faire le pont. Absentes ou mal formées — un schéma manquant
suffit (R-65) — `BrowserConnection::isConfigured()` répond `false`, aucun JavaScript n'est chargé,
et rien ne le dit.

**`engine.reachable_hits` est le `maxTotalHits` du moteur** : le nombre de résultats au-delà duquel
Meilisearch répond `200` **sans aucun hit**, tout en continuant d'annoncer les pages qu'il refuse de
servir. `Pagination` et son miroir `PageWindow` s'en servent pour ne jamais proposer une de ces
pages — et **le module l'écrit sur l'index**, à chaque `ensureIndexExists()`, comme les cinq
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

Un champ absent doit être **absent**, pas vide : `image()` rend `[]` plutôt qu'une chaîne vide, pour qu'un
document ne porte jamais une clé qui ne veut rien dire. `price()` aussi, pour un contenu qui n'est pas un
produit — mais un produit sans prix porte `card.price` vide, ce que rend `get_price_html()`.

## Points d'extension

Des bindings du conteneur Laravel, à poser dans le `register()` d'un provider du projet.

| Contrat | Défaut | Rôle | Remplaçable |
| --- | --- | --- | --- |
| `CardProjector` | `DeferredCardProjector` : `WooCommerceCardProjector` autour de `DefaultCardProjector` si WooCommerce est chargé **au moment de la projection** | ce que le document porte pour peindre une carte | oui, `extend` |
| `TermHierarchy` | `WordPressTermHierarchy` | remontée d'un terme vers ses ancêtres | oui, mais interne |
| `IndexAttributes` | `ConfiguredIndexAttributes`, autour de `DeferredIndexAttributes`, qui lit `WooCommerceIndexAttributes` si WooCommerce est chargé **au moment de la lecture** (`R-171`) | attributs d'index contribués | oui, `extend` — un `bind` retire les champs de prix et `displayed_attributes` |
| `FacetCounter` | `DisjunctiveFacetCounter` | comment les compteurs de facettes sont calculés | oui, `bind` — **rendu serveur seulement** (voir ci-dessous) |
| `SearchEngine` | `MeilisearchEngine` | l'envoi des recherches au moteur | oui, `bind` |
| `Listing` | `ProductListing` si WooCommerce | ce qu'un listing déclare | découverte automatique |
| `ProductFacets` | `WooCommerceFacets` — catégorie et marque | les taxonomies que la boutique parcourt | oui, `scoped` |
| `ProductSorts` | `WooCommerceSorts` — prix ↑↓, nouveautés, « Promotions » (`on_sale`, offert seulement si le listing déclare un prix) | les tris offerts | oui, `scoped` |

```php
// Pluralia, AppServiceProvider
$this->app->scoped(ProductFacets::class, CatalogueFacets::class);
```

`CardProjector` est lié par `bindIf` : **il n'est jamais obligatoire**. Un projet neuf obtient
une carte fonctionnelle — titre, lien, image, et prix formaté par WooCommerce quand il est actif
— sans écrire une ligne de PHP. Le remplacer sert à projeter la carte du thème, pas à faire
démarrer le module.

`TermHierarchy` existe pour rendre `TermAncestry` testable sans WordPress. Il est remplaçable,
mais rien ne le présente comme un point d'extension à destination des projets.

`FacetCounter` ne vaut que pour le **rendu serveur**. Il y décide seul du comptage des facettes —
lesquelles reçoivent une recherche à part, et donc lesquelles la requête principale compte
elle-même ; les bornes de prix et la recherche non filtrée ne passent pas par lui. Mais dès le premier geste du visiteur, c'est le client qui bâtit le
plan, et il rejoue la règle par défaut, écrite en dur : une facette multi-sélection dont au moins
une valeur est cochée est comptée à part, les autres sur la requête principale
(`facets/facet-query.ts`). Un compteur qui s'écarte de cette règle voit donc ses comptes remplacés
au premier clic, sans erreur ni message. Cette règle est elle-même une décision du module — « le
disjonctif n'est produit que pour le multi-sélection » — et non un détail d'implémentation
(`R-144`).

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

Toute URL portant un paramètre du listing — facette, tri, page, recherche, borne de prix —, comme toute page
`/page/N` de WordPress, sort en
`noindex, follow` : le contenu existe déjà sur le chemin nu, et les liens qu'elle porte restent
suivis. `IndexingPolicy` s'en charge par le filtre `wp_robots`, que Yoast respecte.

La règle **ne s'applique que sur une page d'archive ou de recherche** : le filtre `wp_robots` est
global, et les noms réservés le sont aussi, donc sans cette garde un lien de campagne portant `?q=`
sortirait l'accueil de l'index. Vérifié le 2026-09-06 : `/boutique` reste `index`,
`/boutique?categorie=cheveux`, `?sort=newest` et `?pg=2` passent en `noindex, follow`,
`/?q=bonjour` reste `index`.

Un paramètre étranger au module ne déclenche rien : `?utm_source=news` reste indexable.

**Pas de canonique** sur ces vues : un `noindex` et une canonique pointant ailleurs sont deux signaux
contradictoires. Le module retire celle de Yoast (`wpseo_canonical`, priorité 20) et ôte `rel="next"`/`rel="prev"`
de toute page de listing, chemin nu compris. Vérifié le 2026-09-22 : `/boutique` garde sa canonique,
`/boutique?sort=newest` n'en a plus.

## ⚠️ Le cron doit tourner, sinon l'index diverge en silence

**Exigence de déploiement, à vérifier sur chaque environnement.** Rien dans le module ni dans
MeiliScout ne prévient si elle n'est pas remplie : l'index se contente de vieillir.

Pollora **désactive WP-Cron par défaut**, sur tous les environnements — un projet peut le rallumer par
`config/wordpress.php`, `'constants' => ['disable_wp_cron' => false]` ; Pluralia ne le fait pas :

```php
// vendor/pollora/framework/src/WordPress/Bootstrap.php:336
Constant::queue('DISABLE_WP_CRON', true);
```

Sans rien mettre à la place — l'attribut `#[Schedule]` de Pollora enregistre
pourtant ses tâches avec `wp_schedule_event()` (`ScheduleDiscovery.php:342`), c'est-à-dire le cron
qu'il vient d'éteindre. Il faut donc qu'un cron **système** pique `wp-cron.php`, ou que quelqu'un
lance les évènements dus à la main.

Deux files, deux conséquences distinctes :

| File | Ce qui s'arrête sans elle |
| --- | --- |
| **WP-Cron** | l'indexation différée de MeiliScout quand elle est activée (`meiliscout_process_async_queue`) et la réindexation lancée depuis son écran d'administration (`meiliscout_process_indexation`) |
| **Action Scheduler** | les bascules de promotion WooCommerce (`wc_product_start_scheduled_sale`, `wc_product_end_scheduled_sale`, `woocommerce_scheduled_sales`) — donc les prix, donc le filtre de prix |

Action Scheduler passe d'abord par WP-Cron (`action_scheduler_run_queue`) : le cron système qui pique
`wp-cron.php` le fait tourner aussi. À défaut, il se déclenche seul, mais **uniquement sur
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
`meiliscout/meiliscout_async_indexing`, ou la variable d'environnement `MEILISCOUT_ASYNC_INDEXING`, qui gagne
sur la base). ⚠️ **Pas de constante** : `Config::get()` teste la constante en majuscules puis la lit en
minuscules (`meiliscout/src/Config/Config.php:24-26`) — une constante définie fait lever `Undefined constant`
(lu dans la source, non mesuré en requête ; `R-150`).

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
2. **`meiliscout/skip_indexing`** — le filtre que MeiliScout expose pour couper l'indexation
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

**Portée exacte.** WooCommerce ne pose la question que depuis les archives produit — boutique, recherche
produit, archive de n'importe quelle taxonomie produit —, dont il prépare la requête principale
(`WC_Query::pre_get_posts()` puis `product_query()`, `class-wc-query.php:381-452`). Sur toute autre page, y compris une page ordinaire
où un gabarit place `meilifacets:listing`, il ne filtre rien et le module n'a rien à désarmer ; sans
WooCommerce, le filtre n'est jamais appelé. Le crochet qu'il pose alors sur `posts_clauses` n'est jamais
retiré (`:588`, alors que `:499` en retire un autre) : la question revient pour chaque `WP_Query` suivante de
la page qui n'a pas `suppress_filters` (`get_posts()` l'active), avec « non » par défaut, puisque WooCommerce y
passe `$wp_query->is_main_query()`.

| Ce qui est désarmé | Ce qui ne l'est pas |
| --- | --- |
| « Filtrer par prix » natif (`min_price`, `max_price`) | le filtre par note (`rating_filter`), une `tax_query` sur les termes `rated-N` de `product_visibility` (`class-wc-query.php:937-955`) |
| la navigation par attribut (`filter_*`), **quand la table de correspondance des attributs est active** (`Filterer.php:70-72` ; active sur Pluralia) | la même navigation sans cette table : WooCommerce passe alors par une `tax_query` de la requête principale (`class-wc-query.php:915-917`), hors de ce filtre |
| | le tri, la visibilité, le stock, le terme de l'archive, la recherche |
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

**Pour un projet qui garde la boucle native de WooCommerce sur une archive** — le module ne peut pas savoir ce
que la vue rendra, la requête principale tournant avant le choix de la route —, il réarme cette archive seule,
après le module :

```php
add_filter(
    'woocommerce_enable_post_clause_filtering',
    fn (bool $enabled, \WP_Query $query): bool => $enabled || ($query->is_main_query() && $query->is_tax('product_cat', 'accessoires')),
    20,
    2,
);
```

⚠️ Pas `__return_true` : il réarmerait toutes les archives produit, et ferait passer par les clauses de prix et
d'attribut de WooCommerce chaque `WP_Query` suivante de la page dès que l'URL porte `min_price`, `max_price` ou
`filter_*`, qu'elle porte sur des produits ou non — ni `price_filter_post_clauses()` ni
`filter_by_attribute_post_clauses()` ne regardent le type de contenu.

## Le prix indexé est celui que la boutique affiche

`price.min` et `price.max` portent le prix que la carte montre — taxes comprises ou non selon « Afficher les
prix dans la boutique » —, pas le prix saisi (`D-e`, `prix.md`). WooCommerce le calcule, à l'indexation :

| Produit | Méthode de WooCommerce |
| --- | --- |
| simple, externe | `wc_get_price_to_display()` — aucun prix si le prix est vide (`D-j`) |
| variable | `get_variation_prices(true)`, premier et dernier prix : la fourchette de sa carte |
| groupé | le modèle de sa carte : `wc_get_price_to_display()` sur chaque enfant de `get_visible_children()`, un enfant sans prix sauté, puis min et max — aucun prix si aucun enfant n'en a |

**WooCommerce 9.8 au moins** : `get_visible_children()` n'existe sur un groupé que depuis cette version
(`class-wc-product-grouped.php:153-160`).

Un groupé ne compte que ses enfants **publiés**. WooCommerce y ajoute ceux que l'utilisateur connecté peut
modifier (`wc-product-functions.php:1743`), ce qui ferait indexer le prix d'un brouillon dès qu'un admin
enregistre ; le module l'évite en projetant en visiteur anonyme (voir ci-dessous). Un groupé sans aucun enfant
à prix n'est pas indexé avec un prix, quel que soit le libellé qu'un thème affiche à sa place.

Le module n'arrondit rien et garde ce que ces méthodes rendent : 49 € saisis TTC dans une boutique qui affiche
HT à 20 % sont indexés 40.833333, et `?max_price=40.83` exclut ce produit affiché 40,83 €. Les prix des
variables arrivent arrondis aux décimales de la boutique par WooCommerce lui-même
(`class-wc-product-variable-data-store-cpt.php:484`).

**Toujours en visiteur anonyme.** Pendant la construction du document, carte comprise, `AnonymousVisitor`
passe l'utilisateur courant à 0 par `wp_set_current_user()` — la fonction de WordPress, celle dont WooCommerce
se sert pour ses webhooks (`class-wc-webhook.php:426-464`) —, puis rétablit l'utilisateur d'origine, même sur
erreur. Sans cela, un groupé indexé depuis le back-office porterait le prix de ses enfants non publiés, et sa
carte l'afficherait à tous (`R-154`). En cron et en ligne de commande, la bascule ne coûte rien :
`wp_set_current_user()` sort tout de suite quand l'utilisateur est déjà 0 (`pluggable.php:31-37`). ⚠️ Cela ne
change que les **droits** : un client en session exonéré de TVA reste hors d'atteinte.

**Toujours à l'adresse de la boutique.** Pendant la construction du document, carte comprise,
`ShopTaxLocation` impose l'adresse de la boutique par le filtre natif `woocommerce_get_tax_location`, en
priorité 10. Sans lui, WooCommerce taxerait à l'adresse du client en session (`class-wc-tax.php:469-488`) — une
modification rapide faite par un admin belge indexerait la TVA belge —, et, sans client en session — cron,
écran d'admin hors AJAX ; la ligne de commande en a un, WooCommerce la traitant comme une requête de front
(`class-woocommerce.php:974-976`) —, il ne prendrait aucune taxe, sauf prix saisis TTC, adresse client par
défaut sur la boutique ou taxes calculées sur l'adresse de la boutique (`class-wc-tax.php:476-484`). Restent hors d'atteinte un client en session exonéré de TVA, pour qui
WooCommerce ne compte aucune taxe (`wc-product-functions.php:1526`, `:1548`), et un filtre plus tardif sur
`woocommerce_get_tax_location`.

**Au filtrage, aucune conversion** : `min_price` et `max_price` comparent la borne tapée au prix affiché, au
premier rendu comme dans le navigateur.

⚠️ **Un changement de taxe ne réindexe rien.** Modifier un taux, activer les taxes, ou changer « Prix saisis
avec taxe » ou « Afficher les prix dans la boutique » laisse l'index — et les cartes — à l'ancienne valeur
jusqu'à la prochaine réindexation :

```bash
ddev wp meiliscout index
```

Sans taxes, un produit simple garde le prix saisi : `is_taxable()` exige `wc_tax_enabled()`
(`abstract-wc-product.php:1851`). Un variable le reçoit arrondi aux décimales de la boutique, avec ou sans
taxes, comme plus haut. Un groupé peut changer par rapport à l'ancien calcul, qui lisait les
lignes `_price` de tous ses enfants en écartant les prix vides et nuls
(`class-wc-product-grouped-data-store-cpt.php:76-86`) : il suit désormais ses enfants visibles et garde un
enfant gratuit à 0. Sur Pluralia, les 76 produits publiés gardent exactement le même prix indexé (mesuré le
2026-09-22).

## Une seule frontière avec MeiliScout

Le module ne touche MeiliScout qu'à trois endroits, et jamais depuis sa logique métier :

| Où | Ce qu'il y prend |
| --- | --- |
| `SearchServiceProvider` (`engine()` et `browser()`) | le client de recherche et le nom de l'index |
| `MeiliScoutBridge` | les filtres d'indexation, qui sont sa raison d'être |
| `FacetedPostIndexable` | la classe qu'il étend, `PostIndexable`, et le réglage `indexed_post_types` |

`MeilisearchEngine` reçoit son client par le constructeur : il ne connaît pas `ClientFactory`. Un
projet qui voudrait un moteur de secours, un cache ou un enregistreur relie `SearchEngine` sans
toucher au reste.

## Ce que le module lit ailleurs

| Source | Clé | Effet |
| --- | --- | --- |
| Réglages MeiliScout (base de données) | `indexed_post_types` | détermine les taxonomies projetées et déclarées filtrables |
| Environnement | `MEILI_HOST`, `MEILI_KEY` | connexion PHP d'indexation, lue par MeiliScout (`ClientFactory::getClient()`) |
| Environnement | `MEILI_HOST`, `MEILI_SEARCH_KEY` | connexion PHP de recherche — le premier rendu du listing —, lue par MeiliScout (`ClientFactory::getSearchClient()`) ; à défaut, l'option `meiliscout/meili_search_key` |
| Environnement, par `config/meilifacets.php` | `MEILI_PUBLIC_URL`, `MEILI_SEARCH_KEY` | connexion du navigateur (`browser.url`, `browser.key`) |

Ni `MEILI_INDEX_NAME` ni `MEILI_MATCHING_STRATEGY` ne sont lus : l'index s'appelle `posts`, en dur dans
`PostIndexable::getIndexName()`.

⚠️ `indexed_post_types` vit **en base**, alimenté depuis l'écran d'administration de MeiliScout :
non versionné, à refaire sur chaque environnement. Les taxonomies filtrables en découlent
directement — ce qui est indexé est filtrable.

## Réglages d'index posés par le module

Écrits par `FacetedPostIndexable::getIndexSettings()`, à chaque `ensureIndexExists()`.

| Réglage | Valeur |
| --- | --- |
| `filterableAttributes` | ceux de MeiliScout, plus `facets.<taxonomie>` pour chaque taxonomie indexée, plus `metas._price`, `metas._stock_status`, `price.min`, `price.max` et `price.onsale` si WooCommerce est actif |
| `sortableAttributes` | ceux de MeiliScout, plus `price.min` et `price.max` si WooCommerce est actif |
| `faceting.sortFacetValuesBy` | `count` pour toutes les facettes |
| `pagination.maxTotalHits` | ce que `engine.reachable_hits` déclare |
| `displayedAttributes` | `ID` et `card`, plus ce qu'ajoutent `IndexAttributes::displayed()` et `displayed_attributes` — la valeur de MeiliScout (`*`) est remplacée ; `*` dans la liste rouvre tout |
| `faceting.maxValuesPerFacet` | ce que `engine.max_facet_values` déclare |

Quatre se règlent sans toucher à la classe : `filterableAttributes`, `sortableAttributes` et
`displayedAttributes` par `IndexAttributes` et `displayed_attributes`, `maxTotalHits` et `maxValuesPerFacet` par
`engine.*`. `sortFacetValuesBy` n'a aucun point d'extension : `FacetedPostIndexable` est `final`, le changer
demande de substituer sa propre sous-classe de `PostIndexable` par `meiliscout/indexables`, à une priorité plus
haute que celle du module.

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
| `meiliscout/post/document` | ajoute `facets`, `card` et `price` au document — les deux derniers à l'adresse de la boutique (`ShopTaxLocation`) |
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

Un listing qui doit imposer son mode l'écrit dans son propre `applyMode()` ; `ProductListing`, `final`, lit la
configuration.

## Déclarer un listing

Une classe qui implémente `Listing`, découverte automatiquement dans `app/` et `Modules/*/app` :
il n'y a rien à enregistrer. Elle porte son filtre de base, qui se calcule au rendu — c'est ce qui
permet à `ProductListing` d'ajouter aux clauses **le terme que le chemin porte, quelle que soit sa
taxonomie** — une catégorie, une marque, une taxonomie du projet —, tant qu'elle s'applique aux
produits. Elle porte aussi son **terme de base** : le texte que la page elle-même cherche. Sur une
recherche que WordPress a routée, `ProductListing` lit le `s` du cœur et le rend — jamais ce que le
visiteur a tapé, qui voyage dans l'état et l'emporte quand il existe. Une classe de projet qui
implémente `Listing` doit donc rendre les deux, `baseFilter()` et `baseQuery()`. Ses facettes et ses
tris, en revanche, ne sont plus écrits dans la classe : ils viennent des contrats `ProductFacets` et
`ProductSorts` ci-dessus, qu'un projet remplace sans toucher au module.

**Une facette dont le chemin épingle déjà la taxonomie n'est plus offerte** : sur `/marque/avril`,
la facette Marque ne proposerait que « Avril », que la page filtre déjà. Elle sort donc du plan de
requête — le moteur ne compte pas sa distribution et ses libellés ne sont pas lus — mais **reste
plaçable** : un gabarit qui l'appelle par son nom obtient un bloc sans valeur, que la vue masque.
La facette catégorie fait exception, et c'est ce pour quoi elle est faite : `ChildTermsFacet` la
**conserve et la restreint** aux enfants du terme courant.

Un `Facet` déclare sa taxonomie, son libellé, son mode de sélection, son ordre d'affichage, sa limite visible,
son plafond, le sort de son terme de repli et son nom. Un **`ChildTermsFacet`** en est une variante pour une
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

Trois composants : `facet` place une facette de termes, `price` le filtre de prix, `facets` prend **ce qui
reste**. Chaque déclaration se place avec le composant de son espèce.

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
déplie rien. La règle vaut pour `facet`, `facets`, `price`, `sort`, `reset`, `pagination`, `active-filters`
et `results` sans exception. Un composant posé dehors est signalé au démarrage :

```
[meilifacets] the client binds inside [data-listing] only. Move inside <x-meilifacets::listing> : facet.
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

### Présenter les valeurs d'une facette

Par défaut, les valeurs sortent en cases (ou en radios pour une facette `SelectionMode::Single`).
Une facette peut les présenter en pastilles :

```php
new Facet('pa_contenance', __('Volume'), name: ShopFacet::Volume, presentation: Presentation::Pill)
```

Le `<fieldset>` porte alors `data-presentation="pill"`, que la feuille du module habille ; l'input
natif reste dans le DOM, masqué visuellement. Une facette `Control` ne porte aucun attribut.

L'ensemble est ouvert : un thème déclare les siennes par un enum qui implémente
`Contracts\ValuePresentation` (`slug()` = valeur de `data-presentation`, `allowsSingleSelection()`),
et les habille lui-même par `[data-presentation="…"]`. Le module ne change **aucun markup** selon la
présentation — pas de vue par présentation.

Une facette `Single` sous une présentation qui ne l'autorise pas (`Pill`) lève dès sa déclaration :
un radio masqué ne se décoche pas (`R-10`).

Un gabarit surcharge ponctuellement : `presentation="pill"` ou `presentation="control"` (cas du
module), `:presentation="ThemePresentation::Swatch"` pour celle d'un thème — même garde.
`<x-meilifacets::price>` n'en a pas : le prix n'a pas de valeurs à cocher.

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

**Une vue peut ne dessiner qu'une poignée.** Le contrat n'en exige qu'une, et le client suit : la
poignée absente vaut alors le bord de la piste, jamais zéro, et le champ qui la reflète reçoit ce
bord — donc rien n'est filtré de ce côté, une borne au bord n'étant pas un filtre. Un curseur à une
seule poignée basse filtre ainsi `min_price` seul (`R-142`).

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

## Tiroir mobile et barre de filtres

Des briques, que le thème compose (architecture v2) :

```blade
<div class="flex items-start gap-x-4">
    <x-meilifacets::drawer-opener />
    <x-meilifacets::drawer class="md:flex-1">
        <x-meilifacets::sort widget="radios" collapsible />
        <x-meilifacets::facets collapsible :with-apply="false" />
        <x-slot:footer>
            <x-meilifacets::reset shape="icon" />
            <x-meilifacets::apply visible-in-drawer />
        </x-slot:footer>
    </x-meilifacets::drawer>
    <x-meilifacets::total class="ml-auto" />
</div>
```

**Mobile first.** Sans rien d'autre, un repliable (`collapsible`) est une **section d'accordéon en
ligne, pleine largeur**, séparée de la suivante par un filet ; les sections s'ouvrent
indépendamment. À partir de `48em`, il devient une pill dont le panneau **flotte** (un seul ouvert,
Échap, clic extérieur, alignement droit `data-align-end`). Le client lit ce choix dans la feuille
(`position: absolute` du panneau) : aucun seuil n'est écrit en TypeScript.

**Le tiroir** (`<x-meilifacets::drawer>`) est un conteneur ordinaire : en-tête (« Filters », bouton
« ✕ », poignée), corps (le slot), pied (slot `footer`, rendu seulement s'il est fourni, classe
`meilifacetsDrawerFooter`).
- À partir de `48em`, c'est une rangée : en-tête et poignée masqués, corps et pied alignés sur une
  ligne (`--meili-bar-gap`), « Appliquer » en fin de rangée.
- Sous `48em`, JavaScript actif, c'est un bottom sheet que l'ouvreur promeut en dialogue modal
  (`role="dialog"`, `aria-modal`, reste de la page `inert`, défilement verrouillé). Échap, « ✕ »,
  la poignée (tap), le voile, « Appliquer » et un glisser vers le bas ferment ; le focus revient à
  l'ouvreur. Échap ferme d'abord ce qu'un contrôle du tiroir tient ouvert.
- Sans JavaScript, rien n'est masqué : les filtres restent en ligne.

**Seuil.** Écrit en dur dans la feuille (`48em`, une media query ne lit pas de variable) et repris
par l'attribut `media` du tiroir (`Drawer::MOBILE`, `(width < 48em)`) ; un test vérifie que toutes
les requêtes de largeur de la feuille utilisent cette valeur. Un thème qui en veut une autre passe
`media="(width < 64em)"` **et** redéclare les blocs de la feuille à sa valeur.

**Attributs du tiroir.** `heading` (`h2` par défaut), `media` ; le sac d'attributs arrive sur le
conteneur.

**Ouvreur** (`<x-meilifacets::drawer-opener>`). « Filters » (fr « Filtres ») puis le nombre de
valeurs tenues, en pastille (crochet `active-count`, même style que le badge `selected-count`,
masqué et vidé à zéro, qui **décrit** le bouton sans entrer dans son nom). Icône par défaut
`images/filters.svg` en `<img alt="" width="14" height="14">` dans un `<span aria-hidden="true">` ;
le slot `icon` la remplace, un slot vide la retire sans laisser d'élément :

```blade
<x-meilifacets::drawer-opener><x-slot:icon><svg …></svg></x-slot:icon></x-meilifacets::drawer-opener>
<x-meilifacets::drawer-opener><x-slot:icon></x-slot:icon></x-meilifacets::drawer-opener>
```

Une icône porteuse de sens ne passe pas par ce slot, qui la cache aux lecteurs d'écran : le thème
surcharge `drawer-opener.blade.php`.

**« Appliquer (X) »** (`<x-meilifacets::apply>`). X = valeurs cochées, attente comprise (crochet
`active-count`, même pastille). Dans le tiroir modal, il le ferme. Selon le mode :
- `submit` : rendu et **visible partout** (sheet, rangée desktop), il lance la recherche ;
- `immediate` : rendu seulement avec `visible-in-drawer`, et alors **visible seulement dans le tiroir
  en sheet** (`data-only="sheet"`), où il ferme sans rechercher — chaque case a déjà cherché ;
  **absent en desktop** (masqué par la feuille dans la rangée).

Rendu, il reçoit le focus quand « Tout effacer » se masque après un clic : celui du même tiroir, ou,
pour un « Tout effacer » hors tiroir, le premier « Appliquer » visible du listing (la rangée desktop
en `submit`) ; à défaut, le titre du tiroir en sheet, sinon la racine du listing (`tabindex="-1"`
posé à ce moment-là), jamais `body`. `<x-meilifacets::facets
:with-apply="false">` cède le sien ; sans l'attribut, le groupe rend ce même composant en bloc
(`shape="block"` : « Apply filters », pleine largeur, sans compte ; `shape="pill"`, le défaut, est la
forme ci-dessus). Plusieurs « Appliquer » sur une page sont légitimes : c'est une commande, pas un contrôle
qui tient un état (la garde `placeSort()` ne vaut que pour les contrôles).

**« Tout effacer » en icône** (`<x-meilifacets::reset shape="icon" />`, `data-shape="icon"`). Bouton
rond et carré, nommé par `aria-label` (« Clear all »/« Tout effacer »), icône `images/trash.svg` en
`<img alt="" width="20" height="20" loading="lazy" fetchpriority="low" decoding="async">`. Le slot
`icon` la remplace, un slot vide la retire. Les autres formes gardent la vue texte.

**« Tout effacer » en pilule** (`<x-meilifacets::reset shape="pill" />`, `data-shape="pill"`) : la vue
texte, arrondie et paddée comme les pills des facettes (`--meili-control-inline`). Sans `shape`, le
bouton texte garde son dessin d'origine (rayon `0.25em`, padding `0.85em`) ; `shape` accepte `text`
(défaut), `pill` et `icon` (`ResetShape`).

**Tri en radios** (`<x-meilifacets::sort widget="radios" collapsible />`, C-4). Un `<fieldset>`
(crochet `sort-choices`) de radios (crochet `sort-choice`), repliable comme une facette. Choisir
trie tout de suite, dans les deux modes (`D-10`). Son déclencheur n'a jamais de compteur : un tri
est un ordre, pas un filtre. `listbox` reste le défaut ; la garde « un seul tri par page » couvre
les deux.

Le déclencheur nomme le tri en force : « Trier par : Pertinence », en deux clés : le libellé `Sort by`
et la valeur `: :choice`, ce qui suit le libellé (le français y met une espace insécable avant les
deux-points ; surchargeable par le catalogue du thème). *Depuis le 2026-09-25 (`R-178`) : l'ancienne
clé `Sort by: :choice` n'est plus lue.*
Le libellé (`.meilifacetsFacetToggleLabel`) et la valeur (`.meilifacetsSortChoice`, crochet
`sort-chosen`) sont deux `<span>` ; le séparateur est la partie du motif qui suit le libellé. Dans
la section du tiroir, la valeur est hors de vue, mais reste dans le nom accessible ; elle s'affiche
dans la pill à partir de `48em`. Le client la met à jour à chaque changement de tri, retour arrière
compris.

**Glisser pour fermer.** La feuille suit le doigt ou la souris depuis la poignée, l'en-tête ou un
corps défilé tout en haut ; vers le haut, elle s'étire en résistant (sauf sur une liste qui défile,
où le geste la fait défiler). Elle ferme au-delà d'un quart de sa hauteur, ou sur un geste rapide :
plus de 0,11 px/ms **sur les 100 dernières ms** (un glisser lent qui s'arrête n'est pas un geste
rapide). Le voile suit la progression. Rattrapée pendant son retour, elle repart d'où elle est. Un
champ, un bouton ou un contrôle qui capture le pointeur (curseur de prix) ne la tire pas. En
mouvement réduit, elle ne suit pas le doigt, mais le geste ferme toujours.

**Mouvement.** La hauteur du sheet suit son contenu (mesurée à chaque changement,
`--meili-duration-resize`, `--meili-ease-resize`). Elle est écrite en ligne **seulement tant que le
tiroir est un sheet ouvert** : retirée à la fin de la sortie et au passage du seuil, jamais écrite en
desktop, remesurée à chaque ouverture. De même pour la position et le voile d'un glisser, retirés si
le tiroir ferme ou dépasse le seuil en cours de geste ; une section s'ouvre en fondu et léger scale,
sur une durée qui suit la hauteur ajoutée (`clamp(150ms, |Δh| / 500 s, 270ms)`, sortie ×0,75,
`--meili-ease-content`). Mouvement réduit : fondus seuls, hauteur sans animation. Échap ferme sans
animation.

**Variables** sur `[data-listing]`, surchargeables : `--meili-control` (3rem), `--meili-control-min`
(plancher tactile), `--meili-control-inline` (1.5rem), `--meili-bar-gap` (0.5rem), `--meili-section-gap` (1.5rem),
`--meili-section-step` (1rem), `--meili-row-gap` (0.5rem), `--meili-drawer-gutter` (2rem),
`--meili-drawer-block` (2.5rem), `--meili-panel-min` (18rem), `--meili-panel-max` (28rem),
`--meili-panel-price` (20rem), `--meili-ease-drawer`, `--meili-duration-drawer-in` (350ms), `--meili-duration-drawer-out`
(250ms), `--meili-duration-fade` (150ms), `--meili-duration-hover` (150ms, toutes les transitions de
survol : fonds, bordures, couleurs, poignée du prix), `--meili-scrim`, `--meili-layer-drawer` (100).

**Limite.** `position: fixed` se rattache au premier ancêtre qui porte `transform`, `filter`,
`contain` ou `container-type` : un tel ancêtre autour du listing enferme le tiroir dans sa boîte.

## Vérifier les noms de paramètres

```bash
ddev exec php artisan meilifacets:check-parameters
```

Compare chaque paramètre aux query vars publiques de WordPress, filtre `query_vars` compris — 95 noms
sur Pluralia, dont ceux que WooCommerce déclare pour ses filtres de produits —, aux noms que WooCommerce lit
dans `$_GET` (`min_price`, `max_price`, `rating_filter`, `orderby`, tout `filter_*`) **et** à la liste que
Varnish efface. La réponse change avec la configuration, mais aussi avec les attributs, les taxonomies
et les extensions actives : relancer la commande après en avoir ajouté.

## Ce qui n'est pas configurable

- **Les noms de champs du document** — `facets`, `card`, `price`, `terms`, `metas` : figés en énumérations.
  Ils sont un contrat entre l'indexation et le client de recherche.
- **Le nom du champ de facette** — toujours `facets.<taxonomie>`, jamais dérivé d'un libellé
  qu'un éditeur pourrait renommer.
- **L'indexation des ancêtres de catégorie** — toujours active. La désactiver viderait les
  archives de catégorie parentes.
- **`meilifacets.name`** — clé de nwidart, sans usage dans le module.
