# MeiliFacets

Recherche à facettes sur Meilisearch pour les projets Pollora. Le module indexe, interroge et
rend ; il ne déclare aucune route et n'intercepte aucune requête WordPress.

- **[Réglages et points d'extension](docs/configuration.md)** — tout ce qui est
  surchargeable, et ce qui ne l'est pas. Le reste de la documentation est dans `docs/` :
  [architecture](docs/architecture.md), [installation](docs/installation.md),
  [décisions](docs/decisions.md), [constats techniques](docs/pieges.md),
  [découpage en lots](docs/lots.md) et le [registre de revue](docs/revue.md).

## Installer

```bash
composer require pollora/meilifacets
```

Le module a besoin de [MeiliScout](https://github.com/AmphiBee/meiliscout) pour l'indexation, et
de deux adresses distinctes pour le même moteur : celle que PHP joint, et celle que le navigateur
joint. Les mélanger donne un listing correct au premier rendu qui échoue à chaque filtre.

L'indexation est celle de MeiliScout, configurée par son environnement :

```dotenv
MEILI_HOST=http://meilisearch:7700
MEILI_KEY=<clé maître, indexation>
MEILI_INDEX_NAME=posts
```

**Le module, lui, ne lit que deux clés**, et il ne les lit pas dans l'environnement : elles se
posent dans le `config/meilifacets.php` du projet, à charge pour lui de les brancher où il veut.

```php
// config/meilifacets.php
return [
    'browser' => [
        'url' => env('MEILI_PUBLIC_URL'),   // schéma obligatoire, sinon tout est désactivé en silence
        'key' => env('MEILI_SEARCH_KEY'),   // clé de recherche seule, jamais la clé maître
    ],
];
```

Sans elles, `BrowserConnection::isConfigured()` répond `false` : la page est servie complète, mais
aucun JavaScript n'est chargé et aucun filtre ne répond. Rien ne le signale.

Le module livre une feuille de style et un client. `Modules/` étant hors du docroot, ils doivent
être copiés dans `public/` — **à rejouer à chaque déploiement** :

```bash
php artisan module:publish MeiliFacets
```

Oubliée, la publication ne casse rien en silence : les éléments que le module masque par `hidden`
réapparaissent à l'écran.

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
rouvre le document entier ; voir [Ce que la clé de recherche peut lire](docs/configuration.md).

**Un nom de paramètre d'URL peut entrer en collision** avec une query var WordPress ou être effacé
par un proxy. `php artisan meilifacets:check-parameters` le vérifie avant que ça ne se voie en
production.

**Le moteur doit être en 1.12 au minimum** pour les motifs d'attributs filtrables, et en 1.50 pour
`facets: ["facets.*"]`.

## Surcharger le markup

Le module cherche ses vues dans le thème actif avant les siennes. Poser un fichier dans
`<thème>/resources/views/modules/meilifacets/components/` suffit à en remplacer une, sans rien
copier : les vues laissées de côté continuent de suivre les mises à jour du module.

**Une seule chose n'est pas négociable : les crochets `data-meili`.** Balises, classes et styles
appartiennent au thème ; ces attributs sont ce que le client adresse. S'il en manque un, ou si la
version du contrat portée par la racine ne correspond plus à la sienne, le client **ne démarre
pas** : la page reste celle du serveur et la console nomme ce qui manque — sauf si c'est
`data-listing` qui manque sur la racine, seul cas où le client sort sans un mot, faute de savoir
qu'il y avait un listing à démarrer. Le tableau des crochets
est dans [architecture.md](docs/architecture.md).

## Tests

Depuis la racine du module, sans projet hôte ni moteur :

```bash
composer check   # formatage, Rector, tests PHP autonomes et client navigateur
composer test    # les seuls tests PHP, suite Unit
npm test         # le seul client navigateur
```

Les tests `Feature` rendent des vues Blade et demandent donc une application : ils ne passent que
depuis le projet, `vendor/bin/phpunit --testsuite Modules`.
