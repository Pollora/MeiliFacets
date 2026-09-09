# MeiliFacets — architecture

Module Pollora de recherche, filtres et suggestions sur Meilisearch, conçu pour être
réutilisable hors de Pluralia.

Documents liés : [installation.md](installation.md) · [configuration.md](configuration.md) · [lots.md](lots.md) · [pieges.md](pieges.md) · [decisions.md](decisions.md) · [revue.md](revue.md)

> Ce document ne contient **que ce qui a été validé**. Tout le reste est listé dans
> [decisions.md](decisions.md) sous « En attente de validation », et n'est pas acquis.

## Répartition avec MeiliScout

Vérifié dans son code source : MeiliScout n'a **aucune notion de facette**. Le paramètre
`facets` n'est jamais envoyé, `facetDistribution` jamais lu, et sa seule entrée de recherche
passe par le détournement de `WP_Query`. Il fournit en revanche l'extraction du contenu
WordPress et la déclaration des attributs filtrables sur l'index.

**MeiliScout indexe. MeiliFacets interroge.** Les facettes sont à construire intégralement.

## Projection des facettes

MeiliScout indexe les termes dans un seul champ `terms`, où toutes les taxonomies sont
mélangées :

```json
"terms": [
  {"term_id": 12, "name": "Chanel", "slug": "chanel", "taxonomy": "product_brand"},
  {"term_id": 45, "name": "100ml",  "slug": "100ml",  "taxonomy": "pa_contenance"}
]
```

Cette forme ne permet pas de faire des facettes. Meilisearch agrège chaque champ isolément :
demander la distribution de `terms.slug` renvoie une liste plate — marques, contenances et
catégories confondues — d'où la taxonomie a disparu. Filtrer la requête sur
`terms.taxonomy` n'aide pas : ce filtre porte sur les produits, pas sur les termes.

MeiliFacets ajoute donc un champ par taxonomie, **chaîne d'ancêtres comprise** :

```json
"facets": {
  "product_brand": ["chanel"],
  "pa_contenance": ["100ml"],
  "product_cat": ["bases-de-teint", "teint", "maquillage"]
}
```

**Les ancêtres sont indexés, pas seulement le terme assigné.** `product_cat` est profondément
hiérarchique — plus de cent termes sur trois niveaux. Sans la chaîne, un produit rangé dans
« Bases de teint » ne répondrait pas à un filtre sur « Maquillage » : la facette serait
inutilisable, mais surtout **l'archive `/categorie-produit/maquillage/` elle-même serait vide**,
son filtre de base ne remontant aucun produit de ses sous-rayons. La correction ne peut pas
vivre dans `FacetProjection`, qui est pure : `TermAncestry` la porte, et lit la hiérarchie par
un `TermHierarchy` injecté — la chaîne d'un rayon partagé n'est alors résolue qu'une fois par
réindexation.

**Mécanisme retenu : deux points d'extension, un par besoin.** Le formatage des documents et la
déclaration des attributs filtrables ne passent pas par le même chemin dans MeiliScout — les
confondre produit un index dont les réglages sont corrects et les documents vides de facettes,
sans la moindre erreur.

| Besoin | Point d'extension |
| --- | --- |
| Ajouter `facets.*` et `card` au document | filtre `meiliscout/post/document` |
| Déclarer les attributs filtrables et le tri des valeurs | `meiliscout/indexables` + `getIndexSettings()` |

**Les champs, par le filtre document.** Il est appliqué à l'intérieur même de
`PostIndexable::formatForIndexing()`, donc sur tous les chemins d'indexation — complète, temps
réel, file asynchrone. Le `terms` reçu est déjà construit : le regroupement par taxonomie est une
transformation en mémoire, sans requête WordPress supplémentaire.

```php
#[Filter('meiliscout/post/document')]
public function addFacets(array $document, WP_Post $post): array
{
    $document['facets'] = $this->groupByTaxonomy($document['terms'] ?? []);

    return $document;
}
```

**Les réglages, par l'indexable.** `Indexer` lit bien l'indexable filtré pour appeler
`updateSettings($indexable->getIndexSettings())`. Une classe qui étend celle de MeiliScout et
surcharge cette seule méthode suffit — sans toucher à `formatForIndexing()`.

**Pourquoi surcharger `formatForIndexing()` ne marcherait pas**, et c'est vérifié dans le code :
`Indexer::indexItemsBatch()` teste `$indexable instanceof PostIndexable` et délègue alors le
formatage à `PostSingleIndexer`, qui construit son propre `PostIndexable` en dur. Une sous-classe
passe ce test, et sa méthode n'est jamais appelée. Même chose côté temps réel, où
`AbstractSingleIndexer::createIndexable()` retourne `new PostIndexable()` sans filtre.

Écarté également : un index séparé, qui dupliquerait toute l'extraction du contenu.

Deux points à l'implémentation : le filtre `meiliscout/indexables` reçoit un tableau non
associatif, donc la substitution se fait par `instanceof` et non par position ; et les motifs
type `facets.*` dans les attributs filtrables datent de la 1.12, donc la liste doit être
explicite tant que la production tourne en 1.10.3.

### Quelles taxonomies sont projetées

**Toutes celles que MeiliScout indexe.** La règle tient en une phrase : ce qui est indexé est
filtrable. MeiliFacets établit la liste par le même appel que MeiliScout —
`get_object_taxonomies()` sur les post types indexés — donc les deux ne peuvent pas diverger.

Ce choix supprime une source de configuration au lieu d'en ajouter une, et il n'introduit aucun
bruit dans l'interface : être filtrable n'est pas être affiché, c'est le template qui décide ce
qu'il montre. Déclarer vingt taxonomies filtrables n'ajoute aucune facette visible.

La contrepartie est un coût côté **serveur Meilisearch** — ni le module ni MeiliScout ne paient
quoi que ce soit. Le moteur maintient une structure par attribut filtrable, ce qui se paie en
espace disque et mémoire de l'instance, en durée d'indexation, et en reconstruction à chaque
changement de `filterableAttributes`. Rien de tout cela ne ralentit la recherche : c'est au
contraire cette structure qui la rend instantanée. Un attribut filtrable inutilisé est du poids
mort au repos, jamais un ralentissement à l'exécution.

Effet indirect à connaître sur cette infrastructure : l'instance persiste par snapshots poussés
vers Cellar environ toutes les heures, donc un index plus gros produit des snapshots plus gros et
davantage de stockage accumulé.

Sur un catalogue de cosmétiques avec une dizaine de taxonomies, tout cela reste marginal. C'est
une limite à connaître, pas un obstacle — et elle se restreint plus tard si le besoin apparaît,
alors que maintenir une liste dans chaque projet serait un coût permanent.

Cette règle ne couvre que les taxonomies. Les metas ne sont filtrables que si elles ont été
cochées dans l'écran MeiliScout, et le prix WooCommerce est stocké en chaîne : un filtre par
plage et un tri numérique demandent une projection typée. Le prix, le stock et les variations
restent donc un travail à part.

## Les providers

`MeiliFacetsServiceProvider` est le point d'entrée que nwidart découvre. Il ne lie rien lui-même :
il se déclare — nom, commandes, publication des assets, découverte des listings, cascade de vues du
thème — et enregistre **un provider par couche**, calqué sur les espaces de noms du module.

| Provider | Ce qu'il lie |
| --- | --- |
| `IndexingServiceProvider` | `IndexAttributes`, `CardProjector`, `TermHierarchy` — ce que le document porte |
| `SearchServiceProvider` | `SearchEngine`, `BrowserConnection`, `EngineLimits`, `FacetCounter` — l'envoi des recherches |
| `ListingServiceProvider` | les adaptateurs de termes, `NameOrder`, `ProductFacets`, `ProductSorts`, le registre |
| `RenderingServiceProvider` | `ListingScript`, `CardSettings`, `Unavailable` — ce dont la page a besoin en plus. *Nommé ainsi et pas `ViewServiceProvider` : Laravel en charge déjà un du même nom court.* |

Les fabriques privées restent avec leurs liaisons : c'est là que les réglages sont lus, avec leur
défaut, comme la règle l'impose — jamais depuis un objet de domaine.

## Ce que la boutique déclare

Un module générique ne peut pas connaître les taxonomies d'une boutique. `pa_contenance` est à
Pluralia ; `pa_couleur` sera à quelqu'un d'autre. Deux contrats portent donc ce qui change d'un
projet à l'autre, sur le modèle de `CardProjector` :

| Contrat | Défaut du module | Ce qu'il porte |
| --- | --- | --- |
| `Contracts\ProductFacets` | `WooCommerceFacets` — catégorie et marque | les taxonomies parcourues, leur libellé, leur ordre, leurs limites |
| `Contracts\ProductSorts` | `WooCommerceSorts` — prix ↑↓ et nouveautés | les tris offerts |

Les deux sont liés par **`scopedIf`** : un projet qui ne dit rien obtient un listing qui marche,
un projet qui lie le sien gagne. Séparés exprès — donner la main sur les facettes sans obliger à
redéclarer les tris. Pluralia y branche `App\Cms\Products\CatalogueFacets`, qui ajoute la
contenance aux deux facettes du module.

⚠️ Les deux liaisons ne sont **pas** gardées sur WooCommerce, contrairement à `IndexAttributes` et
`CardProjector`. Ce n'est pas un oubli : `register()` s'exécute **avant** que WordPress ne charge
ses extensions — mesuré le 2026-09-08, `function_exists('wc_get_product')` y vaut `false` — donc un
`if` autour de la liaison choisirait toujours la mauvaise branche. C'est pour cette raison que les
deux voisines enferment leur garde dans une closure, évaluée à la résolution. Ici aucune garde
n'est nécessaire : `ProductListing` est le seul consommateur, et il se refuse lui-même.

L'ordre des providers n'entre pas en jeu : si le projet lie en premier, le `scopedIf` du module se
tait ; s'il lie après, son `bind` remplace. **Le projet doit lier avec `bind`/`scoped`, jamais
`scopedIf`** — sinon c'est le premier arrivé qui gagne et l'ordre redevient significatif.

Ce qui **reste** dans le module, parce qu'un défaut y est juste partout : la garde WooCommerce, le
filtre de catalogue (`post_type`, `post_status`, `exclude-from-catalog`), le rayon courant lu de
`is_tax()`, et la taille de page prise à `loop_shop_per_page`. Les réécrire par projet serait
recopier quatre-vingt-dix lignes universellement correctes pour en changer trois.

Les deux implémentations mémoïsent leur liste : `facets()` est lu à sept endroits par requête,
`sorts()` à six, et rien ne les mettait en cache (`T-28`). ⚠️ Le cache retient aussi les libellés
traduits — voir `decisions.md`. En PHP-FPM le processus meurt avec la requête, donc sans effet ;
dans un worker de file, `ListingRegistry` étant un singleton, la liste survit d'un job au suivant.

⚠️ Une couture s'ouvre **quand un projet en a besoin**, pas avant — sinon on finit avec un contrat
par méthode. `baseFilter()` et `perPage()` n'en ont pas, et n'en auront que le jour où un projet
butera dessus.

## Projection de la carte

Le navigateur repeint une carte sans rien demander à WordPress, donc le document porte ce que la
carte affiche, dans un champ `card` — titre, lien, image, et **prix formaté par WooCommerce**.
Reformater un prix en JavaScript reviendrait à reprendre au plugin la devise, les promotions et
les fourchettes qu'on venait de lui laisser.

Le point d'extension n'est pas obligatoire : `DefaultCardProjector` est lié par `bindIf`, donc un
projet neuf obtient une carte qui fonctionne sans écrire une ligne de PHP. Un projet qui veut la
sienne rebinde `CardProjector` — Pluralia y branche `App\Cms\Products\ProductCard`.

Le champ n'est ni filtrable ni triable : il ne coûte rien au moteur. Mesure du 2026-09-02 sur une
page de douze produits : 33,7 Ko de JSON transférés sans restriction, 5,1 Ko avec
`attributesToRetrieve: ["ID", "card"]`.

**Limite assumée** : prix et URL d'image sont figés à l'indexation. Un prix par rôle client ou
par géolocalisation devient impossible. Une modification de meta déclenchant une réindexation,
un changement de prix se propage seul.

## Structure du module

```
Modules/MeiliFacets/
├── app/
│   ├── Console/     commandes de diagnostic
│   ├── Contracts/   points d'extension : projection de carte, lecture de hiérarchie
│   ├── Discovery/   découverte des classes portant le contrat Listing
│   ├── Enums/       contrats figés : champs de document, clés de réglage, crochets
│   ├── Http/        indexabilité et page d'indisponibilité
│   ├── Indexing/    projection des facettes et de la carte, branchement sur MeiliScout
│   ├── Listing/     le domaine : état, facettes, pagination, tri
│   ├── Providers/
│   ├── Search/      connexion, plan de requête, lecture des réponses
│   ├── Seo/         données structurées
│   ├── Support/     gardes de contexte et noms de paramètres
│   └── View/        composants Blade et description publiée au navigateur
├── resources/
│   ├── assets/css/  la seule feuille de style du module
│   ├── assets/js/   client de recherche navigateur
│   └── views/       vues Blade, toutes surchargeables par le thème
├── lang/            catalogue JSON, chargé par le provider
├── tests/Unit/      suite `Unit`, autonome : ni WordPress ni moteur
├── tests/Feature/   suite `Feature`, rend des vues, ne passe que depuis le projet
├── tests/js/        lanceur intégré de Node, happy-dom pour la couche DOM
├── config/config.php
└── CLAUDE.md        règles de travail sur ce module
```

Le module **ne déclare aucune route** : le navigateur interroge Meilisearch en direct, il n'y a
donc ni contrôleur, ni `routes/`, ni `RouteServiceProvider`. Le scaffold généré par `module:make`
en produisait douze, exposées, vers un contrôleur vide — elles ont été retirées.

Pas de `database/` non plus : le module ne crée aucune table.

## Ce qui rend la page, ce qui rend le listing

**WordPress rend la page** : `<html>`, en-tête, menus, panier, fil d'Ariane, balises SEO, pied
de page. Coût habituel, assumé.

**Meilisearch fournit le listing et les facettes.** Au premier rendu, PHP l'interroge pendant
que la page se construit. Ensuite, chaque interaction de filtrage part du navigateur.

### Ce que le compteur d'une valeur est, pour un lecteur d'écran

Le compteur est relié à sa case par `aria-describedby`, **jamais** par `aria-labelledby` ni en
faisant partie du libellé. Il change à chaque filtrage : le nommer ferait renommer, sous le
curseur, la case qu'on est en train de lire. Décrit, il est annoncé après le nom et une
actualisation n'est qu'une information de plus.

Il est aussi écrit en toutes lettres (« 14 résultats », `trans_choice`) plutôt qu'en nombre nu
collé au libellé, qu'un lecteur d'écran rendrait « 15ml 2 ».

## Transport

**Le navigateur interroge Meilisearch en direct.** Aucun proxy PHP, aucune route
`/api/`, aucun passage par WordPress sur le chemin des filtres et des suggestions.

Il n'y a pas de mode de repli par PHP : c'est le seul transport.

## Front

**Aucun framework front.** Le client est un jeu de classes ES sans dépendance, livré par le module
et chargé en `type="module"` depuis `public/modules/meilifacets/`. Pas de TypeScript.

> Corrigé le 2026-09-06. Ce document annonçait Alpine.js. Le contrat `data-meili` fait désormais
> le travail de liaison qu'Alpine devait faire : le garder imposerait au thème de conserver deux
> familles d'attributs — dont une que `Contract` ne sait pas vérifier — pour n'utiliser d'Alpine
> ni `x-for` (le markup appartient au thème) ni sa réactivité (une recherche remplace la grille
> entière). S'y ajoutaient son `MutationObserver`, qui reparcourt chaque carte clonée à chaque
> recherche, et une dépendance à `window.Alpine` que le module ne peut pas garantir : son script
> est publié à part, celui du thème est dans son bundle Vite, et rien n'assure que le nôtre
> s'exécute avant `Alpine.start()`.

**Deux familles de gestes, pas cinq.** Cocher une facette et saisir une recherche *se rassemblent*
— en mode `submit` elles attendent « Appliquer », en mode `immediate` elles partent aussitôt.
Trier, paginer et remettre à zéro ne se rassemblent pas : ce sont des ordres, ils s'appliquent sur
place **dans les deux modes**. Un visiteur qui clique « page 2 » et voit sa demande mise en attente
d'un bouton ne comprendrait pas ce qu'on lui demande.

**L'état décide de ce qui est coché, jamais le dernier clic.** Après toute mise à jour de l'état,
`FacetsView.showSelection()` repose chaque case sur ce que l'état retient — c'est ce qui rattrape
une valeur écartée par un plafond, une remise à zéro et un retour arrière du navigateur.

**Un geste qui remplace la grille peut ramener le haut du listing dans l'écran** — la pagination est
en bas de plusieurs écrans de produits, et sans ça le visiteur lit la fin d'une page dont il n'a
jamais vu le début.

**Désactivé par défaut, demandé composant par composant** :

```blade
<x-meilifacets::pagination scroll />
<x-meilifacets::sort />            {{-- ne déplace rien --}}
```

Le composant rend alors `data-meili-scroll`, et le client ne déplace la page que si le contrôle
cliqué est à l'intérieur d'un élément qui le porte. Un thème peut donc le vouloir sur la pagination
et pas sur le tri, ce que ni une clé de configuration ni un réglage global ne permettraient.

L'attribut n'entre pas dans `VERSION` : son absence est le comportement par défaut, donc un thème
écrit avant lui continue de fonctionner à l'identique.

Pour un pointeur seulement : au clavier, le focus tient déjà la place, et déplacer la page sortirait
de l'écran le bouton qu'on vient de presser.

⚠️ **Un thème à en-tête collant doit poser `scroll-margin-top` sur `[data-listing]`.** Le module ne
peut pas connaître cette hauteur, et sans la marge le haut du listing atterrit sous l'en-tête.

**Composants Blade** pour l'intégration. Un bloc Gutenberg est envisagé plus tard, pas
maintenant.

**Le composant listing porte un état de chargement, sur un seul cas.** Au clic sur un filtre, la
grille affichée reste pertinente et peut être atténuée sans être masquée. Les seuils se calibrent
sur la vitesse réelle, ils ne se devinent pas.

> Corrigé le 2026-09-04. Ce document décrivait un second cas — « au chargement d'une page portant
> déjà des filtres, la grille servie est fausse et doit être masquée ». C'était vrai avant le lot
> 3b ; depuis, le serveur applique les filtres de l'URL et la grille servie est juste. Masquer du
> contenu correct dégraderait le LCP des URLs filtrées et irait contre la première exigence du
> projet — que le contenu soit présent au premier rendu.

## URLs

Le chemin reste celui que WooCommerce produit nativement, hiérarchie de catégories comprise
(`/categorie-produit/maquillage/teint/bases-de-teint`). Le module ne réécrit rien et ne crée
aucune règle de réécriture.

Les filtres appliqués vivent en query vars, hors du chemin.

Toute URL portant **un paramètre de listing rempli** — facette, tri, recherche ou numéro de page —
est servie en `noindex, follow` : le même contenu existe déjà sur le chemin nu.

> Élargi le 2026-09-06. Le tri et la pagination étaient exclus, pour deux raisons dont une seule
> tenait. La bonne : les noms réservés sont **globaux**, donc un lien de campagne portant `?q=`
> désindexait n'importe quelle page du site, accueil compris (constaté le 2026-09-04). La mauvaise :
> « une page paginée doit rester indexable, sinon les produits qu'elle porte perdent leur seul lien
> interne » — argument déjà caduc, puisque le passage des contrôles en boutons a supprimé ces liens
> et que la découverte des fiches repose sur le sitemap Yoast.
>
> Le correctif porte donc sur la portée, pas sur la liste des paramètres : la classe — depuis
> renommée `IndexingPolicy` — ne décide
> plus que sur une page d'**archive ou de recherche**. Vérifié le 2026-09-06 — `/boutique` reste
> `index`, `/boutique?categorie=cheveux`, `?sort=newest` et `?pg=2` passent en `noindex, follow`,
> et `/?q=bonjour` comme `/?categorie=cheveux` restent `index` sur l'accueil.
>
> Limite connue : un listing posé sur une page libre, hors archive, n'est pas couvert par la garde.

Aucune canonique n'est posée vers le chemin nu — `noindex` et une canonique pointant ailleurs sont
deux signaux contradictoires.

⚠️ Ne pas compter sur le `follow` dans la durée : une page durablement `noindex` finit traitée
comme `nofollow` par Google. Ce qui garantit la découverte des fiches produits est le sitemap
Yoast, pas les liens des pages filtrées.

Le nom de chaque paramètre est déclaré en configuration — `meilifacets.url_parameters`, qui
associe une taxonomie à un nom lisible : `product_brand` devient `marque`, et l'URL affiche
`?marque=lumen,aeris&contenance=100ml`.

**Une taxonomie non déclarée prend un préfixe, jamais son nom nu.** Un nom de taxonomie est une
query var publique de WordPress : `?product_cat=visage` fait déjà filtrer WordPress lui-même
(voir [pieges.md](pieges.md)). Le préfixe évite ce double filtrage, et rend le mapping manquant
visible dans l'URL au lieu de le laisser agir en silence. Deux styles d'URL cohabitent donc :
le préfixe signale un mapping à faire, pas un état normal.

Les paramètres réservés au module — page, tri, recherche — prennent des défauts **en anglais**
(`pg`, `sort`, `q`), que le projet habille comme il habille les taxonomies. Trois d'entre eux
sont interdits sans qu'aucun test d'URL ne le révèle : `page`, `paged` et `order` sont des query
vars WordPress. La validation d'un nom se fait contre `$wp->public_query_vars` et contre la liste
que Varnish efface, dans la commande de diagnostic — jamais sur le chemin d'une requête.

**Pagination** : pages numérotées en query var, sans rechargement. Les pages au-delà de la
première cessent donc d'être indexables. Ce qui compte n'est pas l'indexation de la page 2, mais
la découvrabilité des produits qu'elle contient — assurée ici par le sitemap Yoast, qui publie
chaque fiche avec son URL propre (`product-sitemap.xml`, `noindex-product` à `false`, vérifié).
Si ce sitemap était désactivé ou tronqué, l'argument tomberait.

Ces noms sont figés en configuration plutôt que dérivés du libellé de la taxonomie : ils
partent dans des URLs indexées, et ne doivent pas changer parce qu'un éditeur a renommé un
libellé en back-office.

## Carte produit et mise à jour du DOM

Trois exigences, dans cet ordre :

1. **Au premier chargement, filtré ou non, le contenu est dans le HTML** — sinon il n'est pas
   crawlable.
2. **Le filtrage est ressenti comme instantané.**
3. **Le tout aussi rapide que possible**, sans markup mort dans la page.

> Corrigé le 2026-09-04. Ce document posait une règle — « aucun nœud n'est créé côté client » —
> qui n'a jamais été une exigence du projet et qui dessert les deux dernières : elle impose de
> rendre un emplacement pour tout ce qui pourra être montré, donc du markup vide dans une page
> qui doit être crawlée. Elle est remplacée par les trois exigences ci-dessus.

**Le critère de découpage est le focus**, pas la nature de l'élément :

| Élément | Traitement | Pourquoi |
| --- | --- | --- |
| Cartes de résultats | clonées depuis un `<template>` rendu par le même composant Blade | personne n'a le focus dans la grille pendant un filtrage |
| Valeurs de facettes | nœuds stables, masquées quand leur compte tombe à zéro | le focus est sur la case qu'on vient de cocher ; les recréer éjecte l'utilisateur clavier |
| Pagination | fenêtre de taille fixe, slots vides rendus puis remplis | le nombre de pages varie avec les filtres |

Le markup reste celui du thème dans les deux cas : le `<template>` est produit par le composant
Blade que le thème peut surcharger, donc il n'existe jamais deux sources de markup.

**Conséquence directe : ce que le client doit pouvoir mettre à jour doit exister dans la page
servie.** Le message « aucun résultat », le bouton « tout effacer », le badge de filtres actifs, la
pagination et ses slots sont rendus dans tous les cas, avec l'attribut `hidden` quand ils n'ont rien
à dire. Le nombre de slots est celui que la vue rend — `Pagination::SLOTS` en produit sept, et le
client **compte** ce qu'il trouve au lieu de le supposer, donc une vue surchargée qui en rend cinq
obtient une fenêtre de cinq. C'est le prix de la règle « le markup appartient au thème » : le client révèle,
il n'invente pas.

**Limite connue, non résolue** : une valeur de facette dont le compte est nul n'est pas dans la
distribution, donc pas dans le HTML — le client ne peut pas la faire réapparaître quand un autre
filtre est relâché. Le comptage disjonctif couvre le cas courant (relâcher une valeur de la
facette elle-même) ; le cas croisé demanderait un `<template>` par facette, à décider si le besoin
se présente.

### Boutons, pas des liens

**Un seul `<a href>` dans un listing : la carte produit**, qui mène vraiment ailleurs. Pagination
et remise à zéro sont des `<button type="button">` portant leur valeur dans l'attribut `value`
natif. La règle est « Meilisearch full AJAX » : un contrôle qui affiche un `href` annonce une
navigation qui n'a pas lieu, et un lien dont le `href` doit être réécrit après chaque recherche —
sinon il renvoie l'état d'avant — est un piège pour rien.

**Le tri est une liste déroulante du module, pas un `<select>` natif** : un `<select>` ne se style
pas au-delà de sa boîte, ses options échappent au design system. Le module rend donc le motif
combobox de l'ARIA APG, en `ul`/`li` :

```html
<div class="meilifacetsSort" data-meili="sort">
  <label id="…-label" for="…-trigger">Trier par</label>
  <button id="…-trigger" role="combobox" aria-haspopup="listbox" aria-expanded="false"
          aria-controls="…-list" aria-labelledby="…-label …-trigger" data-meili="sort-trigger">
    Nouveautés
  </button>
  <ul id="…-list" role="listbox" aria-labelledby="…-label" hidden data-meili="sort-list">
    <li id="…-newest" role="option" data-value="newest" aria-selected="true"
        data-meili="sort-option">Nouveautés</li>
  </ul>
</div>
```

- `aria-labelledby` cite le label **et** le déclencheur : le nom lu est « Trier par, Nouveautés » ;
- le `<label for>` reste un vrai label — valide hors formulaire, et rendu par la vue quoi qu'il
  arrive : la feuille du module le sort de la vue sans le sortir du nom lu, un thème le remontre
  d'une règle ;
- chaque option porte un `id`, préfixé du nom du listing : `aria-activedescendant` en a besoin, et
  deux listings sur une page ne doivent pas se marcher dessus ;
- la première option, de valeur vide, est l'ordre du moteur — sans elle, aucun retour en arrière
  une fois un tri choisi ;
- le module pose l'apparence par défaut de ce contrôle — bordure, panneau solidaire, coche,
  survol —, sans famille de police, sans taille absolue et sans couleur de marque ; le thème la
  remplace ou la désinscrit. Pour les cartes, l'apparence reste entièrement au thème.

Le clavier — ouverture, flèches, `Home`/`End`, `Entrée`, `Espace`, `Échap`, `Tab`, saisie au vol —
est livré depuis le lot 3c-2, avec `aria-activedescendant` et le focus qui ne quitte jamais le
bouton.

Trois conséquences, toutes assumées :

- l'URL reste la source de l'état, mais elle est écrite par `history.pushState`, plus par le
  navigateur qui suit un lien. `ListingUrls` a donc disparu : il n'existe plus qu'une seule
  implémentation de la construction d'URL, côté client, au lieu de deux à tenir alignées ;
- sans JavaScript, le listing servi est complet et lisible mais **inerte**. C'était déjà le cas
  des facettes, qui n'ont pas de formulaire depuis le lot 3b : les liens de tri et de pagination
  étaient les seules commandes à moitié fonctionnelles, ce qui était plus déroutant qu'utile ;
- aucune URL de tri ni de page n'est crawlable. C'est un gain sur le tri (autant de doublons en
  moins) et sans effet sur les fiches produits, découvertes par le sitemap.

### Ordre des valeurs d'une facette

Deux besoins distincts, longtemps confondus dans un seul réglage :

- **quelles valeurs remontent** — c'est le moteur qui tranche, en comptant. `sortFacetValuesBy`
  reste à `count` : sur deux cents marques, un tri alphabétique côté moteur ne ferait remonter que
  celles qui commencent par A, et le plafond de trente couperait le reste ;
- **dans quel ordre on les lit** — c'est la facette qui le déclare, de deux façons.
  `DisplayOrder` porte les deux ordres qui n'ont besoin de rien : `Count` (le défaut, pour une
  longue traîne) et `Declared`. Tout ordre qui a besoin d'un collaborateur est un
  `Contracts\ValueOrder` — le module en livre un, `NameOrder`, et **un projet peut fournir le
  sien**.

`NameOrder` range les libellés **comme la langue du site le fait** : `Collator` d'ICU, construit sur
`get_locale()` avec `NUMERIC_COLLATION`, donc `Démaquillants` avant `Diffuseurs` et `9ml` avant
`10ml`. Sans `ext-intl` — déclarée en `suggest`, jamais en `require` — il retombe sur
`strnatcasecmp`, qui range les accentués après tout l'ASCII. C'est pour cette raison qu'il est un
`ValueOrder` et non un cas d'énumération : il porte un collateur, que seule une facette qui le
demande fait construire.

`Declared` lit **l'ordre que la taxonomie porte déjà**, sans rien deviner. WooCommerce laisse la
boutique le régler par attribut — *Ordre personnalisé* (glisser-déposer), *Nom*, *Nom (numérique)*
ou *Identifiant du terme* — et l'applique à **tous** les `get_terms()` par le filtre
`get_terms_defaults` (`wc-term-functions.php`). `WordPressTermLabels` interrogeant déjà `get_terms()`,
l'ordre arrive gratuitement : il suffit de le conserver au lieu de le jeter. C'est pour ça que
`TermLabels::of()` promet ses clés **dans l'ordre de la taxonomie** — un tableau dont l'ordre est
une donnée, pas un détail.

C'est la seule réponse juste pour un attribut qui mélange les grandeurs : `pa_contenance` porte des
ml, des g, des gélules, des sachets, des patchs et un `7x2ml`. Aucune règle automatique ne range
correctement cet ensemble — lire la grandeur dans le libellé (`15ml` → 15 millilitres) revient à
deviner au rendu ce que la boutique peut énoncer une fois. Un système de facettes qui a besoin
d'une magnitude l'indexe comme **un nombre en unité canonique**, préparé à l'indexation depuis une
donnée qui connaît son unité ; il ne l'extrait jamais d'un libellé saisi à la main.

⚠️ Corollaire opérationnel : un attribut réglé sur *Ordre personnalisé* dont personne n'a glissé
les termes retombe sur l'ordre alphabétique. `Declared` ne remplace pas la décision, il la sert.

**Le plafond et le repli répondent à deux maîtres.** `cap` est dépensé sur le compte : c'est le
moteur qui décide quelles valeurs survivent, et c'est juste — sur deux cents marques, on veut les
plus peuplées. `visible` est dépensé sur l'ordre déclaré : les valeurs sont **réordonnées d'abord,
repliées ensuite**, donc ce que le visiteur lit est la tête de l'ordre que la facette a demandé.
Une valeur que l'URL tient échappe au repli où qu'elle tombe (`R-86`).

Replier sur le compte, comme le module l'a fait jusqu'au 2026-09-08, rendait invisible l'ordre
qu'on venait de déclarer — et surtout, le client ne pouvait pas reproduire cette règle : il ne
connaît que l'ordre du DOM, pas le rang moteur. Les deux replis divergeaient donc dès que l'ordre
n'était pas `Count`, et la première recherche remplaçait la liste par une autre (`R-83`, `R-85`).

**Le client le décide à nouveau à chaque réponse**, sur les compteurs qui viennent d'arriver — sans
quoi la première recherche révélait tout ce que le moteur comptait encore. Une valeur se lit quand
**le visiteur la tient**, ou quand elle a des résultats **et** que le repli a encore de la place
pour elle (`R-86`). Une facette dont plus rien ne se lit est masquée en entier, bloc et légende
compris, comme le serveur le fait déjà quand elle n'a aucune valeur.

Le bouton `more` demande à lire une facette en entier. Il n'interroge pas le moteur : rien n'a
changé du côté des comptes. Son libellé et son `aria-expanded` suivent l'état, et il se masque
quand il n'y a plus rien à déplier.

## Contrat `data-meili`

Le client n'adresse jamais une classe : les classes appartiennent au thème et changent avec le
design. Il adresse des **crochets** `data-meili="…"`, posés par les composants Blade et
énumérés une seule fois côté PHP (`Enums\Hook`) et une seule fois côté JavaScript
(`contract.js`).

La racine du listing porte `data-meili-contract="1"`. Le client compare cette valeur à la sienne
et **refuse de démarrer** si elle diffère, ou si un crochet structurel manque : la page reste
celle du serveur, entièrement fonctionnelle, et la console nomme ce qui manque. Une surcharge de
thème périmée dégrade donc vers le rendu serveur, jamais vers une interaction à moitié morte.

| Crochet | Où | Rôle |
| --- | --- | --- |
| `results` | `<x-meilifacets::results>` | la liste que le client repeint |
| `card-template` | idem | `<template>` cloné pour chaque résultat |
| `empty` | idem | message « aucun résultat », révélé ou masqué |
| `card` | idem | un résultat |
| `url` `image` `title` `price` | `<x-meilifacets::card>` | les valeurs écrites dans une carte |
| `facets` | `<x-meilifacets::facets>` | le conteneur qui écoute les changements |
| `facet-value` | idem | une valeur, masquée quand son compte tombe à zéro |
| `input` | idem | la case ou le bouton radio qui porte la valeur |
| `count` | idem | le compte réécrit à chaque recherche |
| `apply` | idem | le bouton « appliquer », en mode `submit` |
| `pagination` | `<x-meilifacets::pagination>` | la nav, masquée s'il n'y a qu'une page |
| `page` `previous` `next` | idem | les sept slots de la fenêtre et les deux flèches |
| `sort` | `<x-meilifacets::sort>` | le conteneur du tri |
| `sort-trigger` | idem | le bouton qui ouvre la liste et affiche le tri courant |
| `sort-list` | idem | la `listbox`, masquée à la fermeture |
| `sort-option` | idem | une option, sa clé dans `data-value` |
| `facet` | `<x-meilifacets::facet>` | un bloc de facette. **Le crochet va sur l'élément le plus extérieur** : c'est celui-là que le client masque quand la facette n'a plus rien à montrer, donc un thème qui enrobe le déplace avec lui |
| `more` | idem | le bouton qui lit la facette en entier |
| `reset` | `<x-meilifacets::reset>` | le bouton « tout effacer » |
| `active-filters` | `<x-meilifacets::active-filters>` | le compteur de filtres actifs |

**Ce qui est exigé et ce qui est toléré.** Le refus ne peut porter que sur ce que le thème
contrôle, jamais sur ce que la donnée décide :

- toujours exigés : `results`, `card-template`, `empty`, et à l'intérieur du template `card`,
  `url`, `image`, `title`, `price` ;
- exigés dès que leur hôte est rendu : `input` dans une `facet-value`, `more` dans un `facet`,
  `page`/`previous`/`next` dans une `pagination`,
  `sort-trigger`/`sort-list`/`sort-option` dans un `sort` ;
- optionnels : tout le reste. Un thème peut légitimement ne pas afficher de facettes, de tri ou
  de compteurs — et un listing sans résultat ne rend aucune `facet-value`. **Un bloc `facet` vide
  n'est donc pas une infraction** (`R-84`) : une catégorie feuille et une URL filtrée sans
  résultat en produisent tous deux, et le client refusait alors de démarrer.

**Trois exigences ne sont pas des crochets, et le contrat ne les voit donc pas.** Une vue surchargée
qui les oublie casse le client sans qu'aucune infraction ne soit signalée :

| Ce qu'une vue doit rendre | Ce qui casse sinon |
| --- | --- |
| `data-listing="<nom>"` sur la racine | le client ne trouve aucun listing et sort **sans un mot** — le seul démarrage raté silencieux |
| `name="<paramètre d'URL de la taxonomie>"` sur l'`<input>` d'une facette | `FacetsView` ne sait retrouver la taxonomie que par ce nom : les cases deviennent inertes |
| un élément racine unique dans le `<template>` de carte | le clonage rend `undefined` et la grille lève à chaque recherche |

Ajouter, renommer ou retirer un crochet **incrémente `Contract::VERSION`** des deux côtés.

**Ce que la version protège, exactement.** Les deux nombres viennent du module — le serveur écrit
`Contract::version()`, le client compare à sa constante — donc un incrément les déplace ensemble et
la comparaison continue de réussir. Elle ne détecte donc pas un thème périmé, mais **un client
périmé face à un serveur à jour** : le navigateur qui garde un ancien `contract.js` en cache (R-70)
annonce l'ancien numéro, ne le retrouve pas, et **refuse de démarrer** au lieu de chercher des
crochets que son code ignore. C'est le seul cas, et il suffit à justifier la règle.

**Ce qu'elle ne protège pas** : un thème qui a surchargé une vue et n'a pas suivi. La racine émet
toujours la version du module, la comparaison passe, et il manque un crochet en silence — sauf si
une règle de `contract.js` l'exige *à l'intérieur d'un hôte rendu*. C'est pourquoi `facet` exige
`facet-value` **et** `more` : un bloc qui replie des valeurs sans offrir de les déplier est R-46,
réintroduit.

`ContractParityTest` fait le reste : il compare chaque crochet que le client adresse, les deux
attributs du contrat, le préfixe de champ, le séparateur de valeurs, la borne de recherche et la
première page.

Les valeurs que le client doit lire voyagent dans des attributs natifs quand il en existe un —
`value` sur les boutons de pagination, `data-value` sur une option de tri, `value`/`checked` sur
une case de facette.

Trois marques déplacées à chaque recherche : `aria-current="page"` sur le bouton de la page lue et
`aria-selected` sur les options de tri, toutes deux **rendues par les vues** puis entretenues par le
client ; et `data-active` sur l'option de tri que le clavier désigne, **écrite par le seul client**
— elle n'a pas d'équivalent ARIA sur l'option, `aria-activedescendant` étant porté par le bouton.
Aucune n'entre dans `VERSION` : le contrat porte sur les crochets qu'un thème doit rendre, pas sur
les attributs que le client entretient.

Le client garantit aussi un `id` sur chaque option de tri, faute de quoi `aria-activedescendant`
ne désignerait rien : une vue surchargée peut l'omettre, `Contract` ne le vérifie pas.

⚠️ En panne moteur, aucun template n'est rendu : le contrat échoue, le client ne démarre pas, la
page d'indisponibilité reste. C'est le comportement voulu, pas un effet de bord.

## Panne du moteur

Une vue Blade de repli, annonçant un problème technique, servie en `503` avec `Retry-After` et
`Cache-Control: no-store` — pour que l'erreur soit traitée comme temporaire par les moteurs de
recherche et qu'elle ne soit pas figée par le cache.

## Clé de recherche

Le navigateur s'authentifie avec une clé fixe, fournie par l'infrastructure via le submodule
Docker AmphiBee. Le module la consomme, il ne la crée pas et n'en gère pas le cycle de vie.

## Conventions

Les diagnostics et messages d'erreur sont en anglais, avec la cause et l'action corrective.
Les libellés d'interface restent traduisibles.

Pas de chaînes littérales dans le code : énumérations ou constantes pour les ensembles fermés.

## Multilingue

Le site est monolingue et le restera pour ce projet, mais l'architecture doit permettre
d'ajouter une couche multilingue sans refonte. Trois contraintes techniques constatées :
Meilisearch configure mots vides, synonymes et tolérance aux fautes **par index** ; une valeur
de facette doit rester identifiable entre traductions ; un plugin de traduction n'est pas
disponible hors du contexte WordPress complet.
