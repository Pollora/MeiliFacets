# MeiliFacets — revue de projet

Voir aussi : [installation.md](installation.md) · [architecture.md](architecture.md) · [configuration.md](configuration.md) · [lots.md](lots.md) · [pieges.md](pieges.md) · [decisions.md](decisions.md)

Registre de revue. Chaque entrée porte un identifiant stable — **R** constat, **Q** question
ouverte, **T** tâche, **I** idée — une date d'ouverture et un état. **Une entrée ne se supprime
pas : elle se ferme**, avec la date et la raison. C'est ce qui permet de reprendre point par point
sans reperdre le fil.

| Gravité | Sens |
| --- | --- |
| 🔴 | bloque la mise en production ou rend une promesse du module fausse |
| 🟠 | défaut réel, corrigeable sans tout reprendre |
| 🟡 | à surveiller, ou dette assumée à réexaminer |
| ⚪ | cosmétique, résidu |

| État | Sens |
| --- | --- |
| ouvert | rien de décidé |
| à trancher | attend une décision (voir la question liée) |
| accepté | on assume, avec la raison écrite |
| fermé | corrigé ou sans objet |

**Vérifié** signale ce qui a été mesuré pendant la revue du 2026-09-06, avec le moyen. Le reste est
une lecture de code, signalée comme telle.

---

## Synthèse au 2026-09-06

Le socle est solide et le niveau d'exigence du code est réel : typage complet, unités pures et
testables, séparation indexation / recherche / rendu qui tient, 121 tests PHP et 49 tests Node qui
passent (vérifié : `vendor/bin/phpunit --testsuite Modules`, `npm test`).

Trois écarts expliquent l'impression de dispersion.

**1. Le module publie une bibliothèque, pas un comportement.** Sept fichiers ES sont livrés dans
`public/modules/meilifacets/js/`, aucun n'est chargé par une page — vérifié sur `/boutique`, seule
la feuille de style est inscrite. `Contract` n'est instancié nulle part, `Listing.listenToHistory()` (alors `onBack()`) n'est
appelé par personne, aucun PHP ne transmet au navigateur ni la connexion (`MEILI_PUBLIC_URL`,
`MEILI_SEARCH_KEY`) ni la description du listing qu'attendent `ListingQuery` et `ListingUrl`. Le
lot 3c a produit les pièces sans jamais produire l'assemblage, et le document `lots.md` le dit
lui-même (« écrit, jamais exécuté »).

**2. Le socle serveur a des défauts qu'aucun client ne rattrapera.** Une facette mono-sélection est
une porte à sens unique (R-10, vérifié en HTTP), la facette catégorie est plate sur une taxonomie à
trois niveaux (R-11, vérifié), les valeurs repliées n'ont aucun bouton pour se déplier (R-46), et
rien ne réindexe quand un terme change de nom, de slug ou de parent (R-12). Ce sont des questions
de conception, pas de JavaScript.

**3. La documentation a pris de l'avance sur le code.** 1 683 lignes réparties sur six fichiers,
plus longues que le code applicatif, hors du module, et déjà fausses sur au moins quatre points
(R-36). Écrire la décision avant de la vérifier a produit un corpus qu'il faut désormais auditer
comme du code.

Un point d'attention qui n'est écrit nulle part : **le transport direct navigateur → moteur rend
cosmétique tout filtre de sécurité posé côté serveur** (R-28). `post_status = "publish"` sera une
chaîne dans une requête que le visiteur contrôle.

Aucun de ces points ne remet en cause l'architecture. Le découpage en sept lots reste bon ; c'est
l'ordre à l'intérieur du lot 3 qui a été pris à l'envers — le contrat de liaison a été livré avant
que le rendu qu'il contractualise soit juste.

---

## 0. Décisions prises pendant la revue — 2026-09-06

Quatre réponses données en séance le 2026-09-06, six prises depuis (D-05 à D-10). Elles ferment ou
réorientent les questions citées.

### D-01 — Module du framework Pollora, en construction ; Pluralia est le banc d'essai

*Répond à Q-01, oriente Q-04, Q-07, Q-13, Q-14.*

MeiliFacets est un **module Pollora générique**, écrit pour de futurs projets. Pluralia sert de
terrain d'essai à la conception, pas de destinataire final. La règle de partage est confirmée et
précisée :

- **le module porte le fonctionnement** — indexation, recherche, état, contrat, comportement ;
- **le thème porte l'apparence**, et chaque vue de facette doit rester surchargeable ;
- **le module livre malgré tout une feuille de style minimale**, limitée à ce qui ne fonctionne pas
  sans elle — le premier cas nommé étant la liste déroulante de tri.

Ce dernier point est nouveau et contredit `architecture.md`, qui écrit aujourd'hui : « le module ne
pose **aucun style** : ni positionnement, ni liste sans puces ». Précision apportée en séance : la
feuille n'est pas oubliée, elle est **en cours de production** — la revue a été demandée avant de
la produire, précisément pour repartir sur une base plus conventionnée. Voir R-53, qui porte donc
sur la règle à écrire, pas sur un manquement.

Conséquence sur Q-14 : le contrat `data-meili` **est justifié** — la surcharge par le thème est une
exigence, pas une hypothèse. La question devient « comment le vérifier sans coûter cher », pas
« faut-il le garder ».

Conséquence sur Q-07 : `ProductListing` peut rester dans le module tant qu'il est réellement
conditionnel. R-09 passe donc de « à trancher » à « à corriger ».

### D-02 — Le module vit dans son propre dépôt : `Pollora/MeiliFacets` (privé)

*Répond à Q-02, ferme partiellement R-38.*

Le dépôt imbriqué est **voulu** : il sépare le code du module de celui du projet. Vérifié —
`origin` pointe sur `git@github.com:Pollora/MeiliFacets.git`. La manière dont Pollora versionne ses
modules (paquet Composer, submodule, autre) se décidera plus tard, quand le module sera stabilisé.

Ce qui reste ouvert, et qu'il faut garder en tête : **rien ne relie aujourd'hui une révision du
projet à une révision du module**. Tant que c'est le cas, « ce qui est déployé » n'est pas une
information disponible. À rouvrir avant la première mise en production, pas avant.

### D-03 — Fondations d'abord, un point à la fois

*Répond à Q-04.*

Méthode retenue, dans les mots du projet : « préparer des fondations très solides, se focaliser
point par point, valider la fondation, puis feature par feature. Trop de choses en même temps crée
de la confusion, de la dette technique sur du code oublié, une méthode faite à moitié, non
documentée, puis oubliée. »

Traduction opérationnelle :

1. le chantier B (socle serveur juste) passe **avant** le chantier C (client) ;
2. **un point à la fois** — une entrée R ouverte, discutée, corrigée, testée, documentée, fermée,
   avant d'ouvrir la suivante ;
3. rien n'est marqué « livré » sans une observation vérifiable, comme le veut déjà `lots.md`.

C'est aussi la raison d'être de ce registre : il n'existe que pour qu'un point commencé se termine.

### D-06 — Le module se vérifie sans hôte, et assume le seul couplage global qui l'en empêchait

*Décidé le 2026-09-06, dans le cadre de T-38.*

`SortChoices` appelle `__('Relevance')`. Hors d'un hôte, cette fonction globale n'existe pas et
quatre tests tombent. Trois issues étaient possibles : sortir ces tests de la suite autonome,
injecter un vrai traducteur, ou déclarer un `__()` de repli dans le bootstrap de test.

**Retenu : le repli dans `tests/bootstrap.php`**, derrière `if (! function_exists('__'))` — c'est
la garde qu'emploie `pollora/helper-overrider` lui-même (`vendor/pollora/helper-overrider/src/helpers.php:29`),
avec sa signature exacte, `(string $key, array|string $replace = [], ?string $locale = null)`. Une
version antérieure inventait une signature à deux paramètres : corrigée.

**Aucun précédent dans le projet, vérifié le 2026-09-06.** `Modules/Wishlist` ne pouvait pas
servir de modèle : il n'a **aucun test** (`tests/Unit` et `tests/Feature` ne contiennent qu'un
`.gitkeep`), aucun outil, et son `composer.json` est resté le squelette de nwidart —
`"name": "nwidart/wishlist"`, auteur « Nicolas Widart ». Le plugin `pluralia-fulfillments`, lui,
fait le choix inverse : ses dix tests vivent dans `tests/Feature/Fulfillments/` **du projet**,
étendent `Tests\TestCase`, et tournent donc dans une application bootée où `__()` est le vrai. Il
n'a aucun outillage propre.

MeiliFacets est donc **le premier composant de ce projet à viser une vérification autonome**. Il
n'y avait pas de convention à suivre : il y en a une à poser.

Limite acceptée : le repli renvoie sa clé sans appliquer de remplacement. Aucun `__()` du module ne
passe aujourd'hui de placeholder — les pluriels passent par `View\CountLabel`, qui remplace `:count`
lui-même. Le jour où un test autonome portera sur
une chaîne à placeholder, c'est I-10 qu'il faudra ouvrir, pas étendre le repli à l'aveugle.

### D-07 — La catégorie reste une facette, mais contextuelle : `ChildTermsFacet`

> *« Le module ne sait pas que ce sont des pastilles » renversé le 2026-09-24 (`R-164`, chantier
> « barre de filtres », C-5) : la maquette de la barre demande une présentation propre à chaque
> facette, donc `Presentation` (`Control`/`Pill`) est déclarée sur la facette dans `ProductFacets` —
> voir `decisions.md`. « Ce que la maquette ferme au passage : R-47 » ne tient plus non plus : des
> pastilles retirables sont décidées le même jour (C-7). Le reste de D-07 tient.*

*Décidé et livré le 2026-09-06, sur maquette cliente.*

La maquette d'une page de catégorie produit montre, sous le titre du rayon, une bande de pastilles
portant **les sous-catégories du rayon courant** — sur « Visage » : Démaquillants, Lotions,
Gommages, Sérums… Une seule apparaît active. Suivent un panneau repliable « Affiner la sélection »
contenant les autres facettes, un compteur « Filtres appliqués : 1 », « Annuler la sélection » et
« Trier par ».

**Ce que ça tranche.** La catégorie **reste une facette** — l'option « navigation par liens » est
écartée. Mais elle cesse d'être une liste plate : ses valeurs sont **les enfants directs du terme
sur lequel on se trouve**, jamais la distribution globale. C'est ce qui rend 81 rayons utilisables
sans plafond ni ordre d'affichage : on n'en montre que le niveau courant. R-11 se referme par la
forme.

**Nom retenu : `ChildTermsFacet`** — d'après le comportement, pas d'après l'apparence. Le module ne
sait pas que ce sont des pastilles, comme il ne sait pas aujourd'hui que « Marque » est une colonne
de cases à cocher. Le mécanisme est générique : il vaut pour `category` sur des articles ou pour
la taxonomie d'un CPT, pas seulement pour `product_cat`. **Un listing ne mélange jamais les types
de contenu** : une page, un type, une taxonomie.

**Décision renversée.** `decisions.md` porte « Facette catégorie sur une archive de catégorie :
retirée — le rayon est déjà porté par le chemin », appliquée dans `ProductListing::facets()` par un
`is_tax()`. C'est l'inverse de ce qu'il faut : sur une archive de catégorie, la facette ne
disparaît pas, elle **se restreint au niveau courant**.

**Ce que la maquette ferme au passage :** R-47 — « Filtres appliqués : 1 » est bien un compteur, pas
des puces retirables, donc `<x-meilifacets::active-filters>` fait déjà ce qu'il faut. Et R-46 /
R-48 trouvent leur forme dans le panneau « Affiner la sélection ».

**Tranché ensuite le même jour :** la facette ne montre **rien** sur un terme sans enfants — le
déplacement latéral relève d'un fil d'Ariane, pas d'un contrôle qui par ailleurs restreint, et
frères et multi-sélection sont de toute façon incompatibles (on ne restreint pas à « Sérums +
Crèmes » quand on *est* dans Sérums). Elle est **multi-sélection** : « Démaquillants + Lotions » est
une demande évidente, c'est cohérent avec les autres facettes, et ça règle la désélection sans
mécanisme dédié. Et l'URL reste une **query var sur le chemin courant** — ce que le multi impose,
un chemin ne pouvant porter qu'un rayon.

Le doublon assumé : `/visage?categorie=demaquillants` sert le même contenu que
`/categorie-produit/visage/demaquillants/`. Sans effet SEO, la version filtrée étant en `noindex`.

#### Livré le 2026-09-06

`Facet` cesse d'être `final` et ouvre un seul point de variation, `within()`, qui décide des valeurs
qu'une facette peut montrer parmi celles que le moteur a renvoyées — par défaut, toutes.
`ChildTermsFacet` le surcharge et ne garde que les enfants du terme courant, lus par un contrat
`TermScope` mémoïsé par taxonomie : **une requête de termes par listing**, quel que soit le nombre
de facettes. `Facet` reste sans WordPress, donc testable.

Mesuré en HTTP :

| | Avant | Après |
| --- | --- | --- |
| `/boutique` | 30 valeurs, 10 atteignables, trois niveaux mêlés | **6**, le niveau 0, toutes atteignables |
| `/categorie-produit/cheveux` | 21 valeurs co-occurrentes, mono, radio indécochable | **5 enfants de « cheveux »**, cases à cocher |
| catégorie feuille (`ampoules-cures`) | — | **facette masquée**, 1 produit |
| multi-sélection | impossible | visage 16 → `soins-visage` 9 → `soins-visage,soleil` **10** |

Le plafond de 30 et l'ordre d'affichage cessent d'avoir de l'importance : une douzaine de frères
n'ont besoin ni de l'un ni de l'autre. **R-11 est fermé par la forme**, pas par un réglage.

115 tests dans le module, 133 dans le projet. Robots et canoniques inchangés.

### D-08 — Pas d'estimation sans mesure : on produit, on mesure ensuite

*Décidé le 2026-09-07, en réponse à Q-29.*

Aucun arbitrage de performance ne se prend sur une extrapolation. On livre, on mesure sur le
catalogue réel, on décide ensuite.

Ce que la mesure du 2026-09-07 a déjà donné, sur `/boutique` en local (76 produits) :

| | Temps |
| --- | --- |
| Le moteur seul, `multi-search` complet avec facettes | **3 ms** |
| `/boutique`, page entière | ~350 ms |
| `/`, accueil sans aucun listing | ~410 ms |

Le moteur pèse **1 %** de la page, et la page de listing n'est pas plus lente que les autres — elle
est même plus rapide que l'accueil. Le listing n'est pas un point chaud ; le coût est WordPress, les
plugins et le thème.

Conséquence directe sur le reste-à-faire : le client JavaScript ne rendra pas le premier rendu plus
rapide. Il transformera **un rechargement de 350 ms en un appel moteur de 3 ms** sur chaque filtre,
chaque tri, chaque page. C'est là qu'est le facteur cent.

**Q-29 est fermée** par cette décision, et **Q-30** (alléger la requête principale de l'archive)
avec elle : ce chiffre est local, sur 76 produits, sans cache d'opcode représentatif. Il ne dit rien
de la production. Les deux se rouvriront quand un catalogue réel sera disponible en local.

### D-09 — Le client est écrit en JavaScript moderne, vérifié par des outils

> *« Pas de TypeScript » renversé le 2026-09-16 (`R-132`) : le client est en TypeScript, limité à la
> syntaxe effaçable, et livré empaqueté — voir `decisions.md`. Le reste de D-09 tient.*

*Décidé et appliqué le 2026-09-07, lot 3c-1.*

Les sept fichiers livrés au lot 2 n'avaient jamais été recettés. Revus, ils tenaient sur le style —
champs privés, injection par constructeur, `AbortController` — et pas sur la conception. Ce qui a
changé :

| | Avant | Après |
| --- | --- | --- |
| État | public, muté sur place | `ListingState` **immuable**, chaque geste rend un état neuf |
| Abonnement | un rappel `onBack(repaint)` | `Listing extends EventTarget`, événements `change` / `results` / `failed` |
| Annulation | `setTimeout` + `clearTimeout` à la main | `AbortSignal.any([controller, AbortSignal.timeout()])` |
| Fin de recherche | `null` pour « annulé » **et** « rien » | `SearchSuperseded` et `SearchError`, distinctes |
| Typage | aucun | JSDoc + `jsconfig.json` (`checkJs`), le contrat PHP↔JS en `@typedef` |
| Outillage | aucun | **ESLint**, dans `composer check` |

**Pas de TypeScript**, décision inchangée : JSDoc avec `checkJs` donne la vérification dans
l'éditeur sans build ni coût d'exécution. Une vérification en CI demanderait `typescript` en
dépendance de développement ; à rouvrir si le besoin apparaît.

**ESLint a payé immédiatement** : il a trouvé deux imports d'exécution qui n'existaient que pour le
typage — le navigateur téléchargeait des modules dont il n'avait pas besoin — que l'audit manuel
avait laissés passer.

**Le passage des données** utilise l'API officielle de WordPress 6.9,
`wp_enqueue_script_module()` et le filtre `script_module_data_{id}`. Trois avantages sur un
`type="module"` forcé : c'est la convention du cœur, les données **ne vivent pas dans une vue** que
le thème pourrait supprimer, et un module ES étant différé, le JSON est présent avant l'exécution.

### D-05 — La méthode de livraison est outillée, pas seulement écrite

*Décidé le 2026-09-06, en réponse à une constatation mesurée.*

Constat qui a déclenché la décision : la règle sur les commentaires **existait déjà** dans le
`CLAUDE.md` du module, formulée sans ambiguïté (« a comment that … justifies a design choice … is
deleted »), et elle a été enfreinte une trentaine de fois. Mesure du 2026-09-06 : 148 blocs PHPDoc
pour ~2 200 lignes applicatives, dont 91 de pur `@param`/`@return` et 57 porteurs de prose — la
majorité de ces derniers justifiant un choix de conception. Second cas : `@return list<string>`
écrit **12 fois** pour trois informations (`IndexAttributes` et ses trois implémentations).

Deux enseignements, distincts :

1. **Une règle enfreinte trente fois n'est pas une règle à réécrire, c'est une règle que personne
   ne vérifie.** Écrire mieux ne changera rien ; il faut un moment de vérification.
2. **Une règle peut aussi avoir un trou** : celle sur PHPDoc autorise les génériques de tableau
   sans dire qu'ils s'écrivent une fois, sur l'interface. Là, c'est bien le texte qu'il faut
   compléter.

Ce qui est mis en place, par fiabilité décroissante :

| Levier | Se déclenche | Où |
| --- | --- | --- |
| **Hook** — `composer check` | toujours, sans intervention | **posé** le 2026-09-06 dans `.claude/settings.json` du module : `cd "$CLAUDE_PROJECT_DIR" && composer check`, versionné parce qu'il ne porte aucun chemin machine |
| **`CLAUDE.md` du module** | chargé à chaque session | réécrit le 2026-09-06 |
| **Sous-agent `conformity`** | avant d'écrire, sur invocation | `.claude/agents/conformity.md` |
| **Sous-agent `module-review`** | avant de rendre, sur invocation | `.claude/agents/module-review.md` |

`CLAUDE.md` gagne quatre choses qu'il n'avait pas : une **passe de conformité avant d'écrire**
(la demande contredit-elle une décision ? sous quelle entrée du registre travaille-t-on ?), la
règle PHPDoc complétée, une **définition de « fini »** cochable, et une liste de ce qui ne se fait
jamais sans accord.

Écarté volontairement : un *skill* de livraison. Il aurait fait une quatrième copie des mêmes
règles, donc une quatrième à tenir en phase — exactement le défaut relevé en R-25 entre `Hook` et
`contract.js`.

Réserve à garder en tête : un sous-agent ne s'exécute que si on l'invoque, et ses constats ne
valent que s'ils sont traités. `CLAUDE.md` dit désormais qu'un constat rapporté doit être **corrigé
ou refusé par écrit** — un constat ni corrigé ni répondu est précisément le mode de défaillance que
tout ceci cherche à empêcher.

### D-04 — Le but premier est de sortir le listing du coût de WordPress

*Précise le cadrage ; laisse Q-03 ouverte, reformulée.*

Rappel du besoin d'origine, tel que redit en séance : sur un listing à gros volume, charger
WordPress à chaque requête coûte plusieurs secondes là où Meilisearch répond en quelques
millisecondes. Le module existe pour supprimer ce coût.

Ce cadrage n'est **pas** la même question que Q-03 (à qui l'on fait confiance pour construire le
filtre). Les deux sont traitées séparément : Q-03 est reformulée ci-dessous, et le cadrage lui-même
ouvre R-54 — parce qu'il n'est aujourd'hui tenu qu'à moitié.

---

### D-10 — Trier, paginer ou remettre à zéro vaut validation des filtres en attente

*Décidé le 2026-09-07, en réponse à `R-77`. Aucune ligne de code changée : c'est le texte qui suit.*

En mode `submit`, cocher une case ne cherche rien mais **pose l'état** — sinon la case ne pourrait
pas rester cochée. Tout geste qui remplace la grille part donc de cet état, et emporte les filtres
qu'on n'avait pas validés.

L'alternative aurait été de faire repartir ces gestes du dernier état **appliqué**. Elle impose de
tenir deux états, et surtout elle laisse le visiteur devant une grille qui ignore les cases qu'il
vient de cocher, avec « Appliquer » pour seul moyen de les faire coïncider. Le comportement retenu
est aussi celui de la plupart des listes à facettes.

## 1. Constats — architecture et conception

### R-01 · 🟠 · ouvert · 2026-09-06 — `QueryPlan` est une classe qui a perdu son constructeur

`QueryPlan::results($listing, $state)`, `QueryPlan::counting($listing, $state, $facet)`,
`QueryPlan::isCountedApart($facet, $state)`, `QueryPlan::facetClauses($listing, $state, $except)` :
quatre méthodes statiques qui reçoivent le même contexte à chaque appel. C'est mot pour mot ce que
le `CLAUDE.md` du module interdit — « une fonction qui prend le même contexte à chaque appel est
une méthode qui a perdu sa classe ». Un `QueryPlan` construit avec `(Listing, ListingState)`
supprime le passage de paramètres, permet de mémoïser `facets()` (voir R-07) et rend le plan
injectable.

**Toujours vrai le 2026-09-17**, relevé par les passes de `R-131` : cinq statiques désormais
(`filterQueries()`, `sortQuery()`, `results()`, `apart()`, `unfiltered()`). Sur `/boutique?marque=nord-sel`,
la revue compte `ProductListing::sorts()` sept fois par rendu et `SortQuery` construite quatre fois — en
mémoire, sans requête.

Même remarque, moins grave, pour `PageSize` (deux statiques sans état) et `FacetProjection` (une
statique pure — acceptable). `FilterExpression` est une vraie façade de fonctions pures, elle peut
rester ainsi.

### R-02 · 🟠 · ouvert · 2026-09-06 — le plan de requête circule en tableau non typé, avec des clés littérales

Entre `QueryPlan`, `FacetCounter`, `SearchEngine::multiSearch()` et
`MeilisearchEngine::toSearchQuery()`, le plan est un `array<string, mixed>` dont les clés sont des
chaînes en dur : `'q'`, `'filter'`, `'facets'`, `'sort'`, `'hitsPerPage'`, `'page'`,
`'attributesToRetrieve'`. Deux règles du module tombent en même temps : « pas de chaînes
littérales » et « typage complet ». `MeilisearchEngine::apply()` en est le symptôme — un `match`
sur un nom d'option, avec un `default` qui suppose `attributesToRetrieve`.

Un objet `SearchRequest` (readonly, avec un `toArray()` et un `fromArray()`) fermerait l'ensemble
et donnerait au passage la forme sérialisable dont le client JavaScript a besoin (voir I-01).

**Toujours vrai le 2026-09-17**, relevé par les passes rejouées de `R-137` : `QueryPlan::unfiltered()` écrit
une troisième fois `'q'`, `'filter'`, `'facets'`, `'hitsPerPage'` et `'page'` en littéraux, comme `results()`
et `apart()`.

### R-03 · ⚪ · **fermé le 2026-09-22** · ouvert le 2026-09-06 — `Contract` n'est pas une énumération et vit dans `app/Enums/`

`Modules\MeiliFacets\Enums\Contract` est une `final readonly class`. Elle appartient à
`app/View/` ou à un `app/Contract/` dédié, avec `Hook`.

**Fermé le 2026-09-22**, relevé par Louis (« n'est pas un enum, normal ?? »). Tranché avec lui : au lieu
de déplacer la classe, en faire **un vrai enum**. Les trois attributs sont un ensemble fermé, ce que
`CLAUDE.md` §3 met en énumération, et un déplacement vers `app/View/` aurait fait dépendre `Enums\Hook` de la
couche de rendu. Cas nommés comme leurs jumeaux du client — `Attribute` (`data-meili`), `VersionAttribute`
(`data-meili-contract`), `ScrollAttribute` (`data-meili-scroll`) — après que les passes ont relevé que
`Version`, `VERSION` et `version()` ne différaient que par la casse, et que `Contract::Hook` côtoyait l'enum
`Hook` ; `VERSION` et `version()` restent. Aucune valeur ne change, le client non plus. `ContractParityTest`
compare désormais `data-meili-scroll`, écrit des deux côtés sans que rien les confronte, et l'attribut que
`version()` pose sur la racine, qu'aucun test ne vérifiait — deux mutations tuées. Passes : le commentaire
du cas de défilement est retiré (il justifiait un choix, écrit dans `architecture.md`) ; les tests réutilisent
leurs assistants de sélecteur au lieu de le recopier.

### R-04 · 🟡 · **fermé le 2026-09-07** · ouvert le 2026-09-06 — service locator dans les composants Blade

`ListingComponent::listing()` fait `app(CurrentListing::class)` et `Results::itemList()` fait
`app(RobotsPolicy::class)`. Les composants Blade de Laravel résolvent depuis le conteneur tout
paramètre de constructeur qui n'est pas passé en attribut : l'injection est disponible, elle n'est
pas utilisée. Effet direct : ces deux composants ne se testent qu'avec une application bootée.

**Fermé le 2026-09-07** — par R-62, sans que ce constat soit mis à jour. Relevé par la passe de
conformité de la documentation : le code qu'il décrit n'existe plus.
### R-05 · 🟠 · ouvert · **rouvert le 2026-09-22** (fermé à tort le 2026-09-07) · ouvert le 2026-09-06 — la configuration est lue depuis les objets de domaine

`ApplyMode::fromConfig()`, `UrlParameters::fromConfig()`, `Results::eagerCards()`,
`ProductListing::applyMode()`, `Card` (rien) : quatre points où `config()` est appelé depuis un
objet qui n'a rien à voir avec le conteneur. Une énumération qui lit la configuration est une
dépendance globale déguisée en valeur.

Le contournement du piège nwidart (ne rien déclarer dans `config/config.php`, lire le défaut dans
le code) est juste — c'est **l'endroit** de la lecture qui est discutable. Le provider est le seul
qui devrait lire `config()`, comme il le fait déjà bien pour `DefaultCardProjector`.

**Fermé le 2026-09-07** — par R-62, sans que ce constat soit mis à jour. Relevé par la passe de
conformité de la documentation : le code qu'il décrit n'existe plus.

**Au 2026-09-22** (passe documentaire, `R-153`) : **rouvert, la fermeture était fausse.** Deux des quatre points
existent toujours : `ApplyMode::fromConfig()` (`app/Enums/ApplyMode.php:15-18`), appelé par
`ProductListing::applyMode()`, et `UrlParameters::fromConfig()` (`app/Support/UrlParameters.php:19-24`),
que `ListingServiceProvider` lie sans lire la configuration lui-même. `Results::eagerCards()` et
`Card` n'en lisent plus. C'est la règle de `CLAUDE.md` §3, que `architecture.md` énonce comme tenue.

### R-06 · 🟠 · ouvert · 2026-09-06 — le contrat `Listing` mélange déclaration et résolution

`name()`, `facets()`, `sorts()` déclarent. `baseFilter()` appelle `is_tax()` et
`get_queried_object()`, `perPage()` appelle WooCommerce, `applyMode()` lit la configuration : trois
méthodes dépendent de la requête en cours. L'instance est pourtant construite une fois au `boot()`
et conservée dans le registre.

Ça fonctionne — les méthodes sont appelées tard — mais rien dans l'interface ne dit qu'elles
doivent être idempotentes, bon marché et sûres à appeler avant que WordPress ait résolu sa requête.
Un implémenteur tiers qui mettrait un état en cache dans son constructeur se ferait piéger sans
avertissement.

Piste : séparer `Listing` (déclaratif, sans WordPress) d'un `ListingContext` résolu par requête, ou
au minimum documenter le contrat temporel dans l'interface.

### R-07 · 🟠 · **fermé le 2026-09-08** (R-88) · ouvert le 2026-09-06 — `facets()` est appelée six fois par requête, sans mémoïsation

Sites d'appel relevés en lecture, pour un seul rendu : `StateReader::facets()`,
`QueryPlan::fieldsCountedOnMain()`, `QueryPlan::facetClauses()` (une fois pour la requête
principale, plus une par facette comptée à part), `DisjunctiveFacetCounter::queries()`,
`ListingSearch::distributions()`, et la vue `facets.blade.php`. Chaque appel de
`ProductListing::facets()` refait un `is_tax()`, trois `__()` et trois `new Facet`. `sorts()` :
trois sites.

**Fermé par la couture de `R-88`.** `ProductFacets` et `ProductSorts` mémoïsent leur liste, et le
recomptage du 2026-09-08 donne **sept** sites pour `facets()`, **six** pour `sorts()` : les appels
restent, mais ils rendent un tableau déjà construit. Les `new Facet` et les `__()` ne se font plus
qu'une fois par requête, les implémentations étant liées en `scoped`. Le `is_tax()` cité ici avait
déjà quitté `facets()` pour `baseFilter()`.

Rien de dramatique en volume absolu, mais c'est la règle « compter les appels, pas les lignes » du
`CLAUDE.md` du module qui n'est pas tenue par le module lui-même. Une mémoïsation dans
`ResolvedListing` (ou dans le `QueryPlan` de R-01) supprime le problème et le rend impossible à
réintroduire.

### R-08 · 🟡 · **fermé le 2026-09-08** · ouvert le 2026-09-06 — le provider fait six choses

`MeiliFacetsServiceProvider` porte les bindings, l'enregistrement des listings, la déclaration de
la discovery, la cascade de vues du thème, la publication des assets et les commandes. 147 lignes,
lisibles, mais c'est le fichier que personne n'ose plus toucher. Trois providers (bindings,
listings, vues/assets) coûteraient moins cher à faire évoluer.

**Fait le 2026-09-08**, en quatre plutôt qu'en trois, calqués sur les espaces de noms du module :

```
avant :  1 fichier · 195 lignes · 19 liaisons · 10 méthodes
après :  MeiliFacetsServiceProvider 83 · ListingServiceProvider 67
         IndexingServiceProvider 48 · SearchServiceProvider 47 · RenderingServiceProvider 23
```

Le point d'entrée ne lie plus rien : il se déclare et enregistre les couches. Vérifié par le
conteneur — **19 liaisons, 0 en échec**, la précédence `scopedIf` du projet survit au découpage, et
le parcours de filtrage est recetté en navigateur. `RenderingServiceProvider` porte ce nom et pas
`ViewServiceProvider` : Laravel en charge déjà un du même nom court.

---

## 2. Constats — correction

### R-09 · 🟠 · **fermé le 2026-09-06** · ouvert le 2026-09-06 — la garde WooCommerce sur `ProductListing` était inopérante

`MeiliFacetsServiceProvider::registerListings()` n'ajoute `ProductListing` au registre que si
WooCommerce est actif. Mais `ListingDiscovery::apply()` instancie **toute** classe implémentant
`Listing` trouvée dans les emplacements scannés, `ProductListing` comprise, sans aucune garde.

**Vérifié le 2026-09-06** : une classe jetable implémentant `Listing`, posée dans `app/Tmp/` puis
dans `Modules/MeiliFacets/app/Tmp/`, apparaît dans le registre après `discovery:clear`
(`listings: [products, probe-module]`). La découverte fonctionne donc bien — et elle enregistre
`ProductListing` par un second chemin, non gardé.

Conséquence sur un projet Pollora sans WooCommerce : le listing `products` existe, et
`perPage()` appelle `wc_get_default_products_per_row()` — fonction absente, `Error` fatale au
rendu. Le `catch (Throwable)` de `apply()` ne protège que la construction, pas les appels
ultérieurs. La promesse « listing produit livré **quand WooCommerce est actif** » est fausse telle
qu'écrite.

**Fermé le 2026-09-06, sans toucher au contrat.** `ListingDiscovery::apply()` portait déjà la
couture — « a listing whose dependencies cannot be built is skipped » — mais `ProductListing` ne
déclarait pas sa dépendance : il se construisait sans rien demander puis échouait plus tard sur
`wc_get_default_products_per_row()`, hors du `catch`. Son constructeur refuse désormais de se
construire sans WooCommerce, et la couture existante fait son travail.

L'enregistrement explicite du provider a été retiré dans la foulée : il faisait double emploi avec
la découverte, vérifiée fonctionnelle (R-31). Un seul chemin, gardé. Vérifié après
`discovery:clear` : `/boutique` rend toujours 16 produits, `?categorie=cheveux` 14, une archive de
catégorie 14.

Ajouter `Listing::isAvailable()` au contrat avait été envisagé puis écarté : ça aurait élargi un
contrat public pour un problème interne au module, alors que la couture existait déjà.

**Fermeture partielle**, relevée le jour même : `ListingComponent` désigne toujours
`ProductListing::NAME` comme listing par défaut de tous les composants. Le module refuse désormais
de *construire* le listing sans WooCommerce, mais continue de le *nommer* par défaut. Voir R-62.

**La réponse de fond reste ailleurs** : `ProductListing` est du WooCommerce dans un paquet
générique. Le sortir — vers le projet, ou vers un pont `meilifacets-woocommerce` — ferait
disparaître la question au lieu de la garder. C'est un choix de produit, pas une correction : voir
Q-07.

### R-10 · 🟠 · **fermé le 2026-09-06** (D-07) · ouvert le 2026-09-06 — une facette mono-sélection était une porte à sens unique

`QueryPlan::isCountedApart()` exige `needsDisjunctiveCount()`, qui exige `SelectionMode::Multiple`.
Une facette mono — la catégorie — est donc comptée sur la réponse principale, elle-même filtrée par
la valeur choisie. Les autres valeurs disparaissent de la distribution, donc du HTML.

**Vérifié le 2026-09-06** en HTTP : `/boutique` rend 30 valeurs de `product_cat` ;
`/boutique?f_product_cat=cheveux` n'en rend plus que 21, toutes co-occurrentes de « cheveux ».
« maquillage », « parfums », « soins-visage » ont disparu. S'y ajoute que l'`<input type="radio">`
rendu pour une facette mono **ne se décoche pas** : le seul retour en arrière est « Tout effacer »,
qui vide aussi marque et contenance.

Le raisonnement documenté — « le disjonctif n'est produit que pour le multi-sélection » — est
exactement inversé. Un groupe de boutons radio doit montrer ses alternatives, sinon il n'est pas un
choix ; c'est le mono qui a le plus besoin du comptage disjonctif.

**Fermé le 2026-09-06 par D-07**, en supprimant le cas plutôt qu'en le traitant : la seule facette
mono du projet passe en multi-sélection, et le comptage disjonctif la couvre donc nativement. La
règle « le disjonctif n'est produit que pour le multi » n'a plus de contre-exemple. Elle reste
fausse en principe : une facette mono déclarée à l'avenir retomberait dans le piège, et rien ne
l'en avertit.

### R-11 · 🟠 · **fermé le 2026-09-06** (D-07) · ouvert le 2026-09-06 — la facette catégorie était plate sur une taxonomie à trois niveaux

Les ancêtres sont indexés — décision juste, sans quoi les archives parentes seraient vides. Mais la
distribution qui en résulte mélange tous les niveaux dans une seule liste.

**Vérifié le 2026-09-06** : sur `/boutique?f_product_cat=cheveux`, la facette liste côte à côte
`cheveux`, `shampoings`, `apres-shampoings`, `brosses-peignes`,
`cheveux-beaute-de-linterieur-complements-alimentaires`, `corps`, `huiles-visage` — 21 valeurs sans
la moindre indication de niveau, plafonnées à 30 sur une taxonomie qui compte plus de cent termes.

Le plafond de 30 est pris sur le compte : les rayons les plus fournis remontent, les feuilles rares
tombent. Une facette de catégories illisible sur le catalogue de recette le sera davantage sur le
catalogue réel.

**Mesuré le 2026-09-06**, sur le catalogue local (76 produits publiés) :

| Taxonomie | Termes non vides | Rendues dans le HTML | **Atteignables sans JavaScript de dépliage** |
| --- | --- | --- | --- |
| `product_cat` | **81** (6 au niveau 0, 24 au niveau 1, 51 au niveau 2) | 30 | **10** |
| `product_brand` | 9, plats | 9 | 9 |
| `pa_contenance` | 24 | 24 | **10** |

Un visiteur atteint donc aujourd'hui **10 rayons sur 81**, et **10 contenances sur 24**. La
taxonomie complète compte 109 termes sur trois niveaux. Aucun plafond ni aucun ordre d'affichage
ne rend une liste plate de 81 valeurs utilisable : c'est la forme qui ne convient pas, pas son
réglage. `product_brand`, à l'inverse, est une facette exemplaire — 9 valeurs plates, toutes
visibles.

À noter aussi : `product_cat` n'est pas dans `url_parameters`, donc la facette est servie sous
`f_product_cat` — le préfixe que la documentation du module décrit explicitement comme « un
mapping à faire, pas un état normal ».

### R-12 · 🟠 · ouvert · 2026-09-06 — rien ne réindexe sur changement de taxonomie

Renommer un terme, changer son slug, le déplacer sous un autre parent ou le supprimer laisse
`facets.*` et la chaîne d'ancêtres périmés sur tous les produits concernés. Aucun hook n'est posé
sur `edited_term`, `delete_term` ou `set_object_terms` côté module, et MeiliScout ne réindexe les
posts que sur sauvegarde de post.

Effets concrets, aucun visible :

- un rayon déplacé vide ou remplit à tort l'archive de son ancien parent, durablement ;
- un slug renommé casse toutes les URLs filtrées déjà indexées **et** fait disparaître la valeur de
  la facette ;
- un terme supprimé laisse ses produits filtrables sur une valeur qui n'existe plus, avec un
  libellé qui retombe sur le slug (`FacetValues::of()`, `$labels[$slug] ?? $slug`).

Le cas jumeau — la promotion qui expire sans sauvegarder le produit — est documenté dans
`pieges.md`. Celui-ci ne l'est nulle part, et il est plus fréquent.

**Au 2026-09-22** (passe documentaire, `R-153`) : **en partie corrigé en amont.** MeiliScout réindexe
désormais les posts d'un terme créé ou modifié (`created_term`, `edited_term` →
`reindexPostsForTerm()`, `SingleIndexingServiceProvider.php:114-115`) ; la phrase « MeiliScout ne
réindexe les posts que sur sauvegarde de post » est donc fausse. Reste la suppression, lue dans la
source et non mesurée : WordPress détache les objets du terme (`wp_set_object_terms()`,
`taxonomy.php:2156`) **avant** `delete_term` (`:2212`), si bien que `reindexPostsForTerm()` ne
retrouve plus aucun post à réindexer.

### R-13 · 🟠 · ouvert · 2026-09-06 — un hit sans champ `card` est jeté en silence

`SearchResults::cards()` filtre les hits dont le champ `card` n'est pas un tableau. Un document
indexé avant l'ajout du projecteur, ou par un autre chemin, disparaît donc de la grille — mais il
compte toujours dans `totalHits`, donc dans la pagination. Symptôme : une page qui affiche 15
produits là où le module en annonce 16, sans une ligne de log.

À rendre bruyant, ou à traiter comme une carte vide plutôt que comme une absence.

### R-14 · 🟡 · ouvert · 2026-09-06 — `multiSearch()` peut lever un `ValueError` non converti

`MeilisearchEngine::multiSearch()` fait
`array_combine(array_keys($queries), array_slice($responses, 0, count($queries)))`. Si le moteur
renvoie moins de réponses que de requêtes, `array_combine` lève un `ValueError` — **hors** du `try`
de `send()`, donc jamais transformé en `SearchFailed`. Résultat : une 500 au lieu de la vue de
repli, exactement dans le cas où celle-ci sert.

### R-15 · 🟡 · **fermé le 2026-09-07** · ouvert le 2026-09-06 — les identifiants de compteur ne portent pas le nom du listing

`Facets::countId()` produit `meilifacets-{taxonomie}-{slug}`, alors que `Sort::id()` produit
`meilifacets-{listing}-sort` avec, en commentaire, « deux listings sur une page ne doivent pas se
marcher dessus ». Deux listings sur une page produisent donc des `id` dupliqués et un
`aria-describedby` qui pointe vers le mauvais compteur. Un des deux composants applique la règle,
l'autre non.

**Fermé le 2026-09-07** — par R-62, sans que ce constat soit mis à jour. Relevé par la passe de
conformité de la documentation : le code qu'il décrit n'existe plus.
### R-16 · 🟡 · **fermé le 2026-09-06** · ouvert le 2026-09-06 — `RobotsPolicy` s'appliquait à toute page du site

Le filtre `wp_robots` est global. `RobotsPolicy::appliesTo()` déclare une URL filtrée dès qu'un
paramètre porte le préfixe `f_` ou figure dans `url_parameters` — sans jamais vérifier qu'un
listing est rendu sur la page. Un lien de campagne `?marque=lumen` sur un article, ou n'importe
quel `?f_…` sur l'accueil, bascule la page en `noindex`.

C'est la même classe de bug que celui corrigé le 2026-09-04 pour `?q=`, resté ouvert du côté des
facettes.

**Fermé le 2026-09-06.** `RobotsPolicy` ne décide plus que sur une page d'archive ou de recherche.
`wp_robots` s'exécutant dans `<head>`, donc avant que le composant du listing ne soit rendu, la
décision ne peut pas venir du listing lui-même sans changer de mécanisme — trois options avaient
été pesées :

| | Verdict |
| --- | --- |
| `Listing::appliesToRequest()` sur le contrat | **écarté** — élargit un contrat public pour un problème de timing interne, duplique une troisième fois la logique de contexte déjà présente dans `baseFilter()` et `facets()`, et transforme une certitude en pronostic silencieusement faillible |
| `X-Robots-Tag` posé au rendu, par ce qui sait | juste sur le fond, mais repose sur un chemin d'en-têtes que Pollora malmène déjà (R-40) : à mesurer avant de s'y fier |
| Garde de contexte dans `RobotsPolicy` | **retenu** — trois lignes, aucun contrat touché, supprime le risque réel, et ne ferme pas la porte au précédent |

Ce correctif a rendu sûr l'élargissement du `noindex` à `sort`, `q` et `pg` — voir R-58.

Limite assumée : un listing posé sur une page libre, hors archive, n'est pas couvert.

### R-17 · ⚪ · **accepté le 2026-09-06** · ouvert le 2026-09-06 — un crochet mal orthographié dans un thème produit une 500

`ContractComponent::hook()` fait `Hook::from($name)`, qui lève un `ValueError` sur une valeur
inconnue. Une vue surchargée par un thème avec `{{ $hook('titre') }}` fait tomber la page entière —
alors que tout le contrat `data-meili` est construit sur la promesse inverse : « une surcharge de
thème périmée dégrade vers le rendu serveur, jamais vers une interaction à moitié morte ».
**Accepté le 2026-09-06**, après examen sous R-62 : la promesse de dégradation porte sur une
surcharge **périmée**, pas sur une surcharge **cassée**. Une faute de frappe dans un `$hook()` est
une erreur PHP dans une vue, et toute erreur de vue Blade produit déjà un 500. Rendre `hook()`
tolérant masquerait un bug au lieu de le montrer.

### R-18 · ⚪ · ouvert · 2026-09-06 — branchement de repli inatteignable dans `results.blade.php`

`<x-meilifacets::listing>` remplace déjà tout son slot par `<x-meilifacets::unavailable>` quand la
recherche a échoué. Le `@if ($resolved->failed())` de `results.blade.php` n'est donc jamais vrai
dans l'usage documenté — mais il est couvert par un test, ce qui donne une fausse impression de
couverture.

### R-19 · 🟠 · ouvert · 2026-09-06 — le client de recherche de MeiliScout est inadapté au chemin de rendu

`MeiliFacetsServiceProvider::searchEngine()` prend `ClientFactory::getSearchClient()`. Lecture du
code du plugin : cette fabrique fait, à la première résolution de chaque process PHP, un
`checkdnsrr($host, 'A')` **puis** un `GET /health` avant de rendre le client.

Deux conséquences :

- une résolution DNS et un aller-retour HTTP s'ajoutent devant chaque première recherche d'une
  requête ; à ce stade c'est du bruit en local (mesuré : ~280 ms de TTFB sur `/boutique`), pas
  forcément en production ;
- `checkdnsrr(..., 'A')` renvoie `false` sur un hôte qui n'a qu'un CNAME — cas courant d'une URL
  interne PaaS. Le client vaut alors `null`, `SearchFailed::unconfigured()` est levée, et **le
  listing s'affiche en panne alors que le moteur répond**.

S'y ajoute qu'aucun timeout n'est posé sur le client Meilisearch : un moteur qui accepte la
connexion sans répondre bloque le rendu jusqu'au timeout PHP. Le lot 6 prévoit « timeout et repli
côté rendu serveur » ; c'est en réalité un prérequis du lot 3, pas du lot 6.

---

## 3. Constats — client de recherche et contrat

### R-20 · 🔴 · **fermé le 2026-09-07** · ouvert le 2026-09-06 — le client n'existait pas comme comportement

Sept fichiers ES sont publiés dans `public/modules/meilifacets/js/` (vérifié). Aucun n'est chargé :
`Stylesheet` inscrit la feuille de style, rien n'inscrit de script — vérifié sur `/boutique`, seul
`/modules/meilifacets/css/meilifacets.css` apparaît dans le HTML.

Il manque, dans l'ordre : un point d'entrée, son inscription en `type="module"`, l'instanciation de
`Contract` sur chaque racine `[data-listing]`, la vérification avant démarrage, les cinq gestes
(cocher, appliquer, trier, paginer, remettre à zéro), le repeint, et le branchement de
`Listing.onBack()`, devenu `listenToHistory()` au lot 3c-1. Le module livrait alors une bibliothèque testée et morte.

**Fermé le 2026-09-07 (lot 3c-1).** Le client démarre, vérifie le contrat, et refuse de démarrer en
nommant ce qui manque. `listing-page.js` amorce une instance par racine, `ListingBinding` écoute sur
la racine — donc une carte clonée n'a rien à câbler — et `CardPainter` remplit une carte depuis le
document.

**Recetté en navigateur**, pas seulement en test : sur `/boutique`, cocher « lumen » ne fait rien
en mode `submit`, « Appliquer » ramène 16 cartes à 10, l'URL devient `?marque=lumen`, et les
compteurs des autres facettes se resserrent — visage passe de 24 à 6. Quatre combinaisons
successives vérifiées, dont le décochage qui rend l'URL vide.

**Complété le 2026-09-07 (lot 3c-2).** Tri, pagination et remise à zéro écoutent désormais, et la
synchronisation des cases est faite : `ListingBinding` n'est plus qu'un câblage, quatre vues portent
le rendu (`ResultsView`, `FacetsView`, `PaginationView`, `SortCombobox`). Reste au lot 3c-3 :
l'état d'attente et le comportement après échecs répétés.

### R-21 · 🔴 · **fermé le 2026-09-07** · ouvert le 2026-09-06 — rien ne transmettait la connexion ni la description au navigateur

`SearchClient` attend `{ url, key, index }`. `ListingQuery` et `ListingUrl` attendent un objet
`listing` portant `facets[]` (avec `taxonomy` et `multiple`), `perPage`, `sorts`, `filter`,
`params`, `reserved`, `attributes`. **Aucun PHP ne produit cet objet.** `MEILI_PUBLIC_URL` et
`MEILI_SEARCH_KEY` ne quittent nulle part le serveur.

C'est le vrai chaînon manquant, et il n'est pas neutre : c'est aussi lui qui décidera de la forme
du contrat de données (JSON dans un `<script type="application/json">` ? attributs `data-*` ? un
`window.meilifacets` ?) et de ce qui est exposé.

**Fermé le 2026-09-07.** `BrowserConnection` portait déjà l'adresse, la clé et l'index depuis R-52 ;
`ListingDescription` s'y ajoute et publie `filter`, `perPage`, `attributes`, `apply`, `facets`
(taxonomie, multiple, plafond), `params`, `reserved`, `sorts` et le motif de pluriel des compteurs.
Le tout par `script_module_data_@meilifacets/listing`, et **seuls les listings réellement rendus
sont décrits** : le composant s'inscrit au moment où il rend, WordPress imprime en pied de page.

Conséquence assumée, déjà écrite en R-28 : le filtre de base part en clair dans la page.

### R-22 · 🟠 · **fermé le 2026-09-07** · ouvert le 2026-09-06 — les deux implémentations du plan divergeaient

La dette « une même règle, deux implémentations à tenir en phase » est présentée comme un risque.
C'est déjà une divergence :

| Règle | PHP (`StateReader`) | JS (`ListingUrl`, `Listing`) |
| --- | --- | --- |
| dédoublonnage des valeurs | oui (`array_unique`) | non |
| plafond au `cap` de la facette | oui | non |
| une seule valeur sur une facette mono | oui (`array_slice(…, 1)`) | non — `toggle()` ignore `facet.multiple` |
| tri des valeurs | oui | oui |
| longueur de `q` bornée à 200 | oui | non |

Concrètement, en mode `immediate`, cocher deux catégories côté client produirait un état que le
serveur refuse — et une URL que le rendu suivant ne reproduira pas.

**Fermé le 2026-09-07 — puis rouvert et refermé le même jour, la première fermeture était fausse.**

`ListingState` déduplique, trie et borne la recherche ; `toggling()` reçoit la description de la
facette, donc une facette mono-sélection remplace au lieu d'empiler.

**Ce que la première fermeture affirmait à tort** : « le plafond est respecté ». Il ne l'était que
sur le chemin d'écriture, `toggling()`. **Le chemin de lecture n'en avait aucun** — `toState()`
n'appliquait ni le plafond ni la règle mono-sélection. Trouvé par une passe de revue, mesuré :

```
?brand=a,b,c,d,e,f,g,h   sur une facette plafonnée à 3
client : ['a','b','c','d','e','f','g','h']      serveur : ['a','b','c']
```

Deux lignes du tableau ci-dessus restaient donc vraies après une fermeture qui prétendait le
contraire.

**Et la borne de `q` était jumelle sans l'être** : PHP tronque en **caractères** (`mb_substr`),
JavaScript tronquait en **unités UTF-16** (`slice`). Mesuré sur `'a' + '😀'×210` : PHP garde
200 caractères, JavaScript en gardait **101 et finissait sur un demi-substitut isolé** — une chaîne
UTF-16 invalide envoyée au moteur. `ContractParityTest` comparait le nombre `200` des deux côtés et
donnait donc une confiance que le code ne méritait pas : *la constante était jumelle, l'unité ne
l'était pas.*

**Refermé le 2026-09-07.** `toState()` applique `slice(0, multiple ? cap : 1)`, miroir exact de
`StateReader::values()` ; la troncature de `q` compte des points de code. Deux tests neufs, vérifiés
en les mutant. Recetté en navigateur sur une URL forgée à 60 valeurs : **30 après le premier geste,
30 clauses envoyées au moteur**.

**Enseignement** : un constat fermé sur « la règle est respectée » doit nommer *par quel chemin*.
Ici l'écriture était couverte, la lecture ne l'était pas, et le tableau du constat le disait encore.

### R-23 · 🟡 · **fermé le 2026-09-07** · ouvert le 2026-09-06 — le client lit des attributs hors contrat

`data-taxonomy` (sur le `<fieldset>`), `data-apply` (sur le conteneur de facettes),
`data-listing` (sur la racine) et `data-value` (sur une option de tri) sont indispensables au
client mais ne font pas partie de `Hook`, ne sont pas vérifiés par `Contract::breaches()` et ne
figurent pas dans le tableau des crochets d'`architecture.md`. La règle « le client n'adresse que
des crochets `data-meili` » est déjà entamée, sans que le mécanisme de version le voie.

**Fermé le 2026-09-07** — par R-62, sans que ce constat soit mis à jour. Relevé par la passe de
conformité de la documentation : le code qu'il décrit n'existe plus.
### R-24 · ⚪ · **fermé le 2026-09-07** · ouvert le 2026-09-06 — `Hook::PageTemplate` était déclaré et rendu nulle part

`case PageTemplate = 'page-template'` existe dans l'énumération PHP, n'apparaît dans aucune vue,
n'est pas dans `contract.js`, n'est pas dans le tableau d'`architecture.md`. Le contrat annonce
24 crochets, 23 sont réels.

### R-25 · 🟡 · **fermé le 2026-09-07** · ouvert le 2026-09-06 — rien ne vérifiait que les deux listes de crochets coïncident

`Contract::VERSION = 1` en PHP, `const VERSION = 1` en JavaScript, chacun écrit à la main. Le
mécanisme de version protège contre un thème périmé, **pas** contre un oubli d'incrément ni contre
une divergence des listes elles-mêmes : `Hook` porte 24 cas, les `RULES` de `contract.js` en
citent 13. Aucun test ne compare les deux fichiers.

**Fermé le 2026-09-07** par `ContractParityTest`, qui lit les fichiers JavaScript et compare à la
constante PHP : la version du contrat, **chaque crochet que le client adresse**, l'identifiant du
module de script, l'attribut `data-meili` et celui qui porte la version, le préfixe de champ
`facets.`, le séparateur de valeurs, la borne de la recherche et la première page. Vérifié en les
cassant une à une — chaque mutation fait tomber une assertion.

**Deux duplications ont disparu au lieu d'être gardées**, le 2026-09-07 : le préfixe `f_` et les
noms des paramètres réservés étaient écrits dans le client *et* publiés par le serveur, qui les
produit toujours en entier — `params` boucle sur chaque facette, `reserved` sur chaque cas de
`QueryParameter`. Les valeurs de repli du client étaient donc mortes. Même défaut que
`PageWindow.SLOTS`, trouvé le même jour, et il masquait des jeux d'essai faux : deux fixtures
déclaraient `reserved: {}`, ce qu'aucun serveur ne produit — l'URL sortait `?undefined=2` une fois
les défauts retirés.

**Enseignement** : une valeur par défaut côté client, en face d'un serveur qui publie toujours la
donnée, n'est pas une sécurité — c'est un mensonge qui tient debout les tests qui devraient tomber.

---

## 4. Constats — sécurité

### R-26 · 🟠 · à trancher (Q-11) · 2026-09-06 — le prix est du HTML non filtré, et la doc dit le contraire

`Card::$price` est un `HtmlString` construit depuis le champ `card.price` de l'index, rendu tel
quel. La décision de ne pas filtrer est argumentée et défendable (`decisions.md`, `pieges.md` §
audit du 2026-09-04 : une liste blanche casse promo et variable, et ne protège d'aucun vecteur
réaliste).

Mais `pieges.md` affirme toujours, quelques paragraphes plus loin : « Le prix passe par `wp_kses`
au rendu, pas à l'indexation ». **Le code ne filtre plus rien.** Deux affirmations contradictoires
dans le même document, dont une fausse — exactement le genre d'écart qui fait rouvrir un débat déjà
tranché à la prochaine revue.

À faire quoi qu'il arrive : supprimer le passage périmé. À trancher : le seuil auquel on refiltre
(un plugin branché sur `woocommerce_get_price_html`, une donnée saisie par un utilisateur non
privilégié).

**Au 2026-09-22** (passe documentaire, `R-153`) : le passage `wp_kses` de `pieges.md` est retiré ; le
seuil de refiltrage est écrit dans la décision « Markup du prix » (`decisions.md:42`). Reste à
marquer `Q-11` répondue — c'est à Louis de le confirmer.

### R-27 · 🟡 · ouvert · 2026-09-06 — `searchableAttributes` reste à `["*"]`

**Vérifié** sur l'index réel : `searchableAttributes: ["*"]`, `displayedAttributes: ["ID","card"]`.
La restriction d'affichage empêche de **lire** `post_content` et les metas ; elle n'empêche pas de
les **cibler**. Avec la clé de recherche publique, un visiteur peut confirmer par recherche
booléenne la présence d'une valeur dans n'importe quel champ indexé — `_edit_lock`,
`_yoast_wpseo_*`, le contenu d'un brouillon s'il en entrait un.

C'est noté « lot 5, question de pertinence ». Ç'en est aussi une de fuite d'information, et elle
n'est pas évaluée comme telle.

### R-28 · 🟠 · à trancher (Q-03) · 2026-09-06 — tout filtre de sécurité posé côté serveur devient cosmétique

C'est la conséquence structurelle du transport direct, et elle n'est écrite nulle part.

`ProductListing::baseFilter()` pose `post_type = "product"`, `post_status = "publish"` et
l'exclusion de `exclude-from-catalog`. Au premier rendu, PHP applique ces clauses. **Dès le premier
filtre, c'est le navigateur qui construit la requête** — et il devra recevoir ce filtre de base sous
forme de chaîne (c'est ce qu'attend `ListingQuery`, champ `listing.filter`). Un visiteur qui édite
cette chaîne interroge l'index sans aucun garde-fou.

Le seul verrou réel est côté moteur : un **tenant token** Meilisearch, dont les `searchRules`
imposent le filtre. `pieges.md` l'évoque une fois, en passant, à propos des brouillons. C'est en
réalité le pivot de tout le modèle de sécurité du module.

### R-29 · 🟡 · ouvert · 2026-09-06 — la clé de recherche n'a pas de périmètre documenté

Le module consomme `MEILI_SEARCH_KEY` sans jamais dire à quoi elle doit être limitée : quels index,
quelles actions (`search` seule ?), quelle expiration. Sur un projet réutilisable, c'est une
consigne d'installation obligatoire, pas un détail d'infrastructure.

---

## 5. Constats — tests

### R-30 · 🟠 · ouvert · 2026-09-06 — les tests couvrent les unités pures, pas le câblage

**Vérifié le 2026-09-06** : 121 tests PHP, 212 assertions, verts ; 49 tests Node, verts. La qualité
des cas est réelle — noms lisibles, un comportement par test, doublures propres.

Ne sont couverts par **aucun** test : `MeiliScoutBridge`, `FacetedPostIndexable::getIndexSettings()`
(seul `ConfiguredIndexAttributes` l'est), `MeilisearchEngine`, `CurrentListing`, `ResolvedListing`,
`ListingDiscovery`, `ListingRegistry`, `Unavailable`, `Stylesheet`, `CheckParametersCommand`,
`WordPressTermLabels`, `DefaultCardProjector`, `WooCommerceCardProjector`, `PageSize`,
`WordPressTermHierarchy`.

C'est exactement la liste de ce qui casse : la substitution d'indexable, la conversion en
`SearchQuery`, la résolution du listing, la découverte, l'émission des en-têtes. R-09 et R-14
seraient tombés sur un test.

### R-31 · 🟡 · fermé le 2026-09-06 — la découverte automatique d'un listing n'avait jamais été exercée

Ouvert puis vérifié dans la même session : une classe jetable implémentant `Listing`, posée dans
`app/Tmp/` puis dans `Modules/MeiliFacets/app/Tmp/`, apparaît bien dans le registre après
`php artisan discovery:clear`. La découverte fonctionne dans les deux emplacements.

Reste que la seule implémentation livrée est **aussi** enregistrée explicitement par le provider :
sans la classe de test, les deux chemins étaient indiscernables. Un test qui prouve la découverte
manque toujours (voir T-14), et l'enregistrement explicite doit disparaître (R-09).

### R-32 · 🟡 · ouvert · 2026-09-06 — rien ne teste ce que la dette PHP/JS demande de tester

La dette assumée est « bornée en couvrant les deux côtés avec les mêmes cas ». Or aucun test ne
compare `Hook` à `contract.js`, ni `QueryPlan` à `ListingQuery` sur un même jeu d'états. Les deux
suites vivent côte à côte sans se regarder — et R-22 montre qu'elles ont déjà divergé.

**Réduit le 2026-09-24**, à la demande de Louis, après la panne de `R-158`. Deux jumelages de plus,
tous deux tués par mutation : `ResolvedListingParityTest` compare par réflexion les méthodes publiques
de `Listing` à celles de `ResolvedListing` — la classe que lisent les vues recopie le contrat à la main,
et l'oubli mettait tout le site en 500 ; `ListingDescriptionTest` compare les clés que
`ListingDescription::of()` publie aux champs déclarés dans `description.ts`, un champ ajouté d'un seul
côté faisant désormais échouer la suite. **Reste ouvert** : le cœur de la dette, `QueryPlan` contre
`ListingQuery` sur un même jeu d'états.

---

## 6. Constats — code mort et résidus

### R-33 · ⚪ · **fermé le 2026-09-07** · ouvert le 2026-09-06 — déclarations jamais lues

Vérifié par recherche sur `app/`, `resources/` et `tests/` :

| Déclaration | Statut |
| --- | --- |
| `PageSize::forPosts()` | jamais appelée |
| `FacetValueOrder::Alphabetical` | jamais utilisée (seul `ByCount` l'est) |
| `Facet::$highCardinality` | déclarée, jamais lue |
| `Unavailable::announced()` | jamais appelée |
| `Hook::PageTemplate` | voir R-24 |
| `HeadingLevel::H2/H4/H5/H6` | jamais utilisées — acceptable, c'est un ensemble fermé |

#### Vérifié avant de supprimer, le 2026-09-07

**Deux gardées.** `FacetValueOrder::Alphabetical` modèle un ensemble fermé **imposé par le
moteur** — vérifié en lui envoyant une valeur invalide : « expected one of `alpha`, `count` ». En
retirer une moitié ferait un modèle incomplet. Idem `HeadingLevel`, ensemble fermé du HTML.

**Quatre supprimées**, aucune n'ayant la moindre trace documentaire — ni intention écrite, ni lot
qui l'attende :

| | Pourquoi |
| --- | --- |
| `Facet::$highCardinality` | drapeau booléen que rien ne lit, et que les règles du module condamnent par ailleurs |
| `Unavailable::announced()` | redondant avec `ResolvedListing::failed()`, que la vue interroge déjà |
| `Hook::PageTemplate` | **contredit une décision prise** : la fenêtre de sept emplacements est rendue par le serveur, un `<template>` de page servirait à cloner des boutons |
| `PageSize::forPosts()` | aide pour un listing d'articles qui n'existe pas ; une ligne à réécrire le jour où le lot 5 en aura besoin |

Pas d'incrément de `Contract::VERSION` pour `PageTemplate` : jamais rendu, jamais lu par le client,
donc rien ne change pour un thème.

### R-34 · ⚪ · ouvert · 2026-09-06 — résidus de scaffold

`app/Providers/.gitkeep`, `config/.gitkeep`, `resources/assets/.gitkeep`,
`resources/views/.gitkeep`, `tests/Feature/.gitkeep` — dont un est **publié** dans
`public/modules/meilifacets/.gitkeep` par `module:publish`. Et `.playwright-mcp/` (cinq traces de
console et cinq instantanés de page) vit dans le module, ignoré par git mais présent sur disque.

### R-35 · ⚪ · **fermé le 2026-09-22** · ouvert le 2026-09-06 — `config/config.php` existe pour ne rien déclarer

Le fichier ne porte plus que `'name' => 'MeiliFacets'`, dont `configuration.md` dit lui-même que
c'est une « clé de nwidart, sans usage dans le module ». Il reste utile comme porte-commentaire de
la règle « un réglage déclaré ici n'est pas surchargeable » — à dire explicitement, ou à supprimer.

**Fermé le 2026-09-22** (passe documentaire, `R-153`) : la règle est dite explicitement, dans le
fichier (`config/config.php:5-9`) et dans `configuration.md`. Le fichier reste, comme porte-commentaire.

---

## 7. Constats — documentation

### R-36 · 🟠 · ouvert · 2026-09-06 — la documentation est plus longue que le code, et déjà fausse par endroits

1 683 lignes sur six fichiers, contre environ 2 200 lignes de code applicatif (tests exclus). Écarts
relevés pendant la revue :

| Où | Ce qui est écrit | Ce qui est vrai |
| --- | --- | --- |
| `pieges.md` | « Le prix passe par `wp_kses` au rendu » | aucun filtrage — voir R-26 |
| `decisions.md`, dettes | `product_tag` mappé sur `tag` | `config/meilifacets.php` mappe `etiquette` (corrigé, dette non fermée) |
| `config/meilifacets.php` (projet) | « Une taxonomie laissée de côté garde son propre nom » | elle prend le préfixe `f_` |
| `architecture.md`, tableau des crochets | 24 crochets | `page-template` absent des vues — voir R-24 |
| `lots.md`, recette du lot 3 | « Aucune `WP_Query` de produits n'est exécutée » | la requête principale de l'archive s'exécute toujours, par décision assumée ; c'est le **listing** qui n'en ajoute pas |

Le fond est excellent — c'est le meilleur corpus de décisions que j'aie lu sur un module de ce
type. Le problème est son coût de maintenance : à ce volume, il faut l'auditer comme du code, et
rien ne le fait.

**Au 2026-09-22** (passe documentaire, `R-153`) : les cinq écarts du tableau sont corrigés. La passe du
2026-09-22 en a trouvé et corrigé d'autres, sur les huit fichiers de `docs/` et le README (`R-153`).
Reste la seconde moitié de `Q-13` : le coût du corpus.

### R-37 · 🟡 · **fermé le 2026-09-07** · ouvert le 2026-09-06 — la doc vivait hors du module

`README.md` du module renvoie à `../../docs/meilifacets/`, un chemin qui n'existe pas depuis le
dépôt du module (dépôt git distinct, voir R-38) et qui n'existera pas après extraction en paquet.
Le rapatriement est prévu « au lot 7 », c'est-à-dire après tout le reste — donc au moment où le
corpus sera le plus gros et le plus périmé.

---

## 8. Constats — dettes structurelles

### R-38 · 🟡 · accepté (D-02), à rouvrir avant production · 2026-09-06 — le module est un dépôt git imbriqué

**Vérifié** : `git status` du projet rend `?? Modules/MeiliFacets/`, et `git ls-files
Modules/MeiliFacets` ne rend rien. Le projet ne versionne pas le module, et le module ignore le
projet. Conséquences immédiates :

- rien ne garantit qu'un déploiement embarque la révision attendue ;
- aucune revue de code du projet ne voit les changements du module ;
- `docs/meilifacets/`, `config/meilifacets.php` et `themes/pluralia/.../archive-product.blade.php`
  évoluent dans un dépôt, le module dans l'autre, sans commit commun.

**Instruit le 2026-09-08, différé en fin de projet** (`T-42`). Mesuré :

```
Modules/Wishlist    → 30 fichiers suivis par le projet
Modules/MeiliFacets →  0
origin du module    : git@github.com:Pollora/MeiliFacets.git   (47 commits, non poussés)
```

`modules_statuses.json` déclare `"MeiliFacets": true` et le `merge-plugin` cherche
`Modules/*/composer.json` — mais **un clone du projet n'a pas le module** : une déclaration qui
pointe dans le vide, et le listing disparaît. Sans conséquence tant qu'une seule personne y
travaille ; bloquant dès la deuxième.

Trois sorties, du plus léger au plus juste :

| | Ce que ça donne | Ce que ça coûte |
| --- | --- | --- |
| `/Modules/MeiliFacets/` au `.gitignore` du projet | statut propre, piège du `git add -A` désamorcé | officialise que le projet n'est pas autonome |
| vrai sous-module git | le projet enregistre le SHA, `clone --recursive` reconstruit | commandes de sous-module pour tous, **et il faut pousser les 47 commits d'abord** |
| `composer require pollora/meilifacets` | le module va en `vendor/`, versionné par le lock | le plus lourd — mais c'est ce que le `README` du module annonce déjà, et la fin logique de `Q-01` |

Ce point n'est pas une commodité : c'est ce qui empêche aujourd'hui de dire « voilà ce qui est
livré ».

**Au 2026-09-22** (passe documentaire, `R-153`) : toujours vrai. Le décompte de commits non poussés
écrit plus haut est périmé : la branche suit désormais `origin`.

### R-39 · 🟠 · ouvert · 2026-09-06 — `amphibee/meiliscout` pointe une branche non mergée

`dev-feat/meilifacets`, commit `1c59a05`. Rappel : sans le correctif `resolveIndexable()`, les
facettes cassent à la première sauvegarde de contenu.

**Au 2026-09-22** (passe documentaire, `R-153`) : toujours vrai. Le lock est à `a83fa4b` (depuis `R-112`),
ni `1c59a05` ni `2acf53a` cités plus haut ; le module exige toujours `dev-feat/meilifacets`
(`composer.json`). Le merge amont n'est pas vérifiable d'ici.

### R-40 · 🟠 · ouvert · 2026-09-06 — le `503` ne sort pas

Pollora écrase le statut HTTP de WordPress. Correctif rédigé dans `decisions.md`, **non soumis en
amont**. Tant qu'il ne l'est pas, `Unavailable::announce()` produit un `200` avec deux
`Cache-Control` contradictoires.

À noter en passant : `Unavailable` écrit ses en-têtes par `header()` et `status_header()`,
c'est-à-dire hors de l'objet réponse Laravel. Même une fois Pollora corrigé, c'est un contournement
à réexaminer.

### R-41 · 🟠 · ouvert · 2026-09-06 — l'écart de version du moteur n'est pas refermé

**Vérifié** : local en 1.53.1, `pagination.maxTotalHits = 1000`,
`faceting.maxValuesPerFacet = 100`, `searchCutoffMs` non posé. La production est en 1.10.3.

Rien de ce qui est validé en local ne vaut engagement tant que la montée n'est pas faite. Les
`filterableAttributes` posés aujourd'hui sont explicites (pas de motif `facets.*`), donc a priori
compatibles — a priori seulement.

### R-42 · 🟠 · **fermé le 2026-09-08** · ouvert le 2026-09-06 — la pagination promet des pages que le moteur ne sert pas

*Formulation corrigée le 2026-09-07 : elle était fausse.* Elle disait que `totalHits` plafonnait et
que « rien ne le dit ». Mesuré sur 1.53.1 avec des index fabriqués pour l'occasion (détail dans
[pieges.md](pieges.md)) :

- **les facettes et les filtres sont exacts** au-delà du plafond — `{a: 1500, b: 1000}` sur
  2 500 documents, `totalHits: 1500` sur un filtre à 1 500 ;
- **`totalHits` et `totalPages` ne sont pas plafonnés** — 1 570 et 157 sur 1 570 documents ;

**Ajouté le 2026-09-07 (revue du lot 3c-2), puis fermé le même jour.** `engine.reachable_hits`
**déclarait** le plafond sans le **poser** : le module n'écrivait jamais `pagination.maxTotalHits`
sur l'index. Les deux valeurs étaient tenues à la main, et un projet qui montait le `maxTotalHits`
de son index sans toucher la clé gardait une pagination tronquée, en silence.

**Corrigé.** `FacetedPostIndexable::getIndexSettings()` écrit `pagination.maxTotalHits` depuis
`EngineLimits`, par le même chemin que les quatre autres réglages du module. La clé de
configuration est désormais la seule source.

**Mesuré le 2026-09-07 sur le chemin de réindexation complète** : `reachable_hits` posé à 2500 →
`meiliscout index --clear` → l'index déclare `{"maxTotalHits":2500}` et la description publiée au
navigateur porte `"reachableHits":2500` ; remis au défaut → `{"maxTotalHits":1000}`. Deux tests
`Feature` couvrent l'écriture, la suite `Unit` ne pouvant pas la porter — `getIndexSettings()`
remonte dans MeiliScout, qui lit des options WordPress.

⚠️ **Fermé puis rouvert le même jour : la mesure ne portait que sur un chemin.** Interrogé sur la
qualité de la vérification, j'ai refait la mesure sur le **chemin normal** — l'enregistrement d'un
article, sans purge. Le réglage n'était pas écrit : `reachable_hits` à 1750, un `wp post update`,
l'index restait à 1000. La cause était R-79.

**Refermé le 2026-09-08**, une fois R-79 corrigé en amont. Mesuré sur le chemin normal :
`reachable_hits` à 3000, un simple `wp post update` **sans purge**, l'index déclare
`{"maxTotalHits":3000}`. Le plafond suit la configuration sur les deux chemins.

**Enseignement** : une vérification qui ne couvre qu'un chemin ne ferme rien. La première mesure
portait sur `meiliscout index --clear`, qui recrée l'index — le chemin rare. Le chemin normal, un
rédacteur qui publie, faisait exactement l'inverse.

⚠️ Contrepartie assumée : un `maxTotalHits` posé à la main sur l'index sera écrasé à la prochaine
indexation. C'est vrai de tous les réglages que le module pose.
- **seules les pages le sont** : à 10 par page, la 100 sert 10 produits, la 101 en sert **zéro**, en
  répondant `200`. Le moteur annonce 157 pages et en refuse 57.

Le défaut est donc l'inverse de ce qui était écrit : pas un plafond silencieux, **une promesse de
pages inexistantes**. Sur 1 570 produits, un tiers du catalogue est hors d'atteinte.

**Relever le plafond ne coûte rien** — page 1 à 0 ms que le plafond vaille 1 000 ou 20 000, et
11 ms contre 10 en page 62. C'est la profondeur qui coûte (181 ms au 19 200ᵉ résultat), pas
l'autorisation. Aucun aller-retour supplémentaire : c'est un réglage d'index.

**Différé le 2026-09-07, décision du projet** : on ne relève pas pour l'instant. Le catalogue compte
76 produits, le plafond est à treize fois sa taille, et les pages de listing sont en `noindex` sans
lien interne au-delà de la première — donc aucun robot n'ira. À rouvrir avant qu'un catalogue
approche les 1 000 résultats sur une seule recherche.

**Ce qui reste à faire quelle que soit la valeur** : le module ne doit jamais afficher un numéro de
page qu'il ne peut pas servir. Le bornage est indépendant du réglage, et c'est le même geste que
R-61 — voir là-bas.

---

## 9. Constats — fonctionnel manquant ou oublié

### R-43 · 🟠 · **fermé le 2026-09-15** · ouvert le 2026-09-06 — aucune facette prix ni disponibilité

**Le prix est livré** (commit `885daff`) : `PriceFilter` compose une piste et deux champs, les bornes
viennent de `facetStats` et suivent le filtrage, le filtre teste le **chevauchement** de l'intervalle
d'un produit avec la plage demandée.

Le défaut bloquant que cette entrée nommait — « pour un produit variable, seul le prix le plus bas
est indexé » — est levé par `8fda131`. Vérifié sur l'index par un filtre qui ne peut pas répondre
autrement :

```
price.min <= 28 AND price.max >= 62   →  1 produit
```

Un produit ne croise ces deux bornes que si son intervalle est indexé ; avec le seul prix le plus
bas, la réponse serait zéro.

Deux choses de cette entrée ne sont **pas** livrées, et c'est délibéré :

- **les tranches de prix** qu'elle proposait sont renversées par `D-a` (`prix.md`) — WooCommerce
  résout la question en min/max, et inventer une forme que la plateforme traite autrement est le
  travers de `R-81` ;
- **la disponibilité** est différée par `D-f`, et le stock par variation par `D-g`, tous deux datés
  du 2026-09-15. Rien ne se perd en fermant ici : les deux volets portent leur propre décision.

Le cadrage d'origine est conservé ci-dessous.

---

**Cadrage d'origine, 2026-09-06**

**Cadrage écrit le 2026-09-09 dans `docs/prix.md`** — cas, mesures et décisions à prendre. Deux
choses y corrigent cette entrée :

- son argument « le catalogue de recette n'en contient aucun » (produits variables) **est faux
  aujourd'hui** : 64 simples, **8 variables**, 2 groupés, 2 externes ;
- « une facette de tranches de prix » ne suit pas la plateforme : WooCommerce filtre par **min/max**
  sur un **intervalle** par produit, pas par tranches sur un prix unique.

Et un défaut bloquant, mesuré : pour un produit variable ou groupé, **seul le prix le plus bas est
indexé**. `metas._price >= 40 AND <= 70` ne rend pas un produit vendu de 28 à 62 €. 10 produits sur
76 sont concernés.

**Vérifié** dans les réglages de l'index : `metas._price` et `metas._stock_status` sont filtrables,
`metas._price` est triable. Aucune facette ne les utilise ; seuls deux tris de prix existent.

Ce sont les deux premiers filtres qu'un visiteur de boutique cherche. Ils sont repoussés au lot 4
pour une raison — les produits variables — qui ne concerne pas les produits simples, et le
catalogue de recette n'en contient aucun. Sur les produits simples, une facette de tranches de prix
et une case « en stock » sont livrables aujourd'hui.

### R-44 · 🟠 · à trancher (Q-09) · 2026-09-06 — la recherche texte est à moitié câblée

`ListingState` porte `query`, `StateReader` la lit et la borne à 200 caractères, `QueryPlan`
l'envoie au moteur, `ListingQuery` aussi. **Aucun composant ne la saisit ni ne l'affiche.** Une URL
`?q=parfum` filtre donc la grille en silence : le visiteur voit un sous-ensemble sans savoir
pourquoi, la remise à zéro est masquée uniquement si l'état est vierge (elle le serait donc
correctement, mais rien ne nomme le terme recherché), et `RobotsPolicy` laisse la page indexable.

Soit on livre le champ, soit on retire `q` du lecteur d'état jusqu'au lot 5.

### R-45 · 🟠 · à trancher (Q-10) · 2026-09-06 — la carte du module remplace celle du thème, wishlist comprise

`<x-meilifacets::results>` rend `<x-meilifacets::card>`. Le thème, lui, a
`<x-theme::product-card>` qui porte le bouton wishlist sur la ligne du titre (module `Wishlist`,
maquette cliente). Sur l'archive produit, **ce bouton a disparu**.

`lots.md` annonce pourtant l'inverse : « la bascule sur `<x-theme::product-card>` étant assumée ».
Ce n'est pas ce qui est livré. Trois issues : le listing rend la carte du thème, la wishlist
devient un crochet du contrat, ou la disparition est assumée et écrite.

**Au 2026-09-22** (passe documentaire, `R-153`) : toujours vrai, mesuré sur `/boutique` — l'archive rend
`<x-meilifacets::card>`, sans wishlist. **Contredit une décision validée** : `decisions.md` dit
« bascule sur `<x-theme::product-card>` ». Annoté là-bas comme non tenu ; `Q-10` reste à trancher.

### R-46 · 🟠 · **fermé le 2026-09-08** (T-07) · ouvert le 2026-09-06 — les valeurs repliées n'avaient aucun moyen d'être dépliées

`FacetValues` marque `folded` tout ce qui dépasse `visible` (10), la vue les rend avec `hidden`, et
le cap est à 30. **Il n'existe aucun bouton « voir plus »**, aucun crochet correspondant dans
`Hook`, aucune ligne dans `contract.js`.

**Mesuré le 2026-09-06** sur `/boutique` : 63 valeurs rendues, **34 en `hidden`**, sans aucun moyen
de les atteindre — même avec JavaScript, puisque rien ne sait les révéler. Détail : `product_cat`
30 rendues dont 20 masquées, `pa_contenance` 24 rendues dont 14 masquées, `product_brand` 9
rendues et 0 masquée.

### R-47 · 🟡 · ouvert · 2026-09-06 — les filtres actifs ne sont qu'un nombre

`<x-meilifacets::active-filters>` rend un `<span>` avec un entier. Sur une archive filtrée, rien ne
dit **quoi** est filtré en dehors des cases cochées — invisibles dès qu'une facette est repliée,
hors écran, ou dans un panneau mobile. Le motif attendu sur un listing e-commerce est une liste de
puces retirables une à une.

**Au 2026-09-22** (passe documentaire, `R-153`) : **deux textes se contredisent.** `D-07` dit que la maquette
ferme ce constat (« Filtres appliqués : 1 » est un compteur) ; le Journal du 2026-09-07 dit qu'il
reste ouvert (« une pastille bien centrée ne remplace pas des puces retirables »). À trancher par
Louis.

**Au 2026-09-24** (chantier « barre de filtres », C-7) : **tranché par Louis**, dans le sens du
Journal — des pastilles retirables une à une sont construites (`<x-meilifacets::active-values>`, prix
compris, libellés publiés dans la description, `R-57`), le compteur actuel reste. Reste ouvert
jusqu'à la livraison : étape 2b de [chantier-filtres.md](chantier-filtres.md), qui porte aussi `T-08`.

### R-48 · 🟡 · ouvert · **chantier ouvert le 2026-09-24** · ouvert le 2026-09-06 — rien pour le mobile

Pas de composant de bascule, pas de tiroir de facettes, pas de crochet prévu. Sur un thème
e-commerce, c'est la moitié du trafic, et la colonne de facettes de
`archive-product.blade.php` (`lg:col-span-1`) est simplement empilée au-dessus de la grille en
dessous de `lg`.

**Au 2026-09-24** : chantier « barre de filtres » ouvert sur la branche `feat/filter-bar`, suivi dans
[chantier-filtres.md](chantier-filtres.md) — cette entrée en est le parapluie. Architecture v2 validée
le même jour ([chantier-filtres-architecture.md](chantier-filtres-architecture.md)) : le thème compose,
le module fournit des briques, dont un tiroir qui promeut en dialogue sur mobile le conteneur des
filtres déjà rendu, sans doublon. Décisions reportées dans `decisions.md` (« Validées », 2026-09-24).
Rattachés au chantier : `R-47` (étape 2b), `R-162` (étape 2c), `R-163` (étape 2a), `R-164` (étape 3).
Reste ouvert jusqu'à la dernière étape.

### R-49 · 🟡 · ouvert · 2026-09-06 — le cul-de-sac « zéro résultat » est atteignable en deux clics

Quand la recherche ne rend rien, toutes les distributions sont vides, donc tous les `<fieldset>`
sont `hidden` (`@if ($values === [])`), donc **il ne reste que « Tout effacer »**. Le comptage
disjonctif couvre le cas où l'on relâche une valeur de la facette qui contraint ; il ne couvre pas
le croisement de deux facettes. C'est documenté comme « limite assumée » — mais aucune mesure ne
dit à quelle fréquence le cas est atteint sur un vrai catalogue, et un message « aucun résultat »
sans aucune facette visible est un mur.

**Au 2026-09-22** (passe documentaire, `R-153`) : en partie. Mesuré : `?q=` sans résultat masque les
quatre blocs de facette ; `?marque=aeris&categorie=parfum` rend zéro carte, mais les facettes
catégorie et marque restent visibles.

### R-50 · ⚪ · ouvert · 2026-09-06 — `ItemList` ne publie pas `numberOfItems`

Détail SEO, une clé.

### R-51 · 🟡 · ouvert · 2026-09-06 — le mode `submit` par défaut est peut-être le mauvais défaut ici

`apply_mode` vaut `submit` par défaut, justifié par « un gros catalogue, où chaque case cochée
coûterait une recherche ». Le catalogue de ce projet compte 76 produits publiés (vérifié). Sur ce
volume, `immediate` est probablement le bon réglage — et le bouton « Appliquer les filtres » est
aujourd'hui rendu et inerte.

**Au 2026-09-22** (passe documentaire, `R-153`) : `submit` est toujours le défaut du code
(`ApplyMode::OnSubmit`), mais le bouton n'est plus inerte depuis `R-20`. Le gabarit publié
(`config/meilifacets.php.stub`) écrit, lui, `'immediate'`. `Q-24` reste ouverte.

### R-52 · 🟡 · **fermé le 2026-09-07** · ouvert le 2026-09-06 — pas de `preconnect` vers l'origine du moteur

Relevé par l'audit du 2026-09-04, jamais fait : `MEILI_PUBLIC_URL` est une seconde origine, donc le
premier filtre paie DNS + TCP + TLS.

**Mesuré le 2026-09-07**, depuis l'hôte comme un visiteur : `dns=2 ms`, `tcp=0,3 ms`,
**`tls=60 ms`** — 65 ms au total, sur une machine qui se parle à elle-même. Sur mobile en 4G, ces
trois postes coûtent couramment 200 à 500 ms, payés au moment précis où le visiteur attend une
réponse instantanée.

**Un point de l'audit était plus alarmiste que la réalité** : il comptait un préflight CORS à chaque
requête. Vérifié — Meilisearch renvoie `access-control-max-age: 86400`, donc le préflight est mis en
cache 24 heures. Un aller-retour la première fois, pas à chaque recherche.

#### Livré le 2026-09-07 — et c'est la première tranche de R-21

La question « où le module apprend-il l'adresse publique » n'avait jamais été posée : **personne ne
lisait `MEILI_PUBLIC_URL`**, ni le module, ni MeiliScout, ni le thème. Elle dormait dans `.env`
depuis l'installation.

Trois formes étaient possibles ; `env()` en direct est écartée — Laravel rend `null` dès que le
projet lance `config:cache`, et le `preconnect` disparaîtrait en silence en production. Retenu :
**`config('meilifacets.browser.url')` et `.key`, déclarées par le projet**, parce qu'une clé
déclarée par le module gagnerait sur celle du projet et s'évaporerait sous `config:cache`.

`BrowserConnection` porte l'adresse, la clé et le nom d'index — c'est exactement ce dont le client
du lot 3c aura besoin (R-21), donc cette tranche pose la connexion une fois pour les deux usages.
Elle expose `origin()`, puisqu'une connexion s'ouvre par origine et non par chemin.

`Preconnect` n'émet que sur une page de listing, ce qui a fait apparaître que `IndexingPolicy`
portait déjà ce prédicat en privé : il est extrait dans `Http\ListingPage`, seul endroit qui
répond désormais à « cette requête affiche-t-elle un listing ». Deux consommateurs, une définition.

Vérifié : les deux balises sortent sur `/boutique` et sur une archive de catégorie, **et pas** sur
l'accueil ni sur une fiche produit. 122 tests dans le module, 140 dans le projet.

**Reste ouvert** : sans clé de recherche en configuration, le module se tait au lieu de le dire.
C'est un cas pour `meilifacets:doctor` (lot 6), pas pour un journal sur chaque rendu.

### R-53 · 🟠 · fermé le 2026-09-07 — la ligne entre fonctionnement et apparence n'est pas écrite

**Vérifié** : `resources/assets/css/meilifacets.css` contient quatre lignes — la contre-règle
`[hidden]`, et rien d'autre. Aucune feuille du thème `pluralia` ne cible une classe
`meilifacets*` (recherche sur `themes/pluralia/resources/assets/css/`).

Conséquence à l'écran, aujourd'hui : la liste déroulante de tri est un `<ul role="listbox">` brut —
puces visibles, aucun positionnement, aucune superposition. Une fois ouverte par le client, elle
poussera le contenu au lieu de flotter au-dessus. Même chose pour la liste des valeurs de facettes
et la grille de résultats, rendues en `<ul>` nus.

`architecture.md` écrit pourtant : « le module ne pose **aucun style** : ni positionnement, ni
liste sans puces ». D-01 dit l'inverse. Il faut trancher **où passe la ligne** — ma proposition :
le module pose ce sans quoi le composant ne fonctionne pas (positionnement du `listbox`, retrait
des puces, `[hidden]`), jamais ce qui relève de l'apparence (couleurs, espacements, typographie,
états de survol). *Voir Q-28.*

**Tranché le 2026-09-07, et pas dans le sens proposé ci-dessus** : le module livre l'apparence par
défaut de ce qu'il rend — le **tri** (bordure, panneau solidaire, coche, survol, ouverture animée),
la **colonne de facettes** (espacements, listes sans puces ni indentation, case et libellé alignés,
compteur en fin de ligne) et ses **boutons** (« Appliquer », « Tout effacer », pagination, page
courante marquée). Neutre au sens strict : ni famille de police, ni taille absolue, ni couleur de
marque. La grille de résultats est mise en colonnes (`minmax(var(--meili-card), 1fr)`) ; l'intérieur de la carte reste au thème. Décision et coût dans `decisions.md`, « Le tri est livré habillé, le thème le
remplace ». La prose fausse d'`architecture.md` est corrigée dans la foulée.

### R-54 · 🟠 · ouvert · 2026-09-06 — l'objectif « sortir du coût de WordPress » n'est tenu qu'à moitié

D-04 rappelle le besoin d'origine. Voici où on en est, mesuré et lu :

| Chemin | Coût WordPress | État |
| --- | --- | --- |
| Premier rendu d'une URL de listing | **complet** — cœur WP, plugins, thème, requête principale de l'archive, WooCommerce | inchangé, et assumé par une décision (`decisions.md`, « requête principale conservée ») |
| Filtre, tri, page suivante | **nul** — navigateur → moteur en direct | conçu, pas encore branché (R-20) |

Autrement dit : le gain visé n'existe aujourd'hui sur **aucun** chemin. Sur le premier rendu il n'a
jamais été cherché ; sur les suivants il n'est pas encore livré.

Trois choses méritent d'être dites explicitement, parce qu'elles décident de la suite :

1. **Le premier rendu paie toujours WordPress en entier**, y compris sur une URL filtrée. C'est
   même pire qu'avant sur ce point précis : le listing ajoute une recherche Meilisearch **par-dessus**
   la requête principale de l'archive, qui n'a pas été allégée (`pre_get_posts`, `no_found_rows` :
   listés « en attente de validation » depuis le début).
2. **`ClientFactory::getSearchClient()` ajoute un DNS et un `/health` avant la première recherche**
   (R-19) — soit exactement le genre de coût que le module existe pour supprimer.
3. **Varnish ne protège pas le public qui convertit** : tout visiteur portant un cookie panier
   passe en `pass` et paie un rendu PHP complet à chaque URL filtrée (`pieges.md`, audit du
   2026-09-04).

Si le gain de latence est l'objectif premier, alors la première mesure à faire n'est pas le client
JavaScript : c'est de chronométrer un rendu de `/boutique` filtrée sur un catalogue réel, avec et
sans allègement de la requête principale. Aucune mesure de ce genre n'existe à ce jour.

**Au 2026-09-22** (passe documentaire, `R-153`) : en partie. Filtrer, trier et paginer ne rechargent plus
la page (`R-20`) ; le premier rendu est inchangé ; le point sur `R-19` reste vrai.

### R-55 · 🟠 · **fermé le 2026-09-06** (T-38) · ouvert le 2026-09-06 — le module ne savait pas se vérifier lui-même

Relevé en cherchant à écrire un hook qui ne dépende pas de Pluralia. **Vérifié le 2026-09-06** :

| | État |
| --- | --- |
| `require-dev` du module | vide — ni `phpunit/phpunit`, ni `laravel/pint` |
| `phpunit.xml`, `pint.json` du module | aucun des deux ; ceux du projet servent |
| 16 tests `Unit` | autonomes — `PHPUnit\Framework\TestCase` pur, aucune dépendance projet |
| 2 tests `Feature` | dépendent de `Tests\TestCase` **de Pluralia**, donc de son `bootstrap/app.php` |
| nom de suite `Modules` | déclaré dans le `phpunit.xml` de Pluralia, pas dans le module |
| `npm test` | autonome — Node, aucune dépendance |

Conséquence directe : **aucune commande de vérification du module n'est indépendante du projet
hôte.** Un module destiné à être installé ailleurs (D-01) ne peut donc pas emporter sa propre
recette. C'est aussi ce qui rend un hook impossible à écrire proprement aujourd'hui — il coderait
en dur un chemin et un lanceur (`ddev`) qui appartiennent à Pluralia.

Ce n'est pas grave en soi ; c'est simplement une brique qui manque, et qui n'était identifiée
nulle part.

**Fermé le 2026-09-06 par T-38.** Le module a désormais ses propres outils : `require-dev`
(phpunit, pint, rector, rector-laravel), `phpunit.xml`, `pint.json`, `rector.php`, un
`tests/bootstrap.php`, et des scripts Composer. `composer check` vérifie formatage, Rector,
103 tests PHP et 49 tests Node **sans aucun chemin ni aucun `ddev`** — donc le module emporte sa
recette là où il sera installé.

Ce qui a rendu la chose possible et n'était pas acquis : **le module s'installe seul**. Vérifié —
`amphibee/meiliscout` se résout depuis Packagist, aucun dépôt privé n'est nécessaire, donc pas
besoin d'entrée `repositories` ni de script de résolution de chemins.

Deux effets à connaître :

- **la suite `Feature` reste dépendante d'un hôte** (`CardComponentTest`, `ResultsComponentTest` rendent
  du Blade et utilisent `Tests\TestCase` de Pluralia). Elle est déclarée à part et lancée depuis le
  projet. La rendre autonome demanderait un `TestCase` propre au module montant `illuminate/view`
  seul — travail à part, voir I-09 ;
- **la boucle de retour passe de 2,9 s à 38 ms** pour les tests autonomes : 103 tests hors
  WordPress contre 121 à travers le projet.

Ce que Rector a trouvé et ce qui a été appliqué : cinq règles sur trois fichiers — première classe
citoyenne à la place d'une fonction fléchée déléguante, `readonly` sur une classe anonyme de test,
types de retour de fonctions fléchées, `assertCount` à la place d'un `assertSame` sur `count()`.
Quatre règles ont été **écartées nommément dans `rector.php`, avec le numéro du constat qu'elles
masqueraient** : `AppToResolveRector` (renommerait le service locator de R-04 au lieu de le
supprimer), `StringCastAssertStringContainsStringRector` (ajouterait des `(string)` plutôt que de
typer le plan de R-02), plus deux qui nuisent à la lisibilité.

### R-56 · 🟡 · ouvert · 2026-09-06 — `check-parameters` ne détecte pas deux taxonomies mappées sur le même nom

`ReservedParameters::conflicts()` teste chaque paramètre contre les query vars publiques de
WordPress et contre la liste que Varnish efface. Il ne compare **jamais les paramètres entre eux**,
et `UrlParameters::all()` peut rendre une liste comportant des doublons sans que rien ne le
signale.

Deux taxonomies mappées sur le même nom **au sein d'un même listing** partageraient donc
silencieusement leur paramètre : `StateReader::facets()` lirait la même valeur d'URL pour les deux,
et cocher une valeur en cocherait une homonyme dans l'autre facette.

Le cas légitime existe et doit rester permis : `product_cat` et `category` peuvent tous deux
prendre le nom `categorie`, puisqu'un listing ne mélange jamais les types de contenu (D-07) et
qu'une seule des deux taxonomies est lue sur une page. **La vérification doit donc porter sur les
facettes d'un même listing, pas sur la configuration entière** — ce qui change la nature de la
commande : elle passe d'un contrôle de configuration à un contrôle par listing.

**Élargi le 2026-09-22** (passe documentaire, `R-153`) : le même aveuglement couvre une taxonomie
mappée sur un paramètre réservé du module. `UrlParameters::all()` rend alors deux fois le même nom, et
rien ne le relève : sur `sort`, `q` ou `pg`, `check-parameters` se tait ; sur `min_price` ou
`max_price`, la taxonomie ne reçoit que l'avertissement prévu pour la borne (`D-h`). Lu dans la source.
`prix.md` promettait l'inverse (« `UrlParameters::taxonomies()` écarte déjà les réservés ») ; annoté.

### R-57 · 🟡 · ouvert · 2026-09-06 — le client n'a aucune source de libellés

*Formulation corrigée le jour même. Ce constat affirmait d'abord que `get_terms()` au rendu violait
la règle du projet. C'est faux : la règle est **temporelle**, pas catégorielle — au premier rendu
WordPress construit déjà la page et peut être interrogé ; c'est **à partir du premier filtre** que
plus rien ne doit repasser par PHP, sous peine de perdre l'instantanéité. Les trois requêtes de
`WordPressTermLabels` sont donc légitimes.*

Ce qui reste vrai, et qui est la vraie forme du constat : **une fois le client aux commandes, il
n'a aucun moyen d'obtenir un libellé.** Meilisearch ne lui renvoie que des slugs et des comptes. Il
ne peut donc afficher que les valeurs déjà présentes dans le HTML servi — ce qui est cohérent avec
le parti pris « nœuds stables, masqués quand le compte tombe à zéro », mais ferme définitivement le
cas d'une valeur qui devrait réapparaître.

Deux conséquences, à traiter le jour où le cas se présente :

- une valeur absente de la distribution initiale ne peut jamais entrer dans le DOM ;
- `ChildTermsFacet` (D-07) hérite du même plafond : les pastilles d'un rayon sont figées à celles du
  rendu serveur.

La sortie connue, si le besoin apparaît : projeter le couple slug → libellé à l'indexation, comme la
carte l'est déjà. À ne pas faire par anticipation — R-12 deviendrait bloquant, puisqu'un terme
renommé rendrait le dictionnaire périmé.

### R-58 · 🟠 · **fermé le 2026-09-06** · ouvert le 2026-09-06 — un tri et une page numérotée entraient dans l'index

`RobotsPolicy` ne déclenchait le `noindex` que sur une facette remplie. `?sort=price_asc` et
`?pg=10` restaient indexables — soit, pour le tri, exactement le même contenu dans un autre ordre,
et pour la pagination une page sans valeur propre qui dilue le rayon canonique.

La justification inscrite dans le code était périmée : *« a paginated page must stay indexable, or
the products it holds lose their only internal link »*. La décision du 2026-09-06 avait transformé
pagination et tri en `<button>` — il n'existe plus aucun lien interne vers la page 2, et
`architecture.md` l'admettait déjà (« la découverte des fiches repose entièrement sur le sitemap
Yoast »). Le commentaire n'avait pas suivi la décision.

**Fermé le 2026-09-06**, après R-16 : la garde de contexte a rendu l'élargissement sans danger.
`appliesTo()` couvre désormais tout paramètre déclaré par `UrlParameters::all()`, donc les noms
réservés y compris renommés. Vérifié en HTTP :

| URL | Avant | Après |
| --- | --- | --- |
| `/boutique` | index | index |
| `/boutique?categorie=cheveux` | noindex | noindex |
| `/boutique?sort=newest` | **index** | **noindex** |
| `/boutique?pg=2` | **index** | **noindex** |
| `/?q=bonjour` | **noindex si `q` avait été inclus** | index |
| `/?categorie=cheveux` | **noindex** | **index** |

### R-59 · 🔴 · **fermé le 2026-09-06** · ouvert le 2026-09-06 — chaque URL de listing émettait `noindex` **et** une canonique vers une autre URL

**Mesuré le 2026-09-06** en HTTP, sur le site local :

| URL | robots | canonical |
| --- | --- | --- |
| `/` | `index, follow` | `/` |
| `/?q=bonjour` | `index, follow` | `/` — Yoast retire le paramètre inconnu, comportement sain |
| `/boutique` | `index, follow` | `/boutique` |
| `/boutique?sort=newest` | **`noindex, follow`** | **`/boutique`** |
| `/boutique?categorie=cheveux` | **`noindex, follow`** | **`/boutique`** |

`decisions.md` porte pourtant : « Aucune canonique n'est posée vers le chemin nu — `noindex` et une
canonique pointant ailleurs sont deux signaux contradictoires ». La décision décrivait ce que **le
module** fait ; elle ne dit rien de ce que **la page** émet. **Yoast pose cette canonique de
lui-même**, et personne n'avait vérifié le rendu réel.

Le risque est documenté par Google : associer `noindex` à une canonique pointant ailleurs peut faire
**transférer le `noindex` vers la cible de la canonique**. La cible est ici `/boutique`. La
fermeture de R-58 vient par ailleurs d'étendre le `noindex` au tri et à la pagination, donc de
multiplier les URLs concernées.

Trois sorties possibles :

| | Effet |
| --- | --- |
| **Supprimer la canonique** quand le module pose `noindex` (filtre `wpseo_canonical`) | Un seul signal, sans ambiguïté. Introduit une dépendance à un filtre Yoast |
| **Canonique auto-référente** + `noindex` | La paire sûre et standard. Demande de reconstruire l'URL courante nous-mêmes |
| **Renoncer au `noindex`** et s'en remettre à la canonique | Contredit la règle du projet, et les URLs filtrées resteraient explorées |

**Fermé le 2026-09-06 : la canonique est supprimée quand le module pose `noindex`.**

Deux choses apprises en le corrigeant, aucune des deux évidente :

**Yoast le faisait déjà — sur ses propres robots.** `Canonical_Presenter::get()` commence par
`if (in_array('noindex', $this->presentation->robots, true)) return '';`. Mais cette évaluation est
interne à Yoast ; la nôtre est écrite sur `wp_robots`, que ce chemin ne lit jamais. Les deux ne se
parlaient pas. Un seul filtre `wpseo_canonical` suffit à les réconcilier.

**Et il fallait passer après l'intégration WooCommerce de Yoast.** `Integrations\Third_Party\WooCommerce`
enregistre son propre `wpseo_canonical` à la priorité 10 et **réécrit la canonique de la page
boutique**. À priorité égale, il passait après nous et effaçait notre valeur. Symptôme mesuré :
le correctif fonctionnait sur `/` et sur `/categorie-produit/cheveux`, et restait sans effet sur
`/boutique` — la page qui compte. Le module s'inscrit donc à 20.

Aucune dépendance conditionnelle à écrire : sans Yoast, `wpseo_canonical` n'est jamais appliqué,
donc le filtre est inerte. Et WordPress n'émet aucune canonique sur une archive — `rel_canonical()`
sort immédiatement hors d'un contenu singulier.

Vérifié en HTTP le 2026-09-06 :

| URL | robots | canonical |
| --- | --- | --- |
| `/` | index | `/` |
| `/?q=bonjour` | index | `/` |
| `/boutique` | index | `/boutique` |
| `/boutique?sort=newest` | noindex | **aucune** |
| `/boutique?pg=2` | noindex | **aucune** |
| `/boutique?categorie=cheveux` | noindex | **aucune** |
| `/categorie-produit/cheveux` | index | `/categorie-produit/cheveux` |
| `/categorie-produit/cheveux?marque=lumen` | noindex | **aucune** |

`RobotsPolicy` a été renommée **`IndexingPolicy`** : avec deux balises à sa charge, l'ancien nom
était devenu faux.

### R-60 · 🔴 · **fermé le 2026-09-06** (B+) · ouvert le 2026-09-06 — deux paginations coexistaient, et c'est celle que le module ignore qui est indexée

Analyse dédiée, mesures du 2026-09-06 sur l'installation locale (76 produits publiés).

#### Ce qui est mesuré

Le module pagine sur `?pg=`. WordPress pagine sur `/page/N`. Les deux répondent, aucune ne connaît
l'autre.

| URL | Statut | Produits | Premier produit |
| --- | --- | --- | --- |
| `/boutique` | 200 | 16 | Sérum Éclat Vitamine C |
| `/boutique/page/2` | 200 | 16 | **Sérum Éclat Vitamine C** |
| `/boutique/page/3` | 200 | 16 | **Sérum Éclat Vitamine C** |
| `/boutique/page/4` | 200 | 16 | **Sérum Éclat Vitamine C** |
| `/boutique/page/5` | 200 | 16 | **Sérum Éclat Vitamine C** |
| `/boutique/page/6` | 404 | — | — |
| `/boutique?pg=2` | 200 | 16 | Patchs Yeux Défatigants |
| `/boutique?pg=5` | 200 | 10 | Crème Nuit Régénérante |

**La pagination du module fonctionne** — `?pg=` sert bien des produits différents. C'est la
pagination native qui ment : `/page/2` à `/page/5` servent quatre fois la première page.

Trois aggravants, tous mesurés :

- **chaque page porte une canonique auto-référente** (`/boutique/page/2` → elle-même) et
  `index, follow` : elles sont donc présentées à Google comme quatre pages distinctes et
  canoniques, toutes identiques ;
- **la chaîne s'auto-entretient** : `/boutique` porte `rel="next"` vers `/page/2`, qui porte
  `rel="next"` vers `/page/3`, et ainsi jusqu'à `/page/5`. Un robot entré sur la boutique parcourt
  toute la série ;
- **c'est le seul vecteur de découverte** — le sitemap ne contient aucune URL paginée
  (`page-sitemap.xml` ne liste que `/boutique`), et le corps de la page ne contient aucun lien
  `/page/N`. Sans le `rel="next"` de Yoast, la série serait inatteignable.

#### L'échelle

Aujourd'hui : **4 URLs dupliquées**, et une seule catégorie sur 81 dépasse 16 produits — donc le
problème est presque entièrement concentré sur `/boutique`. Les archives de catégorie renvoient
404 dès `/page/2`, faute de contenu.

Sur un vrai catalogue, la règle est : **une URL dupliquée par page de chaque archive de plus de 16
produits**. À 5 000 produits, `/boutique` seule en produit 312, auxquelles s'ajoutent celles de
chaque rayon fourni. C'est un budget d'exploration dépensé à lire quatre cents fois la même page.

#### Pourquoi ça existe

Rien n'est cassé : deux systèmes corrects s'ignorent. `StateReader` lit `pg`, jamais `paged`. La
requête principale de WordPress, elle, est conservée par décision (elle porte le routage et le SEO)
et continue de paginer pour son propre compte. Yoast lit cette requête-là pour poser `rel="next"`
et la canonique.

C'est le même défaut de fond que R-59 : la décision « pagination en query var » décrivait ce que le
module fait, sans regarder ce que la page continue d'émettre.

#### Les issues

| | Ce que ça donne | Ce que ça coûte |
| --- | --- | --- |
| **A. Le listing lit `paged`, `/page/N` devient indexable** | Les URLs paginées servent le vrai contenu, `rel="next"` redevient exact, et les fiches des pages 2+ gagnent un chemin explorable | **Contredit la décision du 2026-09-06** : « tri, recherche et pagination ne doivent pas être indexables ». Et laisse deux formes d'URL pour une même page |
| **B. Fermer la série** | `noindex` sur `is_paged()`, `rel="next"`/`prev` supprimés. Plus aucune page paginée dans l'index, plus aucune invitation à explorer | `/page/2` continuerait de servir la page 1 : un visiteur arrivé par un lien ancien verrait le mauvais contenu |
| **B+. Fermer la série, et dire la vérité** | Idem, **plus** : `StateReader` lit `paged` en repli de `pg`, donc `/page/2` sert la vraie page 2 — sans être indexée | Une ligne de lecture de plus, et une règle de priorité à écrire (`pg` gagne sur `paged`) |
| **C. Ne rien faire** | — | La série grandit avec le catalogue |

#### Ce qui est retenu et pourquoi

**B+.** Elle est la seule à satisfaire les trois exigences en présence :

1. **la règle du projet** — aucune page paginée dans l'index. A la contredit frontalement ;
2. **ne pas mentir** — une URL qui répond `200` doit servir ce qu'elle annonce. B seule laisse
   `/page/2` afficher la page 1 ;
3. **ne rien inventer** — `paged` est déjà résolu par WordPress au premier rendu, sa lecture ne
   coûte rien et ne contrevient pas à la règle du transport (elle est temporelle : WordPress au
   premier rendu, jamais au filtrage).

Lire `paged` n'entre pas en conflit avec l'interdiction des noms `page`/`paged` comme paramètres du
module : cette interdiction porte sur le **double filtrage** qu'un paramètre homonyme provoquerait.
Ici on ne déclare rien, on lit un contexte que WordPress a déjà établi.

#### Ce que B+ demande

1. `IndexingPolicy` : `noindex` aussi sur `is_paged()`, indépendamment des paramètres d'URL.
2. Suppression de `rel="next"` et `rel="prev"` sur une page de listing — filtres
   `wpseo_next_rel_link` et `wpseo_prev_rel_link` (vérifiés présents dans Yoast 28.3), inertes sans
   Yoast comme `wpseo_canonical`.
3. `StateReader` : `paged` en repli quand `pg` est absent.
4. Un test de non-régression sur la règle de priorité entre les deux.

#### Reste ouvert après B+

La découverte des fiches situées au-delà de la première page repose entièrement sur le sitemap
Yoast — qui liste bien les 76 produits (vérifié : 77 entrées dans `product-sitemap.xml`). C'était
déjà l'état documenté depuis le passage des contrôles en boutons ; B+ ne le dégrade pas, mais le
rend définitif. Si ce sitemap venait à être tronqué ou désactivé, l'argument tomberait.

#### Livré le 2026-09-06

Trois changements, dans `IndexingPolicy` et `CurrentListing` :

- `isSecondaryView()` remplace `isFilteredView()` — le concept couvre désormais « une vue qui n'est
  pas le chemin nu », donc la pagination native (`is_paged()`) autant que les paramètres ;
- deux filtres `wpseo_next_rel_link` et `wpseo_prev_rel_link` rendent une chaîne vide sur une page
  de listing : la chaîne d'exploration est coupée à sa source ;
- `CurrentListing::requestQuery()` fusionne `get_query_var('paged')` sous le nom réservé de la
  page, **quand aucun paramètre ne le porte déjà**. `/page/N` sert donc la vraie page N.

Vérifié en HTTP :

| URL | robots | canonical | rel next/prev | premier produit |
| --- | --- | --- | --- | --- |
| `/boutique` | index | `/boutique` | **0** | Sérum Éclat Vitamine C |
| `/boutique/page/2` | **noindex** | **aucune** | **0** | **Patchs Yeux Défatigants** |
| `/boutique/page/3` | noindex | aucune | 0 | **Lait Corps Amande Douce** |
| `/boutique/page/5` | noindex | aucune | 0 | Crème Nuit Régénérante (10 produits) |
| `/boutique/page/6` | 404 | — | — | — |
| `/boutique?pg=2` | noindex | aucune | 0 | Patchs Yeux Défatigants — identique à `/page/2` |
| `/boutique/page/2?pg=4` | noindex | aucune | 0 | **Gloss Repulpant Miel** — `pg` l'emporte |
| `/categorie-produit/cheveux` | index | soi-même | 0 | inchangée |
| `/` | index | `/` | 0 | intacte |

Les quatre URLs dupliquées ont disparu : `/page/2` à `/page/5` servent quatre pages distinctes, et
aucune n'entre dans l'index. La règle de priorité `pg` > `paged` est vérifiée en conditions réelles
et couverte par un test unitaire.

### R-61 · 🟠 · **fermé le 2026-09-08** · ouvert le 2026-09-06 — une page au-delà de la dernière annonçait « aucun résultat »

**Mesuré le 2026-09-06** : `/boutique?pg=2&categorie=cheveux` répond `200` et affiche « Aucun
résultat n'a été trouvé. » Or la catégorie « cheveux » contient 14 produits — ils sont tous sur la
page 1. Le message ment : il n'y a pas *aucun* résultat, il n'y a pas *cette page-là*.

Le cas est atteignable sans rien forger : il suffit d'être en page 3 d'un listing et de cocher une
facette qui réduit le résultat à une page. `ListingState::page` n'est pas remis à 1 côté serveur —
le client le fait (`Listing.toggle()` pose `page = 1`), mais le client n'existe pas encore, et une
URL partagée ou un rechargement passent par le serveur.

Deux réponses possibles, à ne pas confondre :

| | Effet |
| --- | --- |
| **Ramener à la dernière page existante** | `pg=2` sur un résultat d'une page servirait la page 1. Le visiteur voit des produits, jamais un cul-de-sac. Mais l'URL ne décrit plus ce qui est affiché |
| **Distinguer les deux messages** | « Aucun résultat » quand le total est nul ; « Cette page n'existe plus » avec un retour à la première quand le total est non nul mais la page hors bornes |

Antérieur au correctif de R-60 et indépendant de lui : `?pg=2&categorie=cheveux` se comportait déjà
ainsi. Ce que R-60 change, c'est que le cas devient atteignable par deux chemins d'URL au lieu d'un.

**Atténué le 2026-09-07 (lot 3c-2), pas fermé.** `PageWindow` (JS) ramenait `current` dans les
bornes, `Pagination` (PHP) ne le faisait pas : les deux miroirs divergeaient, et c'est le lot qui
avait introduit l'écart. `Pagination` ramène désormais `current` comme son miroir. Mesuré sur
`/boutique?pg=999` — avant : « Précédent » pointait vers la page **998**, aucune page n'était
marquée courante ; après : « Précédent » pointe vers 4, la page 5 porte `aria-current`, « Suivant »
est masqué.

Le clampage ne touche **que le widget** : la requête moteur lit `$state->page` dans `QueryPlan`,
pas `Pagination::offset()`. La grille restait donc vide et le message restait « Aucun résultat ».

**Fermé le 2026-09-08 : le message dit laquelle des deux vérités.** Ni redirection, ni changement de
code HTTP — la troisième voie du tableau ci-dessus, choisie en séance. `Pagination` garde côte à
côte la page **demandée** (`asked`) et celle **qui existe** (`current`), et `isPastTheEnd()` répond
vrai quand il y a des résultats mais pas sur cette page-là.

**Mesuré le 2026-09-08** sur quatre cas :

| URL | Message |
| --- | --- |
| `/boutique?pg=10000` | « Il n'y a rien sur cette page. » |
| `/boutique?categorie=cheveux&pg=9` | « Il n'y a rien sur cette page. » |
| `/boutique?categorie=inexistante` | « Aucun résultat n'a été trouvé. » |
| `/boutique` | masqué |

Le deuxième cas est celui qui comptait : il s'atteint **sans rien forger** — être en page 3, cocher
une facette qui réduit le résultat à une page, partager le lien.

**Ce qui a décidé** : `/boutique/page/10000/` répond déjà `404` par WordPress, et toute vue paginée
est en `noindex, follow` (R-58). Le SEO n'était donc pas en jeu ; restait un visiteur devant une
page morte, et un message qui lui mentait — il y a bien 17 produits, simplement pas là.

⚠️ **Le client ne remplace pas ce texte.** `ResultsView` bascule l'attribut `hidden` du message, il
n'en réécrit pas le contenu : après un retour arrière vers une URL forgée, le visiteur verrait le
message du rendu serveur. Inatteignable par les boutons, qui sont bornés. À reprendre si le lot 3c-3
publie un motif pour ce message, comme il le fera pour l'état d'attente.
*Repris le 2026-09-16 sous R-137 (#5) : Blade rend les deux messages, le client révèle le bon.*

Lié à R-42 (`maxTotalHits`), qui produit le même symptôme pour une autre raison.

### R-62 · 🟠 · **fermé le 2026-09-06** · ouvert le 2026-09-06 — la couche vue portait des décisions qui ne lui appartenaient pas

Revue dédiée des huit composants Blade, demandée le 2026-09-06 après que la revue initiale n'en
eut relevé que trois symptômes isolés (R-04, R-15, R-17) sans jamais examiner la couche comme un
tout.

**1. Le composant de base d'un module générique dépend de WooCommerce.**

```php
abstract class ListingComponent extends ContractComponent
{
    public function __construct(public string $name = ProductListing::NAME) {}
```

Tous les composants héritent d'un défaut qui désigne le listing produit. Sur un projet sans
WooCommerce, `<x-meilifacets::results />` sans attribut `name` cherche un listing `products` qui
n'existe pas. C'est la même racine que R-09, dont la fermeture est donc **partielle** : le
constructeur de `ProductListing` refuse bien de se construire, mais la vue continue de le nommer
par défaut. Le défaut doit venir de la configuration, ou ne pas exister.

**2. Service locator au lieu d'injection**, trois sites : `app(CurrentListing::class)` dans
`ListingComponent`, `app(IndexingPolicy::class)` dans `Results`. Laravel résout depuis le conteneur
tout paramètre de constructeur qu'un attribut Blade ne fournit pas — l'injection est disponible,
elle n'est pas utilisée. Effet direct : aucun de ces composants ne se teste sans application bootée.

**3. Loi de Demeter, systématiquement.** `ResolvedListing` est conçue comme la façade du listing —
elle expose `facets()`, `cards()`, `activeFilterCount()`, `parameterFor()`. La moitié des appelants
la traversent quand même :

| Où | Ce qui est écrit |
| --- | --- |
| `Sort::build()` | `$resolved->listing->sorts()`, `$resolved->state->sort` |
| `Results::itemList()` | `$resolved->pagination()->offset()` |
| `facets.blade.php` | `$resolved->listing->applyMode()->value`, `->applyMode()->needsButton()` |
| `reset.blade.php` | `$listing()->state->isDefault()` |

Chaque traversée fige la structure interne de `ResolvedListing` dans un gabarit qu'un thème peut
surcharger — donc dans du markup qui n'est pas à nous.

**4. Des objets métier construits dans la vue.** `new SortChoices(...)` dans `Sort`,
`new ItemList(...)` dans `Results`, `new CardDocument(...)` et `new CardImage(...)` dans `Card`. Le
composant décide *quoi* construire autant qu'il décide *comment* l'afficher.

**5. Configuration lue depuis le composant** — `config('meilifacets.card.eager', …)` dans
`Results`. Voir R-05 : c'est le provider qui doit lire la configuration.

**6. Deux schémas d'identifiants ad hoc, et un préfixe littéral répété.**
`'meilifacets-'.$this->name.'-sort'` d'un côté, `'meilifacets-'.$facet->taxonomy.'-'.$value->slug`
de l'autre. Le second ne porte pas le nom du listing (R-15), et `'meilifacets-'` est une chaîne
magique écrite deux fois dans un module qui interdit les chaînes littérales.

**7. Les noms de crochets sont des chaînes littérales dans les vues.** `{{ $hook('results') }}`,
`{{ $hook('card-template') }}`… `ContractComponent::hook()` reçoit une chaîne et fait
`Hook::from()`. La règle « pas de chaîne littérale, une énumération pour un ensemble fermé » est
enfreinte à l'endroit exact où l'ensemble fermé compte le plus — le contrat que le client vérifie —
et une faute de frappe y lève un `ValueError` (R-17), contre la promesse de dégradation.

**8. Mémoïsation à la main**, deux fois (`$this->choices ??=`, `$this->eager ??=`). Bénin en soi,
mais c'est le signe que le composant porte un calcul dont il n'est pas propriétaire.

#### Direction proposée

Aucune de ces corrections n'est urgente ni risquée ; elles se font ensemble ou pas du tout, sous
peine de mélanger deux styles dans la même couche.

- `ListingComponent` **injecte** `CurrentListing`, et son `$name` n'a pas de défaut WooCommerce ;
- `ResolvedListing` devient la **seule** surface que les vues touchent — elle gagne `applyMode()`,
  `sortChoices()`, `isPristine()`, `offset()`, et les traversées disparaissent ;
- les identifiants passent par un objet unique, préfixe compris ;
- les crochets s'écrivent avec l'énumération plutôt qu'avec une chaîne.

Ce qui reste **légitimement** dans les composants : `inputType()` (traduire un mode de sélection en
type d'`input` est de la présentation), `countLabel()` (une formulation destinée à un lecteur
d'écran), et `priority()` (une décision de chargement d'image).

#### Deux points révisés avant de coder

**Le point 4 était en partie faux.** `SortChoices` vit dans `View\`, `ItemList` dans `Seo\` : les
construire dans un composant est légitime, ce sont des objets de présentation. Ce qui ne l'était
pas, c'est la traversée `$resolved->pagination()->offset()` et le `app(IndexingPolicy::class)`.

**Le point 7 est accepté plutôt que corrigé** (voir R-17). Le contrat promet qu'une surcharge de
thème *périmée* dégrade vers le rendu serveur. Une faute de frappe dans un `$hook()` n'est pas un
contrat périmé, c'est une erreur PHP dans une vue — et toute erreur de vue Blade produit déjà un
500. `Hook::from()` qui lève est cohérent avec le reste ; le rendre tolérant masquerait un bug au
lieu de le montrer.

#### Livré le 2026-09-06

`ListingComponent` injecte `CurrentListing` et n'a plus de défaut WooCommerce : une vue qui ne
nomme aucun listing obtient **le seul déclaré**, et l'ambiguïté est refusée plutôt que devinée
(`ListingRegistry::sole()`). `Results` reçoit `IndexingPolicy` et un `CardSettings` construit par le
provider. `ResolvedListing` gagne `name()`, `isPristine()`, `applyMode()`, `sorts()`,
`currentSort()`, `offset()`, et ses deux propriétés passent en privé — plus aucune traversée ne
reste, ni en PHP ni en Blade. `ElementId` porte le préfixe une fois et le nom du listing toujours.

**Vérification qui compte** : les 123 tests de la suite sont passés au vert **alors que le site
répondait 500** sur toutes ses pages — `sole()` rend une `ResolvedListing`, dont j'appelais
`name()` qui n'existait pas encore. Aucun test ne couvre les composants (R-30), et c'est le curl
qui a trouvé la panne. Deux unités neuves sont désormais testées (`ListingRegistry::sole()`,
`ElementId`), mais le trou de couverture sur la couche vue reste entier.

Après correction : `/boutique` 16 produits, `?categorie=cheveux` 14, `/page/2` la vraie page 2,
robots et canoniques inchangés, 63 identifiants portant `meilifacets-products-…`, 128 tests verts.

### R-63 · ⚪ · **fermé le 2026-09-06** · ouvert le 2026-09-06 — un même concept portait deux noms

`ListingState::isDefault()` existait depuis le lot 3b. `ResolvedListing::isPristine()` a été ajouté
une heure plus tôt, dans la refonte de la couche vue (R-62), pour déléguer au premier. Deux noms
pour la même question, dont l'un sur une façade publique.

Aligné sur `isPristine()` : « default » ne dit pas de quoi — l'état par défaut, le listing par
défaut ? — quand « pristine » dit ce qui compte, que le visiteur n'a touché à rien.

**Deux enseignements de méthode, qui valent plus que la correction :**

1. **La passe de lisibilité a examiné le code déplacé, pas le vocabulaire d'ensemble.** Ajouter une
   méthode à une façade sans regarder comment s'appelle déjà ce qu'elle délègue est la manière
   exacte dont deux noms s'installent pour un concept. À ajouter à la passe : *un nom introduit se
   compare à celui de ce qu'il enveloppe*.

2. **Le signal traînait dans la suite de tests depuis le début.** Le test s'appelait
   `it_reports_an_untouched_listing_as_pristine` et assertait `isDefault()` : le nom du test disait
   déjà que le nom de la méthode était mauvais. Un écart entre le nom d'un test et celui de la
   méthode qu'il exerce est un constat en attente.

### R-64 · 🟡 · **fermé le 2026-09-06** · ouvert le 2026-09-06 — la catégorie par défaut était proposée comme un rayon

**Mesuré le 2026-09-06** sur `/boutique`, la facette de catégorie rend six valeurs, dont
`non-classe` — « Non classé », la catégorie que WooCommerce assigne d'office à un produit qui n'en
a aucune. Ce n'est pas une famille de produits, c'est un artefact technique, et il est offert au
visiteur comme les cinq autres.

Le symptôme cache deux problèmes qui ne se règlent pas au même endroit :

- **du code** — rien n'exclut cette valeur. `get_option('default_product_cat')` la nomme, mais
  `ChildTermsFacet` n'a aucun mécanisme d'exclusion, et l'exclusion est propre à WooCommerce donc
  ne peut pas vivre dans le module générique ;
- **de la donnée** — son compteur vaut 1 : un produit du catalogue n'a réellement aucun rayon.
  Aucune exclusion ne le range, elle le rendrait seulement invisible dans la facette tout en le
  laissant dans la grille.

Trois issues pour la partie code :

| | Effet |
| --- | --- |
| `Facet` déclare des valeurs exclues | Générique, réutilisable, mais ajoute un réglage à un objet qu'on vient de garder minimal |
| `ProductListing` filtre son `baseFilter()` | `NOT facets.product_cat = "non-classe"` sortirait aussi le produit de la grille — donc invisible en boutique, ce qui est peut-être pire |
| Rien dans le module, la donnée est corrigée | Le plus simple si le cas est accidentel. Mais rien n'empêche qu'il revienne |

Le même piège vaut pour `product_visibility` et `product_type`, taxonomies techniques déjà
filtrables (vérifié dans les réglages d'index) : elles ne sont pas *affichées* aujourd'hui parce
qu'aucune facette ne les déclare, mais rien ne l'interdit.

#### Livré le 2026-09-06 — la partie code

**Ce n'est pas propre à WooCommerce.** Deux conventions nomment la même notion, et les deux sont
lues : `default_term_<taxonomie>`, depuis l'argument `default_term` de `register_taxonomy`, et
`default_<taxonomie>` pour celles qui la précèdent. Mesuré sur cette installation —
`default_category` vaut 1 pour les articles, `default_product_cat` vaut 15 pour les produits, et les
taxonomies plates (`product_brand`, `pa_contenance`, `post_tag`) n'en ont aucune.

**Masqué par défaut**, parce qu'un terme de repli n'est jamais un moyen de parcourir un catalogue :
il dit qu'un contenu n'a été rangé nulle part, ce qui est un fait sur le catalogue, pas une
navigation. Une facette dont le repli est un terme réel choisi par un éditeur déclare
`DefaultTerm::Shown` — une énumération plutôt qu'un booléen, comme `SelectionMode` et `DisplayOrder`
à côté d'elle.

Le tri se fait dans `FacetValues`, avant `within()`, plutôt que dans `Facet` : ce n'est pas une
variation du *niveau* montré mais une règle uniforme, et `Facet` reste un objet de valeur sans
dépendance.

**Le produit reste en boutique** — « Trousse Vide Nomade » est toujours dans la grille, page 4, et
sur son URL. La facette cesse d'offrir une entrée qui ne veut rien dire, elle ne cache pas un
produit.

Vérifié : `/boutique` rend 5 valeurs au lieu de 6, 16 produits inchangés, 118 tests dans le module
et 136 dans le projet.

#### Reste ouvert — la partie donnée

« Trousse Vide Nomade » (#412) n'a toujours aucune catégorie. Le masquage la rend invisible dans la
facette sans la ranger : elle n'est atteignable que par la boutique entière ou par une recherche.
C'est une correction de contenu, pas de code.

### R-65 · 🟠 · ouvert · 2026-09-07 — une adresse de moteur sans schéma désactive tout, en silence

`MEILI_PUBLIC_URL` est l'adresse que le navigateur utilise. Sur Clever Cloud, la forme naturelle
qu'on copie depuis la console est `3ds-staging-meilisearch.cleverapps.io/` — **sans schéma**.

**Mesuré le 2026-09-07** : `Illuminate\Support\Uri` lit une telle valeur comme un *chemin*, donc
`scheme()` et `host()` valent tous deux `null`. `BrowserConnection::origin()` rend une chaîne vide,
`isConfigured()` rend `false`, et il ne se passe **rien** : ni `preconnect`, ni — demain — de client
de recherche. Aucune erreur, aucune trace, aucun avertissement.

Deviner `https` serait une devinette : le module ne sait pas si le moteur est joignable en clair ou
non, et se tromper produit une requête bloquée pour contenu mixte plutôt qu'un message.

Ce qui manque n'est donc pas une normalisation, c'est un **diagnostic** : une commande qui dise
« `meilifacets.browser.url` n'a pas de schéma ; écrivez `https://…` ». C'est le premier candidat
concret pour `meilifacets:doctor` (lot 6), et il vaut aussi pour la clé de recherche absente.

En attendant, `installation.md` doit dire que le schéma est obligatoire.

### R-66 · 🟡 · **fermé le 2026-09-07** · ouvert le 2026-09-07 — le module n'a jamais déclaré sa dépendance à Illuminate

**Mesuré le 2026-09-07** : 14 fichiers sur 90 importaient `Illuminate\Contracts\View\View` (9),
`Illuminate\Support\HtmlString` (4), `Illuminate\View\Component`, `Illuminate\Support\Facades\View`
et `Illuminate\Console\Command`, alors que `composer.json` ne déclarait que `php` et
`amphibee/meiliscout`.

Ça ne cassait jamais **par accident** : aucune classe exercée par la suite autonome n'y touchait —
les 14 sont des composants, le provider ou la commande, que seule la suite `Feature` couvre, dans le
projet hôte.

Fermé en déclarant `illuminate/console`, `illuminate/contracts`, `illuminate/support` et
`illuminate/view` en `^12.0 || ^13.0` — les deux versions de Laravel que visent Pollora 12 et 13.

**Et la déclaration honnête a immédiatement révélé une dépendance implicite de plus** :
`Illuminate\Support\Uri` s'appuie sur `league/uri`, que `illuminate/support` ne déclare qu'en
*suggest*. Dans un projet Laravel complet il est présent parce que le framework le tire ; en
autonome, `Class "League\Uri\Uri" not found`. Ajouté explicitement.

C'est l'argument contre « Laravel sera toujours là » : c'est vrai à l'exécution, et faux dès qu'on
installe le module autrement — ce que la suite autonome fait à chaque exécution.

### R-67 · 🟠 · **fermé le 2026-09-07** · ouvert le 2026-09-07 — la racine du listing ne portait plus son nom

`listing.blade.php` rendait `data-listing="{{ $name }}"`, l'attribut Blade — vide depuis que le
défaut WooCommerce a été retiré de `ListingComponent` (R-62), alors que le composant résout bien le
listing unique. Le client trouvait donc une racine sans nom, ne trouvait aucune description, et
**ne démarrait pas, en silence**.

Corrigé : le markup porte le nom **résolu**, `$listing->name()`.

Deux enseignements :

- **aucun test ne rend `listing.blade.php`** — la suite `Feature` couvre `results` et `card`, pas la
  racine. Quatrième panne de cette semaine trouvée par un `curl` ou un navigateur plutôt que par la
  suite (R-30) ;
- **l'échec était muet.** `listing-page.js` sortait sans un mot quand aucune description ne
  correspondait. Deux `console.error` ont été ajoutés : quand la page ne publie aucune donnée alors
  qu'une racine existe, et quand une racine porte un nom que la page ne décrit pas.

### R-68 · 🟡 · ouvert · 2026-09-07 — le site servi en `http` casse tout son JavaScript, thème compris

**Mesuré le 2026-09-07.** `home`, `siteurl` et `APP_URL` déclarent tous trois
`https://pluralia.ddev.site`, donc `asset()` et `wp_enqueue_*` produisent des URLs en `https`. Une
page ouverte en `http://pluralia.ddev.site` voit alors ses propres scripts comme une autre origine,
et le navigateur les refuse :

```
Access to script at 'https://…/build/theme/pluralia/assets/app-*.js'
from origin 'http://pluralia.ddev.site' has been blocked by CORS policy
```

**Le bundle du thème est bloqué exactement comme celui du module** : en `http`, le site n'a aucun
JavaScript. Ce n'est donc pas un défaut du module, c'est un piège d'environnement — et il a coûté du
temps, toutes les vérifications `curl` de la revue ayant été faites en `http` par habitude. Elles
restent valables pour le HTML servi ; elles ne disaient rien du JavaScript.

À écrire dans `installation.md` : **en local, le site se consulte en `https`**. *Fait.*

**Complété le 2026-09-08 — on n'y arrive pas que par habitude : une URL avec slash final y mène.**

```
https://pluralia.ddev.site/boutique/    301 -> http://pluralia.ddev.site/boutique
https://pluralia.ddev.site/panier/      301 -> http://pluralia.ddev.site/panier
https://pluralia.ddev.site/mon-compte/  301 -> http://pluralia.ddev.site/mon-compte
```

La redirection canonique perd le schéma alors que `home` et `APP_URL` sont tous deux en `https` —
un proxy de confiance / `X-Forwarded-Proto` non honoré. Un lien collé avec un slash final suffit
donc à servir la page sans aucun JavaScript, sans erreur visible. Hors périmètre du module ; à
traiter côté projet.

### R-69 · 🟡 · **fermé le 2026-09-07** (lot 3c-2) · ouvert le 2026-09-07 — la couche DOM n'a aucun test automatisé

`happy-dom` avait été validé comme dépendance de développement pour tester la liaison au DOM. Il n'a
pas été installé : `CardPainter`, `ListingBinding` et `listing-page.js` ont été recettés **à la main
dans un navigateur**, par Playwright, ce qui prouve qu'ils marchent aujourd'hui et ne protège de
rien demain.

Les 58 tests Node couvrent l'état, l'URL, le plan de requête et le contrat — tout ce qui ne touche
pas au document. C'est R-30 sous une autre forme, et la couche DOM est celle qui vient de grossir
le plus.

**Fermé le 2026-09-07.** `happy-dom` installé en dépendance de développement du module,
`tests/js/dom.js` monte un document et un balisage qui reproduit ce que rendent les composants
Blade, hooks et états `hidden` compris. Quatre suites nouvelles : `FacetsView`, `PaginationView`,
`SortCombobox` et `ListingBinding` de bout en bout, `Listing` réel branché sur un moteur et un
historique factices. 49 → 102 tests Node.

Vérifié qu'ils mordent : en retirant le câblage de la pagination et l'appel à `showSelection()`,
cinq tests tombent — dont les trois qui portent le correctif demandé.

### R-70 · 🟠 · **fermé le 2026-09-16** (`R-132`) · ouvert le 2026-09-07 — seul le point d'entrée du client est versionné ; les dix-sept autres fichiers sont figés dans le navigateur

**Mesuré le 2026-09-07.** `ListingScript` inscrit `listing-page.js?ver=<filemtime>` : le point
d'entrée porte bien un cache-buster. Ses imports, eux, sont **relatifs** (`./listing-binding.js`) et
sont donc demandés à leur URL nue — sans `ver`. Or ces fichiers statiques sont servis avec :

```
cache-control: max-age=31536000, public
```

Un navigateur qui a déjà chargé la page retélécharge l'entrée (son `ver` a changé) et **réutilise
les dix-sept autres depuis son cache, pendant un an**.

Constaté en séance, pas déduit : après publication des fichiers du lot 3c-2, la page continuait
d'exécuter l'ancien `listing-binding.js` — un clic sur une page de pagination ne faisait rien, sans
la moindre erreur en console. Un `fetch` de la même URL avec `?bust=` renvoyait le nouveau contenu,
sans `bust` l'ancien. Il a fallu `Network.clearBrowserCache` pour débloquer.

**Conséquence** : tout correctif apporté à `listing.js`, `search-client.js`, `listing-binding.js`…
n'atteint jamais un visiteur déjà venu. Pire qu'une absence de correctif — l'entrée, elle, se met à
jour, et s'exécute alors contre des voisins périmés.

L'en-tête vient du serveur statique (nginx en local) : la valeur peut différer en production, le
mécanisme non. Trois sorties possibles, aucune gratuite :

| | Ce que ça coûte |
| --- | --- |
| **Carte d'imports** (`wp_register_script_module` par fichier, spécificateurs nus) | La réponse native de WordPress 6.9. Mais `./listing.js` devient `@meilifacets/listing`, que ni `node --test` ni ESLint ne savent résoudre sans alias |
| **Un bundle** (esbuild, Vite) en un fichier au nom haché | Une chaîne d'outils dans le module, là où il n'y a aujourd'hui que des fichiers servis tels quels |
| **Publier sous un répertoire versionné** | Les imports relatifs héritent du répertoire, donc de la version. Le moins invasif ; demande de faire porter la version à l'étape de publication |

**Tranché le 2026-09-08 : publication dans un répertoire versionné.** Différé — une v1 part avant.

`public/modules/meilifacets/<empreinte>/js/…` : les dix-huit fichiers changent d'URL d'un seul
coup, **les imports relatifs héritent du répertoire** donc de la version, et rien ne bouge côté
JavaScript. Les deux autres voies ont été écartées pour ce qu'elles coûtent :

- la **carte d'imports** transforme `./listing.js` en `@meilifacets/listing`, que ni `node --test`
  ni ESLint ne résolvent sans alias — les 147 tests Node passent tous par là ;
- le **bundle** fait entrer une chaîne d'outils dans un module qui n'en a aucune, où les fichiers
  sont servis tels quels.

Le coût se concentre dans `publishAssets()` et `ListingScript`, qui doit connaître l'empreinte
plutôt que d'appeler `filemtime()` sur l'entrée.

⚠️ **Une inconnue avant de chiffrer l'urgence** : l'en-tête d'un an vient d'nginx en local. Ce que
Clever Cloud sert sur `public/` n'a pas été vérifié. Si la production répond `no-cache`, le défaut
reste réel mais cesse d'être bloquant.

**Vérifié le 2026-09-08**, le mécanisme est intact : la page n'inscrit toujours qu'une URL versionnée
(`listing-page.js?ver=1788877596`) et un import relatif répond `max-age=31536000, public`. Le défaut
s'est manifesté deux fois dans la séance du jour — du JavaScript périmé débogué en croyant tester le
neuf, contourné à la main par `?cb=`.

T-39.

**Fermé le 2026-09-16 par `R-132`**, sans la voie tranchée le 2026-09-08 : Louis a retenu l'empaquetage
(`decisions.md`, « Le client est écrit en TypeScript et livré empaqueté »). La page n'inscrit plus qu'un
fichier, `dist/listing.js?ver=…`, qui n'importe rien : il n'existe plus d'import relatif à versionner.
Vérifié dans Chrome, cache vidé : une seule requête pour le client.

**Test ajouté le 2026-09-17** : `tests/ts/bundle.test.ts` vérifie que le paquet livré n'importe aucun fichier
relatif, statiquement ou dynamiquement — la cause de ce constat ; le motif attrape `from"./…"` et
`import("../…")` et laisse passer une URL absolue. `build:check` garantit déjà que le fichier commité est
celui que les sources produisent.

### R-71 · 🔴 · **fermé le 2026-09-07** (revue du lot 3c-2) · ouvert le 2026-09-07 — atteindre la dernière page jetait le clavier hors du document

`PaginationView.show()` masque « Suivant » quand il n'y a plus de page suivante — donc **le bouton
qui vient d'être pressé**. Le navigateur retire alors le focus de l'élément masqué et le repose sur
`<body>` : un visiteur au clavier se retrouve en haut du document, après avoir simplement paginé
jusqu'au bout. Symétrique sur « Précédent » en revenant en page 1.

Trouvé par la passe « contexte » de `module-review`, dans un lot dont le clavier était précisément
l'objet — `SortCombobox` rend le focus à son déclencheur, la pagination ne rendait rien.

**Corrigé.** `show()` retient l'élément qui a le focus **avant** de peindre, et si la peinture l'a
masqué, pose le focus sur le bouton de la page courante. Deux tests, dont un qui vérifie que le
focus ne bouge pas tant que le bouton reste. Recetté en navigateur : « Suivant » pressé quatre fois
sur cinq pages garde le focus sur « Suivant », puis le passe au bouton « 5 » — jamais à `<body>`.

### R-72 · 🟠 · **fermé le 2026-09-07** · ouvert le 2026-09-07 — le garde-fou `[hidden]` ne protégeait que les vues non surchargées

Le module rend des éléments vides et masqués que le client révèle — sept emplacements de page, le
message « aucun résultat », la remise à zéro, le badge. Il livre pour cela une règle
`display: none !important`, parce qu'un thème qui mate ses boutons en `flex` bat le `hidden` natif
du navigateur.

Cette règle ne visait que `[class^="meilifacets"]`. Or **le cas supporté est exactement l'inverse** :
une vue surchargée garde les crochets `data-meili` — le contrat l'exige — et remplace les classes,
que le module déclare appartenir au thème. Un thème faisant les deux choses ensemble voyait
réapparaître deux boutons vides et cliquables au bout de sa pagination.

Trouvé en tirant sur une remarque de séance — « des boutons vides » dans le DOM — et non par une
passe de revue : les cinq passes lisent le code, pas le rendu.

**Corrigé.** Le sélecteur porte désormais aussi sur `[data-meili][hidden]`. Vérifié en navigateur
contre une feuille de thème `nav button { display: inline-flex !important }` : l'emplacement reste
`none` **classe retirée**, donc c'est bien le nouveau sélecteur qui tient.

⚠️ Non couvert par un test : happy-dom donne à l'attribut `hidden` une priorité absolue et rend
l'assertion verte avec ou sans la règle. Un test a été écrit, constaté incapable d'échouer, puis
retiré — `tests/js/stylesheet.test.js` le dit en tête. Les curseurs et la superposition de la liste,
eux, sont testés et mordent.

### R-73 · 🟠 · **fermé le 2026-09-07** · ouvert le 2026-09-07 — changer de page laissait le visiteur à la fin de la nouvelle page

Rien ne ramène le regard en haut du listing après un geste. La pagination est en bas d'un listing
long : le visiteur clique « 2 » depuis le bas, la grille est remplacée sous lui, et il se retrouve
devant la **fin** de la page 2.

**Mesuré le 2026-09-07** sur `/boutique`, écran de 900 px, listing de 5252 px : après un clic sur
« 2 » depuis la pagination, défilement à 4335 px, haut des résultats à **-3944 px**, **1 carte
visible sur 16**.

Absent de tous les lots et de tous les documents — ce n'est pas un arbitrage oublié, c'est un cas
jamais posé. Relevé en séance le 2026-09-07.

Le geste n'est pas seulement « défiler » : il décide aussi où va le focus, et il croise donc R-71,
fermé le jour même. Trois formes, à trancher (T-40) :

| | Souris | Clavier |
| --- | --- | --- |
| **A · défiler seulement** | juste | la page saute, le focus reste en bas hors écran — on presse « Suivant » à l'aveugle |
| **B · déplacer le focus en haut des résultats** (`tabindex="-1"`) | juste | cohérent, et annoncé aux lecteurs d'écran ; mais paginer plusieurs fois de suite impose de retraverser la grille |
| **C · distinguer la source du geste** (`event.detail > 0` = pointeur) | juste | le focus ne bouge pas, la page non plus : on continue à paginer |

Même question pour le tri et la remise à zéro, qui **remplacent** ce qu'on lisait. Pas pour les
facettes, qui **rétrécissent** ce qu'on regarde déjà — et en mode `immediate`, défiler à chaque
case cochée serait intenable.

Sa place naturelle était le lot 3c-3, avec l'état d'attente. **Avancé et livré le jour même** : ce
n'est pas un raffinement, c'est un basique qui manquait.

**Retenu : C.** Le geste ramène le haut du listing dans l'écran, *pour un pointeur seulement* —
`event.detail` vaut `0` sur un clic que le clavier a levé, et non nul sur un vrai. Le clavier garde
donc sa place et son bouton, ce que R-71 venait d'assurer.

Portée : pagination, tri, remise à zéro et « Appliquer » — les gestes qui **remplacent** la grille.
Jamais une case cochée, qui **rétrécit** ce qu'on regarde déjà : en mode `immediate`, défiler à
chaque clic serait intenable.

Le défilement vise la racine du listing, pas le haut du document — on revient aux facettes et au
tri, pas à l'en-tête du site.

**Le module n'impose aucune animation** : `scrollIntoView` est appelé sans `behavior`, donc le
`scroll-behavior` calculé décide — c'est-à-dire le thème.

L'animation est donc une décision de thème. Côté Pluralia, `common/motion.css` :

```css
@media (prefers-reduced-motion: no-preference) {
    html { scroll-behavior: smooth; }
}
```

⚠️ **La propriété s'applique à la boîte de défilement, donc à `html` : tout le site est animé**,
pas seulement le listing. C'est la contrepartie assumée du choix de le faire en CSS ; la seule
façon de ne viser que ce geste serait de passer `behavior` depuis le module, ce qui mettrait une
décision d'apparence dans un module qui s'en interdit.

⚠️ **Mesuré le 2026-09-07 : 1451 ms** pour les 4788 px qui séparent la pagination du haut du
listing, en 147 positions. La durée est décidée par le navigateur et croît avec la distance ; elle
n'est pas réglable sans réécrire l'animation à la main. Sous `prefers-reduced-motion: reduce`, même
trajet en **2 positions**, instantané — la garde CSS fonctionne. C'est long pour un geste de
pagination : à rouvrir si l'usage le confirme.

**Mesuré après correction**, même page, même écran : défilement 4335 → **144 px**, haut du listing
à 139 px, **5 cartes visibles au lieu d'1**. Au clavier, `Entrée` sur « Suivant » : défilement
inchangé à 4139 px, focus toujours sur « Suivant », URL en `?pg=3`.

⚠️ **Le thème doit poser `scroll-margin-top` sur `[data-listing]`** s'il a un en-tête collant, sinon
le haut du listing atterrit dessous. Le module ne peut pas connaître cette hauteur. Côté Pluralia :
trois lignes dans un `components/listing.css` neuf, `scroll-margin-top: 9rem`.

**Deux détours avant d'y arriver, et ils valent la correction :**

1. La hauteur a d'abord été **relevée dans un navigateur puis écrite en dur**
   (`--pluralia-header-height: 123px`), avec un commentaire demandant de la tenir à jour à la main.
   Mesurée ensuite aux autres largeurs, elle était fausse trois fois sur cinq — **92 px en mobile,
   94 px en tablette, 122-123 px en desktop** : l'en-tête est dimensionné par son contenu. *Une
   valeur mesurée une fois n'est pas une constante ; il suffisait de changer la largeur.*
2. Elle a ensuite été remplacée par un `ResizeObserver` publiant la hauteur réelle. Inutile :
   **seul le dégagement insuffisant est un défaut** — trop dégager ne montre qu'un peu plus de ce
   qui est au-dessus, ce que personne ne remarque. Une marge fixe et généreuse suffit donc, et
   quinze lignes de JavaScript dans le thème ont été retirées. *Relevé en séance ; c'est
   l'asymétrie du problème qui avait été manquée, pas la technique.*

Mesuré après simplification, aux quatre largeurs : haut du listing à 144 px partout, dégagement de
21 à 52 px sous l'en-tête. Aucune trace laissée dans `header.js` ni `header.css`.

### R-74 · ⚪ · **fermé le 2026-09-07, sans code** · ouvert le 2026-09-07 — trier n'ouvre pas d'entrée d'historique, et efface celle de la page

`Listing` ne pousse une entrée d'historique que pour un changement de page ; tout le reste
**remplace**, pour qu'une poignée de cases cochées ne coûte pas autant d'appuis sur « retour ». La
règle a été écrite avant que le tri ne soit câblé, et le tri est passé du mauvais côté sans
décision.

**Mesuré le 2026-09-07** sur `/boutique`, en navigateur :

| Geste | URL | Historique |
| --- | --- | --- |
| chargement | *(vide)* | entrée A |
| clic « 2 » | `?pg=2` | **pousse** B |
| tri par prix décroissant | `?sort=price_desc` | **remplace** B |
| facette + « Appliquer » | `?categorie=cheveux&sort=price_desc` | remplace |
| retour arrière | *(vide)* | revient en **A** |

**Fermé le jour même, et la première rédaction de ce constat était fausse.** Elle affirmait que « le
tri ne s'annule pas par un retour arrière ». Remesuré pas à pas :

```
1. chargement     (vide)            page 1, Pertinence
2. clic « 2 »     ?pg=2             page 2, Pertinence
3. tri par prix   ?sort=price_asc   page 1, Prix croissant
4. retour         (vide)            page 1, Pertinence
```

Le retour **annule bien le tri** et rend la page 1 non triée. Le comportement est celui qu'on
attend, et il ne demande aucun code.

La seule conséquence réelle : l'entrée `?pg=2` est écrasée par le tri, donc on ne peut pas revenir
à « la page 2 non triée ». C'est défendable — trier ramène en page 1, cette position n'existe plus.
Faire pousser une entrée au tri rendrait au contraire « retour » vers une page 2 non triée depuis
une page 1 triée, ce qui serait plus déroutant que l'inverse.

**Enseignement** : un constat écrit à partir d'une trace d'historique lue de travers. La mesure
était juste, sa lecture ne l'était pas — il fallait dérouler le scénario du visiteur, pas la pile
d'entrées.

### R-75 · 🔴 · **fermé le 2026-09-07** · ouvert le 2026-09-07 — deux correctifs du même jour se battaient, et la page descendait

Signalé en séance : « je clique sur Précédent, ça défile vers le bas ».

**Reproduit et mesuré**, `/boutique?pg=2`, clic sur « Précédent » depuis le bas :

```
départ           y=4091   focus BODY
 74 ms           y=4091   focus « previous »
116 ms           y=4083   focus « page »      ← le focus saute au bouton de la page courante
249 ms           y=4243
590 ms  arrivée  y=4927   ← 836 px SOUS le point de départ
```

Deux correctifs livrés le même jour, chacun juste, incompatibles ensemble :

| | Ce qu'il fait |
| --- | --- |
| **R-71** | quand le bouton pressé disparaît, pose le focus sur la page courante — sinon le clavier tombe sur `<body>` |
| **R-73** | ramène le haut du listing dans l'écran |

`focus()` **fait défiler l'élément dans la vue**. Le focus posé sur un bouton resté en bas ramenait
donc le navigateur vers lui — en douceur depuis que `scroll-behavior: smooth` est déclaré, ce qui
rendait le mouvement bien visible. Le défilement vers le haut n'avait jamais le temps de démarrer.

**Corrigé** par `focus({ preventScroll: true })` : le focus bouge, la vue non. Mesuré après
correction sur les trois gestes — Précédent 2→1, Suivant 4→5, Suivant 2→3 : arrivée à 139 px dans
les trois cas, et la position la plus basse atteinte est exactement le point de départ.

**Pourquoi la recette ne l'avait pas vu.** Les deux correctifs avaient été recettés séparément, et
sur la bonne gestuelle : R-71 en pressant « Suivant » jusqu'à la dernière page, R-73 en cliquant un
numéro de page. Mais R-71 vérifiait **le focus** et R-73 **le défilement** — jamais les deux sur le
geste où ils se croisent. *Deux correctifs qui touchent la même page se recettent ensemble, sur la
même mesure.*

### R-76 · 🟠 · **fermé le 2026-09-07** · ouvert le 2026-09-07 — deux crochets et toute la feuille de style échappaient au test de parité

`ContractParityTest` affirmait couvrir « chaque crochet que le client adresse ». Il lisait en fait
`one('…')`, `all('…')` et `selector('…')` — mais pas `#hookOf(node, '…')`, qui prend le crochet en
**second** argument. `apply` et `reset` n'étaient donc vus par aucune des deux expressions
régulières : renommer `Hook::Apply` aurait laissé la suite verte et le bouton « Appliquer » inerte.

La feuille de style échappait pour une autre raison : le test ne balaie que `resources/assets/js/*.js`,
alors que `meilifacets.css` écrit huit noms de crochets en dur depuis qu'elle s'accroche à
`data-meili` plutôt qu'aux classes.

**Corrigé** : le balayage inclut `#hookOf(…, '…')` et la feuille de style.

### R-77 · 🟠 · **fermé le 2026-09-07** (D-10) · ouvert le 2026-09-07 — en mode `submit`, trier envoie les filtres jamais validés

Trouvé par la troisième passe de revue, **mesuré** :

```
après deux cases cochées → recherches: 0 | url: []
après changement de tri  → recherches: 1 | url: ["?brand=acme,globex&sort=price_asc"]
filtre envoyé            : (facets.product_brand = "acme" OR facets.product_brand = "globex")
```

`#byMode()` pose l'état même quand il ne cherche pas — c'est ce qui permet aux cases de rester
cochées. `#atOnce()` part de cet état, donc trier emporte les filtres en attente et les écrit dans
l'URL. Le mode `submit` est le défaut (`apply_mode`), donc c'est le chemin normal.

`decisions.md` (« Deux familles de gestes, pas cinq ») écrit pourtant : « Un filtre **se rassemble** :
en mode `submit` il attend « Appliquer » ». **Il ne l'attend pas.** Aucun test ne coche puis ne trie.

Deux sorties, et ce n'est pas au module de choisir :

| | Ce que ça vaut |
| --- | --- |
| **Le code suit le texte** | `#atOnce()` repart du dernier état appliqué. Demande de tenir deux états — celui qui est affiché, celui qui est en attente — et laisse le visiteur devant une grille qui ignore ses cases cochées |
| **Le texte suit le code** | trier vaut validation : tout geste qui remplace la grille emporte ce qui est en attente. Plus simple, et c'est ce que font la plupart des listes à facettes |

**Tranché le 2026-09-07 (D-10) : le texte suit le code.** Trier vaut validation. Aucune ligne de
code ne change ; `decisions.md` porte désormais la clause « et ils emportent avec eux les filtres en
attente », et dit ce que l'autre branche aurait coûté.

### R-78 · ⚪ · **fermé le 2026-09-17** · ouvert le 2026-09-07 — la règle du pluriel est anglaise des deux côtés, les chaînes ne le sont pas

**Fermé par `R-137` #4** : serveur et client choisissent la forme par la règle CLDR de la langue
(`View\CountLabel` en PHP, `CountLabel` en TypeScript), vérifiés sur `tests/plural-cases.json`. « 0 filtre actif » des deux côtés.

`countLabel()` choisit la forme sur `count === 1`. C'est la règle anglaise. Le serveur, lui, passe
par `trans_choice`, dont la règle suit la locale — et le français range **zéro avec le singulier**.

**Mesuré le 2026-09-07** sur le badge de filtres actifs, en français :

| | zéro | un | deux |
| --- | --- | --- | --- |
| Serveur (`trans_choice`) | 0 filtre actif | 1 filtre actif | 2 filtres actifs |
| Client (`countLabel`) | 0 filtre**s** actif**s** | 1 filtre actif | 2 filtres actifs |

**Inobservable aujourd'hui**, et par construction : les deux seuls porteurs de ce motif sont masqués
quand leur compte est nul — `host.hidden = hits === 0` pour une valeur de facette,
`badge.hidden = count === 0` pour le badge. L'écart n'existe que dans le DOM, jamais à l'écran.

Le commentaire de `facet-counts.js` annonce déjà la limite : « deux formes seulement ; une langue
qui en demande trois demanderait la règle, pas seulement les chaînes ». Le cas de zéro montre que
c'est déjà vrai à deux formes.

Sortie propre le jour où ça compte : publier la locale et passer par `Intl.PluralRules`, ou
envoyer la forme choisie plutôt que le motif. Aucune des deux ne vaut d'être faite tant que rien
ne l'affiche.

Cinq passes : voir `R-137`, lignes #2 et #4, rejouées le 2026-09-17.

### R-79 · 🔴 · **fermé le 2026-09-08** · ouvert le 2026-09-07 — enregistrer un article détruisait les réglages d'index du module

**Trouvé en vérifiant R-42 sur le chemin normal, et reproductible en trois commandes.**

```
réindexation complète  → filterableAttributes : […, facets.category, facets.product_cat, …]
                         sortableAttributes   : [metas._price, post_date, post_title]
                         la boutique rend 17 cartes

wp post update <un produit>

après                  → filterableAttributes : [post_type, post_status, terms.*]
                         sortableAttributes   : [post_date, post_title]
                         la boutique rend 0 carte, page d'indisponibilité
```

**Un seul enregistrement d'article suffit à casser le listing**, jusqu'à la prochaine réindexation
complète. En production, c'est un rédacteur qui publie.

**Cause, lue dans MeiliScout.** `AbstractSingleIndexer::__construct()` appelle `createIndexable()`,
qui appelle `resolveIndexable()`, qui applique le filtre `meiliscout/indexables`. Or l'indexeur est
construit dans `SingleIndexingServiceProvider::register()` — **avant** que la découverte d'attributs
de Pollora n'ait enregistré les `#[Filter]` du module. Le filtre ne trouve donc personne, l'indexeur
mémorise le `PostIndexable` nu pour toute la requête, et chaque `save_post` fait
`ensureIndexExists()` → `updateSettings()` avec les réglages de base, qui écrasent les nôtres.

Vérifié que le filtre lui-même est sain : sous `wp eval`, `apply_filters('meiliscout/indexables',
[new PostIndexable])` rend bien `FacetedPostIndexable`, avec `facets.*` et le `maxTotalHits` du
module. C'est l'**instant** de la résolution qui est faux, pas la résolution.

**Antérieur au lot 3c-2** : les attributs de facettes sont posés depuis le lot 1, et rien n'a jamais
enregistré d'article pendant une session de travail. Le correctif de R-42 n'a fait que rendre le
défaut visible — il en a ajouté une troisième victime, `pagination.maxTotalHits`.

**Sortie, dans l'ordre de préférence de `CLAUDE.md`** — corriger la dépendance plutôt que la
contourner, comme `resolveIndexable()` l'a déjà été : rendre la résolution **paresseuse** dans
`AbstractSingleIndexer`, c'est-à-dire sortir `$this->indexable = $this->createIndexable()` du
constructeur pour la mémoïser dans un accesseur appelé après le démarrage. Trois lignes en amont.

**Corrigé en amont le 2026-09-08**, dans `AmphiBee/MeiliScout` sur `feat/meilifacets`
(`2acf53a`) : la propriété passe en `?Indexable`, la résolution sort du constructeur, et un
accesseur `indexable()` la mémoïse au premier usage — dix points d'appel redirigés, dans
`AbstractSingleIndexer` et ses deux sous-classes.

Le correctif prolonge celui du 2026-09-02, qui avait introduit `resolveIndexable()` : le geste
était juste, il résolvait au mauvais instant.

**Mesuré après mise à jour de la dépendance** — `composer update amphibee/meiliscout`, la
`composer.lock` du projet passant de `1c59a05` à `2acf53a` :

```
après réindexation : facettes 14 · prix triable oui · plafond 1000
après un save      : facettes 14 · prix triable oui · plafond 1000
boutique           : 17 cartes
```

Avant, le premier `save` donnait `facettes 0 · prix triable NON · 0 carte`.

**Rien à patcher à la main.** Le module requiert `amphibee/meiliscout` dans son propre
`composer.json`, et le `merge-plugin` du projet inclut `Modules/*/composer.json` : la dépendance
remonte donc au projet, qui l'installe en `type: wordpress-plugin` vers
`public/content/plugins/meiliscout` par ses `installer-paths`. Ce répertoire est ignoré par git
parce qu'il est **une sortie de Composer**, pas une source — ce qui avait d'abord été lu comme une
installation manuelle, à tort.

### R-80 · ⚪ · **fermé le 2026-09-07, sans code** · ouvert le 2026-09-07 — un mouvement vers le bas soupçonné avant le retour en haut

Signalé en séance après activation de `scroll` sur la pagination : « j'ai l'impression qu'il descend
puis remonte ».

**Reproduit d'abord, puis démenti.** Une première mesure, avec la pagination en partie sous la
ligne de flottaison, donnait bien une descente :

| Pagination visible | La page descendait de |
| --- | --- |
| 10 px | 23 px |
| 30 px | 3 px |
| 60 px et plus | 0 |

J'en ai tiré une cause plausible — le navigateur amène un bouton cliqué dans la vue avant que le
`click` ne parte — et un correctif : prendre le focus soi-même en `preventScroll`. **Les deux
étaient faux.**

Vérifié ensuite, pas à pas : un `focus()` nu sur ce bouton partiellement visible **ne déplace pas la
page**. Et un clic à coordonnées réelles (`page.mouse.click`), qui n'amène rien dans la vue,
donne `descendDe: 0` à 10, 25 et 120 px de visibilité. **La descente venait de Playwright**, qui
fait défiler l'élément dans la vue avant de cliquer. Un artefact d'instrument, pas un défaut.

Le correctif a été retiré : il défendait contre un cas qui n'existe pas — le défaut même que la
journée a passé son temps à supprimer.

**Ce qui est mesuré, et qui reste** : le défilement est monotone vers le haut sur les trois gestes
(clic sur un numéro, « Précédent », « Suivant »), depuis le bas réel du document. Deux faits
peuvent expliquer l'impression sans être des défauts : l'animation dure **1451 ms** sur 4788 px
(R-73), et **la grille grandit de 160 px à 72 ms**, en plein vol, quand la nouvelle page a des
cartes plus hautes.

**Enseignement** : une mesure obtenue par un outil d'automatisation décrit l'outil autant que le
site. Un clic de Playwright n'est pas un clic de visiteur tant qu'on ne l'a pas prouvé.

### R-81 · 🟡 · **fermé le 2026-09-08, renversé le 2026-09-08** · ouvert le 2026-09-08 — une facette mélangeait trois grandeurs sur une seule échelle

Relevé en séance sur `pa_contenance`, qui portait, dans cet ordre :

```
1L · 4g · 5ml · 7x2ml · 10ml · 15ml · 20g · 30 sachets · 30ml · 40ml · 50ml · 60 gélules · …
```

`DisplayOrder::Name` trie par `strnatcasecmp`, qui compare **le nombre en tête** : l'ordre était donc
exactement celui demandé, et c'est la demande qui était incomplète. Un attribut WooCommerce écrit à
la main porte couramment trois grandeurs — volume, masse, décompte — et un tri numérique les lit
comme une seule échelle.

**Deux sorties étaient possibles**, et la meilleure n'a pas été retenue : séparer l'attribut en
trois taxonomies donnerait des facettes justes sans une ligne de code, et empêcherait de cocher
« 50ml » et « 60 gélules » dans la même liste. Écarté en séance — le projet ne peut pas créer de
taxonomies pour l'instant.

**Livré.** `Contracts\ValueOrder` : un projet déclare l'ordre qu'il veut, à la place de
l'énumération. `Facet::$order` accepte donc `DisplayOrder|ValueOrder`. `MeasureOrder`, livré avec,
groupe par unité puis par quantité, ramène chaque échelon métrique dans sa famille, et **range à part ce
qu'il ne sait pas lire** plutôt que de le deviner.

**Mesuré, servi sur `/boutique`** :

```
4g · 20g · 100g · 180g · 200g · 60 gélules · 5ml · 10ml · … · 500ml · 1L · 60 patchs · 30 sachets · 7x2ml
```

Coût : **nul**. Un `usort` sur au plus `cap` valeurs déjà en mémoire, une fois par rendu serveur —
et il n'y a qu'un rendu serveur, le filtrage se passant ensuite dans le navigateur. Le tri du
moteur reste `count`, il ne décide que du plafond.

⚠️ **Les décomptes s'intercalent entre les mesures** : `gélules` tombe entre `g` et `ml`, par ordre
alphabétique d'unité. Déterministe, mais un lecteur attend peut-être les deux vraies mesures
d'abord.

---

**Renversé le 2026-09-08, le jour même.** `Measure`, `MeasureOrder` et `ScaledUnit` sont supprimés.

Deux signaux, l'un après l'autre. D'abord le tableau des unités : il ne portait que `l`, `cl`, `kg`,
choisis d'après ce que Pluralia avait sous la main. Mesuré sur un jeu élargi —

```
2dl · 5dl · 4g · 1kg · 500mg · 5ml · 50ml · 1L
```

— `dl` et `mg` formaient chacun leur propre famille : quatre groupes là où il en fallait deux. Le
compléter (`cl`, `dl`, `l`, `mg`, `kg`) n'était qu'un pansement : la table restait arbitraire et le
module continuait de décider à la place de la boutique.

**Ce qui n'avait pas été vérifié avant d'écrire une ligne : WooCommerce porte déjà la réponse.**
`class-wc-admin-attributes.php:333` offre quatre modes d'ordre par attribut — *Ordre personnalisé*
(glisser-déposer), *Nom*, *Nom (numérique)*, *Identifiant du terme* — et
`wc_change_get_terms_defaults()` les applique à **tous** les `get_terms()` sur un attribut produit.
Mesuré sur la base du projet :

```
orderby que WooCommerce impose : menu_order
ordre rendu par get_terms()    : 100g · 10ml · 125ml · 180g · 1L · 200g · 20g · 30 sachets · 400ml …
```

Autrement dit `pa_contenance` était **déjà** réglé sur « ordre personnalisé » — la boutique avait
déjà dit que l'ordre serait celui qu'elle pose — et personne n'avait encore glissé les termes, d'où
le repli alphabétique.

*(Une sonde de cette session cherchait la méta `order_pa_contenance` et la trouvait vide sur les 24
termes ; la bonne clé est `order`. La conclusion tenait — c'est `get_terms()` qui la portait — mais
la ligne était fausse et a été retirée.)*

**Vérifié après livraison**, les 24 termes ayant été ordonnés dans l'admin entre-temps : la méta
`order` va de 1 à 24, et `/boutique` rend cet ordre au caractère près.

```
admin  : 4g · 20g · 100g · 180g · 200g · 5ml · 10ml · 15ml · 40ml · 100ml · … · 1L · 30 sachets · 60 gélules · 60 patchs · 7x2ml · 250ml · 30ml · 50ml · 75ml
rendu  : identique
```

Et c'est aussi ce que font les autres systèmes de facettes : la magnitude est **un champ numérique
préparé à l'indexation**, jamais une grandeur extraite d'un libellé au rendu. Les facettes
textuelles se rangent par compte, alphabétiquement, ou dans un ordre que quelqu'un a déclaré.

**Livré à la place** : `DisplayOrder::Declared`, qui conserve l'ordre que `TermLabels::of()` reçoit
déjà de `get_terms()` — le contrat le promet désormais explicitement. `Contracts\ValueOrder` reste,
comme point d'extension pour un projet qui veut autre chose.

**Coût assumé** : tant que les termes ne sont pas glissés dans Produits → Attributs → Configurer les
termes, la facette s'affiche dans l'ordre alphabétique ci-dessus. Le module sert la décision, il ne
la remplace pas.

**Enseignement** : lire ce que la plateforme fait déjà avant d'écrire. Trois classes, deux fichiers
de test et deux passages de revue ont porté sur du code qui n'avait pas lieu d'être.

### R-82 · 🔴 · **fermé le 2026-09-08** · ouvert le 2026-09-08 — le repli ne survivait pas à la première recherche

Trouvé en préparant T-07, et plus grave que l'absence du bouton : **le client ignorait le repli.**
`FacetsView.showCounts()` posait `hidden` sur le seul critère du compte, écrasant la décision du
serveur.

**Mesuré**, sur `pa_contenance` et ses 24 valeurs :

```
au chargement                  10 visibles
cocher puis décocher, Appliquer 24 visibles
```

Un aller-retour sans effet sur le résultat suffisait à tout déplier, définitivement, sans que le
visiteur l'ait demandé.

**Corrigé avec T-07.** Le repli est désormais un état que le client tient : `visible` voyage dans la
description, et le client refait le repli à **chaque** réponse, sur les compteurs qui viennent
d'arriver — une valeur se lit quand elle a des résultats et que le repli a encore de la place.

⚠️ **La phrase « les deux règles sont identiques », écrite ici le 2026-09-08, était fausse au
moment où elle a été écrite** : le serveur repliait au rang moteur *avant* de réordonner, le client
dans l'ordre du DOM. Constat ouvert sous `R-85`, et **vraie depuis sa fermeture le même jour**.

**Un piège rencontré en chemin, et écarté** : la première version faisait repeindre les compteurs au
clic sur « Voir plus », avec une réponse vide faute de recherche préalable — donc **toutes les
valeurs de toutes les facettes passaient à zéro et disparaissaient**. Déplier ne doit rien demander
au moteur : rien n'a changé du côté des comptes. Un test le verrouille (« reads a facet in full
before any search has answered »).

### R-83 · 🟡 · **fermé le 2026-09-08** · ouvert le 2026-09-08 — replier au compte contredit un ordre déclaré

Sur `pa_contenance`, les dix mieux comptées sont **toutes des millilitres** : l'ordre que la
boutique a posé est invisible au premier coup d'œil, il faut déplier pour le voir.

Le critère est juste pour une longue traîne de marques : on veut les plus peuplées. Il l'est moins
pour un ordre que quelqu'un a déclaré, dont le visiteur attend de voir le début.

*Réécrit le 2026-09-08 : le constat portait sur `MeasureOrder`, supprimé depuis (`R-81`). Il vaut
tel quel pour `DisplayOrder::Declared`, et déjà pour `Name`.*

Trois voies étaient possibles : ne rien replier quand l'ordre n'est pas `Count` ; replier après
réordonnancement plutôt qu'avant ; ou laisser ainsi, le bouton résolvant le symptôme.

**Tranché le 2026-09-08 : on réordonne, puis on replie.** « Bien sûr que les dix premiers éléments
doivent être les dix premiers de l'ordre défini. » Fermé avec `R-85`, dont c'était la même racine.

### R-84 · 🔴 · **fermé le 2026-09-08** · ouvert le 2026-09-08 — un bloc de facette vide mettait tout le client hors service

La règle `{ host: 'facet', hooks: ['facet-value', 'more'] }`, ajoutée avec T-07, exigeait au moins
une valeur dans un bloc de facette. Or `facets.blade.php` rend le `<fieldset>` — masqué — même
quand la facette n'a aucune valeur, et `Contract.#breachesOf()` ne teste que le **premier** hôte
`facet`.

**Mesuré**, en exécutant le vrai `Contract` sur le markup du module :

```
première facette vide  -> ["facet > facet-value"]
facette vide en second -> []
```

`listing-page.js` journalise l'infraction et passe le listing : plus de filtrage, plus de tri, plus
de pagination. Deux cas réels et attendus le produisent — une catégorie feuille, où
`ChildTermsFacet::within()` rend `[]` par conception, et une URL filtrée sans résultat, où le
visiteur ne peut alors même plus retirer son filtre. Et le déclenchement dépend de l'ordre des
facettes, donc le défaut est intermittent d'une page à l'autre.

**La règle était fausse, pas la vue.** `architecture.md` pose que le contrat porte sur ce que le
thème contrôle, jamais sur ce que la donnée décide — et la présence d'une `facet-value` est décidée
par la donnée. Le même diff avait d'ailleurs déplacé `facet-value` vers les crochets « exigés »
trois lignes au-dessus de la phrase qui dit l'inverse.

**Corrigé** : la règle se réduit à `{ host: 'facet', hooks: ['more'] }`, `architecture.md` est
remise d'accord avec elle-même, et un test couvre le bloc vide (« accepts a facet block the data
left empty »). Vérifié après correction : `[]` dans les deux dispositions, et avec toutes les
facettes vides.

### R-85 · 🔴 · **fermé le 2026-09-08** · ouvert le 2026-09-08 — serveur et client ne replient pas le même ensemble

Le serveur marque `folded` sur le **rang moteur** (`FacetValues::of()`), *puis* réordonne selon
l'ordre déclaré. Le client (`FacetsView.#showFolds()`) replie dans l'**ordre du DOM**. Les deux
règles ne coïncident que pour `DisplayOrder::Count`.

**Mesuré**, sur une distribution de 16 valeurs mélangeant trois grandeurs :

```
ordre rendu   : 4g · 20g · 100g · 60 gélules · 5ml · 10ml · … · 1L · 30 sachets
serveur montre: 5ml · 10ml · 15ml · 20ml · 30ml · 40ml · 50ml · 100ml · 250ml · 500ml
client montre : 4g · 20g · 100g · 60 gélules · 5ml · 10ml · 15ml · 20ml · 30ml · 40ml
```

La première recherche, **même sans effet sur les résultats**, remplace la liste par une autre.

**Le défaut est antérieur à T-07 et à R-81** : il touche déjà `DisplayOrder::Name`, livré et
commité. Mesuré sur 14 marques :

```
serveur montre: quies · roge · svr · topicrem · uriage · vichy · weleda · xerus · yuka · zorro
client montre : avene · bioderma · caudalie · ducray · quies · roge · svr · topicrem · uriage · vichy
```

**Reproduit sur le site**, `/boutique`, facette Contenance (24 valeurs) :

```
rendu serveur          : 10ml · 15ml · 30ml · 50ml · 75ml · 100ml · 150ml · 200ml · 400ml · 500ml
après un aller-retour  : 4g · 20g · 100g · 180g · 200g · 60 gélules · 5ml · 10ml · 15ml · 30ml
sur « Voir plus »
```

Deux voies, et la première renversait une décision validée (« le repli est décidé sur le compte,
avant réordonnancement ») :

- **replier après réordonnancement, côté serveur** — les deux règles deviennent identiques par
  construction, et `R-83` se ferme au passage. Coût : sur une longue traîne triée par nom, on
  montre les dix premières alphabétiquement au lieu des dix mieux comptées ;
- **faire ranger le client au rang moteur** — il faudrait lui transmettre ce rang, que la
  description ne publie pas ; le brut de `facetDistribution` ne suffit pas, le serveur l'ayant
  filtré (`within()`, terme par défaut) et plafonné avant de classer.

**Corrigé par la première**, tranchée le 2026-09-08. `FacetValues::of()` construit les valeurs sans
repli, les réordonne, **puis** replie sur leur rang d'affichage — `folded()`. Le plafond, lui, reste
dépensé sur le compte : c'est le moteur qui décide quelles valeurs survivent, jamais lesquelles se
lisent. Une valeur tenue par l'URL échappe toujours au repli (`R-86`).

**Vérifié après correction**, sur les deux jeux qui divergeaient :

```
Ordre déclaré (contenance)
  serveur : 4g · 20g · 100g · 60 gélules · 5ml · 10ml · 15ml · 20ml · 30ml · 40ml
  client  : identique

Ordre par nom (marques)
  serveur : avene · bioderma · caudalie · ducray · quies · roge · svr · topicrem · uriage · vichy
  client  : identique
```

Deux tests verrouillent la distinction : « it_folds_what_the_display_order_puts_last » et
« it_spends_the_cap_on_the_count_and_the_fold_on_the_order », où une valeur qui trie en tête mais
compte en dernier est écartée par le plafond avant que l'ordre ne s'applique.

**Recetté dans le navigateur** sur `pa_contenance`, dont les 24 termes sont désormais ordonnés dans
l'admin. Rendu serveur de `/boutique` : les dix premiers de l'ordre, `4g · 20g · 100g · 180g ·
200g · 5ml · 10ml · 15ml · 30ml · 40ml`. Puis, sur la même page, un filtre par marque côté client
comparé au rendu serveur de la même URL :

```
/boutique?marque=aeris — rendu serveur   : 15ml · 30ml · 40ml · 50ml · 75ml · 100ml · 125ml
/boutique puis « Aeris » coché, client   : identique
```

Sept valeurs et non dix : les dix-sept autres tombent à zéro, et le client les retire — c'est la
part de la règle qu'il doit rejouer. « Voir plus » ouvre les 24 dans l'ordre, de `4g` à `7x2ml`.

### R-86 · 🟠 · **fermé le 2026-09-08** · ouvert le 2026-09-08 — une valeur cochée pouvait disparaître de la vue tout en filtrant

Trouvé en vérifiant T-07 dans le navigateur. Ni `FacetValues::of()` ni `FacetsView.#showFolds()`
n'exemptaient du repli une valeur que l'URL tient.

**Mesuré**, `/boutique`, après avoir coché `50ml` et validé :

```
url          ?contenance=50ml
résultats    9 cartes
case 50ml    checked = true, hors de vue (repliée)
```

Le visiteur voit neuf produits filtrés par un critère qu'il ne voit plus et ne peut plus décocher —
il lui reste « Tout effacer », qui lève tous les filtres, ou « Voir plus ». Le défaut est visible
d'autant plus vite que le repli est court.

**Corrigé des deux côtés**, sous la même règle : *une valeur tenue est toujours lue.* Serveur,
`$rank >= $facet->visible && ! $selected` ; client, `! input.checked && (…)`. Le décompte des
places (donc l'affichage du bouton) reste calculé sur les valeurs comptées, inchangé. Un test par
côté (« it_never_folds_away_a_value_the_url_holds », « never folds away a value the visitor
holds »).

### R-87 · 🟠 · **fermé le 2026-09-08** · ouvert le 2026-09-08 — `DisplayOrder::Name` classe mal les libellés accentués

`FacetValues` trie `Name` avec `strnatcasecmp`, qui compare des octets. En UTF-8 un `é` vaut deux
octets qui tombent après tout l'ASCII : chaque mot accentué est rejeté à la fin de son groupe de
lettres.

```
strnatcasecmp  : Diffuseurs · Dissolvants · Démaquillants · Déodorants
Collator fr_FR : Démaquillants · Déodorants · Diffuseurs · Dissolvants
```

**Mesuré sur le site**, facette Catégorie, 109 termes : l'ordre `Name` diverge de celui que MySQL
rend dès le rang 25, et la divergence court sur tout l'alphabet. `product_brand` (9 termes, sans
accent en tête) est identique dans les deux ordres — le défaut ne se voyait pas là.

**Corrigé en réparant `Name`**, pas en basculant vers `Declared` : c'est un défaut, pas un choix
d'ordre. `Declared` reste disponible et garde son sens — honorer un ordre posé au glisser-déposer.

**Trois fausses pistes écartées, chacune par la mesure :**

| | Nombres | Accents FR | Suédois (`ä`/`ö` après `z`) |
| --- | --- | --- | --- |
| `strnatcasecmp` | ✅ | ❌ | ❌ |
| `Collator` **sans réglage** | ❌ `100ml · 10ml · 9ml` | ✅ | ✅ |
| `iconv('ASCII//TRANSLIT')` + `strnatcasecmp` | ✅ | ✅ | ❌ — fige le modèle français |
| **`Collator` + `NUMERIC_COLLATION`** | ✅ | ✅ | ✅ |

`Collator` avait d'abord été rejeté pour son classement des nombres, sans avoir essayé l'attribut
qui existe exactement pour ça. Et le repli ASCII, proposé ensuite, code en dur un modèle de
collation aussi sûrement qu'une locale écrite en clair — relevé en séance : le module doit rester
compatible Polylang (`decisions.md` : « multilingue non implémenté, mais l'architecture doit le
permettre sans refonte »).

**Livré** : `Listing\NameOrder implements ValueOrder`, recevant un **`?Collator`** — pas une locale.
Le provider lit `get_locale()` **dans la fermeture du binding**, jamais dans `register()` : Polylang
pose la langue sur un crochet plus tardif, et une locale vide collationne en `en_US_POSIX`. Un seul
`is_int()` couvre les deux replis.

**Trois pièges mesurés, dont un qui faisait un `500` :**

```
compare("\xC3\x28", 'b')   → false   ← contre un type de retour `: int`, TypeError non rattrapé
new Collator('xx_INVALID') → objet, ACTUAL=root      (repli silencieux)
new Collator('!!!…!!!')    → IntlException           (donc try/catch)
```

**Pourquoi `get_locale()` et pas `app()->getLocale()`** : seul le premier porte la région, et la
région décide. `fr_FR` et `fr` retombent tous deux sur la collation racine, mais pas `fr_CA` :

```
fr_FR → ACTUAL=root  : cote · coté · côte · côté
fr_CA → ACTUAL=fr_CA : cote · côte · coté · côté     ← accents lus à rebours
```

Raison de fond, indépendante du multilingue : on trie des noms de termes WordPress, lus par
`get_terms()`. C'est la locale qui a produit les chaînes qui doit les ranger.

**Effet mesuré sur le site**, facette Catégorie : **31 positions sur 109** changent.

```
rang 36   avant: Diffuseurs à bâtonnets       après: Démaquillants & nettoyants
rang 37   avant: Dissolvants                  après: Déodorants
rang 39   avant: Déodorants                   après: Diffuseurs à bâtonnets
```

**Coût** : `Collator::compare()` vaut 0,121 µs la paire contre 0,029 µs pour `strnatcasecmp`, soit
**+7 µs par facette** au plafond de trente — 0,005 % d'une page à 306 ms. `Collator::getSortKey()`
en décoration-tri-restitution a été mesuré **1,8× plus lent** à ce volume (`usort` ne fait que
quatre comparaisons par élément sur trente valeurs, quand une clé en coûte trois) : écarté.
`sortWithSortKeys()` aussi — appelée sur autre chose que des chaînes, elle rend `true` sans rien
trier, sans code d'erreur.

**Conséquences écrites dans `decisions.md`** : `Name` devient sensible à la casse au niveau
tertiaire ; l'ordre suit WordPress, pas Laravel ; sans `ext-intl` le repli est `strnatcasecmp`.
L'extension est déclarée en `suggest`, jamais en `require` — l'exiger contredirait le constat
lui-même.

⚠️ **Ce qui reste ouvert** : les deux facettes du module en `Name` (catégorie, marque) sont
purement textuelles. Savoir si Pluralia les garde en `Name` ou les passe à `Declared` — qui
honorerait en plus un ordre posé dans l'admin — est une autre question, à ouvrir sous son propre
numéro le jour où elle se pose.

### R-88 · 🟠 · **fermé le 2026-09-08** · ouvert le 2026-09-08 — le module livre les facettes de Pluralia, et un projet ne peut pas déclarer les siennes

Relevé par Louis sur `ProductListing.php:27` (`private const string SIZE = 'pa_contenance';`).

**Étendue mesurée — plus étroite qu'il n'y paraît.** Deux littéraux seulement, dans tout le module,
sont propres à Pluralia : `product_brand` et `pa_contenance`, tous deux dans `ProductListing`.
`product_cat`, `product_visibility`, `exclude-from-catalog`, `post_type`, `post_status` sont des
universaux WooCommerce/WordPress, pas des choix de projet.

**L'indexation, elle, est déjà générique** : `FacetedPostIndexable::resolveIndexedTaxonomies()` lit
`get_object_taxonomies()` des types indexés — **toutes** les taxonomies du site deviennent
`facets.<taxonomie>` et filtrables, sans configuration. Un site avec `pa_couleur` l'a déjà dans son
index.

**Ce qui se passe sur un site sans ces taxonomies** : rien ne casse. La facette déclarée ne reçoit
aucune distribution, `FacetValues::of()` rend `[]`, le `<fieldset>` sort masqué (c'est le cas de
`R-84`), et elle ne coûte **aucune** requête supplémentaire — `QueryPlan::isCountedApart()` exige
une valeur sélectionnée, qu'une facette vide n'a jamais.

**Ce qui est impossible** : déclarer les facettes du site. La liste est figée dans
`ProductListing::facets()`, du code du module. Et la porte de sortie théorique — le projet déclare
son propre `Listing` — bute sur `ListingRegistry::add()`, qui indexe par `name()` : une classe de
projet nommée `products` entre en collision avec celle du module, et c'est l'ordre de découverte
qui tranche. Ni conçu, ni documenté, ni testé.

C'est l'énoncé précis de ce que `Q-07` pose en termes de dépendance WooCommerce. **`T-41` en est un
symptôme** — rendre l'*ordre* réglable par configuration n'a de sens que tant que le projet ne peut
pas déclarer ses facettes ; s'il le peut, l'ordre vient avec elles.

---

**Corrigé le 2026-09-08 sans sortir `ProductListing` du module.** La réponse de fond de `Q-07`
restait lourde ; le module avait déjà l'idiome qu'il fallait — `bindIf` sur `CardProjector`, « le
module fournit un défaut, sauf si le projet a lié le sien ».

Deux contrats, séparés pour donner la main **partiellement** :

| Contrat | Défaut du module |
| --- | --- |
| `Contracts\ProductFacets` | `WooCommerceFacets` — catégorie et marque |
| `Contracts\ProductSorts` | `WooCommerceSorts` — prix ↑↓, nouveautés |

Liés en `scopedIf`. `ProductListing` ne fait plus que déléguer, et perd les deux littéraux de
Pluralia ; `product_cat`, `product_brand` et `product_visibility` passent dans une énumération
`ProductTaxonomy`. Côté projet, `App\Cms\Products\CatalogueFacets` déclare les trois facettes de
la boutique, `pa_contenance` comprise, et `AppServiceProvider` la lie.

**Ce que ça ferme au passage :**

- **`T-28` pour moitié** — les deux implémentations mémoïsent. `facets()` est lu à **sept**
  endroits par requête et `sorts()` à **six**, sans aucun cache : on passe de treize
  reconstructions à deux ;
- **le `catch (Throwable)` muet de `ListingDiscovery`** — une exception de projet dans ce chemin
  faisait disparaître le listing et remontait sous la forme trompeuse « aucun listing déclaré ».
  Un `ListingUnavailable` dédié marque le retrait volontaire (la garde WooCommerce) ; tout le reste
  est désormais `report()`é.

**Vérifié.** Suite complète : `OK (169 tests, 354 assertions)`. Et sur `/boutique`, les trois
facettes rendues avec leurs libellés traduits, la contenance dans l'ordre de l'admin, et les tris
du module :

```
product_cat « Catégorie » · product_brand « Marque » · pa_contenance « Contenance »
Pertinence · Prix croissant · Prix décroissant · Nouveautés
```

**Trouvé en écrivant les tests, et non documenté jusqu'ici** : la suite du projet partage **une
seule application** pour tout le run (gotcha 23 du `CLAUDE.md` racine, posé pour le `LogManager`).
Corollaire non écrit : **les liaisons du conteneur fuient d'un test à l'autre.**

⚠️ **Le premier correctif était pire que le défaut**, relevé par une revue contradictoire le jour
même. Reposer les défauts dans `setUp()` remplaçait durablement le binding du projet pour **tous
les tests suivants** : un test placé après la classe obtenait `WooCommerceFacets` au lieu de
`CatalogueFacets`. Prouvé par une sonde, puis corrigé en ne touchant plus au conteneur du tout —
les objets sont construits à la main (`new ProductListing($facets, $sorts)`), et la précédence de
`scopedIf` est vérifiée dans `tests/Unit/ProductSeamBindingTest.php` sur un `Container` neuf, hors
de l'application. La même sonde passe désormais.

**Ce que ça ne ferme pas** : `Q-07` reste ouverte — `ProductListing` est toujours du WooCommerce
dans un paquet générique, et la collision de nom dans `ListingRegistry` n'est toujours ni conçue ni
testée. Elle est simplement devenue moins urgente : un projet n'a plus besoin de déclarer son
propre `Listing` pour choisir ses facettes. `R-89` (ce qu'un gabarit rend) est fermé depuis le 2026-09-09.

### R-89 · 🟠 · **fermé le 2026-09-09** · livré le 2026-09-08 · ouvert le 2026-09-08 — un gabarit ne peut pas choisir les facettes qu'il rend

`facets.blade.php:2` boucle sur `$listing->facets()` sans filtre, et `ListingComponent` n'accepte
que `name` et `scroll`. Un thème rend donc **toutes** les facettes déclarées, dans un seul `<div>`,
ou aucune.

Deux besoins déjà exprimés que ça bloque :

- placer une facette ailleurs qu'avec les autres — un rayon en tête de page, le reste en colonne ;
- la modale mobile où **chaque `fieldset` est un menu déroulant** (demandée en séance le
  2026-09-08, avec T-07) : elle suppose de pouvoir rendre les blocs séparément.

Aujourd'hui la seule issue est de surcharger la vue entière, ce qui fige le markup du module dans
le thème et le décroche des versions suivantes du contrat.

**Piège à ne pas rater dans le correctif** : le sous-ensemble doit être **de rendu seulement**. Une
facette non rendue mais présente dans l'URL filtre toujours, et la description JSON doit continuer
de la publier — sinon le client cesse de la compter. Le plan de requête ne doit donc pas voir la
différence : `CurrentListing` partage une seule recherche par nom, deux composants d'une même page
ne peuvent pas produire deux recherches.

**Contrainte posée en séance le 2026-09-08 : aucun nom de taxonomie en dur dans un gabarit.**
« Si on change de nom un jour ça sera la merde. » Le risque est borné — `product_cat` vit aussi
dans `term_taxonomy`, dans les URLs `/categorie-produit/…` et dans l'index, et le renommer est une
migration que `CLAUDE.md` § 6 encadre déjà — mais un gabarit n'a pas à porter ce nom.

Deux cas, deux réponses :

- **placer chaque facette** ne demande aucun identifiant : le slot expose la liste, le thème boucle
  et habille (`@foreach ($facets as $facet)` autour d'un `<x-meilifacets::facet :facet="$facet" />`) ;
- **n'en rendre que certaines** en demande un. Trois formes possibles — la taxonomie nue (casse en
  silence), une constante de classe (`:taxonomy="ProductListing::CATEGORY"`, un FQCN dans une vue),
  ou **un nom porté par la facette** (`new Facet('product_cat', __('Category'), name: 'category')`,
  puis `<x-meilifacets::facet name="category" />`). La troisième suit l'habitude du module, qui
  n'expose jamais une taxonomie et la mappe déjà (`url_parameters`). Réserve : ce serait un
  **troisième** nom pour une même chose, après la taxonomie et le paramètre d'URL. Réutiliser le
  paramètre d'URL comme identifiant l'éviterait, mais il est en français quand le code est en
  anglais, et une taxonomie non mappée retombe sur `f_<taxonomie>`.

**Mesuré le 2026-09-08 — déclarer des facettes ne coûte rien, en tenir coûte linéairement.**

```
0 facette tenue → 1 requête dans le multi-search   22 ms
1 facette tenue → 2 requêtes                       18 ms
2 facettes      → 3 requêtes                        8 ms
3 facettes      → 4 requêtes                       10 ms
```

Une seule requête HTTP dans tous les cas — c'est un `multi-search`. Le nombre de requêtes internes
vaut `1 + facettes multi-sélection tenues` (`QueryPlan::isCountedApart()` exige une valeur
sélectionnée). À ce volume de catalogue, le coût marginal reste sous le bruit de mesure. Corollaire
pour `R-88` : un back-office qui laisserait cocher douze facettes ne coûterait rien tant que le
visiteur n'en tient qu'une ou deux.

**Livré le 2026-09-08, complété le 2026-09-09, validé par Louis le 2026-09-09** sur constat d'usage :
« j'ai bien les filtres qui sont ok même en étant détaché » — c'est-à-dire le point qui engageait le
plus, une facette sortie du groupe qui filtre toujours. Deux modes de placement, et un nom porté par
la facette. *Ce bloc remplace un bilan écrit le 2026-09-08 qui
affirmait le contraire de ce qui a fini par être livré : « ni nom porté par la facette », « il n'y a
rien à nommer », « le sous-ensemble n'a pas été livré ». Les trois étaient vrais de la première
livraison et faux le lendemain (`R-104`).*

**1 · La vue est découpée**, et un thème passe déjà devant la cascade de vues du module.

```
avant : facets.blade.php = conteneur + boucle + <fieldset> + bouton Appliquer
après : facets.blade.php = conteneur + boucle + bouton Appliquer
        facet.blade.php  = un <fieldset>
```

Un thème qui veut une facette en menu déroulant surcharge **`components/facet.blade.php` seule**,
et hérite des versions suivantes du markup interne.

**2 · Une facette porte un nom**, la troisième forme que le constat énumérait :

```php
new Facet('product_cat', __('Category'), name: ShopFacet::Category)
```

`string|BackedEnum`, avec repli sur la taxonomie quand rien n'est déclaré — donc rien à écrire pour
démarrer. Un projet range ses noms dans une énumération (`App\Cms\Products\ShopFacet`), et **aucun
nom de taxonomie n'apparaît dans un gabarit** : la contrainte posée en séance est tenue.

**3 · Deux modes de placement**, comme `sort` et `reset` :

```blade
<x-meilifacets::facet :facet="ShopFacet::Category" class="lg:col-span-2" scroll />
<x-meilifacets::facets />
```

Le premier place une facette où le thème veut ; le second prend **ce qui reste**
(`ResolvedListing::remainingFacets()` filtre sur ce qu'un gabarit a placé à part). L'ordre est
libre : une facette placée après le groupe lève, puisque le groupe l'a déjà prise.

**Le sous-ensemble a donc bien été livré**, contrairement à ce que le bilan précédent disait —
`facetNamed()`, `place()`, `placeApart()`, `remainingFacets()`.

**4 · Une facette n'est rendue qu'une fois.** Deux blocs identiques dupliqueraient les entrées et
les identifiants qu'elles portent, d'où une exception nommée plutôt qu'un doublon silencieux
(demandé en séance : « il faut générer une erreur Laravel non ? »). Réserve ouverte : `R-95`.

**5 · Ce qu'un gabarit passe arrive.** `{{ $attributes->class('meilifacetsFacet') }}` et
`{{ $scrollMark() }}` sur le `<fieldset>` — ajoutés le 2026-09-09 par `R-98` et `R-99`, sans quoi
déplacer une facette dans une case de grille précise ne servait à rien.

**Deux contraintes de conception, venues de la séance :**

- **le crochet `facet` est sur l'élément le plus extérieur**, parce que c'est lui que le client
  masque (`R-84`). Un thème qui enrobe déplace le crochet avec lui — écrit dans la vue et couvert
  par un test ;
- **le panneau est animable**. Emil animera ces blocs, probablement en grille. La vue livre donc
  `.meilifacetsFacetPanel` (la ligne de grille) et son enfant `.meilifacetsFacetPanelInner`
  (`overflow: hidden`) : sans eux, animer imposait de réécrire toute la liste. Le panneau porte un
  `id` (`ElementId::facetPanel()`) pour qu'une gâchette de thème y pointe son `aria-controls`.
  `<details>` a été écarté : son ouverture n'est animable qu'avec du CSS très récent et inégalement
  supporté.

**Mesuré dans le navigateur**, en injectant le CSS qu'un thème écrirait :

```
panneau ouvert 259px → à mi-parcours 51px → fermé 0px → rouvert 259px
```

La collapse en `grid-template-rows: 1fr → 0fr` fonctionne sur le markup livré, sans une ligne de
JavaScript. Vérifié aussi que le découpage n'a rien cassé : dépliage `10 → 24`, filtrage
`?marque=aeris` à 10 cartes, et une facette vidée par la recherche masque bien son `<fieldset>`,
légende comprise.

⚠️ **Ce qui reste à faire côté feuille de style**, et qui n'est pas dans le module :
`[data-meili][hidden] { display: none !important }` (`meilifacets.css:1`). Le `!important` oblige un
thème à surenchérir pour reprendre `display`, donc pour animer. Le retirer suffit — la spécificité
`0-2-0` bat déjà un reset de thème. Corollaire pour Emil : rendre visible dans l'arbre ce que
`hidden` en sortait rend les cases repliées focalisables, d'où un `inert` sur le conteneur fermé.

L'API publique qui en sort est documentée dans `configuration.md`, section « Placer les facettes
dans un gabarit ».

### R-90 · 🟡 · ouvert · 2026-09-08 — une facette sur une taxonomie non indexée rend tout le listing indisponible, sans la nommer

Résiduel d'un constat de revue par ailleurs invalidé. L'indexation rend filtrables **toutes** les
taxonomies attachées aux types indexés (`FacetedPostIndexable::resolveIndexedTaxonomies()`), donc
une facette déclarée par un projet fonctionne sans réglage — c'est vérifié, et c'est ce qui
invalidait le constat d'origine.

Reste le cas étroit : une facette déclarée sur une taxonomie qui n'est attachée à **aucun** type
indexé — faute de frappe, taxonomie d'un autre type de contenu, ou taxonomie enregistrée après le
dernier rafraîchissement des réglages d'index. Meilisearch rejette alors la recherche entière,
`ResolvedListing` attrape l'échec, et le visiteur voit la vue de repli « indisponible » **sans que
rien ne nomme la facette fautive**.

Ergonomie de diagnostic, pas défaut de comportement. Une garde au rendu — comparer les taxonomies
déclarées à `filterableAttributes` et journaliser l'écart — coûterait un appel de réglages par
rendu, donc à peser contre le chemin chaud.

### R-91 · 🟡 · ouvert · 2026-09-08 — le garde-fou de la feuille de style recopie ce qu'il devrait vérifier

Relevé par une revue contradictoire, sur des fichiers en cours de rédaction côté Louis
(`resources/assets/css/meilifacets.css`, `tests/js/stylesheet.test.js`) : **non corrigé, consigné.**

`stylesheet.test.js` énumère les hooks de commande dans un littéral qui est la copie de la liste du
CSS. Un crochet oublié des deux côtés passe au vert : le test ne vérifie pas la couverture, il
vérifie que deux listes identiques le sont. Le dériver de `Hook` ferait échouer l'oubli suivant.

Deux symptômes déjà présents, mesurés :

- `data-meili="more"` n'apparaît dans **aucun** sélecteur de la feuille — `grep -c` rend `0` sur
  386 lignes. Le bouton de dépliage sort donc en `<button>` brut, sans `cursor: pointer` ni la
  primitive partagée par les six autres commandes ;
- `[data-meili="sort-option"][data-active]` (ligne 277) et `[data-meili="sort-option"]:hover`
  (ligne 299) ont la **même** spécificité `0-2-0` — une requête média n'en ajoute pas — donc le
  survol gagne. Au clavier, pointeur posé sur la liste, l'option active perd sa teinte au profit
  de celle du survol : deux lignes se lisent « courante », aucune « sélectionnée ».

### R-92 · 🟠 · **fermé le 2026-09-08** · ouvert le 2026-09-08 — le correctif de `R-87` était inerte, et son cas d'énumération était mal placé

Deux défauts, trouvés en répondant à « as-tu réellement vérifié ? ». Non.

**1. Le correctif ne s'exécutait pas.** `use Collator;` n'avait jamais été ajouté au provider — le
remplacement visait `use Illuminate\Support\Facades\Blade;`, absent de ce fichier. Dans le
namespace du provider, `Collator::class` résolvait `Modules\MeiliFacets\Providers\Collator`,
`class_exists()` rendait `false`, et `collator()` sortait `null` dès sa première ligne.

```
app(NameOrder::class) → collateur : NULL — repli strnatcasecmp
```

**Rien ne l'a signalé** : `composer check` vert, 178 tests verts. Les tests unitaires de `NameOrder`
fabriquent leur propre `Collator` ; ils prouvaient le comparateur, jamais son câblage. C'est la
faute de `R-42` refaite à l'identique — mesurer le mécanisme et appeler ça une vérification.

**Et la mesure vendue avec était trompeuse** : les « 31 positions sur 109 » portaient sur la liste
à plat des catégories, que personne n'affiche. La facette catégorie est un `ChildTermsFacet`, elle
ne montre qu'un niveau à la fois — et sur ce catalogue **aucun niveau ne change d'ordre**. Le
correctif est juste ; son effet visible aujourd'hui est nul.

**2. `DisplayOrder::Name` était un `ValueOrder` déguisé.** Les deux autres cas ne demandent rien —
`Count` ne trie pas, `Declared` lit un ordre déjà présent. Seul `Name` avait besoin d'un
collaborateur, ce qui forçait `FacetValues` — service générique traversé par tous les listings — à
prendre une quatrième dépendance et à construire un collateur **à chaque rendu**, y compris quand
aucune facette ne trie par nom.

**Corrigé** : `Name` sort de l'énumération, `NameOrder` devient l'ordre qu'une facette déclare
(`order: $this->names`). `FacetValues` perd sa dépendance, `comparing()` perd une branche et se
réduit à une ligne, `DisplayOrder` ne garde que les deux cas autonomes, et le collateur n'est
construit que si une liste de facettes le demande. Liaison passée de `bind` à `scoped` : une
instance par requête au lieu d'une par résolution (5,48 µs mesurés).

**Vérifié par le conteneur, pas par un script :**

```
liste résolue : App\Cms\Products\CatalogueFacets
  product_cat → NameOrder · product_brand → NameOrder · pa_contenance → DisplayOrder::Declared
NameOrder du conteneur : collateur présent (fr)
même instance sur deux résolutions : true · la facette porte bien CE NameOrder : true
```

**Coût mesuré de la méthode incriminée** : `collator()` vaut **1,18 µs** à chaud — 0,0004 % d'une
page à 306 ms. Le reproche « peu optimisé » ne portait pas sur le temps mais sur le couplage, et
c'est celui-là qui est levé.

---

### R-158 · 🟠 · **fermé le 2026-09-23** · ouvert le 2026-09-23 — la recherche produit sert tout le catalogue

Mesuré en fermant `R-149` : `/?s=creme&post_type=product` rend 16 cartes du catalogue quand WordPress
trouve **6 produits** pour ce terme, et le filtre de base publié ne porte rien sur la recherche. Le
module ne lit que son propre paramètre (`StateReader::read()`, `QueryParameter::Query`), jamais le `s`
de WordPress — alors qu'il tient bien la page pour un listing (`ListingPage::isCurrent()` inclut
`is_search()`).

Même famille que `R-149`, et que `R-60` avant lui pour `/page/N` : **le module remplace la requête de
WordPress, donc tout ce que l'URL veut dire doit être relu explicitement.** Les query vars des facettes
le sont de façon générique ; le terme d'archive l'est depuis `R-149` ; la recherche ne l'est pas. À
trancher : relire `s` quand le paramètre du module est absent, ou écrire que la recherche produit
native n'est pas servie par le listing.

**Tranché par Louis le 2026-09-23 : le terme appartient à la page, pas à l'état.** Même forme que
`R-149` — ce que l'URL désigne est épinglé par le serveur, ce que le visiteur coche ou tape reste son
état. La passe de conformité avait posé les trois options ; celle retenue évite qu'un terme voyage deux
fois dans l'URL (`?q=creme&s=creme`, mesuré comme conséquence de l'option « amorcer l'état »).

**Corrigé** : `Listing::baseQuery()` à côté de `baseFilter()` ; `ProductListing` lit `get_query_var('s')`
quand `is_search()`, coupé à `StateReader::MAX_QUERY_LENGTH` comme le paramètre du module ;
`QueryPlan::text()` applique une règle unique — ce que le visiteur a tapé, sinon le terme de la page —
sur les trois requêtes, la non filtrée comprise ; `ListingDescription` publie `baseQuery`, et le client
en fait le même usage (`listing-query.ts`). `s` n'est jamais écrit par le client : `PageAddress` le garde
dans l'adresse, comme le 2026-09-17 l'a décidé.

**Mesuré après correctif** : `/?s=creme&post_type=product` rend 8 produits au lieu de 16, avec
`baseQuery='creme'` ; `/?s=creme&q=lait&post_type=product` rend 4 produits, le terme du visiteur
l'emportant ; `/boutique` et `/marque/avril` inchangées. ⚠️ Les deux moteurs ne coïncident pas :
WordPress trouve **6** produits pour « creme », le moteur **8** — les deux de plus remontent par le nom
de leur catégorie (« Laits & crèmes corps »), le module laissant `searchableAttributes` à `["*"]`
(`R-27`). C'est le réglage de pertinence du lot 5, et l'écart est voulu tant que le module doit
remplacer la recherche native.

**Panne trouvée par la mesure, pas par la suite** : ajouter la méthode au contrat a mis toutes les pages
en 500, `ResolvedListing` enveloppant le listing sans implémenter le contrat. Aucun test ne rendait la
description ; `ListingDescriptionTest` le fait désormais, et échoue si la délégation manque (vérifié en
la retirant).

**Passes** : un docblock déplacé qui annotait la mauvaise méthode dans la doublure de test, quatre
commentaires qui paraphrasaient le code, deux miroirs écrits en polarités inverses, un nom de test qui
disait l'inverse de son contenu. La décision, elle, est écrite dans `decisions.md` plutôt que dans un
commentaire.

**Second tour de passes** : le commentaire d'un test contredisait son propre corps, un nom de test
promettait plus qu'il ne prouvait, `QueryPlan::text()` portait le nom que `StateReader::text()` donne à
autre chose (devenu `searched()`), la constante disait l'état plutôt que la query var
(`SEARCH_QUERY_VAR`), et le `trim()` n'était couvert par aucun test — il l'est.

**La cause de la panne est fermée, pas seulement l'incident** : `ResolvedListing` recopie le contrat à
la main, et c'était la deuxième panne globale de cette famille (la première est au Journal du
2026-09-08). `ResolvedListingParityTest` compare par réflexion les méthodes publiques de `Listing` à
celles de `ResolvedListing` : retirer la délégation fait désormais échouer la suite autonome, sans
qu'aucune page n'ait à être chargée.

**Vérifié** : `composer check` vert, suite `Modules` 403 tests, client 282. Relevés à part : `R-159`,
`R-160`, `R-161`.

### R-164 · 🟡 · ouvert · 2026-09-24 — une facette ne peut pas déclarer comment ses valeurs se présentent

Toutes les valeurs de facette sortent en cases à cocher (ou en radios, selon `SelectionMode`) ; rien
ne permet de dire qu'une facette se présente en pastilles type « 15 ML », sinon en surchargeant
`facet.blade.php` pour tout le listing. La maquette de la barre de filtres mêle les deux. Relevé par
la passe de conformité du chantier « barre de filtres » ; `D-07` l'excluait à la lettre (« le module
ne sait pas que ce sont des pastilles »).

**Tranché le 2026-09-24** (C-5) : enum `Presentation` (`Control`/`Pill`, sans `Radio` — `R-10`),
déclarée sur la facette dans `ProductFacets`, surchargeable par attribut ; une facette `Control` doit
sortir le HTML actuel à l'octet près. Renversement de `D-07` noté à sa place. Rattaché au chantier
`R-48`, étape 3a de [chantier-filtres.md](chantier-filtres.md).

### R-163 · 🟡 · ouvert · 2026-09-24 — le listing n'annonce pas son nombre de résultats

Aucun composant ne rend le total (« 88 articles ») et aucune vue ne porte de région `aria-live`
(lecture de `resources/views/components/`) : après un filtrage, rien ne dit combien de produits
restent, et un lecteur d'écran n'apprend même pas que la grille a changé. La maquette de la barre le
montre en desktop comme en mobile. Relevé par la passe de conformité du chantier « barre de
filtres » ; voisin de `R-50`, qui est la même donnée pour les moteurs.

Rattaché au chantier `R-48`, étape 2a de [chantier-filtres.md](chantier-filtres.md) :
`<x-meilifacets::total>`, crochet `total`, région `aria-live="polite"`.

### R-162 · 🟠 · ouvert · 2026-09-24 — le doublon silencieux n'est gardé que pour les facettes

`ResolvedListing::place()` refuse qu'une facette soit rendue deux fois, et c'est le seul garde-fou du
module. Rien n'équivaut pour les autres composants :

- `Reset` et `ActiveFilters` n'appellent ni `place()` ni `placing()` (`app/View/Components/Reset.php`,
  `ActiveFilters.php`), et le client les peint par `contract.one(...)`
  (`listing/filter-summary-view.ts:21-22`) — donc **le premier trouvé seulement**. Un second « Tout
  effacer » ou un second compteur de filtres actifs posé dans un tiroir mobile réagirait au clic, la
  délégation étant sur la racine, mais **n'afficherait jamais rien de juste** : figé sur son rendu
  serveur pendant que l'autre se met à jour.
- Même famille, plus large : deux `<x-meilifacets::listing>` du même nom sur une page ne sont gardés
  nulle part — `listing-page.ts` lie une instance par racine, donc deux écritures d'historique, et
  `ElementId` ne distingue que par nom de listing, donc des identifiants dupliqués.

Trouvé en fermant `R-95`, dont la garde est justement ce qui manque ici. Le chantier mobile (`R-48`)
rend le cas concret : c'est dans un tiroir qu'un thème est tenté de reposer un compteur ou un bouton
« Tout effacer » déjà rendus ailleurs.

Pistes : étendre la garde à tout `Placeable` rendu, ou peindre par `contract.all(...)` là où le
doublon est légitime. À trancher avec le chantier mobile, pas avant : c'est lui qui dira si un thème a
besoin de deux exemplaires.

### R-161 · ⚪ · ouvert · 2026-09-23 — une recherche sans type de contenu est comptée comme listing sans en rendre aucun

Relevé par la passe de conformité de `R-158`. `/?s=creme`, sans `post_type=product`, rend le gabarit de
recherche du thème : zéro carte, aucune description publiée. `Http\ListingPage::isCurrent()` répond
pourtant vrai (`is_search()`), donc la page est traitée comme une page de listing par la politique
d'indexation et par le chargement du client. Sans conséquence aujourd'hui — le cœur pose déjà le
`noindex` sur une recherche — mais la garde ment sur ce qu'elle garde.

### R-160 · 🟡 · ouvert · 2026-09-23 — sur une recherche, le module n'exclut pas ce que WooCommerce exclut

Relevé par la passe de conformité de `R-158`, lu dans la source. Sur une recherche, WooCommerce écarte
les produits marqués `exclude-from-search` (`class-wc-query.php:929`, appliqué depuis `:424-437`), alors
que le module écarte toujours `exclude-from-catalog`, quelle que soit la page
(`ProductListing.php`, `HIDDEN_FROM_CATALOG`). Depuis `R-158`, la recherche produit est servie par le
moteur : elle rend donc un ensemble qui n'est pas celui de WooCommerce. Sur Pluralia, un produit est
`exclude-from-search` seulement (#400, mesuré au lot prix) : il reste trouvable ici, alors que la
recherche native le cacherait.

### R-159 · 🟡 · ouvert · 2026-09-23 — un terme que le moteur ne tokenise pas sert tout le catalogue

Mesuré pendant les passes de `R-158` : `/?s=%3F%28&post_type=product` rend **16 produits** — le
catalogue entier — quand `WP_Query` en trouve **0**. Le moteur ne reconnaît aucun mot dans `?(` et
répond comme à une recherche vide. Le défaut n'est pas propre au terme de la page : `?q=%3F%28` fait la
même chose, il préexiste donc sur le paramètre du module. À trancher : traiter « le moteur n'a rien
reconnu » comme zéro résultat, ou l'écrire comme limite. Lot 5, avec la pertinence.

Même famille, mesuré au second tour de `R-158` : `trim()` en PHP ne coupe pas les blancs Unicode, `.trim()`
en JavaScript si. `/?s=%C2%A0&post_type=product` publie donc une espace insécable comme terme de page et sert
les 16 produits, là où le client aurait lu une chaîne vide.

### R-157 · ⚪ · ouvert · 2026-09-23 — un refus du moteur est journalisé comme une absence de réponse

Relevé en traitant `R-149`. `MeilisearchEngine::send()` enveloppe tout `Throwable` dans
`SearchFailed::unreachable()`, dont le message dit « Meilisearch did not answer. Check MEILI_HOST and
that the engine is running ». Or le moteur répond, et précisément : mesuré, un filtre sur un attribut
non déclaré rend `Attribute 'facets.x' is not filterable`. Le diagnostic envoie donc chercher une
panne de connexion là où il s'agit d'un réglage d'index. Le comportement, lui, est juste : vue
indisponible et journalisation. Relève du lot 6.

### R-156 · 🟡 · ouvert · 2026-09-23 — un client en session exonéré de TVA fait indexer des prix hors taxe

Relevé par les passes de `R-154`, déjà écrit deux fois dans la documentation (`configuration.md`, `prix.md`)
sans porter de numéro. `AnonymousVisitor` ne change que les **droits** : `WC()->customer`, le client en
session, n'est pas réinitialisé — personne n'écoute `set_current_user` chez WooCommerce. Or l'exonération de
TVA se lit là (`wc-product-functions.php:1526`, `:1548`) : un administrateur dont la session est exonérée fait
indexer des prix hors taxe pour tout le monde. Même forme que `R-154`, autre porte. Non mesuré.

### R-155 · 🟡 · ouvert · 2026-09-23 — rien ne réindexe un produit groupé quand un de ses enfants change

Relevé par la passe de conformité de `R-154`, lu dans la source, non mesuré. Le prix d'un groupé se calcule
au moment où **le groupé** est indexé, à partir de ses enfants. Or WooCommerce ne recalcule les prix d'un
groupé qu'à l'enregistrement du parent, et seulement si sa liste d'enfants a changé
(`class-wc-product-grouped-data-store-cpt.php:50-55`, `:63-65`) ; aucun code du module ni de MeiliScout ne
réindexe le parent quand un enfant est enregistré, passe en brouillon ou change de prix. Le document du
groupé garde donc son ancienne fourchette jusqu'à sa propre réindexation. Antérieur à `R-146`, où le même
décalage portait sur les lignes `_price`. Jumeau connu : `R-12`, pour les termes.

### R-154 · 🟡 · **fermé le 2026-09-23** · ouvert le 2026-09-22 — un produit groupé n'indexe pas la même chose selon qui déclenche l'indexation

Relevé par le troisième tour de passes de `R-146`, mesuré en mémoire : l'enfant #368 de #444 passé en
brouillon, #444 est projeté 30,60–47,88 sans utilisateur (cron, ligne de commande) et 23,40–47,88 avec
l'administrateur connecté. WooCommerce compte un enfant que l'utilisateur courant peut modifier
(`wc_products_array_filter_visible_grouped()`, `wc-product-functions.php:1743`) — pour le prix indexé
comme pour la carte (`get_price_html()`) : la carte indexée pendant un enregistrement en admin montre à
tous les visiteurs le prix d'un brouillon. Moins large qu'avant `R-146`, où les lignes `_price` comptaient
tous les enfants (`class-wc-product-grouped-data-store-cpt.php:76-85`). Réponse possible : projeter en
visiteur anonyme, comme `ShopTaxLocation` impose l'adresse — un comportement visible, à trancher par Louis.

**Tranché par Louis le 2026-09-23 : indexer en visiteur anonyme.** Mesuré à nouveau avant de coder, en
mémoire sur #444 (enfants #360 à 39,90 €, #361 à 25,50 €, #368 à 19,50 €, #360 simulé en brouillon) : sans
utilisateur, prix et carte donnent 19,50–25,50 ; avec l'administrateur, 19,50–39,90.

**Ce que la plateforme offre** (passe de conformité) : aucune fonction ne calcule un prix « en visiteur » —
`get_price_html()` et `get_visible_children()` passent par `get_primed_visible_children()`, qui appelle
`current_user_can()` sans cache (`class-wc-product-grouped.php:201-209`). WooCommerce bascule lui-même
l'utilisateur, dans un `try/finally`, pour ses webhooks (`class-wc-webhook.php:426-464`) et ses notes
d'admin (`Notes.php:408-411`). Seul le cœur de WordPress écoute `set_current_user` (`kses_init` et les notes
de bas de page, `default-filters.php:589`, `:651`) : ni WooCommerce, ni MeiliScout, ni les onze extensions
actives, ni l'hôte.

**Corrigé** : `AnonymousVisitor::during()` passe l'utilisateur à 0 et rétablit l'ancien dans un `finally` ;
`MeiliScoutBridge::asShopVisitor()` compose les deux gardes, adresse de la boutique et visiteur anonyme,
autour de la carte et du prix. Coût nul en cron et en ligne de commande (`wp_set_current_user()` sort quand
l'utilisateur est déjà 0, `pluggable.php:31-37`). Portée : les **droits** seulement — un client en session
exonéré de TVA reste hors d'atteinte, comme déjà écrit.

**Tests** : `AnonymousIndexingTest` projette un groupé à enfant brouillon deux fois, déconnecté puis connecté
comme administrateur (créé et supprimé par le test, sur décision de Louis ; vérifié avant : Mailjet inactif,
aucun webhook actif), et **compare les deux documents** — c'est la promesse du point, et elle tient quels que
soient les réglages de taxe, les attentes étant lues chez WooCommerce (`wc_get_price_to_display()`) plutôt
qu'écrites en dur. Il vérifie aussi que l'administrateur est rendu même quand la projection lève.
`AnonymousVisitorTest` couvre la garde sans WordPress. **Mutations tuées** : sans la garde, les deux documents
diffèrent ; sans le `finally`, l'utilisateur reste à 0. **Câblage vérifié en mémoire** sur le vrai filtre :
`apply_filters('meiliscout/post/document', [], $post)` sur #444, enfant #360 simulé en brouillon, rend
19,50–25,50 des deux côtés, et l'utilisateur courant est rendu.

**Passes (2026-09-23)** — traités : le nom `editor` pour un administrateur, `projected()` qui désignait deux
choses dans deux classes sœurs (devenu `documentOf()`), un test dont le nom ne parlait que du chemin d'erreur
(coupé en deux), la garde WooCommerce qui écartait aussi le test sans boutique, la garde de `during()` qui ne
couvrait qu'une des deux fonctions appelées, trois commentaires qui racontaient l'histoire du code plutôt
qu'une anomalie amont, et un identifiant fixe pour l'administrateur du test — qui se nettoie désormais de
lui-même si une exécution a été coupée avant son ménage.

**Refusé, par écrit** : fusionner `addCard()` et `addPrice()` pour n'envelopper qu'une fois. Mesuré :
44,2 µs par aller-retour d'utilisateur avec un administrateur connecté, 0,45 µs sans personne, soit **2,2 ms**
pour un enregistrement de produit en back-office (une cinquantaine de bascules, l'indexation repartant à
chaque écriture de méta). Les deux méthodes portent deux champs distincts du document ; les réunir pour
gagner un millième de seconde coûterait cette séparation.

**Vérifié** : `composer check` vert, suite `Modules` 388 tests, aucun utilisateur ni produit de test laissé en
base. Relevés à part : `R-155`, `R-156`.

### R-153 · 🟡 · ouvert · 2026-09-22 — la documentation décrivait le module d'avant le prix et le TypeScript

Demandé par Louis le 2026-09-22 : « Fait une grosse passe sur la documentation, pour voir si la doc
module est toujours bien à jour ». Six relectures en lecture seule, par sous-agents — README,
`installation.md` et `lots.md` ; `architecture.md` ; `configuration.md` ; `pieges.md` et `prix.md` ;
`decisions.md` ; le registre. Chaque affirmation décisive a été revérifiée dans la source avant
d'être corrigée ; ce qui n'a pas pu l'être est écrit comme tel.

**Ce qui était faux, en gros** : le prix décrit comme un travail à faire alors qu'il est indexé,
filtrable et triable ; `resolveIndexable()` ignoré, donc « une surcharge de `formatForIndexing()`
ne serait jamais appelée » ; `MEILI_INDEX_NAME` et `MEILI_MATCHING_STRATEGY` présentées comme lues ;
Pluralia décrite comme liant son propre `CardProjector` ; la règle de `Contract::VERSION` donnée
dans sa version d'avant `R-116` ; le contrat `facet` → `more` sans sa condition ; les paramètres
réservés, le `noindex` et la canonique sans les bornes de prix ni Yoast ; des valeurs CSS, des noms
de fichiers `.js` et une classe (`CardPainter`) antérieurs au passage au TypeScript.

**Corrigé** : les huit documents de `docs/` et le README. Les décisions validées ne sont pas
réécrites : un écart entre une décision et le code y est **annoté**, daté, avec son renvoi. Deux
commentaires de code répétaient les mêmes erreurs et sont corrigés — `FacetedPostIndexable` (la
surcharge « jamais appelée ») et le docblock de `IndexingPolicy::appliesTo()` (bornes de prix) ;
aucune ligne exécutable ne change.

**Registre** : `R-05` rouvert (fermé à tort), `R-35` fermé, `R-56` élargi ; ouverts : `R-150`,
`R-151`, `R-152`. Questions répondues et marquées : `Q-05`, `Q-06`, `Q-07`, `Q-14`, `Q-15` ; idées
`I-03` et `I-05`. `D-09` annoté de son renversement. Les tableaux de tâches ont retrouvé leur
colonne « État », que le rendu masquait.

**À trancher par Louis** — écrits dans les documents comme non tenus, pas corrigés :

- le renversement du défilement (`85b3097`, 2026-09-08) : qui l'a décidé ;
- la carte de l'archive, `<x-theme::product-card>` décidée, `<x-meilifacets::card>` rendue (`Q-10`, `R-45`) ;
- `R-47`, fermé par `D-07` et gardé ouvert par le Journal ;
- l'appui sur la visibilité native de WooCommerce : `exclude-from-catalog` n'apparaît qu'en passant dans
  une liste d'`architecture.md`, alors que le module lit la taxonomie `product_visibility` que la fiche
  produit écrit, l'applique dans le filtre de base — donc aux comptes aussi — et n'applique **pas**
  `exclude-from-search` (`R-160`). Écrire l'un sans l'autre donnerait une complétude fausse ;
- Varnish à 180 s dans « Validées », la stratégie de cache HTTP dans « En attente » ;
- les quatre apports du moteur postérieurs à la 1.10.3, qu'aucune ligne n'emploie (`R-41`) ;
- le repli de `NameOrder` sans `ext-intl`, « jamais silencieux » mais sans signal ;
- `apply_mode` : `submit` par défaut dans le code, `'immediate'` dans le gabarit publié ;
- « Structure, commandes, configuration » en attente alors qu'en partie tranché ;
- la suite `Plugin` du projet, à remesurer ; l'« Historique » de `decisions.md` et le Journal ci-dessous,
  arrêtés au 2026-09-02 et au 2026-09-09 : les tenir ou les retirer ;
- `Q-11` : tranchée dans `decisions.md`, jamais marquée ;
- `D-05` : les hooks du module ont changé (`.claude/hooks/`, non suivi par git) sans entrée au registre.

**README réécrit le 2026-09-22**, sur remarque de Louis (« il ne respecte pas les conventions »),
d'après les deux modèles qu'il a donnés : en anglais, avec *Why*, *Principles*, points d'extension,
*Requirements*, *Installation*, *Usage*, *Development*, *Documentation*, *Contributing* et
*License* — sans numéro de registre. Ni badge (pas de CI) ni SemVer (aucune version taguée).

### R-152 · ⚪ · ouvert · 2026-09-22 — des chaînes littérales restent là où la règle demande un nom

Trouvé par la passe documentaire (`R-153`), contre la décision « pas de chaînes littérales ».
`ProductListing::baseFilter()` écrit `'post_type'` et `'post_status'`, deux champs que MeiliScout
pose dans le document et que `DocumentField` ne nomme pas. `ProductPriceProjector::carriesSalePrice()`
lit `'_sale_price'` à côté de `ProductMeta::Price`. Les clés du plan de requête relèvent de `R-02`.

⚠️ Piège pour le correctif : ajouter `SalePrice` à `ProductMeta` le déclarerait **filtrable**, puisque
`WooCommerceIndexAttributes` déclare `ProductMeta::paths()`, soit tous ses cas.

### R-151 · 🟡 · ouvert · 2026-09-22 — le compteur d'une valeur de facette entre dans le nom de sa case

`architecture.md` pose que le compteur est **décrit** (`aria-describedby`) et jamais **nommé**, pour
qu'un filtrage ne renomme pas la case sous le curseur. La vue ne le tient qu'à moitié :
`facet.blade.php` rend le `<span data-meili="count">` **dans** le `<label>` qui enveloppe l'`<input>`,
donc dans le nom accessible que le navigateur calcule depuis ce label — et il est en plus décrit. Le
test (`FacetComponentTest`) vérifie `aria-describedby` et l'absence d'`aria-labelledby`, pas le nom.
Lu dans la vue et le calcul du nom accessible ; non écouté sur un lecteur d'écran. Le correctif touche
une vue surchargeable : à décider avec Louis.

### R-150 · 🟡 · ouvert · 2026-09-22 — MeiliScout teste une constante en majuscules et la lit en minuscules

`Config::get()` (`meiliscout/src/Config/Config.php:24-26`) teste `defined(strtoupper($key))` puis
renvoie `constant($key)`, la clé d'origine en minuscules. Une constante `MEILISCOUT_ASYNC_INDEXING`
définie ferait donc lever `Undefined constant` au lieu d'activer l'indexation différée. Lu dans la
source, non mesuré en requête ; `configuration.md` le signale. Correctif amont d'une ligne
(`constant($constKey)`), à proposer sur `feat/meilifacets` comme `resolveIndexable()` avant lui.

### R-149 · 🟠 · **fermé le 2026-09-23** · ouvert le 2026-09-17 — une archive de marque affiche tout le catalogue

`/marque/aeris` rend 16 cartes sur 5 pages et une plage de 0 à 199 €, comme `/boutique` ; `/boutique?marque=aeris`
en rend 10, de 9 à 47 € (mesuré par `curl` le 2026-09-17). `ProductListing::currentAisle()` ne lit que
`product_cat` : sur une archive de marque, le filtre de base ne porte pas le terme du chemin. Trouvé par la
passe de conformité des retours de la PR #2.

**Étendue mesurée le 2026-09-23** : le défaut ne touche pas que les marques. Toute archive produit
qui n'est pas une catégorie servait le catalogue entier — `/marque/avril` 76 produits au lieu de 5,
`/marque/aeris` au lieu de 10, `/selection/la-selection-coup-de-coeur` au lieu de 14,
`/les-essentiels/lefficacite-sans-effort` au lieu de 3 ; seule la catégorie était juste. Les
compteurs de facettes et les bornes du curseur suivaient : 0–199 € sur `/marque/aeris` au lieu de
9–47 €.

**Depuis quand** : le 2026-09-04, jour où l'archive a été confiée au module — `76e60f7` côté module,
`400eed4` côté thème, qui place le listing dans `archive-product.blade.php`, gabarit de **toutes** les
archives produit. `currentAisle()` n'a jamais lu que `product_cat`. Avant cette date, la grille venait
de la requête WordPress, qui filtrait juste. Le périmètre, lui, était déjà écrit : « sur les archives
produit, `ProductListing` aurait dû toutes les déclarer, puisque Pluralia y rend le listing partout »
(`decisions.md`, 2026-09-22).

**Corrigé** : `currentAisle(): ?string` devient `browsedTerm(): ?WP_Term` — `is_tax()` sur n'importe
quelle taxonomie, `get_queried_object()`, et une garde `is_object_in_taxonomy('product', …)` : un
produit ne porte aucun champ pour une taxonomie qui n'est pas la sienne, et filtrer dessus viderait le
listing. `baseFilter()` écrit `facets.<taxonomie> = <slug>`.

**Tranché par Louis le 2026-09-23** : une facette dont le chemin épingle la taxonomie n'est plus
offerte. Mesuré avant décision sur `/marque/avril` : la facette Marque ne proposait plus que « Avril
(5 résultats) », et `?marque=aeris` y rendait zéro produit. `Facet::narrowsUnder()` répond non quand
sa taxonomie est celle du chemin, `ChildTermsFacet` répond oui — c'est ce pour quoi il existe (`D-07`
intact, mesuré : la catégorie propose toujours ses enfants). `ProductListing::facets()` filtre là-dessus,
`filters()` non : la facette **sort du plan de requête** mais reste plaçable par son nom, sans quoi un
gabarit qui l'appelle explicitement lèverait « No facet named … ». Le choix porte sur le volume, à la
demande de Louis : le moteur ne compte plus sa distribution sur la recherche principale **ni** sur la
requête non filtrée, et ses libellés ne sont plus lus — sur Pluralia l'écart est sous le plancher de
mesure (0–1 ms), il ne l'est pas sur un catalogue de milliers d'entrées. Effet de bord souhaitable :
`/marque/avril?marque=aeris` rend les 5 produits de la marque au lieu d'une page vide, le paramètre
contradictoire n'étant plus lu.

**Recette, mesurée après correctif** : 5, 10, 14, 3 produits sur les quatre archives ci-dessus ; 1 sur
`/categorie-produit/maquillage/accessoires` et 16 par page sur `/boutique`, tous deux inchangés ; bornes
9–47 € sur `/marque/aeris` ; facette Marque masquée sur son archive, catégorie intacte sur la sienne.

**Tests** : `ProductArchiveTest` (sept cas : catégorie, toute autre taxonomie produit, taxonomie native
partagée avec les produits, taxonomie hors catalogue, hors archive, facette épinglée retirée du plan mais
gardée plaçable, catégorie conservée) et un cas dans `ChildTermsFacetTest`. **Cinq mutations tuées** : retour à `is_tax(product_cat)`, garde
`is_object_in_taxonomy` retirée, filtre `narrowsUnder` retiré de `facets()`, et `ChildTermsFacet`
rendu à la règle plate.

**Passes** : le nom `currentAisle` (« rayon » ne vaut que pour une catégorie), un commentaire qui
donnait une cause fausse — le moteur refuse un attribut **non déclaré filtrable**, alors qu'un champ
absent des documents rend simplement zéro résultat (mesuré des deux côtés) —, `baseFilter()` coupé en
deux niveaux, et quatre points sur le test : requêtes de termes en double, saut silencieux au milieu
d'une boucle, `assertNotEmpty` caché dans un assistant, nom de test illisible.

**Étendu le 2026-09-23, sur remarque de Louis** : `browsedTerm()` ne passe plus par `is_tax()`, qui
répond faux sur les taxonomies natives (`class-wp-query.php:2304` ne les range pas avec les autres) —
il lit `get_queried_object()`, qui rend le terme dans tous les cas. Une boutique qui attache
`category` à ses produits (`register_taxonomy_for_object_type()`, `taxonomy.php:768`, mesuré :
`is_category = true`, `is_tax = false`, objet interrogé `WP_Term`) est donc couverte elle aussi, et un
test l'épingle — cinquième mutation tuée en remettant `is_tax()`.

**Limites écrites, non traitées** : une
taxonomie produit enregistrée après la dernière poussée des réglages d'index fait répondre au moteur
« not filterable », donc la vue indisponible, jusqu'à la prochaine sauvegarde de produit ; le **champ**
du filtre est interpolé sans échappement, à la différence de la valeur — un nom de taxonomie vient de
`register_taxonomy()`, l'URL ne choisit que laquelle est interrogée.

**Vérifié** : `composer check` vert, suite `Modules` 396 tests / 1333 assertions. Relevé à part : `R-157`.

### R-148 · 🟡 · **fermé le 2026-09-17** · ouvert le 2026-09-17 — un produit dont le prix a été vidé est indexé à 0

Retour de revue sur la PR #2, vérifié par la passe de conformité. Vider le prix d'un produit simple ou
externe enregistre `_price = ''` (`class-wc-product-data-store-cpt.php:876`, seulement quand un champ de prix
change) ; `get_post_meta(…, false)` rend `['']`, qui passe la garde `[] ===` de
`ProductPriceProjector.php:39`, et `(float) ''` vaut 0. Le produit entre dans toute plage bornée en haut et
tire le minimum mesuré à 0. Le test existant ne couvre pas ce cas : un produit créé sans prix n'a aucune
ligne `_price`. Produits variables et groupés non concernés. Aucun produit touché sur Pluralia. Tranché par
Louis : `D-j` (`prix.md`).

**Corrigé** : `ProductPriceProjector::pricesOf()` écarte les lignes `_price` vides ou `null` avant de lire les
bornes — le prédicat de `sync_price()` et de MeiliScout, qui écartait déjà `''` de `metas._price` — et garde
`'0'` (`D-c`). Tests (`ProductPriceProjectionTest`) : un prix vidé après une sauvegarde ne projette rien
(prémisse `['']` vérifiée en base), une ligne laissée à `null` non plus (prémisse `[null]`), un produit à
`'0'` reste à 0. Mutations : garder `''`, garder `null`, écarter aussi `'0'` — chacune fait échouer un test.

**Cinq passes** (`module-review`). *Lisibilité* : `'_price'` écrit en dur — `ProductMeta::Price` ;
`_sale_price` reste littéral, l'ajouter à `ProductMeta` en ferait un attribut filtrable que le moteur
maintiendrait pour rien. *Commentaires* : la ligne disait « in the admin » alors que l'API REST, l'import CSV
et la modification rapide écrivent la même valeur, et citait un numéro de ligne de WooCommerce — réécrite
sur `handle_updated_props()` ; `prix.md` laissait croire que le correctif touchait `metas._price` — précisé.
*Performance* : rien, une lecture de meta déjà en cache. *Sécurité* : rien. *Contexte* : deux tests existants
parcourent tout le catalogue et supposaient que chaque produit a un prix — ils échouaient dès qu'un produit
au prix vidé existait, constaté pendant la revue ; ils ne vérifient plus que les produits dont toutes les
lignes sont numériques, la règle étant tenue par les trois tests ciblés, et la classe est verte avec un tel
produit présent (produit créé puis supprimé pour la mesure) ; une ligne `null` était
convertie en 0 — écartée, test ajouté. Un produit groupé dont un enfant est gratuit n'a pas un minimum à 0 —
**laissé tel quel** : `update_prices_from_children()` de WooCommerce retire `'0'` avec
`array_filter` avant d'écrire la fourchette du groupé, et le module lit les lignes que WooCommerce écrit ; les
deux produits groupés de Pluralia (#444, #445) n'ont aucun enfant gratuit.

**Cinq passes, second tour** (`module-review`, sur la version corrigée) : les six constats du premier tour
vérifiés résolus. *Lisibilité* : l'assistant des tests de catalogue recopiait la règle de production sous un
nom inexact, si bien qu'une règle fausse des deux côtés serait passée — retiré, ces tests ne vérifient plus
que les produits aux lignes toutes numériques ; `'_price'` restait en dur dans le test — `ProductMeta`.
*Commentaires* : `D-j` disait encore « dans l'admin » avec un numéro de ligne de WooCommerce — aligné sur
`handle_updated_props()` ; `prix.md` attribuait le cas `null` à `D-j` — rattaché à ce point ; le commentaire
du projecteur ne valait que pour un produit variable — il cite aussi `update_prices_from_children()`.
*Performance* : rien ; la lenteur de la file renvoie à `pieges.md` et `R-79`. *Sécurité* : rien. *Contexte* :
`it_gives_a_product_with_several_prices_an_interval` comptait des lignes brutes, qu'un groupé aux enfants de
même prix double — compte désormais les valeurs distinctes. Mutations rejouées sur la version finale (garder
`''`, garder `null`, écarter `'0'`) : chacune fait échouer un test ; classe verte avec un produit au prix vidé
présent (créé puis supprimé). Le code de production n'a changé depuis la vérification réelle ci-dessous que
d'un commentaire.

**Vérifié le 2026-09-17 en conditions réelles** — indexation synchrone, produit temporaire 686 :

- créé à 5 € : document `price` 5–5 ; dans le navigateur (Playwright), listé sous `/boutique?max_price=10`
  (7 cartes) ;
- prix vidé par `set_props()` puis `save()`, le chemin de la fiche produit : `_price` vaut `['']`, le document
  n'a plus ni `price` ni `metas._price` ; rejoué trois fois, avec une trace de chaque document construit par
  MeiliScout ;
- dans le navigateur : absent de `/boutique?max_price=10` (6 cartes), présent sans prix sur
  `/boutique?q=R-148`, bloc de prix masqué ;
- `ddev wp meiliscout index --clear` : 103 documents comme avant, promotions 20 / 56 inchangées, plage
  0–199 €, 686 seul produit sans `price` ;
- supprimé : document en 404, 76 produits ; `/boutique` rend 16 cartes sur 5 pages, plage 0–199 €, aucune
  erreur dans la console.

**Rejoué sur le code final** (après les correctifs des passes), produit temporaire 733 : document `price` 5–5,
puis prix vidé → `price` et `metas._price` absents, puis ligne `null` → absents ; tri `price.min:asc` et
`price.max:desc` dans le moteur → 77ᵉ sur 77 ; dans le navigateur, dernière carte de la page 5 en « prix
croissant » comme en « prix décroissant », absent de `/boutique?max_price=10` ; aucune erreur dans la console ;
supprimé, document en 404, 76 produits, promotions 20 / 56.

Relue deux secondes après une sauvegarde, une première lecture gardait l'ancien prix : la file de Meilisearch
traitait les tâches en 6 s, chaque poussée étant accompagnée d'une mise à jour des réglages de l'index (le
comportement de `ensureIndexExists()` décrit dans `pieges.md` et `R-79`). Sans lien avec ce point.

`composer check` vert (260 tests PHP, 280 TypeScript, Rector) ; suite `Modules` verte (377 tests).

### R-147 · 🟡 · **fermé le 2026-09-22** · ouvert le 2026-09-17 — `NativeFiltering` désarme toute requête produit principale

Retour de revue sur la PR #2, vérifié par la passe de conformité. `NativeFiltering::leaveTheMainQueryAlone()`
rend `false` sans condition : sur toute archive produit, WooCommerce ne filtre plus par prix ni par attribut
(`class-wc-query.php:787`, `Filterer.php:70`), qu'un listing du module soit rendu ou non. La portée écrite
est fausse deux fois : le docblock et `configuration.md` la disent « inerte sur tout autre listing », et
`filter_*` n'est désarmé que si la table de correspondance des attributs est active
(`class-wc-query.php:915-917`). L'exemple de retour arrière, `__return_true` global, annule la protection
partout. Aucun effet sur Pluralia (aucun widget de prix natif).

**Tranché par Louis, deux fois.** Le 2026-09-17 : chaque listing déclarerait ses pages. Le 2026-09-22, jamais
codée, cette décision est renversée : le composant peut être posé sur n'importe quelle page, WooCommerce ne
filtre que la requête principale des archives produit, et `ProductListing` aurait dû toutes les déclarer —
une méthode de plus dans le contrat pour une portée identique (`decisions.md`, « Le filtrage natif reste
désarmé sur toutes les archives produit »). La passe de conformité avait mesuré que les sept pages de
Pluralia qui rendent le listing sont toutes des archives produit.

**Corrigé — sans changement de comportement** : `NativeFiltering` rend toujours `false`. Son docblock passe de
huit lignes, dont une portée fausse (« inert on every other listing »), à une exacte. `configuration.md` dit la vraie portée — archives produit seulement
(`class-wc-query.php:381-452`), crochet `posts_clauses` jamais retiré (`:588`) et question reposée à chaque
`WP_Query` suivante sans `suppress_filters` —, corrige deux erreurs de plus (`filter_*` n'est désarmé qu'avec la
table de correspondance des attributs, active sur Pluralia, `Filterer.php:70-72`, sinon `tax_query` à
`:915-917` ; `rating_filter` est une `tax_query` sur les termes `rated-N`, pas une `meta_query`) et remplace
l'exemple `__return_true` par un filtre qui réarme une seule archive, avec l'avertissement : ni
`price_filter_post_clauses()` ni `filter_by_attribute_post_clauses()` ne regardent le type de contenu.

Tests (`NativeFilteringTest`, réécrit) : le filtre est interrogé comme WooCommerce l'interroge, avec une
`WP_Query` posée en requête principale, et non plus avec `null` ; le retour arrière documenté réarme une
catégorie et laisse la boutique désarmée ; ignoré sans WooCommerce ou sans catégorie. Mutations : le module
qui rend `true`, ou qui passe après le projet (priorité 30), fait échouer un test.

**Cinq passes** (`module-review`). *Lisibilité* : l'exemple visait `product_tag`, sans terme sur Pluralia, et le
test `product_cat` — l'exemple est passé sur `product_cat`, il est désormais l'extrait testé ; au second tour, il
réarmait encore toutes les catégories alors que la doc promet « cette archive seule » — il vise un terme, et le
test vérifie qu'une autre catégorie et une marque restent désarmées ; `A && B ? true :
$enabled` masquait la priorité des opérateurs — `$enabled || (A && B)` ; recherche du terme sortie dans un
assistant, `askedWhileMain` renommé `answerAsMainQuery`. *Commentaires* : ce registre renvoyait à la décision
remplacée — réécrit ; deux commentaires du test (une justification, une redite du nom) supprimés ; « chaque
requête suivante » disait trop, `get_posts()` activant `suppress_filters` — précisé, et la clause des attributs
nommée à côté de celle du prix ; références de lignes élargies à `:381-452` ; la file d'attente disait R-149
« le seul visible » — « le seul mesuré ». *Performance* : rien, un `return false` constant. *Sécurité* : rien.
*Contexte* : **l'exemple typait `WP_Query` sans barre oblique** — collé dans un fichier de thème à namespace, il
aurait levé une `TypeError` sur chaque archive produit, donc des 500 — `\WP_Query` ; sans WooCommerce, le test
plantait au lieu de s'ignorer — garde ajoutée.

**Vérifié le 2026-09-22** : l'exemple de `configuration.md`, collé tel quel dans un `ddev wp eval` en mémoire,
rend `true` pour la catégorie visée en requête principale, `false` pour une autre catégorie, pour une marque et
pour la boutique, `false` pour la catégorie visée en requête secondaire. `ddev wp option get woocommerce_attribute_lookup_enabled` : `yes`.
Premier commit : `eb1aab5` ; les corrections des passes suivent dans un second.

### R-146 · 🟠 · **fermé le 2026-09-22** · ouvert le 2026-09-17 — sur une boutique TTC, le client filtre et le curseur borne en HT

Retour de revue sur la PR #2, vérifié par la passe de conformité. `PriceQuery` retire la taxe des bornes
saisies (`PriceTax::excluding()`, `D-e`), `price-query.ts` non : le client ne reçoit aucune donnée de taxe,
et son docblock promet « the same test the server writes ». Charger `?max_price=50` filtre à ≤ 41,67 HT à
20 %, tout geste du client à ≤ 50. Les bornes affichées restent HT des deux côtés, alors que le widget
classique leur ajoute la taxe : une saisie entre le maximum HT et le maximum TTC est prise pour le bord et ne
filtre rien. `D-e` demandait aussi de l'écrire dans `configuration.md`, jamais fait. Déjà relevé sans numéro
(`R-137` « Déjà connus », `R-121`). Dormant sur Pluralia (taxes désactivées).

**Tranché par Louis le 2026-09-22 : indexer le prix affiché** (`D-e` réécrit, `prix.md`). Le complément du
2026-09-17 — publier la règle de taxe et la recopier en JavaScript — est renversé avant d'être codé : dès le
premier geste, le navigateur interroge Meilisearch sans PHP, et il aurait fallu recopier les calculs de taxe
de WooCommerce. Le prix affiché est calculé une fois, à l'indexation, par les méthodes de WooCommerce, à
l'adresse de la boutique ; plus aucune conversion au filtrage.

**Corrigé** : `ProductPriceProjector` indexe le prix que la carte montre, par les méthodes de WooCommerce —
`wc_get_price_to_display()` pour un produit simple ou externe (rien si le prix est vide, `D-j`),
`get_variation_prices(true)` pour un variable, `get_min_price()`/`get_max_price()` pour un groupé (rien si sa
carte est vide, tranché par Louis le 2026-09-22). `ShopTaxLocation` impose l'adresse de la boutique par
`woocommerce_get_tax_location` autour de la carte et du prix (`MeiliScoutBridge`). `PriceTax`, son appel dans
`PriceQuery` et `PriceTaxTest` — qui écrivait des réglages de taxe dans la base de dev — sont supprimés. Rien
ne change côté client ni dans le contrat.

Tests : `ShownPriceProjectionTest` (taxes simulées en mémoire par `pre_option_*` et `woocommerce_find_rates`,
produits créés puis supprimés) — simple saisi HT affiché TTC (60 pour 50 à 20 %), saisi TTC affiché HT
(41,666667), simple en promotion (48, le prix facturé), variable (12–24), groupé (12–24), groupé à la carte
vide (rien), groupé aux enfants gratuits (0–0), adresse de la boutique imposée à un client belge en session,
prix et carte ; `ProductPriceProjectionTest` compare désormais le catalogue au prix facturé qu'affiche la carte
(prix barré et texte pour lecteurs d'écran retirés) ; `ShopTaxLocationTest` (sans WooCommerce). Mutations,
toutes détectées : prix saisi au lieu du prix affiché, prix régulier au lieu du prix facturé (deux tests), garde
du prix vidé retirée, variable aux prix bruts (`get_variation_prices(false)`), variable ou groupé traités
comme un produit simple, garde de la carte vide retirée, adresse non imposée au prix ou à la carte, garde sans
WooCommerce retirée.

**Cinq passes, premier tour** (`module-review`). *Lisibilité* : le test de catalogue ne vérifiait qu'une
inclusion de texte (un prix barré passait) et aucun test ne couvrait variable ou groupé sous taxe — produits
de test dédiés et oracle au prix facturé ; `singlePrice` rendait une fourchette — `singleRange` ; valeurs de
taxe écrites en dur dans le test — `TaxDisplayMode`, `TaxBasedOn` de WooCommerce. Un objet valeur min/max est
**refusé** : quatre méthodes privées d'une même classe, un seul lecteur. *Commentaires* : le docblock de classe
redisait `D-e` et était faux pour un groupé — supprimé ; deux commentaires du test — supprimés ;
`configuration.md` taisait les écarts des groupés et le cas sans client en session — écrits. *Performance* :
rien ; `PriceTax::excluding()` quitte même le chemin de rendu. *Sécurité* : rien. *Contexte* : **un groupé aux
enfants tous au prix vidé était indexé à 0** (`get_min_price()` compte `''` pour 0) — rien si sa carte est
vide ; `get_min_price()` exige WooCommerce 10.1 — écrit ; `ShopTaxLocationTest` dépendait de l'ordre de la suite
— ignoré quand WooCommerce est chargé ; le test taxé plantait sans WooCommerce — produits suivis dans une liste
vidée au démontage ; l'absence d'arrondi exclut un produit affiché 40,83 € de `?max_price=40.83` — écrit dans le
coût de `D-e`. Non vérifié : une extension de multidevise ou de prix par rôle indexerait la valeur de la
session, comme la carte le fait déjà ; aucune sur Pluralia.

**Vérifié le 2026-09-22 sur Pluralia** (taxes désactivées) : en mémoire, par `MeiliScoutBridge::addPrice()`,
les 76 produits publiés ont le même prix indexé qu'avant (`===`), sans aucune écriture en base ; index
reconstruit (`ddev wp meiliscout index --clear`) avec le nouveau code : les 76 prix identiques produit par
produit ; `/boutique` 16 cartes, plage 0–199 €, `?max_price=10` 6 cartes, `?marque=aeris` 10 cartes, 9–47 €.
Un document orphelin, 867, sans prix et sans article derrière, a disparu à la reconstruction : la suite
`Modules`, relancée, n'en laisse aucun ; origine non établie.

**Passes, second tour (2026-09-22)** — six constats du premier tour résolus, deux en partie. Traités :
l'oracle du catalogue compare désormais un montant **isolé** (`STANDALONE_AMOUNT`) — « 2,00 € » était
trouvé dans « 12,00 € » ; tué par mutation : un minimum faux (`fmod($price, 10)`) passait l'ancien oracle
(304 assertions vertes) et fait échouer le nouveau ; le motif retiré de la carte s'appelle ce qu'il
retire (`STRUCK_AND_SCREEN_READER_TEXT`). `ShownPriceProjectionTest` : plus de paramètre-drapeau
`'yes'`/`'no'` (deux méthodes, `shownWithTax()` et `shownWithoutTax()`), taux nommés, pays de la
boutique figé par `woocommerce_default_country`, et projection par `ShopTaxLocation` comme en
production — les tests ne dépendent plus de l'adresse client par défaut ni du pays de la boutique.
`ShopTaxLocation::shopAddress()` extrait ; la propriété du pont s'appelle `$shopTaxLocation`.
`configuration.md` : 76 produits publiés (et non 79), la ligne de commande a un client en session,
un variable est arrondi même sans taxes.

**Refusé, par écrit** : dériver les attentes (60, 48, 12–24, 41,666667) des taux nommés. Ce serait
réécrire dans le test le calcul de taxe de WooCommerce, c'est-à-dire l'oracle à partir de ce qu'il
vérifie ; les valeurs restent des exemples concrets.

**Tranché par Louis le 2026-09-22 : « reprendre le modèle WooCommerce ».** La relecture avait mesuré en
mémoire deux cas faux : un thème qui affiche un libellé sur un groupé sans prix le faisait indexer 0–0
(`woocommerce_grouped_empty_price_html`, `woocommerce_get_price_html`), et un seul enfant vidé donnait 0–21
pour une carte 16–21 (`get_min_price()` compte l'enfant vidé à 0). **Corrigé** : `childrenRange()` suit
`get_price_html()` — enfants visibles (`get_visible_children()`), `singleRange()` sur chacun, donc
`wc_get_price_to_display()` et un enfant sans prix sauté, puis min et max ; plus aucune lecture de HTML.
Minimum requis : WooCommerce 9.8 au lieu de 10.1. Deux tests ajoutés (un enfant sans prix sauté, un
libellé de thème sans effet), **tués par mutation** : l'ancien code en fait échouer deux ; un calcul qui
ne saute pas les prix vides, trois. Relevé aussi, non traité : quatre parcours des enfants par groupé
indexé au lieu d'une lecture de `_price` — négligeable sur deux groupés de trois enfants, et désormais
deux (le prix, la carte).

**Passes, troisième tour (2026-09-22)** — confirmé : la fourchette d'un groupé est celle de sa carte, en
source (`get_price_html()` contre `wc_get_price_to_display()`) et en mémoire sur #444 et #445, sept cas.
Traités : `childrenRange()` ne reposait que sur deux implicites (`array_filter` sans rappel gardant
`[0.0, 0.0]`, `array_column` sur une paire dégénérée) — il lit désormais `shownPrice(): ?float` et filtre
les `null` explicitement ; tué par mutation (un `array_filter` sans rappel perd les enfants gratuits) ; le
commentaire sur `get_min_price()` est supprimé (sa raison est dans `D-e`). Les tests n'utilisent plus
`TaxDisplayMode` (WooCommerce 11.0) ni `TaxBasedOn` (10.8), au-dessus du minimum de 9.8 ; chaque nom
d'option est une constante ; `underTax()` se sépare en `withOptions()` et `withRates()` ; les assistants
disent ce qu'ils figent (`enteredWithoutTaxShownWithTax()`). L'oracle du catalogue lit la carte par
`Dom\HTMLDocument` et compare chaque borne **à égalité** aux montants facturés : l'expression régulière
supposait une locale — « 234,00 € » était trouvé dans « 1 234,00 € », le séparateur de milliers de Pluralia
étant une espace. Re-tué par mutation (`fmod($price, 10)` : « 9,00 € » absent). Relevé hors périmètre :
`R-154`. `composer check` vert (262 tests autonomes), suite `Modules` : 384 tests, 1302 assertions.

**Passes, quatrième tour (2026-09-22)** — l'oracle DOM vérifié sur les 76 produits (simples, promotions,
externes, variables, groupés). Traités : un suffixe de prix qui porte un montant (`{price_excluding_tax}`)
ajoutait un montant à la carte, et l'oracle acceptait alors le prix HT d'une carte TTC — mesuré en mémoire,
`["58,80 €","49,00 €"]` avant, `["58,80 €"]` après avoir retiré `.woocommerce-price-suffix` ; le test dit ce
qu'il vérifie (`it_indexes_the_prices_the_card_bills`) ; la carte du test d'adresse se compare à égalité ;
un `array_values()` sans effet retiré ; `prix.md` ne dit plus qu'un groupé compte l'enfant vidé pour 0.

**Fermé le 2026-09-22.** Quatre tours de passes, chaque constat traité ou refusé par écrit ci-dessus.
`D-j` est désormais tenu par `ProductPriceProjector::shownPrice()` pour les simples, externes et enfants de
groupé — le `pricesOf()` décrit par `R-148` n'existe plus. Vérifié : `composer check` vert (262 tests
autonomes), suite `Modules` 385 tests / 1303 assertions ; `/boutique`, `?max_price=10` (6 cartes, comme
avant) et `/marque/aeris` répondent 200. Aucun JavaScript touché. Reste ouvert à côté : `R-154`.

### R-145 · 🟡 · ouvert · 2026-09-17 — suites des passes rejouées sur les points fermés du lot

Relevés par les cinq passes rejouées le 2026-09-17 sur `R-120`, `R-135`, `R-136`, `R-137` et `R-138`, et
laissés ouverts parce qu'ils restructurent au-delà du point relu (`D-03`). Chaque ligne se traite sous ce
numéro ou s'en détache.

1. **Locale** : trois chemins — `CountLabel` reçoit une `Closure`, `NameOrder` une autre, `SiteCollator`
   appelle `SiteLocale::current()` en statique. Piste : un `SiteLocale` `scoped`, injecté (`R-138`).
2. **Pluriel sans `ext-intl`** : le repli n'est pas testable là où intl est chargé, et diverge du client
   (`pt_BR` et `ar` à 0 et 1, `pt_AO`, `kk`). Piste : l'extraire et le passer sur `plural-cases.json`
   (`R-138`, `R-137` #4).
3. **`NameOrder`** relit la locale à chaque comparaison du tri, jusqu'à ~350 lectures par facette. Piste :
   lire le collator une fois par tri ; touche le contrat `ValueOrder` (`R-138`).
4. **Repli des valeurs** : règle écrite deux fois sans table de cas partagée (`tests/fold-cases.json`) ;
   `FacetValue::$folded` veut dire « masquée », d'où le re-filtrage de `Facet::hasFoldedValues()`, et
   `folds` côté client veut dire autre chose ; « readable » a trois sens ; paramètres booléens `readable`,
   `expanded`, `foldable` (`facets-view.ts:107,115`) ; `FacetValues::of()` nomme la même distribution
   `$unfiltered` puis `$offered` (`R-137` #2, #3, #7).
5. **Vue surchargée** qui masque son bloc sur `$values === []` : la légende reste au-dessus de rien jusqu'à
   la première recherche. Piste : calculer les plis à la liaison (`R-137` #4).
6. **Clauses reconstruites** : `ListingSearch::isNarrowed()` et `QueryPlan::filterQueries()` refont toutes
   les clauses pour savoir si elles sont vides, jusqu'à k+3 fois par rendu (`PriceTax::excluding()` compris,
   jusqu'à son retrait par `R-146`).
   Piste : une méthode de `FilterQuery` qui teste l'état ; lié à `R-01` (`R-136`, `R-137` #4).
7. **Structure du client** : `listing-binding.ts` importe toutes les fonctionnalités, alors que `R-136`
   dit le contraire ; cycles de dossiers par `ListingState` ; trois fonctions libres (`filterQueriesOf`,
   `facetField`, `drawn`) ; `SortQuery` et `PriceQuery` construites deux fois ; `#acted` agit au lieu de
   répondre ; ESLint ne vérifie ni les paramètres booléens, ni les fonctions libres, ni les frontières de
   dossiers (`R-136`).
8. **Prix** : `data-active` nommé deux fois (`ACTIVE_HANDLE`, `ACTIVE_OPTION`) ; `Math.min`/`Math.max`
   calculés deux fois (`price-control.ts:187,190`) ; chaque `pointermove` force une mise en page et
   réécrit tout, même quand la valeur arrondie ne change pas ; aucun test ne relâche après un croisement
   ni n'envoie `pointercancel` (`R-120`).
9. **Carte** : le chemin d'une image visible n'a aucun test Node ; un `image_alt` ou un `title` numérique est
   écrit par le client et vidé par le serveur ; la règle de repli de l'`alt` vit dans le client, qu'une vue
   surchargée ne peut pas changer (`R-135`).
10. **Adresse** : `PageAddress::PAGED_QUERY_VAR` est un nom de query var hors énumération ;
    `ListingUrl.toSearch()` n'est public que pour les tests ; les permaliens simples n'ont aucun test,
    `RequestsAnAddress` fige `/%postname%` (`R-137` #1).
11. **Commentaires** : voir `Q-31`.

### R-144 · 🟠 · **fermé le 2026-09-24** · ouvert le 2026-09-17 — remplacer `FacetCounter` ne change que le premier rendu

`configuration.md` présentait `FacetCounter` comme remplaçable (`bind`), mais le client écrit en dur la
règle disjonctive (`facets/facet-query.ts:30-32`, `listing/listing-query.ts:28-32`) : un projet qui
remplace le compteur a ses comptes au premier rendu, et le client les écrase au premier geste, sans
signal. Trouvé par les passes rejouées de `R-136`.

**Le défaut était pire que l'entrée ne le disait**, et c'est la passe de conformité qui l'a lu :
`QueryPlan::results()` décidait *aussi* quelles facettes la requête principale compte, par
`FacetQuery::isMeasuredApart()` — **sans demander au compteur**. Un compteur qui laissait une facette
multi-sélectionnée à la requête principale ne produisait donc aucune clé `count:` *et* voyait son champ
écarté de la principale : **comptes vides dès le premier rendu**. La couture ne pilotait pas même l'écran
d'ouverture.

**Trois voies étaient possibles ; Louis a tranché pour « documenter et réparer le premier rendu »** :
publier le plan de comptage dans la description a été écarté — cela renverserait le retrait validé
d'`isCountedApart()` (`decisions.md`), contredirait le précédent `R-85` (où la voie « transmettre au
client » a déjà été refusée au profit d'une règle serveur que les deux côtés partagent par construction)
et resterait bloqué sur la moitié ouverte de `R-32`.

**Corrigé** : `ListingSearch::run()` compose d'abord les recherches mesurées à part — comptage et bornes
de prix — puis passe leurs clés à `QueryPlan::results($listing, $state, $apart)` ; `fieldsOnMain()` ne
filtre plus que sur ces clés et n'a plus de règle à lui. Le compteur décide donc seul du premier rendu.
Les recherches `unfiltered` restent hors de cet ensemble : elles ne retirent aucun champ à la principale.

**Aligné sur la convention des coutures** (`CLAUDE.md` § 2) : `SearchServiceProvider` lie en `bindIf` au
lieu de `bind`. `FacetCounter` était le seul contrat présenté comme remplaçable qu'un projet ne
l'emportait qu'en s'enregistrant après le module.

**Documenté** : `configuration.md` dit désormais que la couture vaut pour le **rendu serveur seulement**,
ce que le client rejoue en dur, et que cette règle est une décision du module — « le disjonctif n'est
produit que pour le multi-sélection » — et non un détail d'implémentation. `decisions.md` et le `README`
portaient déjà la limite ; c'était la seule page à promettre « oui, `bind` » sans réserve.

**Tests** : `it_counts_on_the_main_search_what_the_counter_leaves_to_it` (un compteur qui ne mesure rien à
part fait compter toutes les facettes par la principale) et `it_counts_apart_the_facet_the_counter
_measures_apart` rejoignent `ListingSearchTest`, avec le cas des bornes de prix — ils testaient la
composition, pas le plan. `QueryPlanTest` garde un cas pur du nouveau paramètre. `CounterSeamBindingTest`
est neuf, calqué sur `ProductSeamBindingTest` : défaut lié quand le projet ne dit rien, pas de côté quand
le projet a lié avant. **Trois mutations tuées** : rendre tous les champs à la principale (quatre tests), rétablir l'ancienne
règle dans `ListingSearch` (trois tests, dont celui du compteur, qui incarne le défaut corrigé), et
remettre `bind` à la place de `bindIf`. Cette dernière n'était **tenue par rien** : `CounterSeamBindingTest`
rejoue l'appel sur un conteneur nu au lieu d'observer le provider. `Feature\CounterBindingTest` le
regarde désormais, et la mutation tombe. Le même angle mort vaut pour `ProductSeamBindingTest`, non
traité ici (`D-03`).

**Vérifié** : `composer check` vert, suite `Modules` 411 tests. Et les **valeurs** confrontées à la base,
pas seulement la forme des requêtes — trois scénarios comptés par `WP_Query` avec la visibilité
WooCommerce appliquée, comparés au rendu serveur puis au recalcul du client :

| Scénario | Page | Client après un geste | Base |
| --- | --- | --- | --- |
| marques, sans filtre | 10 · 5 · 12 · 4 · 10 · 7 · 2 · 10 · 8 | — | identiques |
| catégories sous `?marque=avril` | 0 · 1 · 1 · 1 · 2 | — | identiques |
| marques sous `?categorie=visage` | 5 · 2 · 4 · 0 · 6 · 0 · 1 · 4 · 1 | identiques | identiques |

La facette filtrée garde ses comptes complets, les autres se réduisent : la mesure à part fait son office,
et serveur et client coïncident sur la règle par défaut.

**Rien ne change à l'écran, et c'est mesuré** : les quatre fichiers remis dans leur état d'avant rendent
exactement les mêmes nombres sur les trois pages. Le défaut est **latent** — les deux décideurs
appliquaient la même règle tant que personne ne remplace `FacetCounter`. Ce point paie une couture qui
tient sa promesse, pas une valeur fausse. Un premier comptage de contrôle donnait 25 au lieu
de 24 pour « visage » : c'est la requête de contrôle qui avait tort, elle ignorait `exclude-from-catalog`
que le filtre de base écarte (produit 370). Vérifier les valeurs, pas seulement la forme des requêtes, est
ce qui a permis de le dire.

**Les cinq passes**, et ce qu'elles ont donné :

- *lisibilité* — constat de fond, corrigé : la seconde règle de comptage avait été **débranchée, pas
  supprimée**. `isMeasuredApart()` quitte le contrat `FilterQuery` et `SortQuery`, où elle ne répondait
  que `false` ; elle reste sur `FacetQuery` et `PriceQuery`, seuls appelants réels. `run()` est ramenée à
  un niveau d'abstraction, le plan part dans `searches()`, et le nom `$apart` ne désigne plus deux choses
  (`$measuredApart` pour des requêtes, `$apartKeys` pour des clés) ;
- *tests* — un cas avait été perdu au déplacement : `it_never_counts_a_facet_twice` revient dans
  `ListingSearchTest`, en invariant sur toutes les recherches plutôt qu'en égalité sur deux facettes.
  `CounterSeamBindingTest` reçoit le cas du projet qui lie **après** le module — le seul chemin qu'un
  projet emprunte vraiment, et celui que `configuration.md` promet. Le double « compteur qui ne mesure
  rien » était écrit deux fois en classe anonyme : il devient `Doubles\FakeFacetCounter` ;
- *commentaires* — trois ajoutés, trois retirés : deux redisaient le nom de leur test, le troisième
  justifiait un choix de conception déjà écrit quatre fois ailleurs et une cinquième dans
  `configuration.md` ;
- *performance* — un seul aller-retour moteur, nombre de recherches inchangé pour le compteur par
  défaut ;
- *sécurité* — rien : aucune valeur d'URL n'entre dans les clés, qui viennent de taxonomies déclarées et
  d'une constante ; les préfixes `count:`, `bounds` et `unfiltered` sont disjoints, donc un compteur ne
  peut se substituer ni à la recherche principale ni à la non filtrée ;
- *contexte et i18n* — rien : aucune chaîne visible ajoutée, le test de couture tourne sur un conteneur nu,
  hors application.

**Deux inexactitudes écrites, corrigées** : `configuration.md` disait que le compteur « décide
entièrement de la première page » — il ne décide ni les bornes de prix ni la recherche non filtrée ; et
`R-153` listait encore `FacetCounter` parmi les promesses non tenues, ligne devenue périmée par ce point.

**Reste ouvert, et c'est écrit** : le client rejoue la règle par défaut en dur. Le rendre pilotable
demande la table de cas commune `QueryPlan`/`ListingQuery` de `R-32`, et une décision de Louis sur le
retour d'une méthode sans état au contrat `FacetCounter`.

Deux constats ouverts en chemin, non traités ici (`D-03`) : une taxonomie inconnue rendue par un compteur
fait payer une recherche que personne ne lit, sans garde ni diagnostic, et le champ reste alors compté sur
la requête principale ; et le commentaire de miroir de `listing-query.ts` promet toujours que « le même
état produit les mêmes recherches des deux côtés », ce que la couture dément depuis ce point.

### R-143 · 🟡 · **fermé le 2026-09-24** · ouvert le 2026-09-17 — le client efface le balisage d'un bouton « Voir plus » surchargé

`facets-view.ts:124` réécrivait `button.textContent` : une vue surchargée perd l'icône ou le texte réservé
aux lecteurs d'écran de son bouton — le défaut que `R-137` #5 a retiré du message vide. Trouvé par les
passes rejouées de `R-137` #3.

**La phrase d'ouverture était fautive** : elle réclamait `Contract::VERSION` des deux côtés alors que
`R-116`, fermé deux jours plus tôt, avait renversé exactement cette règle — on incrémente quand un crochet
est **renommé ou retiré**, jamais quand on en ajoute (`Contract.php:15`, `architecture.md:674`). La version
reste à `1`. Louis l'a confirmé : le module est en développement, aucune vue n'est surchargée, mais le cas
d'une surcharge future doit être prévu.

**Corrigé** en reprenant le mécanisme de `R-137` #5, sans en inventer un second : `facet.blade.php` rend
les **deux** libellés dans le bouton, chacun sous son crochet (`more-label`, `less-label`), le second
`hidden` ; `FacetsView` ne bascule qu'une visibilité et ne touche plus au contenu du bouton. `foldLabels`
quitte `ListingDescription` et `description.ts` — aucune chaîne du bouton ne transite plus par la
description. Les deux crochets restent **hors de `RULES`** (`contract.ts:16-24`) : les inscrire ferait
d'une vue surchargée une infraction, et `listing-page.ts` refuserait de démarrer le listing entier — la
« panne plus large » que `R-116` a refusé d'acheter. La garde sur `aria-expanded` est conservée telle
quelle (`Q-31`).

**Comportement d'une vue qui ne rend aucun des deux libellés** : son balisage est intact, `aria-expanded`
bascule toujours, seul le texte reste figé. Dégradation inerte, la même que pour `no-results`/
`past-the-end`.

**Tests** : trois cas — le bouton rend les deux libellés dont un masqué (`FacetComponentTest`), le balisage
qu'une vue a mis dans le bouton survit aux deux bascules, et un bouton sans libellé crocheté n'est pas
réécrit (`facets-view.test.ts`). **Trois mutations tuées**, une par cas : écrire un libellé quand la vue
n'en crochète aucun, figer la bascule, retirer le `hidden` du second libellé.

**Vérifié** : `composer check` vert, suite `Modules` 408 tests, client 291. En navigateur sur `/boutique`,
après `view:clear` : « Voir plus » → « Voir moins », 10 valeurs → 24, et un `<svg class="chevron">` injecté
dans le bouton survit au dépliage comme au repliage — c'est le défaut que ce point visait.

**Les cinq passes**, et ce qu'elles ont donné :

- *lisibilité* — le test `Feature` déréférençait trois nœuds sans garde, là où le fichier assère
  `assertNotNull` avec un message : corrigé, le crochet manquant est nommé au lieu d'un « on null » ;
- *commentaires* — un seul avait été ajouté, sur le test, et il reformulait son nom : supprimé. Zéro
  commentaire ajouté au code de production ; celui sur `aria-expanded` reste, c'est une anomalie amont ;
- *performance* — le Blade fait 2 `__()` par facette au lieu de 2 par page, lectures d'un tableau déjà
  chargé ; côté client, 2 `querySelector` au plus par facette et par réponse, et zéro quand l'état ne
  bouge pas ;
- *sécurité* — rien, et la surface diminue : le client n'écrit plus ni `textContent` ni `innerHTML` sur ce
  bouton, et plus aucune chaîne du bouton ne transite par la description ;
- *contexte et i18n* — la seule assertion qui reliait un libellé à la langue de la page partait avec
  `foldLabels` : remplacée par `it_writes_the_fold_labels_in_the_language_wordpress_translates_in`
  (mutation tuée : un libellé écrit en dur dans le Blade fait tomber le test). Et `PublishedAssetsTest`
  était rouge — les assets n'avaient pas été republiés après la dernière construction.

**Deux écarts assumés, et pourquoi :**

- `#showFoldLabel(button, expanded)` ajoute un paramètre booléen au fichier que `R-145` (4) vise. Il est
  gardé : `expanded` n'est pas un interrupteur de comportement — la méthode fait la même chose dans les
  deux cas — mais l'état ARIA que le bouton porte déjà. C'est une donnée, pas un drapeau.
- La bascule est placée **dans** la garde `aria-expanded`, là où `results-view.ts:39-42` pose sa paire à
  chaque rendu. Une vue dont l'état initial des libellés contredirait son `aria-expanded` ne serait donc
  jamais rattrapée ; la sortir de la garde coûterait deux lectures DOM par facette et par réponse pour
  rattraper une vue que le serveur rend toujours cohérente.

`architecture.md` porte désormais la règle **« les deux libellés ou aucun »**, qui n'était écrite nulle
part et qu'un thème lisait autrement.

À signaler pour la suite : `#showFoldButton()` porte toujours deux paramètres booléens, terrain de
`R-145` (4) ; la signature n'a pas été touchée ici (`D-03`). Et `facets-view.ts:177` écrit toujours
`label.textContent` sur le crochet `count` — même geste que celui corrigé ici, sur du texte réellement
dynamique ; non traité, à ouvrir si le cas d'un compteur surchargé se pose.

### R-142 · 🟠 · **fermé le 2026-09-24** · ouvert le 2026-09-17 — le glissé du prix casse sur une vue à une seule poignée basse, et hors du bouton principal

Trouvé par les passes rejouées de `R-120`, par simulation happy-dom ; pas encore reproduit dans Chrome.

- Une vue surchargée qui ne rend que la poignée basse respecte le contrat (`contract.ts:23` n'exige qu'une
  `price-handle`), mais `nowOf(null)` vaut 0 : tout déplacement compte comme un croisement, et
  `handOver(null)` lâche la prise (`price-control.ts:180-193`). Tirée de 55 à 70, la marque disparaît au
  premier mouvement, rien n'est validé au relâchement, les champs affichent 0 et 70. La poignée haute seule
  fonctionne.
- `SliderDrag` ne filtre ni `button` ni `buttons`, et n'écoute pas `lostpointercapture`
  (`slider-drag.ts:31-34`) : après un `pointerdown` du bouton droit, un survol sans bouton déplace la poignée
  et `data-active` reste posé ; `showBounds` garde les bornes en attente tant qu'aucun `pointerup` n'arrive.
  La perte réelle du `pointerup` (menu contextuel sous macOS) reste à confirmer dans Chrome.

Pistes : ne jamais croiser vers une poignée absente ; `button === 0` à la prise, `buttons === 0` vaut
relâchement, écouter `lostpointercapture`.

**Reproduit dans Chrome le 2026-09-24**, ce que l'entrée posait comme « à confirmer » — et la gravité
était sous-estimée. Sur `/boutique`, un glissé au **bouton droit** déplace la poignée basse : le champ
passe de 0 à 85 sans qu'aucune URL ne change, puis **le geste légitime suivant l'applique** — cocher une
facette et presser « Appliquer » a envoyé `?categorie=cheveux&min_price=85`, soit zéro résultat. Un clic
droit arme donc un filtre que le visiteur n'a jamais posé.

**Valeurs de la plateforme, mesurées dans Chrome** plutôt que citées : `pointerdown` au bouton droit
donne `button = 2`, `buttons = 2` ; un déplacement sans bouton, `button = -1`, `buttons = 0` ; et Chrome
émet bien `lostpointercapture`, juste après `pointerup`. happy-dom n'implémente pas la capture, donc
cette moitié ne pouvait pas se fermer dans la suite seule.

**Corrigé** : `SliderDrag` ne prend la main que sur le bouton principal, traite un déplacement sans
bouton comme une fin de geste, et écoute `lostpointercapture` en plus de `pointerup` et `pointercancel`.
`PriceControl::#moveTo()` ne croise plus vers une poignée absente : la vue qui n'en dessine qu'une voit
l'autre extrémité valoir **le bord de la piste**, jamais zéro — et une borne au bord n'étant pas un
filtre (`R-122`), une poignée basse seule filtre `min_price` seul.

**Tests** : sept cas ajoutés à `price-control.test.ts`, dont un sur une vue à une seule poignée, rendue
possible par une fixture qui choisit les poignées dessinées et qui n'écrit plus une borne qu'on ne lui a
pas donnée. **Sept mutations tuées**, une par garde. Et un défaut de la suite au passage : ses cinq tests
de glissé simulaient un déplacement **sans bouton enfoncé** — c'est-à-dire le geste que le correctif
refuse ; ils portent désormais `buttons: 1`.

**Les cinq passes, deuxième tour**, après réduction de `SliderDrag` (plus de rappel de fin stocké : chaque
écoute porte sa condition, ce qui lève le couplage temporel entre `onMove` et `onRelease`) :

- la séquence réelle de Chrome, `pointerup` **immédiatement suivi de** `lostpointercapture` sur un même
  geste, n'était jouée nulle part : seule la garde `#released()` empêche deux recherches par glissé, et
  la couverture montrait qu'elle ne s'exécutait jamais. Un cas la joue, la mutation est tuée ;
- une capture que le navigateur ne rend pas — geste fini sur un déplacement sans bouton — restait prise
  sur la piste : toute pression suivante était détournée vers elle, la poignée n'en revoyait aucune.
  `SliderDrag::release()` la rend, un cas le tient ;
- sans l'extrémité opposée — bornes non encore mesurées — la vue à une poignée écrivait `aria-valuemax=""`
  et 0 : `#movedAlone()` ne dessine plus rien tant qu'elle manque ;
- `#shows()` disait deux choses, et son nom se confondait avec `#shown()` : replié dans ses deux appelants ;
- un nom de test contredisait son assertion, et trois commentaires racontaient l'histoire du code au lieu
  d'un fait — supprimés.

**Vérifié** : `composer check` vert, suite `Modules` 406 tests, client 289. En navigateur, après
correctif : le clic droit ne déplace plus rien et ne marque plus aucune poignée ; le glissé normal marque
la poignée et écrit 80 ; `pointerup` suivi de `lostpointercapture` ne rejoue pas le relâchement ; une fin
par `lostpointercapture` seul garde la valeur glissée et rend la capture.

### R-141 · 🟡 · ouvert · 2026-09-17 — cinq tests `Feature` dépendent de l'ordre de la suite

`ddev exec vendor/bin/phpunit --testsuite Modules --order-by=reverse` : 2 erreurs et 3 échecs, alors que
l'ordre par défaut est vert (369 tests).

- `FacetComponentTest::it_shows_a_held_value_that_has_no_result_left` — `hasAttribute()` sur `null` ;
- `FacetComponentTest::it_describes_a_value_with_its_count_rather_than_naming_it` — `getAttribute()` sur `null` ;
- `FacetComponentTest::it_keeps_every_value_on_a_narrowed_page_and_hides_those_without_results` — aucune
  valeur trouvée ;
- `PriceComponentTest::it_draws_the_filled_part_of_the_track_before_any_script_runs` — `--from: 0; --to: 0` ;
- `PriceComponentTest::it_leaves_a_field_empty_when_nothing_was_asked`.

`PriceComponentTest` échoue aussi seul en ordre inverse : l'état fuit entre ses propres tests, pas seulement
depuis une autre classe. Cause non cherchée. Trouvé par les passes de `R-139`, sans lien avec ce point :
ni `FacetComponentTest` ni `PriceComponentTest` ne sont touchés par son commit.

### R-140 · 🟡 · ouvert · 2026-09-17 — Pollora casse la redirection canonique de `//chemin`

`//boutique` répond 200 au lieu d'un 301 vers `/boutique`. WordPress redirigerait (`canonical.php:716-717`
réduit les `//`), mais le filtre `user_trailingslashit` de Pollora
(`vendor/pollora/framework/src/Permalink/Infrastructure/Providers/PermalinkServiceProvider.php:54`) passe
le chemin à `Uri` (`src/Support/Uri.php:28`), dont `parse_url('//boutique')` lit `boutique` comme un hôte : la
redirection devient `https://pluralia.ddev.siteboutique`, que la garde anti-chaîne annule. Côté module,
`pagePath` écrit déjà `/boutique` ; correctif à proposer en amont (§2). Ne couvre pas `//?s=…`, que
`redirect_canonical` ignore. Trouvé par la passe de conformité des reliquats de `R-137`.

### R-139 · 🟡 · **fermé le 2026-09-17** · ouvert le 2026-09-17 — `check-parameters` ne voit pas les query vars ajoutées par filtre

`meilifacets:check-parameters` compare les noms du module à `$wp->public_query_vars`. En console, Pollora
n'appelle jamais `wp()` (`vendor/pollora/framework/src/WordPress/Bootstrap.php:62-66`), donc le filtre
`query_vars` n'est pas appliqué : 74 noms au lieu de 95 sur une requête HTTP (mesuré). WooCommerce y ajoute
`min_price`, `max_price`, `rating_filter`, `filter_*`, `query_type_*` ; `brands`, `categories`, `tags` et
`checkout-link` passeraient la commande tout en entrant en collision en HTTP. Faux par conséquence :
la docblock « `min_price` never reaches `public_query_vars` » de `ReservedParameters` et de son test, et les
« 74 query vars » de `configuration.md` et `pieges.md`. Trouvé par la passe de conformité de `R-137` #6.

**Corrigé** : `ReservedParameters::wordPress()` applique le filtre `query_vars` lui-même tant qu'aucune
requête n'a été analysée (`did_action('parse_request')`) ; sur une requête, `WP::parse_request()` l'a déjà fait
et la liste est reprise telle quelle. Jamais avant `init` : `Params` de WooCommerce
(`src/Internal/ProductFilters/Params.php`) garde en statique, pour tout le processus, les paramètres de filtre
de ses taxonomies de produits (`categories`, `brands`, `filter_essentiel`…), que `get_taxonomies()` ne
connaît qu'après `init`. La liste lue avant `init` n'est pas gardée ; celle d'après est dédoublonnée (117
entrées filtrées, 95 noms) et gardée pour l'instance. Le motif des bornes de prix est testé avant les query
vars, pour rester « lu dans `$_GET` par WooCommerce » maintenant qu'elles y figurent. Sur une requête,
`pageQuery` est inchangé ; sur Pluralia la commande passe toujours (`min_price`, `max_price` acceptées par
`D-h`). Tests : `Feature\ReservedParametersTest` (liste filtrée, chaque nom une fois, motif des bornes une
fois `min_price` constaté dans la liste, liste d'une requête analysée non refiltrée, liste relue une fois
`init` passé), ignoré sans WooCommerce, et `CheckParametersCommandTest` (verte sur la configuration du projet,
rouge sur `checkout-link`, que seul le filtre déclare) ; six mutations tuées, rejouées après la seconde revue
(filtre, dédoublonnage, garde `parse_request`, garde `init`, liste d'avant `init` gardée, ordre des motifs).
Le test de la commande l'enregistre lui-même : chaque test finit par `Artisan::forgetBootstrappers()`, et
l'application partagée n'enregistre les commandes des modules qu'une fois.

**Passes, première revue** (`module-review`, sur le diff avant commit). *Lisibilité* : un nom de test disait
« on a request » pour un test qui tourne en console, et le cas d'une requête n'avait aucun test — tests
déplacés dans `Feature\ReservedParametersTest`, cas de la requête ajouté ; une condition mêlait trois
questions — quatre méthodes nommées. *Commentaires* : la cause donnée à l'enregistrement de la commande dans
son test était fausse — réécrite, et vérifiée (commande trouvée quand son test passe en premier, perdue après
un autre) ; « filtres d'attributs » était faux — ce sont les taxonomies de `Params`. *Performance* :
`registerCommand()` tournait pour quatre tests dont deux seulement appellent Artisan — la classe ne garde que
ces deux-là. *Sécurité* : rien. *Contexte* : la liste lue avant `init` restait gardée pour de bon — elle ne
l'est plus, test ajouté. Docs : `pieges.md` conseillait de vérifier un nom contre `$wp->public_query_vars`,
c'est-à-dire de reproduire ce point à la main — renvoie désormais à la commande ; deux inexactitudes de
cette entrée corrigées.

**Passes, seconde revue** (`module-review`, sur le commit). *Lisibilité* : noms de crochets écrits en dur —
constantes ; le test du motif des bornes supposait `min_price` déclaré — il le vérifie ; le dédoublonnage
était testé sous un nom qui ne le disait pas — test à part ; `registerCommand()` était appelé sur le contrat
du noyau, qui ne la déclare pas — noyau de Foundation vérifié d'abord ; le test `Unit` nommé d'après les
« attribute filters » couvre tout le préfixe `filter_` — renommé ; déplacer le motif `$_GET` en tête donnait
à `orderby` un message sur `wc_is_filtered()` qui ne le lit pas — message valable pour les quatre noms.
*Commentaires* : `wc_is_filtered()` n'existe pas, c'est `is_filtered()`, et `class-wc-query.php:316-318`
n'est pas une lecture de `$_GET` — docblock ramenée à une ligne exacte, même nom corrigé dans
`configuration.md` ; la docblock de `ListingDescriptionTest` sur `min_price`, rendue fausse par ce point,
est supprimée (son `add_query_var` reste, pour que le test ne dépende pas de WooCommerce) ; `architecture.md`
précisé. *Performance* : rien. *Sécurité* : rien. *Contexte* : les tests qui lisent des noms de WooCommerce
échouaient sans lui — ignorés. Tests dépendants de l'ordre trouvés au passage, sans lien : `R-141`.

**Refusé** : `CheckParametersCommand` lit `$parameters->all()` deux fois — en console seulement, hors de ce
point.

**Vérifié le 2026-09-17**, par `curl` sur les pages servies : `/boutique?s=creme&post_type=product&utm_source=x`
→ `pageQuery` `s=creme&post_type=product` ; `/boutique?min_price=10&filter_stock_status=instock` →
`filter_stock_status=instock` ; `/boutique?categories=visage` → `categories=visage`. Pas de navigateur : aucun
JavaScript ne change. `ddev exec php artisan meilifacets:check-parameters` : `min_price` et `max_price`
signalés comme acceptés, puis « No blocking conflict. 11 parameters checked. »

### R-138 · 🟡 · **fermé le 2026-09-17** · ouvert le 2026-09-17 — les libellés publiés au client ignorent les traductions de WordPress

`ListingDescription` publiait `countPattern`, `filterPattern` et `foldLabels` par `trans()`, et `Facet` et
`ActiveFilters` traduisaient leurs motifs de compte de la même façon, en `app()->getLocale()`. Blade écrit
les mêmes chaînes avec `__()`, qui lit le catalogue Laravel **dans la locale de WordPress** (`get_locale()`),
puis le domaine `default`. Trouvé par la passe « contexte » de `R-137` #5.

**Ce que la conformité a montré** : la divergence tient d'abord à la locale, pas au domaine `default`. Sur
Pluralia les six chaînes sortent identiques (`APP_LOCALE=fr`, WordPress en `fr_FR`) ; sur un hôte resté en
`APP_LOCALE=en`, Blade écrirait « Voir plus » et le client « Show more », « 3 results ».

**Corrigé** : les motifs et libellés passent par `__()` (`ListingDescription`, `Facet::countLabel()`, qui ne
traduit son motif qu'une fois par facette, `ActiveFilters`). `View\CountLabel` et le collator de `NameOrder`
lisent la locale au moment où ils servent, par `Support\SiteLocale` ; seuls les objets coûteux sont gardés,
un formateur ICU et un collator par locale (`SiteCollator`).

**Repris après les cinq passes** : une première version lisait la locale une fois, à la construction. Or le
module résout `CountLabel` et `NameOrder` dès le démarrage de l'application, par la découverte de
`ListingScript` et `ProductListing` : la langue était figée avant que Polylang la fixe (mesuré). Le
collator de `NameOrder` l'était déjà avant ce point. La garde `function_exists('get_locale')`, retirée
comme inutile, a été remise : sur un site sans base de données, Pollora ne charge pas `l10n.php` et la
découverte échouait (`DB_HOST=null php artisan --version`, trois erreurs ; aucune depuis).

Tests : `SiteLocaleTest` résout d'abord `CountLabel` et `NameOrder` par le conteneur, change la langue
ensuite (`sv_SE` et `de_DE` rangent « äpple » et « zebra » à l'inverse), et attend la nouvelle ;
`ListingDescriptionTest` et `ActiveFiltersComponentTest` règlent Laravel sur `fr` et WordPress sur
`en_US`, et attendent l'anglais. Six mutations, toutes tuées. Une troisième passe a relevé que le repli
sans `ext-intl` recevait la locale brute (`de_DE_formal`), inconnue de `MessageSelector` : il reçoit
désormais `langue_RÉGION`. Ce repli n'est pas testable là où `ext-intl` est chargé. Aucun texte visible ne change sur Pluralia ;
`locale` publié passe de `fr` à `fr-FR`, même règle de pluriel.

**Vérifié le 2026-09-17** par `curl` sur `/boutique` (WordPress en `fr_FR`) : la description publie
`countPattern` « :count résultat|:count résultats », `filterPattern` « :count filtre actif|:count filtres
actifs », `foldLabels` « Voir plus » / « Voir moins » et `locale` `fr-FR` ; les compteurs rendus par Blade
disent « 14 résultats ». Pas de navigateur : aucun JavaScript ne change.

**Cinq passes, rangées une par une (seconde revue, 2026-09-17, sur le code à HEAD).** *Lisibilité* :
`CountLabel::locale()` rendait un tag de langue — renommée `languageTag()` ; le même bloc « Laravel en `fr`,
WordPress en `en_US` » était recopié dans deux tests — `underLocales()` dans `SwitchesTheSiteLocale`, repris
par un troisième ; locale par trois chemins, repli sans intl intestable — `R-145` (1, 2). Le nom de
`SiteLocaleTest` est gardé : la classe teste ce qui suit la langue du site, et exerce désormais
`SiteLocale::current()`. *Commentaires* : la ligne sur la table de Laravel, retirée avec les commentaires
inutiles, signalait pourtant une anomalie amont — rétablie, chiffrée (111 locales) ; deux commentaires de
`ListingDescription` hors de ce point — `Q-31`. *Performance* : `CountLabel` recalculait le tag de langue
pour chaque valeur de facette (38 par page) — gardé par locale, `CountLabelTest` échoue si le cache ignore la
locale ; `NameOrder` relit la locale à chaque comparaison — `R-145` (3). *Sécurité* : rien. *Contexte* : le
compte de chaque valeur, libellé le plus rendu, n'avait aucun test de langue —
`FacetComponentTest::it_counts_a_value_in_the_language_wordpress_translates_in`, qui échoue en `trans()` ;
`SiteLocale` ne suivait pas la garde du résolveur de `__()` (`CoreWordPressTranslator::locale()` exige
`wp_cache_get` et remplace une locale vide par celle de Laravel) — alignée, test
`it_speaks_laravels_language_when_wordpress_names_none`, qui échoue sans.

### R-137 · 🟠 · **fermé le 2026-09-17** · ouvert le 2026-09-16 — le client et le serveur divergent encore sur quinze règles

Audit demandé par Louis le 2026-09-16 (« d'autres choses divergent par rapport au serveur ? »), en deux
volets exécutés des deux côtés sur les mêmes entrées : lecture de l'URL et plan de requête (57 chaînes
de requête, `StateReader`/`ListingSearch` contre `ListingUrl`/`ListingQuery`), puis rendu (20 parcours
dans Chrome, chaque grille repeinte comparée au rendu serveur de la même URL ; 50 583 montants et ratios
comparés). Aucun des écarts ci-dessous n'est couvert par un test.

**Visibles par un visiteur ordinaire**

| # | Écart | Constaté |
| --- | --- | --- |
| 1 | Le client ignore `/page/N` : le serveur lit `paged`, le client seulement `pg` | `/boutique/page/2`, cocher une marque : l'URL devient `/boutique/page/2?marque=aeris`, recharger affiche « Il n'y a rien sur cette page » ; Retour ramène la page 1 au lieu de la 2 |
| 2 | Une valeur de facette absente du premier rendu ne revient jamais (le client ne crée pas de nœud) | `/boutique?marque=aeris` puis « Tout effacer » : 4 catégories sur 5, 7 contenances sur 24, plus de « Voir plus » — contredit par la décision « le client révèle, il n'en crée aucun », qui prévoyait de rouvrir « si le catalogue réel le montre » |
| 3 | « Voir plus » apparaît alors que rien n'est replié : le client compte les valeurs cochées dans la place disponible | `/categorie-produit/visage`, déplier, cocher trois contenances, Appliquer : bouton « Voir moins » qui ne fait qu'inverser son libellé ; aucun bouton côté serveur |
| 4 | Une valeur cochée tombée à 0 : le client l'affiche « 0 résultats » (règle de pluriel anglaise), le serveur ne la rend pas du tout et le visiteur ne peut plus la décocher | `/boutique?marque=nord-sel`, prix mini 150 |
| 5 | Le message « aucun résultat » n'est jamais réécrit (le serveur en choisit un parmi deux) | `/boutique?pg=99`, prix mini 1000 : « Il n'y a rien sur cette page » au lieu de « Aucun résultat » |
| 6 | Sur une recherche produit, le client efface les paramètres qui ne sont pas à lui | `/?s=creme&post_type=product`, trier : l'URL devient `/?sort=newest`, qui sert l'accueil |

**URL fabriquées à la main**

| # | Écart |
| --- | --- |
| 7 | Une valeur de facette en UTF-8 invalide (`?marque=%FF`) fait échouer l'encodage JSON côté serveur : page « moteur indisponible » et une erreur journalisée à chaque appel ; le client cherche normalement |
| 8 | Bornes de prix : `12,5`, `12abc`, `0x1A` refusées par le serveur (`is_numeric`), lues 12 ou 0 par le client (`parseFloat`) ; `-0` écrit `>= -0` côté client, que Meilisearch n'interprète pas comme `>= 0` (un produit gratuit perdu) |
| 9 | Paramètre répété : le serveur garde le dernier, le client le premier |
| 10 | Page `1e3` : 1000 côté serveur, 1 côté client ; tri non rogné côté client ; espaces Unicode rognés côté client seulement ; ordre des valeurs numériques |

**Cosmétique ou inatteignable aujourd'hui** : texte masqué du badge à zéro (`R-78`) ; chiffres périmés
dans un bloc de prix masqué ; les quatre premières images perdent `eager`/`high` après une recherche
(voulu, verrouillé par `CardComponentTest`) ; format de prix sans WooCommerce ; `visible: 0` ; cas de
champs de carte absents du catalogue ; `q` en UTF-8 invalide ; bornes `1e300` ; flottants écrits par
Blade ; plafond de facette à 0 ; noms de paramètre contenant un point.

**Déjà connus** : taxes retirées côté serveur seulement (`D-e`, sans effet sur Pluralia, taxes
désactivées — fermé par `R-146`, le prix indexé étant désormais le prix affiché) ; `alt` `'0'`.

**Identiques** (vérifiés en exécutant) : échappement, assemblage et ordre des clauses, recherches à part,
tri, lecture des valeurs de facette, requête texte, page (hors cas ci-dessus), prix (hors cas ci-dessus),
formatage des bornes, compteurs, pagination, contrôle de prix et ses attributs, tri, badge, cartes.

**Corrigé le 2026-09-16**, sans décision à prendre (« corrige ceux où tu n'as pas besoin de moi ») —
chaque correction a un test, vérifié en échec sans elle :

| # | Correction | Test | Vu dans Chrome |
| --- | --- | --- | --- |
| 1 | *Première version :* `ListingUrl` lisait `/page/N` avec un motif `/page/` en dur. *Depuis le 2026-09-17 :* le serveur publie le chemin de la première page (`pagePath`, `Http\PageAddress::path()`, soit `get_pagenum_link(1)`), qui suit le vrai nom du segment de pagination et retire les barres de tête ; le client écrit ce chemin puis ses paramètres, la page en `pg` | `PageAddressTest` (segment renommé, `//boutique/page/2`, `//?s=creme`, permaliens fixés en mémoire), `ListingDescriptionTest`, `listing.test.ts`, `listing-url.test.ts` | `/boutique/page/2` : page 2 marquée ; Suivant puis Retour ramène la page 2 ; cocher une marque donne `/boutique?marque=aeris`, page 1 |
| 3 | `FacetsView` ne replie plus une valeur cochée, qui garde sa place avant le pli (*rectifié le 2026-09-17 : la première rédaction disait qu'elle ne comptait plus dans la place, ce que contredit le test « counts a held value among the places before the fold »*) | `facets-view.test.ts` | `/categorie-produit/visage`, trois contenances repliées cochées : 13 valeurs, aucun bouton, comme le serveur ; trois visibles cochées : 10 valeurs et « Voir plus » des deux côtés |
| 5 | Blade rend les deux messages, chacun sous son crochet (`no-results`, `past-the-end`), l'un masqué ; `ResultsView` révèle celui de `PageWindow.isPastTheEnd`, copie de `Pagination::isPastTheEnd()` ajoutée aux cas partagés `tests/pagination-cases.json`. Le client n'écrit aucun texte : une première version réécrivait le contenu de `empty` et aurait effacé le markup d'une vue surchargée (passe « contexte ») | `ResultsComponentTest`, `listing-binding.test.ts`, `page-window.test.ts`, `PaginationTest` | `/boutique?pg=99` puis prix mini 1000 : « Aucun résultat n'a été trouvé. » ; retour sur `?pg=99` : « Il n'y a rien sur cette page. » |
| 6 | *Première version, retirée le 2026-09-17 :* tout paramètre que le listing ne possède pas était recopié, `add-to-cart` et `utm_*` compris. *Depuis :* seuls les paramètres que WordPress a lus pour construire la page, publiés par le serveur tels qu'envoyés (`Http\PageAddress`), hors ceux du listing et `paged`. Une version intermédiaire lisait `request()->query()`, déjà rogné et vidé par les middlewares : `/?s=&post_type=product` perdait `s=` et le premier geste servait la boutique (trouvé par les cinq passes). Trois relectures au total : la deuxième a fait tester l'exclusion d'un nom de facette devenu query var publique, que rien ne protégeait, rendu `pageQuery` obligatoire et suivi `arg_separator.input` ; la troisième, sur ces corrections, trois retouches de commentaires et de test. Mutations : 7, toutes tuées | `PageAddressTest`, `ListingDescriptionTest`, `listing-url.test.ts`, `listing.test.ts` | `/boutique?add-to-cart=999999&utm_source=news`, cocher une marque : `/boutique?marque=aeris` ; `/?s=creme&post_type=product&utm_source=news`, trier : `/?sort=price_asc&s=creme&post_type=product` (16 cartes) ; `/?s=&post_type=product&utm_source=news`, trier : `/?sort=price_asc&s=&post_type=product`, toujours une recherche au rechargement ; `/boutique?min_price=10&utm_source=x` publie `pageQuery` vide |
| 7 | `StateReader` écarte une valeur qui n'est pas de l'UTF-8 (*la moitié client, une valeur remplacée par U+FFFD, a disparu avec `url-text.ts` : le client ne lit plus l'URL*) | `StateReaderTest` | `/boutique?marque=%FF` : 200, 16 cartes ; `?marque=aeris,%FF` filtre sur aeris |
| 8–10 | *Première version, retirée le 2026-09-17 :* le client lisait l'URL avec une copie en TypeScript des règles de PHP (`url-text.ts`). *Depuis :* le client ne tire plus aucun état d'une URL, il part de l'état publié par le serveur (`state` dans la description) ; valeurs triées par octet côté serveur (`SORT_STRING`) ; `-0` tenu pour `0` | `StateReaderTest` (18 URL tapées à la main, `assertSame`), `ListingDescriptionTest`, `tests/url-writing-cases.json` lu des deux côtés | `/boutique?marque=nord-sel,aeris,aeris&pg=abc&sort=inconnu` : état publié `aeris, nord-sel`, page 1, pas de tri ; cocher `avril` écrit `?marque=aeris,avril,nord-sel` |
| 11 | Retour et Suivant : l'état est rangé dans l'entrée d'historique, sous le nom du listing, et restauré sans réécrire l'entrée ; une entrée qu'aucun listing n'a écrite reçoit l'état affiché à la même adresse, recharge la page sinon ; pas de recherche vers un état déjà affiché | `browser-history.test.ts` (10 cas, onglet simulé), `listing.test.ts` | `/boutique/page/2`, Suivant, tri, Retour : adresse `/boutique/page/2` gardée, page 2, Pertinence ; lien d'évitement `#main`, Retour, Suivant : ni rechargement ni recherche ; entrée d'un autre script à une autre adresse : rechargement, le serveur relit `?marque=aeris` |

`composer check` vert (215 tests PHP, 254 tests du client), suite `Modules` verte (294 tests).

**Cinq passes sur #5** :
- lisibilité : un `PageWindow` nommé `pages`, déjà le nom de son nombre de pages et des boutons → `pageWindow` ; `show()` mêlait grille et message → `#showEmpty()` ;
- commentaires : deux ajoutés qui redisaient le code, supprimés ; `ListingDescription` citait `description.js` → `.ts` ;
- performance et sécurité : rien ;
- contexte : la première version réécrivait le contenu de `empty` et publiait les messages par `trans()`, là où Blade passe par `__()`. Corrigé en rendant les deux messages dans Blade.

Trouvé au passage et **non corrigé** : `countPattern`, `filterPattern` et `foldLabels` publiés par `trans()` là où Blade écrit `__()` — ouvert sous `R-138`.

**Retrait de `url-text.ts` (#8–10, #11), 2026-09-17.** Décision de Louis écrite dans `decisions.md`. Conformité
passée avant le code : elle a relevé que l'entrée servie devait recevoir son état au démarrage (sinon le
Retour mesuré en `R-74` régressait), qu'une entrée d'un autre script peut porter un état non nul, et qu'une
page peut porter plusieurs listings. Cinq passes avant l'annonce :
- lisibilité : un effet de bord caché dans un argument (`#recording`), trois verbes pour « enregistrer »,
  le format d'une entrée connu de `Listing` et de `BrowserHistory` → `record()`, un seul propriétaire ;
  `StateChanges` dérivé de `StateDescription` ;
- commentaires : sept supprimés, trois faux corrigés, dont une phrase de `decisions.md` sur l'ordre de tri
  (JavaScript compare des unités UTF-16, PHP des octets : même ordre pour tout slug WordPress) ;
- performance : une recherche relancée vers un état déjà affiché → évitée ;
- sécurité : un tri pris par le prototype (`constructor`) → `Object.hasOwn` ;
- contexte : rognage de la recherche et rejet d'une valeur vide perdus avec `url-text.ts` → rétablis sur
  les gestes ; `"facets":[]` publié pour une sélection vide → objet. Le constat « le Delay JS de WP Rocket
  vide l'état » est faux : ses seuls `replaceState` sont dans le script d'administration.

Quatre défauts de test relevés par la même passe (un test d'ancre qui passait sans enregistrer l'ancre,
`replace`, un seul écouteur, l'entrée suivie au retour) → corrigés ; 24 mutations, toutes tuées. Le premier
passage dans Chrome montrait encore des recherches en trop : le document HTML était en cache
(`max-age=3600`) et chargeait l'ancien paquet. Revérifié après rechargement.

**Reliquats, 2026-09-17.** Le motif `/page/` en dur et `//boutique` (le listing ne répondait plus :
`replaceState` levait sur une URL lue comme un hôte) sont réglés côté module par `pagePath` ; la redirection
canonique de `//boutique`, cassée par Pollora, reste ouverte sous `R-140` ; un test manquant sur le
repli (une valeur cochée compte dans la place avant « Voir plus ») est ajouté. Conformité avant le code ;
cinq passes : un commentaire déplacé, la règle « le numéro de page est au listing » écrite à deux endroits
→ regroupée dans `PageAddress`, des tests qui ne passaient que grâce à la structure de permaliens de
Pluralia → structure fixée en mémoire (`RequestsAnAddress`). **Incident** : pour vérifier les permaliens
simples, l'agent de revue a enregistré une structure vide en base (~3 min) ; restaurés : l'option, la
config WP Rocket, les 24 indexables Yoast (`ddev wp yoast index`). Vérifié dans Chrome : `//boutique`, filtrer
→ `/boutique?marque=aeris` ; `/boutique/page/2`, Suivant → `/boutique?pg=3`, Retour → `/boutique/page/2`.

Restent connus, non traités ici : la limite de Safari sur `replaceState` (100 appels en 30 s, non mesurée) ; une requête `q` en UTF-8 invalide est publiée
convertie par `wp_json_encode`.

**#2 et #4, 2026-09-17.** Louis a retenu l'option C pour #2, puis tranché #4 (« Affichée »), que C
obligeait à trancher. Le serveur compte chaque facette sous le seul filtre de base quand le visiteur a
restreint le listing (`QueryPlan::unfiltered()`, clé `unfiltered`, `ListingSearch::isNarrowed()`), rend
toutes ces valeurs (`FacetValues`) et masque celles qui n'ont plus de résultat ; une valeur sans résultat
ne prend pas de place dans le repli ; une valeur tenue reste affichée, même à 0, et une valeur tenue que
le plafond écartait est ajoutée. La description publie les comptes rendus (`facets[].counts`) et la langue
(`locale`) : le client choisit « 0 résultat » par `Intl.PluralRules`. Tests : `ListingSearchTest`,
`FacetValuesTest`, `FacetComponentTest`, `ListingDescriptionTest`, `facets-view.test.ts`,
`count-label.test.ts`. Pluriels : Louis a retenu la règle CLDR des deux côtés — `View\CountLabel` (ICU,
repli sur la table de Laravel sans `ext-intl`) et `CountLabel` côté client, vérifiés sur
`tests/plural-cases.json`. Trois relectures : la deuxième a trouvé le plafond appliqué à la seule liste non
filtrée, qui faisait disparaître d'une page restreinte des valeurs ayant des résultats (union des deux
listes plafonnées), 111 locales où `Intl.PluralRules` divergeait de `trans_choice()` (d'où la décision
CLDR), et un `RangeError` sur une locale comme `pt_PT_ao90` ; la troisième, un test qui ne protégeait pas
les valeurs tenues hors des deux plafonds, un repli sans `ext-intl` qui aurait écrit « 0 résultats », un
motif ICU relu à chaque appel. Mutations : quinze, toutes tuées. Conformité avant le code (deux passes, la première
bloquée par le watchdog et relancée). Vu dans Chrome : `/boutique?marque=aeris` rend 5 catégories et 24
contenances (4 et 7 visibles) ; « Voir plus » avant toute recherche ne révèle aucune valeur vide ; « Tout
effacer » → 5 catégories, 24 contenances dont 10 lisibles et « Voir plus ». `/boutique?marque=nord-sel`,
prix mini 150 : « Nord Sel » cochée, « 0 résultat » côté client comme dans le rendu serveur, décochable.
Par `curl` : aucune page filtrée ne rend plus de valeurs que sa page non filtrée ; `?q=` sans résultat
masque tous les blocs.

**Cinq passes rejouées (2026-09-17, sur le code à HEAD)** pour les lignes qui n'en avaient pas (#1, #3,
#7) et pour #2 et #4, qui n'avaient eu que des relectures.

- **#1** — *Lisibilité* : `PAGED_QUERY_VAR` hors énumération, `toSearch()` public pour les tests — `R-145`
  (10). *Commentaires* : cinq justifications ou redites (`PageAddress.php:9,12`, `CurrentListing.php:63`,
  `listing-url.ts:34`, `description.ts:66`) — `Q-31`. *Performance* : rien. *Sécurité* : rien — par
  `curl`, `?s="></script>…` est publié échappé et `get_pagenum_link()` ne garde qu'une barre en tête.
  *Contexte* : permaliens simples sans test — `R-145` (10).
- **#2 et #4, qui ferment `R-78`** — *Lisibilité* : règle de repli sans table partagée, `folded` à double
  sens, `$unfiltered`/`$offered` — `R-145` (4) ; clés du moteur en littéraux dans `QueryPlan::unfiltered()` —
  `R-02`. *Commentaires* : dans `facets-view.ts`, « Mirrors FacetValues::folded() », inexact, supprimé ;
  « A block names no taxonomy of its own », faux pour la vue du module, corrigé ; deux justifications
  supprimées. *Performance* : `isNarrowed()` — `R-145` (6) ; `WordPressDefaultTerms` ne retenait pas une
  taxonomie sans terme par défaut (`??=` sur `null`) et relisait deux options et deux termes, deux fois par
  facette — corrigé, `WordPressDefaultTermsTest` échoue sans. *Sécurité* : `distribution[slug] ?? 0` lisait
  le prototype — une valeur tenue de slug `constructor` aurait affiché « function Object() { [native code] }
  résultats » — corrigé par `Object.hasOwn`, test « counts nothing for a value the answer does not name,
  whatever its slug », qui échoue sans. *Contexte* : repli sans intl divergent — `R-145` (2) ; plis non
  calculés à la liaison — `R-145` (5) ; ce registre citait `ListingState::isNarrowed()` et `configuration.md`
  taisait qu'une page filtrée garde jusqu'à deux fois `cap` — corrigés.
- **#3** — *Lisibilité* : paramètres booléens, double sens de `folds` — `R-145` (4). *Commentaires* :
  justifications de `facets-view.ts` et de ses tests — `Q-31` ; la ligne #3 du tableau contredisait le test —
  rectifiée. *Performance* : rien. *Sécurité* : rien. *Contexte* : le client efface le balisage d'un bouton
  « Voir plus » surchargé — `R-143`.
- **#7** — *Lisibilité* : « readable » a trois sens — `R-145` (4). *Commentaires* : rien. *Performance* : rien.
  *Sécurité* : rien — par `curl`, `?marque=%FF` rend 200 et `?marque=aeris,%FF` publie l'état `aeris`.
  *Contexte* : rien. La ligne #7 du tableau décrivait encore la moitié client retirée avec `url-text.ts` —
  rectifiée.

---

### R-136 · 🟠 · **fermé le 2026-09-16** · ouvert le 2026-09-16 — le client et le plan de requête sont rangés par couche, pas par fonctionnalité

Constat du 2026-09-16, partagé avec Louis : chaque fonctionnalité nouvelle modifie les mêmes fichiers
centraux (`listing-query`, `listing-binding`, `QueryPlan`, `ListingDescription`), `price-control.ts`
fait 405 lignes pour une seule classe, et rien n'empêche un fichier de grossir. Point 2 du chantier
« qualité du JavaScript », demandé par Louis (`decisions.md`) : règles de découpage, rangement par fonctionnalité,
découpage des deux grosses classes, contributions de chaque filtre à la requête des deux côtés.

**Règles ajoutées** (ESLint, sources du client) : 200 lignes par fichier, 20 par fonction, complexité 8,
trois paramètres, pas de ternaire imbriqué — lignes vides et commentaires non comptés. Premier passage :
17 dépassements dans 11 fichiers, dont `price-control.ts` (301 lignes utiles), `sort-combobox.ts`
(`#pressedOpen` 28 lignes), `listing-url.ts` (`toSearch` 26 lignes), `page-window.ts` (5 paramètres).

**Contrainte gardée** : `FacetCounter` reste le point d'extension du comptage (`decisions.md`).

**Fait**, étape par étape, tests verts à chaque étape :

1. **Dossiers** : `shared` (contrat, description, client de recherche), `listing`, `facets`, `price`,
   `sort`, `results`, `pagination` ; le point d'entrée reste à la racine. `git mv`, historique gardé ;
   `ContractParityTest` lit le client récursivement.
2. **Contributions à la requête**, des deux côtés, sous les mêmes noms : `FilterQuery` (clé, champs,
   clause, mesure à part), `FacetQuery`, `PriceQuery`. `ListingQuery` et `QueryPlan` ne font
   qu'assembler ; le comptage d'une facette et la mesure des bornes, écrits deux fois, deviennent une
   seule règle (`apart()` : toutes les clauses sauf la sienne). Les clés `count:` et `bounds` vont chez
   leur filtre, `RESULTS` dans `shared/search-client.ts`. `FacetCounter` inchangé.
3. **Prix** : `price-control.ts` (405 lignes) devient `range.ts` (miroir de `Listing\Range`, `clamp()` et
   `ratio()` compris), `price-inputs.ts`, `price-slider.ts` (`PricePart::Slider`), `slider-drag.ts`,
   `slider-keys.ts` et un `price-control.ts` qui les orchestre (203 lignes). Les 71 tests du prix passent
   sans modification de leurs cas.
4. **Tri** : `listbox-keys.ts` (ce que veut dire une touche, sans DOM), `type-ahead.ts`,
   `sort-combobox.ts` (le menu).
5. **Reste** : `ListingPage` remplace la fonction du point d'entrée ; `PageWindow` prend un objet nommé
   comme le constructeur de `Pagination` (`asked`) ; `ListingBinding` prend trois arguments (le contrat
   porte sa racine) ; méthodes découpées dans `ListingState`, `ListingUrl`, `FacetsView`.

**Deux écarts de comportement, voulus** : `Money` passe par `Intl.NumberFormat.formatToParts()` au lieu
d'une regex de groupement — un demi-centime s'arrondit désormais comme `number_format()` de WooCommerce
(1,005 → « 1,01 », `toFixed()` donnait « 1,00 »), test ajouté ; `Range::ratio()` arrondit à quatre
décimales comme le serveur, donc `--from`/`--to` s'écrivent comme au premier rendu.

**Non fait, et pourquoi** : `ListingDescription` reste une classe — elle est déjà l'assembleur de la forme
publiée, une méthode par partie, et `shared/description.ts` en est le miroir en un seul fichier ; la
disperser éparpillerait le contrat que ces deux fichiers tiennent ensemble.

**Revue** (`module-review`, comportement comparé à HEAD : 160 plans PHP, 1 005 plans et URL du client,
99 étapes du tri, 28 du prix — identiques hors écarts voulus). Suites données :

- *Vocabulaire* — un nom par concept, celui du serveur : une seule `Range` (`shared/range.ts`, bornes
  nullables comme `Listing\Range`) remplace `Span`, `PriceRange` et la classe à bornes infinies ; les
  bornes lues dans les réponses passent dans `PriceQuery.boundsFrom()` (miroir de
  `PriceFilter::boundsFrom()`, `Range` vide plutôt que `null`) ; `PriceBound` comme l'énumération ;
  `#fill` (`View\Fill`), `floor`/`ceiling` (`View\RangeHandle`), `writeBounds`/`renderedBound` ;
  `held` ne désigne plus deux choses (`SliderDrag.grabbed`) ; `QueryPlan::filterQueries()` et
  `#filterQueries`/`#filterExpression` côté client, pour ne plus confondre avec `Listing::filters()` ;
  cas de pagination partagés renommés `asked`/`current`, comme `Pagination`.
- *Dépendances* — `listing/` n'importe plus `facets/` ni `price/` : les `FilterQuery` sont assemblées au
  point d'entrée (`filter-queries.ts`) et données au `Listing` ; `RESULTS`/`Plan`, `countLabel`,
  `FilterQuery` et `Range` vont dans `shared/`. Le filtre soulevé est reconnu par sa clé des deux côtés.
- *Bornes inconnues* — elles valent `null` au lieu de ±∞ : une vue surchargée sans bornes n'écrit plus
  « ∞ € » ni `--from: NaN`, et Début/Fin n'y déplacent rien (test ajouté).
- *Bug trouvé en écrivant le test de `Range`*, **des deux côtés** : avec une seule borne,
  `clamp()` ne ramenait pas une valeur sous le minimum (`Range(min: 12.5)->clamp(5)` rendait 5).
  Inatteignable côté serveur (bornes complètes ou vides), atteignable côté client ; corrigé à
  l'identique, cas ajouté à `PriceRangeTest`, qui échoue sur l'ancien code.
- *Tests ajoutés* — ratio non terminal (`0.2764`), grand pas avec Maj (échoue sans lui), facette
  comptée à part qui garde la plage tenue (PHP et TypeScript), bornes inconnues ; `price-range.test.ts`
  devient `price-filtering.test.ts`, `PriceQueryTest` porte ce que `QueryPlanTest` testait de
  `PriceQuery`.
- *Commentaires* — cinq faux corrigés ou retirés, six retirés (justifications, paraphrases, histoire).
- *API retirée, à signaler* — `QueryPlan::counting()`, `priceBounds()`, `isCountedApart()`,
  `measuresPriceApart()` n'existent plus ; un projet qui remplace `FacetCounter` écrit
  `QueryPlan::apart($listing, $state, new FacetQuery($facet))`. Le contrat `FacetCounter` est inchangé.
- *Refusé, par écrit* — `max-lines-per-function` reste à 20 : c'est la valeur validée par Louis dans la
  proposition du chantier ; `CLAUDE.md` dit « ~15 » et la passe de lisibilité continue de le relever.
  Assets republiés avant la suite `Modules` (la revue l'avait trouvée rouge sur une copie périmée).

**Mesuré** (après la revue) : ESLint vert ; 227 tests Node, couverture 95,3 % des lignes, 92,3 % des
branches (94,7 / 91,1 avant le point) ; 195 tests `Unit`, 273 `Modules`. Dans Chrome, sur
`/boutique?marque=aeris&max_price=44` : grille du serveur (plan PHP) identique à celle de la première
recherche du client (plan TypeScript) ; plan envoyé = résultats, comptage de la marque sans sa clause,
bornes sans la clause du prix ; tri au clavier et saisie au vol, poignée au clavier et glissée,
remise à zéro, pagination, retour, sans erreur.

**Cinq passes rejouées (2026-09-17, sur le code à HEAD).** *Lisibilité* : `listing-binding.ts` importe
toutes les fonctionnalités, contrairement à ce que dit la revue ci-dessus ; cycles par `ListingState`,
fonctions libres, `SortQuery` et `PriceQuery` construites deux fois, `#acted`, règles ESLint absentes pour
les booléens, les fonctions libres et les frontières — `R-145` (7) ; `QueryPlan` statique — `R-01`. Mesuré
à 15 lignes par fonction, trois fonctions dépasseraient (`#bind` 19, `#acted` 19, `#post` 16) : le seuil
reste 20, validé par Louis. *Commentaires* : une trentaine relevés, dont douze pointeurs vers le miroir PHP —
`Q-31`. *Performance* : clauses reconstruites jusqu'à k+3 fois par rendu — `R-145` (6). *Sécurité* : rien,
échappement en une passe identique des deux côtés. *Contexte* : remplacer `FacetCounter` ne change que le
premier rendu — `R-144`.

---

### R-135 · 🟡 · **fermé le 2026-09-16** · ouvert le 2026-09-16 — le client efface le texte alternatif que le serveur a rendu

Constaté pendant la recette de `R-133`, antérieur à la conversion. Le thème écrit
`$card['image_alt'] ?: $card['title']` (`parts/posts/post-card.blade.php`), le client
`card.image_alt ?? card.title` : un `image_alt` indexé vide — le cas de « Crème Hydratante Riche » — donne
le titre au premier rendu et un `alt` vide dès la première recherche. L'image perd son nom pour un lecteur
d'écran.

**Corrigé** (demandé par Louis) : le rendu du module suit `CardImage::from()` — `image_alt ?: titre` — et
`CardView` fait désormais de même (`image_alt || titre`). Écart résiduel assumé : PHP tient aussi la chaîne
`'0'` pour vide, pas JavaScript. Test ajouté, qui échoue sur l'ancienne règle. Vérifié dans Chrome sur
`/boutique?marque=aeris` : « Crème Hydratante Riche » garde son `alt` après deux tris, aucune image de la
grille n'a d'`alt` vide.

**Cinq passes (2026-09-17, sur le code à HEAD).** *Lisibilité* : rien. *Commentaires* : le docblock de
`CardView` disait qu'une carte garderait ce que la précédente affichait, faux puisque chaque carte est un
clone neuf du gabarit — ramené à sa première phrase ; « Mirrors CardImage::from() » était inexact, le client
gardant `'0'` et un nombre que `CardDocument::text()` refuse — supprimé ; la justification des dimensions
(`card-view.ts:49-52`) — `Q-31`. *Performance* : rien. *Sécurité* : rien, `alt` écrit par propriété.
*Contexte* : `alt` ou titre numérique traités différemment des deux côtés, repli de l'`alt` dans le client,
image visible sans test — `R-145` (9).

---

### R-134 · 🟠 · **fermé le 2026-09-16** · ouvert le 2026-09-16 — le chargement du client n'a été confronté ni à la priorité de WordPress ni au Delay JS de WP Rocket

Relevé par la vérification des sources à jour demandée par Louis (septembre 2026). Tranché par Louis le
2026-09-16 : mesurer la priorité, exclure le client du Delay JS (`decisions.md`).

**Delay JS — corrigé.** Activée, cette option de WP Rocket réécrit un `type="module"` en
`text/rocketlazyloadscript` et ne l'exécute qu'au premier geste du visiteur (`DelayJS/HTML.php:219-263`) :
le listing resterait inerte jusque-là. Désactivée localement, son réglage ailleurs n'est pas connu.
`ListingScript::excludeFromDelayedScripts()` ajoute le chemin du paquet aux exclusions
(`rocket_delay_js_exclusions`), brut comme les intégrations de WP Rocket le font : il échappe lui-même `+`,
`?ver` et `#`, et un chemin déjà passé par `preg_quote` casserait la regex en silence s'il en contenait un.
Deux tests `Feature` construisent une balise de module avec `wp_get_script_tag()`, appliquent à chaque
exclusion l'échappement puis la correspondance de WP Rocket (`HTML.php:120-131`, `:221`) : le client est
exclu, un autre module ne l'est pas. Le premier échoue sans l'attribut `#[Filter]`. Vérifié aussi, par la
revue, sur la vraie balise de `/boutique`, avec un autre hôte (CDN) et sous le chemin minifié de WP Rocket.

**Passes** (`module-review`, R-133 et R-134 ensemble). *Lisibilité* : le second test annonçait « every
other script » et n'appelait pas le filtre — renommé et passé par `apply_filters` comme le premier ; les
noms de hooks restent écrits en dur, comme les neuf attributs du module — question pour tout le module,
pas pour ce point. *Commentaires* : ligne de test inexacte (elle sautait l'échappement) corrigée, docblock
`array<mixed>` supprimé, commentaire de `bundle.ts` rendu caduc par npm 11.19 supprimé, phrase du docblock
de `ListingScript` précisée. *Performance* : rien. *Sécurité* : double échappement latent retiré (voir
ci-dessus). *Contexte* : coût de la version de Node figée dans ddev écrit dans `decisions.md` ; formulations
de R-133 et de `decisions.md` rectifiées (écart de `CardPainter`, lockfile, `bundle.ts`).

**Priorité — mesurée, puis appliquée.** WordPress charge ses propres modules en `fetchpriority="low"`, le
contenu étant rendu par le serveur (note 6.9). Page `/boutique` servie en statique, seule la balise
changeant, médianes :

| | `auto` (actuel) | `low` |
| --- | --- | --- |
| local (9 passes) : LCP / client lié | 128 ms / 68 ms | 132 ms / 69 ms |
| « 4G lente », 150 ms, 1,6 Mbit/s (7 passes) : LCP | 1 428 ms | 1 196 ms |
| id. : client reçu / client lié (`DOMContentLoaded`) | 552 ms / 1 475 ms | 1 538 ms / 1 550 ms |

`low` gagne 232 ms de LCP en réseau lent et lie le listing 75 ms plus tard. Limite : scripts du serveur
Vite retirés des deux variantes, donc sans la concurrence d'un thème en production.

**Tranché par Louis le 2026-09-16** : la meilleure configuration, surchargeable par filtre. `ListingScript`
inscrit le module en `low`, lu à travers `meilifacets/script_fetchpriority` (`decisions.md`,
`configuration.md`). Deux tests `Feature` impriment les modules d'un registre `WP_Script_Modules` neuf :
`fetchpriority="low"` par défaut, `high` quand un filtre le demande ; tous deux échouent si la priorité
n'est pas transmise.

---

### R-133 · 🟠 · **fermé le 2026-09-16** · ouvert le 2026-09-16 — outillage du client en retard sur ses sources à jour

Relevé par la vérification des sources à jour demandée par Louis (notes de version TypeScript 6 et 7,
typescript-eslint, ESLint 10, calendrier Node). Tranché par Louis le 2026-09-16 : points 1 à 4 appliqués
(`decisions.md`, « Le client est écrit en TypeScript et livré empaqueté »). Passes : voir `R-134`, qui les a
menées pour les deux points.

**Node.** 24.1.0 sur la machine (retrait des types encore expérimental, un avertissement par fichier de
test), 24.18.0 dans ddev (sans le correctif de sécurité de 24.18.1). Passés tous deux en 24.21.0 : `nvm
install 24` (l'alias par défaut `24` suit), et `nodejs_version: "24.21.0"` dans `.ddev/config.yaml` du
projet — `"24"` restait sur la version de l'image, même après reconstruction. `engines` : `>=24.12`.

**TypeScript.** Paquets aux noms officiels : `@typescript/native` (7) et `typescript` →
`@typescript/typescript6` ; l'alias `typescript-7` était celui que l'équipe TypeScript a retiré de son
annonce (typescript-go#4567). `tsconfig.json` ajoute `exactOptionalPropertyTypes` et `noImplicitOverride`
(proposés par `tsc --init` en 7.0) et retire `DOM.Iterable`, inclus dans `DOM` depuis 6.0 ; les tests sont
vérifiés en `nodenext`, comme Node les exécute. Zéro erreur, sans rien changer au code.

**Lint typé** (`recommendedTypeChecked`, `projectService`), que typescript-eslint « recommande
fortement ». Trouvé et corrigé :

- `CardPainter` écrivait `String(valeur ?? '')` : un champ de carte ni texte ni nombre s'affichait tel que
  JavaScript le convertit — « [object Object] », « true », ou les éléments d'un tableau joints par des
  virgules. Il s'écrit désormais vide — **seul écart de comportement**, sur une donnée que
  `DefaultCardProjector` n'émet pas (chaînes et entiers seulement) ; chaîne vide, `'0'`, `0` et `null`
  s'écrivent comme avant. Test ajouté, qui échoue sur l'ancien code ;
- trois lectures JSON (`listing-page.ts`, `search-client.ts`) arrivaient en `any` : leur forme est
  désormais déclarée (`as`), rien n'est vérifié à l'exécution ;
- tests : doublure qui rejetait une valeur non typée, table de pagination lue en `any`, type de propriété
  CSS qui comprenait les méthodes.

Les appels `describe`/`it` de `node:test` sont déclarés sûrs (`allowForKnownSafeCalls`) au lieu de
236 alertes.

**Vérifié** : `composer check` vert sur la machine (Node 24.21, sans avertissement), 219 tests Node,
269 tests `Modules` ; paquet reconstruit et publié ; dans Chrome, marque, tri au clavier, poignée au
clavier, remise à zéro, pagination, sans erreur.

`CardPainter` est renommée `CardView` le même jour, à la demande de Louis : `show()`, comme les autres vues
du client qui remplissent le balisage du serveur (`ResultsView`, `FacetsView`, `PaginationView`).

**Un `node_modules` par système, pas deux** : npm 11.19, désormais des deux côtés, ne garde que les
binaires du système qui installe, quelle que soit l'option (`--os`, dépendances optionnelles explicites,
`--force`). **Tranché par Louis le 2026-09-16 : pas de second `node_modules`.** L'outillage Node du module
s'installe et tourne sur la machine ; ddev ne sert qu'au PHP (`decisions.md`, `README.md`).

---

### R-132 · 🟠 · **fermé le 2026-09-16** · ouvert le 2026-09-16 — les types du client ne sont vérifiés par rien, et le client part en vingt et un fichiers

Mesuré le 2026-09-16, avant toute modification :

- `jsconfig.json` déclare `strict` et `checkJs`, mais aucune commande ne l'exécute. `tsc --noEmit` y
  trouve **56 erreurs dans le code livré**, 560 avec les tests — dont la plage de prix, déclarée en
  nombres, que `listing-url.js` remplit de chaînes ;
- le navigateur charge **21 fichiers** non minifiés : 79,2 Ko, 25,1 Ko une fois chacun compressé ;
- seul le point d'entrée porte un `?ver=` : c'est `R-70`, qui s'est reproduit ce matin sur
  `listing-binding.js`.

**Tranché par Louis le 2026-09-16** (`decisions.md`, « Le client est écrit en TypeScript et livré
empaqueté ») : sources en TypeScript, un seul fichier construit et commité.

Premier des trois points du chantier « qualité du JavaScript » validé le même jour, un à la fois :

1. **ce point** — outillage, conversion fichier pour fichier sans changer la structure, erreurs de
   type corrigées, empaquetage ;
2. garde-fous ESLint (taille de fichier et de méthode, complexité, paramètres) et rangement par
   fonctionnalité, qui découpe `price-control` et `sort-combobox` ;
3. performance du client, mesurée avant et après.

`R-131` est mis en pause pour ne pas mêler les deux chantiers dans les mêmes fichiers.

**Corrigé.** Fichier pour fichier, sans toucher à la structure (point 2) :

- sources et tests renommés par `git mv` en `.ts` ; types JSDoc convertis par les correctifs du
  compilateur lui-même, puis repris à la main : `strict`, `noUncheckedIndexedAccess`,
  `erasableSyntaxOnly`, `verbatimModuleSyntax`. Aucun `any` ; les réponses du moteur ont un type
  (`SearchAnswer`, `Plan`), la description aussi (`reserved` nomme ses cinq clés) ;
- `Listing` dépend de `HistorySeam` et `SearchSeam` — ce qu'il appelle réellement — plutôt que des
  classes : les doublures de test s'y conforment sans cast ;
- tests : le faux `Node` du test du contrat est remplacé par le DOM de happy-dom, mêmes cas ;
  `FakeHistory` et `FakeClient`, recopiés dans deux fichiers, n'existent plus qu'une fois
  (`tests/ts/fixtures.ts`) ; `described()` refuse une description de test mal formée, ce qui a
  complété trois fixtures sur ce que PHP publie réellement (`filter: ''` au lieu de `null`) ;
- ESLint lit le TypeScript ; ses règles recommandées ont fait réécrire six expressions employées comme
  instructions (`a ? b() : c()`, `a && b()`) en `if` ;
- `composer check` ajoute la vérification des types, des seuils de couverture (94 % des lignes, 90 %
  des branches et des fonctions, sous les 94,7 / 91,1 / 93,1 mesurés) et la conformité du paquet
  commité ; `ListingScript` sert `dist/listing.js`.

**Deux écarts de comportement, et pourquoi.** `ListingUrl` ignore une facette sans paramètre d'URL au
lieu de lire un paramètre nommé « undefined », et `ResultsView` saute une carte dont le gabarit n'a
pas d'élément au lieu de peindre la carte précédente. Les deux cas sont impossibles tant que PHP publie
un paramètre par facette et que la règle `card-template` du contrat tient.
Testés le 2026-09-17 (`ListingUrl` écrit désormais l'adresse sans la lire, `R-137`) : `listing-url.test.ts`
« writes nothing for a facet the server published no parameter for » et `results-view.test.ts` « paints no
card when the card template holds no element » ; chacun échoue sans sa garde.

**Régression attrapée par les tests pendant la conversion** : un remplacement mécanique avait écrit
`split(' | ')` pour `split('|')` dans `countLabel` — « 1 result|1 results » ; corrigé avant livraison.

**Mesuré après**, Chrome, cache vidé : une requête pour le client au lieu de vingt et une, **8,8 Ko
transférés** (8,5 Ko compressés, 24,5 Ko décompressés) au lieu de 25,1 Ko compressés. 217 tests Node,
193 tests `Unit`, 267 tests `Modules`.

**Performance comparée**, même page `/boutique` servie en statique, seule la balise du script changeant
(client de HEAD contre paquet), scripts de développement du thème retirés des deux, Chrome, médianes :

| | 21 fichiers (HEAD) | paquet |
| --- | --- | --- |
| à froid, local (9 passes) : client reçu / `DOMContentLoaded` | 88 ms / 98 ms | 24 ms / 66 ms |
| cache chaud, local (5 passes) : client reçu / `DOMContentLoaded` | 57 ms / 65 ms | 16 ms / 54 ms |
| à froid, réseau « 4G lente » 150 ms, 1,6 Mbit/s (5 passes) : client reçu / `DOMContentLoaded` | 1 560 ms / 1 746 ms | 552 ms / 1 327 ms |
| temps JavaScript de la page (`ScriptDuration`), local / 4G lente | 27 ms / 51 ms | 25 ms / 47 ms |
| recherche : clic sur « Appliquer » → grille peinte (9 passes), dont moteur | 36 ms, 8 ms | 35 ms, 7 ms |

Le gain est au chargement et vient du nombre d'allers-retours : les imports se découvrent en cascade,
chacun attend le précédent. L'exécution et la recherche ne changent pas, ce qui est attendu — le code
exécuté est le même.

**Vérifié dans Chrome, sur le code publié** : marque et « Appliquer », tri à la souris puis au clavier,
poignée au clavier puis glissée à la souris (`data-active` posé), remise à zéro, pagination, retour
arrière ; aucune erreur en console.

**Passes** (`module-review`) :

- *Lisibilité.* Retenu et corrigé : le repli de `open()` sur `document.body`, qui laissait une fixture
  sans racine se lier en silence (séparé en `load()` et `open()`) ; `described(object)` sans contrôle ;
  `Plan` qui réécrivait la clé `results` ; l'ensemble `min`/`max` écrit trois fois dans
  `PriceControl` (`ENDS` porte le type) ; `#find()` à 25 lignes (découpé) ; les signatures de rappel
  répétées (`Commit`, `Choose`) ; le type `Card` que le transport importait d'une vue (déplacé dans
  `description.ts`) ; deux noms pour la version du contrat en test ; la clé de facette écrite à la main
  et un sélecteur `:has()` répété (`facetField()`, `closestHook()`) ; `:nth-child` qui comptait aussi
  ce qui n'est pas une option (`nth()`).
- *Commentaires.* 340 lignes de balises JSDoc retirées des sources. Supprimés : huit descriptions de
  la description qui redisaient le nom, et trois commentaires ajoutés en test. **Gardés, par écrit** :
  six descriptions de membres de `description.ts` (`cap`, `visible`, `reachableHits`, `sorts`,
  `params`, `countPattern`) — ce sont les `@property` de la forme publiée par PHP, déjà présentes avant,
  qui disent ce que le type ne dit pas ; et les lignes antérieures de `page-window.ts` et
  `PriceControl::#receive`, que ce point ne touche pas.
- *Performance.* Rien. Une requête au lieu de vingt et une ; `ListingState.with()` ne clone plus les
  facettes, que le constructeur recopie de toute façon ; les raisons d'échec d'une recherche ne sont
  plus réallouées à chaque échec.
- *Sécurité.* Rien : `#escape` inchangé, aucun secret dans le paquet.
- *Contexte.* Retenu et corrigé :
  - **`Contract.one()`/`all()` ne rendaient plus que des éléments HTML**, ce qui refusait un crochet
    posé sur du SVG. Écart non décidé, donc retiré : ils rendent tout élément, comme avant, et chaque
    vue garde ses propres vérifications. Le contrôle de prix accepte une poignée, une piste ou un rail
    SVG. Seule contrainte nouvelle : les deux champs du prix doivent être des `<input>`, ce que
    `architecture.md` exigeait déjà ;
  - `build:check` masquait un échec d'esbuild derrière « ne correspond pas aux sources » ;
  - la décision ne mentionnait pas TypeScript 7 ;
  - `R-70` et `T-39` restaient ouverts ;
  - un docblock de `PaginationTest` citait encore `tests/js` ;
  - aucune version minimale de Node n'était déclarée (`engines`, 22.18).

  **Refusé, par écrit** : les copies `ts/` que `PublishedAssets` surveille sont publiées par la même
  commande que le paquet, donc périmées ensemble ; coût déjà écrit dans la décision.

**Rouvert le jour même par Louis : `composer check` KO sur sa machine.** Les dépendances avaient été
installées depuis ddev, donc pour Linux seulement : TypeScript 7 échouait sur
`Unable to resolve @typescript/typescript-darwin-arm64`, et `node_modules/.bin/esbuild` était un
exécutable Linux (code 126). Mesuré : une installation depuis ddev (npm 11.16) retire les binaires
macOS, une installation depuis la machine (npm 11.3) garde ceux de Linux. Corrigé : dépendances
installées depuis la machine, et le paquet construit par l'API d'esbuild (`bundle.ts`), qui résout le
binaire du système courant — ce qui remplace aussi les deux commandes esbuild qui répétaient leurs
options. `engines` passe à Node 24, celui des deux environnements. `composer check` vert sur la
machine et dans ddev ; paquet reconstruit identique à l'octet.

**Constaté, hors de ce point** : `search-client.ts` couvert à 60 %, `browser-history.ts` sans aucune
fonction testée, `listing-page.ts` absent du rapport — antérieur à la conversion.

---

### R-131 · 🟠 · **fermé le 2026-09-17** · ouvert le 2026-09-16 — aucune option « Promotions » : un tri ne sait pas filtrer

Décision du 2026-09-16 (`decisions.md`, « Promotions est une option du tri qui filtre ») : une option du
menu de tri qui n'affiche que les produits en promotion, remplace le tri, n'apparaît que si le listing
déclare un prix, que WooCommerce est actif et qu'il reste des promotions dans la sélection. Le drapeau
est indexé depuis `R-130`.

Ce que le modèle ne sait pas faire aujourd'hui : `Sort` ne porte que des expressions de tri ; la
description envoyée au client les publie en tableau nu (`sorts`) ; rien ne compte ce qu'une option
retiendrait ; le menu rend toutes ses options sans condition, et sa navigation clavier ignore `hidden` —
une option masquée y resterait atteignable par les flèches et la saisie au vol.

**En pause depuis le 2026-09-16**, pour `R-132` : le travail, non commité, est remisé dans
`git stash` (« wip(R-131): Promotions sort filter, paused for R-132 ») et sera repris sur le client
restructuré. Deux comportements y ont été codés sans décision et attendent Louis : le filtre
« Promotions » appliqué aux comptes des facettes et aux bornes du prix, et l'option jamais masquée
quand elle est en cours.

**Repris le 2026-09-17** sur le client TypeScript. Louis a tranché les deux comportements : les comptes et
les bornes suivent la grille, l'option reste visible tant qu'elle est choisie. Porté depuis le stash :
`Sort::filtering()`, `SortFilter`, `WooCommerceSorts`, `ProductListing::sorts()` (sans prix, pas de
promotions), `SortChoices` et la vue (option rendue `hidden` quand elle ne garderait rien). Réécrit sur le
modèle actuel : `Search\SortQuery` et `sort/sort-query.ts` sont des `FilterQuery` comme `PriceQuery`, donc le
filtre entre dans la recherche principale, les comptes à part et les bornes sans code de plus ; « ce que la
sélection garderait » se lit sur la distribution `price.onsale` de la recherche principale. Relevé par la
conformité et corrigé : une page servie en `?sort=on_sale` seul ne comptait pas le listing non filtré, et
revenir à un autre tri aurait perdu les marques sans promotion — la recherche non filtrée part désormais
dès qu'un filtre, tri compris, restreint le listing. Clavier : les flèches, `Home`/`End` et la saisie au vol
ne parcourent que les options visibles ; un clic sur une option masquée est ignoré. Tests : `QueryPlanTest`,
`ListingSearchTest`, `SortChoicesTest`, `SortComponentTest`, `ProductFacetsTest`, `listing-query.test.ts`,
`sort-combobox.test.ts`, `listing-binding.test.ts` ; dix mutations, toutes tuées. Vu dans Chrome : choisir
« Promotions » → 16 cartes, 7 marques sur 9 avec leurs comptes de promotions, bornes 8–199 € au lieu de 0–199 € ;
cocher Nord Sel → option toujours affichée, grille vide ; revenir en « Pertinence » → 9 marques, option
masquée ; `End` au clavier s'arrête sur « Nouveautés ». Par `curl` : `/boutique?marque=nord-sel` et
`?marque=terra-nova` rendent l'option masquée.

Cinq passes : un défaut clavier réel — une réponse qui masquait l'option active pendant que la liste était
ouverte laissait `Tab` choisir « Pertinence » ; le clavier revient désormais sur le tri en cours (test
ajouté). Aussi : trois noms pour « ce qu'un tri garderait » → `matchesIn`/`sortMatches` ; la même
`SortQuery` construite deux fois côté serveur → `QueryPlan::sortQuery()` ; `SortChoices::of()` sans
comptes masquait toutes les options filtrantes → paramètre obligatoire ; `sortFilters` publié en `[]` →
objet ; `ResolvedListing::sorts()` mémoïsé ; deux commentaires supprimés ; quatre passages de
`architecture.md` mis à jour (champ filtrable, tri qui restreint, option `hidden`, vue surchargée).
Laissé en l'état : `ListingBinding` construit sa `SortQuery` à côté de celle du plan, comme
`PriceControl` construit sa `PriceQuery`.

**Cinq passes, rangées une par une** (seconde revue `module-review`, 2026-09-17, sur le code à HEAD — la
première n'avait pas attribué ses constats). *Lisibilité* : `SortCombobox` passait la même liste d'options
visibles à trois méthodes privées — extraite dans `sort/shown-options.ts` (`position()`, `indexAt()`,
`labels()`), comportement inchangé, 21 tests du tri verts ; `needsNoPrice()` gardait `price_asc` —
renommée `isOfferedWithoutPrice()` ; `SortChoices::visible()` pouvait rendre une option masquée — renommée
`hiddenIfEmpty()` ; `FilterExpression::facet()` écrivait l'égalité en ligne à côté de `equals()` — passe par
`equals()`, comme le client ; `SortQuery.matchesIn()` recevait les réponses côté client et la distribution
côté serveur — même contrat des deux côtés, `ListingBinding` extrait la distribution ; un test de
`QueryPlanTest` disait « counts » — renommé `it_asks_the_main_search_for_the_field_a_sort_filters_on` ; un
test de `SortComponentTest` vérifiait `ListingDescription` — déplacé dans `ListingDescriptionTest`.
`QueryPlan` reconstruit à chaque appel (`sorts()` sept fois par rendu selon la revue) : c'est `R-01`,
complété. *Commentaires* : le contrat `ProductSorts` annonçait « to its expression », faux pour un tri qui
filtre — « to its sort ». *Performance* : `ListingSearch` évaluait `isNarrowed()`, qui construit toutes les
requêtes de filtre, avant de savoir si le listing a des facettes — conditions inversées ; le reste relève
de `R-01`. *Sécurité* : rien — `sort` est comparé aux tris déclarés, la valeur échappée une fois, le champ
vient du code. *Contexte* : rien sur Pluralia ; deux constats refusés ci-dessous.

**Refusé, par écrit** :

- `SortChoice` garde son paramètre `bool $hidden` : c'est un objet `readonly`, et PHP 8.4 n'a pas de
  `clone with` ; `hide()` est le seul appelant qui le passe ;
- la vue `sort` compte les options masquées (`count($choices) > 1`), si bien qu'un projet dont le seul tri
  filtre afficherait un menu réduit à « Pertinence » : ne compter que les visibles empêcherait le client
  de faire apparaître l'option quand une recherche lui rend des résultats, puisque le menu ne serait pas
  rendu. Masquer tout le menu serait un comportement visible nouveau, à trancher par Louis ;
- `SortComponentTest` s'ignore sur un hôte sans filtre de prix : PHPUnit le signale comme ignoré, et lier
  un autre `ProductFacets` testerait un listing qui n'est pas celui de l'hôte.

**Vérifié après ces changements, le 2026-09-17**, `composer check` vert, suite `Modules` verte (374 tests),
paquet reconstruit et publié, dans le navigateur (Playwright, touches envoyées par `dispatchEvent`) :
`/boutique`, Nord Sel cochée puis « Appliquer » → « Promotions » masquée, comptes « 1 résultat »,
« 0 résultat » ; menu ouvert à la flèche, `End` s'arrête sur « Nouveautés », `Home` revient sur
« Pertinence », `n` va sur « Nouveautés » ; aucune erreur dans la console.

---

### R-130 · 🟠 · **fermé le 2026-09-16** · ouvert le 2026-09-16 — `price.onsale` suit le badge, pas le panier

Préalable de l'option « Promotions » (décision du 2026-09-16, `decisions.md`), comme l'intervalle de
prix l'a été du filtre (`D-b`). `ProductPriceProjector` indexe `is_on_sale()`, qui lit les dates de promo
à la volée ; la décision retient le prix **réellement facturé** — le drapeau figé de WooCommerce, prix promo
renseigné et égal au prix courant (`class-wc-product-data-store-cpt.php`, colonne `onsale`), qu'appliquent
aussi ses propres listes « en promotion » (`wc_get_product_ids_on_sale()`, Store API `on_sale`).

Mesuré sur Pluralia :

| définition | produits |
| --- | --- |
| drapeau figé, produits eux-mêmes | 16 |
| + parents dont une variation visible est en promo | 20 |
| `is_on_sale()`, l'index actuel | 21 |

L'écart restant est le lot groupé #444, dont un enfant est remisé : **tranché par Louis, un lot n'est pas
en promotion**, comme dans les listes de WooCommerce.

Le test actuel, `it_follows_woocommerce_on_whether_a_product_is_on_sale`, compare au `is_on_sale()` que
la projection appelle elle-même : il ne peut pas échouer (`R-102`), et son docblock énonce la règle
rejetée.

**Corrigé.** `ProductPriceProjector::isBilledOnSale()` : pour un produit simple ou externe, la formule de la
colonne `onsale` — `wc_format_decimal()` des deux côtés, prix promo non vide et égal au prix courant —
**lue sur les metas**, pas dans la table de correspondance, que WooCommerce rafraîchit après avoir écrit
`_price` et donc après l'indexation qu'elle déclenche ; pour un produit variable, la même formule sur ses
variations visibles (l'ensemble de `sync_price()`, donc celui de `price.min`/`max`), leurs metas chargées
en une requête ; pour un lot groupé, jamais.

**Tests réécrits** : la référence est désormais la liste de WooCommerce lui-même
(`get_on_sale_products()`, produits et parents des variations) — l'ancien code échoue sur #444 ; et un
produit construit dans la fenêtre de `R-112`, prix promo renseigné mais prix courant resté plein :
`is_on_sale()` vrai, projeté hors promotion.

Réindexé, puis mesuré sur le moteur : `price.onsale` → `{"true": 20, "false": 56}`.

**Passes.** Lisibilité : deux méthodes privées, trois cas nommés. Commentaires : deux lignes, la règle
de la plateforme et la raison de lire les metas. Performance : une requête de metas par produit variable
indexé, aucune pour les autres. Sécurité : aucune entrée extérieure. Contexte : WooCommerce absent,
`project()` rend déjà `[]` avant.

**Page vérifiée le 2026-09-17** par `curl` : `/boutique?sort=on_sale` rend 16 cartes sur deux pages,
`/boutique/page/2?sort=on_sale` les 4 dernières — les 20 produits que le moteur compte en promotion.

---

### R-129 · 🟡 · **fermé le 2026-09-16** · ouvert le 2026-09-16 — en mode `immediate`, chaque flèche lance une recherche

`PriceControl::#stepped()` déplace la poignée **et** valide à chaque `keydown`. En mode `immediate`, dix
appuis — ou une flèche maintenue, qui répète le `keydown` — donnent dix recherches et dix réécritures
d'URL. Les recherches périmées sont annulées (`SearchSuperseded`), donc la grille reste juste ; le coût
est dans le moteur, et dans `history.replaceState`, dont Safari limite le débit.

**Ce que fait la plateforme.** Le curseur du bloc WooCommerce bouge à chaque `input` et ne filtre qu'au
relâchement : `data-wp-on--keyup="actions.navigate"`, comme `mouseup` et `touchend`
(`ProductFilterPriceSlider.php:131,143`).

**Tranché par Louis le 2026-09-16 : au relâchement de la touche, comme WooCommerce** (`decisions.md`).

**Corrigé** : `#stepped()` ne fait plus que déplacer la poignée au `keydown` ; `#steppedOff()` valide au
`keyup`, et seulement pour une touche qui déplace quelque chose — relâcher `Shift` ou `Tab` ne valide rien.
Une flèche maintenue ne valide qu'une fois. Le mode `submit` n'en est pas changé pour le visiteur : la
recherche y attend toujours « Appliquer ».

**Vérifié dans Chrome, sur le code publié, mode `submit`** : dix `keydown` amènent la poignée haute à 189 ;
« Appliquer » sans relâchement n'écrit rien ; après le `keyup`, « Appliquer » écrit `?max_price=189`.

**Non vérifié en navigateur, et pourquoi** : le comptage des recherches en mode `immediate`. La
description a bien été basculée en `immediate` par interception de la réponse, mais l'entrée clavier de
CDP (`Input.dispatchKeyEvent`) n'atteint pas la page dans Chrome headless — la poignée ne bouge pas, même
focalisée — et le navigateur Playwright est resté bloqué après les interceptions. Le chemin est le même
`#commitFields()` → `Listing::priceBetween()` → `#byMode()` que les autres gestes, et deux tests JS tiennent
le changement : poignée déplacée à chaque appui sans validation, validation unique au relâchement ; rien
validé au relâchement d'une touche qui ne déplace rien. Les tests existants passent désormais par
`stroke()` (appui puis relâchement), ajouté à `dom.js` avec `release()`.

**Vérifié le 2026-09-17 dans le navigateur (Playwright), mode `immediate`** : `apply_mode` passé en
`immediate` dans `config/meilifacets.php` de Pluralia le temps de la mesure, puis remis à `submit`. Dix
`keydown` sur la poignée haute l'amènent de 199 à 189 sans aucune requête `multi-search` ni changement
d'adresse ; le `keyup` en envoie une seule et écrit `?max_price=189`. Les touches passent par
`dispatchEvent` : les événements clavier de Playwright n'atteignent toujours pas la poignée.

**Passes.** Lisibilité : un écouteur et une méthode de trois lignes. Commentaires : une ligne, la
répétition du `keydown` sur une touche maintenue. Performance : une recherche par relâchement au lieu d'une
par répétition. Sécurité : aucune. Contexte : aucun.

---

### R-128 · 🟡 · **fermé le 2026-09-16** · ouvert le 2026-09-16 — le style du prix visait des classes, et une vue surchargée le perdait

`decisions.md` : « la règle s'accroche à `data-meili`, jamais aux classes : ce sont les crochets qu'un
thème garde en surchargeant une vue ». Tout le CSS du prix visait `.meilifacetsRange*` et
`.meilifacetsPrice*`. Un thème qui surcharge `components/price/range.blade.php` avec ses propres classes
— ce que `configuration.md` documente depuis `R-113` — obtenait une piste sans hauteur, des poignées
dans le flux et une bulle toujours visible. C'est le défaut de `R-93`, pour le prix.

L'amendement du 2026-09-07 (« ce que le module rend, il l'habille ») ne l'autorisait pas : il dit que
le module livre l'apparence de ce qu'il fabrique, pas qu'il la livre sur des classes. La liste de tri,
l'autre contrôle fabriqué, est d'ailleurs stylée par ses crochets.

**Tranché par Louis le 2026-09-16** : le contrôle sur ses crochets, la mise en page sur ses classes —
voir `decisions.md`. Aucun crochet ajouté. Le remplissage est un dégradé sur `price-track` ; sa `div`,
qui n'avait pas de crochet, est retirée de la vue.

**Tests** : `tests/js/price-stylesheet.test.js`, sur le modèle de `R-93` — le même contrôle rendu avec
les classes du module, puis avec celles d'un thème. Rail, poignées et bulles comparés propriété par
propriété ; chiffres tabulaires vérifiés sur chaque crochet. Les trois échouaient avant la réécriture.
Une première version du troisième passait **pour une mauvaise raison** : happy-dom n'hérite pas
`font-variant-numeric`, donc la comparaison était vide des deux côtés — réécrite pour vérifier la
valeur sur l'élément.

happy-dom ne calcule pas `linear-gradient` : le remplissage est vérifié dans Chromium. Plage 40–120 €,
puis poignée haute tirée à 159 € — rendu identique à l'avant, bulle comprise.

**Passes.** Lisibilité : les règles du contrôle forment un bloc, les rangées un autre. Commentaires :
un en-tête ajouté puis retiré — il justifiait le choix, c'est la décision écrite ; restent deux raisons
techniques (la marge qui laisse la bulle s'ouvrir, la remise à zéro limitée à la boîte). Performance :
une `div` de moins par piste. Sécurité : sans objet. Contexte : un champ sorti de sa boîte garde son
contour de focus.

---

### R-127 · 🟡 · **fermé le 2026-09-16** · ouvert le 2026-09-16 — une piste d'un seul prix reste dessinée

Quand tous les produits filtrés ont le même prix entier — une marque à un seul produit à 20,00 € —
les bornes élargies valent 20–20. La piste est dessinée sans largeur : deux poignées superposées qui ne
peuvent aller nulle part, et deux champs qui n'acceptent que 20.

**Ce que fait la plateforme.** WooCommerce masque tout le filtre de prix dans ce cas :
`ProductFilterPrice.php:158` (`$min_range === $max_range` → wrapper `hidden`) et
`ProductFilterPriceSlider.php:45` (même test → rien n'est rendu). Un prix à 20,40 € donne 20–21 : la piste
a une largeur et reste dessinée.

**Corrigé des deux côtés, là où les bornes naissent** : `PriceFilter::boundsFrom()` et `PriceBounds.of()`
ne rendent aucune borne quand les extrémités élargies se rejoignent. Le bloc se masque alors par le
chemin déjà prévu pour « aucun résultat » — `hidden` côté serveur, `#receive()` côté client.

Vérifié dans Chromium sur `/categorie-produit/parfum`, où Avril n'a qu'un produit, à 38,00 € : cocher
Avril masque le bloc sans recharger ; décocher le réaffiche ; la même URL rendue par le serveur le masque
aussi ; et depuis ce rendu masqué — bornes vides dans le HTML — décocher Avril rend la piste à 19–199 €,
poignées aux extrémités. Un test PHP et un test JS : 20–20 masqué, 20–20,40 dessiné en 20–21.

**Passes.** Lisibilité : une condition de plus à l'endroit qui décide déjà qu'il n'y a rien à dessiner.
Commentaires : aucun ajouté. Performance : aucune. Sécurité : aucune. Contexte : une plage tenue dans
l'URL sur un listing à prix unique n'est plus modifiable depuis le bloc masqué ; « Tout effacer » reste
disponible — même limite chez WooCommerce.

---

### R-126 · 🟡 · **fermé le 2026-09-16** · ouvert le 2026-09-16 — le client écrit une borne de prix en notation exponentielle

`FilterExpression::number()` écrit une borne à quatre décimales au plus, sans exposant. `ListingQuery`
l'interpole telle que JavaScript la convertit en chaîne. Pour les prix courants, c'est la même chose ;
aux extrêmes, non :

| borne | serveur | client |
| --- | --- | --- |
| `0.0000001` | `0` | `1e-7` |
| `1e21` | `1000000000000000000000` | `1e+21` |

Meilisearch refuse `price.max >= 1e-7` : une URL `?min_price=0.0000001`, que `ListingState` accepte
parce que c'est un prix positif, rend la page côté serveur puis fait échouer la première recherche du
client. C'est la règle écrite en tête de `listing-query.js` qui est rompue.

**Corrigé** : `ListingState.boundTo()` écrit une borne comme `FilterExpression::number()` — quatre
décimales au plus, sans séparateur de milliers, jamais d'exposant — par un `Intl.NumberFormat` créé une
fois. Il sert au filtre **et** à l'URL : le parcours avait montré la même fuite dans l'adresse réécrite
par le client (`min_price=1e-7`), que le serveur relisait sans erreur mais qui donnait deux écritures à un
même état. Il vit à côté de `valuesTo()`, qui écrit déjà les valeurs de facettes dans l'URL.

Vérifié dans Chromium sur `?min_price=0.0000001&max_price=60`, puis Aeris coché : filtre envoyé
`price.max >= 0`, aucune réponse d'erreur du moteur, URL réécrite `?marque=aeris&min_price=0&max_price=60`.
Deux tests JS, filtre et URL.

**Passes.** Lisibilité : une méthode statique, deux appelants. Commentaires : une ligne, l'anomalie du
moteur. Performance : un formateur créé au chargement du module. Sécurité : la sortie ne contient que des
chiffres et un point. Contexte : le format est celui du moteur, pas celui de la langue du visiteur — c'est
voulu, la locale d'affichage reste l'affaire de `Money`.

---

### R-125 · 🟡 · **fermé le 2026-09-16** · ouvert le 2026-09-16 — une borne saisie peut croiser l'autre, et la plage ne filtre plus rien

Les poignées ne peuvent pas produire une plage inversée : `#moveTo()` range toujours la basse sous la
haute. Les champs, si : taper 100 dans « De » puis 20 dans « À » valide `min_price=100&max_price=20`.
Aucun produit ne chevauche un intervalle vide, la grille se vide, et rien ne dit pourquoi.

**Ce que fait la plateforme.** Le bloc de filtre de prix de WooCommerce ignore la borne qui croiserait
l'autre et garde la précédente (`product-filter-price.js`, `setPrice` : un minimum n'est pris que
`t < maxPrice`, un maximum que `t > minPrice`). Il écarte aussi une valeur hors de la piste — ce que le
module fait déjà, en la traitant comme une borne ouverte (`R-122`).

**Corrigé comme la plateforme** : une saisie qui croiserait l'autre borne n'est pas validée, et le champ
reprend ce qu'il affichait — `PriceControl` retient la dernière valeur écrite dans chaque champ
(`#fill()`), qu'elle vienne d'un `show()` ou d'une poignée. **Un écart assumé** : WooCommerce refuse
aussi l'égalité (`t < maxPrice`), le module l'accepte, parce que ses poignées peuvent déjà se rejoindre
sur un seul prix et que la saisie doit dire la même chose qu'elles.

Une URL inversée venue de l'extérieur reste servie telle quelle, vide : même limite que celle écrite
pour `R-122` — seules les URL que le module écrit sont canoniques.

Vérifié dans Chromium sur `?max_price=60` : 100 saisi dans « De » → champ revenu à 0, URL inchangée ;
20 saisi → `?min_price=20&max_price=60`. Trois tests JS : refus avec piste, refus sans piste (le champ
revient vide), égalité acceptée.

**Passes.** Lisibilité : l'écriture des champs passe par un seul `#fill()`, qui retient ce qu'il écrit ;
`#write()` s'y réduit. Commentaires : aucun. Performance : une comparaison à la validation. Sécurité :
aucune. Contexte : aucun.

---

### R-124 · 🟡 · **fermé le 2026-09-16** · ouvert le 2026-09-16 — un listing sans filtre de prix lit quand même la plage dans l'URL

`StateReader` (PHP) et `ListingUrl::toState()` (JS) lisent `min_price`/`max_price` quel que soit le
listing. Sur un listing qui ne déclare aucun `PriceFilter`, `QueryPlan` n'écrit aucune clause de prix
— mais l'état porte la plage : le listing n'est plus « vierge », passe en `noindex`, affiche « Tout
effacer », et depuis `R-123` compterait un filtre actif. Une URL `?min_price=20` fait donc tout cela
sans filtrer quoi que ce soit.

`CLAUDE.md` § 4 le demande explicitement : rien de ce qui dépend du prix ne doit agir quand le listing
ne le déclare pas (`R-09`).

**Corrigé des deux côtés** : `StateReader` ne lit la plage que si le listing déclare un `PriceFilter`,
`ListingUrl` que si la description porte `priceFields` — ce que le serveur ne publie que dans ce cas.

La recherche du `PriceFilter` parmi les filtres d'un listing existait en deux boucles identiques
(`QueryPlan`, `ListingDescription`) et en aurait eu une troisième. Elle vit désormais sur la classe :
`PriceFilter::among()` pour qui veut le filtre, `isDeclaredAmong()` pour qui ne veut qu'un oui ou un
non — ce second nom évite l'`! … instanceof` que Rector impose sur une comparaison à `null`. Le contrat
`Listing`, qu'un projet implémente, n'a pas bougé.

Vérifié : `/boutique?min_price=20&max_price=30`, listing qui déclare un prix, lit toujours la plage
(badge « 1 filtre actif », champs 20 et 30). Aucun listing du site ne déclare **pas** de prix : ce cas
est porté par deux tests PHP (sans prix → plage vide, listing vierge, zéro filtre ; avec prix → plage
lue) et un test JS.

**Passes.** Lisibilité : `StateReader::read()` délègue la plage à `price()`, et deux boucles sont
remplacées par une recherche nommée. Commentaires : aucun ajouté. Performance : une boucle courte sur les
filtres déclarés, une fois par lecture d'état. Sécurité : moins d'entrée lue, pas plus. Contexte : c'est
le point — rien du prix n'agit sans déclaration.

**Vérifié le 2026-09-17 dans le navigateur (Playwright)** : `/boutique?min_price=20&max_price=30` → « 1 filtre
actif », champs à 20 et 30.

---

### R-123 · 🟡 · **fermé le 2026-09-16** · ouvert le 2026-09-16 — une plage de prix ne compte pas parmi les filtres actifs

Reproduit dans Chromium : `?min_price=60&max_price=199` appliqué, le badge dit « 0 filtres actifs »
pendant que « Tout effacer » s'affiche. `ListingState::activeFilterCount()`, en PHP comme en
JavaScript, ne compte que les valeurs de facettes cochées ; `isPristine()` inclut pourtant le prix.
Le visiteur voit donc un bouton pour effacer des filtres que le compteur dit absents.

Nommer la plage (« jusqu'à 25 € ») reste l'affaire de `R-47`. Ici, il s'agit de la compter.

**Corrigé des deux côtés** : une plage compte pour **un** filtre, qu'elle tienne une borne ou deux — comme
WooCommerce la présente en un seul libellé. Depuis `R-122`, une borne posée au bord n'entre plus dans
l'état : le compteur ne peut donc pas compter une plage qui ne filtre rien.

Vérifié dans Chromium : plage seule → « 1 filtre actif » ; plage et Aeris → « 2 filtres actifs » ; la
même URL rendue par le serveur → « 2 filtres actifs ». Un test PHP et un test JS, sur les trois formes :
deux bornes, une seule, et avec des facettes.

**Passes.** Lisibilité : une expression de chaque côté. Commentaires : le commentaire JS disait « seules
les valeurs cochées comptent », devenu faux, réécrit. Performance : aucune. Sécurité : aucune. Contexte :
un listing qui ne déclare aucun prix lit pourtant `min_price` dans l'URL et le compterait — défaut
antérieur, point suivant.

---

### R-122 · 🟡 · **fermé le 2026-09-16** · ouvert le 2026-09-16 — une poignée laissée au bord écrit quand même sa borne dans l'URL

Reproduit dans Chromium : poignée haute ramenée de 199 à 198 au clavier, appliquer → l'URL devient
`?min_price=0&max_price=198`. Le `min_price=0` ne filtre rien, puisque 0 est le bord de la piste.
« Pas de minimum » a donc deux URL — `?max_price=198` et `?min_price=0&max_price=198` — alors que
`listing-state.js` pose qu'un état n'en a qu'une, sans quoi un cache le garde deux fois. Et une piste
laissée entière au bord (0–199) écrit une plage qui ne filtre rien du tout.

**Ce que fait la plateforme.** Le bloc de filtre de prix de WooCommerce retire la borne qui repose sur
le bord (`price-filter-frontend.js` : `r >= max ? undefined : r`, `e <= min ? undefined : e`, puis
suppression du paramètre). Ce bord suit la même mesure que le reste du filtrage : il bouge quand une
autre facette change.

**Tranché par Louis le 2026-09-16 : retirer, comme WooCommerce.** Voir `decisions.md`.

**Corrigé** dans `PriceControl::#asked()`, seul endroit qui connaît les bornes au moment de valider :
une borne égale au bord — ou au-delà — part comme `null`. Sans piste dessinée, les bornes se lisaient
sur les poignées absentes et valaient 0–0, ce qui aurait fait de toute saisie une borne « au bord » :
elles se lisent désormais sur les `min`/`max` que le serveur pose sur les champs, et restent ouvertes
quand rien ne les dit.

Vérifié dans Chromium : poignée haute à 198 → `?max_price=198` ; basse à 1 → `?min_price=1&max_price=198` ;
les deux ramenées au bord → URL vide ; saisie de 199 dans « À » → URL vide ; sous Aeris, poignée haute
poussée à 47 → `?marque=aeris`. Trois tests JS, dont le mode champs seuls ; un test existant attendait
`[0, 89]` et attend désormais `[null, 89]`, ce qui est exactement le changement.

**Passes.** Lisibilité : `#commitFields()` délègue à `#asked()`, 10 lignes ; `#number()` factorise la
lecture tolérante des deux attributs. Commentaires : une ligne, la raison des bornes ouvertes. Performance :
une comparaison par borne à la validation. Sécurité : la valeur saisie reste relue par `ListingState`,
qui refuse ce qui n'est pas un prix. Contexte : aucun.

---

### R-121 · 🟠 · **fermé le 2026-09-16** · ouvert le 2026-09-16 — le client ne remesure jamais les bornes de prix

Reproduit dans Chromium : sur `/boutique`, cocher Maison Solaire puis appliquer. La grille se
filtre, mais la piste reste à **0 – 199 €**. Rechargée, la même URL affiche **43,40 – 199 €** : le
serveur mesure, le client non.

Le serveur fait une recherche de bornes qui lève la contrainte de prix (`QueryPlan::priceBounds()`,
`measuresPriceApart()`, clé `bounds`, `R-111`), et sinon demande les champs de prix sur la recherche
principale. `ListingQuery` ne fait ni l'un ni l'autre : `PriceControl` lit ses bornes **une fois**,
dans les `aria-valuemin`/`aria-valuemax` du rendu serveur. Dès le premier geste, la piste, la ligne
des bornes, les limites des champs et le `hidden` du bloc ne suivent plus le filtrage. C'est la règle
écrite en tête de `listing-query.js` qui est rompue : « the same state must produce the same searches
on both sides, or the grid contradicts itself between render and first click ».

**Corrigé, en miroir du serveur.**

- `ListingQuery` ajoute la recherche `bounds` dès qu'une plage est tenue, contrainte de prix levée, et
  demande sinon les champs de prix sur la recherche principale — mêmes clés, mêmes tableaux `facets`
  dans le même ordre que `QueryPlan` et `ListingSearch` ;
- `PriceBounds` lit les `facetStats` dans la réponse qui les a mesurées, élargies à l'unité entière
  comme `PriceFilter::boundsFrom()` depuis `R-119` — sur le modèle de `FacetCounts` ;
- `PriceControl::showBounds()` reçoit ces bornes : limites des poignées, ligne sous la piste, limites
  des champs visibles, `hidden` du bloc. `show()` ramène une borne tenue dans les bornes, comme
  `Price::effective()` côté serveur.

La ligne sous la piste n'avait aucun crochet. Deux s'ajoutent, **`price-bounds-min`** et
**`price-bounds-max`**, nommés sur `$bounds` côté serveur. Un premier jet les appelait `price-floor` et
`price-ceiling`, mais ces mots désignent déjà, dans `RangeHandle`, les limites d'**une** poignée : les
laisser entrer dans le contrat aurait figé deux sens pour un nom. `Contract::VERSION` reste à 1 : un
crochet ajouté n'incrémente pas (`R-116`).

Vérifié dans Chromium sur le code publié : Maison Solaire → piste et champs à **43 – 199 €** sans
recharger ; plage posée dessous → bornes inchangées ; marque retirée → **0 – 199 €**, plage conservée.

**Les cinq passes** (revue déléguée), et ce qui en a été fait :

- *Lisibilité* — nommage revu avant commit (ci-dessus) ; la constante `BOUNDS` de `PriceControl`, qui
  croisait `ListingQuery.BOUNDS`, devient `ENDS` et `#reachable` devient `#bounds` ; la recherche de
  bornes partage désormais la jointure `#joined()` des autres ; `page: 1` littéral remplacé par
  `FIRST_PAGE`, exporté de `listing-state.js` (et repris dans `#counting`, qui avait le même
  littéral) ; `showBounds()` délègue à `#receive()`, qui seul écrit le `hidden` et les bornes ; les
  champs `type="hidden"` ne reçoivent plus de `min`/`max`/`placeholder` que le serveur ne leur donne
  pas ; `state.selected()` n'est plus appelé deux fois par facette.
- *Commentaires* — cinq ajoutés au premier jet, cinq supprimés : deux justifiaient `R-111`/`R-119`,
  deux redisaient le nom d'un test ou d'une aide, et deux `@typedef` recopiaient des formes déjà
  annotées, désormais importées. Deux lignes ajoutées ensuite, conformes à la table : le report d'une
  réponse sous une poignée tenue (contournement), la réécriture d'un `aria-valuetext` identique qu'un
  lecteur d'écran peut annoncer de nouveau (anomalie amont).
- *Performance* — `showBounds()` réécrivait tout à chaque réponse, et `show()` tournait deux fois par
  clic : il ne repeint plus que si les bornes ont bougé, et un attribut n'est écrit que s'il change.
  Ce qui s'ajoute est inévitable : sans plage tenue, la recherche principale demande les deux champs
  de prix, seule façon d'obtenir `facetStats` — le serveur fait de même.
- *Sécurité* — rien : aucun nombre de l'URL dans la recherche de bornes, valeurs du moteur filtrées
  par `typeof`, écrites en `textContent`.
- *Contexte* — **un vrai défaut introduit, corrigé** : une réponse arrivée pendant qu'on tient une
  poignée la ramenait au dernier état validé, sous le doigt. Elle est désormais mise en attente et
  appliquée au relâchement. Deux tests : poignée intacte pendant la tenue, bornes prises au relâchement.
  Et le branchement `#repaint` → `showBounds` n'était couvert par rien — supprimer la ligne laissait la
  suite verte ; un test de `ListingBinding` le tient.

**Constats de la revue laissés tels quels, avec leur raison :**

- une borne tenue hors des nouvelles bornes s'affiche ramenée, et un geste suivant la valide telle
  quelle — le serveur rend déjà la même valeur, et c'est le coût que Louis a accepté en tranchant
  qu'une borne posée au bord ne filtre pas (point suivant) ;
- sur une boutique TTC, cette borne ramenée est un prix HT que le serveur relit comme TTC — dormant ici
  (`woocommerce_calc_taxes = no`), traité avec la décision sur les taxes — sans objet depuis `R-146` : bornes
  et prix indexé sont dans l'unité affichée ;
- une vue de prix surchargée avant ce changement n'a pas les deux crochets : sa ligne sous la piste
  reste figée, sans signal — coût accepté par `R-116`, les crochets restent optionnels ;
- deux blocs prix dans une même racine : seul le premier est branché — limite antérieure (`R-95`,
  `R-48`).

La vérification navigateur du premier jet avait porté sur une copie publiée **avant** la dernière
retouche, et la suite `Modules` était rouge pour cette raison : l'ordre est désormais publier, puis
tester, puis ouvrir le navigateur.

**Revérifié le 2026-09-17 dans le navigateur (Playwright), sur le code publié** : sur `/boutique`, Maison
Solaire cochée puis « Appliquer » → poignées et champs à `43–199` sans rechargement ; la même adresse
rechargée rend `43–199`.

---

### R-120 · 🟡 · **refermé le 2026-09-16** · rouvert et ouvert le 2026-09-16 — la bulle de valeur disparaissait pendant le glissé

La bulle d'une poignée ne s'affichait qu'au survol ou au focus clavier. Dès qu'on tire, le pointeur
quitte la poignée : plus de survol, et un clic souris ne donne pas `:focus-visible`. La valeur
restait donc invisible **pendant** le glissé — le seul moment où on la cherche des yeux. Mesuré dans
Chromium : `opacity: 0` au milieu d'un glissé.

**Corrigé en CSS seul**, sans attribut d'état ni changement de contrat. Un élément reste `:active`
tant que le bouton est enfoncé, même quand le pointeur s'en éloigne et que la piste a capturé le
pointeur — vérifié à 40 px sous la poignée : `:active` vrai, `:hover` faux, bulle à `opacity: 1`
affichant la valeur courante. Le module utilisait déjà `:active` comme état de glissé
(`cursor: grabbing`) ; la bulle et le grossissement de la poignée suivent ce précédent.

**Pas de test automatisé, délibérément.** `happy-dom` ne simule pas `:active`, et un test qui
vérifierait la présence du sélecteur dans la feuille recopierait ce qu'il prétend vérifier — le
travers de `R-91`. La vérification est celle du navigateur, écrite ci-dessus.

**Passes.** Lisibilité : deux sélecteurs ajoutés à des règles existantes. Commentaires : aucun.
Performance : aucune règle nouvelle. Sécurité : sans objet.

**Rouvert le même jour — le correctif était faux au croisement.** La passe de conformité l'a signalé,
Chromium l'a confirmé : quand la poignée tirée dépasse l'autre, `#moveTo()` passe la main à cette
autre poignée, mais `:active` reste sur celle qu'on a pressée. Mesuré en tirant la basse au-delà de
la haute : pointeur à 80 %, poignée haute à 159 € **sans** bulle, poignée basse immobile à 59 € **avec**
bulle. L'affirmation précédente sur Safari iOS n'avait, elle, jamais été mesurée : retirée.

**Corrigé avec le précédent du module** : `data-active`, écrit par le client hors contrat, comme sur
l'option de tri (`decisions.md`, « `data-active` est écrit par le client… »). `PriceControl::#hold()`
le pose sur la poignée réellement tirée, le déplace au croisement, le retire au relâchement. La règle
CSS s'accroche désormais au crochet — `[data-meili="price-handle"][data-active]` — comme l'exige la
décision « la règle s'accroche à `data-meili`, jamais aux classes ». `:active` ne reste que pour le
curseur.

Vérifié dans Chromium, même geste : avant le croisement, bulle sur la basse (30 €) ; après, sur la
haute (159 €) et plus sur la basse ; relâché, aucune. Et cette fois testable : trois tests JS — la
marque posée sur la seule poignée tirée, déplacée au croisement, retirée au relâchement.

**Cinq passes (2026-09-17, sur le code à HEAD, correctif `data-active` compris).** *Lisibilité* :
`data-active` nommé deux fois, `Math.min`/`Math.max` doublés, aucun test de relâchement après croisement —
`R-145` (8). *Commentaires* : le commentaire du croisement (`price-control.ts:184-185`) justifie un choix —
`Q-31`. *Performance* : `pointermove` refait tout à chaque événement — `R-145` (8). *Sécurité* : rien, des
nombres écrits par `textContent` ou attribut. *Contexte* : une vue à une seule poignée basse ne glisse plus,
et un bouton autre que le principal laisse une poignée tenue — `R-142`.

---

### R-119 · 🟠 · **fermé le 2026-09-16** · ouvert le 2026-09-16 — la piste de prix arrondit ses extrémités, et perd le produit qui s'y trouve

Les bornes de la piste sont les `facetStats` brutes — 9,80 €, 43,40 €, 46,40 € — mais une poignée ne
se pose que sur un entier (`Math.round` dans `PriceControl::#moveTo()`). Pousser la poignée haute au
bout sur une borne à 46,40 € envoie donc `46`, et le produit à 46,40 € disparaît du filtre qui
prétend tout couvrir. Même chose en bas : une borne à 43,40 € devient 43, ce qui ne perd rien, mais
une borne à 9,80 € arrondie à 10 perd le produit à 9,80 €.

**Ce que fait la plateforme.** WooCommerce arrondit les bornes **vers l'extérieur**, jamais les
poignées : `floor` pour le minimum, `ceil` pour le maximum — à l'entier dans le bloc
(`ProductFilterPrice.php:219-220`), au pas dans le widget (`class-wc-widget-price-filter.php:111-112`).
La piste couvre alors toujours les prix extrêmes, et une poignée entière ne peut pas en sortir.

**Corrigé à la source**, dans `PriceFilter::boundsFrom()` : `floor` sur le minimum, `ceil` sur le
maximum. Le client lit ses bornes dans les `aria-valuemin`/`aria-valuemax` que le serveur rend, donc
une correction suffit aux deux. Les poignées gardent leur pas entier ; c'est la piste qui s'élargit.

Vérifié dans Chromium sur `?marque=aeris` (prix de 9,50 à 46,40 €) : piste **9 – 47 €**, poignées
ramenées au bout par `Home` et `End` puis appliquées, les 10 produits restent, dont les deux
extrêmes. Un test : 9,8 → 9 et 46,4 → 47 ; le test existant passe de 4,5 à 4.

⚠️ Quand le client remesurera lui-même ses bornes (point suivant du lot), il devra arrondir **de la
même façon** : sinon la piste change de largeur entre le rendu serveur et le premier clic.

**Passes.** Lisibilité : une expression. Commentaires : aucun ajouté au code. Performance : deux
appels natifs par rendu. Sécurité : valeurs du moteur, typées `float`. Contexte : aucun.

---

### R-118 · 🟠 · **fermé le 2026-09-16** · ouvert le 2026-09-16 — une poignée de prix garde sa valeur périmée après une saisie ou une remise à zéro

Reproduit dans Chromium, au clavier seul : saisir `90` dans « À », revenir sur la poignée haute,
appuyer sur `←`.

| étape | position à l'écran | `aria-valuenow` | champs |
| --- | --- | --- | --- |
| saisie 90, `Tab` | 0,45 | **199** | 0 / 90 |
| `←` sur la poignée haute | **0,99** | 198 | **0 / 198** |

La poignée se déplace à l'écran mais croit valoir 199 : la flèche repart de là et **écrase la
saisie**. Même chose après « Tout effacer » : poignées revenues aux extrémités à l'écran, annoncées
à leur ancienne valeur. Un lecteur d'écran entend donc une valeur que la page n'affiche pas.

Cause : `PriceControl::show()` repeint la position, le remplissage et la lecture, mais n'appelle
jamais `#showBound()`, seul endroit qui écrit `aria-valuenow`, `aria-valuetext` et la bulle. Aucun
test ne regardait ces attributs après un `show()`.

**Corrigé.** `show()` et `#moveTo()` passent désormais par `#describe()`, qui écrit la valeur, son
texte, la bulle **et** les limites de chaque poignée — `aria-valuemax` de la basse suit la haute,
`aria-valuemin` de la haute suit la basse, ce que le rendu serveur posait déjà et que le client
laissait se périmer. L'écriture des champs est séparée (`#write()`) : sans piste, un champ vide
signifie une borne ouverte et `show()` ne doit pas y écrire un nombre.

Vérifié dans Chromium avec le même parcours : saisie 90, `←` → **89** ; « Tout effacer » → poignées
annoncées 0 et 199. Quatre tests JS : la valeur annoncée après `show()`, la flèche qui repart de la
saisie, les extrémités après remise à zéro, les limites croisées.

**Passes.** Lisibilité : `#showBound()` faisait deux choses (décrire la poignée, écrire le champ),
scindé ; `#describeHandle()` fait 20 lignes, dont huit d'écriture d'attributs sans branche. Commentaires :
aucun ajouté. Performance : quatre `setAttribute` de plus par déplacement, sur deux éléments déjà
en main, sans requête. Sécurité : valeurs numériques issues de l'état, écrites par `setAttribute` et
`textContent`, jamais en HTML. Contexte et i18n : les montants passent par `Money`, comme avant.

---

### R-117 · 🔴 · **fermé le 2026-09-15** · ouvert le 2026-09-15 — la garde des paramètres refusait les défauts que le module s'était choisis

`meilifacets:check-parameters` sortait en échec sur `min_price` et `max_price` : deux noms que
`ReservedParameters` interdit, et que `D-h` a pourtant retenus comme défauts. Le lot ne pouvait pas
passer une porte de déploiement qui lance cette commande, et les deux règles ne pouvaient pas tenir
ensemble.

**Ce qui reste réellement nuisible, mesuré le 2026-09-15** — avec `NativeFiltering` en place :

| | état |
| --- | --- |
| WooCommerce filtre la requête principale en parallèle | **non** — `apply_filters('woocommerce_enable_post_clause_filtering', true, null)` rend `false` sur le site |
| `is_filtered()` appelé par WooCommerce lui-même | **jamais** — zéro occurrence hors sa définition (`wc-conditional-functions.php:341`) |
| widget de filtre de prix posé | **aucun** — `widget_woocommerce_price_filter` est vide, seules sidebars : `footer-widget`, `contact-widget` |

Dommage résiduel aujourd'hui : nul. Mais le fil reste sous tension — poser un widget de prix
WooCommerce, ou installer une extension qui appelle `is_filtered()`, le réveille sans un mot.

**Tranché par Louis** : garder les noms et apprendre la mitigation à la garde, plutôt que renverser
`D-h`. `ReservedParameters::acceptedFor()` distingue désormais une collision **assumée** d'une
collision subie, et la commande avertit au lieu d'échouer.

L'acceptation porte sur le **défaut**, pas sur le nom : elle ne vaut que tant que la borne répond
encore à `min_price`/`max_price`. Un projet qui renomme une borne perd la compatibilité des liens
WooCommerce **et** l'excuse — si une taxonomie vient alors prendre le nom libéré, la commande
rebloque. Deux tests le tiennent.

Ce que ça coûte, et qu'il faut se rappeler : la garde ne protège plus ces deux noms. C'est
exactement l'exception que `D-h` disait vouloir éviter en refusant de les figer ; elle est ici
assumée dans l'autre sens, avec sa raison écrite à l'endroit où la commande la répète.

---

### R-116 · 🟡 · **fermé le 2026-09-15** · ouvert le 2026-09-15 — la règle du contrat mettait l'ajout d'un crochet sur le même plan que son retrait

`Contract.php:19` disait : « Incremented whenever a hook is **added**, renamed or removed ». Le lot
prix ayant ajouté sept cas à `Hook`, j'ai incrémenté `Contract::VERSION` à `2`. **Louis a renversé**
l'incrément le jour même, et il a raison :

- **un ajout est additif.** Une surcharge de thème écrite en v1 ne rend pas le composant prix : rien
  de ce qu'elle a copié ne cesse de fonctionner ;
- **l'incrément achetait une panne plus large que celle qu'il évitait.** Un client ancien face à des
  crochets nouveaux se contente de ne pas piloter le prix ; refuser de démarrer dégrade **tout** le
  listing en rendu serveur, pour protéger une partie que le thème ne rendait pas ;
- et le module est en cours de développement : rien n'est encore déployé qu'un numéro protégerait.

Un renommage ou un retrait restent l'inverse : là, la surcharge ancienne adresse du vide, et le refus
de démarrer est exactement ce qu'on veut.

**Corrigé** : `VERSION` revient à `1` des deux côtés, et la règle écrite dans `Contract.php` distingue
désormais l'ajout du renommage — c'est elle qui m'a induit en erreur, pas une inattention. Même
distinction portée dans `architecture.md`.

Deux défauts trouvés en chemin, qui tiennent indépendamment de l'incrément et sont **gardés** :

- `tests/js/price-control.test.js` figeait `data-meili-contract="1"` à la main, là où `dom.js` et
  `contract.test.js` lisent la version **dans la source du client** précisément pour qu'un incrément
  n'envoie personne éditer des fixtures. `dom.js` exporte désormais `CONTRACT`, et le fichier du prix
  l'utilise : le prochain incrément, quand il viendra, ne touchera aucune fixture ;
- `PublishedAssetsTest::it_names_a_copy_left_behind_by_its_source` était fragile par construction : il
  reculait la date de la copie publiée de 60 secondes en espérant passer sous celle de la source. Après
  n'importe quel `module:publish`, toutes les copies sont neuves et ce pas fixe ne suffit plus — le test
  échouait sans qu'aucun code de production ne soit en cause. Il se date maintenant sur la source.

Et le tableau des crochets d'`architecture.md`, qui ignorait les sept du prix, les énumère.

---

### R-115 · 🟡 · ouvert · 2026-09-15 — le symbole monétaire est reconstruit sept fois par rendu

`Money::symbol()` n'est pas mémoïsé, et sous lui `get_woocommerce_currency_symbol()` rebâtit un
tableau d'environ 160 entrées puis relance `apply_filters('woocommerce_currency_symbols')` à chaque
appel (`public/content/plugins/woocommerce/includes/wc-core-functions.php:531`). `wc_price()`
l'appelle lui aussi (`wc-formatting-functions.php:647`).

Comptage pour une facette prix montrant curseur **et** champs : 2 appels depuis la boucle de
`components/price/fields.blade.php`, 4 par `wc_price()` — les deux bornes de `range.blade.php` et
les deux poignées via `RangeHandle::written()` — et 1 par `ListingDescription.php:43`. Soit **sept
constructions du tableau pour une page**, là où un mémo sur `Money` en laisse une.

Le défaut précède le passage en composants anonymes ; il a été relevé en le faisant. Non corrigé
parce que la mémoïsation suppose que la devise ne change pas en cours de requête, ce qui est vrai
sur ce projet mais pas garanti sous un plugin multi-devise — à trancher, pas à rustiner.

**Au 2026-09-22** (passe documentaire, `R-153`) : toujours vrai ; la ligne citée pour `ListingDescription`
est aujourd'hui `:52`.

---

### R-114 · 🟡 · ouvert · 2026-09-15 — deux façons de nommer un crochet, et le clivage est structurel

Depuis le passage des sous-vues du prix en composants anonymes, le module a deux écritures pour
émettre un crochet :

```blade
{{ $hook('price-range') }}                 {{-- 11 vues, 27 sites d'appel --}}
{{ Hook::PriceRange->attribute() }}        {{-- components/price/range.blade.php --}}
```

Ce n'est pas un oubli : **un composant anonyme ne peut pas atteindre `$hook()`**, qui est une
méthode de `ContractComponent`. Le clivage se répétera donc à chaque sous-vue extraite ensuite.

Les deux formes sont équivalentes au rendu et aucune n'introduit de chaîne littérale — `$hook()`
fait `Hook::from($name)`, qui lève sur un nom inconnu (`R-17`), là où le cas d'énumération est
vérifié à la compilation. La seconde est même la plus sûre des deux.

À trancher sous ce numéro plutôt que vue par vue : soit `$hook()` disparaît au profit de l'enum
partout, soit il reste et les composants anonymes reçoivent leurs crochets en props.

**Au 2026-09-22** (passe documentaire, `R-153`) : toujours vrai ; le décompte a bougé — 29 appels à
`$hook(` dans 9 vues.

---

### R-113 · 🟡 · **fermé le 2026-09-15** · ouvert le 2026-09-15 — les vues du prix vivaient là où le README ne promet rien

`README.md:88-91` promet : « poser un fichier dans `<thème>/resources/views/modules/meilifacets/
**components/**` suffit à en remplacer une ». C'était faux pour trois vues du lot prix, qui vivaient
dans `resources/views/price/` et n'étaient donc surchargeables qu'à `.../modules/meilifacets/price/`,
chemin qu'aucun document ne mentionne.

Elles étaient tirées par `@include`, les trois seuls du module, et lisaient la portée implicite du
composant parent — six noms (`$handles()`, `$parameter()`, `$shown()`, `$bounds()`, `$hook()`,
`$money`) qu'aucune ne déclarait. Un thème voulant en remplacer une devait les deviner.

**Corrigé** : elles sont devenues des composants anonymes sous `resources/views/components/price/`,
chacun ouvrant sur `@props`. `parameter` et `shown` sont descendus sur `RangeHandle` — ils prenaient
le même contexte à chaque appel — de sorte qu'aucune vue ne reçoit de fermeture en prop. Les trois
points de surcharge sont désormais écrits dans `configuration.md`.

Vérifié sur la page réelle, pas seulement en test : `/boutique` rend les huit crochets prix,
`name="min_price" value="0" min="0" max="199"`, bornes 0,00 € – 199,00 €.

Deux défauts trouvés en fermant, et corrigés dans la foulée :

- `PriceComponentTest::render()` faisait `request()->merge($query)`, donc chaque test lisait les
  paramètres du précédent. Un test attendant un champ vide recevait le `min_price=55` d'un test
  antérieur. Remplacé par `replace()`.
- le chemin « aucun résultat » n'était couvert par aucun test de vue. Il l'est : `<fieldset>` masqué,
  la paire présente, les deux valeurs vides.

---

### R-112 · 🔴 · **fermé le 2026-09-15** · ouvert le 2026-09-15 — la bascule de promo change le prix sans que l'index le sache

**Défaut amont, dans MeiliScout**, sur le même garde que `R-109`. Trois faits se composent :

1. **WooCommerce ne passe pas par `save_post` pour une bascule de promo.**
   `wc_apply_sale_state_for_product()` ne change qu'une seule prop, `price`
   (`wc-product-functions.php:631`). `price` n'est pas dans la liste des onze props qui déclenchent
   `wp_update_post()` (`class-wc-product-data-store-cpt.php:327`) : la branche `else` écrit
   `post_modified` directement en base (`:369`). Le seul crochet que MeiliScout entende est
   `updated_post_meta`, atteint parce que WooCommerce écrit `_price` à la main
   (`wc-product-functions.php:638` et `:647`). Pour un produit variable c'est
   `deleted_post_meta` + `added_post_meta`, via `sync_price()`.

2. **La file Action Scheduler n'avance que sur une requête d'admin.**
   `maybe_dispatch_async_request()` teste `is_admin()`
   (`ActionScheduler_QueueRunner.php:141`), et Pollora désactive WP-Cron en dur
   (`Bootstrap.php:336`), donc l'autre chemin est mort tant qu'aucun cron système ne prend le
   relais : six visites de `/boutique` laissent l'action `pending`, un appel à `admin-ajax.php` la
   termine. Mesuré le 2026-09-15.

3. **Ce runner-là passe par `admin-ajax.php`, donc `DOING_AJAX`** — et
   `SingleIndexingServiceProvider::shouldSkipPostOperation()` (`:395-397`) renvoie `true` dessus,
   en silence.

**Mesure, produit 362 « Huile Régénérante Nuit », promotion programmée qui démarre :**

```
avant          _price=46.00   index price.min=46
appel admin-ajax.php?action=heartbeat  (aucun WP-CLI, aucun cron)
après          _price=32.00   index price.min=46     ← l'index ment de 14 €
```

La même bascule rejouée **hors** `DOING_AJAX` (WP-CLI) amène bien l'index à 32. Le garde est le
seul responsable.

**Portée, bien plus large que les promotions.** Vérifié dans le cœur de WordPress le 2026-09-15.
`DOING_AJAX` n'est qu'un drapeau de transport — un seul endroit le pose, `admin-ajax.php:16`. Le
garde ne sélectionne donc pas une catégorie de contenu, il sélectionne une catégorie de tuyau :

| Geste | Transport | Constante | Indexé |
| --- | --- | --- | --- |
| éditeur complet / blocs | REST | `REST_REQUEST` (`rest-api.php:466`) | oui |
| modification groupée | `edit.php:193` → `bulk_edit_posts()` | — | oui |
| modification rapide | `admin-ajax.php`, action `inline-save` | `DOING_AJAX` | **non** |
| déclinaisons d'un produit variable | `admin-ajax.php`, `woocommerce_save_variations` | `DOING_AJAX` | **non** |
| bascule de promo | `admin-ajax.php`, Action Scheduler | `DOING_AJAX` | **non** |

**Le cas le plus grave n'est pas la promotion, c'est la déclinaison.** `save_variations` et
`bulk_edit_variations` sont des actions AJAX de WooCommerce (`class-wc-ajax.php:206-207`,
enregistrées en `wp_ajax_woocommerce_*`) : **changer le prix d'une déclinaison depuis la fiche
produit ne met pas l'index à jour**. C'est le geste quotidien d'un gestionnaire de boutique, et
quatre produits de ce catalogue sont variables. Mesuré sur le Sérum Hydratant, déclinaison haute
passée de 62 € à 80 € dans le contexte AJAX :

```
base   parent min=28.00  max=80.00
index  min=28   max=62      ← inchangé
```

**Aucun découpage plus fin n'est défendable.** Filtrer sur la seule action `inline-save` laisserait
passer Action Scheduler et les déclinaisons, mais préserverait un défaut : le cœur montre qu'une
modification rapide est un enregistrement **ordinaire** — `wp_ajax_inline_save()`
(`ajax-actions.php:2061`) appelle `edit_post()` puis `wp_update_post()`, la même fonction que
l'éditeur complet. Et la référence officielle de `save_post` énumère les gardes recommandés
(révision, type de contenu, capacité, champs `$_POST`) **sans jamais mentionner `DOING_AJAX`**.

Le commentaire du garde dit « to avoid indexing during quick saves » : l'intention était le coût,
pas la justesse — et le coût se traite par l'indexation différée, pas par un refus d'indexer.

**Correctif, écrit le 2026-09-15 dans `/Users/louis/Sites/MeiliScout`, branche `feat/meilifacets`,
non commité.** `DOING_AJAX` n'est pas un motif de ne pas indexer ; il ne l'a jamais été. Le motif
recherché à l'origine — ne pas indexer un brouillon automatique — est déjà couvert par les trois
autres gardes, qui restent. Le garde est donc supprimé, et la raison écrite dans le docblock.

Trois fichiers : `src/Providers/SingleIndexingServiceProvider.php` (le garde),
`tests/Unit/Providers/SkipPostOperationTest.php` (trois tests, le premier tombe si on remet le
garde), et `tests/Pest.php` — deux fichiers de test déclaraient chacun leur propre `WP_Post` avec
des propriétés différentes, collision invisible tant qu'on ne les lance pas ensemble ; la classe
est désormais déclarée une fois pour toute la suite.

Suite amont : **14 échecs avant, 14 échecs après**, tous antérieurs (les tests `QueryBuilder`, et
`ArchiveIntegrationTest` qui cherche une `TestCase` dans le mauvais espace de noms). Aucune
régression.

**Déployé** : commité et poussé par Louis en `a83fa4b`, tiré côté projet par
`composer update amphibee/meiliscout`. **Ne jamais corriger dans
`public/content/plugins/meiliscout/`**, qui est une sortie de Composer.

**Vérifié après déploiement, sur les deux chemins qui échouaient :**

```
bascule de promo, par le vrai runner Action Scheduler (appel à admin-ajax.php)
  base 46,00 → 32,00        index 46 → 32        ✓   (avant : l'index restait à 46)

prix d'une déclinaison, contexte wp_ajax_woocommerce_save_variations
  parent 28–80              index 28 → 80        ✓   (avant : l'index restait à 62)
```

**L'argument le plus court pour défendre le correctif**, trouvé en cherchant un découpage plus fin :
le garde n'était appliqué qu'à la moitié du plugin. Les trois gestionnaires de posts passaient par
`shouldSkipPostOperation()` (`:144`, `:204`, `:241`), les trois gestionnaires de termes non
(`:271`, `:308`, `:344`). Sur le même écran et d'un même geste, modifier rapidement une catégorie
réindexait, modifier rapidement un produit non. Une asymétrie, pas une politique.

**À surveiller désormais** : les crochets de meta ne filtrent pas par clé, donc toute écriture de
meta sur un contenu indexé réindexe. Le périmètre, le cas concret présent sur ce site (l'éditeur
groupé de Yoast) et les deux contre-mesures sont dans `configuration.md`, « Toute écriture de meta
réindexe ».

**Effet de bord, et sa réponse — arbitré par Louis le 2026-09-15.** Indexer pendant l'AJAX, c'est
aussi indexer pendant une modification groupée. La réponse n'est pas de regrouper les poussées en
fin de requête, comme je l'avais d'abord proposé, mais d'activer l'**indexation différée** de
MeiliScout : elle dédoublonne déjà, et sort le coût de la requête au lieu de le diviser par deux.
Mesuré : sauvegarde à **3 ms au lieu de 36**, et **cinq sauvegardes d'affilée ne font qu'une entrée
de file**. Chiffres et conditions dans `configuration.md`, « Indexation différée ».

Attention à l'ordre : `shouldSkipPostOperation()` est appelé **avant** `isAsyncMode()`
(`SingleIndexingServiceProvider.php:241` puis `:245`). Avec le garde en place, une bascule de promo
n'est même pas mise en file. Ce correctif-ci est donc le préalable du différé, pas une alternative.

**Fenêtre annexe, sans rapport avec le garde.** `price.onsale` vient de `is_on_sale()`, qui lit les
dates en direct, alors que `price.min`/`price.max` viennent de `_price`, qui n'est réécrit que par
la bascule. Entre l'heure de début d'une promotion et le passage de la file, l'index peut donc
porter `onsale = true` avec le prix plein. WooCommerce vit la même incohérence de son côté
(`wc_product_meta_lookup.onsale` reste à `0`). Sans conséquence aujourd'hui — aucun filtre ne lit
`price.onsale` — mais à trancher avant d'en écrire un.

---

### R-111 · 🔴 · **fermé le 2026-09-15** · ouvert le 2026-09-15 — la piste de prix se coupait les jambes

Signalé par Louis : « On m'affiche 55-199 ».

Les bornes de la piste viennent de `facetStats`, calculé par le moteur **sur l'ensemble filtré —
prix compris**. Filtrer resserrait donc la piste, et une piste resserrée ne rendait plus les prix
qu'elle venait de masquer :

```
/boutique                             bornes 0,00 € – 199,00 €
/boutique?min_price=55                bornes 28,00 € – 199,00 €   ← la piste s'est rétrécie
/boutique?min_price=55&max_price=120  bornes 28,00 € – 109,00 €
```

C'est exactement le problème que le module résout déjà pour les facettes : `DisjunctiveFacetCounter`
compte une facette **en levant sa propre contrainte**, sinon ses autres valeurs tombent à zéro. Le
prix demandait le même geste.

**Correctif.** `QueryPlan::pricing()` ajoute au `multiSearch` une recherche dédiée, filtrée par le
filtre de base et les clauses de facettes mais **pas** par `FilterExpression::overlapping()`, sous
la clé `pricing`. `ListingSearch::facetStats()` lit les bornes de cette réponse-là quand elle
existe. `QueryPlan::isPricedApart()` ne paie cette recherche que lorsqu'une fourchette est tenue —
et la requête principale cesse alors de demander des bornes qu'elle ne sert plus, ce qui évite
aussi de rapatrier une distribution de tous les prix distincts pour rien.

**Vérifié** — les bornes restent celles du catalogue atteignable, et le périmètre d'une catégorie
continue de les resserrer, lui :

```
/boutique                               0,00 € – 199,00 €   17 cartes
/boutique?min_price=55                  0,00 € – 199,00 €    8 cartes
/boutique?min_price=55&max_price=120    0,00 € – 199,00 €    6 cartes
/categorie-produit/cheveux?min_price=30   9,80 € – 59,00 €   5 cartes
```

Cinq tests, cinq mutations tuées : prix conservé dans la requête de bornes, mesure jamais faite à
part, bornes lues sur la réponse principale, bornes calculées hors du périmètre des facettes,
requête principale qui redemande les bornes en double.

---

### R-110 · 🟠 · **fermé le 2026-09-15** · ouvert le 2026-09-15 — le seul plafond que le module ne pose pas, et qui coupe en silence

**Les trois gestes sont livrés.**

1. Le module **écrit** `faceting.maxValuesPerFacet`. Constaté sur l'index après réindexation :
   `{"maxValuesPerFacet":1000,"sortFacetValuesBy":{"*":"count"}}`.
2. La valeur se lit dans `SearchServiceProvider` sous `engine.max_facet_values`, défaut
   `EngineLimits::DEFAULT_MAX_FACET_VALUES`. **`EngineLimits` étendu plutôt qu'un objet dédié** :
   c'est le seul précédent exact — il porte déjà `pagination.maxTotalHits`, se lit en provider et
   descend jusqu'à l'indexable. Un objet séparé n'achetait qu'une pureté de couche, contre une
   sixième dépendance à `MeiliScoutBridge`.
3. Le garde-fou vit dans `ResolvedListing::valuesOf()`, dernier instant où le module tient encore la
   distribution brute : juste après, `FacetValues::of()` restreint puis tranche, et l'information
   est perdue. Il émet `FacetTruncated` par `report()`, là où `report()` vivait déjà.

**Deux choix qui méritent d'être écrits.**

`ResolvedListing` recevait **déjà** `EngineLimits` : le seuil du garde vient donc du même objet que
ce qui est écrit sur l'index, sans dépendance nouvelle. C'est la leçon de `R-91` — un garde-fou qui
recopie la valeur qu'il devrait dériver ne vérifie rien.

Le garde se déclenche aussi sur **100**, le défaut du moteur. Sans ça il serait aveugle exactement
quand il sert le plus : entre le déploiement du code et la réindexation, l'index coupe encore à 100
pendant que le module compare déjà à 1 000.

**`R-90` n'a pas été joint**, malgré le même diagnostic — « le module sait, et ne dit rien ».
`D-03` veut un point à la fois ; `R-110` ne coûte qu'un `count()` quand `R-90` demande un appel de
réglages par rendu, non mesuré. Coupler le geste gratuit au geste cher aurait retardé le gratuit.
Si `R-90` réclame un canal partagé, l'extraire d'un seul appelant sera trivial.

**Mesuré après coup** : distributions inchangées — `product_cat` 81, `pa_contenance` 24,
`product_brand` 9 — page intacte, aucun `FacetTruncated` émis à tort.

**Ce que le garde ne couvre pas**, et qui est assumé : deux listings sur une page parlent deux fois ;
une taxonomie qui utilise pile le plafond déclenche un faux positif, ce que le message dit
lui-même ; et une fois le client aux commandes, plus aucun PHP ne voit la distribution (`R-57`).

---

### R-110 · cadrage d'origine

`faceting.maxValuesPerFacet` vaut **100**, le défaut du moteur. Le module ne l'écrit pas : c'est le
seul des trois plafonds (`configuration.md`, « Trois plafonds ») qu'on ne trouve pas en lisant son
code, et il tronque `facetDistribution` sans erreur ni avertissement.

**Mesuré le 2026-09-15** : `product_cat` compte 109 termes en base et remonte **81 valeurs**, soit
81 % du plafond. La marge est de dix-neuf catégories.

**Le piège, qui interdit la solution évidente.** Écrire `maxValuesPerFacet = max(cap)` — 30
aujourd'hui — semble naturel puisque `cap` ne peut de toute façon pas le dépasser. C'est faux : le
moteur tronque **par compte global**, avant que `ChildTermsFacet::within()` n'intersecte avec les
enfants du rayon courant. Une valeur peu comptée à l'échelle de la boutique peut être la seule qui
compte sur sa page. Rangs des cinq enfants de « cheveux » dans la distribution triée :

```
soins-capillaires             9        plafond 100 → 5/5 survivent
laver-preparer               11        plafond  81 → 5/5
cheveux-accessoires          30        plafond  40 → 3/5
coiffer-proteger             68        plafond  20 → 2/5
cheveux-beaute-de-linterieur 70        plafond  10 → 1/5
```

Deux des cinq sont aux rangs 68 et 70 sur 81. Ils ne tiennent que parce que le catalogue est petit.
Le plafond doit donc suivre la **taille de la taxonomie**, jamais `cap`.

**Trois gestes, à traiter juste après le lot prix** (arbitré par Louis le 2026-09-15) :

1. le module **écrit** le réglage au lieu de subir le défaut ;
2. sa valeur se lit dans le provider avec son défaut, surchargeable — proposition : **1 000** ;
3. **un garde-fou qui parle** quand une distribution revient pile au plafond : c'est le seul moment
   où le module *sait* qu'il a peut-être perdu des valeurs. Les trois défauts trouvés ce jour —
   `R-109`, la bascule de promo qui n'a pas lieu, la facette tronquée — ont en commun d'être muets.

---

### R-109 · 🔴 · **fermé le 2026-09-15** · ouvert le 2026-09-15 — sauvegarder un produit lui fait perdre ses metas dans l'index

**Défaut amont, dans MeiliScout.** Reproduit à volonté et en une commande :

```
avant                        : #116 a ses metas — 72 produits filtrables
wc_get_product(116)->save()  : #116 n'a plus ses metas — 71
```

`PostIndexable::getMetaData()` boucle sur `$this->metaKeys`, qui n'est peuplé que dans trois
chemins : `getItems()` (indexation complète), `preloadBatchData()` (par lots) et le setter
`setMetaKeys()`. **Aucun ne s'exécute sur une mise à jour d'un seul document.** La boucle parcourt
donc un tableau vide, `$document['metas']` vaut `[]`, et le champ disparaît du document
(`PostIndexable.php:357`).

Conséquences, toutes silencieuses :

- **tout produit modifié depuis la dernière indexation complète sort de tous les filtres de prix et
  de stock** — `metas._price` et `metas._stock_status` n'existent plus sur son document ;
- rien ne se voit à l'écran : la carte reste juste, puisque `card` est reconstruit correctement ;
- trouvé en vérifiant une promo planifiée, ce qui explique pourquoi les trois produits touchés par
  une bascule de promo (#361, #362, #411) étaient les seuls produits sans metas de l'index.

**Ça bloque le filtre de prix** (`R-43`) : une facette de prix serait fausse pour tout produit édité
depuis la dernière réindexation complète, sans que rien ne le signale.

**Corrigé en amont** : `AmphiBee/MeiliScout@26eb035`, « fix(indexing): resolve meta keys when
indexing a single document ». Les trois copies de la résolution sont ramenées à un
`resolveMetaKeys()` partagé, appelé aussi dans le chemin unitaire que rien ne précédait. La
`composer.lock` du projet passe de `2acf53a` à `26eb035`. `CLAUDE.md` § 2 : « prefer fixing a
dependency over working around it » ; `resolveIndexable()` est le précédent.

Un test amont l'accompagne, écrit pour échouer d'abord — `SingleDocumentMetaTest`. La suite de
MeiliScout était déjà rouge sur `2acf53a` (`ArchiveIntegrationTest` ne charge pas, la classe
`Pollora\MeiliScout\Tests\TestCase` n'existe pas) : **14 échecs / 16 réussis** avant, **14 / 17**
après. Mêmes échecs, un test de plus.

Vérifié sur le site après `composer update`, patch manuel retiré :

```
avant : 72 produits filtrables · #362 absent
après une sauvegarde de #362 : 73 · #362 présent
```

**Résiduel** : #361 et #411 restent sans metas dans l'index, abîmés avant le correctif et jamais
resauvegardés depuis. Une réindexation complète — ou une simple modification — les répare.

---

### Revue de la PR #1 — `R-93` à `R-106`

Quatorze constats relevés par une revue contradictoire sur la PR de placement de facettes
(`R-89`), le 2026-09-09, plus deux ouverts en les traitant (`R-107`, `R-108`). Numérotés ici parce qu'un constat sans numéro est un constat que
personne ne retrouve. État de départ : ouvert, sauf mention.

| n° | gravité | état | constat |
| --- | --- | --- | --- |
| `R-93` | 🟡 | **fermé le 2026-09-09** | le style par défaut est porté par `[data-meili="facets"]` et ne suit pas une facette déplacée hors du groupe |
| `R-94` | 🟡 | **fermé le 2026-09-09** | une facette placée hors de `[data-listing]` est inerte, sans avertissement |
| `R-95` | 🟠 | **différé** — avec le rendu des filtres en mobile | rendre deux fois la même facette lève une exception qui sort un 500 sur toute la page |
| `R-96` | 🟡 | **fermé le 2026-09-09** | `Facet::$name` sert d'identifiant sans unicité imposée : deux facettes sur une même taxonomie partagent un nom que personne n'a écrit |
| `R-97` | 🟠 | **fermé le 2026-09-09** | collision d'identifiants entre panneau et compteur |
| `R-98` | 🟡 | **fermé le 2026-09-09** | le composant `facet` n'émet jamais `{{ $attributes }}` : ni classe ni id sur une facette placée |
| `R-99` | 🟡 | **fermé le 2026-09-09** | `$scroll` est accepté puis ignoré par le composant `Facet` |
| `R-100` | 🟢 | **fermé le 2026-09-09** | `Facets::render()` renvoie `''` au lieu de `shouldRender()`, ce qui écrit un fichier compilé vide |
| `R-101` | 🟡 | **fermé le 2026-09-09** | les tests Feature assertaient le catalogue de facettes du projet hôte |
| `R-102` | 🟡 | **fermé le 2026-09-09** | le test du bouton de repli ne peut pas échouer |
| `R-103` | 🟢 | **fermé le 2026-09-09** | `forgetScopedInstances()` sans `tearDown()` symétrique |
| `R-104` | 🟡 | **fermé le 2026-09-09** | le bloc `R-89` du registre affirme le contraire de ce qui a été livré ; `configuration.md` ne documente pas la nouvelle API publique |
| `R-105` | 🟡 | **fermé le 2026-09-09** | la fixture `tests/js/dom.js` ne reflète plus le Blade (panneau absent) |
| `R-106` | 🟡 | **fermé le 2026-09-09** | rien ne verrouille « le retrait est affaire de rendu seulement », que `R-89` nomme pourtant |
| `R-107` | 🟡 | **fermé le 2026-09-09** | le texte des contrôles est 2,3 px au-dessus du centre optique — inhérent au centrage d'une boîte de ligne, pas un défaut de la feuille |
| `R-108` | 🟡 | **différé** — piste validée, à instruire plus tard | rien n'empêche la fixture JS de dériver à nouveau |

### R-97 · 🟠 · **fermé le 2026-09-09** · ouvert le 2026-09-09 — collision d'identifiants entre panneau et compteur

Deux défauts sous un seul constat, de portées très différentes.

**Le premier était atteignable par un éditeur seul**, sans complicité du code : un terme slugué
`panel` rendait `facetCount('product_brand', 'panel')` égal à `facetPanel('product_brand')` —
`meilifacets-products-product_brand-panel`. L'`aria-describedby` de cette valeur résolvait vers le
`<div>` du panneau entier au lieu de son compteur. Les deux méthodes clefaient par ailleurs sur la
**taxonomie** alors que les facettes sont désormais clefées par **nom** (`R-89`) : deux facettes
sur une même taxonomie collisionnaient sur tous leurs identifiants.

Le même défaut latent existait dans la famille `sort`, que la revue n'avait pas vue : une clef de
tri nommée `trigger` aurait volé l'identifiant du bouton. Corrigé du même coup — `sortOption()`
porte maintenant son segment `option`, et la famille est injective par construction puisqu'elle
n'a qu'une seule partie variable, en dernière position.

**Le second était structurel** : `of()` joint avec `-`, caractère que les parties variables
contiennent librement, donc les deux familles se recouvraient par construction. Prouvé sur la
vraie classe après le premier correctif — `facetPanel('brand-value-x')` valait encore
`facetCount('brand', 'x-panel')`. Exhaustivement, sur les noms et slugs composés des mots du
gabarit : 480 collisions.

Portée réelle mesurée avant de corriger : **aucune collision atteignable sur le site**. Il fallait
réunir un nom de facette pathologique (`…-value`, écrit par un développeur dans son énumération) et
un slug qui complète le motif. Les noms en place sont `category`, `brand`, `volume`. Le correctif
ne répare donc rien d'observable : il ferme, pour un caractère, la catégorie de défauts entière
dont le premier cas était l'instance.

Correctif : la famille `facet` ferme le nom par `--`. `sanitize_title()` écrase les suites de
tirets (`|-+|` → `-`) et rogne les extrémités, donc **aucun slug de terme ne contient `--`** —
vérifié sur dix entrées hostiles, tirets insécables compris — et les trois chemins d'écriture d'un
slug y passent (`wp_insert_term`, `wp_update_term`, `wp_unique_term_slug`). Le dernier `--` d'un
identifiant est donc toujours celui qui ferme le nom de facette : la découpe est unique **même si
un projet met `--` dans un nom**, puisqu'on lit depuis la droite. Aucune validation ajoutée nulle
part.

```
meilifacets-products-facet-volume--panel
meilifacets-products-facet-volume--count-100ml
```

Coût mesuré : +8 ns par identifiant (dans le bruit du micro-bench, `implode` reçoit la même
arité), soit +0,3 µs sur les 41 identifiants d'une page `/boutique` dont le TTFB médian est de
264 ms. Aucun appel WordPress ajouté : le slug arrive déjà assaini par l'indexation, la classe
reste pure — ses seuls appels sortants sont `implode` et son propre `of()`. +1 octet par
identifiant dans le HTML.

Le test est devenu une propriété — « aucun identifiant n'est produit par deux éléments
différents » — sur les noms et slugs assemblés à partir des mots du gabarit, noms contenant `--`
compris. Trois mutations passées : revenir au tiret simple le tue, retirer le nom du listing le
tue, et échanger le mot `count` contre `panel` le laisse vert **à juste titre** (la propriété tient
toujours) mais est rattrapé par l'assertion littérale.

**Réserve consignée** : `sanitize_title` est un filtre. Un plugin qui s'y branche pourrait rendre
`--`. Le cœur ne le fait jamais et aucun plugin installé ici ne s'y branche.

---

### R-100 · 🟢 · **fermé le 2026-09-09** · ouvert le 2026-09-09 — `render()` renvoyait une chaîne vide au lieu d'utiliser `shouldRender()`

Le comportement ne change pas : un groupe auquel aucune facette n'est affectée, et qui ne porte pas
le bouton d'envoi, ne rend toujours rien. C'est le mécanisme qui change.

`Component::render()` acceptait une chaîne, que Laravel traite comme un gabarit Blade **inline** :
`extractBladeViewFromString()` en calcule l'empreinte xxh128, `createBladeViewFromString()` écrit un
`.blade.php` dans `view.compiled`, et le rendu l'inclut. Pour la chaîne vide, cela donne
`99aa06d3014798d86001c324468d497f.blade.php`, **0 octet**, inclus à chaque rendu pour ne rien
produire.

Deux corrections à ce que la revue en disait, mesurées :

- l'écriture n'a pas lieu « à chaque rendu ». `static::$bladeViewCache` mémoïse par **processus** :
  c'est une écriture par requête qui atteint la branche, pas par rendu. Vérifié dans les deux sens —
  inode inchangé sur 200 rendus d'un même processus, inode déplacé entre trois processus, puisque
  `Filesystem::replace()` passe par un fichier temporaire puis un `rename` ;
- le garde-fou de Laravel `! is_file($viewFile) || filesize($viewFile) === 0` reste vrai à
  perpétuité pour un contenu vide, donc le fichier est bien réécrit à chaque processus, jamais
  réutilisé.

Coût réel mesuré, médiane sur cinq séries de 200 rendus du groupe vide :

| | par rendu du groupe vide |
| --- | --- |
| `return ''` | 53,8 µs (min 43,9 — max 78,5) |
| `shouldRender()` | 17,1 µs (min 13,7 — max 55,8) |

Une page ne rend ce groupe qu'une fois : le gain réel est de ~37 µs sur un TTFB médian de 264 ms,
soit 0,014 %. **Et aucune page du site n'atteint la branche aujourd'hui** : `apply_mode` vaut
`submit`, donc `needsButton()` est vrai et le conteneur est rendu dans tous les cas. Le retour de
chaîne vide était du code mort en configuration courante.

Ce qui justifie le changement n'est donc pas la performance : `render()` retrouve un type de retour
honnête (`View`, plus `View|string`), la condition prend le nom que le framework lui donne
(`CompilesComponents.php:73` compile littéralement `<?php if ($component->shouldRender()): ?>`, donc
le garde-fou passe avant toute résolution de vue), et `storage/framework/views` cesse de porter un
fichier de 0 octet.

Le test (`it_compiles_no_view_when_it_renders_nothing`) verrouille le mécanisme et non la sortie :
il assert l'absence du fichier compilé. Trois mutations passées — revenir à `return ''`, forcer
`shouldRender()` à `true`, le forcer à `false` — les trois sont tuées.

**Non traité ici, à sa place** : la revue ajoute que le garde-fou fait aussi disparaître
`{{ $scrollMark() }}`, `data-apply` et l'ancre `[data-meili="facets"]` sur laquelle la feuille de
style branche ses règles. C'est exact, mais c'est le comportement — arbitré par Louis le
2026-09-08 (« il ne faut pas d'élément sinon tu vas alourdir le DOM ») — et la conséquence sur le
style appartient à `R-93`.

---

### R-106 · 🟡 · **fermé le 2026-09-09** · ouvert le 2026-09-09 — rien ne verrouillait « le retrait est affaire de rendu seulement »

**Cinq des sept points de lecture ne peuvent pas déraper**, contrairement à ce qu'annonçait la
revue. `QueryPlan`, `ListingSearch`, `DisjunctiveFacetCounter` et `StateReader` reçoivent le contrat
`Listing`, qui n'expose pas `remainingFacets()` — la méthode vit sur `ResolvedListing`. Le
« rangement » redouté ne compilerait pas.

La surface réelle est **une** classe : `ListingDescription::of(ResolvedListing $listing)`, à ses
deux appels (`:58` pour le tableau des facettes, `:69` pour la carte des paramètres). C'est elle qui
alimente le client, donc le comptage et les filtres d'URL des facettes que la page n'a pas montrées.

Test : `it_still_publishes_a_facet_a_template_placed_apart`. Écrit d'abord dans la suite `Unit`, il
a dû migrer en `Feature` — `ListingDescription` appelle `trans()` pour les motifs qui voyagent dans
la description, donc il exige une application. Il lit ses noms et ses comptes à l'exécution, pour ne
pas retomber dans `R-101`.

Les deux mutations sont tuées.

**Second point du constat, traité autrement.** La revue demandait de reporter dans
`facet.blade.php` la justification d'accessibilité supprimée de `facets.blade.php` (« Described, not
named… »). Ce serait contredire la règle de commentaires : une justification de conception va dans
la documentation, pas dans une vue. Elle est donc écrite dans `architecture.md`, section « Ce que le
compteur d'une valeur est, pour un lecteur d'écran » — et surtout **verrouillée par un test**,
`it_describes_a_value_with_its_count_rather_than_naming_it`, ce qu'un commentaire n'aurait jamais
fait. Deux mutations tuées : passer à `aria-labelledby`, retirer l'`id` du compteur.

---

### R-105 · 🟡 · **fermé le 2026-09-09** · ouvert le 2026-09-09 — la fixture JS ne reflétait plus le Blade

`tests/js/dom.js` posait le `<ul>` et le bouton `more` directement dans le `<fieldset>`, alors que
le Blade intercale `.meilifacetsFacetPanel` et `.meilifacetsFacetPanelInner` depuis `R-89`. Son
docblock affirmait pourtant « Mirrors what the Blade components render ». Manquaient aussi la
`<legend>`, les classes `meilifacetsFacet`, `meilifacetsFacetValues`, `meilifacetsFacetMore`, et le
couple `aria-describedby` / `id` du compteur.

Correctif : un helper `facetBlock()` calqué sur le rendu réel, relevé sur `/boutique` plutôt que
réécrit de mémoire.

**Ce que la correction n'a pas révélé, contrairement à ce qu'annonçait la revue.** Elle affirmait
que la fixture périmée « explique que la régression CSS du constat 1 (`R-93`) soit passée
inaperçue ». Les 147 tests JS restent verts après correction. La raison est ailleurs : la fixture ne
contient **aucune facette hors du groupe**, et c'est cette condition-là que `R-93` exige. La
corriger était nécessaire, ce n'est pas suffisant — l'ajout d'une facette hors groupe appartient au
correctif de `R-93`, où il fera échouer les tests concernés.

Vérifié au passage, ce qui étaie `R-93` : cinq règles portent le préfixe `[data-meili="facets"]`
(`fieldset`, `legend`, `ul`, `:last-of-type`) et **aucune** ne vise `.meilifacetsFacetPanel`,
`.meilifacetsFacetValues`, `.meilifacetsFacetMore` ni `.meilifacetsFacetLabel`. Une facette déplacée
perd donc tout son style.

`tests/js/stylesheet.test.js` n'a pas été touché — ses sélecteurs sont des descendants, l'insertion
des deux `div` ne les casse pas.

**Reste ouvert, sans numéro jusqu'ici** : une fixture écrite à la main dérive, c'est ce qui vient
d'arriver. `R-108`.

---

### R-108 · 🟡 · **différé le 2026-09-09** · ouvert le 2026-09-09 — rien n'empêche la fixture JS de dériver à nouveau

`tests/js/dom.js` est recopié à la main depuis le Blade, et la suite JS doit rester exécutable sans
application hôte (`composer check`) — donc elle ne peut pas la générer. `R-105` a corrigé l'écart du
jour ; le prochain changement de vue le recréera en silence, et les tests de feuille de style
continueront de valider un markup mort.

**Piste validée par Louis le 2026-09-09, à instruire plus tard** : un test de la suite `Feature`,
côté PHP, qui lit `dom.js` et vérifie que les classes et crochets structurants qu'il emploie existent
dans le rendu réel. C'est le seul pont possible sans faire dépendre la suite JS de PHP.

**Limite connue d'avance, à peser au moment de le faire** : il attrape ce que la fixture *emploie et
qui a disparu*, pas ce que le Blade a *ajouté et que la fixture ignore* — c'est-à-dire exactement le
cas de `R-105`. Attraper celui-là imposerait de comparer les deux arbres, donc de générer la
fixture, donc de faire dépendre `composer check` d'une application hôte. À reconsidérer avec `R-70`,
qui touche déjà à la manière dont le client est livré.

**Au 2026-09-22** (passe documentaire, `R-153`) : toujours vrai ; le chemin cité est périmé —
la fixture vit sous `tests/ts/`, `tests/js/` n'existe plus. Le déclencheur « avec `R-70` » est passé
(`R-70` fermé par `R-132`).

---

### R-101 · 🟡 · **fermé le 2026-09-09** · ouvert le 2026-09-09 — les tests Feature assertaient le catalogue du projet hôte

Le premier constat traité, avant même que les numéros n'existent — d'où cette entrée écrite après
coup, en complétant le registre.

Les tests de placement écrivaient en dur `3`, `category`, `brand`, `volume`, `product_cat`,
`product_brand` et le nom de listing `products`. Tout cela vient de
`App\Cms\Products\CatalogueFacets`, que Pluralia binde par-dessus `ProductFacets`
(`AppServiceProvider:30`). Le `WooCommerceFacets` du module, lui, déclare deux facettes et n'en
nomme aucune : leurs noms sont `product_cat` et `product_brand`. **Ajouter une quatrième facette à
la boutique cassait la suite du module** — l'inverse de la règle du `CLAUDE.md` : « Pluralia is its
test bed, not its owner. »

Correctif en deux temps :

- les tests `Feature` lisent noms et comptes **à l'exécution** — `$this->first()`,
  `$this->declaredCount()`, `$listing->name()` — au lieu de les recopier ;
- les règles de placement elles-mêmes ont quitté la suite `Feature` pour `Unit\FacetPlacementTest`,
  qui monte un `ResolvedListing` sur des facettes **qu'il déclare lui-même** (`FakeListing`), sans
  conteneur, sans Blade et sans Pluralia. Ce qui reste en `Feature` ne prouve qu'une chose : que le
  composant demande bien au listing ce que la règle exige.

Un test dépendait aussi d'un réglage du projet sans le dire :
`it_renders_no_container_when_every_facet_was_placed_apart` n'était vert que parce que `apply_mode`
valait `immediate`. Il pose désormais son `config([...])`, et un test complémentaire couvre le mode
`submit`, où le bouton d'envoi retient le conteneur.

---

### R-102 · 🟡 · **fermé le 2026-09-09** · ouvert le 2026-09-09 — le test du bouton de repli ne pouvait pas échouer

`it_keeps_the_fold_button_inside_the_panel` comparait deux `strpos` : le bouton apparaissait-il
**après** la balise ouvrante du panneau. Sortir le bouton des deux `div` et le poser juste avant
`</fieldset>` — la régression exacte que le nom du test annonce — garde l'offset supérieur.

Vérifié avant de corriger, en appliquant cette mutation au Blade : **le test reste vert**.

Correctif : l'assertion porte sur la containment, pas sur l'ordre. `Dom\HTMLDocument` (PHP 8.4)
analyse le rendu, `querySelector('.meilifacetsFacetPanelInner')` isole le panneau, et le bouton est
cherché **dans** ce nœud. Les deux messages d'échec nomment ce qui a cassé, ce qui répond au point
secondaire du constat (un `assertGreaterThan(false, false)` ne disait pas que le panneau avait
disparu).

Deux mutations passées :

```
le bouton sort du panneau  → « The fold button sits outside the panel, so collapsing the facet leaves it behind. »
le panneau disparaît       → « The facet renders no panel to collapse. »
```

**Dépendance à confirmer** : le test utilise `ext-dom`, extension du cœur de PHP mais non déclarée
par le module. Elle n'est atteinte que par la suite `Feature`, qui exige déjà une application hôte —
`composer check` et la suite `Unit` ne la touchent pas. À déclarer en `require-dev`, sur accord.

---

### R-103 · 🟢 · **fermé le 2026-09-09** · ouvert le 2026-09-09 — `forgetScopedInstances()` sans `tearDown()`

La suite partage une seule application pour tout le run (gotcha 23 du `CLAUDE.md` projet), donc
`FacetComponentTest` laissait derrière lui un `ResolvedListing` portant les facettes qu'il avait
placées.

**Le mécanisme décrit par la revue est faux, et la réalité est pire.** Elle annonçait un échec sur
`Facet "category" is rendered twice`. Ce qui fuit est `$apart`, pas `$rendered` : `remainingFacets()`
rend `[]` et le groupe sort **avec son bouton et aucune facette**, sans rien lever. Un test suivant
obtient donc un résultat faux au lieu d'une erreur.

Prouvé avant de corriger, par une sonde rendant `<x-meilifacets::facets />` sans réinitialiser :
verte seule, rouge dans la suite complète.

Correctif : `tearDown()` appelant `forgetScopedInstances()` **avant** `parent::tearDown()` — le
`TestCase` du projet met `$this->app` à `null` juste après pour empêcher le `flush()`. La sonde est
devenue `ListingStateIsolationTest`.

**Limite écrite dans son docblock** : ce test ne vaut que si son fichier passe après ceux qui
placent des facettes. Seul, il est vert dans les deux cas. C'est le seul test qui tue la mutation
(retirer le `tearDown()` fait échouer la suite complète), mais il repose sur l'ordre alphabétique.

---

### R-107 · 🟡 · **fermé le 2026-09-09** · ouvert le 2026-09-09 — le texte des contrôles n'est pas au centre optique

Relevé par Louis sur capture, cause confirmée par lui puis mesurée : **les métriques d'Epilogue**.
À 14 px, la police déclare `ascent 11 px / descent 3 px` ; les libellés des contrôles (« Pertinence »,
« Tout effacer ») n'ont aucun jambage, donc la réserve de 3 px reste vide et l'encre remonte.

```
encre à 11,53 px du haut · 13,86 px du bas  →  2,3 px trop haut
```

`align-items: center` centre la **boîte de ligne**, pas les glyphes.

Ce n'est pas le `line-height`, mesuré dans les deux réglages : `1.2` donne 10,60 / 10,90 et `1`
donne 10,50 / 11,00 — le demi-interligne est symétrique, il ne déplace rien. Le décalage précède le
passage à `line-height: 1`.

`text-box: trim-both cap alphabetic` est supporté mais **sans effet ici** : mesuré à 10,50 / 11,00
en `inline-flex`, contre 12,16 / 9,34 en `inline-block`. La propriété s'applique aux boîtes de bloc,
et dans un conteneur flex le texte est un élément anonyme. L'utiliser imposerait d'envelopper le
libellé dans un `<span>` dans quatre vues.

**Fermé le 2026-09-09 sur constat de Louis, sans changement de code.** La mesure qui l'a clos montre
que le décalage ne dépend pas de la feuille mais du **mot** :

| bouton | jambages | décalage |
| --- | --- | --- |
| « Pertinence » | 0,1 px | **−2,33 px** |
| « Appliquer les filtres » | 3,1 px | **0,01 px** |
| le même bouton, texte remplacé par « Prix apres » | 3,1 px | 0,59 px |

Un texte sans jambage laisse vides les 3 px qu'Epilogue réserve en bas et remonte d'autant ; le même
bouton avec un mot qui descend est centré au centième de pixel. C'est le comportement de tout
centrage de boîte de ligne, sur n'importe quelle police et n'importe quel site — pas un défaut de la
feuille du module.

Le corriger vraiment demanderait de rogner à la hauteur de capitale (`text-box: trim-both cap
alphabetic`), qui **ne s'applique pas dans un conteneur flex** : mesuré à `10,50 / 11,00` en
`inline-flex` contre `12,16 / 9,34` en `inline-block`. Il faudrait envelopper le libellé dans un
`<span>` dans quatre vues. À rouvrir seulement si la charte l'exige.

---

### R-94 · 🟡 · **fermé le 2026-09-09** · ouvert le 2026-09-09 — une facette placée hors du listing était inerte, sans un mot

`R-89` a donné aux gabarits la liberté de placer une facette « où ils veulent ». La vraie règle est
« où ils veulent **à l'intérieur de `[data-listing]`** », et cette contrainte n'était écrite nulle
part ni vérifiée par personne — alors que le premier cas d'usage que `R-89` énonce, « un rayon en
tête de page », est précisément celui qui casse.

Mesuré dans le navigateur, facette de catégorie posée avant `<x-meilifacets::listing>` :

| geste « Voir plus » | `aria-expanded` |
| --- | --- |
| facette **dans** la racine | `false` → **`true`** |
| facette **hors** de la racine | `false` → `false` |

Rendue, stylée, cochable, et totalement morte. Zéro avertissement : `Contract.#breachesOf()` rend
`[]` dès que `this.one(host)` ne trouve rien **dans la racine**, donc ce qui est dehors lui est
invisible par construction.

*Première mesure écartée* : en mode `submit`, cocher une case ne filtre pas non plus **dans** le
listing — « la case ne fait rien » ne prouvait rien. Seul le bouton de repli discrimine.

**Aucun correctif côté serveur n'est possible** : Blade évalue le `$slot` avant le composant
`listing` qui l'enveloppe, donc « rendu avant » ne dit rien sur « contenu dans ».

Correctif en deux temps, le premier plus important que le second :

1. **la règle est écrite** dans `configuration.md` — tout composant du module vit dans
   `<x-meilifacets::listing>`, et la section « Placer les facettes » montre désormais l'imbrication.
   La documentation livrée avec `R-104` ne mentionnait pas une seule fois `x-meilifacets::listing` :
   elle documentait la liberté sans sa limite ;
2. **le code la dit** — `Contract.orphans(document, roots)`, appelé au démarrage par
   `listing-page.js`, sur le canal `console.error` qui sert déjà aux manquements au contrat. La
   vérification est au niveau du document, là où `Contract` est scopé à une racine : c'est pour ça
   qu'elle vit à côté de la boucle des racines et non dans `RULES`.

Seules les racines orphelines sont nommées — les crochets qu'une facette égarée contient ne sont pas
une seconde faute. Sur la page réelle : 17 éléments `data-meili` hors racine, **un** nom rapporté.

```
[meilifacets] the client binds inside [data-listing] only. Move inside <x-meilifacets::listing>: facet.
```

Quatre mutations tuées. *Deux d'entre elles avaient d'abord été rapportées « survivantes » à tort* :
mon harnais ne vérifiait pas que le remplacement avait eu lieu, et l'une des mutations produisait un
code invalide. Corrigé, elles tuent bien.

**Rencontré en vérifiant, et déjà connu** : le navigateur exécutait encore l'ancien `contract.js`.
`?ver=` porte le `filemtime` de `listing-page.js` seul ; ses imports relatifs n'ont aucune version et
sont cachés indéfiniment. C'est exactement `R-70`, tranché et différé après la v1.

---

### R-96 · 🟡 · **fermé le 2026-09-09** · ouvert le 2026-09-09 — deux facettes pouvaient répondre au même nom

`Facet::$name` retombe sur la taxonomie quand rien n'est déclaré, donc deux facettes sur une même
taxonomie — un `ChildTermsFacet` et un `Facet` sur `product_cat`, par exemple — partageaient un nom
que **personne n'avait écrit**.

Trois symptômes, tous prouvés avant correction sur un `ResolvedListing` monté à la main :

```
noms déclarés               : product_cat, product_cat
facetNamed('product_cat')   : rend la PREMIÈRE, la seconde est inatteignable
après placeApart(la 1re)    : il reste 0 facette sur 2   ← la seconde disparaît en silence
sans rien placer, le groupe : « Facet "product_cat" is rendered twice […] Place it on its own
                               before <x-meilifacets::facets> »
```

Le dernier est le pire : le message accuse le gabarit d'une faute qui est dans la **déclaration**,
et le remède qu'il propose ne peut pas marcher — placer la facette à part retire les deux.

Correctif : `ResolvedListing::refuseSharedNames()`, à l'image de `ListingRegistry` pour les noms de
listing. Le message nomme les deux **libellés**, seule chose qui distingue deux déclarations sur une
même taxonomie, et indique la sortie :

```
Facets "Aisles" and "Shelves" answer to the same name "product_cat" in listing "products".
Declare `name:` on all but one of them.
```

*Deux versions écartées en chemin, sur remarque de Louis.* Une première nommée `distinctlyNamed()` —
un adjectif pour une garde qui lève. Une seconde qui sortait tôt par
`count(array_unique($names)) === count($names)` puis identifiait la paire dans un second parcours :
elle **triplait la méthode** pour un chemin normal mesuré à **0,11 µs** (3 facettes ; l'écart entre
les deux approches n'apparaît qu'à 12 facettes, `−33 %`, soit 0,13 µs). La boucle simple identifie
la paire gratuitement, au passage. 17 lignes, un seul parcours, `sprintf`, **un seul littéral** —
`pint.json` n'impose aucune longueur de ligne et le module en porte déjà à 168 caractères, donc la
concaténation ne servait qu'à couper une phrase en deux et à la rendre non-greppable. Le « pourquoi »
qu'elle portait est dans `configuration.md`, pas dans un message d'erreur.

`facets()` est désormais mémoïsé — non pour le temps (**4 appels par rendu de `/boutique`**,
mesuré), mais pour que la validation tourne une fois et non quatre. **Cette mémoïsation n'est
couverte par aucun test et ne peut pas l'être** : elle ne change rien d'observable ; la mutation qui
la retire laisse la suite verte. Corollaire assumé : un `Listing` dont `facets()` varierait au cours
d'une requête verrait sa première réponse figée — aucun n'en dépend, les quatre appels rendaient
déjà la même chose.

Deux tests, deux mutations tuées : la garde qui ne lève plus, la garde qui clefe sur la taxonomie
plutôt que sur le nom. Le second test montre la sortie — nommer l'une des deux — et vérifie qu'elle
fonctionne.

---

### R-93 · 🟡 · **fermé le 2026-09-09** · ouvert le 2026-09-09 — le style par défaut ne suivait pas une facette déplacée

Quatre règles habillaient la facette depuis le **groupe** (`[data-meili="facets"] fieldset`,
`… legend`, `… ul`, et l'échelle `--meili-ui`). Une facette placée ailleurs par un gabarit — ce que
`R-89` vient d'autoriser — n'en recevait aucune.

Mesuré dans un vrai navigateur, facette de catégorie posée hors du groupe :

| | avant | après |
| --- | --- | --- |
| taille du texte | **16 px** (échelle de la page) | 14 px |
| puces de la liste | **`disc`** | `none` |
| graisse de la légende | **400** | 600 |

S'y ajoutaient, mesurés sous happy-dom, le cadre et le remplissage que le navigateur pose sur un
`<fieldset>` nu, et la marge basse.

Correctif : les quatre règles s'accrochent à `[data-meili="facet"]`, le crochet que la facette porte
elle-même. C'est ce que `decisions.md:186` demandait déjà — « la règle s'accroche à `data-meili`,
jamais aux classes ». Seule reste au groupe `[data-meili="facets"] [data-meili="facet"]:last-of-type`,
qui parle vraiment d'une position dans le groupe. La spécificité baisse de `0-2-0` à `0-1-0`, donc un
thème surcharge plus facilement — dans le sens voulu.

Tests : `tests/js/facet-placement.test.js`, quatre cas comparant une facette groupée et une facette
placée. La fixture porte désormais une facette hors du groupe, ce que `R-105` avait identifié comme
manquant. Les quatre échouaient avant le correctif ; chacune des quatre règles rescopées est tuée
par au moins un test.

**`tests/js/stylesheet.test.js` n'a pas été touché**, ses 147 cas restent verts.

*Défaut corrigé en cours de route dans mon propre test* : il s'appelait « strips the bullets and the
indent » en assertant `listStyleType` et `paddingInlineStart`, que happy-dom ne calcule pas — il
rendait `''` des deux côtés et ne prouvait rien. Remplacés par `listStyle` et `paddingLeft`, les
propriétés que le moteur résout et qu'emploie déjà `stylesheet.test.js`.

---

### R-95 · 🟠 · **fermé le 2026-09-24, sans code** · différé le 2026-09-09 · ouvert le 2026-09-09 — rendre deux fois la même facette sort un 500 sur toute la page

**Différé par Louis, à traiter avec le rendu des filtres en mobile** : c'est ce chantier qui dira si
la modale est le même DOM présenté autrement ou un second rendu, donc si l'exception gêne.

`ResolvedListing::place()` lève quand un nom est déjà rendu — voulu, demandé en séance (« il faut
générer une erreur Laravel non ? »), et le seul comportement qui évite un doublon silencieux
d'entrées et d'identifiants. Reste que l'exception traverse le rendu et sort un 500 sur la page
entière, là où une facette dupliquée est une faute de gabarit, pas une panne de service.

**Correction d'une affirmation portée par erreur dans ce registre le 2026-09-09** : il y était écrit
que le Figma pouvait vouloir la même facette dans le panneau desktop **et** dans la modale mobile.
Les deux frames relevées disent l'inverse — la catégorie est en pastilles en haut de page (`5-31`)
et **n'est pas reprise** dans le panneau déplié (`6-169`), qui porte Type de peau, Besoin, Actifs,
Texture, Utilisation, Confort, Formulation, Label, Marque, Prix. Aucune facette n'y apparaît deux
fois : c'est exactement le modèle à deux modes livré par `R-89`.

La question qui reste, plus étroite : **la modale mobile est-elle le même DOM présenté autrement, ou
un second rendu ?** Aucune frame mobile relevée ne permet de trancher. Si c'est du CSS, l'exception
ne gêne personne et le constat se ferme sans code.

**Tranché par Louis le 2026-09-24, en ouvrant le chantier mobile : « même système, je ne veux pas de
doublons. »** C'est la condition ci-dessus, remplie : la modale présentera l'unique rendu autrement.

**Fermé sans code**, et vérifié dans le code plutôt que déduit : `ResolvedListing::place()`
(`ResolvedListing.php:203-213`) ne lève que si **le même nom** est placé deux fois dans la même
requête, et `CurrentListing` mémoïse un `ResolvedListing` par nom (`CurrentListing.php:33`). Un thème
qui rend chaque facette une fois et la présente en colonne ou en tiroir selon la largeur ne franchit
jamais la garde. Celle-ci reste ce qui évite des `id` et des cases dupliqués en silence, et elle est
couverte par `FacetPlacementTest:58`.

**Ce qui change pour le visiteur : rien.** Le point est fermé parce que la décision d'architecture le
rend sans objet, pas parce qu'un correctif a été écrit.

**Trouvé en le fermant, et ouvert à part** : la garde ne protège que les facettes — `R-162`.

---

### R-104 · 🟡 · **fermé le 2026-09-09** · ouvert le 2026-09-09 — le registre affirmait le contraire de ce qui a été livré

Le bilan de `R-89` avait été écrit le 2026-09-08, après la première livraison — la vue découpée — et
jamais repris quand le placement a été terminé le lendemain. Il affirmait trois choses que le diff
contredisait : « ni nom porté par la facette », « il n'y a rien à nommer », « le sous-ensemble n'a
pas été livré ». Les trois étaient exactes la veille. Il n'avait en outre aucun en-tête `### R-xx`
et vivait sous `R-92`, à 150 lignes de l'entrée qu'il concluait.

Correctif : le bilan est réécrit et rattaché à `### R-89`, en cinq points — la vue découpée, le nom
porté par la facette, les deux modes de placement, l'unicité du rendu, et ce que le composant
transmet (`R-98`/`R-99`). Il porte la mention de ce qu'il remplace, pour qu'une relecture ne croie
pas à un oubli. L'état de `R-89` n'avait alors pas bougé ; il a été **validé le 2026-09-09**.

L'API publique est documentée dans `configuration.md`, nouvelle section « Placer les facettes dans
un gabarit » : les deux composants, le paramètre `name`, ce que le sac d'attributs transmet, et la
surcharge de `components/facet.blade.php` seule.

Vérifié en exécutant l'exemple exact de la documentation plutôt qu'en le relisant — la facette
placée à part sort `class="meilifacetsFacet lg:col-span-2" … data-meili-scroll`, le groupe rend les
deux autres dans l'ordre déclaré (`product_brand`, `pa_contenance`), template du thème restauré
après la sonde.

---

### R-98 et R-99 · 🟡 · **fermés le 2026-09-09** · ouverts le 2026-09-09 — le composant `facet` jetait ce qu'un template lui donnait

Deux numéros, un seul défaut, sur la même ligne de la même vue : traités ensemble sur accord de
Louis, plutôt qu'en deux commits qui se seraient marchés dessus.

```blade
<x-meilifacets::facet :facet="ShopFacet::Brand" class="lg:col-span-2" scroll />
```

Ni la classe ni le `scroll` n'arrivaient. La classe tombait dans le sac d'attributs, que la vue
n'émettait jamais ; `Facet::__construct` appelait `parent::__construct($listings, $name)` sans le
troisième paramètre, et la vue n'émettait pas `$scrollMark()`.

Conséquence concrète pour `R-89` : une facette déplacée dans une case de grille précise ne pouvait
pas y être stylée, ce qui vidait le déplacement de son intérêt — et c'est précisément ce dont
l'animation en grille a besoin. Pour `R-99`, la même facette se comportait différemment selon
l'endroit où le template la posait, alors que les quatre autres composants (`facets`, `sort`,
`reset`, `pagination`) portent tous `$scrollMark()`.

Correctif : `{{ $attributes->class('meilifacetsFacet') }}` et `{{ $scrollMark() }}` sur le
`<fieldset>`, `bool $scroll = false` transmis au parent. Le motif est celui de `card.blade.php`,
déjà verrouillé par `CardComponentTest::it_merges_the_classes_the_caller_adds`.

Vérifié sur `/categorie-produit/cheveux`, facette de catégorie placée à part :

```
<fieldset class="meilifacetsFacet lg:col-span-2" data-taxonomy="product_cat" data-meili="facet" data-meili-scroll>
<fieldset class="meilifacetsFacet"               data-taxonomy="product_brand" data-meili="facet">
```

Les facettes du groupe restent inchangées, et ni `scroll` ni `class` ne fuient en attribut brut.

Trois tests ajoutés, trois mutations passées : figer la classe, retirer `$scrollMark()` de la vue,
cesser de transmettre `$scroll` — les trois sont tuées.

---

## 10. Questions ouvertes

Rangées de la plus structurante à la plus locale. Une réponse ici ferme ou réoriente les constats
qui la citent.

### Cadrage

**Q-01 · ~~Ce module est-il un module de projet ou un paquet réutilisable ?~~** — **répondu le
2026-09-06, voir D-01.** Module Pollora générique, Pluralia en banc d'essai. Le module porte le
fonctionnement, le thème l'apparence, chaque vue reste surchargeable, et le module livre une
feuille de style **minimale** — ce dernier point ouvre R-53 et Q-28.

**Q-02 · ~~Que fait-on du dépôt git imbriqué ?~~** — **répondu le 2026-09-06, voir D-02.**
Dépôt séparé assumé : `Pollora/MeiliFacets`, privé. Le mode de versionnage des modules Pollora se
décidera plus tard. Reste à rouvrir avant la première mise en production : rien ne relie une
révision du projet à une révision du module.

**Q-03 · (reformulée le 2026-09-06) Qui a le droit de construire le filtre envoyé au moteur ?**

*Première formulation mal posée. Reprise en clair.* Le point n'est pas la latence — sur ce sujet
voir D-04 et R-54 — mais **la confiance**. Dès que le navigateur parle au moteur en direct, c'est
lui qui écrit la requête. Le filtre `post_status = "publish" AND post_type = "product"` que PHP
pose au premier rendu devra être transmis à la page, sous forme de chaîne, pour que le client
puisse le rejouer. N'importe quel visiteur peut alors l'effacer dans la console et interroger
l'index sans lui : brouillons, produits masqués du catalogue, articles non publiés — tout ce que
l'index contient devient lisible, dans la limite de `displayedAttributes`.

Ce n'est pas une faille du code écrit : c'est la conséquence mécanique du transport direct, et elle
n'était écrite nulle part. Elle ne rend pas la décision mauvaise — elle dit seulement que **le
filtre de sécurité doit vivre côté moteur, pas côté page**.
Elle a un coût qui n'a jamais été écrit : aucun filtre de sécurité n'est opposable, et le filtre de
base part en clair dans la page. Trois options, à peser explicitement :
1. on garde le direct **et** on pose un tenant token Meilisearch (le filtre devient opposable
   côté moteur) ;
2. on garde le direct et on assume que l'index ne contient que du public, en le garantissant à
   l'indexation plutôt qu'à la recherche ;
3. on introduit une route Laravel légère (mode API de Pollora, ~100 ms annoncés, sans plugins) qui
   signe la requête — ce qui contredit une décision validée, mais rend le modèle défendable.
*Cite : R-27, R-28, R-29.*

**Q-04 · ~~Quelle est la définition de « fini » ?~~** — **répondu le 2026-09-06, voir D-03.**
Fondations d'abord, un point à la fois, chaque point validé et documenté avant d'ouvrir le suivant.
Le chantier B passe avant le chantier C.

**Q-04b · Reste à fixer : quel est le critère de recette du socle ?**
« Fondations solides » demande une ligne d'arrivée observable. Proposition : le socle est validé
quand, sur `/boutique` et sur une archive de catégorie, filtrée et non filtrée, (a) toute facette
laisse atteindre ses autres valeurs, (b) aucune valeur rendue n'est inatteignable, (c) un
changement de terme se propage à l'index, (d) le câblage est couvert par des tests. À valider ou à
amender.

### Ordre de marche

**Q-05 · ~~Que devient une facette mono-sélection ?~~** — **répondue le 2026-09-06 par D-07** : comptage
disjonctif, catégorie en multi-sélection restreinte au niveau courant (`T-05` fait). *Marquée le 2026-09-22.*
Trois issues à R-10 : (a) comptage disjonctif pour toutes les facettes, mono comprise ; (b) la
catégorie cesse d'être une facette et devient une navigation par liens sur le chemin — ce qui est
déjà à moitié le cas puisque la facette se retire sur une archive de catégorie ; (c) on garde le
comportement actuel et on l'écrit comme une limite. Ma préférence : (b) pour la catégorie,
(a) comme règle générale.

**Q-06 · ~~La facette catégorie doit-elle restituer la hiérarchie ?~~** — **répondue le 2026-09-06 par D-07** :
ni hiérarchie ni liste plate, le niveau courant (`ChildTermsFacet`, `T-06` fait). *Marquée le 2026-09-22.*
Si oui, le modèle éprouvé est un champ par niveau (`facets.product_cat_lvl0/1/2`), ce qui change
la projection d'indexation — donc c'est une décision de lot 1, à prendre avant d'écrire le client.
Si non, il faut assumer une liste plate plafonnée à 30 sur plus de cent termes. *Cite : R-11.*

**Q-07 · ~~`ProductListing` reste-t-il dans le module ?~~** — **répondue le 2026-09-06 par D-01** : il reste,
conditionnel (`R-09` fermé). *Marquée le 2026-09-22.*
S'il en sort, R-09 disparaît, la dépendance WooCommerce du module aussi, et le lot 4 devient un
travail de projet. S'il reste, il faut une garde que la découverte respecte
(`Listing::isAvailable()`).

**Q-08 · Prix et disponibilité : facettes tout de suite sur les produits simples, ou après le lot 4 ?**
Les attributs sont déjà filtrables et triables. Livrer une facette de tranches de prix et une case
« en stock » maintenant donne de la valeur immédiate ; le risque est de la refaire quand les
variations arriveront. *Cite : R-43.*

**Répondu le 2026-09-15 — ni l'un ni l'autre.** La question supposait qu'il fallait choisir entre
servir les produits simples tôt et attendre les variations. `D-b` a supprimé le choix : l'intervalle
par produit a été indexé **d'abord**, le filtre livré par-dessus, donc juste dès le premier jour pour
les 76 produits — variables compris. Livrer avant aurait été faux pour dix d'entre eux, et silencieux.
La forme a changé aussi : min/max et non des tranches (`D-a`). La disponibilité, elle, est bien
différée (`D-f`).

**Q-09 · La recherche texte : on livre le champ, ou on retire `q` du lecteur d'état ?**
L'état intermédiaire actuel — le paramètre agit sans que rien ne l'affiche — est le pire des trois.
*Cite : R-44.*

**Q-10 · La carte du listing est-elle celle du module ou celle du thème ?**
La wishlist a disparu de l'archive produit et la doc annonce l'inverse. *Cite : R-45.*

**Q-11 · Le prix reste-t-il du HTML non filtré ?**
La décision est argumentée et je ne la conteste pas ; il faut soit la confirmer et purger la doc
contradictoire, soit poser le seuil auquel on refiltre. *Cite : R-26.*

**Q-12 · Combien de temps garde-t-on le module en 1.53.1 en local contre 1.10.3 en production ?**
Tant que l'écart dure, chaque recette locale est une hypothèse. Qui porte la montée de version, et
selon quel calendrier ? *Cite : R-41.*

### Conception

**Q-13 · ~~La documentation : on la rapatrie maintenant ?~~** — **répondue le 2026-09-07 : oui**,
faite (R-37). Reste ouverte la seconde moitié — **la réduit-on ?** Ancienne formulation :
1 683 lignes hors du module, avec quatre affirmations fausses relevées aujourd'hui. Trois strates
s'y mélangent : les décisions (à garder, c'est la valeur), les constats techniques sur les
dépendances (à garder, c'est irremplaçable) et le journal de session (à archiver). Qui la lit,
en dehors de nous deux ? *Cite : R-36, R-37.*

**Q-14 · ~~(réorientée par D-01) Le contrat `data-meili` est acquis — comment le rend-on fiable
sans le rendre cher ?~~** — **réglée le 2026-09-07** : `R-23` et `R-25` fermés, `ContractParityTest`
compare les deux côtés, et la vérification tourne toujours, pas seulement en `WP_DEBUG`. *Marquée le
2026-09-22.*
La surcharge par le thème est une exigence, donc le contrat reste. Ce qui est encore ouvert : sa
vérification tourne-t-elle en production ou seulement en `WP_DEBUG` ? Comment garantit-on que les
deux listes ne divergent pas (R-25, voir I-05) ? Et que fait-on des attributs hors contrat que le
client lit déjà — `data-taxonomy`, `data-apply`, `data-listing`, `data-value` (R-23) ?

**Q-15 · ~~Accepte-t-on définitivement les deux implémentations du plan de requête, ou le serveur
émet-il le plan que le client rejoue ?~~** — **réglée** : les deux restent, écrites en dette
(`decisions.md`, « Le plan de requête existe des deux côtés ») ; `T-16` fait. Rien ne la borne encore
(`R-32`). *Marquée le 2026-09-22.*
La divergence est déjà là (R-22). Si PHP sérialise un plan complet en JSON, `ListingQuery`
disparaît, `ListingUrl` reste, et la dette se referme. Le coût : le client ne sait plus recalculer
un plan sans aller-retour — ce qui n'est un problème que si l'on veut chaîner deux filtres sans
recharger… c'est-à-dire tout le temps. À examiner sérieusement plutôt qu'à écarter. *Voir I-01.*

**Q-16 · `Listing` doit-il être scindé en déclaration et contexte de requête ?** *Cite : R-06.*

**Q-17 · Où lit-on la configuration ?** Provider seul, ou objets de domaine ? *Cite : R-05.*

**Q-18 · Un index dédié plutôt que `posts` partagé avec MeiliScout ?**
Aujourd'hui le module impose `displayedAttributes: ["ID","card"]` sur l'index commun, ce qui casse
par construction la recherche `WP_Query` de MeiliScout pour tout autre usage du projet. Un index
propre coûte une duplication d'extraction ; il rend le module indépendant.

**Q-19 · `IndexAttributes` est-il le bon découpage ?**
Un seul contrat porte `filterable()`, `sortable()` et `displayed()` — trois besoins avec trois
cycles de vie. `ConfiguredIndexAttributes` n'implémente d'ailleurs que le troisième et fait suivre
les deux autres.

### Exploitation

**Q-20 · Qui déclenche la réindexation sur changement de taxonomie, et à quel coût ?**
Réindexer tous les descendants d'un terme déplacé peut être long. Hook synchrone, tâche
planifiée, ou commande manuelle documentée ? *Cite : R-12.*

**Q-21 · Que fait le client après un échec, et après plusieurs ?**
Question déjà listée « en attente » dans `decisions.md`. Elle bloque l'écriture du client, donc
elle doit se prendre maintenant : annulation du geste, message, dégradation vers un rechargement
serveur ?

**Q-22 · Accepte-t-on l'explosion du cache Varnish ?**
Chaque combinaison de filtres devient une entrée de cache 180 s, et tout visiteur portant un cookie
panier passe en `pass`. Sur trois facettes à 9, 24 et 30 valeurs, l'espace de clés est déjà très
grand. Faut-il déclarer les URLs filtrées non cachables plutôt que de les cacher toutes ?

**Q-23 · Les noms de paramètres d'URL (`marque`, `contenance`, `etiquette`, `selection`) sont-ils
validés côté client et SEO ?** Ils partent dans des URLs indexées ; les changer casse des liens.
`meilifacets:check-parameters` valide qu'ils ne collisionnent pas, pas qu'ils sont les bons.

**Q-24 · Le mode d'application par défaut : `submit` ou `immediate` sur ce catalogue ?**
*Cite : R-51.*

**Q-25 · Jusqu'où patche-t-on MeiliScout en amont ?**
`getSearchClient()` (DNS + health à chaque process, pas de timeout, R-19), `IndexingLogger`
(fichier maison hors monitoring), `configureIndices()` (code mort), la contradiction
`terms.*` / `taxonomies.*`. La règle du module — « corriger la dépendance plutôt que la contourner »
— dit de le faire ; le calendrier de la PR en cours dit peut-être l'inverse.

**Q-26 · Qui est responsable des réglages MeiliScout stockés en base (`indexed_post_types`,
`indexed_meta_keys`) ?**
Non versionnés, à refaire par environnement, et ils déterminent les taxonomies projetées. Une
commande d'installation qui les pose serait plus fiable qu'une consigne.

**Q-27 · Les classes CSS en camelCase (`meilifacetsCardImage`) restent-elles la convention ?**
Choix assumé et documenté, mais il isole le module du reste du thème (BEM kebab-case) et il est la
raison d'être de la contre-règle `[hidden]`. Confirmé ?

### Nouvelles, ouvertes par les réponses du 2026-09-06

**Q-28 · ~~Où passe exactement la ligne entre « fonctionnement » et « apparence » dans la feuille
de style du module ?~~** — **répondue le 2026-09-07 : la ligne passe par composant, pas par
propriété.** Ce que le module rend de toutes pièces, il l'habille ; le thème remplace ou désinscrit. Voir
`decisions.md`, « Ce que le module rend, il l'habille ; le thème remplace ».
Ancienne formulation :
D-01 demande une feuille minimale ; `architecture.md` en interdit une. Proposition à valider : le
module pose **uniquement** ce sans quoi le composant est cassé — positionnement et superposition de
la `listbox` de tri, `list-style: none` sur ses propres listes, la contre-règle `[hidden]`, et le
masquage des options fermées. Il ne pose jamais couleur, espacement, typographie, bordure, ni état
de survol. Tout est sous des classes `meilifacets*`, donc désinscriptible d'un
`wp_dequeue_style('meilifacets')`. *Cite : R-53.*

**Q-29 · ~~Mesure-t-on le coût réel de WordPress avant d'aller plus loin ?~~** — **répondue le
2026-09-07, voir D-08.** Pas d'estimation sans mesure : on produit, on mesure ensuite.
Ancienne formulation :
R-54 montre que l'objectif premier n'est tenu sur aucun chemin aujourd'hui, et qu'aucune mesure
n'existe. Une demi-journée de chronométrage sur un catalogue réel (rendu nu, rendu filtré, avec et
sans allègement de la requête principale par `pre_get_posts`) dirait où est réellement le temps —
et si le client JavaScript est bien le prochain gain, ou seulement le plus visible.

**Q-30 · ~~Allège-t-on la requête principale de l'archive ?~~** — **différée le 2026-09-07 (D-08)**,
en attente d'un catalogue réel en local. Ancienne formulation :
Question déjà listée « en attente de validation » depuis l'ouverture du projet, jamais reprise.
Elle devient centrale au vu de D-04 : c'est elle qui porte l'essentiel du coût du premier rendu.
Réserves connues : le gain n'est pas mesuré (Q-29), et une archive sans post peut basculer en 404.

**Q-31 · Les commentaires de rôle et les pointeurs vers le miroir PHP restent-ils ?** — ouverte le
2026-09-17. Les passes rejouées de `R-120`, `R-135`, `R-136` et `R-137` relèvent une quarantaine de
commentaires que le tableau du `CLAUDE.md` supprimerait : des docblocks qui disent le rôle d'une classe
(`SortCombobox`, `ListingBinding`, `ListingPage`, `FacetCounts`, `SliderKeys`…), des justifications
(`price-control.ts`, `type-ahead.ts`, `listing-url.ts`, `PageAddress.php`, `CurrentListing.php`,
`ListingDescription.php`…) et douze pointeurs « The browser's copy of… » / « Mirrors… » (`facet-query.ts`,
`sort-query.ts`, `filter-expression.ts`, `range.ts`, `price-query.ts`, `price-bound.ts`, `page-window.ts`,
`listing-query.ts`, `listing-state.ts`, `contract.ts`). Les docblocks de rôle existent dans tout le module :
les retirer de ces seuls fichiers rendrait le reste incohérent. Deux voies : appliquer le tableau à tout le
module, ou écrire l'exception (un docblock de rôle d'une ligne, un pointeur vers le miroir qui tient la
parité lisible). Les commentaires devenus faux ou inexacts sont déjà corrigés ou retirés.

---

## 11. Roadmap proposée

### File d'attente au 2026-09-24

Un point à la fois (`D-03`), dans cet ordre, sauf décision contraire de Louis :

1. **Retours de la PR #2 et suites** : `R-146`, `R-147`, `R-148`, `R-149`, `R-154`, `R-158` et `R-03`
   sont fermés et commités (`3ee3edf`, `93c7703`, `7d7eab3`, `618a697`, `6939e4f`, `7046e7b`, `cd3f5b5`,
   `c8345cc`, `cd10de6`). `R-142` et `R-143` sont fermés et commités le 2026-09-24
   (`ccbc945`, `64c5569`, `127c23b`, `7cc871b`).
2. `R-141` — cinq tests `Feature` dépendent de l'ordre de la suite.
3. `Q-31` — commentaires de rôle et renvois vers le miroir PHP : à trancher par Louis avant toute purge.
4. `R-145` — les restructurations relevées par les passes rejouées, ligne par ligne.

Le chantier courant est **`R-48`** — le rendu mobile des filtres, ouvert le 2026-09-24 : un seul rendu
présenté autrement, décidé par Louis le jour même (« même système, je ne veux pas de doublons »). Il a
fermé `R-95` sans code et ouvert `R-162`. Suivi dans [chantier-filtres.md](chantier-filtres.md) :
architecture v2 validée, décisions reportées le 2026-09-24. Ordre des étapes : `R-163` (2a), `R-47`
(2b), `R-162` (2c), `R-164` (3), puis repliable, tiroir, accessibilité, animations, habillage.

Ouverts, non planifiés : `R-150` à `R-153`, `R-155`, `R-156`, `R-157`, `R-159` à `R-161`. Ces trois
derniers viennent de `R-158` et relèvent du lot 5, avec la pertinence.

*Ce qui suit, jusqu'aux tableaux, est le plan du 2026-09-06, gardé comme historique : l'ordre courant
est la file d'attente ci-dessus.*

Le découpage en sept lots reste valable. Ce qui change : **le lot 3c ne s'ouvre pas tant que le
rendu qu'il contractualise n'est pas juste.** Écrire le client sur un socle qui a R-10, R-11 et
R-46 revient à figer ces défauts dans un contrat versionné.

Trois chantiers avant de reprendre le fil des lots.

> **Méthode retenue (D-03) : un point à la fois.** Une entrée R ouverte, discutée, corrigée,
> testée, documentée, fermée — puis la suivante. Les tableaux ci-dessous sont une file d'attente,
> pas un plan de sprint.

**Ordre de départ proposé**, si rien ne s'y oppose : T-02 (forme du listing) puis T-31, parce que
tous deux changent le markup que le contrat fige — donc tout ce qui est fait avant eux serait à
refaire. Ensuite T-33, qui dira si le prochain gain est bien le client. T-03 peut être traité en
parallèle : il ne touche pas au rendu.

### Chantier A — décider (bloquant, aucune ligne de code)

| Id | Tâche | Ferme | État |
| --- | --- | --- | --- |
| T-01 | Cadrage : rôle du module, dépôt, méthode | R-38 (partiel) | **fait** — D-01, D-02, D-03 |
| T-02 | Trancher Q-05, Q-06, Q-10 (forme du listing) | R-10, R-11, R-45 | partiel — Q-05 et Q-06 réglées par D-07 ; reste Q-10 |
| T-03 | Trancher Q-03 et Q-11 (modèle de sécurité) | R-26, R-27, R-28 | à faire — Q-11 tranchée dans `decisions.md`, non marquée (R-26) |
| T-04 | Fixer le calendrier de montée de version du moteur (Q-12) | R-41 | à faire |
| T-31 | Fixer la ligne fonctionnement / apparence de la feuille de style (Q-28) | R-53 | **fait** — Q-28 répondue, R-53 fermé |
| T-32 | Fixer le critère de recette du socle (Q-04b) | — | à faire |
| T-33 | Mesurer le coût WordPress d'une URL de listing (Q-29, Q-30) | R-54 | partiel — mesure locale de D-08 ; catalogue réel attendu |
| T-35 | Outiller la méthode : `CLAUDE.md` v2, agents `conformity` et `module-review` | — | **fait** — D-05 |
| T-38 | Rendre le module vérifiable seul : `require-dev`, `phpunit.xml`, `pint.json`, scripts | R-55 | **fait** — `composer check` vert, sans chemin |
| T-36 | Hook `Stop` exécutant `composer check` | R-55 | **fait** — `.claude/settings.json` du module, versionné, sans chemin machine |
| T-37 | Purger les commentaires que la règle réécrite condamne | D-05 | **fait** sur le lot 3c-2 — 86 → 49 lignes de prose, plus aucun bloc de plus d'une ligne |

### Chantier B — rendre le socle serveur juste

| Id | Tâche | Ferme | État |
| --- | --- | --- | --- |
| T-05 | Comptage disjonctif selon la décision de Q-05 ; radio décochable ou navigation par liens | R-10 | **fait** |
| T-06 | Facette catégorie : hiérarchie ou limite écrite | R-11 | **fait** |
| T-07 | Bouton de dépliage : crochet, vue, contrat, version | R-46 | **fait** — R-46 fermé, plus R-82 à R-86 relevés en chemin |
| T-08 | Filtres actifs en puces retirables | R-47 | à faire — tranché le 2026-09-24 (C-7), étape 2b du chantier `R-48` |
| T-09 | Réindexation sur `edited_term`, `delete_term`, `set_object_terms` | R-12 | partiel — fait en amont par MeiliScout, sauf la suppression d'un terme |
| T-10 | Timeout explicite sur le client Meilisearch + client construit par le module, pas par `ClientFactory` | R-19 | à faire |
| T-11 | `IndexingPolicy` ne décide qu'en présence d'un listing | R-16 | **fait** |
| T-12 | ~~`Hook::from()` tolérant~~ (refusé, `R-17` accepté) ; `array_combine` protégé ; hit sans `card` journalisé | R-17, R-14, R-13 | à faire |
| T-13 | `countId()` porte le nom du listing | R-15 | **fait** |
| T-14 | Tests du câblage : bridge, indexable, moteur, découverte, listing résolu | R-30, R-31 | partiel — voir R-30 |
| T-15 | `preconnect` vers `MEILI_PUBLIC_URL` | R-52 | **fait** |
| T-34 | Feuille de style du module, selon la ligne fixée en T-31 — **tri, facettes, boutons et mise en colonnes des résultats livrés** | R-53 | **fait** — R-53 fermé |

### Chantier C — livrer le client (ex-lot 3c)

| Id | Tâche | Ferme | État |
| --- | --- | --- | --- |
| T-16 | Décider la forme du contrat de données serveur → navigateur (Q-15) | R-21 | **fait** |
| T-17 | Sérialiser connexion et description du listing | R-21 | **fait** |
| T-18 | Point d'entrée, inscription du script, démarrage sur vérification du contrat | R-20 | **fait** |
| T-19 | Les cinq gestes, le repeint, `popstate` | R-20 | **fait** |
| T-20 | Aligner `toggle()` sur la sélection mono, dédoublonner et plafonner côté JS | R-22 | **fait** |
| T-21 | Test croisé `Hook` / `contract.js` et `QueryPlan` / `ListingQuery` | R-25, R-32 | moitié faite — `ContractParityTest` ; rien pour le plan (R-32) |
| T-22 | Comportement après échec, simple et unique (Q-21) | — | à faire |
| T-23 | Retirer `Hook::PageTemplate` ou le rendre | R-24 | **fait** |

### Puis, dans l'ordre des lots

- **Lot 4** — prix, stock, variations. **Moitié prix livrée** le 2026-09-15 (`R-43` fermé), taxes
  tranchées le 2026-09-22 (`D-e`, `R-146`) ; restent le stock et les variations, différés par `D-f`
  et `D-g`.
- **Lot 5** — recherche et suggestions. Prérequis : Q-09, et `searchableAttributes` (R-27).
- **Lot 6** — diagnostics. `meilifacets:doctor`, canal de log, messages avec `errorCode` /
  `errorLink`.
- **Lot 7** — réutilisabilité. Dépendait de Q-01, répondue par D-01.

### Nettoyage, à faire au fil de l'eau

| Id | Tâche | Ferme | État |
| --- | --- | --- | --- |
| T-24 | Supprimer les déclarations jamais lues | R-33 | **fait** |
| T-25 | Supprimer les `.gitkeep` et `.playwright-mcp` | R-34 | à faire |
| T-26 | Corriger les quatre affirmations fausses de la doc | R-36 | **fait** |
| T-27 | Fermer la dette `product_tag => tag` (déjà corrigée en config) | R-36 | **fait** |
| T-28 | Extraire un `QueryPlan` instanciable ; mémoïser `facets()` et `sorts()` | R-01, R-07 | moitié faite — mémoïsation faite, `QueryPlan` toujours statique |
| T-29 | Objet `SearchRequest` typé à la place du tableau de plan | R-02 | à faire |
| T-30 | Déplacer `Contract` hors de `Enums` | R-03 | **fait autrement** — `Contract` devient un enum (2026-09-22) |
| T-39 | Versionner les modules ES importés : publication dans un répertoire portant l'empreinte | R-70 | **sans objet** — remplacé par l'empaquetage le 2026-09-16 (`R-132`) |
| T-40 | Ramener le regard en haut du listing après pagination et tri | R-73 | **fait** |
| T-41 | Rendre l'ordre d'une facette réglable en configuration | Q-07 (partiel) | **différé après livraison** — décidé le 2026-09-08 |
| T-42 | Sortir le module du dépôt imbriqué : ignoré, sous-module ou paquet composer | R-38 | **différé en fin de projet** — décidé le 2026-09-08 |

#### T-41 · Ordre d'une facette réglable en configuration

**Différé volontairement le 2026-09-08 : à reprendre s'il reste du temps après la livraison.**

Aujourd'hui l'ordre est une propriété de `Facet`, construite dans `ProductListing::facets()`, donc
du code du module. Vérifié : `ResolvedListing::facets()` ne fait que renvoyer
`$this->listing->facets()`, et le module ne compte qu'un seul `apply_filters` en tout — celui de
WooCommerce, dans `PageSize`. **Ni le thème ni le projet ne peuvent demander « cette facette, par
nombre de résultats »** sans surcharger la vue et trier dans le gabarit, ou sans redéclarer un
`Listing` entier dont la victoire sur celui du module dépendrait de l'ordre de découverte.

C'est un angle de `Q-07` : si le listing produit vivait côté projet, la question ne se poserait pas.

**Forme retenue si on le fait** — la même que les six réglages existants : déclaré côté projet, lu
**dans le provider** avec son défaut, injecté en objet de valeur.

```php
'facet_order' => ['pa_contenance' => 'declared', 'product_brand' => 'count'],
```

Deux conditions, sans lesquelles ça devient un piège :

1. **`DisplayOrder` adossé à des chaînes** (`enum DisplayOrder: string`), pour que `tryFrom()` fasse
   retomber une valeur inconnue sur le défaut au lieu de jeter ;
2. **le listing lit le réglage au lieu d'être écrasé en douce** —
   `order: $this->orders->of(self::SIZE, DisplayOrder::Declared)` plutôt qu'une surcharge
   invisible. Une valeur écrite en code qu'un fichier ailleurs contredit sans le dire est
   précisément ce que la session du 2026-09-08 a passé son temps à retirer.

`ProductListing` étant construit par la découverte via le conteneur, l'injection par constructeur
suffit : la règle « jamais de config lue depuis un objet de domaine » tient.

**Limites à écrire dans `configuration.md` le jour où c'est livré** : la config nomme les trois
ordres livrés, un `ValueOrder` sur mesure reste du code ; et la clé étant une taxonomie, elle est
globale à tous les listings — sans objet avec un seul, à revoir avec deux.

**Écarté au passage** : un filtre `meilifacets/facets`. Reconstruire une `Facet` dans un filtre
oblige à réénumérer sept arguments de constructeur pour en changer un, et c'est un réglage, pas un
comportement — les filtres sont pour le comportement.

---

## 12. Idées

**I-01 · Le serveur émet le plan, le client le rejoue.**
Plutôt que deux implémentations du plan de requête, PHP sérialise un `SearchRequest` complet dans
la page ; le client n'a plus qu'à substituer l'état (facettes cochées, page, tri) dans une
structure qu'il ne construit pas. `ListingQuery` disparaît, `ListingUrl` reste. Ferme R-22 et une
dette explicitement acceptée. À confronter à Q-15.

**I-02 · Tenant token Meilisearch.**
Un token dérivé de la clé de recherche, portant des `searchRules` qui imposent
`post_type = "product" AND post_status = "publish"`. C'est le seul moyen de rendre opposable un
filtre côté navigateur, et ça ferme R-28, R-27 et la question des brouillons d'un coup. Coût : le
token a une expiration, donc un point d'émission côté PHP — ce que la page fait déjà.

**I-03 · ~~Facette hiérarchique par niveau.~~** — *sans objet depuis D-07 (niveau courant). Marquée le 2026-09-22.*
`facets.product_cat_lvl0/1/2` à l'indexation, façon Algolia. Rend R-11 solvable, permet de ne
montrer que le niveau courant et ses enfants, et supprime le mélange de niveaux. Décision de lot 1,
à prendre avant le client.

**I-04 · `searchCutoffMs` côté moteur.**
Réglage d'index, non posé aujourd'hui (vérifié : `null`). Il borne le temps de recherche du moteur
lui-même et rend une réponse dégradée plutôt qu'une attente. Complément naturel du timeout PHP de
T-10.

**I-05 · ~~Un test qui compare les deux contrats.~~** — *réalisée : `ContractParityTest`. Marquée le 2026-09-22.*
Un test PHP qui lit `contract.js`, en extrait les crochets et la version, et les compare à `Hook` et
`Contract::VERSION`. Quinze lignes, et la dette « deux listes qui divergent en silence » cesse
d'exister.

**I-06 · `meilifacets:doctor` avant le lot 6.**
Une commande qui vérifie : moteur joignable, index existant et non vide, `filterableAttributes`
contenant chaque facette déclarée, un document témoin portant `facets` et `card`, écart entre le
nombre de produits publiés et le nombre de documents. Trois maillons sur quatre échouent sans
exception : c'est le seul outil qui les rend visibles, et il servirait dès maintenant.

**I-07 · Un mode dégradé sans JavaScript, ou l'assumer une bonne fois.**
Aujourd'hui le listing servi est complet mais totalement inerte. C'est une décision prise ; elle
mérite d'être revérifiée à la lumière de R-46 (des valeurs dans le HTML que rien ne peut révéler) et
du fait que la pagination et le tri, qui **étaient** fonctionnels, ont été retirés.

**I-10 · Un vrai traducteur en test plutôt qu'un repli inerte.**
`illuminate/translation` en `require-dev` et un `__()` de bootstrap qui le résout donneraient deux
choses que le repli actuel ne donne pas : les remplacements nommés appliqués, et surtout la
possibilité de **tester `lang/fr.json`** — aujourd'hui aucun test ne vérifie qu'une clé du
catalogue existe ni qu'elle est bien formée. À ouvrir le jour où une chaîne à placeholder entre
dans la suite autonome. Voir D-06.

**I-09 · Un `TestCase` propre au module, pour rendre la suite `Feature` autonome.**
`CardComponentTest` et `ResultsComponentTest` n'ont besoin que du moteur Blade, pas de WordPress ni de
l'application complète : `illuminate/view` monté à la main en `require-dev` suffirait. Le module
vérifierait alors ses vues partout, et non seulement dans Pluralia. Ouvert par T-38.

**I-08 · Mesurer avant d'arbitrer sur le catalogue réel.**
Trois décisions ouvertes attendent le catalogue de production (compteurs sous variations, mode
d'application, taille des facettes). Rapatrier un dump récent en local coûte moins cher que de
continuer à décider sur 76 produits sans variations.

---

## 13. Journal

- **2026-09-06** — Revue complète du module à la demande du projet. Lecture intégrale du code
  (~2 200 lignes applicatives, 1 400 lignes de tests, 7 fichiers ES), des six documents de
  `docs/meilifacets/`, et vérifications sur l'environnement local : suites de tests (121 PHP,
  49 Node, vertes), réglages réels de l'index, rendu HTTP de `/boutique` filtré et non filtré,
  hooks effectivement enregistrés, découverte automatique d'un `Listing` prouvée par une classe
  jetable, dépôt git imbriqué confirmé. 52 constats, 27 questions, 30 tâches consignés.
- **2026-09-06** — Quatre réponses de cadrage en séance : D-01 à D-04. Q-01, Q-02 et Q-04 fermées,
  Q-03 reformulée, Q-14 réorientée. Deux constats ouverts par ces réponses — R-53 (le module ne
  livre aucun style alors que D-01 en demande un) et R-54 (l'objectif de latence n'est tenu sur
  aucun chemin, et n'a jamais été mesuré). Trois questions nouvelles : Q-28, Q-29, Q-30. Total :
  54 constats, 4 décisions, 30 questions, 34 tâches.
- **2026-09-06** — Méthode de livraison outillée (D-05, T-35). Mesure à l'origine de la décision :
  148 blocs PHPDoc, 57 porteurs de prose dont une trentaine condamnés par une règle déjà écrite ;
  `@return list<string>` répété 12 fois pour trois informations. `CLAUDE.md` du module réécrit
  (45 → 132 lignes), deux sous-agents ajoutés dans `.claude/agents/`. Le hook est **différé** :
  chercher à l'écrire sans dépendre de Pluralia a fait apparaître R-55 — le module n'a ni
  `require-dev`, ni `phpunit.xml`, ni `pint.json`, et deux de ses tests dépendent du `TestCase` du
  projet. Total : 55 constats, 5 décisions, 30 questions, 38 tâches.
- **2026-09-06** — T-38 livré, R-55 fermé. Le module a ses propres `require-dev`, `phpunit.xml`,
  `pint.json`, `rector.php`, `tests/bootstrap.php` et scripts Composer ; `composer check` est vert
  et ne dépend d'aucun chemin. Rector appliqué sur trois fichiers, quatre de ses règles écartées
  nommément. Boucle de retour : 38 ms pour 103 tests autonomes, contre 2,9 s via le projet. La
  suite du projet reste verte (121 tests). I-09 ouvert. D-06 : le repli `__()` du bootstrap de
  test est assumé et documenté, après vérification qu'aucun précédent n'existe dans le projet —
  `Modules/Wishlist` n'a aucun test, et `pluralia-fulfillments` fait le choix inverse en logeant
  les siens dans le projet. I-10 ouvert. T-36 posé dans la foulée : hook `Stop` exécutant
  `composer check`, versionné dans le module. Il est bloquant — une vérification rouge empêche de
  rendre la main et son sortie remonte ; à retirer de `.claude/settings.json` si ça gêne.
- **2026-09-06** — T-02 ouvert sur maquette cliente : D-07, `ChildTermsFacet`. `product_cat` et
  `category` mappés sur `categorie` dans `config/meilifacets.php`. Puis trois corrections livrées —
  **R-09**, **R-16** et **R-58** fermés, sans aucune addition au contrat `Listing`. Deux constats
  ouverts au passage, R-56 et R-57, ce dernier reformulé le jour même après une mauvaise lecture de
  la règle du projet : elle est **temporelle** — WordPress au premier rendu, jamais au filtrage —
  et non catégorielle. 104 tests PHP, 49 tests Node, `composer check` vert.
- **2026-09-07** — Lot 3c-2 livré : synchronisation des cases, tri au clavier complet, pagination,
  remise à zéro. `ListingBinding` redevient un câblage — quatre vues portent le rendu
  (`ResultsView`, `FacetsView`, `PaginationView`, `SortCombobox`), aucune ne dépasse 110 lignes.
  Distinction nouvelle dans `Listing` : un filtre attend un envoi, **trier, paginer et remettre à
  zéro s'appliquent sur place dans les deux modes** — ce ne sont pas des filtres qu'on rassemble.
  `SortCombobox` implémente le motif ARIA « combobox select-only » : flèches, Origine/Fin, Entrée,
  Espace, Échap, Tabulation, recherche par frappe, `aria-activedescendant`, focus qui ne quitte
  jamais le bouton. Feuille de style du module complétée au strict nécessaire (liste en surcouche,
  couleurs système, marque sur l'option active) — le `<select>` natif rendait ça gratuitement.
  R-69 fermé au passage : `happy-dom` installé, 49 → **102 tests Node**, 131 tests PHP autonomes,
  149 via le projet. Recetté en navigateur, cache vidé : pagination (URL, page courante, entrée
  d'historique), tri au clavier (`?sort=price_desc`, focus rendu, grille repeinte, retour en
  page 1), filtre + « Appliquer » + « Tout effacer », et retour arrière qui recoche les cases.
  Une divergence trouvée en relisant : `PageWindow` bornait `current`, `Pagination` non — corrigée,
  R-61 atténué sans être fermé. Trois décisions consignées dans `decisions.md`.
  **R-70 ouvert** — et c'est le vrai enseignement de la séance : seul le point d'entrée porte un
  `?ver`, les dix-sept autres fichiers sont figés un an dans le navigateur. Le lot a d'abord semblé ne
  pas fonctionner pour cette seule raison. T-39 posé, décision à prendre hors lot.
- **2026-09-07** — Les cinq passes passées sur le lot 3c-2 par `module-review`, et **le lot repris**.
  Verdict initial : « à reprendre ». Corrigé : **R-71** (le focus jeté hors du document en fin de
  pagination, 🔴) ; `Pagination::next()` qui valait `0` sans résultat ; la divergence des deux
  miroirs — `WINDOW`/`SIZE` et `window()`/`slots()` alignés sur `slots()` des deux côtés, la constante ne restant qu'en PHP, et
  surtout **un jeu d'essai unique**, `tests/pagination-cases.json`, lu par `PaginationTest` et par
  `page-window.test.js` : les deux copiaient les mêmes cas à la main, c'est ce qui avait laissé
  passer la divergence du clampage. `FilterSummaryView` extrait — la liaison ne peint plus rien
  elle-même. `FacetsView` mémoïse ses nœuds (cocher une case coûtait ~123 requêtes DOM sur six
  facettes de vingt valeurs). `ListingUrl` refuse un tri que le listing ne déclare pas, comme
  `StateReader` — sans quoi un `?sort=` forgé laissait la commande annoncer un tri jamais appliqué.
  `SortCombobox` garantit un `id` par option, `aria-activedescendant` en dépendant sans que
  `Contract` le vérifie. Le CSS du module s'accroche désormais à `data-meili` et non aux classes,
  qu'un thème peut légitimement remplacer. `EngineLimits` passe en `scoped`. Dix blocs de prose
  supprimés — le lot n'en avait effacé aucun. `ResultsComponentTest` renommé `ResultsComponentTest` : le
  nom désignait deux choses depuis l'arrivée de `results-view.js`.
  **Refusé en connaissance de cause** : les cinq `closest()` par clic (un gestionnaire de clic n'est
  pas un chemin chaud, il s'exécute une fois par geste) ; l'écouteur `click` du document jamais
  retiré (le contrôle vit aussi longtemps que la page) ; la borne haute de `page` à l'entrée, qui
  appartient à R-61 et non à ce lot. Ajouté à R-42 : le module **déclare** son plafond sans le
  **poser** sur l'index — les deux valeurs sont tenues à la main, c'est documenté en avertissement.
  133 tests PHP autonomes, 151 via le projet, 131 Node. Recette navigateur refaite, cache navigateur
  **et** cache minifié de WP Rocket vidés — ce dernier servait encore l'ancien CSS.
- **2026-09-07** — Trois oublis du même genre que `PageWindow.SLOTS`, trouvés en cherchant
  systématiquement les valeurs écrites des deux côtés. Deux **replis morts** supprimés du client —
  le préfixe `f_` et les noms des paramètres réservés — parce que le serveur publie toujours
  `params` et `reserved` en entier ; ils masquaient deux fixtures déclarant `reserved: {}`, que
  rien ne produit. Six **jumeaux légitimes** entrés dans `ContractParityTest` (`data-meili`,
  l'attribut de version, le préfixe `facets.`, le séparateur de valeurs, la borne de recherche, la
  première page), vérifiés en les mutant un à un. R-25 fermé. Passe de compaction des commentaires :
  86 → 49 lignes de prose hors tests, **plus aucun bloc de plus d'une ligne** ; le cas de
  `page-window.js` est exemplaire — deux de ses quatre lignes étaient devenues fausses depuis que le
  client compte les boutons. R-74 ouvert sur l'historique du tri. 133 tests PHP autonomes, 151 via
  le projet, 116 Node ; chaîne complète recettée en navigateur, sans erreur console.
- **2026-09-07** — **Le tri est livré habillé.** Prototype de trois défauts neutres comparés dans un
  harnais (bordé, fantôme, ancré), « ancré » retenu : bordure sur le déclencheur, panneau solidaire,
  filets entre les options, coche sur l'ordre choisi, teinte au survol, ouverture animée, `Escape` et
  clavier inchangés. Rien de tout cela ne décide de la typographie ni de la couleur — `font: inherit`,
  dimensions en `em`, `currentColor` / `Canvas`, trois teintes en `color-mix` exposées en variables sur
  `[data-meili="sort"]`. Le libellé « Trier par » est masqué visuellement, sans quitter le nom lu
  (`clip-path`, jamais `display: none`) : le déclencheur répète déjà la valeur.
  **Q-28 répondue, R-53 fermé, T-31 fait** — mais hors de l'ordre annoncé (T-02 devait passer avant)
  et **pendant que 3c-3 est ouvert**, donc contre D-03 : c'est une décision prise en connaissance de
  cause, pas un oubli. `architecture.md` disait encore « le module ne pose aucun style » ; corrigé.
  Le balisage d'essai de `tests/js/dom.js` ne portait pas le `<label>` que rend `sort.blade.php` —
  ajouté, avec son `aria-labelledby`, et un test qui échoue si le masquage repasse à `display: none`.
  La colonne de facettes suit dans la même feuille : espacements, listes sans puces ni indentation,
  case et libellé alignés, compteur poussé en fin de ligne à `0.85em`. Deux tests de plus, dont un
  qui a d'abord échoué à tort — `happy-dom` ne dérive pas `list-style-type` de la forme courte
  `list-style`, il faut interroger la propriété raccourcie.
  Les cinq boutons du module suivent : une règle partagée, page courante marquée par `aria-current`,
  et « Appliquer » en plein `CanvasText` sur toute la largeur de sa colonne — le seul geste qui
  engage. Les trois teintes montent sur `[data-listing]` — le tri les gardait pour lui.
  133 tests PHP autonomes, 150 via le projet, 123 Node. Recette navigateur sur `/boutique` : tri
  ouvert, choisi, URL et grille suivies, aucune erreur console — assets republiés et `cache/min` de
  WP Rocket vidé, sans quoi c'est l'ancienne feuille qui est servie. **Et vider `cache/min` ne
  suffit pas** : l'URL minifiée garde son `?ver`, donc le navigateur ressert sa copie mémorisée —
  une vraie recette demande le cache navigateur vidé, sinon on mesure l'ancienne feuille en croyant
  la nouvelle inerte.
- **2026-09-07** — Passe de finition sur la feuille, mesurée dans le navigateur avant et après.
  Le vrai défaut n'était pas le détail mais la **mise en page** : sans règle sur `[data-meili="results"]`,
  quarante cartes s'empilaient en une colonne — 6674px de page, 4547px pour la seule liste. Grille en
  `auto-fill` : trois colonnes et 3270px. Le reste est du métier de composant — `:active` en
  `scale(0.97)`, survol derrière `(hover: hover) and (pointer: fine)`, cibles élargies sous
  `(pointer: coarse)` (ligne de facette 28 → 35px au doigt), chevron et panneau resynchronisés à
  160ms, transition de `border-radius` retirée (les angles se ré-arrondissaient après la disparition
  du panneau), courbe `cubic-bezier(0.23, 1, 0.32, 1)` au lieu de l'`ease-out` natif. La liste de tri
  quitte le `@keyframes` pour une transition avec `@starting-style` et `display allow-discrete` :
  elle a maintenant une **sortie** animée, et une ouverture interruptible. Mesuré : à +60ms après la
  fermeture, `display: block` et `opacity: 0.097`, puis `none`.
  **Reste ouvert et hors module** : sur mobile la colonne de facettes passe avant les produits — on
  déroule quarante valeurs avant la première carte (R-48), et le contrôle de tri est seul, aligné à
  gauche au-dessus de la colonne de facettes, parce que la barre du thème est un
  `flex justify-between` dont le second enfant est masqué tant que rien n'est filtré.
  **Reprise le même jour, après relecture critique** : la première passe avait posé les règles sans
  mesurer les boîtes. Trois défauts, tous réels — « Tout effacer » à 38px et 18px de texte, parce
  que le raccourci `font: inherit` du bloc bouton écrase le `font-size` posé plus haut et que ce
  bouton est le seul à ne pas vivre dans un conteneur déjà mis à l'échelle ; déclencheur à 32,8px
  contre 30px pour les boutons, deux paddings pour des commandes côte à côte ; et des numéros de
  page de largeurs différentes (30,5 et 32,1px), les chiffres n'étant pas tabulaires. Corrigé par
  une primitive unique — `inline-flex` centré, `--meili-control` en hauteur partagée, `min-width` et
  `tabular-nums` sur les numéros. Mesuré après : tout à 33,6px, numéros carrés.
  **Troisième passe, même jour** : la case à cocher. Mesurée au `TextMetrics` — son centre tombait
  1,9px sous le centre optique du libellé, et sur un libellé de deux lignes le centrage la posait au
  milieu du bloc. Accrochée à la première ligne, portée à `1em`, `accent-color` sur `currentColor`.
  Écart après : 0,85px. Le test a attrapé au passage un défaut de portabilité que le navigateur
  cachait : un `<input>` n'hérite pas de la taille de police, donc `1em` valait 13,3px partout où le
  thème ne remet pas les polices de formulaire à plat — Pluralia le fait, d'où l'illusion.
  Le compteur passe en `white-space: nowrap` : sur un libellé long, « 14 résultats » se coupait.
  **Alignement de la barre** — le tri était posé au-dessus de la colonne de facettes (69,5 → 367)
  alors qu'il commande la grille (399 → 1355), et le texte de son déclencheur, décalé de 12px par
  bordure et padding, ne tombait sur rien. Corrigé **dans le thème** (`archive-product.blade.php`) :
  la barre entre dans la colonne des résultats. Mesuré : tri à `x = 399`, « Tout effacer » à
  `1355,5` — les deux arêtes de la grille. Le badge de filtres actifs, jusque-là un chiffre nu
  flottant au-dessus de « Catégorie », prend une pastille neutre. Il a cessé entre-temps d'être un
  chiffre : la vue rend désormais une phrase, donc la pastille est dimensionnée par son texte et non
  ronde. Son centrage est optique et non géométrique — `padding: 0.47em 0.75em 0.23em`, l'espace du
  jambage étant compensé en haut : écart entre le centre de la boîte et le centre des lettres ramené
  de 2,02px à **0,27px**, gouttières gauche et droite à 10,5px chacune. R-47 n'est pas fermé pour
  autant : une pastille bien centrée ne remplace pas des puces retirables.
  **Le retour d'appui du déclencheur de tri est retiré.** Le `scale(0.99)` faisait rentrer chaque
  bord de 0,98px (196 → 194,04px mesuré sous `Input.dispatchMouseEvent`), et le panneau, qui s'ouvre
  au relâchement, arrivait à pleine largeur pendant que le déclencheur était encore rétréci : le
  joint des deux bordures se décalait d'un pixel puis se recalait. Une commande soudée à un panneau
  ne bouge pas de géométrie ; elle change de fond (`--meili-press`, 14%). Corollaire trouvé au
  passage : posée avant la requête de survol, la règle `:active` perdait à spécificité égale — le
  fond restait à 8% sous le doigt. Les états d'appui vont après.
  **Les quatre autres boutons vérifiés dans la foulée**, et alignés sur la même règle : ils
  reculaient de 3,3px (« Tout effacer »), 2,97px (« Appliquer »), 2,25px (« Suivant ») et 1px (un
  numéro), ce qui décrochait les deux premiers de l'arête de colonne sur laquelle ils sont alignés —
  et surtout laissait deux langages d'appui dans un même composant, un fond pour le tri et un recul
  pour les autres. Un seul désormais : le fond. Mesuré après, les cinq à déplacement **nul** ;
  transparent → 8% au survol → 14% à l'appui, et 0,15 → 0,30 pour le bouton plein.
  **Survol et focus clavier passés au même crible**, trois trous trouvés : la **page courante** ne
  réagissait pas au survol (8% au repos comme au survol) et une page survolée lui devenait
  identique — elle passe à 14% au repos, 20% au survol ; la **ligne de facette** n'avait aucun
  retour, seulement le curseur — tuile au survol, posée avec `margin: 0 -0.4em` pour que ni la case
  ni le texte ne bougent (mesuré : case et légende toujours à `x = 69,5`) ; et la **case à cocher**
  gardait l'anneau du système, bleu et de 1px, quand les cinq autres commandes ont l'anneau du
  module — `2px solid currentColor`, décalé de 2px, non rogné (vérifié en parcourant la page au
  `Tab`). Dernier point : la position du clavier dans la liste de tri valait 8%, comme le survol, donc
  les deux étaient indiscernables quand la souris reposait sur une autre option ; elle passe à 14%.
  **Les mêmes états au doigt** — ce qui marchait : les règles de survol sont bien hors jeu
  (`(hover: hover) and (pointer: fine)` ne matche pas), rien ne reste collé après le tap, aucun
  anneau de focus résiduel. Ce qui ne marchait pas : **aucun retour à l'appui**, parce que
  `-webkit-tap-highlight-color` vaut `transparent` sur toute la page (hérité de `html`) et qu'iOS ne
  déclenche pas `:active` sans écouteur tactile — c'était déjà vrai du temps du `scale`, personne ne
  l'avait vu. Le module redéclare donc le flash natif à `var(--meili-press)` sur ses commandes ; sur
  le bouton plein il devient blanc à 14% tout seul, `currentColor` y valant `Canvas`. Cibles portées
  à `--meili-control: 2.9em` sous `(pointer: coarse)` : commandes et numéros de page à **40,6px**,
  lignes de facette à **37,8px** (contre 33,6 et 35). Alignement conservé, case et légende à
  `x = 12`, aucun débordement horizontal.
  **Dernier point de la série, signalé à l'œil et confirmé au calcul** : c'était le compteur, pas la
  case. `align-items: flex-start` calait sa boîte plus courte (18,9px contre 21) en haut de la ligne,
  donc son texte flottait 1,5px au-dessus de la ligne de base du libellé. La ligne centre désormais
  ses trois éléments, la case seule gardant `align-self: flex-start` pour rester sur la première
  ligne d'un libellé qui se replie. Mesuré : centres optiques du compteur et du libellé à **0,09px**,
  case à **0,00px** de la bande de capitales.
  Enfin, une taille est désormais décidée — `--meili-ui`, `0.875rem`, sur les commandes seules :
  hériter des 18px de Pluralia donnait des facettes plus grosses que ce qu'elles filtrent. Mesuré
  après : déclencheur et libellés à 14px, compteurs à 12.6px, titre de carte inchangé à 32px.
- **2026-09-07** — Passe de conformité de la documentation, en sous-agent, sur les neuf documents :
  chaque affirmation nommant un symbole, une constante, un nombre ou un chemin vérifiée contre les
  sources. **Douze affirmations franchement fausses** corrigées, dont quatre qui égaraient — la
  commande de test du `README`, `data-apply` donné comme lu par le client, `RobotsPolicy` renommée
  depuis R-59, et le `preconnect` donné comme restant à faire alors que R-52 l'a livré. Le piège le
  plus coûteux tenait à deux documents ensemble : `README` et `installation.md` suivis à la lettre
  donnaient un listing **sans JavaScript**, sans qu'aucun ne dise que le module ne lit ni
  `MEILI_PUBLIC_URL` ni `MEILI_SEARCH_KEY` mais seulement `meilifacets.browser.*`. Trois exigences
  imposées au thème, jamais écrites, entrent dans `architecture.md` : le `name` de l'`<input>`, le
  `data-listing` de la racine — seul démarrage raté qui ne dit rien — et l'élément racine unique du
  `<template>`. **R-04, R-05, R-15 et R-23 fermés** : corrigés par R-62 sans que personne ne le
  note. Treize tâches de la roadmap marquées faites, qui fermaient des constats déjà fermés — un
  lecteur reprenant le module par la roadmap aurait refait du travail livré.

  **Enseignement** : la documentation vieillit plus vite que le code, et sans bruit. Trois documents
  décrivaient un module qui n'existait plus depuis le matin même.
- **2026-09-08** — **R-79 et R-42 fermés**, par un correctif en amont. Enregistrer un seul article
  détruisait les réglages d'index du module — facettes filtrables, `metas._price`, plafond — et
  vidait la boutique jusqu'à la réindexation suivante. La cause était une **séquence** : MeiliScout
  résout son indexable dans un constructeur, exécuté pendant le chargement des plugins, quand aucun
  `#[Filter]` du module n'est encore posé. `AmphiBee/MeiliScout@2acf53a` rend la résolution
  paresseuse ; la `composer.lock` du projet passe de `1c59a05` à `2acf53a`. Deux mesures avant/après
  sur le même geste : `facettes 0 · prix triable NON · 0 carte` → `facettes 14 · prix triable oui ·
  17 cartes`, et le plafond suit désormais la configuration sur les deux chemins d'indexation.
  Deux pièges écrits dans `pieges.md` : la séquence de chargement, et le fait que le plugin installé
  est une **sortie de Composer** — ce que j'avais d'abord lu comme une installation manuelle, en
  concluant à tort qu'un patch local était la seule voie.
- **2026-09-09** — **Revue contradictoire de la PR #1 traitée en entier.** Quatorze constats
  numérotés `R-93` à `R-106` — ils n'existaient nulle part au registre pendant la première journée
  de correction, ce qui était le vrai manque. Douze fermés, `R-95` différé avec le rendu mobile,
  plus deux ouverts en chemin : `R-107` (fermé) et `R-108` (différé).

  **Quatre constats étaient plus étroits que la revue ne l'annonçait, et le dire a compté autant que
  le correctif.** `R-106` : cinq des sept points de lecture ne peuvent pas déraper, le contrat
  `Listing` n'expose pas `remainingFacets()`, le « rangement » redouté ne compilerait pas. `R-103` :
  ce qui fuit est `$apart` et non `$rendered`, donc le groupe sort **vide et silencieux** au lieu de
  lever — pire que décrit. `R-105` : corriger la fixture n'a révélé aucune régression, la condition
  qui manquait était une facette hors groupe, pas les deux `div`. `R-97` : le scénario nommé était
  déclenchable par un éditeur, le résiduel structurel ne l'est que par un développeur.

  **Trois correctifs ne réparent rien d'observable aujourd'hui** — `R-97`, `R-100`, `R-96` — et
  c'est écrit dans chaque entrée. Ils ferment des classes de défauts, pas des pannes.

  **`R-93` était le seul défaut visible sur la page** : quatre règles habillaient la facette depuis
  le groupe, donc une facette déplacée sortait en 16 px avec des puces et le cadre d'un `<fieldset>`
  nu. Corrigé en accrochant les règles à `[data-meili="facet"]`, ce que `decisions.md:186` demandait
  déjà.

  **`R-94` a révélé un trou dans ma propre documentation** : `configuration.md`, écrit la veille pour
  `R-104`, ne contenait pas une fois `x-meilifacets::listing`. Il documentait la liberté de placer
  une facette sans sa seule limite — être dans la racine, faute de quoi elle est inerte.

  **Enseignement de méthode, payé cher.** Plusieurs corrections ont dû être corrigées : une méthode
  triplée pour optimiser un chemin à 0,11 µs, des commentaires paraphrasant le code retirés en deux
  passes, une affirmation sur le Figma écrite sans avoir rouvert les captures — elles disaient
  l'inverse —, et un harnais de mutation qui rapportait « survivant » sans vérifier que la mutation
  s'était appliquée. Trois réflexes en sortent : mesurer avant d'optimiser, relire ses propres
  commentaires avec la table du `CLAUDE.md` avant de livrer, et faire échouer un test exprès avant
  de le croire.
- **2026-09-09** (suite) — **`R-89` validé et fermé.** Louis valide sur constat d'usage : « j'ai bien
  les filtres qui sont ok même en étant détaché ». C'était le point qui engageait le plus — une
  facette sortie du groupe doit continuer de filtrer, ce que `R-106` verrouille désormais par un
  test. Les trois choix que la validation entérine : le nom porté par la facette (un troisième nom
  pour une même chose, après la taxonomie et le paramètre d'URL), l'exception au double rendu
  (`R-95`, différé), et le sous-ensemble arbitraire non livré faute de demandeur.
- **2026-09-22** — **Passe documentaire (`R-153`)**, à la demande de Louis. Le journal n'a pas été
  tenu du 2026-09-10 au 2026-09-21 : les entrées `R-109` à `R-149`, datées, et la file d'attente en
  tiennent lieu — le tenir ou le retirer est à trancher. Six relectures en lecture seule, chaque point
  décisif revérifié dans la source. Le prix, le passage au TypeScript et `resolveIndexable()` n'avaient
  pas atteint `architecture.md` ; `decisions.md` portait des décisions que le code ne tient plus, annotées
  sans être réécrites ; le registre avait fermé `R-05` à tort et laissé répondues cinq questions ouvertes.
