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
│   ├── Contracts/   points d'extension : projection de carte, lecture de hiérarchie
│   ├── Enums/       contrats figés : champs de document, clés de réglage, metas produit
│   ├── Indexing/    projection des facettes et de la carte, branchement sur MeiliScout
│   ├── Providers/
│   └── Support/     gardes de contexte et lecture de configuration
├── resources/assets/js/   client de recherche navigateur
├── tests/Unit/            PHPUnit, dans la testsuite `Modules` du projet
├── tests/js/              lanceur intégré de Node, aucune dépendance
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
> Le correctif porte donc sur la portée, pas sur la liste des paramètres : `RobotsPolicy` ne décide
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
servie.** Le message « aucun résultat », le lien « tout effacer », le badge de filtres actifs, la
pagination et ses sept slots sont rendus dans tous les cas, avec l'attribut `hidden` quand ils
n'ont rien à dire. C'est le prix de la règle « le markup appartient au thème » : le client révèle,
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
- le `<label for>` reste un vrai label — valide hors formulaire, il rend le libellé cliquable ;
- chaque option porte un `id`, préfixé du nom du listing : `aria-activedescendant` en a besoin, et
  deux listings sur une page ne doivent pas se marcher dessus ;
- la première option, de valeur vide, est l'ordre du moteur — sans elle, aucun retour en arrière
  une fois un tri choisi ;
- le module ne pose **aucun style** : ni positionnement, ni liste sans puces. Comme pour les
  cartes et les facettes, l'apparence est au thème.

Le clavier (ouverture, flèches, `Home`/`End`, `Escape`, saisie au vol) est du ressort du client,
donc du point 2 du lot 3c.

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
- **dans quel ordre on les lit** — c'est la facette qui le déclare, via `DisplayOrder` :
  `Count` (le défaut, pour une longue traîne) ou `Name`, un tri naturel où « 10ml » précède
  « 500ml » au lieu de le suivre.

Le repli au-delà de dix valeurs est décidé **sur le compte**, avant réordonnancement : on affiche
les dix mieux comptées, rangées par nom. Déplier insère les suivantes à leur place alphabétique.

⚠️ `Name` trie des libellés, pas des grandeurs. Une facette qui mélange les unités
(`4g`, `5ml`, `30 sachets`) restera mélangée. Y répondre demanderait de suivre l'ordre défini par
l'éditeur dans l'admin WooCommerce (glisser-déposer, stocké en `term_meta`) — non implémenté, et
sans objet aujourd'hui puisque aucun ordre n'y est défini (13 termes, tous à zéro).

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
| `reset` | `<x-meilifacets::reset>` | le lien « tout effacer » |
| `active-filters` | `<x-meilifacets::active-filters>` | le compteur de filtres actifs |

**Ce qui est exigé et ce qui est toléré.** Le refus ne peut porter que sur ce que le thème
contrôle, jamais sur ce que la donnée décide :

- toujours exigés : `results`, `card-template`, `empty`, et à l'intérieur du template `card`,
  `url`, `image`, `title`, `price` ;
- exigés dès que leur hôte est rendu : `input` dans une `facet-value`, `page`/`previous`/`next`
  dans une `pagination` ;
- optionnels : tout le reste. Un thème peut légitimement ne pas afficher de facettes, de tri ou
  de compteurs — et un listing sans résultat ne rend aucune `facet-value`.

Ajouter, renommer ou retirer un crochet **incrémente `Contract::VERSION`** des deux côtés. C'est
le seul mécanisme qui empêche les deux listes de diverger en silence. Le compteur ne bouge qu'à
partir du moment où un client le lit : tant que le lot 3c n'est pas fini, la version reste à 1.

Les valeurs que le client doit lire voyagent dans des attributs natifs quand il en existe un —
`value` sur les boutons de pagination, `data-value` sur une option de tri, `value`/`checked` sur
une case de facette.

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
