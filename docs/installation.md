# MeiliFacets — installation

Voir aussi : [architecture.md](architecture.md) · [configuration.md](configuration.md) · [lots.md](lots.md) · [pieges.md](pieges.md) · [decisions.md](decisions.md) · [revue.md](revue.md)

## 1. Service Meilisearch en local

```bash
ddev add-on get kevinquillen/ddev-meilisearch
```

**La version n'est pas épinglée** : le local suit `latest` (`decisions.md`, « Version du moteur ») ; c'est
la production, en 1.10.3, qui doit monter. Pour figer une version malgré tout :

```bash
ddev dotenv set .ddev/.env.meilisearch --meilisearch-tag vX.Y.Z
ddev restart
```

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

Dans le `.env` **à la racine du projet**. Le module ne livre pas de `.env.example` : il livre un
`config/meilifacets.php` de départ, commenté, que `php artisan vendor:publish --tag=meilifacets-config` copie
dans le projet.

```dotenv
MEILI_HOST=http://meilisearch:7700
MEILI_KEY=ddev
MEILI_INDEX_NAME=posts
MEILI_MATCHING_STRATEGY=all

MEILI_PUBLIC_URL=https://<projet>.ddev.site:7701
MEILI_SEARCH_KEY=
```

Ces noms sont ceux déjà en place sur les autres projets AmphiBee : les reprendre tels quels,
sans préfixe propre au module. MeiliScout lit `MEILI_HOST`, `MEILI_KEY` et `MEILI_SEARCH_KEY` par `getenv()`
(`Config::get()`) ; la troisième sert aussi au **premier rendu**, côté PHP (`ClientFactory::getSearchClient()`).
`MEILI_INDEX_NAME` et `MEILI_MATCHING_STRATEGY` ne sont lues par personne : l'index s'appelle toujours `posts`,
en dur dans `PostIndexable::getIndexName()`.

⚠️ **Le module ne lit pas l'environnement.** Il ne connaît que
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
désactive tout le client, sans message (R-65). Et `MEILI_SEARCH_KEY` laissée vide, comme ci-dessus, coupe les
deux côtés : aucun client dans le navigateur (`BrowserConnection::isConfigured()` faux), et un premier rendu
qui tombe sur la vue de repli, `laravel.log` recevant « No Meilisearch client. Check MEILI_HOST and
MEILI_SEARCH_KEY. »

`MEILI_SEARCH_KEY` est une clé de recherche, distincte de la clé maître `MEILI_KEY`. En production elle est
fournie par l'infrastructure (submodule Docker AmphiBee) et doit avoir un **uid figé** : les
instances persistent par snapshots horaires, et une clé créée entre deux snapshots disparaît au
redémarrage. Recréée avec le même uid, elle retrouve exactement la même valeur.

## 4. MeiliScout

MeiliScout ne se requiert pas dans le projet : le `composer.json` du module le demande
(`dev-feat/meilifacets`) et le `merge-plugin` du projet le fait remonter — y ajouter une autre branche, `dev-main`
par exemple, rendrait la contrainte insatisfiable. Reste à l'activer :

```bash
ddev wp plugin activate meiliscout
```

Le paquet déclare `"type": "wordpress-plugin"` : il s'installe dans `public/content/plugins`,
pas dans `vendor/`. La branche `feat/meilifacets` n'est pas optionnelle : elle porte le correctif
d'extensibilité (`R-39`) ; repasser à `dev-main` une fois la PR amont fusionnée (`decisions.md`, « Dépendance MeiliScout »).
Le paquet ne publie aucune version taguée : committer `composer.lock` et relire la référence verrouillée avant
toute mise à jour.

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

À rejouer **à chaque déploiement, à chaque mise à jour du module**, et après toute modification de
`Modules/MeiliFacets/resources/assets/`. `public/modules/` est ignoré par git : c'est de la sortie
publiée, pas de la source.

⚠️ **`vendor:publish --tag=meilifacets-assets` sans `--force` ne fait rien** : Laravel saute les
fichiers qui existent déjà, sans le dire. `module:publish`, lui, écrase — vérifié le 2026-09-15.

### Deux pannes, et la seconde est muette

**Publication absente** : le listing est **entièrement inerte**. `ListingScript::require()` sort
sans rien faire quand `public/modules/meilifacets/dist/listing.js` manque, donc aucun filtre ne
répond — et les éléments que le module masque par `hidden` réapparaissent. Voyant, donc trouvé vite.

**Publication périmée** : bien plus dangereux. Le navigateur reçoit l'ancienne feuille de style et
l'ancien client, qui s'exécutent sans erreur contre un HTML rendu par le nouveau code. Constaté le
2026-09-15 : le CSS servi lisait encore `var(--low, 0)` / `var(--high, 1)` alors que la vue écrivait
`--from` / `--to` ; les défauts du CSS s'appliquaient et **la barre de la fourchette se peignait
entière**. Rien dans les journaux, rien dans la console.

```bash
ddev exec php artisan meilifacets:check-assets
```

Compare chaque fichier source à sa copie publiée et sort en code 1 dès qu'une copie manque ou
est plus ancienne. **À enchaîner après la publication dans un script de déploiement, et à mettre en
intégration continue** — c'est le seul garde-fou contre la panne muette.

### Le cache de page doit être vidé aussi

Republier ne suffit pas si un cache sert une copie minifiée. Avec WP Rocket, `?ver=` suit le
`filemtime` du fichier publié, mais la version minifiée dans `content/cache/min/` garde la sienne :

```bash
rm -rf public/content/cache/min public/content/cache/wp-rocket
```

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

Conséquences de l'écart de version, tant qu'il dure : tout comportement validé en local reste à reconfirmer
sur une instance 1.10.3 avant qu'on s'appuie dessus. Les attributs filtrables sont déjà déclarés un par un, et
les facettes demandées par leur nom, sans motif `facets.*` — a priori compatibles, a priori seulement (`R-41`).
