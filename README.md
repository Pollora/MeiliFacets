# MeiliFacets

Recherche à facettes sur Meilisearch pour les projets Pollora. Le module indexe, interroge et
rend ; il ne déclare aucune route et n'intercepte aucune requête WordPress.

- **[Réglages et points d'extension](../../docs/meilifacets/configuration.md)** — tout ce qui est
  surchargeable, et ce qui ne l'est pas. La documentation vit dans `docs/meilifacets/` du projet ;
  son rapatriement dans le module est prévu au lot 7.

## Installer

```bash
composer require pollora/meilifacets
```

Le module a besoin de [MeiliScout](https://github.com/AmphiBee/meiliscout) pour l'indexation, et
de deux adresses distinctes pour le même moteur : `MEILI_HOST` pour PHP, `MEILI_PUBLIC_URL` pour
le navigateur. Les mélanger donne un listing correct au premier rendu qui échoue à chaque filtre.

```dotenv
MEILI_HOST=http://meilisearch:7700
MEILI_KEY=<clé maître, indexation>
MEILI_INDEX_NAME=posts
MEILI_PUBLIC_URL=https://exemple.test:7701
MEILI_SEARCH_KEY=<clé de recherche, navigateur>
```

## Poser un listing

Sur un projet WooCommerce, il n'y a rien à déclarer : le module fournit un listing produit. Les
composants se placent librement dans le gabarit, ils partagent une seule recherche.

```blade
<x-meilifacets::listing>
    <x-meilifacets::active-filters />
    <x-meilifacets::sort />
    <x-meilifacets::reset />
    <x-meilifacets::facets />
    <x-meilifacets::results />
    <x-meilifacets::pagination />
</x-meilifacets::listing>
```

Pour un autre contenu, déclarez une classe implémentant `Listing` : elle est découverte
automatiquement, il n'y a rien à enregistrer.

## Trois choses à savoir avant de déployer

**La clé de recherche part dans le navigateur.** Le module ne laisse donc lire que les champs
qu'il utilise — `ID` et `card`. Un projet qui a besoin d'autres champs les déclare, et `'*'`
rouvre le document entier ; voir [Ce que la clé de recherche peut lire](../../docs/meilifacets/configuration.md).

**Un nom de paramètre d'URL peut entrer en collision** avec une query var WordPress ou être effacé
par un proxy. `php artisan meilifacets:check-parameters` le vérifie avant que ça ne se voie en
production.

**Le moteur doit être en 1.12 au minimum** pour les motifs d'attributs filtrables, et en 1.50 pour
`facets: ["facets.*"]`.

## Surcharger le markup

Le module cherche ses vues dans le thème actif avant les siennes. Poser un fichier dans
`<thème>/resources/views/modules/meilifacets/components/` suffit à en remplacer une, sans rien
copier : les vues laissées de côté continuent de suivre les mises à jour du module.

## Tests

```bash
vendor/bin/phpunit --testsuite Modules   # unités PHP, sans WordPress ni moteur
npm test                                 # client de recherche navigateur
```
