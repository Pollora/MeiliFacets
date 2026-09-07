# MeiliFacets — installation

Voir aussi : [architecture.md](architecture.md) · [configuration.md](configuration.md) · [lots.md](lots.md) · [pieges.md](pieges.md) · [decisions.md](decisions.md) · [revue.md](revue.md)

## 1. Service Meilisearch en local

```bash
ddev add-on get kevinquillen/ddev-meilisearch
```

**Épingler la version sur celle de production**, avant toute autre chose :

```bash
ddev dotenv set .ddev/.env.meilisearch --meilisearch-tag v1.10.3
ddev restart
```

⚠️ Sans épinglage, l'add-on installe `latest`.
Vérifier la version de prod avant d'épingler.

**Versionner les fichiers de l'add-on**, sinon les autres développeurs n'auront pas le service :

```bash
git add .ddev
```

`.ddev/.gitignore` (généré et maintenu par ddev) exclut déjà tout ce qui est régénérable.

**Vérifier que le moteur répond**, depuis le conteneur web et non depuis l'hôte :

```bash
ddev exec curl -s http://meilisearch:7700/health          # {"status":"available"}
ddev exec curl -s -H "Authorization: Bearer ddev" http://meilisearch:7700/version
```

Tableau de bord : `https://<projet>.ddev.site:7701`, clé `ddev` par défaut
(`MEILI_MASTER_KEY` dans `.ddev/.env` pour la changer).

## 2. Deux adresses pour le même moteur

C'est la confusion la plus coûteuse de cette installation : le module a besoin de **deux**
adresses distinctes, et les mélanger donne un listing qui s'affiche correctement au premier
rendu puis échoue à chaque filtre.

| Qui appelle | Variable | Local | Production |
| --- | --- | --- | --- |
| PHP, depuis le conteneur web | `MEILI_HOST` | `http://meilisearch:7700` | URL interne de l'app Meilisearch |
| Le navigateur du visiteur | `MEILI_PUBLIC_URL` | `https://<projet>.ddev.site:7701` | URL publique de l'app Meilisearch |

Le conteneur web ne voit pas le port publié sur l'hôte : depuis PHP, l'adresse est toujours le
nom du service.

## 3. Variables d'environnement

Dans le `.env` **à la racine du projet** — un `.env.example` livré avec le module sert de
référence à recopier, il n'est jamais chargé par Laravel.

```dotenv
MEILI_HOST=http://meilisearch:7700
MEILI_KEY=ddev
MEILI_INDEX_NAME=posts
MEILI_MATCHING_STRATEGY=all

MEILI_PUBLIC_URL=https://<projet>.ddev.site:7701
MEILI_SEARCH_KEY=
```

Ces noms sont ceux déjà en place sur les autres projets AmphiBee : les reprendre tels quels,
sans préfixe propre au module. Les quatre premiers sont lus par MeiliScout côté PHP.

⚠️ **Les deux derniers ne sont pas lus par le module.** Il ne connaît que
`config('meilifacets.browser.url')` et `.key` : c'est le `config/meilifacets.php` du projet qui
fait le pont, et sans lui rien ne relie l'environnement au navigateur.

```php
// config/meilifacets.php
'browser' => [
    'url' => env('MEILI_PUBLIC_URL'),
    'key' => env('MEILI_SEARCH_KEY'),
],
```

⚠️ **Le schéma de `MEILI_PUBLIC_URL` est obligatoire.** `meili.example:7701` sans `https://`
désactive tout le client, sans message (R-65). Et `MEILI_SEARCH_KEY` laissée vide, comme ci-dessus,
suffit à rendre `BrowserConnection::isConfigured()` faux.

`MEILI_SEARCH_KEY` est une clé de recherche, distincte de la clé maître `MEILI_KEY`. En production elle est
fournie par l'infrastructure (submodule Docker AmphiBee) et doit avoir un **uid figé** : les
instances persistent par snapshots horaires, et une clé créée entre deux snapshots disparaît au
redémarrage. Recréée avec le même uid, elle retrouve exactement la même valeur.

## 4. MeiliScout

```bash
ddev composer require amphibee/meiliscout:dev-main
ddev wp plugin activate meiliscout
```

Le paquet déclare `"type": "wordpress-plugin"` : il s'installe dans `public/content/plugins`,
pas dans `vendor/`. La contrainte `dev-main` n'est pas optionnelle — le paquet ne publie aucune
version taguée. Committer `composer.lock` et relire la référence verrouillée avant toute mise à
jour.

## 5. Sélection des contenus

Dans l'écran d'administration de MeiliScout, cocher les post types, les taxonomies et les meta
keys à indexer.

⚠️ Cette configuration vit **en base de données**, pas dans le dépôt : elle n'est pas versionnée
et ne se propage pas d'un environnement à l'autre. Elle doit être refaite sur chaque
environnement, et toute modification doit être consignée quelque part.

## 6. Première indexation

```bash
ddev wp meiliscout index --clear
ddev wp meiliscout index --clear --chunk-size=50000   # gros catalogues, un process par tranche
```

## 7. Publier les assets du module

`Modules/` est hors du docroot : **la feuille de style et tout le client JavaScript** doivent être
copiés dans `public/`.

```bash
ddev exec php artisan module:publish MeiliFacets
```

À rejouer **à chaque déploiement** et après toute modification de
`Modules/MeiliFacets/resources/assets/`. `public/modules/` est ignoré par git : c'est de la sortie
publiée, pas de la source.

Oubliée, la publication rend le listing **entièrement inerte** : `ListingScript::require()` sort
sans rien faire quand `public/modules/meilifacets/js/listing-page.js` est absent, donc aucun filtre
ne répond — et les éléments que le module masque par `hidden` réapparaissent à l'écran.

⚠️ **En local, le site se consulte en `https`.** Les assets sont inscrits avec le schéma déclaré
par `home`/`siteurl` ; une page ouverte en `http` voit ses propres scripts comme une autre origine
et le navigateur les refuse — le bundle du thème compris, donc le site entier sans JavaScript
(R-68).

## 8. Vérifier

```bash
ddev exec curl -s -H "Authorization: Bearer ddev" http://meilisearch:7700/stats
```

`{"indexes":{}}` signifie que le moteur tourne **sans aucune donnée**. C'est le signal le plus
utile de toute la chaîne, et rien d'autre ne le remonte : un moteur vide répond `200 OK` à tout
le reste.

## État au 6 septembre 2026

| Étape | État |
| --- | --- |
| Add-on ddev installé, service démarré et répondant | fait |
| Variables d'environnement | fait |
| Version épinglée sur celle de production | **non épinglé, décision assumée** — le local tourne en 1.53.1, la production en 1.10.3 |
| Fichiers de l'add-on versionnés | fait — `.ddev/docker-compose.meilisearch.yaml` et `.ddev/addon-metadata/meilisearch/` suivis |
| MeiliScout installé | fait — via la dépendance du module, pas du projet |
| Sélection des contenus | fait |
| Première indexation | fait — 102 documents dans `posts`, dont 76 produits publiés |
| Clé de recherche | créée, uid `37d11b08-9f5e-4529-b006-7666b4e4537e` — à conserver pour la recréer à l'identique |

Conséquences de l'écart de version, tant qu'il dure : les motifs type `facets.*` dans les
attributs filtrables datent de la 1.12 et ne fonctionneront pas en production — lister les
attributs explicitement. Et tout comportement validé en local reste à reconfirmer sur une
instance 1.10.3 avant qu'on s'appuie dessus.
