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

| Clé | Défaut | Lu par | Quand | Surchargeable |
| --- | --- | --- | --- | --- |
| `url_parameters` | `[]` | `UrlParameters` | au rendu et au filtrage | oui |
| `query_parameters` | `sort`, `q`, `pg` | `UrlParameters` | au rendu et au filtrage | oui |
| `card.image_size` | `medium` | `DefaultCardProjector` | à l'indexation | oui |
| `displayed_attributes` | `[]` | `FacetedPostIndexable` | à l'indexation | oui |
| `apply_mode` | `submit` | `ProductListing` | au rendu | oui |
| `card.eager` | `4` | `Results` | au rendu | oui |

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

## Ce qui est indexable

Toute URL portant un paramètre du listing — facette, tri, page, recherche — sort en
`noindex, follow` : le contenu existe déjà sur le chemin nu, et les liens qu'elle porte restent
suivis. `RobotsPolicy` s'en charge par le filtre `wp_robots`, que Yoast respecte.

La règle **ne s'applique que sur une page d'archive ou de recherche** : le filtre `wp_robots` est
global, et les noms réservés le sont aussi, donc sans cette garde un lien de campagne portant `?q=`
sortirait l'accueil de l'index. Vérifié le 2026-09-06 : `/boutique` reste `index`,
`/boutique?categorie=cheveux`, `?sort=newest` et `?pg=2` passent en `noindex, follow`,
`/?q=bonjour` reste `index`.

Un paramètre étranger au module ne déclenche rien : `?utm_source=news` reste indexable.

**Pas de canonique** vers le chemin nu en plus : un `noindex` et une canonique pointant ailleurs
sont deux signaux contradictoires.

## Une seule frontière avec MeiliScout

Le module ne touche MeiliScout qu'à deux endroits, et jamais depuis sa logique métier :

| Où | Ce qu'il y prend |
| --- | --- |
| `MeiliFacetsServiceProvider::searchEngine()` | le client de recherche et le nom de l'index |
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

Aucun point d'extension dédié : les changer demande d'étendre `FacetedPostIndexable` et de le
substituer par `meiliscout/indexables` à une priorité plus haute que celle du module.

`sortFacetValuesBy` à `count` n'est pas cosmétique — Meilisearch trie les valeurs
alphabétiquement par défaut, ce qui ferait afficher à une facette plafonnée à dix valeurs les dix
premières de l'alphabet plutôt que les dix plus représentées. C'est le réglage qui décide **quelles
valeurs remontent** ; l'ordre dans lequel elles s'affichent se déclare par facette
(`DisplayOrder::Count` ou `DisplayOrder::Name`), voir [architecture.md](architecture.md).

## Filtres consommés chez MeiliScout

| Filtre | Ce que le module y fait |
| --- | --- |
| `meiliscout/post/document` | ajoute `facets` et `card` au document |
| `meiliscout/indexables` | substitue `FacetedPostIndexable` à `PostIndexable` |

Le premier est appliqué **à l'intérieur** de `formatForIndexing()`, donc sur tous les chemins
d'indexation. Un projet peut s'y brancher à son tour ; sa priorité décide de l'ordre.

## Quand la recherche part

`apply_mode` décide si cocher une case cherche aussitôt ou attend une validation :

| Valeur | Ce qui se passe | Pour qui |
| --- | --- | --- |
| `submit` (défaut) | un bouton « Appliquer les filtres », une recherche par validation | un gros catalogue, où chaque case cochée coûterait une recherche |
| `immediate` | pas de bouton, une recherche par case cochée | un catalogue modeste, où la réponse immédiate vaut le coût |

Le mode voyage dans le markup : `data-apply="submit"` ou `"immediate"` sur le bloc de facettes.
**Aucun des deux n'est branché en 3b** — les cases sont rendues cochées d'après l'URL, rien ne les
soumet. C'est le lot 3c qui lira cet attribut, sans que le markup change.

Il n'y a délibérément pas de `<form method="get">` : un formulaire GET ne sait produire que
`marque[]=a&marque[]=b`, ce qui donnerait une seconde URL — et une seconde entrée de cache Varnish
— pour un état que `?marque=a,b` décrit déjà.

Un listing qui doit imposer son mode surcharge `applyMode()` au lieu de lire la configuration.

## Déclarer un listing

Une classe qui implémente `Listing`, découverte automatiquement dans `app/` et `Modules/*/app` :
il n'y a rien à enregistrer. Elle porte ses facettes, ses tris et son filtre de base, lequel se
calcule au rendu — c'est ce qui permet à `ProductListing` de retirer la facette catégorie sur une
archive de catégorie, où le rayon est déjà porté par le chemin.

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

## Vérifier les noms de paramètres

```bash
ddev exec php artisan meilifacets:check-parameters
```

Compare chaque paramètre aux 74 query vars publiques de WordPress **et** à la liste que Varnish
efface. La commande ne tourne jamais sur une requête : la réponse ne change qu'avec la
configuration.

## Ce qui n'est pas configurable

- **Les noms de champs du document** — `facets`, `card`, `terms`, `metas` : figés en énumérations.
  Ils sont un contrat entre l'indexation et le client de recherche.
- **Le nom du champ de facette** — toujours `facets.<taxonomie>`, jamais dérivé d'un libellé
  qu'un éditeur pourrait renommer.
- **L'indexation des ancêtres de catégorie** — toujours active. La désactiver viderait les
  archives de catégorie parentes.
- **`meilifacets.name`** — clé de nwidart, sans usage dans le module.
