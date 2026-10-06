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

### D-01 — Module du framework Pollora, en construction ; un projet local est le banc d'essai

*Répond à Q-01, oriente Q-04, Q-07, Q-13, Q-14.*

MeiliFacets est un **module Pollora générique**, écrit pour de futurs projets. Le projet de test sert de
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

**Aucun précédent dans le projet, vérifié le 2026-09-06.** L'autre module nwidart du projet ne
pouvait pas servir de modèle : il n'a **aucun test** (`tests/Unit` et `tests/Feature` ne contiennent qu'un
`.gitkeep`), aucun outil, et son `composer.json` est resté le squelette généré par nwidart. Un plugin du
projet, lui, fait le choix inverse : ses tests vivent dans les tests **du projet**,
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

### R-14 · 🟡 · **fermé le 2026-10-01** · ouvert le 2026-09-06 — `multiSearch()` peut lever un `ValueError` non converti

`MeilisearchEngine::multiSearch()` fait
`array_combine(array_keys($queries), array_slice($responses, 0, count($queries)))`. Si le moteur
renvoie moins de réponses que de requêtes, `array_combine` lève un `ValueError` — **hors** du `try`
de `send()`, donc jamais transformé en `SearchFailed`. Résultat : une 500 au lieu de la vue de
repli, exactement dans le cas où celle-ci sert.

**Au 2026-10-01** (audit) : confirmé. `MeilisearchEngine` recevant une réponse sur deux recherches lève
`ValueError: array_combine(): Argument #1 ($keys) and argument #2 ($values) must have the same number of elements`.
Test : Unit `MeilisearchEngineTest::it_counts_a_short_answer_as_an_outage` (client Meilisearch doublé, une réponse pour
deux requêtes) — **rouge avant** (`ValueError` au lieu d'`EngineUnavailable`), vert après ; `it_hands_each_answer_back_under_its_key`
tient le cas nominal. Correctif : une réponse plus courte que la demande lève `EngineUnavailable::incomplete($asked,
$answered)` (« Meilisearch answered 1 of 2 searches sent together. » — pas « did not answer », cf. `R-157`). Les parties
manquantes comptent donc comme une indisponibilité : `ResolvedListing` la rapporte par `report()` et sert le repli
documenté (message, `503`, `Retry-After`, `no-store`), chaîne tenue par `ListingOutageTest` (`R-205`). Pas de rendu
partiel : un compte de facette retombant sur la réponse principale serait faux sans le dire.

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
listing est rendu sur la page. Un lien de campagne `?marque=acme` sur un article, ou n'importe
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

**Recetté en navigateur**, pas seulement en test : sur `/boutique`, cocher « acme » ne fait rien
en mode `submit`, « Appliquer » ramène 16 cartes à 10, l'URL devient `?marque=acme`, et les
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

### R-27 · 🟡 · **fermé le 2026-09-25** · ouvert le 2026-09-06 — `searchableAttributes` reste à `["*"]`

**Vérifié** sur l'index réel : `searchableAttributes: ["*"]`, `displayedAttributes: ["ID","card"]`.
La restriction d'affichage empêche de **lire** `post_content` et les metas ; elle n'empêche pas de
les **cibler**. Avec la clé de recherche publique, un visiteur peut confirmer par recherche
booléenne la présence d'une valeur dans n'importe quel champ indexé — `_edit_lock`,
`_yoast_wpseo_*`, le contenu d'un brouillon s'il en entrait un.

C'est noté « lot 5, question de pertinence ». Ç'en est aussi une de fuite d'information, et elle
n'est pas évaluée comme telle.

**Mesuré le 2026-09-25** (chantier recherche) : `<domaine>` (le nom de domaine du site) trouve les 67 documents
(domaine dans `url`, `guid`, `card.url`, `card.image_url`) ; `spacer` en trouve 4 (balisage des blocs de `post_content`).

**Au 2026-09-25** (`R-181`, non commité) : `searchableAttributes` est une liste explicite — `post_title`,
`labels.*` des taxonomies visibles, `metas._sku`, `excerpt`, `content` ; plus aucune métadonnée technique,
adresse, `card.*` ni `post_content` brut. Fermeture après réindexation et mesure : `<domaine>` ne doit plus rendre tout l'index, une
requête `attributesToSearchOn: ["url"]` ou `["metas._edit_lock"]` doit être refusée par le moteur.

**Mesuré le 2026-09-25 après les deux réindexations de Louis** (lecture seule) : `<domaine>` → 0 ;
`attributesToSearchOn: ["url"]` refusé ; `"spacer"` et `"strong"` en recherche exacte → 0, sans guillemets 3 et
4 résultats par la seule tolérance aux fautes dans `content` ; `attributesToSearchOn: ["metas._edit_lock"]`
refusé (`invalid_search_attributes_to_search_on`). **Fermé le 2026-09-25** sur validation de Louis.

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
- `docs/meilifacets/`, `config/meilifacets.php` et le `archive-product.blade.php` du thème
  évoluent dans un dépôt, le module dans l'autre, sans commit commun.

**Instruit le 2026-09-08, différé en fin de projet** (`T-42`). Mesuré :

```
Modules/<autre>      → 30 fichiers suivis par le projet
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

### R-40 · 🟠 · **fermé le 2026-10-05** (`R-217`) · ouvert le 2026-09-06 — le `503` ne sort pas

*Corrigé le 2026-10-05 (non commité), avec `R-217`* : le listing ne pose plus d'en-tête à la main ;
`ServiceUnavailable::announce()` marque la panne pendant le rendu, et un middleware global du module
(`Http\ServiceUnavailableHeaders`, poussé dans le noyau HTTP par `RenderingServiceProvider`) applique à la réponse
Laravel `503`, `Retry-After: 120` et `Cache-Control: no-store` — global, il enveloppe la pile de Pollora et passe après
`WordPressHeaders`. Mesuré moteur local arrêté puis relancé : `/boutique` et `?contenance=400ml` en `HTTP/2 503`,
`cache-control: no-store, private`, `retry-after: 120`, vue de panne présente ; moteur relancé, `200` et 19 cartes.
Tests : `ServiceUnavailableHeadersTest` (503 et `no-store` une fois la panne levée, page intacte sinon, middleware
enregistré). Reste, hors de ce point : une page saine porte deux `Cache-Control` (`public, max-age=3600` et
`max-age=0`), dont l'origine n'est pas cherchée.

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

### R-44 · 🟠 · **fermé par `R-201`** (en attente de commit, 2026-09-30) · à trancher (Q-09) · 2026-09-06 — la recherche texte est à moitié câblée

`ListingState` porte `query`, `StateReader` la lit et la borne à 200 caractères, `QueryPlan`
l'envoie au moteur, `ListingQuery` aussi. **Aucun composant ne la saisit ni ne l'affiche.** Une URL
`?q=parfum` filtre donc la grille en silence : le visiteur voit un sous-ensemble sans savoir
pourquoi, la remise à zéro est masquée uniquement si l'état est vierge (elle le serait donc
correctement, mais rien ne nomme le terme recherché), et `RobotsPolicy` laisse la page indexable.

Soit on livre le champ, soit on retire `q` du lecteur d'état jusqu'au lot 5.

### R-45 · 🟠 · **fermé par `R-203`** (en attente de commit et de réindexation, 2026-09-30) · à trancher (Q-10) · 2026-09-06 — la carte du module remplace celle du thème, wishlist comprise

`<x-meilifacets::results>` rend `<x-meilifacets::card>`. Le thème, lui, a
une carte produit qui porte le bouton wishlist sur la ligne du titre (un module du projet,
maquette). Sur l'archive produit, **ce bouton a disparu**.

`lots.md` annonce pourtant l'inverse : « la bascule sur [la carte produit du thème] étant assumée ».
Ce n'est pas ce qui est livré. Trois issues : le listing rend la carte du thème, la wishlist
devient un crochet du contrat, ou la disparition est assumée et écrite.

**Au 2026-09-22** (passe documentaire, `R-153`) : toujours vrai, mesuré sur `/boutique` — l'archive rend
`<x-meilifacets::card>`, sans wishlist. **Contredit une décision validée** : `decisions.md` dit
« bascule sur [la carte produit du thème] ». Annoté là-bas comme non tenu ; `Q-10` reste à trancher.

### R-46 · 🟠 · **fermé le 2026-09-08** (T-07) · ouvert le 2026-09-06 — les valeurs repliées n'avaient aucun moyen d'être dépliées

`FacetValues` marque `folded` tout ce qui dépasse `visible` (10), la vue les rend avec `hidden`, et
le cap est à 30. **Il n'existe aucun bouton « voir plus »**, aucun crochet correspondant dans
`Hook`, aucune ligne dans `contract.js`.

**Mesuré le 2026-09-06** sur `/boutique` : 63 valeurs rendues, **34 en `hidden`**, sans aucun moyen
de les atteindre — même avec JavaScript, puisque rien ne sait les révéler. Détail : `product_cat`
30 rendues dont 20 masquées, `pa_contenance` 24 rendues dont 14 masquées, `product_brand` 9
rendues et 0 masquée.

### R-47 · 🟡 · **fermé le 2026-09-24** · ouvert le 2026-09-06 — les filtres actifs ne sont qu'un nombre

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

**Fermé le 2026-09-24** (étape 2b). `<x-meilifacets::active-values>` rend une `<ul>` (crochet
`active-values`, nommée « Filtres actifs ») d'un `<button name value>` par valeur (`active-value`) et un
`<template>` (`active-value-template`, exigé par le contrat avec son `active-value`). Construites par
`View\ActiveValueList` (miroir TS `listing/active-value-list.ts`) : valeurs cochées dans l'ordre des
facettes puis de l'état, puis **une** pastille pour la plage de prix (`R-123`) — « À partir de »,
« Jusqu'à » ou « min – max », formatée par `Money`/`money.ts`. Libellés lus dans les valeurs rendues,
repliées comprises (`ResolvedListing::labelsOf()`, publiés par facette dans la description, `R-57`) ;
une valeur sans libellé n'a **pas** de pastille (un slug de l'URL affiché tel quel écrirait sur la
page). Nom accessible « Retirer le filtre X », croix en `aria-hidden`. **Les pastilles montrent l'état
appliqué, pas la sélection en attente** (tranché par Louis le 2026-09-24) : `ActiveValuesView` repeint
sur l'événement `results`, dont le `state` est celui que le moteur a servi — aucun second état, c'est
celui que `Listing` retient déjà (`#searchedFor`). En `immediate` rien ne change. Toutes les listes sont
peintes (`contract.all()`). Retirer une pastille (`Listing.withdraw()`/`withdrawPrice()`) est **un
ordre** (`D-10`) : recherche immédiate dans les deux modes, qui emporte les filtres en attente — validé
par Louis avec le choix « pastilles = état appliqué ». Le focus reste sur la pastille jusqu'au
redessin qui suit la réponse, puis passe à la suivante, sinon à la précédente, sinon à la racine du
listing (`tabindex="-1"`), jamais `<body>` ; un focus que le visiteur a déplacé entre-temps n'est pas repris.
Poids de la description (`D-08`) : +829 octets (`/boutique` 1871 → 2700, `?marque=globex` 1891 → 2720).
Observé (Chromium, `https://`) : Globex cochée → aucune pastille ; « Appliquer » → « Globex », 10
articles ; Initech cochée (en attente) puis retrait d'Globex au clavier → `?marque=initech`, pastille
« Initech », 12 articles, Globex décochée, focus sur « Initech » ; max 30 € → « Jusqu’à 30,00 € »,
identique au rendu serveur ; aucune erreur console. `composer check` vert (313 tests TS), suite
`Modules` 436 tests verts.

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

**Au 2026-09-25** : tiroir mobile (`R-173`, étape 5a), tri en radios (`R-174`, étape 4b) et pied
« Annuler / Appliquer » (`R-175`, étape 5b) livrés ensemble, non commités ; repliables passés en
mobile first. Revue des animations appliquée le même jour (`R-176`).
Commités le même jour par fonctionnalité (`7b971d8`, `40822fd`, `860d58c`, `d62e8d9`) ; étape 5
fermée, `R-173` → `R-176` fermés. Restent l'étape 4d (`R-49`, « Voir plus » en panneau), puis 6 à 8.
Audit de la branche le même jour : `R-178`, cinq lots (tous faits).
Étape 7 (animations) le même jour : `R-179` — ANIM-3 et ANIM-9 commités, ANIM-10 et refonte non commités, ANIM-13 en question.

**Au 2026-09-25 (fin de session)** : étapes 0 à 5 et 7 du chantier livrées et commitées, audit `R-178`
fermé ; étapes 6 (accessibilité) et 8 (habillage du thème de test) **mises en attente par Louis**. `R-48` reste
ouvert jusqu'à leur reprise : le mobile est couvert (tiroir), l'accessibilité et l'habillage restent à
finir. Suivi : [chantier-filtres.md](chantier-filtres.md).

### R-49 · 🟡 · **fermé le 2026-09-25** (étape 4d-1) · ouvert le 2026-09-06 — le cul-de-sac « zéro résultat » est atteignable en deux clics

Quand la recherche ne rend rien, toutes les distributions sont vides, donc tous les `<fieldset>`
sont `hidden` (`@if ($values === [])`), donc **il ne reste que « Tout effacer »**. Le comptage
disjonctif couvre le cas où l'on relâche une valeur de la facette qui contraint ; il ne couvre pas
le croisement de deux facettes. C'est documenté comme « limite assumée » — mais aucune mesure ne
dit à quelle fréquence le cas est atteint sur un vrai catalogue, et un message « aucun résultat »
sans aucune facette visible est un mur.

**Au 2026-09-22** (passe documentaire, `R-153`) : en partie. Mesuré : `?q=` sans résultat masque les
quatre blocs de facette ; `?marque=globex&categorie=parfum` rend zéro carte, mais les facettes
catégorie et marque restent visibles.

**Au 2026-09-25** (étape 4d-1, barre de pills et tiroir) : **plus de cul-de-sac, sans code neuf.** La
décision validée « une valeur que le visiteur tient reste affichée, même à 0 » (`R-137` #4) couvre le
cas : serveur (`$value->selected`) et client (`FacetsView::#showFold()`, `! input.checked`) gardent la
valeur cochée, donc son `<fieldset>` et sa pill. Mesuré dans Playwright, `immediate` et `submit`, 1440
et 393 px, rendu serveur puis recherche client (Initech décochée dans le panneau Marque) :
- `?marque=globex,initech&contenance=400ml` (0 article) : pills « Trier par », « Marque 2 »,
  « Contenance 1 », valeurs cochées visibles, pastilles actives et « Tout effacer » visibles, ouvreur
  « Filtres » à 393 px, tiroir : mêmes sections, poubelle du pied visible ; décocher Initech → 0 article,
  « Marque 1 », rien ne disparaît ; en `submit`, « Appliquer » du tiroir → `?marque=globex&contenance=400ml` ;
- `?min_price=60&max_price=80` : pill « Prix 1 » (bornes disjonctives, hors prix), pastille, reset ;
- `/?s=zzzzqq&post_type=product` : seules « Trier par » et l'ouvreur restent — rien n'est tenu, donc
  rien à retirer ; on change de recherche.

**Reste, non traité ici** : une plage de prix tenue **et** des bornes vides (`…&contenance=400ml&min_price=10&max_price=20`)
masque le bloc prix — limite acceptée dans `R-127` (même comportement que WooCommerce). La plage reste
retirable par sa pastille « Entre 10,00 € et 20,00 € » et par « Tout effacer ». La garder visible
renverserait `R-127` et demanderait de choisir quoi dessiner sans bornes : question à Louis. Tests
ajoutés : Feature `CollapsibleFacetTest` (pill d'une facette qui tient une valeur gardée sous une
recherche vide, badge « 1 » ; facette sans valeur tenue masquée), TS `facets-view.test.ts` (bloc d'une
valeur tenue gardé quand la réponse ne compte rien).

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

**Au 2026-10-01** (audit) : l'écart du gabarit est corrigé, `Q-24` reste ouverte (elle porte sur le réglage du projet,
pas sur le défaut du module, validé : « `submit` par défaut », `decisions.md`). Le gabarit publié sous le tag
`meilifacets-config` écrit désormais `'submit'`. Test : Feature `ConfigStubTest` (Feature parce que le gabarit lit
`env()`, qui demande `phpoption/phpoption`, absent du module seul) compare chaque clé ayant un défaut en code —
`apply_mode` à `ApplyMode::DEFAULT` (constante ajoutée, lue par `fromConfig()`), `card` à `CardSettings::DEFAULT_EAGER`
et `DefaultCardProjector::DEFAULT_IMAGE_SIZE`, `engine` à `EngineLimits::DEFAULT_*`, `url_parameters`,
`query_parameters` et `displayed_attributes` à `[]` ; **rouge avant** (`'submit'` attendu, `'immediate'` lu), vert après.
Aucun autre écart : `browser.url`/`browser.key` viennent de `env()` sans défaut, lus `(string)` donc `''` comme en code.
Le `config/meilifacets.php` du projet de test n'est pas touché (md5 `cef5aba086ea0d3c5645af4a6008f07e` avant et après).

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
`[hidden]`, et rien d'autre. Aucune feuille du thème de test ne cible une classe
`meilifacets*` (recherche sur les feuilles CSS du thème).

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

Relevé en cherchant à écrire un hook qui ne dépende pas du projet de test. **Vérifié le 2026-09-06** :

| | État |
| --- | --- |
| `require-dev` du module | vide — ni `phpunit/phpunit`, ni `laravel/pint` |
| `phpunit.xml`, `pint.json` du module | aucun des deux ; ceux du projet servent |
| 16 tests `Unit` | autonomes — `PHPUnit\Framework\TestCase` pur, aucune dépendance projet |
| 2 tests `Feature` | dépendent de `Tests\TestCase` **du projet de test**, donc de son `bootstrap/app.php` |
| nom de suite `Modules` | déclaré dans le `phpunit.xml` du projet de test, pas dans le module |
| `npm test` | autonome — Node, aucune dépendance |

Conséquence directe : **aucune commande de vérification du module n'est indépendante du projet
hôte.** Un module destiné à être installé ailleurs (D-01) ne peut donc pas emporter sa propre
recette. C'est aussi ce qui rend un hook impossible à écrire proprement aujourd'hui — il coderait
en dur un chemin et un lanceur (`ddev`) qui appartiennent au projet de test.

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
  du Blade et utilisent `Tests\TestCase` du projet de test). Elle est déclarée à part et lancée depuis le
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

### R-59 · 🔴 · **fermé le 2026-09-06**, inversé le 2026-10-02 par `R-208` · ouvert le 2026-09-06 — chaque URL de listing émettait `noindex` **et** une canonique vers une autre URL

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

*Inversé le 2026-10-02 par `R-208`* : une vue secondaire porte de nouveau une canonique, vers son chemin nu, numéro
de page du module conservé (`IndexingPolicy::canonicalFor()`) ; le `noindex, follow` reste posé, et le risque décrit
ci-dessus est accepté.

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
| `/categorie-produit/cheveux?marque=acme` | noindex | **aucune** |

`RobotsPolicy` a été renommée **`IndexingPolicy`** : avec deux balises à sa charge, l'ancien nom
était devenu faux.

### R-60 · 🔴 · **fermé le 2026-09-06** (B+) · ouvert le 2026-09-06 — deux paginations coexistaient, et c'est celle que le module ignore qui est indexée

Analyse dédiée, mesures du 2026-09-06 sur l'installation locale (76 produits publiés).

#### Ce qui est mesuré

Le module pagine sur `?pg=`. WordPress pagine sur `/page/N`. Les deux répondent, aucune ne connaît
l'autre.

| URL | Statut | Produits | Premier produit |
| --- | --- | --- | --- |
| `/boutique` | 200 | 16 | Sérum visage |
| `/boutique/page/2` | 200 | 16 | **Sérum visage** |
| `/boutique/page/3` | 200 | 16 | **Sérum visage** |
| `/boutique/page/4` | 200 | 16 | **Sérum visage** |
| `/boutique/page/5` | 200 | 16 | **Sérum visage** |
| `/boutique/page/6` | 404 | — | — |
| `/boutique?pg=2` | 200 | 16 | Patchs yeux |
| `/boutique?pg=5` | 200 | 10 | Crème de nuit |

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
| `/boutique` | index | `/boutique` | **0** | Sérum visage |
| `/boutique/page/2` | **noindex** | **aucune** | **0** | **Patchs yeux** |
| `/boutique/page/3` | noindex | aucune | 0 | **Lait corps** |
| `/boutique/page/5` | noindex | aucune | 0 | Crème de nuit (10 produits) |
| `/boutique/page/6` | 404 | — | — | — |
| `/boutique?pg=2` | noindex | aucune | 0 | Patchs yeux — identique à `/page/2` |
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

**Le produit reste en boutique** — « Trousse de voyage » est toujours dans la grille, page 4, et
sur son URL. La facette cesse d'offrir une entrée qui ne veut rien dire, elle ne cache pas un
produit.

Vérifié : `/boutique` rend 5 valeurs au lieu de 6, 16 produits inchangés, 118 tests dans le module
et 136 dans le projet.

#### Reste ouvert — la partie donnée

« Trousse de voyage » (#412) n'a toujours aucune catégorie. Le masquage la rend invisible dans la
facette sans la ranger : elle n'est atteignable que par la boutique entière ou par une recherche.
C'est une correction de contenu, pas de code.

### R-65 · 🟠 · ouvert · 2026-09-07 — une adresse de moteur sans schéma désactive tout, en silence

`MEILI_PUBLIC_URL` est l'adresse que le navigateur utilise. Chez l'hébergeur du projet de test, la forme
naturelle qu'on copie depuis la console est `projet.ddev.site/` — **sans schéma**.

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
`https://projet.ddev.site`, donc `asset()` et `wp_enqueue_*` produisent des URLs en `https`. Une
page ouverte en `http://projet.ddev.site` voit alors ses propres scripts comme une autre origine,
et le navigateur les refuse :

```
Access to script at 'https://…/build/theme/<thème>/assets/app-*.js'
from origin 'http://projet.ddev.site' has been blocked by CORS policy
```

**Le bundle du thème est bloqué exactement comme celui du module** : en `http`, le site n'a aucun
JavaScript. Ce n'est donc pas un défaut du module, c'est un piège d'environnement — et il a coûté du
temps, toutes les vérifications `curl` de la revue ayant été faites en `http` par habitude. Elles
restent valables pour le HTML servi ; elles ne disaient rien du JavaScript.

À écrire dans `installation.md` : **en local, le site se consulte en `https`**. *Fait.*

**Complété le 2026-09-08 — on n'y arrive pas que par habitude : une URL avec slash final y mène.**

```
https://projet.ddev.site/boutique/    301 -> http://projet.ddev.site/boutique
https://projet.ddev.site/panier/      301 -> http://projet.ddev.site/panier
https://projet.ddev.site/mon-compte/  301 -> http://projet.ddev.site/mon-compte
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
l'hébergement de production sert sur `public/` n'a pas été vérifié. Si la production répond `no-cache`, le défaut
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

L'animation est donc une décision de thème. Côté projet de test, `common/motion.css` :

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
le haut du listing atterrit dessous. Le module ne peut pas connaître cette hauteur. Côté projet de test :
trois lignes dans un `components/listing.css` neuf, `scroll-margin-top: 9rem`.

**Deux détours avant d'y arriver, et ils valent la correction :**

1. La hauteur a d'abord été **relevée dans un navigateur puis écrite en dur**
   (`--site-header-height: 123px`), avec un commentaire demandant de la tenir à jour à la main.
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
choisis d'après ce que le projet de test avait sous la main. Mesuré sur un jeu élargi —

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
/boutique?marque=globex — rendu serveur   : 15ml · 30ml · 40ml · 50ml · 75ml · 100ml · 125ml
/boutique puis « Globex » coché, client   : identique
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
purement textuelles. Savoir si le projet de test les garde en `Name` ou les passe à `Declared` — qui
honorerait en plus un ordre posé dans l'admin — est une autre question, à ouvrir sous son propre
numéro le jour où elle se pose.

### R-88 · 🟠 · **fermé le 2026-09-08** · ouvert le 2026-09-08 — le module livre les facettes du projet de test, et un projet ne peut pas déclarer les siennes

Relevé par Louis sur `ProductListing.php:27` (`private const string SIZE = 'pa_contenance';`).

**Étendue mesurée — plus étroite qu'il n'y paraît.** Deux littéraux seulement, dans tout le module,
sont propres au projet de test : `product_brand` et `pa_contenance`, tous deux dans `ProductListing`.
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

Liés en `scopedIf`. `ProductListing` ne fait plus que déléguer, et perd les deux littéraux du
projet de test ; `product_cat`, `product_brand` et `product_visibility` passent dans une énumération
`ProductTaxonomy`. Côté projet, `ShopFacets` déclare les trois facettes de
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
seule application** pour tout le run (consigné dans les notes du projet de test, pour le `LogManager`).
Corollaire non écrit : **les liaisons du conteneur fuient d'un test à l'autre.**

⚠️ **Le premier correctif était pire que le défaut**, relevé par une revue contradictoire le jour
même. Reposer les défauts dans `setUp()` remplaçait durablement le binding du projet pour **tous
les tests suivants** : un test placé après la classe obtenait `WooCommerceFacets` au lieu de
`ShopFacets`. Prouvé par une sonde, puis corrigé en ne touchant plus au conteneur du tout —
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
démarrer. Un projet range ses noms dans une énumération (`App\Enums\ShopFacet`), et **aucun
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
`?marque=globex` à 10 cartes, et une facette vidée par la recherche masque bien son `<fieldset>`,
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
liste résolue : App\ShopFacets
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
l'emportant ; `/boutique` et `/marque/umbrella` inchangées. ⚠️ Les deux moteurs ne coïncident pas :
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

### R-221 · 🟢 · **fermé le 2026-10-06** · ouvert le 2026-10-06 — `ActiveValueListTest` rouge dans la suite complète du projet

Six tests de `ActiveValueListTest` échouent dans `ddev exec vendor/bin/phpunit`, et passent sous `--testsuite Modules`
(reproduit). Le test, dans `tests/Unit`, attend les motifs anglais et des prix en `number_format` : il suppose
WordPress absent. La suite complète lance d'abord les tests `Feature` du projet, qui chargent WordPress et WooCommerce
dans le même processus ; `__()` traduit alors les motifs (« À partir de 10,00 € », « Retirer le filtre … ») et `Money`
passe par `wc_price()`. La suite `Modules` lance ses `Unit` avant ses `Feature`, d'où le vert. Le code est juste : il
rend sur le site ce qu'il doit rendre. Reproduit avec `--testsuite Modules --filter 'ListingPageTest|ActiveValueListTest'
--order-by=reverse` : 6 rouges.

*Corrigé le 2026-10-06, validé par Louis* : la classe tourne dans un processus séparé
(`#[RunTestsInSeparateProcesses]`). Vert dans les deux ordres ; la classe seule prend 1,9 s pour ses 11 tests ;
`composer check` vert, `Modules` 1127. Le septième échec de la suite complète, `test_permalinks_are_set_to_postname`,
relevait du projet : `/%postname%` sans barre finale est le réglage voulu (Louis), le test du projet est aligné.

### R-220 · 🟠 · **fermé le 2026-10-06** (n°12 noté, à reprendre ; question ouverte sous n°5) · ouvert le 2026-10-05 — revue de la PR #9 (`c86a74e`) : 15 constats

Publiés en anglais sur la PR (`/code-review max`, un commentaire par constat). Traités un par un : vérifier
qu'il est vrai, corriger, tester, puis passer au suivant.

1. *Lot de réindexation refusé au-delà de 100 Mo* (`VariantDocuments`, une fiche variante recopie la fiche produit et
   la liste de toutes les variantes ; MeiliScout envoie 500 produits d'un bloc) ; suppression avant écriture.
   *Vérifié* : produit 125, 5,75 Ko par fiche dont 952 o de liste (2 variantes, 476 o chacune), lot de 500
   (`Indexer.php:56`). *Corrigé le 2026-10-05* : MeiliScout `328fc16` (envois découpés à 10 Mo, filtre
   `meiliscout/max_payload_bytes` ; écriture d'abord, puis suppression des seules fiches périmées) et module
   `0a41ac8` (`ID` filtrable). Mesuré en local : suppression `(parent_id IN [125]) AND NOT ID IN ["125-0",
   "125-1"]` réussie ; une fausse fiche `125-9` disparaît, les deux vraies restent.
2. *Slug d'une taille renommé* : les fiches variante gardent l'ancien slug. *Vérifié* : sur `edited_term`, priorité
   10, MeiliScout (`handleTermSave`, qui réindexe les produits du terme) passe avant `WC_Post_Data::edited_term`, qui
   réécrit ensuite par SQL `attribute_pa_*` des variations et `_default_attributes` des produits, sans vider leur cache
   (`class-wc-post-data.php:275-291`). Sous 50 variations, la régénération des résumés de WooCommerce vide le cache par
   chance ; au-delà (`woocommerce_regenerate_variation_summaries_sync_threshold`), elle part en tâche de fond et une
   réindexation dans la même requête lit l'ancien slug — prouvé par un test rouge, seuil forcé à 0.
   *Premier correctif abandonné le 2026-10-05, avant commit* : une réindexation de plus côté module, qui doublait celle
   de MeiliScout, lisait le cache périmé et dépendait d'un crochet interne de MeiliScout.
   *Corrigé le 2026-10-05, commité `c91a3f2`, MeiliScout `ffd3064`* :
   - MeiliScout (`ffd3064`) : `edited_term` écouté à `EDITED_TERM_PRIORITY` (100), après les plugins qui réécrivent
     les articles d'un terme à la priorité par défaut. Création et suppression inchangées. Action publique
     `meiliscout/schedule_indexation`, sans argument : programme une fois la tâche de fond du bouton « Indexer », sans
     vider l'index, et ne fait rien pendant `meiliscout/skip_indexing`.
   - module : `Indexing\VariationMetaRewrites` vide le cache des métas après chaque réécriture SQL de WooCommerce.
     Sur `edited_term`, à la priorité 20 (entre WooCommerce et MeiliScout), pour un attribut produit : cache des
     produits du terme et des variations qui portent son nouveau slug (la requête même de WooCommerce,
     `class-wc-post-data.php:1046`). Au renommage d'un attribut entier, en fin de requête : cache des variations qui
     portent la nouvelle clé (`wc_variation_attribute_name()`), puis `meiliscout/schedule_indexation`.
   Tests (`VariationMetaRewritesTest`) : attribut fictif en mémoire, aucune ligne dans la table des attributs, cron et
   Action Scheduler court-circuités.
   - À la priorité de MeiliScout, la variation et son produit portent déjà le nouveau slug.
   - Un terme hors attributs, rattaché au produit, laisse le cache du produit et de la variation.
   - Un attribut renommé deux fois dans la même requête vide le cache et ne programme qu'une tâche.
   - Un attribut enregistré sous le même slug ne programme rien.
   Dix mutations, chacune rattrapée par un test : module après MeiliScout, MeiliScout remis à 10, produits oubliés,
   variations oubliées, garde de taxonomie, programmation, comparaison des slugs, vidage retirés ; MeiliScout,
   priorité écrite en dur à 15, garde `skip_indexing` retirée. Base relue après coup : aucune donnée de test, aucun événement programmé.
   *Revue, le 2026-10-05* :
   - Corrigé : une première version reportait la réindexation des termes à `shutdown` dans une liste en mémoire.
     C'était une seconde file à côté de `AsyncIndexingQueue`, et elle reportait aussi `created_term` : un produit
     enregistré avec une nouvelle étiquette aurait été indexé deux fois. Remplacée par la priorité.
   - Corrigé : écouteur de test non retiré (même cause que le n°15). Rector change `[$this, 'm']` en
     `$this->m(...)`, et chaque écriture crée une nouvelle closure que `remove_action()` ne retrouve pas. Une seule
     closure est donc gardée dans une propriété.
   - Corrigé : `tearDown` d'un test sauté ; catégorie de test au nom unique.
   - Corrigé : drapeau booléen retiré de l'action MeiliScout ; `skip_indexing` respecté.
   - Corrigé : `wc_variation_attribute_name()` au lieu d'un préfixe recopié ; noms revus ; `nopaging` au lieu de
     `posts_per_page => -1`.
   - Corrigé : les variations à vider sont cherchées par clé et slug, plus par `post_parent__in` — une liste vide y
     est ignorée et renvoie toutes les variations (`class-wp-query.php:2257`).
   - Corrigé (MeiliScout, tests) : deux fichiers déclaraient un `apply_filters` qui ignore `$GLOBALS['filters']` ;
     chargés avant, ils désarmaient la garde `skip_indexing` de `ReindexPostTest` et `ScheduleIndexationTest`. Un seul
     bouchon dans `tests/Pest.php`, remis à zéro avant chaque test.
   - Refusé : renommer la classe `AttributeMetaRewrites`. « Attribut » désigne déjà dans le module les champs de
     l'index (`IndexAttributes`).
   - Refusé : vider le cache seulement si le slug change. Il faudrait retenir l'ancien slug sur `edit_term` ; le gain
     est de deux requêtes, sur une action d'administration après laquelle MeiliScout relit de toute façon ces produits.
   - Refusé : `clean_post_cache()` pour le cache d'instances produit de WooCommerce. L'option `product_instance_caching`
     est à `no` sur ce site, et `clean_post_cache()` déclenche ses propres écouteurs sur chaque variation.
   - Noté, hors périmètre (MeiliScout, antérieur) : le bouton « Indexer » cherche une tâche déjà programmée avec
     `wp_next_scheduled()` sans arguments et ne la trouve jamais (`IndexationServiceProvider.php:176`).
   *Recensement de tous les chemins qui changent le slug d'un attribut dans une variation* :
   - terme renommé : corrigé ci-dessus ;
   - terme supprimé : WooCommerce ne réécrit pas les variations (`class-wc-post-data.php:1087-1123`, résumés seuls) ;
     MeiliScout réindexe les produits du terme ; la variante garde un slug que plus aucun filtre ne propose et que les
     autres tailles n'atteignent pas — aucun produit ne disparaît, rien à corriger ;
   - variation enregistrée (admin, import CSV, REST) : crochets de variation, déjà gérés ;
   - produit parent enregistré : `save_post`, déjà géré ;
   - méta d'une variation écrite directement, restauration depuis la corbeille : constat n°6 ;
   - attribut entier renommé (`pa_contenance` en `pa_volume`) : WooCommerce réécrit taxonomies et métas par SQL après
     `woocommerce_attribute_updated` (`wc-attribute-functions.php:615-663`) ; toutes les fiches et les réglages de
     l'index gardent l'ancien champ, et le moteur refuse un listing filtré sur le nouveau (vue de panne) jusqu'à la
     fin de la tâche de fond. Corrigé ci-dessus ; il faut un cron (déjà exigé par `production.md`) — en local
     `DISABLE_WP_CRON` est vrai sans cron système, une tâche de MeiliScout attend depuis le 2026-09-25.
     `url_parameters` reste à mettre à jour à la main (doc `indexing/README.md`).
3. *Compteurs et grille divergent* au-delà de `R-214`. *Vérifié le 2026-10-05, en lecture seule sur l'index local* :
   - fourchette seule, 30–35 € : la grille lit les documents variante et liste 7 produits ; les compteurs lisent les
     documents produit, qui se chevauchent avec la fourchette, et en comptent 8. Le produit 125 (variantes 26 € et 39 €)
     ajoute « Visage » (2 au lieu de 1), « 15 ml » (1) et « 400 ml » (1) : cocher l'un des deux affiche 0 produit.
     WooCommerce, lui, filtre par chevauchement (`class-wc-query.php:813-817`) et liste le 125 ;
   - facette de taille seule : `get_available_variations()` écarte une variation désactivée ou sans prix
     (`class-wc-product-variable.php:371`), et une variation en rupture si les produits en rupture sont masqués
     (`:358`). Le produit garde le terme, donc le compteur le compte, mais aucun document variante ne le porte. Pas
     reproductible sur le projet de test aujourd'hui (option à `no`, deux variations visibles) ;
   - `distinct` ne change pas `facetDistribution` : sans filtre, les documents variante comptent « Visage » 9 fois au
     lieu de 8, le 125 une fois par variante.
   *Fourchette seule corrigée le 2026-10-06, commité `ec5d9c4`* (choix de l'utilisateur, « comme WooCommerce », amendement
   de la décision « Produits variables ») : `QueryPlan::readsVariants()` et son jumeau `ListingQuery.#readsVariants()`
   ne passent aux documents variante que pour une facette de variante. Tests PHP et TS : fourchette seule, produits lus
   par chevauchement, sans `distinct`, tri du produit ; garde « liste sans document par variante » portée sur une
   taille. Vérifié sur la page, serveur et client (Playwright) : à 30–35 €, 8 cartes dont le 125, « Visage (2) » → 2
   cartes dont le 125, « Aeris (1) » → 1 carte, aucune requête `distinct`, aucune erreur en console.
   *Taille sans variation disponible* : réglé avec `R-214` (le 2026-10-06) — les tailles se comptent sur les documents
   variante, qui n'existent que pour les variations disponibles ; aucune réindexation.
4. *Requêtes produit écrites hors `VisibleProducts`*. *Vérifié le 2026-10-06* (lecture seule) : un document variante
   recopiait `post_type = "product"` et `post_status = "publish"` ; seule `NOT document_kind = "variant"` l'écartait.
   Les requêtes du module passaient toutes par elle (faux pour le module) ; vrai pour l'intégration `WP_Query` de
   MeiliScout (`use_meilisearch`, `TypeStatusBuilder.php:27-28`) — chaque variante revenait comme son produit parent,
   `(int) "123-0"` valant 123 (`class-wp-post.php:235`), soit 1 + N fois le même produit ; vrai pour un projet qui lie
   ses propres types de recherche ; `upgrading.md` n'en disait rien ; `onVariants()` comparait la clause à l'identique.
   Le projet de test n'est pas touché. *Corrigé le 2026-10-06, commité `b7906bc`* (option structurelle, choix de l'utilisateur) : le
   document variante porte `post_type = "product_variation"`, la clause d'exclusion disparaît, `onVariants()` élargit la
   clause de type (`FilterExpression::any()`, que `facet()` emploie aussi). Tests : `VisibleProductsTest` (produits sur
   leur seul type, variantes à la place des parents, filtre d'un projet élargi, clause écrite autrement laissée
   telle quelle), `VariantDocumentsTest`, requêtes et descriptions mises à jour. Doc : `index-settings.md`,
   `contracts.md`, `search/types.md`, `upgrading.md`, `CHANGELOG.md`.
   *Défaut trouvé à la réindexation locale (autorisée), puis par la revue* : un document variante recopiait
   `document_kind = "parent"` de son produit, que l'ancienne ligne écrasait ; le mode variantes écartait donc aussi les
   variantes (`?contenance=400ml` : 0 carte). `VariantDocumentsTest` figeait ce défaut : l'attendu reprenait le produit
   entier. Corrigé (`array_diff_key`), test qui exige l'absence du champ, rouge sans le correctif. Vérifié après une
   seconde réindexation locale : 125 sous `product`, 125-0 et 125-1 sous `product_variation` sans `document_kind` ;
   400 ml → 1 carte (le 125), 15 ml → 3, 400 ml + 30–35 € → 0, 30–35 € → 8 dont le 125, une seule fois.
   *Revue, le 2026-10-06* : corrigé — `withVariants()` (au lieu de `admittingVariations()`), `any()` ignore les clauses
   vides comme `all()` (test), deux commentaires retirés, `any()` dans `contracts.md`, fixture TS à la forme actuelle.
   Refusé pour l'instant : un enum des types WooCommerce (`product`, `product_variation`) — `VisibleProducts::POST_TYPE`
   sert déjà partout, chantier à part ; un diagnostic quand aucune clause de type n'est élargie — la limite est écrite
   dans la décision et dans `search/types.md`.
5. *Attribut hors variation* : l'ordre « stock d'abord » contredit la carte. *Vérifié le 2026-10-06* : le cas cité
   (attribut descriptif) est réglé par `R-214` tant que la table de WooCommerce sert ; reste un produit qui porte une
   taille sans la décliner, ou une variation « toutes tailles » — la grille le range par sa variante en stock, la
   carte reste projetée. Nouveau cas, visible sur le projet de test, créé par la fourchette seule « comme WooCommerce » : à
   30–45 € triés par prix, le 125 s'affichait « 39 € » rangé avant un produit à 32 €. *Corrigé le 2026-10-06, commité `00c28f0`* (choix de l'utilisateur) : la carte choisit sa variante selon la règle de la grille
   (`VariantChoice::readsVariants()` et son jumeau TS, liste `variantTaxonomies` passée par `ResolvedListing` et par
   la description) ; `CardVariant::carriesAny()` retiré. Cas partagés : fourchette seule → carte projetée ; taille non
   portée → variante en stock ; facette hors attributs de variation → carte projetée ; les 14 cas qui testaient la
   lecture de la liste sous une fourchette seule cochent une taille non portée. Doc : `customising/card.md` (la limite
   « filtres croisés » est retirée : la grille lit les documents variante et ne liste plus ce produit).
   *Question ouverte, le 2026-10-06* : l'utilisateur attend qu'une fourchette seule montre la variante disponible
   (30–45 € → le 125 à 39 €, absent à 30–35 €). Possible sans écart de compteurs depuis `R-214` : la fourchette seule
   relirait les documents variante (revient sur `R-220` n°3 « comme WooCommerce » et sur le cas 2 ci-dessus ; « en stock
   d'abord » s'appliquerait sous une fourchette seule). Laissé en l'état à sa demande, à reprendre.
6. *Produit variable restauré de la corbeille* sans fiches variante. *Vérifié le 2026-10-06* (lecture du code) :
   `wp_untrash_post()` repasse le produit à son statut d'avant (`WC_Post_Data::wp_untrash_post_status()`) et
   MeiliScout le réindexe pendant que ses variations sont encore à la corbeille ; WooCommerce ne les restaure qu'ensuite,
   sur `untrashed_post`, par `wp_untrash_post()` (`class-wc-product-variable-data-store-cpt.php:1054-1071`), sans
   crochet de variation. Même trou pour une méta de variation écrite directement (import, extension). Le stock après
   une commande est couvert : `wc_update_product_stock()` enregistre la variation (`wc-stock-functions.php:62-64`).
   *Corrigé le 2026-10-06, commité `6dd8a27`* : `VariationChanges::rememberProductOfVariationMeta()` écoute l'ajout, la modification et la
   suppression des métas d'une variation et retient son produit, réindexé une fois en fin de requête ; la restauration
   passe par là (WordPress supprime la méta de corbeille de chaque variation) — une écoute de `untrashed_post` essayée
   puis retirée, aucun test ne lui trouvant de rôle. Tests : produit restauré réindexé une fois, avec ses deux
   variations revenues ; méta écrite directement ; méta d'un enfant qui n'est pas une variation (pièce jointe) : rien.
   Rouges sans l'écoute et sans la vérification du type.
15. *Écouteur de test jamais retiré* (`VariationChangesTest`) : corrigé au passage, le 2026-10-06 — une seule closure
   gardée dans une propriété, comme dans `VariationMetaRewritesTest`.
   *Revue des n°5, 6 et 15, le 2026-10-06* : aucun défaut de comportement (même règle PHP et TS, autres appelants
   inchangés — le panneau de recherche n'a ni sélection ni fourchette —, aucune boucle de réindexation, environ 25 000
   `get_post_type()` en cache pour un import de 1 000 variations). Corrigé : `CHANGELOG.md` et `customising/card.md`
   (une fourchette seule ne choisit plus de variante ; un listing de projet n'en choisit que s'il implémente
   `VariantScopedListing`), docblocks de `VariantChoice` et `contracts.md`, deux commentaires de justification retirés,
   `ResolvedListing` passe par `variantListing()`, `rememberProductOfVariationMeta()` (au lieu de
   `rememberProductOfMeta()`) avec sa garde fusionnée, garde `$postId === 0` pour `delete_post_meta_by_key()` (test,
   rouge sans elle), le test de restauration relève le nombre de variations au moment de la réindexation, quatre cas
   partagés renommés, décision précisée pour le tri décroissant. Refusé pour l'instant : une seule méthode
   `ListingState::ticksAnyOf()` pour les quatre copies de la règle (jumeaux PHP/TS déjà verrouillés par les cas partagés
   et les tests de requêtes) ; une liste de taxonomies par cas partagé ; une garde WooCommerce sur l'écoute des métas
   (le type `product_variation` n'existe qu'avec WooCommerce).
7. *Attribut hiérarchique* : un terme parent ne trouve plus ses produits en mode variantes. *Vérifié le 2026-10-06* :
   la fiche produit porte les ancêtres (`TermAncestry`), la variante seulement son terme (`ProductVariants::facetsOf()`)
   et `VariantDocuments` remplace la liste du produit ; WooCommerce enregistre les attributs non hiérarchiques
   (`class-wc-post-types.php:270`), un projet peut les rendre hiérarchiques (`woocommerce_taxonomy_args_{name}`).
   Le projet de test n'est pas concerné. *Corrigé le 2026-10-06, commité `f037d2b`* : la variante porte aussi les ancêtres de son terme
   (`TermHierarchy`), seulement sur une taxonomie hiérarchique — aucun coût sur des attributs plats ; la carte en
   profite (`VariantChoice` lit les mêmes termes). Test : variation 15 ml sous « petits formats », rouge sans la
   correction. Réindexation nécessaire pour un projet concerné.
8. *Attribut local homonyme d'une taxonomie* (`taxonomy_exists` au lieu de `taxonomy_is_product_attribute`).
   *Vérifié et corrigé le 2026-10-06, commité `ca11837`* : `ProductVariants::facetsOf()` ne retient que les attributs produit
   de WooCommerce (`taxonomy_is_product_attribute()`). Test : un attribut local « meilifacets_test_color », homonyme
   d'une taxonomie du site, ne donne aucun terme ; rouge avec `taxonomy_exists()`. Les tests déclarent leur attribut
   fictif en mémoire (`$wc_product_attributes`).
9. *Taille d'image des variantes* liée à `card.image_size`. *Tranché le 2026-10-06 par l'utilisateur* : une seule clé,
   gardée. Corrigé : `customising/card.md` dit qu'un projet qui remplace le `CardProjector` doit fixer `card.image_size`
   à sa taille, l'image propre d'une variante y étant lue. Assumé : sur le projet de test, `portrait` recadre aussi les cartes
   d'articles du panneau de recherche (une clé produits séparée a été écartée).
10. *Deux listes de champs relus tenues à la main* (`READ_BY_THE_MODULE`, `VARIANT_RETRIEVED`). *Vérifié et corrigé le
   2026-10-06, commité `28cde0b`* : identiques mais non liées — un champ lu sans être affiché revient vide, sans erreur.
   `FacetedPostIndexable::READ_BY_THE_MODULE` reprend `QueryPlan::VARIANT_RETRIEVED`. Test : les champs affichés
   contiennent ceux que les deux requêtes lisent ; rouge si la liste ne reprend que `RETRIEVED`.
11. *Suppression par filtre envoyée pour tout article*, pas seulement les produits. *Corrigé le 2026-10-06, commité `447143e`, MeiliScout `ef2bd18`* (demandé par l'utilisateur) : MeiliScout n'envoie plus de suppression quand
   `dependentDocumentsFilter()` rend `null` (contrat `?string`, une seule garde `dependentDocumentsFilterOf()` pour
   l'écriture et la suppression, tests Pest) ; le module ne garde que les produits et les articles déjà supprimés —
   une suppression asynchrone arrive quand l'article n'existe plus (`FacetedPostIndexable::mayHaveVariants()`).
   Tests : une page et une variation n'ont pas de filtre, un lot mêlé ne filtre que ses produits, un article disparu
   est gardé ; rouge si on l'écarte. Vocabulaire MeiliScout renommé « dependent documents » (« dependents » seul se
   lisait « personnes à charge »). Revue des deux dépôts : renommages (`dependentDocumentIdsIn`, `$keptFilter`,
   tests), suppression ramenée à `removeStaleDependentDocuments(…, [])`, commentaire faux corrigé, lignes ramenées
   sous 120 caractères. À publier dans l'ordre : MeiliScout d'abord (`upgrading.md`).
   *Limite acceptée par l'utilisateur le 2026-10-06* (« on se fie au natif ») : un produit variable dont un projet
   change le type de publication (code ou extension, l'admin ne le permet pas) garde ses fiches variante dans l'index.
12. *`card.variants` sur la fiche produit*, envoyé sans être lu hors mode variantes. *Vérifié le 2026-10-06* : vrai,
   un coût et non un défaut — quelques centaines d'octets par variante dans chaque réponse (boutique, panneau). Le
   retirer demande que MeiliScout laisse ôter un champ de la fiche produit après avoir construit les fiches dépendantes.
   *Noté, à reprendre* (choix de l'utilisateur).
13. *Pas de cas partagés PHP/TS* pour `measures` et `variantResults`. *Corrigé le 2026-10-06, commité `5b3dc66`* :
   `tests/variant-plan-cases.json`, sept états (rien, une marque, une taille triée par prix, taille et marque, taille et
   fourchette, fourchette seule, taille et recherche) et le plan attendu de chaque recherche (requête, filtre, champs
   comptés, `distinct`, tri, champs cherchés), joués par `VariantPlanCasesTest` et `listing-query.test.ts` ; un test
   vérifie que la description du fichier est bien celle de `FakeVariantScopedListing`. Aucun écart trouvé ; un
   `distinct` retiré d'un côté fait échouer ce côté.
14. *Registre contradictoire* (503, « non commité »). *Corrigé le 2026-10-06* : états des entrées commitées mis à jour
   (`R-213` `11c0d80`, `R-214` `ec5d9c4`, `R-217` `58f5b33`, `R-219` `02f7506`, points de cette entrée), dette « le
   `503` ne sort pas » de `decisions.md` marquée réglée, note sur l'absence de cas partagés du plan mise à jour.

Écartés par la revue : `several_variants` hors filtre (voulu), réindexation en trop (`R-215`), noyau HTTP (Pollora
utilise celui de Laravel).

*Revue de régression et de véracité de la doc, le 2026-10-06* (après fermeture) :
- Corrigé : deux tests Feature du projet (`ProductCardProjectionTest`) construisaient `VariantChoice` sans liste de
  taxonomies et attendaient une variante ; la liste est désormais obligatoire en PHP, et passée par le test. Le
  troisième échec de ce fichier (`the_wishlist_card_leaves_the_grid…`) vient du travail en cours sur la wishlist.
- Corrigé : le refus du n°2 sur `clean_post_cache()` ne tenait pas — WooCommerce active son cache d'instances produit
  sur toute nouvelle installation (`class-wc-install.php:393`, `:1363`). `VariationMetaRewrites` appelle
  `clean_post_cache()`, que WooCommerce écoute (`ProductCacheController.php:86`). Tests : la variation d'un terme
  renommé et celle d'un attribut renommé passent par `clean_post_cache` ; rouges avec le seul cache des métas.
- Corrigé : doc publique (`upgrading.md` — toute liste avec une facette de variation tombe en panne avant la
  réindexation —, `facets.md`, `price.md`, `results-sort-pagination.md`, réglages d'index, `indexing/README.md`,
  `wordpress-hooks.md`, `contracts.md`), « ticked » remplacé par « checked ».
- Corrigé : une valeur de facette de variation sans variante (L déclaré, variations S et M) apparaissait à 0 dès
  qu'un filtre affinait — la liste des valeurs offertes était lue sur les produits. `QueryPlan::unfilteredVariants()`
  la lit sur les variantes pour ces facettes ; test, rouge sans la correction ; page vérifiée (`/boutique` et
  `?marque=aeris` offrent les mêmes contenances).
- Noté, à reprendre : une facette de variation à choix unique est
  comptée sans sa sélection, une facette partagée avec ; la table de WooCommerce compte les brouillons, et une table
  vide coupe le mode variantes sans secours ; la lecture de la table n'est pas mise en cache d'une requête à l'autre ;
  un `FacetCounter` de projet écrase le compte d'une facette de variation cochée ; liens absolus de
  `accessibility.md`.

### R-219 · 🟢 · **fermé le 2026-10-05** (`02f7506`) · ouvert le 2026-10-05 — une valeur de facette lue dans l'URL n'avait pas de longueur maximale

Audit de sécurité du 2026-10-05. `StateReader::isReadable()` acceptait une valeur de n'importe quelle longueur : une
URL forgée portait jusqu'à `cap` valeurs de plusieurs kilo-octets chacune jusqu'au filtre envoyé au moteur. Une valeur
utile est un slug de terme, et WordPress n'en stocke pas de plus long que 200 octets : colonne
`wp_terms.slug varchar(200)` (`wp-admin/includes/schema.php:68`), slug coupé à 200 octets encodés par
`sanitize_title_with_dashes()` (`utf8_uri_encode( $title, 200 )`, `wp-includes/formatting.php:2291`), et un suffixe
ajouté par `wp_unique_term_slug()` au-delà de 200 fait refuser l'insertion par `wpdb::process_field_lengths()`
(`wp-includes/class-wpdb.php:2997`). Corrigé : constante `StateReader::MAX_VALUE_BYTES` (200), comptée en octets
(`strlen()`), puisqu'un slug non ASCII est stocké encodé en `%xx`. Le client ne lit jamais l'URL (état initial reçu
du serveur, retour arrière par `history.state`) : rien à refléter côté navigateur, pas de jumeau dans
`ContractParityTest`. Tests : `StateReaderTest` garde une valeur de 200 octets, écarte une valeur de 201 octets.
Vérifié : `composer check` vert (669 tests PHP, 941 client), suite `Modules` 1061 verts.

### R-218 · 🟡 · ouvert (sujet de production) · ouvert le 2026-10-05 — `MEILI_KEY` est la clé maître

Audit de sécurité du 2026-10-05. En local, `MEILI_KEY` a la même valeur que `MEILI_MASTER_KEY` du conteneur
Meilisearch (comparaison faite sans afficher les clés). La clé ne quitte pas le serveur, mais sa fuite donnerait tous
les index, toutes les clés et le droit d'en créer. Recommandation : une clé d'administration dédiée, limitée aux index
de MeiliScout (`posts`, `taxonomies`, `getIndexName()` des deux indexables) et aux actions qu'il appelle (`search`,
`documents.*`, `indexes.*`, `settings.*`, `tasks.get`) ; la clé maître hors de l'environnement de l'application.
Ajouté à `docs/production.md` (« The server key »). Non codé.

### R-217 · 🟠 · **fermé le 2026-10-05** (`58f5b33`, `c86a74e`) · ouvert le 2026-10-05 — la vue de panne du listing part en 200 avec un cache public

*Corrigé le 2026-10-05 (non commité), avec `R-40`* : le listing ne pose plus d'en-tête à la main ;
`ServiceUnavailable::announce()` marque la panne pendant le rendu, et un middleware global du module
(`Http\ServiceUnavailableHeaders`, poussé dans le noyau HTTP par `RenderingServiceProvider`) applique à la réponse
Laravel `503`, `Retry-After: 120` et `Cache-Control: no-store` — global, il enveloppe la pile de Pollora et passe après
`WordPressHeaders`. Mesuré moteur local arrêté puis relancé : `/boutique` et `?contenance=400ml` en `HTTP/2 503`,
`cache-control: no-store, private`, `retry-after: 120`, vue de panne présente ; moteur relancé, `200` et 19 cartes.
Tests : `ServiceUnavailableHeadersTest` (503 et `no-store` une fois la panne levée, page intacte sinon, middleware
enregistré). Reste, hors de ce point : une page saine porte deux `Cache-Control` (`public, max-age=3600` et
`max-age=0`), dont l'origine n'est pas cherchée.

Audit de sécurité du 2026-10-05, mesuré en local, Meilisearch arrêté le temps de la mesure puis relancé.
`ResolvedListing::attempt()` (`app/Listing/ResolvedListing.php:77`) appelle `ServiceUnavailable::sendHeaders()`, qui
pose le statut par `status_header(503)` et les en-têtes par `header()`. Relevé sur `/boutique` et
`/boutique?contenance=400ml`, identique sur les deux :

```
HTTP/2 200
cache-control: no-store, max-age=3600, must-revalidate, public
cache-control: max-age=0
retry-after: 120
```

La vue de panne est bien rendue (`meilifacetsUnavailable` dans la page) ; moteur relancé, `/boutique` repasse en 200
sans `retry-after`. Cause : ces appels se font pendant le rendu de la vue, en dehors de la réponse Symfony que Pollora
renvoie. `FrontendController::handle()` construit `response(View::make(...), 200)`
(`vendor/pollora/framework/src/Route/UI/Http/Controllers/FrontendController.php:61`) ; à l'envoi,
`Response::sendHeaders()` réécrit la ligne de statut avec `header(..., true, 200)`
(`vendor/symfony/http-foundation/Response.php:381`), ce qui écrase le 503. `WordPressHeaders` ne voit pas le
`no-store` brut (`hasExplicitCacheDirectives()` lit l'objet réponse, `WordPressHeaders.php:232`) et ajoute
`public, max-age=3600` (`applyPublicCacheHeaders()`, `WordPressHeaders.php:260`) ; Symfony envoie son
`Cache-Control` sans remplacer celui de PHP (`Response.php:358`, `$replace` faux hors `Content-Type`) et nginx joint
les deux. Conséquence : un cache qui lit le statut garde la panne comme une page valide ; le `no-store` ne protège que
derrière un cache qui le lit dans un en-tête contradictoire. Le second `cache-control: max-age=0` et `expires` sont
déjà là sur une page saine : origine non cherchée. `R-205` (« pose `503` ») et `ListingOutageTest` lisent l'appel à
`sendHeaders()`, pas la réponse envoyée : aucun test ne tenait le statut reçu. Aucun code changé.

### R-216 · 🟠 · ouvert (sujet de production) · ouvert le 2026-10-05 — un visiteur peut occuper le moteur des secondes avec une seule requête

Audit de sécurité du 2026-10-05. Avec la clé publique, un filtre de 5 000 clauses `OR` (129 Ko) occupe le moteur
3,5 s ; une recherche multiple de 300 requêtes de 500 clauses (3,7 Mo) l'occupe 40 s. La limite de débit de
`docs/production.md` compte les requêtes, pas leur poids. Correctif au proxy, devant l'URL publique du moteur :
`client_max_body_size` (nginx) ou `LimitRequestBody` (Apache), et seules les routes du navigateur en `POST`
(`/indexes/posts/search`, `/multi-search`, `/indexes/posts/facet-search`, plus `OPTIONS` pour le contrôle CORS).
Pas `--http-payload-size-limit` : il plafonne aussi l'ajout de documents, donc l'indexation. Ajouté à
`docs/production.md` (« Request size »). Non codé.

*Détail retiré de `production.md` le 2026-10-05 (doc réduite au principe, à la demande de l'utilisateur)* : au proxy,
n'ouvrir que `POST` et `OPTIONS` (contrôle CORS du navigateur) sur `/indexes/posts/search`, `/multi-search` et
`/indexes/posts/facet-search`, `403` ailleurs ; `client_max_body_size` (Apache : `LimitRequestBody`) réglé au-dessus
de la plus grosse requête d'un vrai listing, toutes facettes cochées — `64k` n'est qu'un ordre de grandeur, non mesuré ;
le serveur joint le moteur par son adresse privée (`MEILI_HOST`), jamais par ce proxy.

### R-215 · 🟡 · ouvert · ouvert le 2026-10-05 — MeiliScout réindexe un article à chaque écriture d'une de ses métas

`SingleIndexingServiceProvider::handlePostMetaUpdate` (sur `updated_post_meta`, `added_post_meta`,
`deleted_post_meta`) réindexe l'article à chaque méta écrite, depuis le premier commit de MeiliScout : un produit
enregistré par WooCommerce, qui écrit une dizaine de métas, est réindexé une dizaine de fois. Les documents variante
(`R-210` n°1) rendent chaque passage plus coûteux (lecture des variations, suppression puis ajout de leurs documents).
Une variation enregistrée seule fait réécrire `_price` et trois métas du parent par la synchronisation différée de
WooCommerce (`class-wc-product-variable-data-store-cpt.php:940-951`) : au moins quatre réindexations, plus celle de
`VariationChanges`. Non mesuré. *Laissé de côté le 2026-10-05 par l'utilisateur* : le mode différé (`MEILISCOUT_ASYNC_INDEXING`) en
production regroupera les passages.

**Mesuré le 2026-10-06** (lecture seule du code et de la file du moteur local ; seule écriture : la variation 336 passée
en rupture pour un test, avec l'accord de Louis). Le décompte ci-dessus était faux sur l'arithmétique.
- *Neuf réindexations pour une sauvegarde.* `$v->save()` de la variation 336 puis `WC_Product_Variable::sync(125)` :
  neuf séries identiques sur le produit 125 en 0,3 s (tâches 341110 à 341137). Chaque `sync_price`
  (`class-wc-product-variable-data-store-cpt.php:921-957`) fait un `delete_post_meta(_price)` puis un `add_post_meta`
  par prix distinct (2 ici) : 3 réindexations. La synchronisation différée de WooCommerce au `shutdown`
  (`class-wc-post-data.php:113-143`) la refait, l'appel explicite ne retirant pas le produit de sa file : 3 de plus.
  `VariationChanges` : 1. Deux écritures ponctuelles sur le parent, dont `_product_version`, déduites et non prouvées :
  2. Les écritures sur la variation elle-même ne coûtent rien (`product_variation` n'est pas indexé). Rien ne
  regroupe dans MeiliScout en mode synchrone : le gestionnaire ignore la clé de méta
  (`SingleIndexingServiceProvider.php:241-244`). Le mode différé dédoublonne par `post:{id}`
  (`AsyncIndexingQueue.php:53-54`) mais réécrit son option à chaque appel ; il est désactivé en local. La promesse
  « une fois par requête » de la PR #9 ne valait que pour la part de `VariationChanges` (description corrigée).
- *Trois tâches par réindexation, aucune regroupable.* `AbstractSingleIndexer::indexItem` (`:174-205`) envoie à chaque
  fois les réglages (`ensureIndexExists()`, `updateSettings`, `:419-420`, sans comparaison ni cache), les documents,
  puis la suppression par filtre des documents dépendants périmés (`:257-264`, nôtre : `7ce229c`, `328fc16`,
  `ef2bd18`). D'après la documentation de Meilisearch (« Asynchronous operations »), un lot ne réunit que des tâches
  de même type sur le même index et se ferme sur une mise à jour de réglages ou un `deleteByFilter` : une tâche par
  lot, environ 6 par seconde mesurées.
- *Création d'index en échec à chaque processus.* `indexExists()` (`AbstractSingleIndexer.php:437-464`, écrit en amont
  en janvier et mars 2026) lit `$indexes['results']` sur un `IndexesResults` qui ne l'expose pas : l'index n'est jamais
  trouvé, `createIndex` part et échoue (« already exists »). Même lecture dans `Indexer.php:864-884`, sans dégât.
  Une seconde lecture l'attribuait à la file bloquée ; à départager sur la prochaine sauvegarde, file vide.
- *Une fiche ouverte dans l'admin réindexe toutes les deux minutes.* Produit 385 réindexé de 11 h 48 à 11 h 54 UTC au
  rythme du verrou d'édition (`_edit_lock`, utilisateur 1, dernière écriture 12 h 00 UTC) : environ 120 tâches par
  heure sans modification. Concordant, non prouvé tâche par tâche.
- *Les tests du projet écrivent dans l'index de dev.* La suite complète, lancée deux fois entre 11 h 40 et 11 h 47 UTC,
  a mis environ 43 000 tâches en file (`posts` et `taxonomies`), soit environ deux heures de traitement ; annulées et
  index reconstruit le même jour avec l'accord de Louis. Environ 28 classes enregistrent sans `KeepsTheIndexOut`
  (les 23 de `Fulfillments`, `WishlistTest`, `ArchiveContentTest`, `FaqBlockTest`, `FaqRestTest`,
  `CategorySelectionBlockTest`). Les tests tournent sur la base de dev, sans transaction : identifiants 17067 à
  24414 consommés, les suppressions envoyées visent ces identifiants (108 suppressions par identifiant non
  vérifiables). Chaque enregistrement réécrit les réglages de l'index depuis le contexte du test (`R-171`).
  `MEILI_INDEX_NAME` n'est lu nulle part (MeiliScout fixe `posts` en dur) ; vider `MEILI_HOST` ferait planter les
  enregistrements (client `null` non gardé).

**Pistes, rien n'est codé.** En amont, dans MeiliScout : réparer `indexExists()` (`getResults()`, `getUid()`, ou
`getIndex()` pour dépasser les 20 index listés par défaut) ; n'envoyer les réglages qu'à la création ou une fois par
processus ; regrouper les identifiants de la requête et écrire une fois au `shutdown`, après la synchronisation
différée de WooCommerce (priorité 10) et `VariationChanges` (priorité 20) ; ignorer les métas sans effet sur le
document (`_edit_lock`). Dans le module : rien d'obligatoire, `VariationChanges` alimenterait la même liste ; éviter
la suppression par filtre quand l'article n'avait aucun document dépendant demande de connaître l'état précédent (gain
non mesuré). Dans le projet : `meiliscout/skip_indexing` posé pour tous les tests dans `tests/TestCase.php`, comme
`KeepsTheIndexOut` (règle aussi `R-183`). *Reporté le 2026-10-06 par Louis* : la feature est livrée avec cette limite,
écrite dans la description de la PR #9.

### R-214 · 🟡 · **fermé le 2026-10-06** (`ec5d9c4`) · ouvert le 2026-10-05 — un compteur annonce plus de produits que la grille n'en montre

Avec une facette de variante et une fourchette de prix combinées, la grille lit les documents variante (un filtre se
vérifie sur une seule variante) et les compteurs les documents produit (fourchettes qui se chevauchent) : « 400 ml »
peut annoncer 4 produits quand un seul a un 400 ml sous 30 €. Limite inscrite dans la décision « Produits variables »
(2026-10-05, essai sur index temporaire) ; pour l'aligner, compter les facettes de variante sur les documents variante
sans `distinct`.

*Corrigé le 2026-10-06, non commité* (amendement « Produits variables » du 2026-10-06, méthode native de Meilisearch,
choisie par l'utilisateur ; option « liste de produits puis comptage » écartée). La piste d'origine comptait deux
fois un produit par variante (« Visage » 9 au lieu de 8, mesuré). `QueryPlan::measures()` et `measureWithout()`, et
leurs jumeaux `ListingQuery`, lisent les documents variante avec `distinct` en mode page dès qu'une facette de variante
est cochée ; les attributs de variation se comptent toujours à part, sur les variantes, sans `distinct`
(`ListingSearch::variantCountQueries()`, `ListingQuery.#isMeasuredSeparately()`) ; `Listing\VariationTaxonomies` lit
la table de correspondance de WooCommerce, tous les attributs en secours. Tests : 7 cas PHP, 7 cas TS, 2 de
`ListingSearch`, 3 de `VariationTaxonomies` (table lue, table désactivée, table en reconstruction) ; onze mutations,
chacune rattrapée. Vérifié sur la page (serveur, puis client sous Playwright) : 400 ml + 30–35 € → 0 carte et
« Visage (0) » (1 avant) ; 30–35 € seul → « 400 ml (0) », « 15 ml (0) » (1 chacun avant), « 100 ml (1) » → 1 carte,
« Visage (2) » → 2 cartes ; aucune erreur en console. Relevé en chemin : la page de liste est servie avec
`Cache-Control: public, max-age=3600` — un navigateur montre une heure durant les compteurs d'avant un changement.
*Revue en six passes, le 2026-10-06* :
- Corrigé : bornes de prix lues avec `distinct` dans `measures` quand une taille est cochée sans fourchette — mesuré
  39–39 € au lieu de 26–39 € sur le produit 125 ; les bornes ont désormais leur recherche, sur les variantes, sans
  `distinct` (`ListingSearch::boundsQueries()`, `ListingQuery.#needsSearchOfItsOwn()`). Test PHP et TS, rouges sans la
  règle ; vérifié dans le navigateur : quatre recherches, résultats et compteurs partagés avec `distinct`, contenances
  et bornes sans.
- Corrigé : `VariationTaxonomies` se rabat sur tous les attributs si l'API interne de WooCommerce lève une erreur ;
  injectée dans `ProductListing` sans valeur par défaut ; renommages `all()`, `isLookupTableInUse()`,
  `markedInLookupTable()`, `#needsSearchOfItsOwn`, `measuring`/`measuringWithout`, noms de tests ; quatre commentaires
  retirés ou raccourcis ; lignes longues ajoutées ramenées sous 120 caractères.
- Corrigé : tests — `ListingDescriptionTest` compare à la liste du listing et non à l'implémentation ;
  `VariationTaxonomiesTest` fixe lui-même les deux options de WooCommerce ; le test « jamais compté deux fois » inclut
  la recherche principale ; nouveau test des bornes.
- Précisé : la limite assumée couvre tout produit dont plusieurs variantes portent un même terme, pas seulement taille
  × couleur (décision et `reference/index-settings.md`).
- Refusé : placer la règle des facettes de variante dans `DisjunctiveFacetCounter`. Côté PHP, les recherches comptées à
  part (facettes, bornes) se décident déjà dans `ListingSearch` ; un compteur de projet garde la main sur les clés
  qu'il renvoie.
- Refusé : changer le critère du secours. Le module fait confiance à la table exactement quand WooCommerce le fait
  pour filtrer (`Filterer.php:46-47`) ; la table est tenue à jour même désactivée, mais rien ne dit alors qu'elle est
  complète.
- Refusé pour l'instant : mettre en cache la lecture de la table d'une requête à l'autre. Une lecture par requête qui
  affiche un listing, par l'index `is_variation_attribute_term_id` ; à mesurer sur un catalogue de plusieurs dizaines
  de milliers de variations.
- Refusé : renommer `VariationTaxonomies` en `VariantTaxonomies` — « variation » désigne dans le module les objets de
  WooCommerce (`VariationChanges`, `VariationPrices`), et la classe lit ce que WooCommerce dit de ses variations.
- Noté : `QueryPlan` passe à vingt méthodes statiques qui prennent toutes `($listing, $state)` ; le schéma préexiste,
  une classe par listing et par état serait un chantier à part.

### R-213 · 🔴 · **fermé le 2026-10-05** (`11c0d80`) · ouvert le 2026-10-05 — Pollora 13.34 : les vues du module ne sont plus surchargeables par le thème

La mise à jour du projet (`b4389d5`, `pollora/framework` v13.4.2 → v13.34.2) renomme le contrat des actions :
`Pollora\Hook\Domain\Contracts\Action` devient `Pollora\Hook\Domain\Contract\Action`. Le fournisseur du module
importait l'ancien nom et sortait sans rien dire sur `! $this->app->bound(...)` : plus de `prependNamespace` sur
`after_setup_theme`, donc plus de surcharge des vues du module par le thème. Relevé par la suite `Modules`
(`ComponentFoldersTest`, 9 rouges, rouge aussi sur le module tel que commité) et par `ProductCardProjectionTest` du
projet (8 rouges, cartes rendues par la vue du module au lieu de celle du thème). Corrigé par `add_action()` de
WordPress, stable sur toute la plage que le module accepte (`pollora/framework` `>=13.4 <14`). Vérifié : seul nom
importé de Pollora qui ne se charge plus (contrôle d'autoload des 41 `use Pollora\…` du module, du projet et du
thème) ; `ComponentFoldersTest` 21, `ProductCardProjectionTest` 27, `Modules` 1031 verts. Audit des autres effets (2026-10-05,
lecture seule) : aucune autre classe ni garde cassée ; les 10 crochets `#[Action]`/`#[Filter]` du module enregistrés ;
pages du site en 200 sans erreur PHP ; Meilisearch répond avec Guzzle 8 ; `__()` traduit (`helper-overrider` 1.2.1).
Relevés à trancher : styles d'éditeur du thème chargés deux fois par Pollora (`add_editor_style`, thème) ; blocs du
thème dans `resources/blocks`, déprécié jusqu'à Pollora 15 ; le module demande `helper-overrider` ≥ 1.1 pour lire
`lang/fr.json` sur un site `fr_FR` (contrainte à déclarer) ; `patches.lock.json` à versionner dans le projet. La
garde `bound()` qui se tait reste le défaut de fond.

### R-212 · 🟡 · ouvert · ouvert le 2026-10-05 — l'ajout au panier d'une carte est projeté par le projet, pas par le module

Demandé par l'utilisateur le 2026-10-05 : le lien d'ajout au panier (produit et variante) doit être natif au module,
conditionné à WooCommerce, comme les variantes (`R-210` n°1, `decisions.md` « Produits variables »). Aujourd'hui le
projet le pose (`cart_url`, `ajax_add_to_cart`, dans `app/Cms/Products/ProductCard.php`). Lot à part, après les
variantes natives ; lire d'abord ce que WooCommerce offre (`add_to_cart_url()`, `supports('ajax_add_to_cart')`,
`woocommerce_loop_add_to_cart_link`).

### R-211 · 🟡 · ouvert · ouvert le 2026-10-05 — l'option « Masquer les produits en rupture » n'est pas suivie

`VisibleProducts::hiding()` (`app/Search/VisibleProducts.php:38`) n'écarte que `exclude-from-catalog` et
`exclude-from-search`. Avec `woocommerce_hide_out_of_stock_items = yes`, WooCommerce masque un produit en rupture
(terme `outofstock` de `product_visibility`) et ses variations en rupture (`class-wc-product-variable.php:343`) ; le
module affiche le produit. Ses variations en rupture sortent de `card.variants` depuis `ProductVariants` (2026-10-05),
qui passe par `get_available_variations()`, mais leurs termes restent dans les facettes du document produit. Sans effet sur le projet de test (option à `no`, lu le 2026-10-05). Un produit en rupture reste
affiché tant que l'option est décochée : c'est le comportement voulu.

### R-210 · 🟠 · **fermé le 2026-10-06** (n°2 limite connue ; résidus non publiés laissés en l'état) · ouvert le 2026-10-05 — seconde revue de la PR #7 (`9639a56`) : le choix de variante et ce qu'il touche

Rattaché à `R-206`, `R-208`, `R-209`. Constats publiés en anglais dans la PR (revue `5411977381`, un commentaire
par constat). Les points déjà acceptés sous `R-209` sont exclus. Rien n'est corrigé : chaque constat attend d'être
trié. *Reproduit* : constaté par un script ; *lu* : mécanisme confirmé à la lecture ; *plausible* : sans relevé HTTP
ni cas réel.

**Probablement bloquants pour la PR.**
1. *Tri par prix contre prix affiché* (`VariantChoice.php:45`, lu). La carte montre le prix de la variante retenue,
   `price_asc`/`price_desc` trient toujours sur `price.min`/`price.max` du produit : `?pa_volume=400ml&sort=price_asc`
   classe A (15ml à 26, 400ml à 39) avant B (400ml à 30), et la grille lit 39 € puis 30 €.
   *Tranché le 2026-10-05* : un document par variante en plus du document produit, interrogé avec `distinct` quand un
   filtre concerne les variantes, compteurs sur les documents produit (`decisions.md`, « Produits variables »). Mesuré
   sur un index temporaire local, supprimé ensuite ; non codé.
2. *Archive d'attribut sans variante* (`ResolvedListing.php:96`, lu). Sur `/pa_volume/400ml/`, le terme parcouru est
   épinglé dans le filtre de base et absent de `facets()` : `VariantChoice` ne choisit rien, et la carte montre le
   symptôme que `R-206` corrige pour `?pa_volume=400ml`.

**Défauts réels.**
3. *Champs de variante fusionnés clé par clé* (`VariantChoice.php:79`, lu). *En partie le 2026-10-05 (avancement)*. Une variante sans `image_srcset` garde
   celui du produit : `src` de la variante, `srcset` du produit, le navigateur affiche la photo du produit.
4. *Tri filtrant `on_sale` ignoré* (`CardVariant.php:75`, lu). `?sort=on_sale&pa_volume=400ml` liste le produit pour
   sa variante 15ml en promo et montre la 400ml plein tarif.
   *Corrigé le 2026-10-06, non commité* (« très grave », demandé par l'utilisateur ; présenté à tort comme « limite
   connue » dans le registre et la description de la PR #9, sans décision de l'utilisateur) : chaque variante relève
   `is_on_sale()` de sa variation (`CardVariant::$onSale`, `on_sale`), sa fiche porte son propre `price.onsale`, et
   la carte ne choisit qu'une variante en promo sous un tri qui filtre sur `price.onsale`
   (`CardVariant::meetsSortFilter()`, PHP et TS ; le filtre du tri passé par `ResolvedListing` et la description).
   Tests : documents (une variante en promo, l'autre non, sur un produit marqué en promo), lecture de WooCommerce,
   trois cas partagés ; rouges sans chacune des trois règles. Réindexation nécessaire. Commité dans `23ca6df`.
5. *`several_variants` jamais retiré* (`VariantChoice.php:123`, lu et exécuté en mémoire). *Fermé le 2026-10-05
   (avancement)*. Une valeur posée par le
   projecteur survit à une seule correspondance : « À partir de 39 € » pour une variante. Même chose côté TS.
6. *Champs réservés non annoncés* (`VariantChoice.php:37`, lu). `variants` et `several_variants` sont retirés de toute
   carte affichée ; un projet qui les projetait perd le texte sans erreur, `upgrading.md` dit « same contract ».
7. *Borne brute contre borne arrondie* (`listing-binding.ts:228`, reproduit). Le navigateur compare la borne saisie, la
   requête et l'URL portent `formatBound` (4 décimales) : 25.99999 liste le produit à 26, le navigateur garde la carte
   projetée, le serveur montre la variante à 26 € après rechargement.
8. *Canonique `?pg=N` sur une archive sans listing* (`IndexingPolicy.php:42`, plausible). `isSecondaryView()` est vrai
   sur toute archive qui porte `?pg=` : `/category/news/?pg=3` reçoit une canonique vers un paramètre qu'elle ignore.
   Rejoint la question laissée ouverte sous `R-209` (la canonique relit `pg` à sa façon).
9. *ItemList JSON-LD sur les cartes choisies* (`ResolvedListing.php:88`, plausible). Sur une page ordinaire,
   `isSecondaryView()` est faux et `itemListElement.url` publie l'URL de variation au lieu du permalien.

**Robustesse.**
10. *Prix non fini accepté* (`CardVariant.php:24`, reproduit). Le constructeur et `toArray()` acceptent `INF`, que
    `read()` refuse et que `json_encode` ne sérialise pas : le lot d'indexation de MeiliScout l'écarte entier. Des
    slugs entiers `[42]` reviennent en liste vide.
11. *Échelle de prix de la doc* (`customising/card.md`, exemple retiré depuis, plausible). *Fermé le 2026-10-05
    (avancement)*. `wc_get_price_to_display()` peut rendre 8.3333
    contre 8.33 indexé : `?max_price=8.33` liste le produit, aucune variante ne correspond.
12. *Deux `classList()` sur un élément* (`CardBinding.php:134`, reproduit). `ComponentAttributeBag` garde le premier
    marqueur `data-meili-class-list` : la carte du serveur porte les deux jeux de classes, celle redessinée par le
    navigateur perd le second.
13. *Facette lue par la chaîne de prototypes* (`card-variant.ts:60`, reproduit). Une taxonomie `constructor` ou
    `__proto__` sélectionnée lève une `TypeError` dans `#repaintGrid` ; PHP (`isset`) dessine la variante.
14. *Fourchette inversée* (`Range.php:27`, reproduit). `contains()` s'appuie sur `clamp()` : `?min_price=50&max_price=10`
    contient exactement 10, et la carte montre la variante à 10 € comme correspondante. Même chose côté TS.

**Performance.**
15. *Variantes lues avant `isConcerned()`* (`VariantChoice.php:36`, mesuré par micro-benchmark). Sans filtre, 48 cartes
    de 4 variantes : 130 à 170 µs en PHP et environ 98 µs par repeinte navigateur, contre 4 à 8 µs avec un retour
    anticipé quand sélection et fourchette sont vides. Vaut aussi pour chaque résultat du panneau.

*Vérification dans le code, le 2026-10-06* (lecture seule, reproductions en mémoire) : fils de la PR répondus ; n°1,
5, 6, 11 réglés et résolus ; n°10 et 14 ne s'appliquent plus (répondus, ouverts). *Corrigés le 2026-10-06, non
commités* : n°9 — le JSON-LD `ItemList` se bâtit sur les cartes projetées (`ResolvedListing::projectedCards()`),
l'URL du produit et non celle de la variation ; n°7 — le navigateur arrondit les bornes comme la requête et l'adresse
(`ListingState`) ; n°13 — une facette n'est lue que parmi les propres clés de la variante (`Object.hasOwn`), cas partagé
`constructor`/`__proto__` ; n°15 — les variantes ne sont lues que si un filtre les concerne (PHP et TS) ; n°14 — une
fourchette inversée ne contient rien (PHP et TS) ; n°10 — `CardVariant` refuse un prix non fini ; n°3 — des champs
d'image fournis par le projet sont complétés en image entière (`ImageFields::withWholeImage()`). Tests sur chacun, rouges
sans la correction pour n°7 et n°13. À trancher : n°2 (archive d'attribut, sans effet sur le projet de test), n°8 (canonique
d'une archive sans listing : le module ne sait pas, dans le `<head>`, qu'un listing sera rendu), n°12 (deux
`classList()` sur un élément). *Tranché le 2026-10-06 par Louis* : n°2 est une limite connue, les archives d'attribut
sont hors du périmètre actuel et le module recommande de laisser « Enable archives? » décoché
(`docs/customising/card.md`, « Card variants ») ; le projet de test les a désactivées (`attribute_public = 0`, lu).
*Tranché le 2026-10-06 par Louis, corrigé* : n°12, `CardFieldElement::with()` refuse un second `classList()`
(`BindingRefused::secondClassList()`), un tableau comme un sac ; un `merge()` du projet reste silencieux, ce que la
doc dit. Deux tests rouges sans le refus ; `composer check` vert, `Modules` 1121, `/boutique` en 200.
*Tranché le 2026-10-06 par Louis, corrigé* : n°8, `<x-meilifacets::listing>` marque la page
(`ListingPage::markAsCurrent()`, service par requête) et le filtre `meilifacets/is_listing_page` surcharge la réponse
(`decisions.md`, « Indexation des URLs de listing », coût compris). Relevé avant/après sur `/author/amphibee/?pg=3` :
`noindex`, canonique `?pg=3` et preconnect avant, `index` et canonique de Yoast sans `pg` après ; `/boutique?pg=2` et
`/categorie-produit/visage?contenance=400ml` inchangés (`noindex`, canonique, preconnect). Écarte au passage la
recherche native `/?s=` sans listing (`R-161`). Test du `<head>` rouge sans la marque ; `composer check` vert,
`Modules` 1127.

*Fermé le 2026-10-06.* Les quinze constats ont une suite : n°1 par les documents variante (PR #9) ; n°3, 7, 9, 10,
13, 14, 15 dans `6df085b` ; n°4 dans `23ca6df` ; n°5, 6, 11 réglés le 2026-10-05 ; n°12 dans `36af831` ; n°8 dans
`f830cc2` ; n°2 limite connue, validée par Louis, documentée dans `ad43b14`. Les quinze fils de la PR #7 sont
répondus et résolus, chacun avec son commit. Vérifié à la fermeture : `composer check` vert, `Modules` 1127, pages
`/boutique`, `/categorie-produit/visage` et `/author/amphibee/?pg=3` relevées, choix de variante et « Indisponible »
vus dans le navigateur. Les résidus ci-dessous n'ont pas été publiés ni traités : ils restent à trier s'ils doivent
l'être.

**Résidus non publiés** (au-delà du plafond de 15) : docblock de `canonicalFor()` qui redit `decisions.md` ;
`CardView.fieldsOf` crée un `VariantChoice` par résultat et le paquet du panneau grossit d'environ 9 % ; `marker()`
construit encore les paires d'attributs sur une carte rendue avant de les jeter ; `PostDocument` ne réécrit pas en liste
une `Collection` Laravel stockée dans `variants` ; `ResolvedListingStateTest` écrit des noms de champs en chaînes ;
`ListingResults::card()` renumérote les clés numériques avant le choix de variante (antérieur à la PR, casse la parité
que la PR teste).

**Avancement du 2026-10-05, suite.** Étape 2 codée, non commitée : un document par variante écrit et retiré avec
le produit (MeiliScout `7ce229c`, `HasDependentDocuments`, implémenté par `FacetedPostIndexable` avec
`VariantDocuments`) ; chaque requête produit écarte les documents variante ; une facette d'attribut ou une fourchette
de prix fait lire les résultats sur les documents variante (`QueryPlan::readsVariants`, `variantResults`,
`distinct: parent_id`, stock d'abord sous un tri par prix), les compteurs restant sur les produits (`measures`) ;
jumeau navigateur depuis la clé publiée `variantResults`. Réindexation du produit quand une variation change seule
(MeiliScout `246c358`, `meiliscout/reindex_post`, `VariationChanges`). Revue en six passes, vocabulaire unifié
(« variant / parent / measures »). Trouvés sur le moteur réel et corrigés : `parent_id` absent de
`displayedAttributes`, `distinct` ignoré par `MeilisearchQuery`. Vérifié : `composer check` (PHP 667, client 941 après la seconde revue),
`Modules` 1057+, réindexation locale (2 documents variante, 65 produits), navigateur : 15 ml + 400 ml en tri
décroissant, un seul « Eau Micellaire », en tête, « À partir de 26,00 € » ; 400 ml seul, 39,00 €. Reste ouvert :
n°2 (archive d'attribut : la carte ne connaît pas le terme parcouru), n°4 (`price.onsale` hérité du produit par
chaque variante), `R-214`, `R-215`.

**Avancement du 2026-10-05, branche `feat/variant-documents`, non commité.** Étape 1 de n°1 codée : le module lit les
variantes dans WooCommerce (`Indexing\ProductVariants`, appelé par `Indexing\ShopFields`, extrait de `PostDocument`),
stock compris ; un projet ajoute ses champs par `Contracts\VariantFields` (défaut `EmptyVariantFields`) ; règle « en
stock d'abord » et `out_of_stock` des deux côtés, 9 cas partagés de plus (mutations vérifiées : 3 rouges en PHP et en
TS sur la préférence, 1 sur la remise à zéro des drapeaux). Ferme au passage n°5 (`several_variants` et
`out_of_stock` remis à zéro quand une variante est montrée) et n°11 (prix lu dans `get_variation_prices(for_display:
true)`, la liste d'où viennent `price.min`/`price.max`) ; n°3 seulement pour l'image propre d'une variante, écrite en
entier (`ImageFields::whole()`) — la fusion clé par clé reste pour les champs d'un projet. Revue en cinq passes faite ;
laissé ouvert : un `wc_get_product()` et un `get_variation_prices()` par projecteur et par document (au moins trois
objets produit par document), à traiter à part. Vérifié : `composer check` (PHP 647, client 931), `Modules` 1026,
`ProductCardProjectionTest` du projet 27.

**Sain, au moment de la revue (`9639a56`)** : `dist/` conforme aux sources (`node bundle.ts --check`) ; aucune carte du serveur ne contourne
`ResolvedListing::cards()` ; rien ne lit les `data-meili-*` d'une carte rendue ; PHP 636 tests, client 922.

### R-209 · 🟡 · ouvert (commité dans `3209a46`, `2714f09`, `9639a56` ; suite sous `R-210`) · ouvert le 2026-10-02 — revue de la PR #7 (`9c4474a`) et de `14391f7`

Rattaché à `R-206`, `R-207`, `R-208`. Constats publiés en anglais dans la PR (« Review — `9c4474a` »), puis revue de
`14391f7`, qui n'avait été revu par personne. Rien n'est commité, réindexé ni écrit en base ou dans le moteur : les
documents indexés portent déjà une liste JSON, la nouvelle règle de lecture ne change rien à l'index.

**Constats de la PR et suite donnée.**
1. *Branchement client des variantes non tenu* : `ListingBinding` « draws each card through the variant the answered
   state points to ». Rouge quand `fieldsOf(hit, choice)` redevient `fieldsOf(hit)` (mutation vérifiée).
2. *Variantes reçues deux fois par le panneau* : **non corrigé, documenté** (`reference/index-settings.md`, « Watch
   out »). Meilisearch 1.53, mesuré en lecture sur l'index de test : `attributesToRetrieve: ["card.title"]` ne rend
   rien tant que `displayedAttributes` vaut `["ID", "card"]`, et surligner un sous-champ seul ne rend aucun
   `_formatted` ; aucune syntaxe d'exclusion. L'exclure demanderait de déclarer chaque sous-champ affiché dans
   `displayedAttributes` (réglages + réindexation) alors que le module ne connaît pas les champs de carte d'un projet.
   Mesure : une carte de deux variantes porte environ 0,9 Ko de variantes, reçues une seconde fois en `_formatted`.
3. *Parité sur un objet à clés entières non croissantes* : `variants` est une liste JSON ; un objet n'est lu que s'il
   est indexé de 0 à n-1, dans l'ordre des indices (`ksort` + `array_is_list` en PHP, clés `"0"`…`"n-1"` en TS). La
   règle de `R-206` (« un tableau ou un objet se lit par ses valeurs ») est resserrée pour la seule liste des
   variantes : PHP garde l'ordre d'insertion, JavaScript range les clés entières, et PHP ne distingue plus `[a, b]` de
   `{"0": a, "1": b}` une fois décodé — « liste seulement » n'était donc pas tenable des deux côtés. Les listes de
   slugs gardent la règle de `R-206` (leur ordre ne compte pas).
4. *Champs réservés* : `id`, `variants` et `several_variants` sont retirés des `fields` d'une variante, dans
   `VariantChoice` des deux côtés (`FIELDS_SET_BY_THE_MODULE`). `ID_FIELD` déménage de `card-view.ts` dans
   `variant-choice.ts` (jumeau de `ContractParityTest` suivi).
5. *`perf(card)` tenu par la suite autonome* : `CardBindingTest::a_rendered_card_carries_no_binding_instruction`, sur
   les 26 éléments présents des cas partagés (texte, attributs, classes, liste de classes, conditions). `marker()` qui
   écrit toujours : 26 échecs.
6. *Docs* : `customising/card.md` (`data-meili-class-list` n'enlève rien ; le panneau redessine un nœud gardé),
   exemple corrigé (`use App\Shop\ShopCardField`, attributs non taxonomiques écartés par `taxonomy_exists()`), règles
   (liste JSON, champs réservés), limite « fourchette de prix entre deux variantes » ; `reference/components.md`
   (`classList()`, `data-meili-class`, `data-meili-class-list`, carte rendue sans marqueur) ; `upgrading.md`
   (section « Unreleased » : marqueurs, canonique).
7. `VariantChoiceTest` : `assertSame` partout (aller-retour comparé par `toArray()`), test renommé
   `it_shows_the_card_through_the_matching_variant_or_as_projected`.
8. *Registre* : `R-206`, `R-207`, `R-208` fermés avec leurs commits ; `R-206` et `R-207` portent les chiffres mesurés
   à `9c4474a`, `R-208` n'en porte pas ; détails du projet de test reformulés (`R-206`, `R-207`, `R-208`,
   `decisions.md`).
9. *Nits* : type `Selection` (`ListingState`, importé par `VariantChoice`, `CardVariant`, `StateReader`) et `Selection`
   côté TS (`shared/description.ts`) ; `$variants`/`variants` qui désignaient un `VariantChoice` deviennent
   `$choice`/`choice` ; commentaire de `card-variant.ts` supprimé (justifiait un choix) ; celui de `card-binding.ts:16`
   gardé (contournement technique : `\s` de JavaScript est Unicode, celui de PCRE ASCII).

**Relecture en deux passes (code, puis docs).** Corrigés après la passe code : le docblock de `canonicalFor()` disait
encore que Yoast pagine par `/page/N` (il pagine aussi par la requête, sans permaliens « jolis ») ; deux cas partagés
écartent une clé `"00"` et `"-0"` (une mutation TS en `Number(key) === index` passait les 26 autres) ;
`a_rendered_card_carries_no_binding_instruction` ne tourne plus que sur les éléments présents et exige un élément ; le
commentaire de `VariantChoice::listed()` dit le fait (`json_decode` garde l'ordre d'écriture, le navigateur range les
clés entières) au lieu de justifier ; `PostDocument` réécrit `variants` avec `array_values()` à l'indexation, pour
qu'une liste à trous laissée par `array_filter()` ne soit pas stockée en objet et ignorée sans signal.

Corrigés après la passe docs. *Faux* : la canonique vers le chemin nu (`R-208`) n'était pas reportée dans
`architecture.md` ni dans la section « Facette de recherche » de `configuration.md`, qui la disaient encore retirée ; « qu'il
construit » attribuait la canonique au module au lieu de Yoast (`configuration.md`). *Incohérent* : cinq attributs de
liaison et non quatre (`decisions.md`, `architecture.md`, qui dit aussi qu'ils ne sont écrits que dans le gabarit) ;
ligne `data-meili-class-list` ajoutée au tableau « Lier un champ de la carte » ; `R-59` marqué inversé par `R-208` ;
chiffres de `9c4474a` attribués à `R-206` et `R-207` seulement (`Modules` valait 966 à `a4b5319`) ; ligne TS de
`R-206` marquée retirée. *Manquant* : `CHANGELOG.md` (section « Fixed » pour `417b687`, `Enums\VariantField`,
`CardField::Variants`, `CardField::SeveralVariants`) ; champs réservés et écriture en liste JSON à l'indexation dans
`reference/index-settings.md` ; marqueurs écrits par `CardBinding::template()` seul (`customising/card.md`) ;
`upgrading.md` (`Contract::VERSION` inchangé, pas de réindexation, `module:publish`). *Neutralité* : poids par carte,
pages et nombres de cartes, tailles, noms de tests et slugs du projet de test retirés (`CHANGELOG.md`, `R-206` à
`R-209`, `decisions.md`). *Nits* : exemple de poids d'une variante, formulations de `card.md` et `upgrading.md`,
exemples harmonisés en `/shop/?…`, lignes de plus de 120 caractères rewrappées.

**Revue de `14391f7`.** Corrigés : le docblock de `canonicalFor()` disait que Yoast « retire les paramètres qu'il ne
connaît pas » — il construit la canonique depuis le permalien, sans query string ; aucun test ne tenait le séparateur
`&` (canonique qui porte déjà `?`) ; exemples de la doc publique sous les slugs du projet (→ `/shop/?…&brand=`, comme le
reste de la doc) ; nom du projet consommateur et slugs de test dans `R-208` ; entrée de `CHANGELOG.md` manquante.
**Laissés en question** (relevés en HTTP) : la canonique relit `pg` à sa façon au lieu de la page que le listing a
servie — `/shop/page/2/?pg=3` sert la page 3 sous la canonique `/shop/page/2/?pg=3`, `/shop/page/2/?pg=1` sert la page 1
sous `/shop/page/2/`, `?pg=2abc` sert la page 2 (`StateReader` caste) sous le chemin nu, `?pg=99` sert la dernière page
sous `?pg=99` ; une canonique saisie à la main dans Yoast sur un terme reçoit aussi `?pg=N`. Le crochet lui-même
(`canonicalizeSecondaryViews()`, garde `isSecondaryView()`) n'est tenu par aucun test, comme avant lui
`dropCanonicalOfSecondaryViews()`. Sécurité : rien — la page est un entier, `http_build_query` encode le nom.

**Vérifié (2026-10-02).** `composer check` vert (PHP 636, client 922) ; suite `Modules` 1007 verte ; les tests de carte
du projet de test passent. Nouveaux cas rouges sans leur correction : 4 cas partagés en PHP, 3 en TS (l'ordre d'un objet
indexé ne divergeait que côté PHP). Navigateur, WebKit iPhone et Chromium, `submit` et `immediate`, sans ajout au
panier : carte projetée sans filtre, variante au premier rendu et après clic, préfixe sur deux tailles cochées,
aucun marqueur sur les cartes rendues par le serveur ; panneau de recherche : un résultat, surligné, sans variante
dessinée, console sans erreur.

### R-208 · 🟠 · **fermé le 2026-10-02** (`14391f7`, revu sous `R-209`) · ouvert le 2026-10-02 — décision SEO : une vue secondaire porte une canonique vers son chemin nu

**Demande** : décision SEO d'un projet consommateur, validée par Louis — une URL filtrée doit déclarer
une canonique vers l'URL non filtrée. Elle **inverse** la sortie retenue par `R-59` (canonique
supprimée). Deux points tranchés par Louis avant de coder : le `noindex, follow` reste posé ; la
pagination garde une canonique auto-référente, sans les filtres.

**Mesuré avant** : `/shop/?q=…` → `noindex, follow`, aucune canonique.

**Correctif** : `IndexingPolicy::canonicalizeSecondaryViews()` remplace `dropCanonicalOfSecondaryViews()`, sur le même
`wpseo_canonical` à 20. Yoast fournit déjà le chemin nu (il construit la canonique depuis le permalien et sa propre
pagination, sans les paramètres du listing) : `canonicalFor()` n'y remet que le numéro de page du module, entier
strictement supérieur à 1, sous son nom configuré.

**Coût accepté** : la paire `noindex` + canonique vers une autre URL est celle que `R-59` qualifiait
de contradictoire — Google peut reporter le `noindex` sur la cible. Rien ne le signalera : à surveiller
dans la Search Console (statut d'indexation du chemin nu de l'archive produit et des archives de catégorie).
Restent aussi deux formes pour une même page 2, `?pg=2` et `/page/2/`, chacune canonique d'elle-même ;
toutes deux en `noindex`.

**Vérifié** en HTTP le 2026-10-02 :

| URL | robots | canonical |
| --- | --- | --- |
| `/shop/` | index | `/shop/` |
| `/shop/?q=…` | noindex | `/shop/` |
| `/shop/?sort=newest` | noindex | `/shop/` |
| `/shop/?pg=2` | noindex | `/shop/?pg=2` |
| `/shop/?q=…&pg=2` | noindex | `/shop/?pg=2` |
| `/shop/?pg=abc` | noindex | `/shop/` |
| `/shop/page/2/` | noindex | `/shop/page/2/` |
| `/shop/page/2/?brand=x` | noindex | `/shop/page/2/` |
| `/<archive de catégorie>?sort=newest` | noindex | `/<archive de catégorie>` |
| `/?q=…` (accueil) | index | `/` |

*(chemins génériques : le relevé a été fait sur le projet de test, sous ses propres slugs.)*

Tests : `IndexingPolicyTest` (chemin nu, page conservée, page 1 et valeur illisible écartées, paramètre
renommé, canonique absente laissée absente).

### R-207 · 🟢 · **fermé le 2026-10-02** (`9c4474a`, constats de revue suivis sous `R-209`) · ouvert le 2026-10-02 — une carte rendue par le serveur porte des instructions de liaison que personne ne lit

**Constat** : `CardBinding` écrivait `data-meili-text`, `data-meili-attr`, `data-meili-class`,
`data-meili-class-list` et `data-meili-if` sur chaque carte, gabarit ou non. Le client ne relie jamais
une carte rendue : il clone le `<template>` et remplace la liste (`ResultsView.show()`,
`replaceChildren`). Mesuré sur le projet de test : quelques centaines d'octets de liaison par carte, soit
plusieurs dizaines de Ko sur une page qui en montre une cinquantaine.

**Correctif** : les marqueurs ne sont écrits que dans le gabarit (`CardBinding::marker()`). Une carte
rendue garde ses valeurs (attributs, texte, classes) et ses crochets `data-meili`, qu'un thème peut
cibler en CSS ; les noms de champ restent validés dans les deux cas. `Contract::VERSION` inchangé : le
client ne lisait ces marqueurs que dans le gabarit.

**Vérifié** au commit `9c4474a` : `composer check` vert (PHP 597, client 915), suite `Modules` 967 tests
(`CardComponentTest::it_binds_only_the_template`). La suite autonome ne tenait pas encore l'absence des marqueurs
sur une carte rendue : ajouté sous `R-209`. Navigateur, WebKit iPhone et Chromium, `submit` et
`immediate` : aucun marqueur hors gabarit sur les pages de cartes du projet de test, cartes
rendues par le client identiques à avant, wishlist initialisée, ajout au panier en ajax.

### R-206 · 🟠 · **fermé le 2026-10-02** (`a1d695c`, `a4b5319`, constats de revue suivis sous `R-209`) · ouvert le 2026-10-01, revu le 2026-10-02 — la carte d'un produit variable ignore le filtre qui l'a trouvé

Réalise la décision validée « Produits variables », jamais livrée. Constaté sur le projet de test : un produit vendu en
deux tailles, à deux prix, est bien trouvé par le filtre de la plus grande, mais sa carte affiche la plus petite et
son prix — la carte était calculée à l'indexation, sans rien savoir des filtres.

**Conception (option A, validée, révisée « au plus près du natif » le 2026-10-01).** Un document par produit ; la carte
porte ses variantes.

- `CardField::Variants` (`variants`) : liste de `Listing\CardVariant` — termes par taxonomie (`facets`), prix affiché
  (`price`, même échelle que `price.min`/`price.max`) et `fields`, les champs de carte qui remplacent ceux du produit.
  Clés nommées par `Enums\VariantField`. Le module n'en projette aucune et ne connaît aucune taxonomie ; un projet
  WooCommerce projette `get_available_variations()`, qui applique déjà la visibilité, le prix vide et le stock.
- `Listing\VariantChoice::shown(array $card)` (copie `results/variant-choice.ts`) : les variantes ne s'appliquent que si
  un filtre actif les concerne — une fourchette de prix, ou une facette dont une variante porte la taxonomie
  (`CardVariant::carriesAny()`). Une variante correspond si, pour chaque facette active qu'elle porte, un de ses termes
  est coché, et si son prix est dans la fourchette (`Range::contains()`). Parmi les correspondantes, la moins chère (la
  première listée à égalité) ; ses champs remplacent ceux de la carte ; `several_variants`
  (`CardField::SeveralVariants`) vaut `true` si plusieurs correspondent. Aucune : carte projetée. La liste est retirée
  de toute carte que le module montre (listing, serveur et navigateur ; panneau de recherche).
- Serveur : `ResolvedListing::cards()`. Navigateur : `CardView.fieldsOf(hit, choice)`, sur l'état auquel répond la
  recherche (`ListingBinding::#repaintGrid`) ; le panneau de recherche passe sans filtre.
- Hors listing, une carte est rendue telle que projetée, sans `VariantChoice` ; le projet de test ne calcule plus ses
  variantes que pour l'index.
- Liaison : `data-meili-class-list` / `CardBinding::classList()` (jumeau TS, parité testée), pour porter les classes
  que la plateforme calcule (bouton de boucle WooCommerce) au lieu de booléens qui en recopient la règle.

**Limite assumée.** Deux facettes satisfaites par deux variations différentes (`400ml` + `iris`, produit vendu en
« 400 ml rose » et « 15 ml iris ») : le produit est listé, aucune variante ne correspond ; la carte reste projetée.
Écrite dans `decisions.md` et `docs/customising/card.md`.

**Écart à la consigne.** Le préfixe devait avoir `From` pour chaîne source : le module traduit déjà `From` en « De »
(`PriceBound::Min`) dans le même sac JSON. Clé `Starting at` dans le catalogue du thème de test.

**Données mal formées (2026-10-02).** PHP lisait le document décodé en tableaux, le client en objets : une liste de
termes stockée en objet (`{"0":"a"}`) passait en PHP et non en TS ; une taxonomie ou un champ à nom numérique était
écarté en PHP (clé entière après décodage) et gardé en TS. Règle commune, la seule que PHP puisse tenir : un tableau ou
un objet JSON se lit par ses valeurs (listes) ou ses clés (dictionnaires), sans regarder le type des clés. *Resserré le
2026-10-02 pour la liste des variantes (`R-209`)* : l'ordre d'un objet à clés entières diffère entre PHP et JavaScript.
Fusion des champs par `array_replace()` (l'opérateur `...` renumérote les clés entières). Quatre cas ajoutés, chacun
rouge d'un seul côté avant correction.

**Tests.** `tests/card-variant-cases.json` (21 cas : une, plusieurs, aucune, fourchette, borne seule, sans filtre,
variante unique, taxonomie non portée, égalité, sans variantes, prix non fini, listes et dictionnaires mal formés,
noms numériques), joués par `Unit\VariantChoiceTest` et `ts/variant-choice.test.ts` ;
`ResolvedListingStateTest::it_shows_each_card_through_the_variant_the_state_points_to` ; jumeaux de noms dans
`ContractParityTest`. Le projet de test couvre sa projection, la carte filtrée, le préfixe et le gabarit.

**Les cinq passes (2026-10-02).** *Lisibilité* : `CardVariants` renommé `VariantChoice` (il choisit, `CardVariant` lit
et compare) ; `variants_several` renommé `several_variants`. *Commentaires* : retirés ceux qui justifiaient un choix
(`CardBinding::classList()`, `#classList()` côté TS, seconde moitié de `CardVariant::read()` des deux côtés) ; une ligne
ajoutée côté TS (un tableau JSON est un objet pour le serveur), retirée sous `R-209`. *Performance* : un `VariantChoice`
par réponse, linéaire en variantes ; côté projet, la carte de page ne charge plus les variations (produit variable :
carte construite deux fois plus vite, mesures entrelacées dans un même processus). *Sécurité* : l'état de l'URL n'est
que comparé ; les surcharges passent par la même liaison (liste blanche, prix seul en HTML). *Contexte et i18n* : rien
de WooCommerce dans le module ; libellé traduisible côté thème.

**Vérifié au commit `9c4474a` (2026-10-02).** `composer check` vert (PHP 597, client 915) ; suite `Modules`
967 verte ; index du projet de test inchangé (tous les documents comparés avant/après, valeurs identiques) ; navigateur
WebKit iPhone et Chromium, `submit` et `immediate` : filtre sur la grande taille → sa variante et son prix au
premier rendu et après clic ; les deux tailles cochées → préfixe et la moins chère ; aucun marqueur hors gabarit ;
ajout au panier en ajax.

**Constaté hors périmètre.** La fiche produit du projet de test est rendue par une vue générique : aucun formulaire de
variations. La pré-sélection est vérifiée sur la fonction native (`wc_dropdown_variation_attribute_options()` lit
`attribute_<taxonomie>` de l'URL).

### R-205 · ⚪ · **fermé le 2026-10-01, sans code** · ouvert le 2026-10-01 — une panne du moteur au rendu du listing serait silencieuse

Relevé par l'audit du 2026-10-01 (« la panne du moteur pendant le rendu serveur n'est ni signalée ni journalisée »).
**Non reproduit.** `ResolvedListing::attempt()` attrape `EngineUnavailable`, marque l'échec, pose `503`,
`Retry-After` et `no-store` (`ServiceUnavailable::sendHeaders()`) **puis appelle `report($failure)`** — depuis
`76e60f7`, sans interruption (`git log -L`). Le listing est le seul appelant serveur de `SearchEngine` (le panneau de
recherche ne passe pas par PHP, `R-19`). Mesuré dans `storage/logs/laravel.log` : 309 lignes « Meilisearch did not
answer », dont 2 en `local.ERROR`, c'est-à-dire sur de vraies requêtes. Ce qui restait vrai : aucun test ne tenait ce
`report()`.

**Test.** Feature `ListingOutageTest::it_reports_an_engine_that_fails_the_server_render` (moteur qui lève
`EngineUnavailable`, `Exceptions::fake()`) : **vert avant tout changement**, gardé comme verrou. La seule panne qui
échappait au repli est celle de `R-14`, fermée le même jour.

### R-204 · 🟠 · **fermé le 2026-10-01** · ouvert le 2026-10-01 — `IndexedTaxonomies` garde les taxonomies du premier appel

Relevé par l'audit du 2026-10-01. `IndexedTaxonomies::all()` mémoïsait sa liste au premier appel (`??=`), et la
classe est liée `scoped` : dans un processus qui dure — une indexation par lots en WP-CLI, une requête d'admin qui
change `meiliscout/indexed_post_types` puis indexe —, les facettes déclarées (`filterableAttributes`), les champs
cherchés (`DefaultSearchableAttributes`) et les libellés projetés (`PostDocument::labelledOnly()`) restaient ceux des
types indexés **au premier appel**. `IndexSearchSettingsTest::forgetIndexWiring()` contournait déjà le défaut à la
main (`forgetInstance()`).

**Test.** Feature `IndexedTaxonomiesTest::it_follows_the_post_types_indexed_since_its_last_answer` : même instance,
types indexés `page` puis `post` (filtre `pre_option_`) ; **rouge avant** (« Failed asserting that an array contains
'category' »), vert après.

**Correctif.** La mémoïsation est clée sur la valeur du réglage : `all()` relit `IndexedPostTypes::all()` (une option
autochargée, déjà en mémoire — `autoload = auto` relevé en base) et ne refait `get_object_taxonomies()` que si la
liste a changé. Coût par document : une lecture d'option et une comparaison de liste courte, au lieu d'aucune ; les
appels à `get_object_taxonomies()` restent un par type indexé et par changement. Limite assumée : une taxonomie
enregistrée **après** le premier appel, à types indexés constants, n'est pas vue — WordPress les enregistre sur
`init`, avant toute indexation.
Vérifié : `IndexSearchSettingsTest::forgetIndexWiring()` reste nécessaire, et pour cette raison — retirer son
`forgetInstance(IndexedTaxonomies::class)` fait échouer `it_searches_the_labels_of_a_taxonomy_without_archives`, qui
enregistre une taxonomie en cours de suite (essai annulé).

**Les cinq passes (audit du 2026-10-01 : `R-204`, `R-14`, `R-51`, `R-205`, `R-159`).** *Lisibilité* : `all()` à un
niveau, `resolve()` reçoit la liste au lieu de la relire ; `$asked` nommé une fois dans `multiSearch()` ;
`ApplyMode::DEFAULT` suit les `DEFAULT_*` voisins ; nombres magiques du test remplacés par `EngineLimits::DEFAULT_*`.
Relevé, non traité (antérieur) : `MeilisearchEngine` répète l'annotation de forme du contrat. *Commentaires* : aucun
ajouté dans `app/`, aucun supprimé ; le commentaire existant de `all()` est gardé. *Performance* : une lecture
d'option autochargée par document, `get_object_taxonomies()` seulement au changement ; une comparaison d'entiers par
`multiSearch()`. *Sécurité* : rien de nouveau n'atteint un filtre ni une vue ; le message d'`incomplete()` ne porte que
deux entiers. *Contexte et i18n* : diagnostics en anglais, aucune chaîne visible ajoutée ; le test des taxonomies ne
dépend que de `post` et `page`, pas de WooCommerce.

**Vérifié** : `composer check` vert ; suite `Modules` verte ; `/boutique` (63), `/boutique?q=creme` (2),
`/?s=creme&post_type=product` (2), `/categorie-produit/visage` (8), tous en `200` ; `config/meilifacets.php` de l'hôte
inchangé. Rien n'est commité, réindexé ni écrit dans le moteur.

### R-203 · 🟠 · ouvert (en attente de commit et de réindexation) · ouvert le 2026-09-30 — liaison d'attributs de la carte, et la carte du thème de test sur l'archive

Réalise deux décisions validées que rien ne tenait : « Carte produit | markup rendu par le serveur, mis à jour par
liaison d'attributs » et « Carte de l'archive produit | bascule sur [la carte produit du thème] ». Ferme `R-45` et
`Q-10`. Rien n'est commité, réindexé ni écrit en base ou dans le moteur.

**Passe de conformité.** *Change* : le client écrit, en plus des cinq crochets de la carte, les champs que la vue
lie par quatre attributs ; le serveur rend ces mêmes champs par un assistant qui applique les mêmes règles ; le thème
décore `CardProjector` et rend sa carte sur l'archive. *Ferme* : `R-45`, `Q-10`, la ligne « Carte de l'archive
produit » de `decisions.md`. *Contredit* : rien — la liaison était décidée, jamais codée ; ajout additif de crochets,
`Contract::VERSION` inchangé (`R-116`). *La plateforme offre* : rien de neutre — `<template>` ne lie rien, Alpine
est une dépendance du thème, pas du module (« classes ES sans dépendance ») ; WooCommerce lie déjà l'ajout au panier
par délégation sur `.ajax_add_to_cart[data-product_id]`, et Alpine initialise un nœud inséré.

**Conception.**

- *Syntaxe*, sur n'importe quel élément de la carte, champ = clé de `card`, nom `[A-Za-z0-9_]+` :
  `data-meili-text="brand"` (texte, `textContent`) ; `data-meili-attr="href:cart_url data-product_id:id"` (paires
  attribut:champ séparées par des espaces) ; `data-meili-class="is-on:flag"` (classe présente si le champ est vrai) ;
  `data-meili-hidden="flag"` (masqué si le champ est vide ou faux), `data-meili-hidden="!flag"` (masqué s'il est vrai).
  Le `!` n'existe que pour le masquage : c'est ce qui permet deux libellés traduits par le thème, l'un ou l'autre
  affiché selon un champ, sans texte dans le module ni dans l'index.
- *Règle unique pour un champ absent* : **il vaut vide**. Texte vidé, attribut retiré, classe retirée, masquage
  appliqué comme pour un faux. Un nœud cloné ne garde donc jamais la valeur d'un autre produit ni celle du modèle,
  et le serveur, qui rend la même vue avec une carte vide pour le `<template>`, produit exactement ce que le client
  produirait.
- *Typage* : texte = chaîne telle quelle, nombre fini en décimal, tout le reste vide ; vrai = `true`, nombre non nul,
  chaîne non vide, liste ou objet non vide. Mêmes règles des deux côtés, verrouillées par des cas partagés.
- *Interdit* : un attribut hors liste blanche — `href`, `src`, `alt`, `title`, `width`, `height`, `value`,
  `datetime`, `aria-*`, `data-*` sauf `data-meili*` (une carte ne réécrit pas le contrat). Donc aucun `on*`, `style`,
  `srcdoc`, `srcset`, `class`, `id`, ni directive Alpine. `href` et `src` n'acceptent qu'une URL `http(s)` ou
  relative, après retrait des tabulations et sauts de ligne que le navigateur ignore ; une autre est retirée.
- *Sécurité* : jamais de HTML — le texte passe par `textContent` et `{{ }}`, l'attribut par `setAttribute` et `e()` ;
  le prix reste le seul HTML, par son crochet existant. Le serveur **refuse** une liaison interdite (exception au
  rendu, comme un crochet mal orthographié, `R-17`) ; le client l'**ignore**, pour une vue écrite à la main.
- *Alpine* : un attribut de liaison suffit. `x-data` reste statique et lit l'identifiant sur son propre élément
  (`$el.dataset`), que le client écrit avant l'insertion.

**Livré.** Module : `BindingAttribute` (enum), `CardBinding`, `BoundValue`, `BoundAttribute`, `BindingRefused`
(`app/View`), `CardDocument::field()`, `$bind` sur `listing.card` ; client : `results/card-binding.ts`,
`bound-value.ts`, `bound-attribute.ts`, appelés par `CardView::show()` après les cinq crochets (la carte de recherche
en profite aussi) ; garde `[data-meili-hidden][hidden]` dans la feuille. Hôte : la méthode de la carte produit du projet qui la prépare pour l'index (lien d'ajout
bâti sur le permalien pour un simple, `add_to_cart_url()` pour les autres types, qui ne dépendent pas de la requête),
dimensions de l'image `portrait` dans la carte, une énumération des champs de la carte et une classe qui la met à
plat (seule traduction entre la carte WooCommerce et les champs liés), un projecteur de carte du projet posé par
`extend` dans `AppServiceProvider`. Thème : un composant de carte abstrait, une carte de page et une carte de
l'index sur **une seule vue**, celle de la carte produit du thème, et la surcharge
`modules/meilifacets/components/listing/card.blade.php` qui la rend.

**Changements de rendu assumés, sur toutes les cartes du site.** Les `aria-label` à nom interpolé (« Ajouter :name
au panier », « Voir les options de :name ») ne se lient pas sans texte dans l'index : le nom accessible devient le
libellé visible suivi du nom en `sr-only` (« Ajouter au panier Crème… », conforme à 2.5.3 *Label in Name*) ; clés
remplacées par « View options ». L'image est un `<img>` simple à la taille `portrait`, **sans `srcset`** : le client
ne sait pas l'écrire (attribut hors liste blanche). Les deux boutons (ajout et découverte) sont rendus, l'un masqué.
Le titre est décodé (`PlainText`), comme dans la carte du module.

**Mesuré.** Rendu serveur d'une carte depuis WordPress et depuis sa projection (`wp eval`, rien envoyé) : identiques,
sauf le lien d'ajout d'un simple, relatif à la page sur l'accueil, absolu dans l'index. Projection du variable 125 :
prix le plus bas seul (26 €), `adds_to_cart` faux, lien vers la fiche, contenance la plus petite. `/boutique` au
premier rendu : la carte du thème de test, mêmes polices, tailles, couleurs et proportions que sur l'accueil (styles calculés
comparés) ; **l'index n'étant pas reconstruit**, marque, contenance et identifiant y sont absents donc vides, seul
« Découvrir » s'affiche, le sac est masqué. Après un filtre (catégorie « cheveux », carte dessinée par le client) :
même carte dégradée, non cassée, Alpine initialisé ; un clone du modèle portant `data-product-id` avant insertion
donne `Number($el.dataset.productId) === 393`. Aucune erreur console. Les images ne se chargent pas en local
(`uploads` absents, redirigés vers la préprod protégée) : sans rapport, déjà le cas avant.

**Reste** : reconstruire l'index — fait le 2026-09-30, voir la fin de l'entrée.

**Passes.** *Lisibilité* : un paramètre booléen (`hidden(string, bool)`) remplacé par `hiding()`, une expression
combinée nommée (`isMalformed()`, `isReserved()`), la lecture d'une valeur déplacée après le refus côté client.
*Commentaires* : rien retiré ; ceux ajoutés disent une règle du navigateur (URL, flottants) ou d'où vient une valeur.
*Performance* : une requête de sélection par carte côté client ; à l'indexation, prix et image d'un produit sont
calculés deux fois (carte du module puis celle du thème, qui la remplace) — accepté, hors chemin de page. *Sécurité* :
liste blanche, URL, échappement et refus serveur couverts par les cas partagés ; `x-data` reste statique, aucune
directive liable. *Contexte et i18n* : projecteur inerte sans WooCommerce (`function_exists`), libellés traduits par
le thème, rien de traduit dans l'index.

**Vérifié** : `composer check` vert (Unit 475, client 818) ; suite `Modules` 832 ; tests hôte de la carte et de sa
projection 8 (la surcharge retirée, deux échouent). `CardComponentTest` lit désormais les vues du
module (`RendersTheModuleViews`) : la surcharge du thème faisait échouer deux de ses tests.

**Correctifs après les deux revues (2026-09-30).** Remplacent ce qui, plus haut, les contredit (`data-meili-hidden`,
`$bind->value()`, la carte de l'index du thème, carte sans `srcset`). Rien commité, rien réindexé, rien écrit dans le moteur.

- *Une seule façon de remplir une carte* : `url`, image, titre et extrait passent par la liaison ; `CardView` ne garde
  que le crochet `price` (seul champ en HTML) ; `#link`, `#image`, `#text`, `#summary`, `#size`, `#textOf`,
  `withDecorativeImages()`, `showWords()`, `CardImage` (et `BLANK`), `CardDocument` retirés. Repli `alt:image_alt|title`
  (`FALLBACK_SEPARATOR`) pour garder le texte alternatif d'avant.
- *Surface Laravel* : la classe du composant prépare chaque élément (`CardBinding::of()`/`template()`, `CardHooks`,
  `BoundField` dont les attributs sont un `ComponentAttributeBag`) ; la vue ne contient que
  `<x-meilifacets::bound :field="$brand" class="…" />` (`shouldRender()`). `value()` n'existe plus.
- *Un élément sans valeur n'est pas dans le DOM* : omis au rendu, retiré par le client après liaison ; le `<template>`
  (`listing.card-template`) les porte tous. `data-meili-hidden` devient `data-meili-if` ; la garde CSS ajoutée pour lui
  est retirée (plus aucun `hidden` posé par une liaison ; `meilifacets.css` revient à l'état commité). `ResultsMotion`
  ne mesure que les lignes, pas leur contenu : non affecté ; une carte gardée par `SectionView` est redessinée depuis le
  même document.
- *Une seule règle de formatage* : `BoundValue` + `NumberText` (algorithme de `String()` : `1e21` → `1e+21`),
  `bound-value.ts` ; valeurs d'attribut sans blancs de bord (comme `ComponentAttributeBag`) ; dimensions entières
  positives ; `srcset`/`sizes` autorisés, chaque URL contrôlée. Cas partagés ajoutés : URL encodées, casse, blancs,
  `data-meili*`/`DATA-MEILI*`, `srcset`, dimensions, textes, mêmes noms de champ refusés.
- *Constantes* : `data-meili` lu depuis `Contract`/`contract.ts`, liste blanche non recopiée dans `BindingRefused`,
  `LIST_SEPARATOR`/`FALLBACK_SEPARATOR`/`CONDITION_BINDING` dans `ContractParityTest`.
- *Images* : `ImageFields` (module) calcule URL, dimensions, `srcset` et `sizes` (avec `auto`, comme
  `wp_get_attachment_image()` pour une image paresseuse), partagé par `DefaultCardProjector` et la carte du thème de test.
- *Hôte* : le projecteur de carte du projet ne rend que les champs du thème pour un produit, délègue sinon, sans
  `function_exists` (posé derrière `DeferredCardProjector` dans `AppServiceProvider`) ; l'énumération d'actions de la carte,
  seule source des libellés ; `add_to_cart.label`, `add_to_cart.ajax`, `image_id`, `cta.url` retirés (l'éditeur lit `cta.label` et
  `add_to_cart.purchasable`, gardés) ; méthodes de la carte du projet renommées. Thème : le composant de carte abstrait devient
  concret, la carte de l'index est supprimée, une carte de wishlist et une énumération de placement au lieu d'un
  booléen de page,
  `data-product-id` écrit à un seul endroit ; le composant de wishlist n'appelle pas l'API sans identifiant valide.
- *Tests* : `KeepsTheIndexOut` passe après `setUp()` (priorité `-1`) — sans cela, lancé seul, il appelait
  `add_filter()` avant le chargement de WordPress.

**Mesuré.** `/boutique` sans surcharge du thème, avant/après : 16 cartes, balises, attributs et textes identiques hors
attributs de liaison ; le `<template>` perd `href=""`, le GIF `BLANK`, `alt=""` et les `hidden`. Accueil : 55 cartes,
`src`, `srcset`, `sizes`, `alt`, dimensions, lien, marque, prix identiques à l'accueil d'avant le lot (24 `srcset`
retrouvés) ; les 62 images produit identiques à `wp_get_attachment_image()`. Chrome : accueil 55 cartes Alpine, zéro
erreur ; `/boutique` premier rendu 16 cartes ; filtre « cheveux » : une carte dessinée par le client, dégradée (index
pas reconstruit : ni marque ni identifiant, donc ni panier ni appel wishlist), zéro erreur. Onglet wishlist non
vérifié en navigateur : il demande une session (redirection vers la connexion) ; couvert par un test.

**Question ouverte.** Une vue de carte écrite pour les seuls crochets n'est plus remplie par le client ;
`Contract::VERSION` inchangé (annoncé par la racine, il ne dirait rien d'une carte surchargée). À trancher par Louis.

**Reste** : reconstruire l'index — fait le 2026-09-30, voir la fin de l'entrée.

**Carte simplifiée (2026-09-30, second passage).** Demandé par Louis : la carte la plus simple et la plus rapide
possible. Remplace ce qui, plus haut, le contredit (`BoundField`, `<x-meilifacets::bound>`, `adds_to_cart`,
`purchasable`, `id` projeté, le composant de carte abstrait du thème). Rien commité, rien réindexé, rien écrit dans le moteur ni en base
(les produits en rupture des tests sont des fixtures créées et supprimées par le test). Décision datée :
`decisions.md`, « La carte ne se remplit que par liaison ».

- *Passe de conformité.* Change : vocabulaire, rendu sans composant par champ, champs de la carte du thème de test, rupture
  de stock. Ferme : rien de neuf, amende ce point. Contredit : la ligne « Carte produit » (« rendus par
  `<x-meilifacets::bound>` »), amendée sur demande. La plateforme offre : `supports('ajax_add_to_cart')` et sa
  classe de boucle ; la clé primaire `ID` de MeiliScout (`PostIndexable.php:51`) ; `is_in_stock()` (`onbackorder`
  compte en stock) ; `wp_calculate_image_sizes()`, qui dérive `sizes` de la largeur de chaque image.
- *Noms* : `CardFieldElement`, `CardFieldValue`, `CardFieldAttribute` (+ `card-field-value.ts`,
  `card-field-attribute.ts`), `CardField` gardé pour le nom d'un champ ; la carte de listing côté thème.
  `components/bound.blade.php` et `View/Components/Bound.php` supprimés.
- *Rendu* : la vue imprime `<balise {{ $element->attributes->class('…') }}>{{ $element }}</balise>` sous
  `@if ($element->isPresent())`. Mesuré (`hrtime`, 55 cartes de l'accueil, rendues par `Blade::renderComponent`,
  meilleur de 30 passes, six séries) : **0,34 → 0,15 ms par carte**,
  **14,9 → 4,0 vues par carte** (822 → 220). L'ancienne voie a été recréée le temps de la mesure, puis supprimée.
  Balisage de l'accueil comparé avant/après sur les 55 cartes : identique hors attributs de liaison renommés.
- *Stock retiré le 2026-09-30* : un état « Indisponible » (`out_of_stock`, un cas `Unavailable` des actions de la carte, aperçu
  de l'éditeur, `cta.out_of_stock` en REST) et des conditions en liste dans `data-meili-if` (`!a !b`) ont été ajoutés
  sans validation, puis retirés : aucun type de produit n'avait été vérifié (variable, groupé, externe,
  `onbackorder`, option « masquer les ruptures »). La rupture de stock sera un point à part, avec le tri en fin de
  listing. `data-meili-if` reste à une condition.
- *Identifiant* : `CardField::Id` (`id`) et `DocumentField::Id` (`ID`) ; le listing demande `ID` au moteur
  (`QueryPlan`, `ListingDescription`) ; `ListingResults::cards()` et `CardView.fieldsOf()` l'ajoutent à la carte
  liée, par-dessus un `id` qu'elle porterait. Paires `ID_FIELD` et `RETRIEVED` dans `ContractParityTest`.
- *Carte du thème de test* : `ajax_add_to_cart`, `cart_url` seulement si achetable et en stock (l'icône panier en dépend),
  « Découvrir » sous `!ajax_add_to_cart` ; un champ faux ou vide n'est pas stocké. la carte à plat du projet
  (`identified()`) ajoute l'id pour une carte de page. Champ REST de la carte gardé tel quel (l'éditeur lit `add_to_cart.purchasable` et
  `cta.label`, toujours là). L'énumération des metas produit du projet est inchangée.
- *Carte indexée*, projection de lecture sur les 62 produits : clés `title`, `url`, `price`, `image_url`, `brand`,
  `cart_url`, `ajax_add_to_cart` (61), `image_width`, `image_height`, `image_alt` (4), `volume` (12),
  `image_srcset`/`image_sizes` (6) ; `id`, `purchasable`, `adds_to_cart` retirés. **898 → 873 octets** en moyenne.
  `image_sizes` gardé : 4 valeurs distinctes sur 6 cartes.
- *Docs* : `configuration.md` (liaison, `id`, rendu ; l'avertissement sur les vues de carte à
  crochets seuls retiré, le module n'étant pas publié), `architecture.md`, `decisions.md`. `Contract::VERSION`
  inchangé : la question ouverte plus haut est tranchée par là.

**Passes (second passage).** *Lisibilité* : côté projet, un paramètre booléen de la carte remplacé par deux
prédicats nommés ; closures de
`onlyWith`/`onlyWithout` qui masquaient `$field` remplacées par `isTrue()`/`negated()` ; un test renommé
avec la méthode qu'il couvre (`it_asks_only_for_the_card_and_its_document_id`). *Commentaires* : aucun ajouté dans le
module ; un retiré côté hôte (docblock de `cartUrl()`, un choix de conception) ; celui de `Bound` disparu avec la
classe. *Performance* : 4 vues par carte ; reste, accepté, que la surcharge du thème ignore `$link`/`$image`/`$title`/
`$price` que `listing.card` prépare (16 cartes sur `/boutique`) ; `is_purchasable()`/`is_in_stock()` appelés jusqu'à
quatre fois par carte, des accesseurs. *Sécurité* : un nom de condition invalide est refusé par le serveur et ignoré
par le client ; l'`id` passe par la liste blanche (`data-*`), `e()` et `setAttribute` ; libellé en `{{ }}`.
*Contexte et i18n* : projecteur toujours derrière `DeferredCardProjector` ; l'identifiant, neutre, ne suppose aucun type de contenu.

**Vérifié (second passage).** `composer check` vert (Unit 561, client 890, dist reconstruit) ; suite `Modules` 923 ;
tests hôte de la carte 15 ; Pint hôte et module. Chrome : accueil 55 cartes, Alpine sur toutes,
`data-product-id` sur toutes, ajout au panier Ajax (`wc-ajax=add_to_cart`, produit 121), cœur hors connexion →
`/mon-compte?redirect_to=…`, zéro erreur console. `/boutique` en `submit` : 16 cartes rendues par le serveur, l'`id`
venu de `ID` sur les 16 (l'index n'ayant pas de champ `id` pour la plupart) ; « visage » coché puis « Appliquer » :
8 cartes dessinées par le client, identifiants présents, Alpine initialisé. En
`immediate` (config modifiée le temps du test, restaurée à l'md5 `cef5aba…`) : « cheveux » coché, carte redessinée
aussitôt ; réponse modifiée en ajout Ajax : `data-product_id="124"` depuis `ID`, clic → `added_to_cart` 124. Zéro
erreur console. Le cœur connecté n'est pas vérifié en navigateur (session requise).

**Index reconstruit le 2026-09-30** (`ddev wp meiliscout index --clear`, sur demande de Louis). `/boutique` au premier
rendu : 30 ajouts Ajax, 3 « Découvrir », `data-product-id` sur chaque carte.

**Troisième passage (2026-09-30, revue du lot).** `CardFieldElement::with()` fusionnait les attributs à la main
(`[...$a, ...$b]`, dernière valeur gagnante, `class` écrasée) : il passe par `ComponentAttributeBag::merge()`, les
classes s'additionnent, un tableau est échappé par Laravel, un sac déjà échappé passe avec `escape: false`. Aucun
appel ne passait deux fois `class` : risque latent, pas de bug observé. `merge()` place les attributs ajoutés en
tête : l'ordre dans le HTML change, sans effet ; `it_takes_the_heading_level_its_context_needs` lit désormais le DOM
au lieu d'une chaîne. Test ajouté : `it_adds_classes_together_and_escapes_only_what_is_not_escaped_yet`. Doc :
`onlyWith`/`onlyWithout` à un seul champ dans `configuration.md`, chiffres de tests remis à jour. `composer check`
vert (Unit 561, client 890) ; `Modules` 923 ; tests hôte de la carte 15.

Garde d'image : un constat de relecture la disait redondante (`ImageFields::of()` rendrait `[]` pour l'ID 0). Faux :
`get_post(0)` retombe sur le post global, et sur une page de pièce jointe `ImageFields::of(0)` rendait l'image de la
page (vérifié). La garde passe dans `ImageFields::of()` (un ID nul ou négatif rend `[]` sans appeler WordPress) et
quitte `DefaultCardProjector` : un seul garde-fou pour tout appelant, carte d'un projet comprise. Test
`it_lends_no_image_to_a_card_without_one_while_the_global_post_is_an_attachment`, rouge sans la garde. `composer
check` vert (Unit 561, client 890) ; `Modules` 924.

`CardBinding` : la règle « absent si vide, sauf dans le modèle » était écrite deux fois (ternaire dans `text()`, garde
niée dans `price()`) et passait par deux paramètres booléens (`isShown(bool)`, `conditional(string, bool)`). Une seule
écriture, `leavesOut($contenu)`, `isTemplate()` nomme `card === null`, `onlyWith`/`onlyWithout` décident eux-mêmes et
appellent `condition()`. Comportement inchangé : cas partagés verts, empreinte des cartes identique.

Petits constats de la revue, vérifiés puis appliqués : cinq commentaires retirés (justifications déjà portées par
`configuration.md` ou `decisions.md`, paraphrases) ; `isAllowed`, `isSafeUrl`, `isSafeUrlList` privés en PHP comme en
TS, testés par `named()` et `accepts()` (le cas `''` sort des URL sûres, un test dédié dit qu'une valeur vide n'est
jamais écrite) ; `NumberText` : `?: '0'` retiré (une mantisse positive commence par un chiffre non nul, `rtrim` ne
peut pas la vider), condition de boucle nommée `readsBackAs()` ; `architecture.md` dit que `site-search.css` cible les
crochets de carte.

Refusé : faire d'`ImageFields::of($id, $size)` une instance construite avec la taille. La taille est un argument au
même titre que l'ID, comme dans `wp_get_attachment_image_src($id, $size)` qu'elle enveloppe, pas un contexte répété ;
chaque appelant a la sienne, et la carte d'un projet, statique, paierait une construction par carte pour rien.

Refusé : « la carte de recherche écrit `loading`/`alt` dans la vue, celle du listing en PHP ». Même règle des deux
côtés : une valeur fixe s'écrit dans la vue (surchargeable sans PHP), une valeur calculée se prépare en PHP
(`ImagePriority` pour `loading`, repli du texte alternatif). En suspens, à la demande de Louis : le repli sur le titre
de l'`alt` de la carte de listing par défaut fait lire le nom deux fois (image et titre dans le même lien) ; le corriger
amende la décision « Résultats de recherche hors de l'ordre de tabulation » (`R-196`).

Mis de côté par Louis, à traiter ensemble : `Listing\Card::prepare()`, second constructeur appelé par `CardTemplate`
(correction minimale : un constructeur `CardBinding|array`, gain de lisibilité seulement) ; le lien, l'image, le titre
et le prix que la carte prépare alors qu'une vue surchargée les ignore (16 cartes sur une page de listing).

Refusé : « un `<a>` sans `href` quand une carte n'a pas d'`url` ». Le projecteur par défaut pose toujours `url`
(`get_permalink()`) ; seul un projecteur de projet qui l'omettrait produit ce cas, et le rendu reste juste (lien inerte,
ni cliquable ni focalisable, image et titre lisibles). « Un élément sans rien à montrer n'est pas dans le DOM » vise les
champs, pas un conteneur qui garde son contenu.

### R-202 · 🟡 · ouvert (en attente de commit) · ouvert le 2026-09-30 — nettoyage des feuilles `meilifacets.css` et `site-search.css`

Rattaché à `R-180`. Nettoyage issu d'un audit : aucun rendu ne change, sauf le bug du tiroir ci-dessous. Rien n'est
commité ni écrit en base ou dans le moteur.

**Bug corrigé.** Dans le sheet, `:first-of-type`, `:last-of-type` et `[data-meili="facet"] ~` comptaient les facettes
que le client masque : si la première était masquée, la première affichée gardait son padding haut et un filet au-dessus
(idem en bas). Les sélecteurs lisent désormais les sections affichées : `:not(<section affichée> ~ *)` pour la
première, `:not(:has(~ <section affichée>))` pour la dernière, `[data-meili="facet"]:not([hidden]) ~` pour le filet
(même sélecteur dans le bloc desktop qui l'annule, sinon sa spécificité perd). **`:nth-child(1 of …)` écarté** : Chrome,
Safari et Firefox le lisent, mais le minifieur de WP Rocket l'écrit `1of` et le navigateur jette la règle — constaté
en local. happy-dom n'évalue ni `:has(~ …)` ni un sélecteur complexe dans `:not()` : le test lit la feuille, vérifié
rouge sur l'ancienne ; le comportement a été vérifié dans Chrome (première et dernière facettes masquées : padding 0 et
pas de filet sur les affichées).

**Fait.** Quatre commentaires retirés du CSS ; ce qu'ils disaient : la marge haute de `price-range` laisse la place
d'ouvrir la bulle et son padding empêche une poignée en bout de piste de déborder ; les flèches natives des champs de
prix sont retirées parce que les bornes disent déjà l'intervalle ; le cadre et l'outline d'un champ de prix ne sont
retirés que dans la boîte qui dessine le focus. Deux blocs `panel` fusionnés ; transition dupliquée de `sort-trigger`
retirée ; survol de `query-clear` rejoint la liste. Jetons `--meili-radius`, `--meili-line`, `--meili-muted`,
`--meili-surface` (documentés dans `configuration.md`), `--meili-section-gap` pour le pied de la colonne ;
`--meili-control-inline` retiré de la recherche (jamais lu). Masquage visuel : une liste par feuille. Garde `[hidden]`
réduite à `[data-meili][hidden]` (tout nœud qui porte `hidden` dans les vues a un crochet). Survols, mouvement réduit et
`48em` regroupés ; la section prix est remontée avant le bloc de mouvement réduit pour que son `transition: none` y
entre sans perdre la cascade. Mouvement réduit : seuls les chevrons et la poignée du prix perdent leur transition, les
couleurs gardent la leur (pied du sheet compris). Thème : `--meili-surface` au lieu de deux `background`.

**Laissé, avec la raison.** `width: 100%` de `apply[data-shape="pill"]` : il n'est pas redondant, il bat la règle
desktop `flex: none; width: auto` (même spécificité, écrite avant), qui est donc morte — à trancher, ça change le rendu.
`font-size` de `drawer-open` : le `font: inherit` de la même règle le remet à `inherit`, il n'est pas redondant.
Le `prefers-reduced-motion` du sheet reste dans le bloc `scripting` (il doit suivre ses règles) ; `(width >= 48em)
toggle:active` reste après le survol qu'il doit battre.

**`--meili-muted`.** `opacity: 0.6` devient `color: var(--meili-muted)` : même rendu du texte, styles calculés
différents (`opacity` 1, couleur à 60 %) ; le `-webkit-tap-highlight-color` hérité par le compteur passe de 14 % à
8,4 %, sans effet visible (le surlignage se peint sur le libellé).

**Voile du tiroir pendant le glisser** (question de la vérification des variables) : non modifié. Trace Chrome à 393,
60 images : 58 recalculs de 3 éléments, médiane 0,086 ms, un seul de 63 éléments à l'ouverture. `--meili-scrim-shown`
est enregistrée `inherits: false` : le sous-arbre n'est pas recalculé. Sortir le voile en élément réel coûterait un
crochet et la parité PHP/TS pour un gain non mesurable.

**`--meili-edge` du thème à l'encre** : essayé puis retiré. Au-delà de la pagination, du tri et des champs de prix,
il met la poignée du sheet (`.meilifacetsDrawerHandle::before`) en encre pleine au lieu de 25 %, et les pastilles de
la barre, qui lisent `currentColor` dans un panneau `CanvasText`, passent de l'encre au noir. Captures dans
`storage/app/edge/` du projet.

**Vérifié** : styles calculés avant/après sur `/boutique` (barre 1440, tri, prix, pastilles, pagination ; sheet 393
fermé, ouvert, prix et pastilles) et le panneau de recherche avec résultats (1440, 393) : seules différences, celles de
`--meili-muted`. Tailles : `meilifacets.css` 44 598 → 43 483 octets (gzip 6 191 → 5 975), `site-search.css`
14 931 → 15 037 (gzip 3 178 → 3 206 : deux jetons de plus, un imbriquement), thème `site-search.css` 1 640 → 1 539.

### R-201 · 🟡 · ouvert (en attente de commit) · ouvert le 2026-09-30 — facette de recherche du listing (produits)

Rattaché à `R-180`. Ferme `R-44`/`Q-09` (le champ est livré) et `R-185` point 2 (visibilité selon le terme, même
règle serveur et client). Décisions de Louis du 2026-09-30, écrites dans `decisions.md` (« Lien voir tous »,
« Facette de recherche du listing », « Ce qu'une recherche lit »). Rien n'est commité, réindexé ni écrit en base ou
dans le moteur.

**Passe de conformité.** *Change* : brique `listing.search` (formulaire GET, champ, effacement) et pastille du terme ;
une recherche lit le filtre et les champs du type `product` des types cherchables ; « Tous les produits » mène à
`/boutique?q=<terme>`. *Ferme* : `R-44`/`Q-09`, `R-185` pt 2, l'écart panneau/page (`acme` : 2 dans le panneau,
5 sur la page avant ; 2 et 2 après). *Contredit* : S-2 et la ligne « Surcharge des types » (lien sans query var,
pas de `with…()` pour l'archive) — révisés par Louis, écrits ; le contrat `Listing` n'est **pas** touché (contrat
séparé `SearchScopedListing`), `Contract::VERSION` reste 1. *Plateforme* : WooCommerce n'échange le drapeau de
visibilité que sur `is_search()` (`class-wc-query.php:929`), rien de natif pour `?q=` ; `URLSearchParams` encode le
lien ; la soumission implicite d'un formulaire à un champ couvre le cas sans JavaScript ; `attributesToSearchOn` du
moteur ; tout le chemin de `q` existait déjà côté module (`StateReader`, `ListingUrl`, `QueryPlan`, `IndexingPolicy`).

**Livré.**
- *Portée d'une recherche* : `Contracts\SearchScopedListing` (optionnel) et `Listing\SearchScope` (`filter`,
  `fields` nullable) ; `QueryPlan::scope()` et `ListingQuery.#scope()` choisissent par la même règle — un terme,
  tapé ou routé, lit la portée de recherche ; publiée en `searchScope` dans la description (clé additive).
  `ProductListing` : `baseFilter()` = catalogue partout (la branche `is_search()` disparaît), `searchScope()` = type
  `product` de `SearchableTypes` (+ terme parcouru), repli `VisibleProducts::inSearch()` sur tous les champs si le
  type n'est pas cherchable. `unfiltered()` suit la même règle pour le terme routé.
- *Brique* : `View\Components\Listing\Search` + `listing/search.blade.php` (d'abord `Query`, puis `QueryField`, voir plus bas) ; rien rendu sur une recherche routée ;
  champs cachés = paramètres que WordPress lit (`PageAddress::fieldsWithout()`) ; `ElementId::listingSearchInput()`.
- *Crochets* (additifs, parité) : `listing-search`, `listing-search-input`, `listing-search-clear` ; règle `listing-search > listing-search-input`.
- *Pastille* : `ActiveValueKind::Search` / `SEARCH_KIND` (`data-kind="search"`), motif `activeValuePatterns.query` (`“:query”`), en tête ;
  `Listing.removeSearch()` (ordre). « Tout effacer » retirait déjà le terme (`cleared()`, `isPristine()`) : testé.
- *Client* : `listing/listing-search.ts` (`ListingSearch`) ; `Listing.searchNow()` ; `SearchTermInput` et
  `DebouncedAnnouncer` déplacés dans `shared/` (`git mv`), gagnent `show()` et `writeNow()` ; `TotalView` écrit par
  un `DebouncedAnnouncer` par compteur, différé pendant la frappe en `immediate` ; `minChars`/`delay` publiés depuis
  `SearchSettings`. Le champ suit l'état quand un autre geste le change, jamais ce qu'il a écrit lui-même — pas par
  le focus : Safari le laisse dans le champ au clic d'un bouton (trouvé à la recette, corrigé, testé).
- *« Voir tous »* : `site-search/see-all-link.ts` (`SeeAllLink`), `SectionView.show(answer, term)`,
  `seeAllParameter` publié ; `SearchableType::withArchive()`.
- *CSS* neutre (`meilifacets.css`, sans commentaire) ; jetons `--meili-listing-search-min` et `--meili-listing-search-width`. Projet de test : brique posée en
  tête du tiroir de `archive-product.blade.php`, bordure dans la couleur de texte du thème (feuille du listing du thème).

**Préalable au futur listing du blog** (hors lot, Louis) : un second `Listing` fait lever `NamedRegistry::sole()`
pour toute brique sans `name` — `archive-product.blade.php` n'en nomme aucune. À corriger avant de déclarer un listing
d'articles (racine nommée, briques relayées vers la racine qui les entoure).

**Laissé ouvert** (fermé par la revue ci-dessous). `R-159` côté listing : un `q` sans lettre ni chiffre (`?q=%3F%28`, ou Entrée sur « ?( ») bascule
en portée de recherche et sert tout le catalogue moins `exclude-from-search`. Sur le projet de test, aucun produit n'est
`exclude-from-search` ni `exclude-from-catalog` (lu le 2026-09-30) : la visibilité n'a pas pu se mesurer sur données
réelles, elle est prouvée par les tests.

**Tests.** Unit : cas partagés `search-scope-cases.json` (`QueryPlanTest` et `listing-query.test.ts`), pastille,
`withArchive()`, jumeau `SEARCH_KIND`. Feature : `ListingSearchComponentTest` (9), `ProductSearchTest` (portée, catalogue quel
que soit le chemin, repli sans type produit), `ProductArchiveTest` (terme parcouru gardé), `ListingDescriptionTest`
(portée, réglages de frappe), `SiteSearchDescriptionTest` (paramètre, surcharge), `ActiveValuesComponentTest`.
Client : `listing-search.test.ts` (15, deux modes), « voir tous » encodé et archive à requête, contrat, annonceur,
saisie. `noindex` sur `?q=` : `IndexingPolicyTest::it_covers_a_sort_and_a_search` (existant). `composer check` vert
(Unit 369, client 720/720, 99,18 % lignes, 93,88 % branches) ; suite `Modules` **OK (717 tests, 2049 assertions)** ;
classes touchées vertes lancées seules.

**Recette Chrome** (`submit` puis `immediate`, 1440 et 393 ; `config/meilifacets.php` restauré, md5
`cef5aba086ea0d3c5645af4a6008f07e` avant et après) : panneau « ser » → « Tous les produits » → `/boutique?q=ser`,
champ « ser », pastille « « ser » », 1 article = compte du panneau, aucune case cochée ; « crème » →
`?q=cr%C3%A8me`. `submit` : frappe inerte, Entrée → `?q=crème`, page 1, 2 articles, comptes réduits ; « Appliquer »
emporte terme et case ; pastille, bouton d'effacement (focus rendu au champ), « Tout effacer » retirent le terme ;
Retour/Suivant restaurent le champ. `immediate` : recherche après la pause, sous le seuil le terme part, Entrée
immédiat, `acme` 2 = panneau, total écrit une seule fois après la frappe, focus gardé ; facette, prix, pastille,
« Tout effacer ». 393 : champ dans le tiroir, bouton 48×48, police 16 px, fonctionne dans les deux modes.
`/categorie-produit/visage` (8 → `?q=creme` 2), `/?s=creme&post_type=product` (2, sans champ, facettes OK), recherche
de l'en-tête : sans régression. Console : aucune erreur ni alerte.

**Les cinq passes.** *Lisibilité* : une règle en un endroit par langage (`scope()`/`#scope()`), aucun booléen en
paramètre (`searchNow`/`removeQuery`, `show`/`showAfterTyping`), `QueryField` à deux groupes de paramètres
(`max-params`) ; un nom par concept (`query` = état, paramètre, crochet). *Commentaires* : aucun en CSS ; deux
contournements dits (Safari et le focus, `URLSearchParams`) ; un test existant qui comparait des nœuds DOM par
`deepEqual` réécrit. *Performance* : `SearchableTypes::all()` mémoïsé, lu une fois par sous-requête ; aucune requête
en plus sans terme ; en `immediate`, une recherche par pause de frappe, comme le panneau. *Sécurité* : le terme ne
va qu'en `q`, jamais dans un filtre ; échappé par Blade (valeur, pastille), écrit en texte côté client, encodé par
`URLSearchParams` dans le lien ; champs cachés décodés puis échappés. *Contexte et i18n* : trois chaînes traduites
(`Search this list`, `Clear the search`, `“:query”`) ; sans WooCommerce, pas de listing produit donc pas de portée ;
rien du projet de test dans le module.

**Commit proposé.** Module : `feat(listing): add the search facet and search what the site search counts`. Hôte :
`feat(shop): place the listing search field in the shop filter bar`.

**Revue de code du 2026-09-30** (non-commité depuis `98115f0` et diff du thème ; corrections validées par Louis). La
brique `Query` garde son nom (renommage en `QueryField` en attente de Louis).

*Bugs.*
1. *Total visible une seconde après la frappe* : le compteur (`total`) s'écrit à chaque réponse ; l'annonce passe par une
   région séparée, masquée et vide au chargement (crochet additif `total-status`, `Hook::TotalStatus`, parité vérifiée par
   `ContractParityTest`), alimentée seule par `DebouncedAnnouncer` (seedé avec le total servi : le même chiffre n'est pas
   redit). `TotalView.showAfterTyping()` → `showWhileTyping()`. `QueryField.isTyping()` ne lit plus le focus : vrai après
   une frappe qui cherche, faux dès qu'un autre geste bouge l'état (`show()` hors `#moving`), qu'Entrée ou l'effacement.
   Test « once typing rests » réécrit, focus laissé hors du champ.
2. *`immediate` retirait un terme d'URL sous le seuil* : `SearchTermInput.startFromServedTerm()` prend la valeur servie
   pour `#term` sans rejouer `#typed()` (le panneau garde `start()`, qui cherche ce qui a été tapé avant le script).
3. *`R-159` côté listing* : un terme sans `\p{L}\p{N}` compte comme `''` — `StateReader::read()` (UTF-8 invalide compris),
   constructeur de `ListingState` (donc `searching()`), qui réutilise `SearchTermInput.holdsAWord()`. Cas partagé ajouté.
   Le `trim()` reste dans `searching()` : dans le constructeur, il aurait cassé la parité `url-writing-cases` (espaces
   insécables, déjà notées dans `R-159`).
4. *Recherche routée* : `q` ignoré — `StateReader` (état, pastille), `QueryPlan::searchTerm()` et `ListingQuery.#searchTerm()`
   préfèrent le terme routé. Cas partagé « a term typed over a routed search is ignored », `q` attendu dans tous les cas.
5. *Sans JavaScript* : `View\StateFields` rend facettes, tri et bornes du prix appliqués en champs cachés (sans `q` ni page),
   échappés par Blade ; `Range::formatBound()` écrit les bornes comme le client.

*Lisibilité.* 6. `searchedTerm()` → `typedTerm()`, `$typing` → `$searchSettings`, `ActiveValuePatterns::searched()` →
`query()`. 7. `QueryPlan::scope($listing, $state)` unique (`unfiltered()` passe un état vide) ; `SearchableType::scope(array
$extraClauses)`. 8. Ligne de `total-view.ts` coupée (fichier réécrit). 9. Commentaires supprimés aux endroits listés, plus le
docblock de classe de `QueryField` et la moitié de celui de `withArchive()` ; `PageAddress::fieldsWithout()` réécrit.
10. Jetons `--meili-field-font-min` (1rem) et `--meili-pill` (999px), sur `[data-listing]` et `[data-meili="search"]`,
remplacent toutes les occurrences des deux feuilles ; documentés dans `configuration.md`.

*Tests.* 11. Cas Safari rejoué dans les deux modes ; « Voir tous » sous `recherche`. 12. Dans les deux modes, focus gardé
dans le champ : pastille (page 1), bouton d'effacement (page 1), « Tout effacer », Retour vers un état sans terme puis
Suivant ; champs cachés, échappement d'une valeur hostile (facette et `post_type`), terme sans mot, recherche routée
(`QueryFieldComponentTest`, 13). 13. `QueryPlanTest` lit les cas par leur clé ; `ActiveValuesComponentTest` place la pastille du
terme devant celle du prix (l'ordre face aux cases est dans `ActiveValueListTest`) ; `ProductArchiveTest` passe un
`WP_Term` en mémoire comme objet interrogé — ni base ni index, plus aucun saut. 14. Chaque nouveau test vérifié rouge en
retirant sa correction (mutations restaurées) : focus, écriture du compteur, seed, rejeu au démarrage, règle `R-159` (TS
et PHP), recherche routée (lecteur, plan, client), champs cachés, échappement (`{!! !!}`), région, ordre des pastilles,
portée de l'archive, paramètre de « voir tous », retour en page 1, suivi de l'état.

*Vérifié.* `composer check` vert (Unit 372, client 731/731, 99,19 % lignes, 93,92 % branches) ; suite `Modules` **OK (724
tests, 2082 assertions)** ; classes et fichiers touchés verts lancés seuls. Build, `module:publish` (aucune orpheline),
caches WP Rocket vidés (`.htaccess`/`index.html` gardés), `view:clear`. Thème non touché par la revue. Recette Chrome
(contexte isolé, `submit` puis `immediate`, 1440 et 393) : `immediate`, total changé 394 ms après la frappe, région vide
pendant la frappe puis « 2 articles » après la pause, focus hors du champ ; `/boutique?q=a` reste filtré (60 articles,
pastille) dans les deux modes ; `?q=%3F%28` sert 63 articles, champ vide, sans pastille ; `/?s=creme&post_type=product&q=zzzz`
sert 2 articles, sans champ ni pastille, `q` retiré de l'adresse au premier geste ; panneau « crème » (2) → « Tous les
produits » → `/boutique?q=cr%C3%A8me`, 2 articles ; pastille et « Tout effacer » avec le focus dans le champ ; tiroir 393 :
champ 16 px, bouton 48 px, Entrée/effacement/frappe. Console : aucune erreur ni alerte. `config/meilifacets.php` restauré,
md5 `cef5aba086ea0d3c5645af4a6008f07e` avant et après.

**Barre desktop et renommage du 2026-09-30** (décisions de Louis, écrites dans `decisions.md`, ligne « Facette de
recherche du listing »).

*Barre.* Cause mesurée à 1440 : le champ réservait sa largeur entière (`flex: 0 1 var(--meili-query-width)`), une
rangée qui passe à la ligne découpe sur les bases, les pastilles tombaient donc sur une 2ᵉ ligne ; « Appliquer »
flottait au milieu (`align-items: center` du sheet). Règles par défaut du module, corrigées dans `meilifacets.css`
(thème non touché) : jeton `--meili-query-min` (12rem) ; champ `flex: 1 1 var(--meili-query-min)`, `width` et
`max-width` à `--meili-query-width` ; sheet `flex-wrap: nowrap` et `align-items: flex-end`. Le corps a la largeur de
son contenu et rétrécit avant que le pied ne passe dessous. Aucun balisage touché, `with-apply` reste `false`, mobile
inchangé (règles sous `48em` seulement). Une 1ʳᵉ version (`flex: 1 1 0` sur le corps) a été écartée à la recette :
à 1920, 612 px entre les pastilles et « Appliquer ». Test : `stylesheet.test.ts`, « the drawer as a bar » (2).

Mesures (Chrome, contexte isolé, cadres à la largeur, champ vide / « creme », x · largeur) :

| Largeur | `submit` | `immediate` |
| --- | --- | --- |
| 1024 | champ 288 et tri en ligne 1, pastilles en ligne 2 ; « Appliquer » 767 · 115, top 403 = dernière pastille | idem, « Appliquer » masqué |
| 1280 | une ligne ; champ 251 (260 avec terme), « Appliquer » à 8 px après « Prix » | une ligne, champ 288 |
| 1440 | une ligne, champ 288, « Appliquer » à 8 px après « Prix » (1060) | une ligne, champ 288 |
| 1920 | idem 1440, total au bord droit | idem |

Effacement visible (48 × 48) avec terme, dans les deux modes. 393 : champ en tête du tiroir, police 16 px,
« Appliquer » dans le pied, aucune règle desktop appliquée. `submit` : case → rien, « Appliquer » →
`?categorie=cheveux&q=creme` ; pastille retirée ; tri → `sort=price_asc`. `immediate` : frappe → `?q=creme` (2
articles), case, tri `price_desc`, effacement → terme retiré. Console : aucune erreur ni alerte.
`config/meilifacets.php` restauré, md5 `cef5aba086ea0d3c5645af4a6008f07e` avant et après.

*Renommage.* `View\Components\Listing\Query` → `QueryField`, vue `listing/query-field.blade.php`, balise
`<x-meilifacets::listing.query-field>`, classes `meilifacetsQueryField*` (le thème ne les cite pas). Crochets
`query`, `query-input`, `query-clear` inchangés, pas d'alias. `QueryFieldComponentTest` (renommé) ;
`ComponentFoldersTest` : balise résolue, ancienne balise refusée, vue surchargée par un thème sous son nouveau nom.
Thème : une ligne dans `archive-product.blade.php`.

*Cinq passes.* Lisibilité : un seul nom (`QueryField`) côté PHP et TS ; `overrideView()` extrait dans
`ComponentFoldersTest` (doublon retiré). Commentaires : aucun en CSS, aucun supprimé. Performance : rien (CSS).
Sécurité : rien touché à l'échappement. i18n : aucune chaîne nouvelle.

*Vérifié.* `composer check` vert (Unit 372 dont neutralité et parité, client 733/733, 99,19 % lignes, 93,92 %
branches, `build:check`) ; suite `Modules` **OK (727 tests, 2085 assertions)**. Build, `module:publish` (aucune
orpheline), thème construit, caches WP Rocket vidés (`.htaccess`/`index.html` gardés), `view:clear`,
`dump-autoload`.

**Revue finale du 2026-09-30** (non-commité depuis `98115f0` et diff du thème ; corrections demandées par Louis).

*Bloquant.* `MeilisearchEngine` ne transmettait pas `attributesToSearchOn` : la portée de `QueryPlan` était perdue au
premier rendu (`/boutique?q=a` : 60 articles servis, 18 au premier geste ; comptes des facettes faux). La traduction
du plan vers le SDK sort dans `Search\MeilisearchQuery` (`from()`), qui pose `setAttributesToSearchOn()` (SDK installé,
`SearchQuery.php:425`) sur toute requête qui le porte : résultats, comptes disjonctifs, bornes du prix, liste non
filtrée d'une recherche routée. `MeilisearchQueryTest` (3) : rouge sans l'option.

*Commentaires.* 20 supprimés ou réécrits (liste de la revue) ; paramètre `lastWritten` → `alreadySaid` ; mentions
« (Louis, 2026-09-30) » retirées de six tests ; le commentaire gardé de `FilterExpression::number()` passe dans le
docblock de `overlapping()`.

*Conventions.* Règle « terme présent » : `searchTerm()` (PHP, `QueryPlan`) et `#searchTerm()` (TS, `ListingQuery`) ;
les pastilles du terme : `queried()`/`#queried()`. Portée : `QueryPlan::scope()`, le nom de la doc. `isRoutedSearch()`
écrit une fois (`StateReader::isRoutedSearch()`, statique, lu par `QueryPlan` et `ResolvedListing`).
`SearchableType::withoutArchive()` ; `withArchive()` n'accepte plus `null`. `View\HiddenField` (`final readonly`)
remplace `array{name, value}` dans `StateFields` et `PageAddress`. `drawer-sheet` sorti du groupe de règles.
Crochet additif `drawer-body` (`Hook::DrawerBody`, `Contract::VERSION` inchangé) sur le corps du tiroir ; les trois
règles qui visaient `.meilifacetsDrawerBody` le visent (`R-128`) ; le thème ne cite pas la classe (grep).
`Range::boundTo()` → `Range::formatBound()` (PHP et TS), `FilterExpression::number()` supprimé. `QueryField.#elementsIn()`
remplace la double négation. `TotalView` réécrit toujours ses compteurs : ils ne sont plus une région live (le test
« leaves a counter alone », fondé sur ce motif, est supprimé). Chemins `shared/` dans la doc, `query-field.ts` listé.

*Tests.* `#typing` remis à faux par Entrée et par l'effacement (`query-field.test.ts`, 2) ; `1e-9` et `1e21` (`RangeTest`,
`range.test.ts`) ; `ProductSearchTest` compare la portée à des valeurs littérales ; cas partagé « a lone no-break space
browses the catalogue » ; `DrawerComponentTest` : le slot est dans `drawer-body`. Chaque nouveau test vérifié rouge en
retirant sa correction, puis restauré.

*Vérifié.* `composer check` vert (Unit 378, client 736/736) ; suite `Modules` **OK (733 tests, 2098 assertions)** ;
17 classes touchées vertes lancées seules. Build, `module:publish` (aucune orpheline), caches vidés
(`.htaccess`/`index.html` gardés), `view:clear`, `dump-autoload`. Thème non touché. Recette Chrome (contexte isolé) :
`submit` — `/boutique?q=a` 18 articles servis et 18 en page 2, comptes identiques (marques 3/1/13/0/1/0) ; Globex →
3 articles, comptes client = HTML serveur ; pastille, « Tout effacer » (63) ; panneau « crème » (2) → « Tous les
produits » → `?q=cr%C3%A8me`, 2 ; barre à 1024 (champ 288, « Appliquer » sur la ligne des déclencheurs), 1280 et 1440
(une ligne, « Appliquer » à 1060) ; 393 : corps du tiroir stylé par le crochet (padding 40/32, défilement), champ
16 px, Entrée → `?q=lait`. `immediate` — mêmes 18 au premier rendu et en page 2 ; case, pastille du terme,
Retour/Suivant, frappe « creme » (2, focus gardé), « Tout effacer », panneau « ser » → `?q=ser` (1), tiroir 393 :
frappe et effacement. Console : aucune erreur ni alerte. `config/meilifacets.php` md5
`cef5aba086ea0d3c5645af4a6008f07e` avant et après.

*Espacement du champ dans le tiroir mobile* (relevé par Louis). Le corps du tiroir gardait son padding haut de
40 px (`--meili-drawer-block`) quand il commence par le champ, contre 24 px sous le champ. Le padding haut passe à
`--meili-section-gap` dans ce cas seul (`[data-meili="drawer-body"]:has(> [data-meili="listing-search"]:first-child)`, bloc
mobile). Mesuré à 393 : 24 px au-dessus du champ, 24 px jusqu'au titre du tri. Sans champ, 40 px comme la maquette.
Un premier jet (trait sous le champ, padding inchangé) a été retiré : ce n'était pas la demande. Test :
`stylesheet.test.ts`, « pulls the body up to the section gap » (rouge sans la règle).

*Renommage autour de « search »* (2026-09-30, décision de Louis écrite dans `decisions.md`, pur renommage, sans
alias). Brique `Listing\QueryField` → `Listing\Search`, vue `listing/search.blade.php`, balise
`<x-meilifacets::listing.search>` ; client `QueryField` → `ListingSearch` (`listing/listing-search.ts`) ; crochets
`query`/`query-input`/`query-clear` → `listing-search`/`listing-search-input`/`listing-search-clear` ; classes
`meilifacetsListingSearch*` ; jetons `--meili-listing-search-min`/`-width` ; pastille `ActiveValueKind::Search`,
`SEARCH_KIND`, `data-kind="search"`, `Listing.removeSearch()` ; `ElementId::listingSearchInput()`, d'où l'`id` du
champ `meilifacets-<listing>-listing-search-input` (aucune référence ailleurs dans la page). Gardés : `state.query`,
`reserved.query`, `activeValuePatterns.query` (`“:query”`), `QueryParameter::Query`/`q`, `MAX_QUERY_LENGTH` — ils
nomment le terme de l'état, que la recherche routée porte aussi sans la brique — et les requêtes au moteur
(`QueryPlan`, `FacetQuery`, `MeilisearchQuery`, `SiteSearchQuery`). `Contract::VERSION` reste 1 : ces crochets n'ont
jamais été publiés. Tests : `ComponentFoldersTest` résout `listing.search` vers `Listing\Search` **et** `search` vers
la racine, refuse `listing.query-field`, prend la vue surchargée sous `listing/search` ; `contract.test.ts` : un
`listing-search` orphelin est nommé par le listing et non par la recherche (`R-189`). Thème : balise de
`archive-product.blade.php`, crochet de `components/listing.css`. Vérifié : `composer check` vert (Unit 378, client
743/743) ; suite `Modules` **OK (734 tests, 2099 assertions)** ; build, `module:publish` (orpheline
`ts/listing/query-field.ts` supprimée), thème construit, caches vidés, `view:clear`, `dump-autoload`. HTML de
`/boutique?q=creme` : seuls crochets, classes, `id` du champ, `data-kind` et versions d'assets diffèrent ; styles
calculés du tiroir (215 éléments) identiques à 1440 et à 393 ouvert. Recette `submit` : frappe, Entrée → `?q=creme`
(2), pastille, effacement, « Tout effacer », panneau « creme » → « Tous les produits » → `?q=creme` (2) ; console
sans erreur.

### R-200 · ⚪ · ouvert (en attente de commit) · ouvert le 2026-09-30 — noms internes qui disaient une métaphore plutôt que ce qu'ils font

Issu d'un audit de nommage, liste validée par Louis. Pur renommage : aucun comportement, aucun crochet `data-meili`, `Contract::VERSION`
inchangé (`1`). Aucun alias, fichiers déplacés par `git mv`. Rien n'est commité, réindexé ni écrit en base ou dans le moteur.

**Client (TypeScript).** `Departure` → `ExitFade` (`exit-fade.ts`), ses types `Place`/`Origin`/`Frames` → `ExitFadeStart`/
`ExitFadeOrigin`/`ExitFadeLayout` ; `PanelRoom` → `PanelAvailableHeight` (`panel-available-height.ts`), jeton
`--meili-search-room` → `--meili-search-available-height` ; `SearchesUnderWay.leave()/end()` → `PendingSearches.start()/finish()`
(`pending-searches.ts`), `BusySpan` → `BusyListener` ; `HistorySeam`/`SearchSeam`/`WithdrawSeam` → `ListingHistory`/`Searcher`/
`ValueRemover` ; `isMeasuredApart` → `isMeasuredSeparately` ; `NewPills` → `NewActiveValues` (`new-active-values.ts`), `#pill` →
`#activeValue` ; `Listing.withdraw()/withdrawPrice()` → `remove()/removePrice()` ; `heldIn`/`showHeld` → `selectedIn`/`showSelected` ;
`searchesAtOnce`/`#byMode`/`#atOnce`/`#moveTo`/`#announce` → `appliesImmediately`/`#applyIfImmediate`/`#applyNow`/`#setState`/`#dispatch` ;
`EntranceTiming` → `AnimationTiming` ; `PanelMotion.pop()/drop()` → `showFloating()/hideInstantly()` ; `HeldPaint` → `DeferredRepaint`
(`deferred-repaint.ts`), `ListingDrawers.paintPage` → `repaintBehindDrawer` ; `Drawn`/`isDrawn` → `StylableElement`/`isStylable`
(`stylable-element.ts`) ; `FocusLanding.root()` → `FocusFallback.target()` (`focus-fallback.ts`) ; `Typing`/`TypingSettings`/`TypedTerms`
→ `SearchTermInput`/`SearchTermSettings`/`TermListener` (`search-term-input.ts`) ; `ComboboxMove.action` `follow`/`hold`/`release` →
`openLink`/`ignore`/`deactivate`, `offer()` → `setOptions()`.

**Serveur (PHP).** `QueryPlan::apart()` → `measureWithout()`, `$apartKeys` → `$separatelyMeasuredKeys`, `isMeasuredApart()` →
`isMeasuredSeparately()` ; `PagePlacement::placeApart()` → `placeOnItsOwn()` ; `Http\Unavailable::announce()` →
`ServiceUnavailable::sendHeaders()` ; `DefaultTerm` → `DefaultTermVisibility` ; `FacetValueOrder` → `EngineFacetSort` ;
`FieldsOutsideSearchOrder`/`ensureWithinOrder`/`$order` → `UnsearchableFields`/`ensureSearchable`/`$searchable` ; `SortSummary` →
`SortCaption` (propriété `$caption`, classe `meilifacetsSortCaption` : libellé du bouton replié comme légende des boutons radio) ; `Apply::onlyInSheet()` → `onlyInDrawer()` ;
`SearchRegistry::open()` → `add()` ; `Search\SearchResults`/`SearchFailed` → `Search\ListingResults`/`EngineUnavailable`, laissés à
côté de ce qui les produit (`ListingSearch`, `MeilisearchEngine`) ; vue du prix : `data-taxonomy` → `data-filter`, `Price::$facet` →
`$filter`, alias `Declaration` retiré.

**Tests.** `CounterSeamBindingTest`/`ProductSeamBindingTest` → `…DefaultBindingTest` ; `ProbeSearchCard` → `ProjectSearchCard` ;
`PriceRangeTest` découpé en `RangeTest` (5), `PriceFilterTest` (8, `PriceBound` compris) et `FilterExpressionTest` (+3, `overlapping()`).
Les noms de test qui citaient un ancien nom suivent (`…_placed_on_its_own`, `…_measured_separately`).

**Écarts à la proposition.** `countWithout`/`isCountedSeparately`/`separateCountKeys` refusés : la recherche à part lit aussi les bornes
du prix, qui ne sont pas un compte (`countQueries` et `boundsQueries` dans `ListingSearch`) ; « measure » est déjà le mot du PHPDoc de
`QueryPlan`. `Price::__construct(… $facet)` garde son nom : c'est l'attribut Blade `facet="…"`, commun avec `listing.facet`.
`data-filter` n'est lu par aucun code du client ni couvert par le contrat : aucun crochet ni parité à ajouter.

**Vérifié.** `composer check` vert (Unit 359, client 691/691) ; suite `Modules` **OK (691 tests)**. HTML de `/`, `/boutique`,
`/categorie-produit/visage`, `/?s=creme&post_type=product` comparé avant/après, jetons Gravity Forms et `ver=` neutralisés : seuls
`data-filter` et `meilifacetsSortCaption` diffèrent — plus, sur `/categorie-produit/visage`, la valeur « Soins visage », due au produit
116 modifié en base à 08:44 par un tiers entre les deux captures. Thème et plugin d'expéditions du projet : aucune référence (grep). Recette
Chrome : `/boutique` facette, prix, tri, pastilles (focus rendu au listing), pagination, tiroir mobile ; recherche du header ouverture,
frappe, ↓/Entrée, Échap ; console vide.

### R-199 · 🟡 · ouvert (en attente de commit) · ouvert le 2026-09-29 — revue de code avant commit (`R-195` à `R-198`)

Rattaché à `R-180`. Revue du non-commité depuis `378f1cb`, corrections validées par Louis. Rien n'est commité, réindexé
ni écrit en base ou dans le moteur. Un agent concurrent avait entre-temps renommé `isMoving()` en `wasMoving()` et
regroupé le seuil en `STILL` dans `panel-height.ts` : sa lecture unique est gardée, ses noms remplacés par ceux de la revue.

**Constats et suite donnée.**
1. *Annonce muette après fermeture* : le client observe `data-open` sur la racine (`PanelClosing`, `MutationObserver`),
   sans rien importer du chargeur ; à la fermeture, `StatusView::closed()` → `DebouncedAnnouncer::forget()` annule la
   minuterie, oublie la phrase en attente et la dernière écrite, vide la région cachée. Vérifié en ligne : fermé pendant
   le délai, région vide ; rouvert, « sham » → « Produits : 19 résultats » écrit.
2. *`data-instant` pouvait rester posé* : `try { change(); flush } finally { removeAttribute }`.
3. *Hauteur réservée jamais libérée* : `#releaseRoomOnceGone()` n'attend que les transitions `opacity`/`transform`
   (`PanelTransitions`, `shared/`), par `Promise.allSettled`, puis revérifie `#isOpen()`.
4. *`:limit="true"` accepté comme 1* : booléen refusé (« asks for true results… »), `-1` aussi.
5. *Transition de thème prise pour une entrée* : seules les transitions `opacity`/`transform` comptent ;
   `isEnteringOrLeaving()`, lu une fois par réponse dans l'instantané (`panelEnteringOrLeaving`).
6. *Ordre d'appel imposé* : `PanelHeight::before()` rend un instantané (`HeightBefore`), `after(before)` un
   `PanelResize` qui porte `overhang()` et `play()` ; plus d'état entre méthodes publiques (le redimensionnement en cours
   est retrouvé par son `id` WAAPI).
7. *Deux sources de l'état ouvert* : verrou mobile sur `:root:has([data-meili="search"][data-open])`. Coût mesuré à 393
   (2 839 éléments, 400 bascules × 3) : 0,22 ms par recalcul, contre 0,25 ms avec l'ancien sélecteur.
8. `--meili-ease-resize` (défaut `var(--meili-ease)`), lu par `#readTimings`, documenté avec `--meili-duration-resize`.
9. Renommages : `EXIT_OFFSET`/`NO_OFFSET`/`#exitOffset`, `SUBPIXEL` (`shared/subpixel.ts`, seule déclaration),
   `#changesAtOnce`, `ANNOUNCE_DELAY_MS`/`#lastWritten`/`#restartDelay`, `#altText`/`#altFromCard`/`#noAltText`,
   `exitAnimations`, `invalidLimits`/`it_refuses_a_limit_below_one_or_not_whole`, `nextTurn` (`tests/ts/dom.ts`, seul
   utilitaire, repris par `settle()`), `saidOnceSettled()`.
10. Conditions extraites : `#isShownAndAnimated()`, `#heightChanged()`, `#isNew()`, `#takeLinksOutOfTabOrder()`.
11. Commentaires : 7 supprimés (`PanelHeight` ×5, `#readAfter`, « What the script still plays… »), 2 réécrits.
12. Docs : sortie des cartes 120 ms (`decisions.md`, `chantier-recherche.md`), jetons de mouvement complétés dans
    `configuration.md`, espace parasite retiré.
13. Tests renforcés : `finished` rejeté, `setTimeout` espionné, `failed()` écrit puis `cleared()`, test du mouvement
    réduit renommé.
14. Assertions sur des éléments DOM : 2 + 2 dans le diff, et 15 hors du diff trouvées au grep de `tests/ts`
    (`active-values-view`, `drawer`, `disclosure-group`, `pagination-view`), toutes en `a === b, true`.
15. Ajoutés : règle `forced-colors` de la barre, `tabindex="-1"` d'un lien imbriqué, délai figé à 1000 ms, ordre réel
    des enfants de la racine, `followsContent` retiré.

**Vérifié.** `composer check` vert (Unit 359, client 691/691, 99,18 % lignes, 93,96 % branches) ; suite `Modules`
**OK (691 tests)** ; seules : `SearchCompositionTest` 24, `PublishedAssetsTest` 3, `ComponentFoldersTest` 17 ; chaque
fichier TS touché vert seul. Nouveaux tests rouges sans leur correction (mutations temporaires). Recette Chrome 1440 et
393 : ouverture, frappe, 4 → 1 (« sham » → « shampoing » : 465,5 → 237,5 px en 200 ms en desktop, fantômes effacés sur
place ; 780 px fixes en mobile, fondu avec recul de 4 px), Échap, voile (desktop) ou clic hors du panneau (mobile),
↓ + Entrée, état vide, panne simulée, fermeture/réouverture annoncée ; console : seule l'erreur provoquée. `/boutique` :
4 facettes, `?categorie=cheveux` 1 article → 3 en cochant « corps », console vide.

**Cinq passes.** *Lisibilité* : aucun booléen en paramètre, méthodes ≤ 10 lignes, `ResultsMotion` sous le plafond de 200.
*Commentaires* : ajoutés `forget()` et `PanelClosing` (contexte inter-paquets), aucun en CSS. *Performance* : un
`getAnimations()` de plus par réponse (redimensionnement retrouvé par `id`), un observateur d'attribut par racine.
*Sécurité* : rien de dynamique. *Contexte/i18n* : aucune chaîne ; message de `limit` en anglais.

### R-198 · 🟡 · ouvert (en attente de commit) · ouvert le 2026-09-29 — résultats qui diminuent : animés en mobile, pas en desktop

Rattaché à `R-180`, à la suite de `R-193` et `R-197`. Signalé par Louis : quand les résultats passent de 4 à 1, l'animation
se voit à 393, pas à 1440. Rien n'est commité, réindexé ni écrit en base ou dans le moteur.

**Conformité.** *Change* : `ResultsMotion`, `Departure`, `SearchPanel`, `site-search.css` ; nouveau `PanelHeight`
(`ts/site-search/`). *Ferme* : ce constat. *Contredit* : « nothing but `transform` and `opacity` is animated » de
`R-193` — exception assumée pour la hauteur du panneau (`decisions.md`, « Étape 7 : résultats pendant la frappe »).
*La plateforme offre* : WAAPI (`height` et `overflow-y` discret en keyframes), aucune dépendance ; aucun crochet ajouté,
`Contract::VERSION` inchangé.

**Cause confirmée par la mesure** (1440, « se » → « ser », 6 cartes → 2, images relevées à chaque `requestAnimationFrame`).
En mobile, le panneau a la hauteur de la place (`--meili-search-room`) : la section Articles remonte et glisse (FLIP
150 ms, 569 → 341 px). En desktop, sections côte à côte et panneau en `height: auto` : rien ne change de place, et la
hauteur **saute** de 465,5 à 243,5 px à la première image. Pire, les fantômes étant bornés au nouveau bas du panneau,
**2 cartes sortantes sur 4 disparaissaient d'un coup** ; les 2 autres s'effaçaient en 80 ms (opacité 0,46 à 26 ms,
0 à 84 ms), trop court pour être vu.

**Correction.**
- `PanelHeight` : hauteur vue lue avant l'écriture (dans la lecture groupée de `#measure()`), l'animation en cours
  annulée puis la hauteur naturelle lue après (en tête de la lecture groupée de `#after`), puis WAAPI `height` de l'une
  à l'autre en **200 ms** sur `--meili-ease` (`--meili-duration-resize`, déclaré sur la racine de recherche — le jeton
  de 270 ms du listing vit sur `[data-listing]`). Détecté par la hauteur calculée : un panneau à la hauteur de la place
  ne change pas de hauteur, rien ne part en mobile. `overflow-y: hidden` porté par les deux keyframes, pour qu'aucune
  barre de défilement n'apparaisse pendant que le panneau grandit ; `auto` revient à la fin.
- Interruption : la hauteur « vue » est la boîte en cours d'animation ; la nouvelle part de là.
- Rien pendant l'entrée du panneau (sa propre animation ne compte plus comme une entrée), panneau fermé, mouvement
  réduit (hauteur immédiate) ; au clavier, `SearchPanel::#instantly()` termine ce qui joue encore sur le panneau.
- Fantômes : le bas visible tient compte de ce que le panneau montre encore sous son nouveau bord (`overhang`), les 4
  cartes s'effacent donc sur place. Sortie **120 ms** avec recul `translateY(-4px)` (opacité et `transform`), identique
  en mobile et en desktop ; fondu seul en mouvement réduit.
- `align-content: flex-start` sur le panneau à partir de `48em` : sans lui, pendant que la hauteur redescend, les lignes
  flex s'étiraient dans la hauteur en trop et les sections descendaient de 111 px avant de remonter (vu au premier
  essai : CLS 0,026 par réponse ; c'est le « le contenu saute » de Louis sur « se » puis « r »).

**Mesures, avant → après** (1440, « se » → « ser », ms après l'écriture ; hauteur du panneau · opacité des sortantes) :
0 : 243,5 · 1 (2 sur 4) → 465,5 · 1 (4) ; 40 : 243,5 · 0,29 → 359,8 · 0,69 ; 80 : 243,5 · ∅ → 280,1 · 0,21 ;
120 : 243,5 · ∅ → 251 · 0,01 ; 160 : 243,5 → 244,5 ; 200 : 243,5 → 243,5. Recul des sortantes : 0 → −4 px. Sections
immobiles à 176 px, aucun `layout-shift`. Interruption (durée portée à 600 ms le temps du test) : 465 → 297,9 puis
remontée vers 465, plus grand pas entre deux images 14 px, aucun retour à l'ancienne valeur. 393 : panneau à 780 px
constant, aucune animation de hauteur, FLIP d'Articles intact.

**Images perdues** (traces, 6 réponses, écran 120 Hz, `PipelineReporter` du rendu, un `DROPPED` doublé d'une image
présentée au même vsync non compté). ×1 : **0**, aucun intervalle au-delà de 9,3 ms pendant l'animation. ×4 : 0
pendant l'animation (tâches par image ≤ 5,5 ms, une à 10,2 ms sans perte) ; **1 image** à l'image de la réponse dans
3 réponses sur 6 — le rendu de la réponse (style 3–6 ms à ×4), déjà là sans le redimensionnement : même trace avec
l'animation du panneau neutralisée, 1 à 3 images perdues au même endroit dans 5 réponses sur 6. Traces :
`storage/app/jank/resize-*.json.gz` (hôte, non versionné).

**Tests.** Client : `results-motion` (+7 : hauteur animée de l'ancienne à la nouvelle en 200 ms, fantômes gardés dans
ce que le panneau montre encore, panneau à hauteur de la place laissé tel quel, mouvement réduit, sortie sans recul en
mouvement réduit, rien pendant l'entrée, interruption depuis la hauteur vue qui laisse entrer les nouvelles cartes) et
deux tests adaptés (sortie 120 ms + recul) ; `search-panel` (+1 : le clavier termine ce qui joue sur le panneau, le
pointeur non) ; feuille (+1 : `align-content`). `composer check` vert (Unit 359, client 680/680), suite `Modules`
**OK (689 tests)**.

**Cinq passes.** *Lisibilité* : `PanelHeight` porte la hauteur (lecture avant, lecture après, débordement, animation),
`ResultsMotion` passe de 180 à 198 lignes utiles (plafond ESLint 200) ; aucun booléen en paramètre. *Commentaires* : docblocs de
contournement (lecture pendant l'animation, barre de défilement pendant la croissance) ; aucun dans la CSS.
*Performance* : une lecture de boîte de plus avant et après, dans les lectures groupées existantes ; animer `height`
relance la mise en page du seul panneau (`position: absolute`, `contain: layout paint`), coût mesuré nul à ×4 sur
l'image de la réponse. *Sécurité* : rien de dynamique. *Contexte/i18n* : aucune chaîne.

**Revue du diff** (demandée par Louis) : deux points corrigés — `getAnimations()` du panneau lu deux fois par réponse
(`ResultsMotion` et `PanelHeight`), désormais une seule dans `measureBefore()` ; seuil `STILL` (0,5 px) déclaré en double,
désormais exporté par `panel-height.ts`. Rien de mort : chaque méthode ajoutée est appelée et couverte (`panel-height.ts`
100 % lignes et branches). Écarté : `prefersReducedMotion()` lu à plusieurs endroits par réponse (`matchMedia`, sans
mise en page), comme avant.

**Commit proposé** (module) : `fix(search): ease the panel height when results shrink on desktop`.

### R-197 · 🟡 · ouvert (en attente de commit) · ouvert le 2026-09-29 — ouverture de la recherche saccadée en desktop, fluide en mobile

Rattaché à `R-180`, à la suite de `R-196`. Signalé par Louis : ouverture et fermeture du panneau fluides à 393, pas à 1440.
Rien n'est commité, réindexé ni écrit en base ou dans le moteur ; `config/meilifacets.php` et le thème non touchés.

**Mesure** (Chrome, traces de performance, 1440 × 900 × 2 et 393 × 852 × 3, écran à 120 Hz ; clic `detail: 1` rejoué
dans la page — les clics réels de l'outil DevTools tombaient hors de la trace ; images comptées sur le `PipelineReporter`
du rendu, hors images `FORKED`).
- *Pistes écartées par la mesure.* Voile sans pseudo-élément (`content: none`) ou en-tête sans `backdrop-filter` :
  mêmes chiffres qu'avec (3 images `DROPPED` rapportées à chaque ouverture, dont une seule avant la première image
  présentée). Peinture ≤ 1 ms, raster ≤ 0,3 ms, `Layout` ≤ 0,4 ms par phase à chaud : ce n'est pas le rendu. Le voile a
  bien sa couche pendant sa transition (`ActiveOpacityAnimation`) ; le `backdrop-filter` ajoute deux passes de rendu par
  image (57 contre 19) sans image perdue.
- *Cause.* `PanelRoom.measure()` lit la géométrie dans le gestionnaire du clic, juste après `hidden = false` : le style est
  recalculé de force. En desktop, `[data-meili="search"]:has([data-meili="search-toggle"][aria-expanded="true"])::after`
  y pèse **9,27 ms sur 11,6 ms** de sélecteurs (statistiques de sélecteurs, 10 essais, 1 correspondance) ; en mobile,
  seul `:root:has(…)` joue (0,87 ms). Première ouverture après chargement, 1440 sans limitation : style forcé
  **17,4 ms + layout 7,9 ms**, **8 images perdues** d'affilée au début du fondu (≈ 65 ms figées) ; ×4 : 19,4 + 10,9 ms,
  8 images. À chaud ×4 : 3 à 3,9 ms de style forcé à chaque ouverture. En mobile, première ouverture : style 1,15 ms.

**Correction.** `SearchPanel` pose `data-open` (`OPEN`, `shared/attributes.ts`) sur la racine en même temps
qu'`aria-expanded`, et le retire à la fermeture ; le voile devient `[data-meili="search"][data-open]::after`. Rien d'autre :
courbes, durées, `transform`/`opacity` seuls, voile en pseudo-élément et `PanelRoom` inchangés. Aucun crochet ajouté ;
l'attribut est un état, comme `data-instant`, et vaut aussi pour une disposition libre (la loupe peut être n'importe où
dans la racine).

**Après.** Sélecteurs du recalcul forcé : **11,6 → 0,45 ms** (voile : 9,27 → 0,03 ms). 1440 × 1, première ouverture :
style forcé 17,4 → **0,94 ms**, layout 7,9 → 0,91 ms, images perdues pendant l'ouverture **8 → 0** ; à chaud, 12 phases :
1 image perdue en cours d'animation (avant : 1 sur 8). 1440 × 4, première ouverture : 19,4 + 10,9 → **4,5 + 2,4 ms**,
**8 → 1** image ; ouvertures suivantes et fermetures : 0. 393 × 1 : inchangé (0 image perdue sur 8 phases sauf une à la fin
d'une fermeture, style forcé ≤ 1,5 ms). Reste, en desktop seulement : la première image arrive un cycle plus tard à
l'ouverture (8 à 15 ms après le clic, contre 0 à 7 en mobile) — une image d'`opacity: 0`, sans saut visible ; non traité.
Traces : `storage/app/jank/` (hôte, non versionné).

**Tests.** Client : `search-panel` (+1 : la racine porte `data-open` ouverte, le perd fermée, au pointeur comme au
clavier et à Échap, dans les deux dispositions — rouge sans la correction), feuille (le voile suit `data-open`, plus
aucun `:has()` sur la racine dans le bloc `48em`). PHP : `ContractParityTest` (+1 : la feuille dessine le voile depuis
l'attribut que le client pose). `composer check` vert (Unit 359, client 671/671), suite `Modules` **OK (689 tests)**.

**Cinq passes.** *Lisibilité* : deux lignes dans `#open`/`#close`, une constante nommée comme ses voisines. *Commentaires* :
une ligne de doc sur la constante ; aucun dans la CSS. *Performance* : un attribut par ouverture et par fermeture ; le
`:has()` mobile du verrou (0,87 ms, sous `48em` seulement) laissé tel quel. *Sécurité* : rien de dynamique. *Contexte/i18n* :
aucune chaîne.

### R-196 · 🟡 · ouvert (en attente de commit, et de Louis pour la listbox) · ouvert le 2026-09-29 — étape 6, accessibilité de la recherche

Rattaché à `R-180`, étape 6 de [chantier-recherche.md](chantier-recherche.md) ; corrections validées par Louis sur l'audit
du 2026-09-29. Rien n'est commité, réindexé ni écrit en base ou dans le moteur ; `config/meilifacets.php` de l'hôte et le
thème non touchés.

**Conformité.** *Change* : `StatusView` (+ `DebouncedAnnouncer`, nouveau), `SiteSearch`, `SectionView`, `CardView`
(`withDecorativeImages()`), `SearchPanel`, `Search\Section`, `site-search.css`. *Ferme* : quatre des six points de
l'audit, la fermeture « d'un coup, mais partiellement », le `limit="2.5"` tronqué. *Contredit* : rien ; complète
« Annonce de la recherche ». *La plateforme offre* : `tabindex`, `Animation.finished`, `FILTER_VALIDATE_INT`. Aucun
crochet ajouté ni déplacé, `Contract::VERSION` inchangé.

**Listbox unique : arrêtée, pas codée.** Une listbox n'admet que des options et des groupes ; titre et « voir tous »
d'une section autre que la première y tomberaient forcément. Prototype en direct dans Chrome (DOM seul) avec
`aria-owns` : arbre conforme, mais titres et liens lus après tous les résultats. Quatre pistes dans `decisions.md`, à
trancher par Louis.

**Livré.**
- Annonce : écrite après 1 s sans frappe ni réponse, jamais deux fois la même phrase. Journal réel (MutationObserver,
  « crème » à 500 ms par lettre) : cinq frappes, **une** écriture à 3 140 ms ; « creme vi », huit frappes, **une**
  écriture ; trois pauses de 2 s sur des termes à la même réponse : **zéro** écriture.
- Liens des résultats `tabindex="-1"` ; Tab : champ, « Tous les produits », « Tous les articles ». Vignette de recherche
  `alt=""` : l'option se lit « Sérum visage 42,00 € » (avant : le titre deux fois).
- « Voir tous » : `inline-flex`, `min-height: var(--meili-control-min)`. À 393 (pointeur grossier) : 44 px (avant
  106 × 20), loupe 44, champ 48, cartes 72 et 108 ; texte inchangé (13 px).
- `forced-colors` (Playwright, `forcedColors: active`) : ligne active `outline` 2 px, champ au focus `outline` 2 px
  (déjà là) ; **barre lente** peinte en `Canvas` (invisible) → ajoutée à la règle `CanvasText` + `forced-color-adjust:
  none` ; voile réduit à `Canvas` 25 % (décoratif, filet bas du panneau en `CanvasText`).
- Fermeture : la cause est `PanelRoom.release()` dans la même image que `hidden`. À 393 le panneau tombait de 780 à
  471 px dès l'image 0 et laissait voir la page pendant son fondu. La mesure est maintenant relâchée à la fin des
  animations du panneau (`getAnimations()` → `finished`), sauf s'il s'est rouvert. Au clavier, elle l'est aussitôt.
  Images à 0/40/80/120/150 ms avant/après : `storage/app/etape6/close-393-*.png` (hôte, non versionné). L'hypothèse
  `!important` sur `[hidden]` est **écartée par la mesure** : une transition prime sur une déclaration `!important`
  d'auteur, et `display` reste `flex` jusqu'à 150 ms à 1440 comme à 393. Panneau et voile ont la même opacité à chaque
  image (1 / 0,20 / 0,03 / 0 / 0), avec `translateY` −4 px.
- `limit` : `int|float|string`, validé par `FILTER_VALIDATE_INT` (min 1). `0`, `2.5`, `:limit="2.5"` et `abc` lèvent
  « asks for 2.5 results: it shows a whole number of them, at least 1. » ; `limit="2"` et `:limit="2"` passent. Avant,
  `2.5` était tronqué à 2 avec une simple dépréciation.

**Tests.** Client : `debounced-announcer` (4), `site-search` (+1 : pause), `status-view` (délai), `section-view` (+1 :
Tab et vignette), `search-panel` (+1 : mesure gardée jusqu'à la fin de la sortie, gardée si réouverture — rouge avec
l'ancien code), feuille (+1 : « voir tous »). PHP : `SearchCompositionTest` (+4 cas refusés, +1 cas accepté).
`composer check` vert (Unit 358, client 669/669, 99,18 % lignes, 93,85 % branches, `build:check`), suite `Modules`
**OK (688 tests)** ; seules : `SearchCompositionTest` 22, `ComponentFoldersTest` 17, `SearchComponentTest` 7.

**Recette** (Chrome, 1440). Ouverture (focus au champ), « ser », ↓ produit → ↓ article → ↑ produit, Échap (fermé,
focus à la loupe, terme gardé), clic sur le voile (fermé, rien dessous), « zzzzq » → message vide, panne simulée →
« Recherche indisponible » puis retour, ↓ + Entrée → fiche produit ; console : seule l'erreur provoquée. `/boutique` :
4 facettes, 16 → 1 (`?categorie=cheveux`), console vide.

**Cinq passes.** *Lisibilité* : méthodes ≤ 10 lignes, aucun booléen en paramètre ; `#announce` (une ligne qui
déléguait) retiré. *Commentaires* : ajoutés : `limitOf` (troncature silencieuse de PHP), `#releaseRoomOnceGone` ;
retirés à la relecture : deux justifications. Aucun dans la CSS. *Performance* : aucune lecture de layout par frappe ;
un `getAnimations()` par fermeture ; un minuteur par région. *Sécurité* : rien de dynamique ajouté. *Contexte/i18n* :
aucune chaîne ; message de `limit` en anglais (diagnostic).

**Courbe de sortie (décision de Louis, même jour).** Jeton `--meili-ease-exit` (`ease`), sortie du panneau et du voile
seulement, 150 ms gardées ; l'entrée reste sur `--meili-ease`. Opacité panneau = voile à 0/40/80/120/150 ms :
`--meili-ease` 1 / 0,20 / 0,03 / 0 / 0 (translation −3,2 px dès 40 ms) ; `ease` 1 / 0,56 / 0,17 / 0,02 / 0 ;
`cubic-bezier(0.4, 0, 0.2, 1)` 1 / 0,72 / 0,19 / 0,02 / 0 (retient puis chute de 53 points). `ease` retenu : effacement
régulier dès la première image. Au clavier : aucune animation. `CalmAnnouncement` renommé `DebouncedAnnouncer`
(`debounced-announcer.ts`, `DEBOUNCE_DELAY`, `announce()`), sans alias.

**À revoir.** Mouvement réduit vérifié par les tests seulement.

### R-195 · 🟡 · ouvert (en attente de commit) · ouvert le 2026-09-29 — étape 8, habillage du thème de test, verrou mobile et disposition libre

Rattaché à `R-180`, étape 8 de [chantier-recherche.md](chantier-recherche.md) (D-9, S-16). Pas de maquette : habillage
sobre, par les crochets et les jetons, sur le modèle de `listing.css`. Rien n'est commité, réindexé ni écrit en base ou dans
le moteur ; `config/meilifacets.php` de l'hôte non touché.

**Neutralité du module — constat.** `grep -rniE '<nom du projet>|<couleurs du thème de test>|<polices du thème de test>'`
sur `resources/`, `app/`, `dist/` et `tests/` : aucune occurrence ; le nom du projet de test n'est cité que dans `docs/`.
`StylesheetNeutralityTest` vert (aucune
couleur par sa valeur, aucune `font-family`, jamais `display: contents`). Le module n'était pas en cause pour les
graisses : il pose 400/500/600 sans police ; c'est la police héritée du thème (une police sur `body`, une autre sur
`a` et `button`, sans gras réel — synthétisé) qui faisait paraître tout gras.

**En-tête qui change de largeur sur mobile — deux causes, mesurées.**
- *Cause réelle, thème* : sur mobile (émulation `393×852`, barres de défilement superposées), la page débordait
  horizontalement — `scrollWidth` 398 puis 418 px — et l'en-tête `fixed` suit le viewport de mise en page : 398 → 393
  (verrou posé, relayout) → 410,5 px à la fermeture. Élément fautif : l'anneau animé du logo du thème, SVG en rotation
  continue dont la boîte englobante dépasse du bord droit d'une quantité qui dépend de l'angle au moment du relayout.
  Le correctif existait déjà dans les sources du thème (`5a86d85`, `overflow: clip` sur le badge) mais le build servi
  datait d'avant la fusion de `develop` qui l'a apporté (`style-CLofTV63.css` sans `clip`, recopié par WP Rocket). Après
  build : 393 px constant sur trois cycles, `scrollWidth` 393.
- *Cause secondaire, module* : avec des barres classiques (émulation bureau sous `48em`), le verrou
  `:root:has(… [aria-expanded="true"]) { overflow: hidden }` retirait la barre : 378 → 393 → 378 px. Corrigé ici par
  `scrollbar-gutter: stable` dans la même règle : 378 px constant. Sans effet avec des barres superposées (téléphones).
  Limite connue : une page **trop courte pour défiler**, sous `48em`, avec des barres classiques, gagnerait la gouttière
  à l'ouverture (rétrécit de sa largeur) — cas jugé marginal, aucune mesure CSS ne sait si la racine défile.
- À 1440 : 1425 px constant (pas de verrou au-delà de `48em`).

**Disposition libre et surcharge (demande de Louis).** Le client ne suppose ni ordre ni imbrication (tout passe par
`Contract.one/all(hook, scope)`) — **aucun défaut trouvé, aucun code du module changé pour cela**. Prouvé par :
`ComponentFoldersTest` (le cas `search/card` devient un fournisseur de données sur les huit vues, gabarits temporaires
créés et supprimés par le test), `SearchCompositionTest::it_renders_the_bricks_where_a_template_places_them` (message
vide d'abord, articles avant produits, limites 2 et 3, champ après les sections, loupe après le panneau), côté client
`rearrangedSearchMarkup()` : ordre et limites des requêtes, carte au prix au-dessus du titre et image en dernier remplie
sans être réordonnée, ↓ sur la première option affichée, message vide placé en premier ; panneau ouvert, fermé et focus
rendu à une loupe posée après lui. Doc : `configuration.md`, « Disposition libre » (exemple et crochets à garder par vue).

**Taille du champ (Louis : « trop gros nativement »).** Le module posait `max(1rem, var(--meili-ui))` partout, soit
16 px même à la souris. Le plancher ne vaut plus que sous `(pointer: coarse)`, où iOS zoome ; au pointeur fin le champ
suit `--meili-ui` (14 px). Décision révisée dans `decisions.md` (étape 5), avec son coût.

**Habillage (thème, `components/site-search.css`).** Voir [chantier-recherche.md](chantier-recherche.md), étape 8.
Cartes d'article décorées (date, rubrique, temps de lecture) : **attendent la maquette**, non faites. Code mort S-16
retiré du thème.

**Vérifié.** `composer check` vert (Unit 358, client 660/660, `build:check`), suite `Modules` 684. Recette Chrome
1440 et 393 : ouverture, frappe, Échap (focus rendu à la loupe, terme gardé), voile (ferme sans rien activer), ↓ puis
Entrée (fiche produit), état vide, aucune erreur console ; `/boutique` : filtre appliqué (`submit`, 16 → 1,
`?categorie=cheveux`) avec la recherche sur la même page.

### R-194 · 🟡 · ouvert (en attente de commit) · ouvert le 2026-09-29 — composants Blade rangés par racine

Rattaché à `R-180`. Décision de Louis du 2026-09-29, qui révise « Dossiers et crochets du chantier » (`decisions.md`,
C-8 révisée du 2026-09-24 : Blade à plat dans `components/`). Pur rangement : aucun comportement, aucun rendu ne change.
Rien n'est commité, réindexé ni écrit en base ou dans le moteur ; `config/meilifacets.php` de l'hôte non touché.

**Conformité.** *Change* : vues et classes des briques, déplacées par `git mv` dans `components/search/`,
`components/listing/`, `Components/Search/`, `Components/Listing/` (dossiers autorisés par Louis) ; balises en notation à
point ; `SearchableTypeFactory::CARD` = `meilifacets::search.card` ; `Facets::componentFor()` ; messages de
`PagePlacement` ; thème de test (`woocommerce/archive-product.blade.php`). *Ferme* : rien d'autre. *Contredit* : la
ligne « Dossiers et crochets du chantier », révisée avec son coût. *La plateforme offre* : `Blade::componentNamespace()`
(posé par nwidart) et la résolution de `ComponentTagCompiler::componentClass()` — classe `Namespace\Search\Toggle`
d'abord (`findClassByComponent()`, `formatClassName()` découpe sur `.`), vue anonyme
`meilifacets::components.listing.toggle` ensuite ; aucun alias à déclarer.

**Noms.** Racines inchangées (`search`, `listing`, classes `Search`, `Listing`) ; les bases `ContractComponent`,
`ListingComponent`, `SearchComponent` restent à la racine de `Components/` : partagées ou étendues par une racine, et un
dossier ne contient ainsi que des balises. `SearchEmpty` devient `Search\EmptyState` (`empty` est réservé, `class Empty`
ne compile pas), balise et vue `search.empty-state` pour garder le miroir : une balise `search.empty` échoue bruyamment au
lieu de rendre la vue sans sa classe. `Toggle`, `Card`, `Unavailable` existent sous les deux racines, séparés par leur
namespace. Crochets `data-meili` et `Contract::VERSION` inchangés.

**Vérifié.** `ComponentFoldersTest` : résolution (classe de dossier, classe au nom réservé, vue anonyme), anciens noms et
`search.empty` refusés, rendu par les nouveaux noms, surcharge du thème à `components/search/card.blade.php` prise (le
crochet réel du provider, seul, sur un thème temporaire). HTML de `/`, `/boutique`, `/categorie-produit/visage`,
`/journal`, `/?s=creme&post_type=product` identique avant/après, jetons Gravity Forms et `?ver=` de WP Rocket neutralisés.
Recette navigateur : recherche de l'en-tête à 1440 et 393, filtres, tri et tiroir de `/boutique`, console vide.

### R-193 · 🟡 · ouvert (en attente de commit) · ouvert le 2026-09-29 — les résultats clignotaient en bloc à chaque lettre

Rattaché à `R-180`, étape 7 de [chantier-recherche.md](chantier-recherche.md), sur l'état non commité de `R-191`/`R-192`.
Constat validé par Louis : `SectionView::show()` détruisait et reconstruisait toute la liste (`replaceChildren`) à chaque
réponse, y compris les résultats inchangés. Rien n'est commité, réindexé ni écrit en base ou dans le moteur ;
`config/meilifacets.php` de l'hôte non touché.

**Conformité.** *Change* : `SectionView`, `ComboboxKeys`, `SiteSearch`, `SearchPanel`, `Entrance`, `CardView`,
`ResultsView`, `site-search.css` ; nouveaux `ResultsMotion` et `Departure` (`ts/site-search/`). *Ferme* : trois points
« à revoir » de `R-192` (voile en retard, arête de la ligne active, mise en page des premiers résultats) et tranche le
quatrième (flèche). *Contredit* : l'étape 7 disait « rien au remplacement des résultats » et « fondu des premières
sections par `@starting-style` » — remplacé par ce point, à la demande de Louis. *La plateforme offre* : WAAPI
(`composite: 'add'`, `getAnimations()`), aucune dépendance ; aucun crochet ajouté (la barre vit sur `search-field`),
`Contract::VERSION` inchangé.

**Livré.** Valeurs dans `decisions.md` (« Étape 7 : résultats pendant la frappe »).
- Réconciliation par `ID` : un résultat retrouvé garde son nœud, seuls titre et résumé sont réécrits, et seulement s'ils
  changent (le compte aussi) ; nouveaux nœuds depuis le gabarit ; ordre remis par `insertBefore` des seuls nœuds hors
  place. Une section masquée garde ses cartes. `CardView::stamp()` sert désormais la grille du listing **et** les
  sections (doublon de clonage levé), `showWords()` les mots seuls.
- `ComboboxKeys` : l'option atteinte reste atteinte si elle est encore proposée (au nouveau rang), sinon
  `aria-activedescendant` et `data-active` tombent ; identifiants émis une fois par nœud, plus par rang (un nœud gardé et
  un nœud neuf pouvaient porter le même).
- `ResultsMotion` : une lecture groupée avant l'écriture, une après, jamais entre deux écritures ; FLIP additif
  (`composite: 'add'`) relatif à la section, ce qui rend l'interruption continue sans rien annuler ; entrée, sortie hors
  flux (`Departure` : la carte elle-même, ou une copie sans `id` pour une section ou un message), fondu enchaîné vers et
  depuis l'état vide, compte en fondu ; rien si le panneau entre encore, rien au clavier ; fondus seuls en mouvement
  réduit. Les entrées de section passent de `@starting-style` à WAAPI (`SearchPanel::#showWithItsContent()` retiré).
- Barre de recherche lente : `::after` du champ sous `aria-busy`, après `--meili-duration-busy-delay`, transform seul.
- Reprises : voile sur `--meili-ease` ; barre de la ligne active retirée (teinte seule) ; vignette à taille fixe (une
  image lente ou absente faisait grandir la rangée **après** la mesure, les cartes sautaient en fin de glissement).

**Vérifié image par image** (Chrome DevTools, 1440, animations mises en pause à leur création puis positionnées ;
`typing-x4.gif` et `typing-*.png`). « s » → « se » : sections en fondu, cartes immobiles. « se » → « ser » : trois
cartes produits et une carte article s'effacent à leur place (opacité 1 → 0,2 à 40 ms), la carte restante ne bouge pas
d'un pixel, les comptes changent en fondu + 2 px. « ser » → « seru » : aucune animation. « set » → « set h » : trois
cartes glissent de 76 px en 150 ms, « Valentine's Day set » entre en fondu + 4 px. Vide ↔ résultats : 150 ms, sans flou
(message centré et cartes à gauche ne partagent aucun pixel). Premier essai : sortie de 80 ms sur la courbe forte déjà à
3 % à 40 ms (un saut) → fondus sur `ease` ; copie de section qui étirait le défilement du panneau (barre de défilement de
20 px le temps du fondu) → fantômes bornés au bas visible du panneau. 393 : largeur du panneau constante, aucun fantôme
restant.

**Performance** (traces, « serum v » puis « creme vi » à 180 ms par lettre). Hors premiers résultats, par réponse :
tâche 0,95–2,3 ms + image suivante 0,9–2 ms (style ≤ 0,75, layout ≤ 0,36, peinture comprise) sans bridage ; ×4 :
tâche 3,9–7,3 ms dont style ~1,5 et layout ~1 (la lecture après écriture, que l'image suivante n'a plus à refaire :
layout 0,05–0,9 ms), + image suivante 2,8–7 ms peinture comprise. Premiers résultats à ×4 : layout 1,4–2 ms (seuil
16 ms loin : rien à réduire). Aucune long task imputable (la seule, 64 ms, est le démarrage du profileur dans un rAF
du thème). Images : 0 intervalle > 1,5 × 8,3 ms à 120 Hz, avec et sans ×4 (pire 10,4 ms). `will-change` non posé.
Tailles : client 9 333 → 15 775 o (3 765 → 5 979 gzip) ; chargeur 7 526 → 7 449 (2 833 → 2 819) ; listing 51 547 →
51 793 (15 956 → 16 027) ; `site-search.css` 13 099 → 14 368 (2 864 → 3 078). Un import de `Contract` en valeur avait
fait entrer les règles du listing dans le client (18 617 o) : retiré.

**Recette.** Ouverture (focus dans le champ), « ser » (Produits 1 · Articles 1, annonce une fois), Échap (fermé, terme
gardé, focus à la loupe), clic sur le voile (fermé), ↓ + Entrée → `/produit/serum-visage` (aucune animation à
la touche, ligne sans arête), « zzzzq » → message vide, moteur bloqué → « Recherche indisponible », retour → sections ;
« Slow 3G » : barre invisible avant 150 ms, balayage ensuite, retirée à la réponse. `/boutique` : « Catégorie » au
pointeur (transitions opacity + transform), filtre appliqué (1 carte, `?categorie=cheveux`), Échap et focus rendu,
retour à 16 cartes ; tiroir 393 ouvert, `data-closing`, fermé, focus rendu. Console : la seule erreur est celle de la
panne provoquée. Les touches CDP n'atteignent pas une page en contexte isolé : ↓ et Entrée ont été émis en `keydown`
sur le champ.

**Tests.** Client +21 : réconciliation (même nœud, ordre, `insertBefore` compté, carte sans identité, section masquée,
aucune écriture inutile), clavier (option gardée au nouveau rang, lâchée si perdue, identifiants uniques), mouvement
(`results-motion.test.ts` : entrée, glissement additif, interruption continue sans annulation, fantôme placé et retiré,
copie de section sans `id`, fondu enchaîné 150 ms, panneau qui entre, mouvement réduit, clavier, option perdue, fantôme
hors de vue), feuille (barre, `data-leaving`, ligne active, compte). `composer check` vert (Unit 358, client 655/655,
99,03 % lignes, 93,75 % branches, `build:check`), suite `Modules` **OK (666 tests, 1947 assertions)**.

**Cinq passes.** *Lisibilité* : méthodes ≤ 12 lignes, trois paramètres au plus (règle ESLint), aucun booléen en
paramètre ; `#recount` ne parcourt plus que les sections. *Commentaires* : docblocs de contournement (lecture groupée,
FLIP additif, fantôme borné, identifiants par nœud, happy-dom dans les tests) ; aucun dans la CSS. *Performance* : deux
lectures par réponse, écritures seulement si le texte change, aucune écoute ajoutée. *Sécurité* : rien de nouveau n'est
interprété (les copies clonent un DOM déjà échappé). *Contexte/i18n* : aucune chaîne ; la barre balaie de gauche à droite
même en RTL.

**À revoir.** Coût à ×4 au-dessus de 5 ms (tâche ~4,5 ms, le reste est la mise en page que la frame aurait faite) ;
`scaleX` de la barre orienté à gauche en RTL ; mouvement réduit vérifié par les tests seulement (Chrome MCP n'émule pas
la préférence) ; vignettes absentes en local (fichiers d'`uploads` non synchronisés).

**Commit proposé** (module) : `feat(search): keep results in place while typing and animate their changes`.

### R-192 · 🟡 · ouvert (en attente de commit) · ouvert le 2026-09-28 — étape 7, mouvement et reprise du style du panneau

Rattaché à `R-180`, étape 7 de [chantier-recherche.md](chantier-recherche.md), sur l'état non commité de `R-191`
(étape 5 et première passe de style). Retour de Louis : apparition brute, ombre laide. Rien n'est commité, réindexé ni
écrit en base ou dans le moteur ; `config/meilifacets.php` de l'hôte non touché.

**Conformité.** *Change* : `site-search.css`, `SearchPanel`, `PanelRoom`. *Ferme* : étape 7 du plan, deux points « à
trancher » de `R-191` (voile éclaircissant en sombre, 3rem entre sections sur mobile). *Contredit* : rien ; reprend
ANIM-4 (clavier instantané), ANIM-10 (atténuation différée) et la transition `[hidden]` + `@starting-style` des
panneaux flottants. *La plateforme offre* : `@starting-style`, `transition-behavior: allow-discrete` ; aucun JS
d'animation.

**Livré.** Valeurs dans `decisions.md` (« Étape 7 : mouvement de la recherche »).
- Style : ombre retirée (filet `--meili-rule` + voile) ; halo 12 → 8 % ; champ → première section 1.5rem
  (`--meili-search-lead`, marge négative du champ seulement quand une section est visible) ; entre sections 2rem,
  3rem à partir de `48em` ; ligne compensée (`margin-inline: -0.75rem`) : vignette sur le bord du filet et du titre
  (136,5 px pour les trois à 1440, 16 px à 393), `contain: layout paint` → `layout` sur la liste (la teinte déborde) ;
  titre 400, `mark` 600 ; voile `black` à 20 %.
- Mouvement : panneau 200/150 ms, voile 200/150 ms `ease`, premières sections 120 ms, atténuation différée, loupe
  `scale(0.97)`, flèche +2 px, ligne active instantanée, mouvement réduit en fondus.
- Client : `SearchPanel` pose `data-instant` sur la racine au clavier, Échap et Tab ; montre le panneau avec ses
  sections sous `data-instant` (une seule entrée) ; `PanelRoom` mesure depuis l'ancre.
- Demande du coordinateur, même feuille : loupe alignée dans l'en-tête (`display: flex` + `inline-size:
  fit-content`). 1440 avant : racine 26,5 px, bouton 20 px, centres 40 / 36,75 ; après : racine = bouton = 20 px,
  centre 40 (« À propos » 40, liens du menu 41 — ils diffèrent eux-mêmes de 1 px, les deux cibles ne peuvent être
  tenues à ±0,5 ensemble), identique avec `.is-scrolled`. 393 (pointeur grossier) : racine = bouton = 44 px, centre
  36 = burger, avant comme après ; fenêtre étroite au pointeur fin : 20 px, centre 36 = burger.

**Vérifié image par image** (Chrome DevTools, 1440, caches vidés, `module:publish` sans orpheline, aucun
`public/*.hot`). Échantillons `requestAnimationFrame` (~8,3 ms, écran 120 Hz) : ouverture au pointeur `opacity`
0 → 1 et `translateY` −8 → 0 en 200 ms, monotones, 50 % de l'opacité avant 30 ms (ease-out marqué), voile 0 → 1 en
200 ms, aucun saut ; fermeture 1 → 0 et 0 → −4 px en ~150 ms, `display: none` à 154 ms, voile retiré au même moment.
Tab jusqu'à la loupe + Entrée : première image déjà à 1 / 0 px ; Échap : première image déjà `display: none`, focus sur
la loupe. Réouverture avec des résultats : seules les transitions du panneau et du voile démarrent. Premières
réponses : `opacity` 120 ms sur les sections ; frappe suivante : aucune animation. Réseau « Slow 3G » : liste à 0.55
après ~150 ms, retour en 120 ms. Mouvement réduit (règles du bloc `reduce` injectées, Chrome MCP n'émule pas la
préférence) : `transform` à `none` sur toute l'entrée et la sortie, fondus gardés, loupe sans transition.
Chronogramme scrubbé `anim-open-x4.gif` (0, 20, 40, 70, 110, 199 ms).

**Performance** (traces non bridées). Ouverture : tâche la plus longue 1,2 ms, recalcul de style 0,75 ms (17
éléments), une image sautée à 120 Hz (celle du clic), jamais deux de suite. Premières réponses : tâche 10,7 ms
(style 2,8 ms / 43 éléments, layout 6,1 ms), une image sautée. Frappes suivantes : tâche max 5,4 ms, style max
0,43 ms, layout 0,3 ms. Aucune long task. Les images sautées à 500 ms d'intervalle sont le clignotement du curseur :
présentes à l'identique dans une trace au repos (champ focalisé, rien tapé). Aucun intervalle > 16,7 ms imputable au
module. `will-change` non posé (rien ne le justifie). Feuille 10 046 → 13 099 o (2 352 → 2 864 o gzip).

**Recette.** Ouverture, « ser » (Produits 1 · Articles 1), Échap, clic sur le voile (rien d'activé dessous : 0 clic sur
« Slide suivante »), ↓ + Entrée → `/produit/serum-visage` (393) ; sombre forcé : voile
`color(srgb 0 0 0 / 0.2)` ; `/boutique` : filtre « Catégorie » ouvert au pointeur (180 ms, `opacity` + `transform`),
Échap instantané et focus rendu, tiroir 393 animé puis fermé, 16 cartes ; aucune erreur ni avertissement console.
`.htaccess` de `cache/wp-rocket` : absent, non revenu (aucun n'est versionné).

**Tests.** Client : `search-panel` +5 (clavier instantané, pointeur animé, Échap/Tab instantanés, sections entrées
avec le panneau, place depuis l'ancre) ; `site-search-stylesheet` +4 (propriétés animées, aucune ombre sous le
panneau, coupure `data-instant`, mouvement réduit, vignette compensée), voile réécrit. `composer check` vert (Unit 358,
client 634/634, 98,97 % lignes, 93,71 % branches, `build:check`), suite `Modules` **OK (666 tests, 1947 assertions)**.

**Cinq passes.** *Lisibilité* : `#instantly()` et `#showWithItsContent()` de 4 lignes, `#top()` de 7 ; aucun booléen
en paramètre. *Commentaires* : trois docblocs de contournement (vidage de style, section sans style de départ, boîte
décalée par la transformation) ; un justificatif retiré ; aucun dans la CSS. *Performance* : seuls `transform` et
`opacity` animés (plus la couleur au survol), aucune mesure ajoutée (`PanelRoom` lisait déjà la géométrie à
l'ouverture), `:has()` limité aux enfants du panneau. *Sécurité* : rien de dynamique. *Contexte/i18n* : aucune chaîne ;
la flèche avance de 2 px à droite même en RTL (le glyphe `→` l'était déjà).

**À revoir à tête reposée.** Courbe du voile (`ease`) plus lente que celle du panneau au départ ; flèche de « voir
tous » qui n'est plus soulignée ; fine arête visible autour de la ligne désignée (barre de 2 px sur un coin arrondi) ;
layout de 6 ms à la première réponse, à mesurer sur un téléphone moyen.

**Commit proposé** (module) : `feat(search): animate the search panel and refine its style`.

### R-191 · 🟡 · ouvert (en attente de commit) · ouvert le 2026-09-28 — étape 5, panneau et sections

Rattaché à `R-180`, étape 5 de [chantier-recherche.md](chantier-recherche.md) (S-1, S-5, S-9, S-10, S-11, S-12, S-18,
D-7, D-9). Rien n'est commité, réindexé ni écrit en base ou dans le moteur ; `config/meilifacets.php` de l'hôte non
touché (md5 identique avant et après).

**Passe de conformité.** *Change* : briques Blade de la recherche, composition par défaut, fermeture légère partagée,
feuille `site-search.css`, loupe du thème remplacée. *Ferme* : S-12 (`shared/light-dismiss.ts`), S-10, S-5, cases 5 du
plan. *Contredit* : aucune décision validée ; s'écarte de la lettre du plan § 2 sur deux points (listbox par section,
messages sans slot), écrits dans `decisions.md`. *La plateforme offre* : `get_post_type_archive_link()` et les libellés
des types (déjà lus, `R-187`), le motif APG disclosure ; rien de natif pour un panneau de recherche.

**Livré.**
- Composants `SearchComponent` (base : racine par `name`, sinon `sole()` ; `ElementId` de la racine), `SearchToggle`,
  `SearchPanel`, `SearchInput`, `SearchSection` (`type`, `limit`, `name`), `SearchEmpty`, `SearchUnavailable`,
  `SearchCard` ; vues à plat dans `components/`. `<x-meilifacets::search>` sans contenu compose tout
  (`sectionTypes()`), son slot `icon` passe à la loupe ; avec contenu, rien n'est ajouté.
- `SearchRoot::type()` (lève `SearchTypeRefused` en nommant les types de la racine), `SearchRegistry::placeSection()`
  (une section par type et par racine), `limit` ≥ 1.
- Crochet `search-see-all` (`Hook::SearchSeeAll`, autorisé au § 8, additif, `Contract::VERSION` = 1) ;
  `Stylesheet::SiteSearch` = `meilifacets-site-search` ; `images/search.svg` ; `ElementId::searchPanel()`,
  `searchInput()`, `searchHeading()`, `searchListbox()` ; trois chaînes (`Search`, `Nothing matches your search`,
  `Search unavailable`) et leur français.
- Client : `shared/light-dismiss.ts` (`LightDismiss`, extrait de `DisclosureGroup` : Échap consommée et focus rendu,
  focus sorti hors appui, clic hors du chemin), `site-search/panel-room.ts` (`PanelRoom`, `--meili-search-room`),
  `SearchPanel` qui s'en sert (fermeture, remesure au redimensionnement) ; `ComboboxKeys.control()` écrit
  `aria-controls` du champ ; `SectionView.listbox`.
- `site-search.css` : neutre, mobile first, par crochets, sans commentaire, jetons redéclarés sur
  `[data-meili="search"]` ; verrou de page sous `(scripting: enabled) and (width < 48em)`.
- Thème : `parts/header/row.blade.php` pose `<x-meilifacets::search>` avec l'icône du thème dans `icon` ; la règle
  morte `.site-header__search-toggle` retirée de `header.css`. Aucun CSS ajouté au thème : `.site-header`
  (déjà `position: relative`) sert d'ancre.

**Corrigé dans le code existant.** `AcceptedSearchTypes::get()` retiré (doublon de la liste que la racine tient déjà ;
tests de refus passés sur `SearchRoot::type()`) ; `DisclosureGroup` réduit de ~40 lignes (fermeture légère extraite) ;
`'aria-controls'` écrit en dur dans `disclosure-group.ts` et `drawer.ts` → `CONTROLS` (`shared/attributes.ts`) ;
paramètre `ElementId::$listing` → `$root` (il sert aussi les racines de recherche) ; fixtures TS de la recherche alignées
sur les vues réelles ; `load()` des tests accepte une feuille ; `ContractParityTest` : seuil `48em` vérifié sur les deux
feuilles, crochets de `site-search.css` comptés, jumeaux `data-type`/`data-limit` ; `StylesheetNeutralityTest` sur les
deux feuilles (+ aucun `display: contents`).

**Vérifié dans Playwright** (Chromium, accueil, clé publique, caches vidés, `module:publish` sans orpheline, aucun
`public/*.hot`). **1440 px** : loupe → panneau ouvert sous la barre (y = 80 = bas de l'en-tête, pleine largeur),
focus dans le champ, `aria-expanded="true"`, `--meili-search-room` 820px ; « ser » → Produits 1 résultat, Articles
1 résultat (le moteur n'en trouve pas plus : ≤ 4 tenu), liens `/boutique` et `/journal`, annonce « Produits : 1 résultat
et Articles : 1 résultat » ; « zzzz » → « Aucun élément ne correspond à votre recherche » ; Échap → fermé, focus sur la
loupe, terme gardé ; clic extérieur → fermé ; Entrée sans option → URL inchangée ; ↓ + Entrée →
`/produit/serum-visage` ; page non verrouillée. **393 px** : panneau y = 72, 393 × 780 (toute la hauteur
restante), `overflow-y: auto`, page verrouillée ouverte, déverrouillée après Échap ; mêmes résultats, mêmes messages,
Échap, clic extérieur, ↓ + Entrée. **Moteur bloqué** (`page.route` → `abort`) : « Recherche indisponible » affiché et
annoncé, sections masquées, URL inchangée (erreurs console = la panne provoquée). **`/boutique`** : filtre Catégorie
ouvert, Échap le ferme et rend le focus, clic extérieur et Maj+Tab le ferment, case « cheveux » → 62 → 1 article,
`?categorie=cheveux` ; recherche « creme » depuis la boutique → « Produits : 2 résultats et Articles : 1 résultat » ;
tiroir à 393 px ouvert puis fermé par Échap. Aucune erreur console hors la panne provoquée. Une lecture isolée a
montré la loupe absente de `/boutique?categorie=cheveux` après un enchaînement de gestes ; rejouée trois fois à
l'identique, jamais reproduite (loupe présente au rendu serveur comme après chaque geste).

**Tests.** Client : `light-dismiss` (5), `search-panel` (+4 : Échap, clic extérieur, Tab, place mesurée),
`site-search` (+1 : `aria-controls`), `site-search-stylesheet` (6). PHP : Feature `SearchCompositionTest` (15),
`ClientStylesheetTest` (+1) ; Unit `SearchRegistryTest` (+4), `ElementIdTest` (+1, collisions étendues),
`StylesheetNeutralityTest` (2 → 6), `ContractParityTest` (+3). **Résultats** : client 622/622 (les 606 d'avant verts,
`disclosure-group` et `drawer` sans retouche ; 98,96 % lignes, 93,68 % branches), Unit 358, `composer check` vert,
suite `Modules` **OK (664 tests, 1942 assertions)** ; lancées seules : `SearchCompositionTest` 15/15,
`SearchComponentTest` 7/7, `ClientStylesheetTest` 6/6, `SearchableTypesTest` 17/17.

**Les cinq passes.**
- *Lisibilité* : méthodes de 1 à 14 lignes ; aucun booléen en paramètre ; `LightDismiss` ne connaît que
  l'interface `Dismissible` ; un nom par concept (`search-results` = la liste, `listbox` = son rôle, lu par
  `ComboboxKeys.control()`) ; vues sans calcul (`sectionTypes()`, identifiants préparés par la classe).
- *Commentaires* : ajoutés et gardés : Échap non consommée qui vide un champ de recherche (anomalie navigateur),
  aliases figés de `<x-dynamic-component>` (test, contournement) ; retiré : un docbloc de `SearchCard` (justification) ;
  aucun dans la CSS.
- *Performance* : aucune requête ajoutée ; une recherche dans un tableau par section ; `AcceptedSearchTypes::all()`
  une fois par racine ; côté client, un écouteur `resize` par racine qui ne mesure que panneau ouvert ; chargeur
  +545 o gzip.
- *Sécurité* : tout passe par `{{ }}` ; archive et libellés viennent de WordPress ; rien de neuf ne part au
  navigateur ; le panneau n'écrit rien dans l'URL.
- *Contexte et i18n* : trois chaînes traduisibles sans domaine, titres et « voir tous » natifs ; sans WooCommerce, la
  composition par défaut ne pose pas de section produits (type non accepté).

**Hors périmètre, noté.**
- `resources/assets/css/meilifacets.css:1324,1355,1458,1509` : quatre commentaires dans la feuille du listing
  (contraire à la règle « aucun commentaire en CSS ») ; correctif : reporter les trois raisons dans `decisions.md`,
  supprimer les quatre, puis étendre le test `display: contents` de `StylesheetNeutralityTest` à une garde « aucun
  commentaire » sur les deux feuilles.
- Thème : `productSearch` (`js/frontend/product-search.js`, importé par `app.js:11`) n'est plus référencé par aucune
  vue — il ne l'était déjà plus avant cette étape ; la route `/api/products/search` (`routes/api.php:18`) n'est
  référencée que par `window.SiteSearch` (`app/Providers/AssetServiceProvider.php:103-111`), lu par ce seul
  composant. Code mort, retrait prévu à l'étape 8 (S-16) : signalé, non supprimé.
- Hôte : `ddev exec bash -c "cd themes/<thème> && npm run build"` échoue (`@rolldown/binding-linux-arm64-gnu`
  absent : le `node_modules` du thème a été installé sur macOS) ; build fait sur la machine
  (`cd themes/<thème> && npm run build`). Correctif : aligner les consignes de l'hôte sur la règle du module (outillage
  Node sur la machine), ou réinstaller le `node_modules` du thème dans ddev.

**Complément du 2026-09-28 — première passe de style (proposition validée par Louis).** Aucune animation
(étape 7) ; seule transition : la couleur au survol d'une ligne, 150 ms `ease`, posée dans l'état `:hover` (la
désignation au clavier reste instantanée), coupée sous `prefers-reduced-motion`.
- *Appliqué.* Fond du panneau en pleine largeur, contenu centré par le seul `padding-inline`
  (`max(--meili-search-inline, (100% − --meili-search-max) / 2)`, aucune enveloppe) ; champ à bordure fine, au
  focus bordure `currentColor` + halo 4 px à 12 % + `outline` transparent (visible en `forced-colors`) ; loupe à
  gauche et croix native neutralisée (`::-webkit-search-cancel-button`, masque en data URI, **CSS seul**, aucun
  bouton maison : visible seulement avec un terme, comportement natif) ; `mark` sans fond, graisse 600 ; titre 500,
  résumé et prix à 65 % en 0.8125rem ; en-tête de section en capitales espacées à 70 %, compte en chiffres
  tabulaires, filet `--meili-rule` sous l'en-tête ; « voir tous » à droite de l'en-tête, flèche en `content`
  muette pour les lecteurs d'écran (`"→" / ""`), soulignée au survol ; survol 6 %, option désignée 10 % + barre de
  2 px ; vignette 3rem à coins de 0.5rem sur un fond teinté à 6 % (pseudo-élément, recouvert par l'image quand elle
  existe) ; ombre `0 16px 32px -16px` à 20 % ; champ collant ; 3rem entre sections, 0.25rem entre lignes, 0.75rem
  de marge interne ; messages centrés à 65 %, 2rem de marge verticale. `contain: layout paint` sur le panneau et
  sur chaque liste (anneau de focus d'un lien d'option ramené à l'intérieur, `outline-offset: -2px`).
- *Adapté.* **Voile** : premier jet en `position: fixed` sur `::after` de la racine ; retour de Louis, l'en-tête
  passait dessous. Il est désormais placé **comme le panneau** (`absolute`, `top: 100%` de l'ancre, `100dvh`, un
  cran sous le panneau) : il couvre la page sous l'en-tête et rien d'autre, sans JS ni mesure en plus, et échappe du
  même coup au piège du `backdrop-filter` de `.site-header` (bloc conteneur des `fixed`). Opacité ramenée
  de 30 à **20 %**. Un clic sur le voile tombe sur la racine : clic extérieur, fermeture, rien d'activé dessous.
  **Champ collant** : `top` négatif de la marge du panneau (Chromium colle au bord de contenu d'un conteneur
  paddé ; sans ce décalage, une bande de 1.5rem laissait voir les options défiler au-dessus du champ).
- *Crochet ajouté* (additif, `Contract::VERSION` inchangé, aucune règle de contrat : le client ne l'adresse
  pas) : `search-field` (`Hook::SearchField`), enveloppe statique du champ dans `search-input.blade.php`, qui porte
  la loupe et le collage. *Déplacé* : `search-see-all` passe entre le titre et la liste dans
  `search-section.blade.php` (ordre de lecture = ordre visuel) ; le client ne le lit pas, le compte et les options
  sont cherchés dans la section (`contract.one(…, section)`) : inchangés. Fixtures TS alignées sur les vues.
- *Tokens ajoutés* (`[data-meili="search"]`) : `--meili-tint-active`, `--meili-halo`, `--meili-muted`,
  `--meili-label`, `--meili-glyph`, `--meili-shadow`, `--meili-scrim`, `--meili-duration-hover`, `--meili-small`,
  `--meili-search-max`, `--meili-search-row`, `--meili-search-message`, `--meili-search-glyph`,
  `--meili-search-glyph-inline`, `--meili-search-mark`, `--meili-search-clear` ; modifiés : `--meili-tint` 8 → 6 %,
  `--meili-search-gap` 1.5 → 3rem, `--meili-search-thumb` 3.5 → 3rem. Documentés dans `configuration.md`.
- *Mesures.* `site-search.css` : 4 893 → 10 046 o brut, 1 331 → 2 352 o gzip (les deux masques SVG pèsent ~0.9 ko
  brut). Trace Chrome (1440, ouverture puis frappe de « serum », CPU non bridé) : aucune long task (tâche max
  3.96 ms), recalcul de style max **0.76 ms** (celui forcé par `PanelRoom.measure()` à l'ouverture, déjà là à
  l'étape 5, un seul), layout max 1.17 ms à l'arrivée des résultats, paint max 0.24 ms, pas de layout thrash
  (6 lectures forcées, toutes à l'ouverture, `measure` et `focus`), INP 42 ms.
- *Contrastes* (texte atténué sur `Canvas`) : 65 % → 6.98:1 sur blanc, 8.6:1 sur noir, 6.4:1 sur une ligne
  désignée ; 70 % → 8.52:1 et 9.96:1. Rien à remonter.
- *Recette* (Chrome DevTools, accueil, caches vidés, `module:publish`) : ouverture → focus dans le champ ; « ser »
  → Produits 1 · Articles 1 ; Échap ferme et rend le focus à la loupe, voile retiré ; clic sur le voile ferme, la
  loupe et les liens de l'en-tête restent au-dessus et cliquables ; ↓ puis Entrée ouvre
  `/produit/serum-visage` ; aucune erreur console ; `/boutique` : 4 filtres, 16 cartes, tiroir, rendu
  inchangé. Mode sombre émulé : le projet de test ne déclare pas `color-scheme`, le panneau reste clair ; avec
  `color-scheme: dark` posé à la main sur la racine, `Canvas`/`CanvasText` basculent (fond #121212, texte blanc,
  voile clair). Playwright MCP n'a délivré aucun événement souris à la page pendant la session (hors module :
  `element.click()` ouvrait) ; recette faite dans Chrome DevTools.
- *Tests.* Feature : `it_wraps_the_field_in_the_box_that_carries_its_magnifier`,
  `it_places_the_link_to_the_archive_between_the_heading_and_the_options` ; client
  (`site-search-stylesheet.test.ts`) : champ collant, « voir tous » sur la ligne du titre, voile à partir de 48em
  sous l'ancre et aucun `filter`. `composer check` vert (358 PHP, 625 Node), `--testsuite Modules` vert (666).
- *Cinq passes.* Lisibilité : aucun PHP de logique ajouté. Commentaires : aucun dans la feuille, aucun ajouté
  ailleurs. Performance : aucun JS de style, aucun `backdrop-filter`/`filter`, masques inline, pas de police ni
  d'image. Sécurité : rien de dynamique ajouté. Contexte/i18n : aucune chaîne ajoutée ; flèche décorative muette.
- *À trancher (goût).* Voile éclaircissant sous un `color-scheme: dark` (il suit `CanvasText`) ; 3rem entre sections
  aussi sur mobile, où il sépare aussi le champ de la première section ; barre de 2 px épousant l'arrondi de la
  ligne ; titre en 500 qui paraît gras avec la police du projet de test.

**Commit proposé.** Module : `feat(search): compose the search panel and its sections` ; hôte :
`feat(header): open the site search from the header magnifier`.

### R-190 · 🟡 · ouvert (en attente de commit) · ouvert le 2026-09-28 — étape 4, client de recherche : chargeur, client, surlignage, clavier

Rattaché à `R-180`, seconde partie de l'étape 4 de [chantier-recherche.md](chantier-recherche.md) (D-2, D-7, S-4, S-8,
S-9, S-12, S-17, S-18, S-21 ; `R-159` côté client). Rien n'est commité, réindexé ni écrit en base ou dans le moteur. Le
thème n'est pas touché : les vues des briques sont l'étape 5.

**Livré** (`ts/site-search/`, dossier autorisé au § 8).
- `Typing` : seuil et délai lus dans la description, terme compté après `trim()` en points de code, **garde `R-159`**
  (aucune lettre ni chiffre → pas de recherche, `\p{L}`/`\p{N}`), un terme qui ne change qu'en espaces ne repart pas.
- `SiteSearchQuery` : une sous-requête multi-search par section posée, clé = type — `q`, `filter` =
  `FilterExpression.all(baseFilter)`, `page: 1`, `hitsPerPage: limit`, `attributesToSearchOn: searchOn`,
  `attributesToRetrieve: ["ID","card"]`, `attributesToHighlight: ["card"]`, balises U+E000/U+E001.
- `Highlight` : nœuds texte + `<mark>`, jamais d'`innerHTML` sur une chaîne du moteur ; ne lit que
  `_formatted.card.title` et `_formatted.card.summary`.
- `SectionView` : lit les sections **présentes dans le DOM** (`data-type`, `data-limit`, sinon la limite de la
  racine), nomme en console une section dont le type n'est pas décrit ; peint compte (`CountLabel` + `countPattern`)
  et cartes (`CardView` + `Highlight`), masque la section vide.
- `StatusView` : `aria-busy` sur `search-panel` ; annonce **après** la peinture — « Produits : 2 résultats et
  Articles : 1 résultat » (motif `sectionPattern`, conjonction `Intl.ListFormat` de la locale), sinon le texte de
  `search-empty` ; en panne, `search-unavailable` seul révélé et annoncé.
- `ComboboxKeys` : ↓/↑ (↑ depuis le champ = dernière option, bornés aux extrémités), `aria-activedescendant` sur le
  champ, `data-active` sur l'option, Entrée = `click()` du lien de l'option, **Entrée sans option active : rien**
  (touche consommée, S-9) ; `Home`/`End`/←/→ rendent l'option et laissent le curseur au texte (motif APG du combobox
  éditable) ; composition d'une méthode de saisie respectée. **Rien extrait de `ListboxKeys`** : seules deux flèches
  seraient communes, et leurs bornes diffèrent.
- `SiteSearch` : un `SearchClient` par racine ; `abandon()` quand le terme redescend sous le seuil ; une réponse
  dépassée n'est jamais peinte (rejet `SearchSuperseded`) ; panne (refus, délai, réseau) → sections masquées, message
  seul ; latence `performance.mark('meilifacets:search-sent')` → `measure('meilifacets:search-shown')`, hors délai.
- Chargeur `site-search-page.ts` → `dist/site-search.js` (5 299 o, 2 172 o gzip) : `SearchPanel` ouvre, écrit
  `aria-expanded` et met le focus dans le champ **avant** l'import ; au premier survol ou focus de la loupe (ou du
  champ, ou à l'ouverture), `preconnect` (sauf si la page le porte déjà) et `import()` du client ; client absent →
  `search-unavailable`. Client `site-search-client.ts` → `dist/site-search-client.js` (9 150 o, 3 694 o gzip),
  importé sous `?ver=<empreinte sha-256 de son contenu>`, écrite dans le chargeur par `bundle.ts` (`define`) : le
  `?ver=` du chargeur n'atteint pas un import relatif.
- Contrat : crochets `summary`, `search-toggle`, `search-panel`, `search-input`, `search-status`, `search-empty`,
  `search-unavailable`, `search-section`, `search-count`, `search-results`, `search-card-template` (§ 8, renommé depuis `search-template` sur décision de Louis), règles
  dans `RootComponent.SEARCH` ; `Contract::VERSION` reste 1. Description : clé `sectionPattern` (`:heading: :count`,
  fr `:heading : :count`).

**Corrigé dans le code existant.** `SearchSeam` déplacé de `listing.ts` vers `search-client.ts` (le client qu'il
abstrait) ; compteur de recherches en vol, dupliqué, extrait en `shared/searches-under-way.ts` (`SearchesUnderWay`,
utilisé par `Listing` et `SiteSearch`) ; `' AND '` écrit deux fois → `FilterExpression.all()` (miroir de la méthode
PHP), repris par `ListingQuery` et `PriceQuery` ; `aria-activedescendant`, `data-active` et `aria-busy` locaux au tri et
à la grille → `shared/attributes.ts` ; `SearchQuery.facets` optionnel, le listing garde un `FacetedQuery` qui l'exige ;
`CardView` écrit `summary` ; `bundle.ts` en classe `Bundle`, trois paquets ; script `test` avec `--test-timeout=10000`
(dette de `R-189`) ; `ClientScriptTest` ne suppose plus le paquet de recherche absent (`usePublicPath()` vers un
dossier vide) ; huit copies publiées orphelines supprimées de `public/modules/meilifacets/ts/`.

**Vérifié.** Multi-search réel, clé publique, requête identique à `SiteSearchQuery` (« ser ») : accepté (les deux
`attributesToSearchOn` sont dans `searchableAttributes`), produits 1 (#116) et articles 1 (#178), `_formatted.card.title`
= `Sérum Éclat Vitamine C`, `summary` présent pour l'article seul, `_formatted.card` porte aussi `url`, prix
et dimensions (en chaînes) — non lus. Dans Chromium (Playwright, gabarit injecté sur `/boutique`, description réelle
lue par `SiteSearchDescription`) : loupe → panneau ouvert, focus dans le champ, client pas encore chargé ; « creme » →
« Produits : 2 résultats et Articles : 1 résultat », 3 `<mark>` ; Entrée sans option : rien ; ↓↓ → option 2 ; `Home` →
option rendue ; « zzzz » → message vide ; « ?( » → aucune recherche ; moteur coupé → « Recherche indisponible », URL
inchangée ; ↓ + Entrée → `/produit/creme-hydratante`. Aucune erreur console hors la panne provoquée. `/boutique`
inchangé (case → 1 résultat, `?categorie=cheveux`). **Latence** (28 recherches, local) : médiane 9,3 ms, **p95
21,9 ms**, max 25,5 ms — sous l'objectif proposé de 100 ms.

**Tests.** Client : `typing` (6), `highlight` (4, dont `<script>`), `site-search-query` (3), `section-view` (6),
`status-view` (6), `combobox-keys` (9), `search-panel` (5), `site-search` (9 : un seul envoi, réponse dépassée non peinte,
abandon sous le seuil, vide, refus et réseau, `aria-busy`, options, mesure), `bundle` (+4), `contract` (+1 net : la racine
de recherche exige ses briques, section et template) ; `page-roots` : gabarit de recherche complété, assertions
inchangées. Fetch simulé honorant l'annulation : c'est le vrai `SearchClient` qui annule. PHP : `ContractParityTest` +5
(module, clé et paquet de chaque chargeur, champ `card`), `ClientScriptTest` +2. **Résultats** : client 606/606 (les 553
d'avant verts, listing sans assertion changée ; 98,97 % lignes, 93,80 % branches), Unit 345, `composer check` vert,
`build:check` vert, suite `Modules` **OK (635 tests, 1863 assertions)**.

**Les cinq passes.**
- *Lisibilité* : méthodes de 1 à 19 lignes ; aucun booléen en paramètre (`#open`/`#close`) ; un nom par concept
  (`SearchesUnderWay`, `FilterExpression.all`, attributs partagés) ; `SectionView.allIn()` porte la lecture du DOM.
- *Commentaires* : gardés, anomalies ou contournements : `R-159`, surlignage par objet entier, `card.title` sans
  `_formatted`, `length` en UTF-16, composition IME, `?ver=` d'un import relatif, `preconnect` déjà posé par le listing ;
  retirés à la relecture : cinq justifications de conception (reportées ici et dans `decisions.md`).
- *Performance* : une requête par terme retenu (délai, dédoublonnage des espaces), une annulation par frappe ; chargeur
  2,2 Ko gzip sur toutes les pages, client 3,7 Ko à la première intention ; paquet du listing +559 o, +152 o gzip (règles de la racine de recherche, `summary`, `SearchesUnderWay`).
- *Sécurité* : aucune chaîne du moteur en HTML hormis le prix (décision « Markup du prix », inchangée) ; titre et extrait
  en texte, testé avec `<script>` et `<img onerror>` ; seule la clé de recherche voyage ; rien dans l'URL.
- *Contexte et i18n* : diagnostics en anglais ; annonce composée de libellés traduits et de la conjonction de la
  locale ; sans WooCommerce, la section produits n'est pas posée et rien n'est cherché pour elle.

**Hors périmètre, noté.**
- `ts/facets/facets-view.ts:156,162,189-191` écrit `'aria-expanded'` en dur alors que `EXPANDED` existe ; correctif :
  importer `EXPANDED` de `shared/attributes.ts`.
- `app/Indexing/FacetedPostIndexable.php:29` écrit `['ID', 'card']` en dur, et `ID` n'a pas de cas `DocumentField` ;
  correctif : `DocumentField::Id`, puis un jumeau de parité pour `RETRIEVED` de `site-search-query.ts`.
- `ts/listing/listing.ts:147` annonce `failed`, que personne n'écoute côté listing : une panne après le premier rendu ne
  montre rien ; à rattacher au lot 6 (panne du moteur).
- `Escape`, clic extérieur et Tab qui sort (S-12, `shared/light-dismiss.ts`) : étape 5, comme prévu au plan.

**Arbitrages de Louis (2026-09-28).** Validés (`decisions.md`, « Validées ») : annonce par `sectionPattern`, crochet
`search-template` renommé `search-card-template` sans alias, clavier du combobox. Restent en attente : attributs de
section, version du client, chargement, abandon.

**Commit proposé.** `feat(search): add the site search loader and client`.

### R-189 · 🟡 · ouvert (en attente de commit) · ouvert le 2026-09-28 — étape 4, extractions : racines du contrat, lecture des données publiées, feuille paramétrée

Rattaché à `R-180`, première partie de l'étape 4 de [chantier-recherche.md](chantier-recherche.md) (§ 4 « Réutilisé
plutôt que réécrit », S-4, S-17, S-18) ; lève les deux dettes notées par `R-188`. Rien n'est commité, réindexé ni
écrit en base ou dans le moteur. Le comportement du listing est inchangé.

**Extrait.**
- `ts/shared/root-component.ts` — `RootComponent` : `LISTING` (`data-listing`, règles du listing, reprises telles quelles
  de `contract.ts`) et `SEARCH` (`data-search`, **aucune règle** : la racine ne rend qu'un conteneur ; les règles
  `search-*` viendront de façon additive, `Contract::VERSION` reste 1, `R-116`). API : `RootComponent.of(element)`,
  `RootComponent.ownerOf(hook)`, `RootComponent.anySelector`, et par composant `name`, `attribute`, `rules`, `selector`,
  `tag` (`<x-meilifacets::search>`), `nameOf(element)`.
- `Contract` lit le composant racine sur son élément et applique ses règles ; un élément qui n'est aucune racine est refusé
  (« no root: expected one of … »). `Contract.orphans(document, owner)` : un crochet hors de **toute** racine
  est orphelin, nommé par le client qui le possède (`search`/`search-*` → recherche, S-18 ; le reste → listing).
  Ferme la dette `contract.ts:62-69` de `R-188`.
- `ts/shared/page-roots.ts` — `PageRoots<Description>(document, component, module)` et `start(binder)` : lit
  `wp-script-module-data-<module.id>`, prend les descriptions sous `module.roots`, signale l'absence de données,
  les orphelins du composant, une racine non décrite et les infractions, puis appelle `binder.bind({ contract,
  description, connection })` pour chaque racine valide. Types exportés : `ScriptModule` (`{ id, roots }`, miroir de
  l'enum PHP), `RootBinder<D>`, `BoundRoot<D>`. `listing-page.ts` n'est plus que `ListingPage implements
  RootBinder<ListingDescription>` et une ligne `new PageRoots(document, RootComponent.LISTING, MODULE).start(…)` ;
  messages console identiques à l'octet pour le listing.
- PHP : `Enums\Stylesheet` (valeur = poignée, `source()` = chemin publié ; un cas, `Listing` = `meilifacets`) et
  `View\ClientStylesheet` (`register()` inscrit chaque feuille publiée, `require(Stylesheet)`), sur le modèle de
  `ClientScript`/`ScriptModule`. Ferme la dette `Stylesheet.php:14` de `R-188`. La feuille de recherche n'est pas
  créée ; sa poignée est une question (ci-dessous).

**Non extrait : `ListboxKeys`.** Seuls 4 des 8 déplacements de `whileOpen()` (flèches, `Home`, `End`) sont communs ;
Entrée/Espace/Tab y « choisissent », ce qui contredit S-9 et l'Entrée « suivre le lien » du combobox, et dans un
champ éditable `Home`/`End` déplacent le curseur (APG combobox, optionnels). La forme de l'extraction dépend de ce
choix, qui appartient à `ComboboxKeys` : laissé à la partie suivante.

**Corrigé dans le code existant.** `View\Stylesheet` → `View\ClientStylesheet` (classe qui sert plusieurs feuilles),
sans alias ; `StylesheetTest` → `ClientStylesheetTest`. Message « the listing is not in a window » → « the contract
root … » (`Contract` sert aussi la recherche). `listing-page.ts` : `listings` absent de la donnée publiée ne lève plus
de `TypeError`, il tombe sur « describes no listing named ». Docs : `configuration.md`, `architecture.md`,
`pieges.md`, `decisions.md`.

**Tests.** Client : `page-roots.test.ts` (7 : liaison par nom, composant et clé du paquet, silence sans racine ni
donnée, paquet nommé, racine non décrite, infraction, orphelins du composant) ; `contract.test.ts` +5 (racine de
recherche sans exigence, composant lu sur l'attribut, élément sans composant refusé, racine de recherche à côté d'un
listing non signalée ni son contenu, orphelin nommé par son seul propriétaire). Adaptations sans toucher aux
assertions : le gabarit `versioned()` pose `data-listing` ; `orphans()` reçoit le composant. `ContractParityTest` +4 :
clé `listings` des deux côtés, attributs `data-listing`/`data-search` rendus par les vues, préfixe `search` =
`Hook::Search`. **Résultats** : client 553/553 (les 541 d'avant inchangés ; 97,91 % lignes, 94,22 % branches),
Unit 340, `composer check` vert, `build:check` vert, suite `Modules` **OK (628 tests, 1845 assertions)** — après
`module:publish` (sans lui, `PublishedAssetsTest` signale les copies périmées, attendu).

**Vérifié dans Playwright** (`/boutique`, paquet neuf servi, `?ver` et contenu contrôlés) : `immediate` (config de
Louis) — une case cochée filtre (62 → 1, `?categorie=cheveux`), le tri « Prix croissant » réordonne
(`?sort=price_asc`), le tiroir s'ouvre à 393 px (`aria-modal`, focus dedans) ; `submit` (config modifiée puis
restaurée, `cmp` = 0, md5 identique) — la case ne cherche pas, « Appliquer » filtre puis rend 62, tri et tiroir
idem. Aucune erreur console hors un `/boutique/` → `http://` de l'hôte (redirection de la barre oblique, sans
rapport avec le module).

**Les cinq passes.**
- *Lisibilité* : méthodes de 1 à 17 lignes ; `RootComponent` (le composant racine) distinct de `Contract.root` (l'élément) ;
  `ScriptModule` même nom des deux côtés ; `anySelector` plutôt qu'un statique homonyme de `selector` ; aucun
  booléen en paramètre ; enregistrement PHP découpé (`registerHandle()`).
- *Commentaires* : ajoutés : trois docblocs de classe, le miroir de `ScriptModule` ; déplacés : deux ; retirés à
  la relecture : deux justifications de conception (règles vides de la recherche, préfixe S-18), reportées ici et
  dans `decisions.md`.
- *Performance* : un `querySelectorAll` de plus au démarrage (toutes les racines) ; paquet du listing +1 082 o
  (+360 o compressés), dû au composant `search` ; PHP : mêmes appels par requête (un `is_file` par feuille publiée).
- *Sécurité* : aucune entrée d'URL ; le nom de racine vient du rendu serveur ; rien de secret ajouté à la page.
- *Contexte et i18n* : diagnostics en anglais ; aucune chaîne d'interface.

**Hors périmètre, noté.**
- `package.json:11` : le script `test` ne pose ni `--test-timeout` ni plafond mémoire, alors que la consigne
  l'exige ; correctif proposé : `node --test --test-timeout=10000 …` (et `NODE_OPTIONS` documenté).
- `resources/views/components/listing.blade.php:1` et `search.blade.php:1` écrivent `data-listing`/`data-search`
  en dur, sans constante PHP ; désormais tenus par `ContractParityTest`. Correctif proposé si besoin : deux cas de
  plus dans `Enums\Contract`.

**Arbitrages de Louis (2026-09-28).** `RootKind` renommé `RootComponent` (`ts/shared/root-component.ts`, membre
`component` → `tag`), sans alias ; répartition des crochets par préfixe `search` et poignée `meilifacets-site-search`
validées (`decisions.md`, « Validées »). Reste en attente : feuilles nommées par un enum (`Enums\Stylesheet` +
`ClientStylesheet`).

**Commit proposé.** `refactor(client): share the root loader, root rules and stylesheets`.

### R-188 · 🟡 · ouvert (en attente de commit) · ouvert le 2026-09-28 — étape 3b : racine de recherche, réglages et publication partagée

Rattaché à `R-180`, seconde moitié de l'étape 3 de [chantier-recherche.md](chantier-recherche.md) (S-4, S-17,
S-19, S-21) ; suit `R-187`. Rien n'est commité, réindexé ni écrit en base ou dans le moteur.

**Livré.**
- `SiteSearch\SearchSettings` (`final readonly`, `DEFAULT_MIN_CHARS` 2, `DEFAULT_DELAY` 120 ms, `DEFAULT_LIMIT` 4,
  `withMinChars()`/`withDelay()`), lié en `bindIf` dans `SiteSearchServiceProvider` ; aucune clé de config.
- Racine `<x-meilifacets::search>` (`View\Components\Search`, vue `components/search.blade.php`) : attributs `name`
  (défaut `SearchRoot::DEFAULT_NAME`), `min-chars`, `delay` ; rend un conteneur `data-meili="search"`
  (`Hook::Search`, autorisé au § 8), `data-search="<name>"`, version du contrat, slot. Construit un
  `SiteSearch\SearchRoot` (nom, réglages effectifs, types acceptés dans l'ordre de `AcceptedSearchTypes::all()` —
  la donnée des sections automatiques de l'étape 5) et l'ouvre dans `SiteSearch\SearchRegistry` (`scoped`), qui
  refuse un nom en double.
- `View\SiteSearchDescription::of(SearchRoot)` : `name`, `minChars`, `delay`, `limit`, `types` (liste ordonnée de
  `postType`, `heading`, `seeAllLabel`, `baseFilter`, `searchOn`, `archive`), `countPattern` (clé existante
  `:count result|:count results`), `locale`, `preconnect`.
- `View\ClientScript` remplace `ListingScript` : paquet choisi par `Enums\ScriptModule` (`Listing`, `SiteSearch`),
  description passée en `Closure` paresseuse, filtre `script_module_data_<paquet>` posé à l'inscription, exclusion
  du Delay JS limitée aux paquets publiés, `fetchpriority` filtré avec l'identifiant du paquet en second argument.
  La racine de recherche l'appelle déjà ; tant que `dist/site-search.js` n'existe pas, rien n'est inscrit.
- `Preconnect::origin()` public, lu par la balise du `<head>` des listings et par la description.

**Corrigé dans le code existant.** `ListingScript` supprimé (sans alias), remplacé par `ClientScript` dans
`Listing`, `RenderingServiceProvider`, `ContractParityTest`, les docs (`installation.md`, `architecture.md`,
`decisions.md`). `ListingRegistry` réduit à `add()` sur une base extraite `Support\NamedRegistry` (`named()`,
`sole()`, `names()`), partagée avec `SearchRegistry` ; son `get()` disparaît : le « No listing named » de
`CurrentListing` passe dans `named()` (message désormais « Declared: a, b. »). `Preconnect` ne recopie plus
l'expression « configurée ? origine : '' ». Thème et plugin d'expéditions du projet : aucune référence (`grep`).

**Vérifié.** Avant toute modification, `/`, `/boutique`, `/journal`, `/?s=creme&post_type=product` et
`/categorie-produit/visage` capturés ; après (`discovery:clear`, `view:clear`), les trois pages de listing sont
**identiques à l'octet**, `/` et `/journal` ne diffèrent que par les jetons chiffrés de Gravity Forms (aléatoires
à chaque requête, 4 lignes chacun) ; tous en 200. `rocket_delay_js_exclusions` identique :
`["modules/meilifacets/dist/listing.js"]`.

**Tests.** Feature `ClientScriptTest` (8, ex-`ListingScriptTest`) : priorité basse et filtrée, exclusion WP Rocket,
**publication du listing égale, en JSON, à la formule d'avant l'extraction**, aucune valeur égale à la clé
maîtresse de MeiliScout et `connection.key` = clé du navigateur (assertions booléennes, la clé n'est jamais
imprimée), description jamais lue sans connexion, paquet non publié ni inscrit ni exclu. `SearchComponentTest` (7) :
conteneur, défauts, `min-chars`/`delay`, liaison projet + attribut, deux racines, doublon refusé, types dans l'ordre.
`SiteSearchDescriptionTest` (2). Unit `SearchSettingsTest` (2), `SearchRegistryTest` (5). `indexed_post_types`
épinglé en `setUp()` (`PinsIndexedPostTypes`), aucun `#[Before]` WordPress, aucun post enregistré. Lancées seules :
8/8, 7/7, 2/2, 5/5, 2/2 ; `ListingRegistryTest` 3/3. `composer check` vert (Unit 336, client 541/541,
`build:check`) ; suite `Modules` **OK (624 tests, 1839 assertions)**.

**Les cinq passes.**
- *Lisibilité* : méthodes de 1 à 10 lignes ; gardes composées nommées (`canLoad()`, `isPublished()`) ; aucun
  booléen en paramètre ; la surcharge des réglages en deux ternaires simples (Rector refusait la forme en `if`).
  Constructeur de `Search` à 8 paramètres, dont 5 injectés — comme `Facet` (7).
- *Commentaires* : ajoutés et gardés : contournement du filtre de données nommé par WordPress (`ClientScript`),
  idempotence due à `R-171` (`ListingRegistry`). Quatre retirés à la relecture (justifications de conception de
  `SearchSettings`, `SearchRegistry::open()`, `SearchRoot`, `Preconnect::origin()`), reportées dans `decisions.md`.
- *Performance* : types acceptés calculés une fois par racine ; description construite seulement si le paquet est
  inscrit ; deux `is_file` de plus par page pour l'exclusion du Delay JS.
- *Sécurité* : aucune entrée d'URL ; seule la clé de recherche part au navigateur (testé) ; `name` échappé par Blade.
- *Contexte et i18n* : motif de compte traduit sans domaine, `locale` publiée ; sans WooCommerce, la racine ne porte
  que les types non produits (dérivation `R-187`).

**Hors périmètre, noté.** `Contract.orphans()` (`ts/shared/contract.ts:62-69`) signalera `data-meili="search"` hors
d'un listing dès qu'un thème posera la racine sur une page de listing — correctif prévu à l'étape 5 (règles par
racine). `Stylesheet` (`app/View/Stylesheet.php:14`) ne sert que la feuille du listing — paramétrage prévu à
l'étape 5 avec `site-search.css`. *Les deux levées le 2026-09-28 par `R-189` (étape 4, extractions) : `RootComponent` et
`Contract.orphans(document, owner)`, `Enums\Stylesheet` + `ClientStylesheet`.*

**Questions pour Louis.** Voir `decisions.md` « En attente de validation » : doublon de nom refusé, `preconnect`
dans la description, API de `ClientScript` ; et l'attribut `data-search` (calqué sur `data-listing`).

**Commit proposé.** `feat(search): add the search root, its settings and shared script`.

### R-187 · 🟡 · ouvert (en attente de commit ; seconde moitié livrée par `R-188`) · ouvert le 2026-09-28 — étape 3a : déclaration des types cherchables

Rattaché à `R-180`, première moitié de l'étape 3 de [chantier-recherche.md](chantier-recherche.md) (D-3, S-2,
S-19, S-21, `R-160`). La seconde moitié — racine `<x-meilifacets::search>`, `SearchSettings`, description publiée —
reste à faire. Rien n'est commité, rien n'est réindexé, rien n'est écrit en base ni dans le moteur.

**Refonte du 2026-09-28, sur revue de Louis** (décisions dans `decisions.md` « Validées » : types dérivés de
WordPress, types retenus, ordre, `searchOn`, surcharge, carte de recherche, réutilisation pour le client). Le
premier jet déclarait deux types en dur avec ses propres chaînes (`__('Posts')`, `See all …`, un motif de compte
par type) ; il est remplacé ainsi :

- *Objet de valeur* `SiteSearch\SearchableType` (`final readonly`) : `postType`, `heading`, `seeAllLabel`,
  `baseFilter`, `searchOn`, `card`, `archive` — `countPattern` retiré (la section réutilisera la clé existante
  `:count result|:count results`, vérifiée dans `lang/fr.json` et lue par `Facet` et `ListingDescription`).
  `withHeading()`, `withSeeAllLabel()`, `withCard()`, `withSearchOn()`, immuables ; un `searchOn` vide lève
  `NoFieldToSearch` (nomme le type), à la construction.
- *Fabrique* `SearchableTypeFactory` : `forPostType()` lit `labels->name`, `labels->all_items`,
  `get_post_type_archive_link()` (`null` sans archive), filtre `PublishedPosts`, champs titre + `labels.*` des
  taxonomies du type + `excerpt` filtrés par `AttributesToSearchOn::among()`, carte `meilifacets::search-card` ;
  `make()` pour un type dont le filtre et les champs diffèrent ; un type non enregistré lève
  `SearchTypeRefused::unregistered()`.
- *Défauts* : `SearchablePostTypes` = types indexés, publics, non `exclude_from_search`, dans l'ordre de
  `indexed_post_types` ; `WordPressSearchableTypes` les dérive, **`product` toujours ignoré**, mémoïsés par
  requête ; `WooCommerceSearchableTypes` le décore : sous WooCommerce actif (closure lue à chaque appel, `R-171`)
  et `product` retenu, les produits passent en tête par `make()` (`VisibleProducts::inSearch()`, titre +
  `WooCommerceProductFields`). Aucun `product` générique construit puis jeté. Liés dans `SiteSearchServiceProvider` :
  les deux en `scoped`, le contrat en `scopedIf` par nom de classe.
- *Validation* : `AcceptedSearchTypes` lève `SearchTypeRefused` si le type n'est pas déclaré **ou** pas indexé, et
  `FieldsOutsideSearchOrder` (nomme le type et les champs) si un `searchOn` sort de l'ordre de recherche
  (`AttributesToSearchOn::outside()`), dans `all()` comme dans `get()`.
- *Surcharge* : `configuration.md`, section « Types cherchables » (exemple décorateur générique, liaison, ajout et
  retrait d'un type) et ligne du tableau des points d'extension ; l'exemple est exercé tel quel
  (`Tests\Unit\Doubles\RetitledSearchableTypes`).
- *Chaînes* : les six clés du premier jet retirées de `lang/fr.json` (fichier revenu à l'identique de `ce4160d`).

**Doublons, vérifiés avant d'agir.**
- `searchedFields()` / `DefaultSearchableAttributes::labelFields()` : même expression (chemins `labels.*` d'une
  liste de taxonomies) sur deux entrées différentes → **supprimé** : `DocumentField::paths()`, utilisé par les
  deux ; `labelFields()` disparaît.
- `$pluginIsActive` dans `WooCommerceSearchableTypes` et `WooCommerceProductFields` → **gardé** : pas un doublon.
  `WooCommerceProductFields` sert aussi `DefaultSearchableAttributes`, qui n'a pas d'autre garde ; le décorateur,
  lui, en a besoin pour le filtre et la place des produits (sans WooCommerce, `facets.product_visibility` n'est
  pas déclaré filtrable). Seule conséquence : sur le chemin du décorateur, la garde des champs est relue une fois,
  toujours vraie.
- `PostTypeArchive` → **supprimé** : il ne faisait que normaliser `false` en `null` ; c'est désormais une méthode
  privée de la fabrique, seul lecteur.
- `VisibleProducts` → **déplacé** de `Listing\` à `Search\`, à côté de `PublishedPosts`.
- `IndexedPostTypes`, `PublishedPosts` → gardés, justes (une lecture de l'option, un filtre partagé).

**Corrigé dans le code existant (avant 3a).** `IndexedTaxonomies` perd son constructeur à défaut
(`= new IndexedPostTypes`), gardé au premier jet pour ne pas toucher les tests : c'était une compatibilité ; les
quatre tests qui l'instancient passent la dépendance. `DefaultSearchableAttributes::labelFields()` remplacé par
`DocumentField::paths()` (ci-dessus). Thème et plugin d'expéditions du projet : aucun usage des classes touchées (`grep`).

**Vérifié à l'exécution** (`wp eval`, lecture seule, après `discovery:clear`) : `indexed_post_types` =
`post, product` ; **`page` n'est pas indexée** (publique, non `exclude_from_search`) : elle n'entre pas, elle
entrerait dès que MeiliScout l'indexerait. Types acceptés : `product` → « Produits », « Tous les produits »,
`/boutique`, `post_title, labels.product_brand, labels.product_cat, metas._sku`, filtre `exclude-from-search` ;
`post` → « Articles », « Tous les articles », `/journal`, `post_title, labels.category, labels.post_tag,
labels.post_format, labels.post_kind, excerpt`. `/`, `/boutique`, `/journal`, `/?s=creme&post_type=product` : 200.

**Tests.** Feature `SearchableTypesTest` (17) : défaut WordPress sans `product` même indexé ; `withSearchOn(['url'])`
d'un décorateur refusé en nommant `post` et `url` ; liaison par défaut ; CPT indexé, public et cherchable déclaré sans
code (enregistré en mémoire, libellés natifs, carte, `null` sans archive, filtre) ; type `exclude_from_search` et
`page` non indexée absents ; libellés et archives égaux à ceux de WordPress ; `null` sans archive ; produits en tête
puis ordre de l'index ; sans WooCommerce aucun produit ; `searchOn` ⊆ ordre ; champs attendus littéraux ; ordre
restreint suivi ; `searchOn` vide lève ; `SearchTypeRefused` nomme `page` et `product, post` ; type déclaré non
indexé refusé ; type non enregistré refusé ; décorateur de la doc lié au conteneur. Unit `SearchableTypeTest` (2 :
`with…()` sans toucher au reste, `withSearchOn([])` lève), `AttributesToSearchOnTest` (3, dont `outside()`). `ProductSearchTest` :
filtre produits de la recherche = filtre de `ProductListing` sur une recherche. `indexed_post_types` épinglé partout
(`PinsIndexedPostTypes`, dans `setUp()` ou le test, jamais en `#[Before]`) ; aucun post enregistré, donc aucun
`KeepsTheIndexOut`. Lancées seules : 17/17, 6/6, 2/2, 3/3. `composer check` vert (Unit 329, client 541/541,
`build:check`) ; suite `Modules` **OK (604 tests, 1800 assertions)**.

**Les cinq passes.**
- *Lisibilité* : méthodes de 1 à 12 lignes, un niveau chacune ; aucun booléen en paramètre ; conditions
  composées nommées (`isSearchable()`, `searchesProducts()`) ; un nom par concept (`forPostType`/`make`,
  `paths`). `make()` a trois paramètres, le maximum admis. Le décorateur retire `product` **avant** de le remettre
  en tête : un spread de clés chaînes garderait la position du premier et la valeur du second.
- *Commentaires* : seize retirés — six docblocks de classe, trois `@param` scalaires, un `@var` répétant le
  contrat, six commentaires de tests (récits ou redites d'un docblock) ; un réduit (`VisibleProducts`, à l'anomalie
  R-160). Gardés : `AttributesToSearchOn` (anomalie du moteur), la closure `R-171`, les formes
  `list<string>` de `SearchableType`, le docblock « runs here » du test (convention des tests Feature voisins).
  Ajoutés : la constante `WIRING` du test (contournement : instances `scoped` à oublier), le docblock du double
  (convention de `ExcerptFirstSearchableAttributes`).
- *Performance* : une dérivation par type retenu et par requête (liaisons `scoped`, déclarations mémoïsées) ;
  plus aucune dérivation perdue pour `product` (corrigé sur demande de Louis). Par appel de `all()` : un
  `get_option` en cache et un `get_post_type_object()` par type indexé (`SearchablePostTypes::contains()`), un
  `array_intersect_key`, un `array_diff` par type pour l'ordre.
- *Sécurité* : aucune entrée d'URL ; le type vient du gabarit, les libellés et l'archive de WordPress ; les
  produits cachés de la recherche restent exclus, y compris d'un `product` indexé sans WooCommerce.
- *Contexte et i18n* : sans WooCommerce, aucun produit (testé) ; plus aucune chaîne propre : les libellés sont ceux
  que WordPress et les plugins traduisent, dans la langue de la requête.

**Questions tranchées par Louis (2026-09-28).** (1) inclusion de `searchOn` vérifiée à la validation →
`FieldsOutsideSearchOrder` ; (2) pas de `withBaseFilter()`, `make()` suffit (écrit dans `configuration.md`) ; (3)
sans WooCommerce, un `product` d'un autre plugin est écarté (`decisions.md`, « Validées ») ; (4) le `product`
générique n'est plus construit. Aucune question ouverte.

**Commit proposé.** `feat(search): derive the searchable post types from WordPress`.

### R-186 · 🟡 · ouvert · 2026-09-25 — MeiliScout repousse les réglages d'index à chaque sauvegarde

Relevé par la revue de `fd595a3` (hors lot, rien codé). `AbstractSingleIndexer::ensureIndexExists()` appelle
`updateSettings()` à **chaque** sauvegarde d'un post (commentaire amont : « idempotent »). Avec une liste
cherchable calculée (`SearchableAttributes`, taxonomies indexées), une liste qui diffère de la précédente relance
un retraitement complet de l'index côté moteur, et une sauvegarde faite avant réindexation pousse de nouveaux
réglages sur des documents anciens. Correctif amont proposé : n'envoyer les réglages que si leur empreinte a
changé, sur le modèle de l'option `meiliscout/last_indexing_structure`. Patch à proposer à MeiliScout plus tard.

### R-185 · 🟠 · **fermé le 2026-09-28** (point 2 fermé par `R-201` le 2026-09-30, taxonomies techniques laissées ouvertes) · ouvert le 2026-09-25 — corrections de la revue de `fd595a3`

Rattaché à `R-181` et `R-184`. `fd595a3` (« feat(search): rank and clean what the index searches ») est commité ;
ce lot ne l'est pas.

| # | Constat de la revue | Suite |
| --- | --- | --- |
| 1 | 🔴 les libellés suivaient `is_taxonomy_viewable()`, qui écarte les attributs `pa_*` sans archives : « 100ml » et « 50ml » → 0, la facette `pa_contenance` en compte (mesuré par la coordination, lecture seule) | **fait** — liste interdite figée dans la couche WooCommerce (`ProductTaxonomy::technical()`, quatre taxonomies lues dans `class-wc-post-types.php`, dont `pos_product_visibility`), écrite une fois dans `IndexedTaxonomies::labelled()`, lue par l'ordre par défaut et par `PostDocument` ; les deux copies de la règle ont disparu. Surcharge : seulement par le contrat (décision de Louis) |
| 2 | `ProductListing::hiddenHere()` suit `is_search()`, alors que `/boutique?q=creme` est aussi une recherche texte | **arrêté et rapporté, rien modifié** — voir ci-dessous |
| 3 | `DefaultSearchableAttributes` n'était pas instanciable par le conteneur : l'exemple de surcharge était inutilisable | **fait** — dépendances injectées (`IndexedTaxonomies`, `WooCommerceProductFields`, qui lit WooCommerce à chaque appel, `R-171`), liaison par nom de classe en `scopedIf` ; exemple de `configuration.md` réécrit en décorateur, exercé par un test |
| 4 | `card.excerpt` tronquait l'extrait écrit à la main ; commentaire « comme `wp_trim_excerpt()` » faux ; `hasOwnExcerpt()` refaisait mot de passe et nettoyage | **fait** — extrait de l'auteur entier, seul le repli sur le contenu est borné ; un seul nettoyage de l'extrait par appel ; commentaire corrigé (`get_the_excerpt()`) ; note « rendu en texte » dans `configuration.md`. Le renommage `excerpt` → `summary` (`CardField::Summary`, `SummaryCardProjector`, clé `summary` — rien ne lisait `card.excerpt` : ni client, ni Blade, ni JSON-LD) — **validé par Louis** : `summary` pour la carte, `excerpt` reste le nom du champ cherchable |
| 5 | test circulaire : l'attendu était recalculé par le nouveau code | **fait** — remplacé par un instantané figé (valeurs littérales, clés et ordre) sur un post **en mémoire** : aucune écriture en base, rien qui dépende du catalogue |
| 6a | `architecture.md` citait `addFacets`/`addCard`/`addPrice` | **fait** |
| 6b | `MeiliScoutBridge` à cinq dépendances | **fait** — deux (`PostDocument`, `FacetedPostIndexable` lié `scoped`). Rien de MeiliScout n'est résolu trop tôt : ses classes sont chargées par l'autoload Composer de l'hôte, `PostIndexable` n'a pas de constructeur, et toutes les lectures de `FacetedPostIndexable` ont lieu dans `getIndexSettings()` |
| 6c | `array_values(array_unique(array_merge()))` en trois endroits, lecture « chaîne non vide d'un terme » en trois | **fait** — `Support\UniqueList::merge()` ; `TermField::textIn()` (utilisé par `TermGrouping`, `TermAncestry`, `PostDocument`) |
| 6d | `PostText.php:48` au-delà de 120 caractères, commentaire qui justifiait un choix | **fait** — commentaire retiré, raison dans `decisions.md` |

**Point 2, pourquoi arrêté.** Le filtre de base ne dépend d'aucun état : `Listing::baseFilter()` n'a pas de
paramètre, `ListingDescription` le publie une fois (`filter`), et le client le recopie tel quel dans chaque
sous-requête (`listing-query.ts`, `#filterExpression()`), quel que soit `state.query`. Faire dépendre la
visibilité de `q` demande donc : (a) côté PHP, que le filtre de base lise l'état (`QueryPlan` et `unfiltered()`
l'appellent sans lui) ; (b) côté client, deux filtres publiés (catalogue, recherche) et un choix selon
`state.query`, sinon le premier rendu PHP et les requêtes du navigateur divergent dès le premier geste (compte
et cartes différents). C'est une modification du contrat `Listing`, de la description publiée et du client —
plus large que prévu. Aujourd'hui aucun champ ne saisit `q` (`R-44`) : seul un `?q=` écrit à la main est
concerné. **Reporté sur décision de Louis (2026-09-28)** au second livrable du chantier, la facette de recherche
reliée à un listing : c'est elle qui remplira `q`, la règle de visibilité se traite avec elle.

**Revue anti-dette avant commit (2026-09-28, deux relecteurs).** Corrigé : `card.excerpt` → `card.summary` dans le
plan (S-7, surlignage de l'étape 4) ; `PostText::summary()` réutilise `excerpt()` ; test « sans WooCommerce » de
`IndexSearchSettingsTest` supprimé (il ne prouvait rien, `WooCommerceProductFieldsTest` couvre) ; test « relu à
chaque appel » renforcé (lecture avant et après bascule) ; puces de `decisions.md` fusionnées dans R-184 ;
commentaire inexact du provider retiré ; `PostDocumentTest` et `IndexSearchSettingsTest` ne dépendent plus de
l'option `meiliscout/indexed_post_types` de la base locale (trait `PinsIndexedPostTypes`, posé dans `setUp()`
et non en `#[Before]`, cf. `R-183`). Non traité : `R-183` lui-même, hors lot.

**Laissé ouvert (Louis, 2026-09-28).** La liste des taxonomies techniques (`ProductTaxonomy::technical()`) est
figée : un projet ne peut pas en rendre une cherchable — le contrat `SearchableAttributes` peut ajouter
`labels.<technique>`, mais les documents ne portent pas ces libellés. Pistes : labelliser toutes les
taxonomies et n'exclure les techniques que de l'ordre par défaut, ou une méthode d'`IndexAttributes`.

**Vérifié à l'exécution** (sans envoi, `discovery:clear` avant) : `getIndexSettings()` du vrai indexable
contient `labels.pa_contenance` (entre `labels.product_tag` et `excerpt`) et aucun des quatre `labels.<technique>` ;
`formatForIndexing()` sur #116 → `labels.pa_contenance: ["30ml"]`, sur #126 et #127 → `["100ml"]`.

**Tests.** Échouent avec l'ancienne règle (vérifié en la réintroduisant le temps d'un passage : 3 échecs) :
`PostDocumentTest::it_labels_a_taxonomy_without_archives`,
`IndexSearchSettingsTest::it_searches_the_labels_of_a_taxonomy_without_archives` (taxonomie `pa_test_volume`
enregistrée en mémoire, sans archives), et l'ordre par défaut. Ajoutés aussi : taxonomies techniques exclues des
libellés mais facettées, décorateur de `configuration.md` poussé tel quel, `WooCommerceProductFieldsTest` (3,
Unit), extrait entier. `composer check` vert (Unit 324, client 541/541, `build:check`) ; suite `Modules`
**OK (582 tests, 1766 assertions)**.

**Réindexation nécessaire** (nouveaux `labels.pa_*`, clé `card.summary`) : procédure et contrôles dans
`chantier-recherche.md`.

**Les cinq passes.** *Lisibilité* : un endroit par règle (technique, fusion, lecture de terme) ; `summary()`
à un niveau, `opening()` pour le bornage ; `$openingWords` dit ce qu'il borne ; aucun booléen en paramètre.
*Commentaires* : un retiré (justification), un corrigé (faux), un gardé (contournement de `wp_trim_words()`).
*Performance* : `labelled()` = un `array_diff` sur la liste mémoïsée, une fois par document ; un nettoyage de
l'extrait de moins par carte. *Sécurité* : `summary` décodé, à rendre en texte (documenté) ; rien de protégé
par mot de passe n'est projeté. *Contexte* : sans WooCommerce, ni champ produit ni exclusion (testé pour les
champs).

**Commit proposé.** `fix(search): label every non-technical taxonomy and let the project decorate the order`.

### R-184 · 🟡 · ouvert (en attente de commit) · ouvert le 2026-09-25 — l'ordre de recherche n'est pas surchargeable par le projet

Rattaché à `R-180`/`R-181`, demandé par Louis (« la modularité reste valable »). L'ordre des
`searchableAttributes` était calculé dans `FacetedPostIndexable` ; un projet ne pouvait qu'y ajouter des champs
par `IndexAttributes::searchable()`, placés après le titre.

**Livré.** Contrat `Contracts\SearchableAttributes::all()` (liste complète, ordonnée), sur le modèle de
`ProductFacets::all()` ; défaut `DefaultSearchableAttributes` lié en **`scopedIf`** dans
`IndexingServiceProvider`, qui reprend l'ordre existant. Ses deux entrées — taxonomies indexées, WooCommerce
actif — sont des closures lues **à chaque appel** (`R-171` : le pont est construit avant WooCommerce).
`IndexedTaxonomies` (lié `scoped`, mémoïsé) remplace la résolution privée de `FacetedPostIndexable` et sert les
deux lecteurs, facettes et libellés, sans duplication ; `SearchedMeta::Sku` porte le chemin du SKU pour le rang
et pour la tolérance. `FacetedPostIndexable` ne fait plus que lire le contrat.

**Contrat public.** `IndexAttributes::searchable()` est **retiré** — une seule notion pour le rang. Ajouté par
`R-181`, jamais commité : aucun projet ne l'implémente (le projet de test ne lie pas `IndexAttributes`). `exactlyMatched()`
reste à `IndexAttributes` (raison dans `decisions.md`). Constructeurs changés : `FacetedPostIndexable` (4
paramètres) et `MeiliScoutBridge` (5 : il construit l'indexable à la demande, comme avant, pour ne rien résoudre
de MeiliScout avant son chargement) — deux classes internes, `final`.

**Vérifié à l'exécution.** `getIndexSettings()` du vrai indexable (`wp eval-file`, sans envoi), capturé avant
puis après : **identique à l'octet près** ; `searchableAttributes` égal à la liste lue dans le moteur (GET),
`disableOnAttributes` `["metas._sku"]`, `displayedAttributes` `["ID","card"]`.

**Tests.** `IndexSearchSettingsTest` (7) : ordre par défaut identique à l'actuel (attendu recalculé depuis
WordPress, sans valeur du projet de test), ordre sans WooCommerce sans aucun champ produit, **ordre lié par un projet
poussé tel quel** (instance liée au conteneur, retirée ensuite), aucun champ technique, taxonomies non visibles
exclues, SKU sans tolérance, liste vide sans plugin. `IndexAttributesTest` et `DeferredIndexAttributesTest`
allégés, `IndexFacetingTest` et `IndexPaginationTest` suivent le constructeur. `composer check` vert (Unit 321,
client 541/541, `build:check`) ; suite `Modules` **OK (575 tests, 1759 assertions)**.

**Doc.** `configuration.md` : section « Ordre de recherche » (défaut, règle du premier champ, exemple générique
de surcharge et sa liaison, `discovery:clear` + réindexation, `attributesToSearchOn` sous-ensemble) ; tableaux
des points d'extension et des réglages d'index ; `README.md` et `architecture.md`.

**Les cinq passes.** *Lisibilité* : un nom par concept — « searchable attributes » comme le réglage du moteur ;
`all()` comme les contrats voisins ; méthodes de 3 à 10 lignes, aucun booléen en paramètre. *Commentaires* : deux
lignes, `R-171` sur les closures et la raison de `SearchedMeta` hors de `ProductMeta`. *Performance* : taxonomies
résolues une fois par requête pour les deux lecteurs (deux fois auparavant si les deux avaient existé) ; une
closure et un `is_taxonomy_viewable()` par taxonomie à chaque `ensureIndexExists()`. *Sécurité* : la liste
définit la surface ciblable par la clé publique — la doc le rappelle ; un projet qui ajoute un champ technique
le rend ciblable, c'est à lui de le savoir. *Contexte* : sans WooCommerce, aucun champ produit (testé).

**Commit proposé.** `feat(indexing): let the project rank the searchable fields`.

### R-183 · ⚪ · ouvert · 2026-09-25 — une classe de test `KeepsTheIndexOut` lancée seule échoue avant WordPress

Relevé pendant `R-182`. `--filter ProductPriceProjectionTest` (fichier non touché) : **9 erreurs**,
`Call to undefined function add_filter()` dans `KeepsTheIndexOut::keepTheIndexOut()` — la méthode `#[Before]`
s'exécute avant que le `setUp()` de `Tests\TestCase` ait démarré l'application, quand aucun test précédent ne l'a
fait. Dans la suite complète l'application partagée est déjà là, d'où le vert. Aucun envoi au moteur n'en résulte
(le test s'arrête avant d'enregistrer quoi que ce soit), mais une classe ne peut pas se lancer seule. À traiter
comme un point à part.

### R-182 · 🟡 · ouvert (en attente de commit) · ouvert le 2026-09-25 — le pont MeiliScout porte six filtres et huit dépendances

Rattaché à `R-180`/`R-181`, refonte validée par Louis. `MeiliScoutBridge` portait six
`#[Filter('meiliscout/post/document')]` ; `addFacets` et `addLabels` lisaient et vérifiaient `terms` chacun et
appelaient chacun `TermAncestry::expand()` — deux passes de fusion et de dédoublonnage par document, la
mémoïsation des chaînes épargnant seulement la seconde remontée en base.

**Livré.** `PostDocument` (final readonly) construit les champs du module dans un ordre explicite : termes étendus
une fois → `facets` et `labels` (taxonomies visibles, `hasViewableTaxonomy` déplacé ici depuis le pont) ;
`excerpt`, `content` ; `card` puis `price` (s'il n'est pas vide) dans **un seul** passage en visiteur anonyme à
l'adresse de la boutique (deux auparavant). `MeiliScoutBridge` garde deux accroches — `addModuleFields()` et
`declareFacetAttributes()` — et trois dépendances (`PostDocument`, `IndexAttributes`, `EngineLimits`).
`PostDocument` est résolu par le conteneur ; `CardProjector` reste le contrat surchargeable qu'il reçoit.
Comportement gardé : `terms` absent → `facets` et `labels` vides ; `terms` illisible → ni l'un ni l'autre, le
reste projeté (testé). `TermAncestry` reste `final` (Louis, 2026-09-25) : pas de test qui compte les expansions — il
aurait fallu ouvrir la classe pour vérifier un détail d'implémentation ; l'appel unique se lit dans
`PostDocument` et le test d'égalité du document protège le résultat.

**Vérifié à l'exécution** (`wp eval-file`, `PostIndexable::formatForIndexing()` réel donc
`apply_filters('meiliscout/post/document')`, sans envoi) sur #176, #116 et #560 : sortie capturée avant la
refonte puis après — **identique à l'octet près** (mêmes clés, même ordre, mêmes valeurs). `discovery:clear`
lancé avant et après. Dernière tâche du moteur antérieure à la session de tests (réindexation de Louis, 14:28 UTC).

**Tests.** `PostDocumentTest` (7, remplace `SearchFieldsProjectionTest`) : libellés par taxonomie, taxonomie non
visible filtrée mais facettée, document sans termes, `terms` illisible, extrait et contenu en texte brut,
pas de `price` pour une carte sans prix, document identique par l'accroche
unique sur un produit réel (clés et ordre, facettes, libellés, contenu, carte, prix). `AnonymousIndexingTest` et
`ShownPriceProjectionTest` passent par `PostDocument::complete()`. Aucun appelant hors du module (thème,
plugin d'expéditions du projet : `grep`, 0). `composer check` vert (Unit 322, client 541/541, `build:check`) ; suite
`Modules` **OK (577 tests, 1766 assertions)**.

**Les cinq passes.** *Lisibilité* : méthodes de 3 à 10 lignes, un niveau chacune (`complete` n'énumère que des
groupes de champs) ; aucun booléen en paramètre. *Commentaires* : un commentaire de justification retiré avant
livraison (l'expansion est déjà décrite par `TermAncestry`). *Performance* : une expansion et un changement de
contexte (visiteur + adresse de taxe) par document au lieu de deux chacun. *Sécurité* : rien de nouveau, champs
inchangés. *Contexte* : sans WooCommerce, `price` absent comme avant.

**Commit proposé.** `refactor(indexing): build the module's document fields in one place`.

### R-181 · 🟡 · ouvert (en attente de commit et de réindexation) · ouvert le 2026-09-25 — étape « pertinence et index » de la recherche du site

Rattaché à `R-180`, étape 2 de [chantier-recherche.md](chantier-recherche.md) (« Pertinence et index »), avec
l'extrait de carte avancé de l'étape 3 à la demande. Ferme dans le code `R-27` et `R-160` ; tranche en
proposition « `card.title` dans `searchableAttributes` » (`decisions.md`, « En attente »). **Rien n'est commité,
rien n'est réindexé** : le moteur garde ses réglages et ses documents jusqu'à la réindexation que Louis lancera.

**Livré.**
- *Champs de document* (`MeiliScoutBridge`, filtre `meiliscout/post/document`) : `labels.<taxonomie>` — noms des
  termes, ancêtres compris, entités décodées, **taxonomies visibles seulement** (`is_taxonomy_viewable()` : ni
  `product_visibility`, ni `product_type`, ni `pa_contenance`) ; `content` — le seul `post_content`, en texte
  brut (`PostText::content()` : délimiteurs de blocs, balises, `<script>`/`<style>`, shortcodes enregistrés
  retirés, entités décodées, espaces Unicode repliés) ; `excerpt` — le seul `post_excerpt`, même traitement
  (`PostText::excerpt()`). Décisions de Louis en cours d'étape (2026-09-25) : le premier jet, `text`, portait
  extrait puis contenu ; il est devenu `content` seul, puis l'extrait brut `post_excerpt` de MeiliScout a été
  remplacé par ce champ nettoyé. `card.excerpt` (`ExcerptCardProjector`) : extrait de l'auteur, sinon début
  du contenu, borné par `excerpt_length` (55 mots par défaut, lu à chaque carte) ; posé sur toute carte **qui
  n'est pas un produit** (`WooCommerceCardProjector` délègue désormais les non-produits) : la carte produit est
  inchangée. Un article protégé par mot de passe n'a ni `content` ni `card.excerpt`.
- *Réglages* (`FacetedPostIndexable`, via `SearchableAttributes::all()` depuis `R-184`, et `IndexAttributes::exactlyMatched()`) :
  `searchableAttributes` = `post_title`, ce que le plugin déclare (`labels.product_brand`, `labels.product_cat`,
  `metas._sku`), les `labels.*` des autres taxonomies visibles, `excerpt`, `content` ;
  `typoTolerance.disableOnAttributes` =
  `["metas._sku"]` (vide sans WooCommerce, écrit vide plutôt qu'omis). `displayedAttributes` **inchangé**
  (`ID`, `card`) : `card.excerpt` voyage dans `card`, rien de plus n'est lisible par la clé publique.
- *`R-160`* : sur une recherche, `ProductListing::baseFilter()` exclut `exclude-from-search` **à la place de**
  `exclude-from-catalog`, comme `WC_Query::get_tax_query()` (`class-wc-query.php:929`) — un produit « résultats de
  recherche uniquement » est trouvé, un produit « boutique uniquement » ne l'est pas. Le plan disait « plus » ;
  la plateforme dit « à la place », d'où l'écart signalé à Louis.
- `TermGrouping` factorise ce que `FacetProjection` (slugs) et `LabelProjection` (noms) faisaient chacun.

**Réglages qui seraient poussés** (lus par `getIndexSettings()`, sans envoi) : `searchableAttributes` =
`post_title`, `labels.product_brand`, `labels.product_cat`, `metas._sku`, `labels.category`, `labels.post_tag`,
`labels.post_format`, `labels.post_kind`, `labels.product_selection`, `labels.product_highlight`, `labels.product_tag`,
`excerpt`, `content`. Avant réindexation, dans le moteur : `["*"]`, `disableOnAttributes: []` (lu en GET).

**Projection réelle, sans envoi** (appel direct du pont en `wp eval-file`) : article #176 — `labels`
`{category: [Visage], post_kind: [Article]}`, `content` de 2 353 caractères commençant par
« Introduction Lorem ipsum… », **aucune occurrence de `spacer`**, `card.excerpt` = l'extrait ; produit #116
(« Sérum visage ») — `labels.product_brand: [Acme]`, `product_cat: [Visage]`,
`product_selection: [Nouveautés, …]` (entité décodée), `content` vide (produit sans description longue), `excerpt` =
« Texture fluide, usage quotidien. », carte sans extrait ;
produit #560 — contenu HTML ramené à 1 026 caractères de texte, carte avec `price` et sans extrait.

**Conséquence de déploiement.** `AbstractSingleIndexer::ensureIndexExists()` repousse les réglages **à chaque
sauvegarde** : dès que ce code tourne, la première sauvegarde d'un article ou d'un produit écrit la nouvelle liste
cherchable, alors que les documents n'ont pas encore `labels` ni `content` — la recherche ne trouve plus que par le
titre, l'extrait, les marques et le SKU jusqu'à la réindexation complète. Réindexer aussitôt après le déploiement.

**Tests.** Unit : `PlainTextTest` (+6 : délimiteurs de blocs, frontière entre éléments, code embarqué, entités et
espaces, balise échappée gardée en texte), `LabelProjectionTest` (4), `IndexAttributesTest` (+2),
`DeferredIndexAttributesTest` (étendu). Feature : `IndexSearchSettingsTest` (7 : ordre, plugin juste après le
titre, **aucun champ technique ni adresse**, labels des taxonomies visibles seulement, SKU sans tolérance, liste
vide sans plugin), `PostTextTest` (10, dont le contenu seul et l'extrait seul), `ExcerptCardProjectorTest` (5, dont la carte produit inchangée),
`SearchFieldsProjectionTest` (5), `ProductSearchTest` (attente mise à jour). Aucun test n'enregistre de post :
`updatedAt` de l'index `posts` et dernière tâche du moteur identiques avant et après la suite.

**Vérifié.** `composer check` vert (Pint, Rector, Unit **322**, ESLint, types, client **541/541** — 97,84 %
lignes, 94,19 % branches —, `build:check`) ; suite `Modules` **OK (574 tests, 1752 assertions)** après le passage à `content` puis `excerpt`. Un premier
passage a échoué sur `PublishedAssetsTest` : copies publiées identiques (`cmp`) mais plus anciennes que les
sources après le changement de branche ; `module:publish MeiliFacets` les a rafraîchies, sans lien avec l'étape.

**Les cinq passes.** *Lisibilité* : un concept, un nom — « visible » pour `is_taxonomy_viewable()` dans les deux
fichiers (`hasViewableTaxonomy`, `$viewable`, d'abord nommés « public ») ; `TermGrouping` remplace deux boucles
jumelles ; pas de booléen en paramètre ; `summary()` prend un nombre de mots, pas un drapeau. *Commentaires* :
chaque ligne ajoutée dit une anomalie amont ou un contournement (`wp_trim_words()` qui retire les balises,
délimiteurs de blocs en commentaires, `<li>` accolés) ; un commentaire périmé corrigé
(`WordPressTermHierarchy::describe()`, qui citait `terms.name` cherchable). *Performance* : par document, une
expansion d'ancêtres déjà mémoïsée, un `is_taxonomy_viewable()` par terme, trois regex et un
`strip_shortcodes()` ; poids ajouté ≈ la taille du contenu nettoyé (2,4 Ko pour l'article le plus long mesuré).
*Sécurité* : trouvée et corrigée — le contenu d'un article protégé par mot de passe serait parti dans `content`
(ciblable) et dans `card.excerpt` (lisible par la clé publique) ; reste, hors étape, que la carte d'un brouillon
porte désormais un extrait (`R-28`, filtre de base réécrivable). *Contexte et i18n* : sans WooCommerce, pas de SKU
ni de `product_*` (listes vides, testé) ; aucune chaîne visible ; bornage par `wp_trim_words()`, qui compte en
caractères pour les langues qui le déclarent.

**Mesuré après la réindexation de Louis** (2026-09-25, lecture seule, version où l'extrait était encore
`post_excerpt` brut) : réglages poussés conformes — ordre, `displayedAttributes` = `ID`, `card`, SKU sans
tolérance ; `<domaine>` → 0, `srum` → 0, `sérum` → 2, `serom` → 2, `acme` → 5 ; `attributesToSearchOn:
["url"]` refusé par le moteur. `spacer` → 3 (#560, #555, #778) : par tolérance aux fautes sur « space » /
« spaces » du contenu, plus aucun balisage indexé — comportement normal. Surlignage : `["card.title"]` ne rend
plus de `_formatted`, `["card"]` et `["*"]` surlignent `card.title` ; l'étape 4 demandera `["card"]`. **Le champ
`excerpt` nettoyé est venu après : une nouvelle réindexation est nécessaire pour qu'il existe dans le moteur.**
`R-27` reste à fermer après elle (contrôle `excerpt` présent, `post_excerpt` hors de l'ensemble).

**Mesuré après la seconde réindexation de Louis** (2026-09-25, lecture seule, `excerpt` nettoyé présent) :
`excerpt` et `content` sans balise ; `<domaine>` → 0 ; `"spacer"` et `"strong"` en recherche exacte → 0 ;
`spacer` (3) et `strong` (4) sans guillemets ne remontent que par la tolérance aux fautes, dans `content`
seulement — aucun balisage n'est plus indexé.

**Laissé aux étapes suivantes.** `attributesToSearchOn` par usage : il appartient à `SearchableType::searchOn`
(étape 3), qui n'existe pas encore ; les sous-ensembles proposés sont corrigés dans le plan (`post_excerpt` y devient
`excerpt`). `R-159` (terme sans lettre ni chiffre) : placé à l'étape 2 par le plan mais hors du
périmètre demandé pour cette livraison — reste ouvert. Mesures sur les termes de référence : après réindexation.

**Messages de commit proposés.** `feat(indexing): project term labels, excerpt and content as plain text`,
`feat(indexing): rank searchable fields and match the sku exactly`, `feat(cards): give non-product cards an
excerpt`, `fix(listing): hide what WooCommerce hides from its search`, `docs(search): record the relevance step`.

### R-180 · 🟠 · ouvert · 2026-09-25 — recherche du site (lot 5)

Parapluie du chantier [chantier-recherche.md](chantier-recherche.md), branche `feat/site-search`. Rattachés :
`R-27`, `R-159`, `R-160`, `R-181` (étape « pertinence et index »), `R-182` (refonte du pont), `R-184` (ordre de recherche surchargeable), `R-185` (corrections de la revue de `fd595a3`), `R-186` (réglages repoussés à chaque sauvegarde), `R-187` (étape 3a, déclaration des types), `R-188` (étape 3b, racine, réglages, publication partagée), `R-189` (étape 4, extractions partagées), `R-190` (étape 4, client de recherche), `R-191` (étape 5, panneau et sections), `R-192` (étape 7, mouvement et reprise du style), `R-193` (résultats pendant la frappe), `R-194` (composants rangés par racine), `R-195` (étape 8, habillage, verrou mobile, disposition libre), `R-196` (étape 6, accessibilité), `R-197` (ouverture saccadée en desktop), `R-198` (hauteur du panneau quand les résultats diminuent), `R-201` (facette de recherche du listing), `R-202` (nettoyage des feuilles) ; `R-29` à compléter (clé limitée à `posts`).

### R-179 · 🟡 · ouvert (en attente de commit) · ouvert le 2026-09-25 — étape 7 : panneaux desktop, pastilles actives et grille occupée

Rattaché à `R-48`, étape 7 de [chantier-filtres.md](chantier-filtres.md) (ANIM-3, ANIM-9, ANIM-10 ;
ANIM-13 posé en question). Reprend le reste de `R-176` (keyframes d'ANIM-3).

**Commits.** ANIM-3 `b02e098`, ANIM-9 `d325a58`. Le reste est **dans l'arbre, non commité** (consigne de
Louis en cours de lot) : ANIM-10, la refonte demandée ensuite (entrée commune, grille occupée dans sa classe,
renommages, puis le découpage ci-dessous) et ces docs. Messages proposés : `feat(results): dim the grid while a
slow search runs`, `refactor(motion): share one entrance across badge, pills and sections`,
`refactor(client): give input source and new pills their own classes`, `docs(filters): close the animation step`.

**Livré.**
- *ANIM-3* : l'entrée du panneau flottant passe du WAAPI de `PanelMotion::pop()` à une transition CSS
  (`@starting-style` sous `48em`, état `[hidden]` = `translateY(-4px) scale(0.97)`), donc interruptible et
  surchargeable par le thème ; 180/120 ms, `--meili-ease`. `hide()` ne termine plus que les animations scriptées
  (une transition est retournée par l'état suivant). Un clic clavier (`detail === 0`) sur la pill ouvre et ferme
  un panneau flottant sans animation, comme Échap, Tab et la pill voisine.
- *ANIM-9* : `shared/entrance.ts` (`Entrance`, départ + jetons en paramètre) remplace `CountEntry` et l'entrée
  des sections de `PanelMotion` ; `ActiveValuesView` ne fait entrer que les pastilles dont l'identité
  (`kind`, `name`, `value`) n'était pas dans la liste avant le redessin.
- *ANIM-10* : `Listing` annonce `searching` et `settled` autour des recherches en vol (compteur : une recherche
  dépassée ou une réponse périmée ne libère pas la grille tant qu'une autre est dehors) ; `results/busy-grid.ts`
  pose et retire `aria-busy` ; la feuille atténue après `--meili-duration-busy-delay`, jamais derrière un tiroir
  qui couvre la page. Aucune région `aria-live` ajoutée.

**Tests.** TS : ouverture souris sans WAAPI ni coupure, clavier instantané dans les deux sens, transition non
terminée par `hide()`, feuille (keyframes, `@starting-style`, variante réduite) ; nouvelle pastille seule,
serveur et prix redessiné immobiles, retrait sans animation et focus rendu, mouvement réduit (mutation : filtre
d'identité retiré → 4 échecs) ; `searching`/`settled` sur réponse, refus, recherche dépassée, réponse périmée,
`submit` sans recherche ; `aria-busy` par `BusyGrid` ; feuille (délai, retour sans délai, tiroir). Client
**533/533** (97,82 % lignes, 94,15 % branches), `composer check` vert (Unit 310), suite `Modules` **535** verte.

**Mesuré dans Playwright** (Chrome, `submit` puis `immediate`, images `/content/uploads` interceptées, cache
désactivé, caches WP Rocket vidés — un premier vidage raté par un glob zsh servait une feuille périmée, relevé et
refait —, `config/meilifacets.php` restauré, `cmp` = 0 ; 0 erreur console hors images) :
- **1440 px** : ouverture souris 2 `CSSTransition` 180 ms `cubic-bezier(0.23, 1, 0.32, 1)`, fermeture 3 à 120 ms ;
  Entrée, Échap, Tab, pill voisine : 0 animation ; mouvement réduit : `opacity` seule, `transform: none` à
  chaque image. Pastille nouvelle : 1 `Animation` 150 ms `scale(0.95)`, les autres 0, après retrait 0, focus sur
  la pastille suivante ; mouvement réduit : `opacity` seule. Grille, latence 500 ms : `aria-busy` à ~25 ms,
  opacité 1 à 120 ms, 0,55 à 300 ms, rendue 1 en ~130 ms après la réponse ; réponse rapide : opacité jamais < 1 ;
  deux recherches chevauchées : une seule libération ;
- **393 px** : sections du tiroir inchangées (WAAPI 270 ms `scale(0.96)`) ; tiroir ouvert ou sortant pendant une
  recherche de 600–800 ms : opacité 1, atténuée seulement une fois la page découverte ;
- aucune long task ni long-animation-frame ; image la plus longue 9,3 ms (16,6 ms dans une mesure).

**Écarts.** Sortie des sections au clavier toujours animée (hors périmètre ANIM-3). Aucun sélecteur d'état CSS
partagé (`[data-entering]`) : les entrées scriptées sont en WAAPI, celle des panneaux en `@starting-style`, il
n'y a donc pas de règle d'entrée par brique à regrouper. `getKeyframes()` sérialise la translation à
`-8px` alors que la matrice mesurée confirme −4 px (bizarrerie de Chrome, sans effet).

**Les cinq passes.** *Lisibilité* : noms relus (`Entrance.play`, `BusyGrid.watch`, `#markSearching`/
`#markSettled`, `#isKeyboardOnFloatingPanel`, `#closeFromClick`/`#openFromClick`, `NewPills.among`,
`InputSource.isKeyboard`/`isPointerDown`, `#scriptedAnimations`) ; pas de
booléen en paramètre ; fichiers sous les limites ESLint. *Commentaires* : une ligne de pourquoi technique par
méthode, rien sur l'historique ; aucun commentaire CSS. *Performance* : une lecture de style par pastille
nouvelle, aucune boucle par image ; `:has()` sur `[data-listing]` limité à l'état busy. *Sécurité* : aucune
donnée de l'URL. *Contexte et i18n* : aucune chaîne, rien de WooCommerce.

**Découpage (2026-09-25, demandé par Louis ; comportement inchangé).**
- `shared/input-source.ts` (`InputSource`) : d'où vient une interaction. `InputSource.isKeyboard(click)`
  (`detail === 0`) et `isPointerDown()` (d'un `pointerdown` à son clic, remis à faux par une touche) sortent de
  `DisclosureGroup`, qui ne garde que les panneaux (236 → 234 lignes : `#clicked` aiguille désormais vers
  `#closeFromClick`/`#openFromClick`). `ListingBinding#reveal` lisait aussi `detail` : il passe par
  `InputSource.isKeyboard`. Le tiroir n'a pas cette détection (son geste suit ses propres pointeurs).
- `listing/new-pills.ts` (`NewPills`) : quelles pastilles un redessin a amenées, reconnues au filtre qu'elles
  retirent (`data-kind`, `name`, `value`, `KIND` exporté par `active-value-list.ts`). `ActiveValuesView`
  (170 → 157) ne fait plus que dessiner, et joue `Entrance` sur `newPills.among(…)`.
- `BusyGrid.follow` → `watch` ; le champ `#busyGrid` de `ListingBinding` disparaît
  (`new BusyGrid(this.#contract).watch(this.#listing)` dans `start()`, `#root` remplacé par `#contract`).
- `EntranceMotion` → `EntranceStyle` (d'où l'élément arrive **et** ses jetons de durée/courbe : `EntranceFrom`
  n'en aurait nommé que la moitié) ; `#entry` d'`ActiveCountView` → `#entrance`, comme partout ailleurs.
- Gardés, relus : `#scriptedAnimations` (oppose exactement `animate()` aux transitions de la feuille),
  `searching`/`settled` (paire d'événements symétrique, `settled` couvre réponse, refus et dépassement),
  `--meili-duration-busy-delay`, `--meili-duration-settle`, `--meili-busy-opacity` (préfixes du système de jetons
  existant, chacun ne désigne qu'une valeur).

Tests ajoutés : `input-source.test.ts` (4), `new-pills.test.ts` (4). Client **541/541** (97,84 % lignes,
94,19 % branches), `composer check` vert (Unit 310), suite `Modules` 535 verte (un échec isolé sur quatre
passages, non reproduit, sans lien : aucun PHP touché). Recette Playwright rejouée à 1440 et 393 px dans les deux
modes, `cmp` = 0 : mêmes valeurs qu'au-dessus (2×180 / 3×120 ms, 0 au clavier, Échap, pill voisine ; pastille
nouvelle seule à 150 ms ; grille 1 à 125 ms, 0,55 à 301 ms, 1 à ~135 ms de la réponse ; réponse rapide jamais
< 1 ; sections du tiroir WAAPI 270 ms à la souris comme au clavier). En `submit` à 393 px, « Appliquer » ferme le
tiroir : l'atténuation ne commence qu'à ~410 ms, une fois la page découverte.

### R-178 · 🟡 · **fermé le 2026-09-25** · ouvert le 2026-09-25 — audit de la branche `feat/filter-bar` : duplications, fixtures et docs à reprendre

Rattaché à `R-48`. Audit de la branche après la livraison des étapes 4 et 5 (`d1b3d70`), découpé en
cinq lots appliqués dans l'ordre, un commit par lot. Comportement inchangé partout, sauf le bogue du
point A1.

| Lot | Objet | État |
| --- | --- | --- |
| A | duplications et bogues latents | **fait** — `b1cc621` |
| B | tests : fixtures alignées sur les vues, aides partagées | **fait** — `0a4ba1d` |
| C | crochets `drawer-sheet`/`drawer-footer`/rangée du tri, Déméter, `Apply` unique, renommages | **fait** — `2be5b05`, `0ea40d8`, `d028e8b`, ce commit ; thème `ad9fa12` |
| D | feuille de style chargée sous un listing seulement | **fait** — ce commit |
| E | documentation : architecture, registre, ligne vide du thème | **fait** — ce commit |
| F | le libellé du tri redevient une seule phrase traduisible | **fait** — `fix(sort)` du 2026-09-25 |

**Lot F** (2026-09-25, validé par Louis). Le lot C avait coupé la phrase en deux clés concaténées
(`Sort by`, `: :choice`) : une langue ne pouvait plus réordonner ni reponctuer, et `: :choice` n'avait
pas de sens seul. Retour à **une clé**, `Sort by: :choice` (fr `Trier par\u00a0: :choice`), `: :choice`
supprimée. Le composant `Sort` traduit la phrase sans remplacement, `:choice` y reste intact,
et la découpe autour du placeholder lui-même, comme le client (un marqueur privé U+E000 posé d'abord
a été retiré en revue par Louis : indirection inutile) — ni `str_starts_with` ni `substr` sur la
traduction ; texte avant et après la valeur libres, vides ou non. `SortSummary` n'est plus qu'un objet
de valeur (`of()` et son test unitaire retirés). Pour garder « Trier par » seul dans le tiroir sans le
dériver de la phrase, le libellé (clé `Sort by`) reste et la phrase entière va dans le slot :
`Disclosure::withSilentLabel()` pose `aria-hidden` sur le libellé, que la feuille masque à partir de
`48em`. Client : motif `sortPattern` dans la description, `SortRadios` réécrit la phrase par
`replaceChildren(lead, chosen, trail)`, valeur en `textContent`. Tests : Feature (espace insécable,
catalogue `xx` `:choice — sort` rendu valeur en tête, `aria-hidden`, `sortPattern`), TS (motif valeur
en tête, libellé `<b>Price</b>` inséré en texte, libellé masqué à 1440 px). Recette Playwright
`submit` et `immediate` : 1440 px « Trier par : Pertinence » → « Prix croissant » (`?sort=price_asc`),
rendu serveur de `?sort=price_desc`, retour arrière → « Pertinence », avant → « Prix croissant » ;
393 px, section « Trier par » seule, nom « Trier par : Pertinence », phrase `clip-path: inset(50%)` ;
U+00A0 dans le DOM ; 0 erreur console.

**Lot A.** *Bogue* : `CountEntry` lisait `--meili-duration-fade` par un `parseFloat` nu — un thème
écrivant `0.15s` obtenait 0,15 ms. Lecture partagée par `shared/css-timing.ts` (`CssTiming` : durée en
ms, `s` ou `ms` ; courbe ; `prefers-reduced-motion`), utilisée par `CountEntry`, `PanelMotion` et le
tiroir (`REDUCED_MOTION` déclaré une fois au lieu de trois). Test qui échouait avant (0,15 ≠ 150).
Règle « vide à zéro » en un objet `View\Badge` (`Apply`, `DrawerOpener`, `Disclosure`) et son jumeau
`shared/badge.ts`. Le tri n'a plus de badge : `Disclosure` accepte l'absence, `ElementId::sortSelectedCount()`,
l'identifiant et l'`aria-describedby` vers un nœud toujours vide disparaissent. `listing/focus-landing.ts`
porte le repli du focus sur la racine (`ActiveValuesView`, `ResetFocus`). `ActiveValuePatterns` : les
quatre motifs traduits une fois par `ActiveValueList::of()`, plus quatre fois par pastille. Six jetons
de durée (`--meili-duration-press`, `-press-pill`, `-chevron`, `-list`, `-pop-in`, `-pop-out`) : plus
aucune durée en dur, ANIM-2 cochée. Quatre règles de focus fusionnées ; celle de la pastille reste à
part (un `:has()` non reconnu invaliderait toute la liste). `EXPANDED`/`INSTANT` dans
`shared/attributes.ts`, `Contract.window` au lieu de deux `#window()`, `export` inutilisés retirés.
**Non fait** : factoriser les listes `transition` recopiées — une propriété composée figerait les
durées à `[data-listing]` (un thème qui surcharge plus bas ne serait plus suivi) et casserait le test
de cohésion des survols.

**Lot B.** Fixture du tiroir alignée sur `drawer.blade.php` + `reset-icon`/`apply.blade.php` (pied
réel, `data-only` en `immediate`, `active-count`) ; le morph et `data-only` vérifiés sur le DOM
(`R-108`, en partie : le pont PHP → fixture reste différé). Fixture du tri alignée sur
`sort-radios.blade.php` + `toggle.blade.php`. `hooked()` (6 copies) → trait `FindsHooks` ; « deux
valeurs du catalogue » → trait `HoldsCatalogueValues`, qui saute proprement si le catalogue n'a pas les
données. `described()` porte les valeurs par défaut (107 lignes retirées dans 13 fichiers). Délais
réels remplacés : horloge des événements dans `drawer-gesture.test.ts`, trame puis tâche explicites
dans `drawer.test.ts`. Mutations vérifiées : flush de `HeldPaint` à 30 ms, fenêtre de vitesse à
1 000 ms, `display: none` de `data-only` retiré, classe du pied changée, badge TS à « 0 » — chacune
fait échouer un test réécrit.

**Lot C** (2026-09-25, accord de Louis pour les crochets et le HTML de la colonne). *C1* : crochets
additifs `drawer-sheet`, `drawer-footer` et `sort-choice-row` (`sort-choice` reste la radio : la
rangée est un autre nœud, et poser `input` dans le tri l'aurait fait lire comme une facette par
`ListingBinding`). Hors de `RULES`, `Contract::VERSION` à 1. Les 24 sélecteurs de classe du tiroir
dans la feuille et les 2 du thème visent les crochets (même spécificité) ; `Drawer`/`DrawerGesture`
trouvent la feuille par son crochet ; `SortRadios` masque la rangée par son crochet et lit le libellé
dans `data-label`. *C2* : `ResolvedListing::selectedIn()`, `priceFilterCount()`, `askedPrice()` ;
plus de `->state()->` dans les composants (`ListingDescription` sérialise l'état entier, légitime).
*C3* : le groupe rend `<x-meilifacets::apply shape="block">` (`ApplyShape`, décision « Un seul
« Appliquer » ») ; `:has(active-count)` → `[data-shape="pill"]`. Colonne mesurée avant/après
(page temporaire, `submit`, 393 et 1440 px, avec et sans filtre) : « Appliquer les filtres », 48 px de
haut, pleine largeur (346/1345 px), blanc sur noir, rayon 3,5 px, padding 11,9 px, 14 px, `gap: normal`,
transitions fond/bordure — **0 écart** sur 16 propriétés. *C4* : `ActiveCountView`,
`ToggleBadgeView` ; pastille `parameter` des deux côtés et `kind` explicite (`ActiveValueKind`,
`data-kind`, jumeaux dans `ContractParityTest`) ; `Sort::shouldRender()`,
`Reset::hasNothingToClear()` ; trait `ComponentVariant::fromAttribute()` (5 copies : `Sort`, `Reset`,
`Drawer`, `Apply`, `Card`) ; `SortSummary::of()` pure, deux clés (`Sort by`, `: :choice` — l'ancienne
`Sort by: :choice` n'est plus lue) ; `SummaryBinding` extrait (`listing-binding.ts` 242 → 227 lignes,
18 → 14 collaborateurs). Recette Playwright 1440/393, `immediate` et `submit` : tiroir (sheet 550 px,
pied, poubelle, « Appliquer 1 » en pill 265 px, fermeture ; `submit` : URL posée au clic seulement),
focus sur `apply` après la poubelle, pills (badge 2), tri « Trier par : Prix décroissant »
(`?sort=price_desc`), pastilles `term`/`price` et retrait du prix, colonne (`?categorie=cheveux` au clic
d'« Appliquer les filtres ») ; 0 erreur console. **Relevé, non traité** : `archive-product.blade.php`
du thème écrit le seuil en `md:` Tailwind (48rem), pas en `48em` ; égaux à 16 px de base.

**Lot D** (2026-09-25, validé par Louis). `Stylesheet::enqueue()` mettait `meilifacets.css` en file sur
toutes les pages, bloquant le rendu : 31,9 Ko minifiés / 5,7 Ko gzip pour rien sur l'accueil. Désormais
`Stylesheet::register()` (`wp_enqueue_scripts`) n'inscrit que la poignée, et `<x-meilifacets::listing>`
appelle `Stylesheet::require()` à côté de `ListingScript::require()`. Ordre mesuré (instrumentation
temporaire du composant) : construit avec `did_action('wp_head') = 0`, `did_action('wp_enqueue_scripts') = 0`,
sous 5 à 6 tampons — la vue `@extends` rend ses sections avant le `<head>`. Piste (a) écartée :
`ListingPage` devine (`is_archive() || is_search()`), la vue seule sait ; (c) gardée en repli : un listing rendu
après `wp_head` reçoit `wp_print_styles()` devant lui, une seule fois. Décision « La feuille du module ne se
charge que sous un listing » ; `configuration.md`, « Feuille de style du module ». Tests Feature
`StylesheetTest` (absente sans listing ; dans le `<head>` d'un gabarit à sections ; une seule impression après
`wp_head` ; `wp_dequeue_style` ; `wp_deregister_style` après `wp_head`) — retirer l'appel du composant ou la
branche `wp_head` fait échouer un test. Playwright, 393 et 1440 px, cache désactivé, caches WP Rocket vidés,
avant → après : `/` et `/faq` 1 requête `meilifacets.css` → 0 ; `/boutique` et `/categorie-produit/visage` :
1 `<link>` dans le `<head>` avant et après, feuille appliquée dès la première frame du listing (tiroir
`position: fixed` à 393, `static` à 1440, liste des pills `list-style: none`). CLS `/boutique` 0,0005 / 0,0031
avant et après ; catégorie 1440 : 0,0015 → 0,003 (trois essais), décalage porté par le fil d'Ariane de
l'en-tête du thème, pas par le listing.

---

**Fermé le 2026-09-25** : lots A (`b1cc621`), B (`0a4ba1d`), C (`2be5b05`, `0ea40d8`, `d028e8b`,
`00758a2` ; thème `ad9fa12`), D (`9692266`), E (`bfc0279` ; thème `cbef7ce`) et F (`90dabac`) livrés. Relecture finale :
le paramètre booléen de `Disclosure` (contraire à `CLAUDE.md` § 4) devient l'enum `LabelReading`
(`Aloud`/`Silent`), et le marqueur U+E000 du tri est retiré au profit de la découpe sur `:choice`.
Reste hors audit : `FilterCatalogue` à extraire de `ResolvedListing` (différé, sans urgence).

### R-177 · 🟡 · **fermé le 2026-09-25** (étape 4d-2, `d1b3d70`, verrou `e26dc56`) · ouvert le 2026-09-25 — un « Voir plus » déplié dans un panneau le reste à la réouverture

Rattaché à `R-48`, étape 4d-2 de [chantier-filtres.md](chantier-filtres.md) ; suite de `R-46`/`R-82`–`R-86`
(repli tenu par le client). `FacetsView::#unfolded` gardait une facette dépliée après la fermeture de
son panneau ou de sa section, et le tiroir, qui referme ses sections « a posteriori », les rouvrait
dépliées.

**Corrigé.** `FacetsView::refold()`, déjà appelé par `DisclosureGroup` une fois un panneau hors de vue
(clic, Échap, Tab, pill voisine, clic extérieur, sortie du tiroir par `collapseWithin()`), retire du
dépli toute facette dont le déclencheur est fermé (`#isPanelClosed()`, `#toggleOf()`, que
`#isPanelOpen()` réutilise). Une facette sans panneau (colonne) garde son dépli : rien ne la ferme.
Aucun CSS, aucune vue, aucun crochet ; `Contract::VERSION` intact.

**Tests.** TS `facets-view.test.ts` : repli rendu après fermeture (libellé « more » revenu,
`aria-expanded="false"`), dépli gardé quand un autre panneau se ferme, dépli gardé sans panneau ;
`disclosure-group.test.ts` « « Show more » inside a panel » (`ListingBinding` réel, feuille chargée) :
dépli dans le panneau flottant sans le fermer, focus resté sur le bouton dans les deux sens, repli après
Échap et après un second clic du déclencheur. Mutation (ancien `facets-view.ts`) : 3 échecs. Client
495/495, `facets-view.ts` 98,47 % lignes ; `composer check` vert (Unit 301) ; suite `Modules`
**517** verte.

**Mesuré dans Playwright** (`submit` puis `immediate`, `visible` abaissé le temps de la recette dans
`ShopFacets` — Marque 3, Contenance 4 — puis rendu, `cmp` = 0 ; config restaurée, `cmp` = 0 ;
images interceptées ; 0 erreur console hors images), mêmes relevés dans les deux modes :
- **1440 px** : Marque, panneau 288 px, 3 → 6 valeurs, hauteur 154 → 250, « Voir plus » → « Voir
  moins » ; Contenance (pastilles), 323 → **448 px** (`--meili-panel-max`), 4 → 10 valeurs sur deux
  rangées, bord droit 961 < 1425 ; aucun débordement (`scrollWidth` = `clientWidth`). Entrée au
  clavier dans les deux sens : focus toujours sur le bouton. Après Échap ou clic extérieur, réouverture
  repliée ;
- **393 px, tiroir** : Marque, sheet 690 → 744 → 771 → 780 px en ~150 ms (plafond `100dvh − 4.5rem`,
  le corps défile au-delà ; hauteur en ligne 786 = contenu), retour à 690 au repli ; Contenance, sheet
  au plafond, contenu 886 → 998 ; focus sur le bouton après chaque bascule. Tiroir fermé par ✕ puis
  rouvert, et section refermée puis rouverte : Marque repliée (3 valeurs, « Voir plus »). Les 6 px de
  `scrollWidth` en plus sur la section Marque viennent des marges négatives des rangées (`-0.4em`),
  antérieures ; le corps ne défile pas en largeur.

**Les cinq passes.** *Lisibilité* : un niveau d'abstraction dans `refold()` ; `#toggleOf()` factorise la
lecture que faisait `#isPanelOpen()` ; noms comparés (`refold` dit déjà « replier »). *Commentaires* :
la ligne de `refold()` complétée, un commentaire ajouté puis retiré (justifiait un choix). *Performance* :
une boucle sur les facettes dépliées (≤ nombre de facettes) par fermeture de panneau. *Sécurité* :
aucune donnée de l'URL. *Contexte* : aucune chaîne, rien de WooCommerce.

### R-176 · 🟠 · **fermé le 2026-09-25** (`40822fd`, `d62e8d9`) · ouvert le 2026-09-25 — revue des animations : le glisser ne démarre jamais au doigt, Tab entre dans un tiroir qui sort

Rattaché à `R-48` (revue des animations des étapes 5a/5b, corrections validées par Louis).

**Bloquants.**
- `DrawerGesture::#heldElsewhere()` testait `hasPointerCapture()` : au toucher, Chrome capture
  implicitement le pointeur sur la cible du `pointerdown`, le test était toujours vrai et **le glisser
  ne démarrait jamais au doigt** (mesuré à la souris seulement dans `R-173`). Remplacé par une
  exclusion par sélecteur, indépendante du type de pointeur : champs, `price-track`, `price-handle`,
  ✕ de l'en-tête (`button[data-meili="drawer-close"]` ; la poignée, qui porte le même crochet, reste
  saisissable).
- Pendant les 250 ms de sortie, le tiroir restait tabulable. `Drawer::#close()` pose `inert` dès
  `data-closing`, après avoir rendu le focus à l'ouvreur ; retiré à l'ouverture, à la fin de la sortie
  et au passage du seuil.

**À corriger / mineurs.** ✕ : plus de `scale(0.95)` au `:focus-visible`, `:active` `scale(0.75)`
gardé. Panneaux flottants : entrée 180 ms (`--meili-duration-panel-in`, `PanelMotion::pop()`), sortie
120 ms (`--meili-duration-panel-out`) ; Échap, Tab qui quitte le panneau et passage d'une pill à
l'autre sans animation (`data-instant` posé puis retiré par `DisclosureGroup`) ; un appui souris
laisse décider son clic (drapeau « pointeur enfoncé »). Mouvement réduit : sortie de section en
`--meili-duration-fade`, liste du tri en fondu 160 ms. Décisions dans `decisions.md`.

**Tests.** TS : touche capturée implicitement sur l'en-tête (échoue avec l'ancien test, vérifié par
mutation), poignée au doigt, exclusions en `touch` et `mouse` ; tiroir `inert` en sortie (✕ et Échap),
retiré à la fin et à la réouverture, focus sur l'ouvreur ; 180 ms à l'ouverture souris, sorties
instantanées (Échap, Tab, pill voisine), appui ailleurs → sortie animée ; `pop()` en ms et en s ;
feuille (durées, `data-instant` après le bloc réduit, ✕, mouvement réduit). Client **484/484**,
couverture 97,57 % lignes / 93,84 % branches ; `composer check` vert (Unit 301) ; suite `Modules`
**512** verte.

**Mesuré dans Playwright** (Chrome, `immediate` puis `submit`, `config/meilifacets.php` restauré,
`cmp` = 0, images interceptées, cache désactivé, 0 erreur console hors images) :
- **tactile 393 × 852** (CDP `Input.dispatchTouchEvent`) : depuis la poignée et depuis le titre, la
  feuille suit le doigt (`translateY(50px)` à mi-geste, 80 px en fin) ; 80 px lent → reste ouvert ;
  260 px (poignée et titre) → ferme ; flick de 60 px en ~25 ms → ferme ; corps défilé à 200 → 24/37,
  `transform` inchangé ;
- **✕ puis Tab** (Entrée sur ✕, six Tab immédiats) : jamais dans le tiroir, jamais `body` ; tiroir
  `inert` pendant la sortie, plus après ;
- **✕** : focus clavier `transform: none` ; appui souris et tap tactile (`synthesizeTapGesture`)
  `scale(0.75)` ;
- **1440 px** : ouverture souris 180 ms, fermeture souris 120 ms (3 transitions), clic dehors 120 ms ;
  Échap, Shift+Tab hors du panneau, pill voisine : **0 animation** sur l'un et l'autre panneau, aucun
  `data-instant` restant ;
- **mouvement réduit** (393 px) : sortie de section 150 ms ; liste du tri : entrée `opacity` 160 ms,
  sortie `opacity` + `display` 160 ms.

**Reste** : keyframes d'ANIM-3 (`translateY(-4px) scale(0.97)`) non reprises — **repris dans `R-179`** (`b02e098`).

### R-175 · 🟡 · **fermé le 2026-09-25** (étape 5b, `860d58c`, `d62e8d9`) · ouvert le 2026-09-25 — le tiroir n'a pas de pied « Annuler / Appliquer (X) »

Rattaché à `R-48`, étape 5b de [chantier-filtres.md](chantier-filtres.md), avancée dans le même lot
que la 5a à la demande de Louis (`D-03` enfreint à sa demande, noté ici).

**Livré.** `<x-meilifacets::apply>` autonome (« Apply »/« Appliquer » + pastille `active-count`, qui
décrit le bouton), rendu en `submit` ou avec `visible-in-drawer`. En `immediate`, il ne cherche rien de plus
(`ListingBinding::#applied()`, mutation tuée). Dans le tiroir modal, il le ferme. `<x-meilifacets::facets
:with-apply="false">` cède le bouton du groupe ; sans l'attribut, le rendu est identique à l'octet
(test). `<x-meilifacets::reset icon />` : vue à part `reset-icon.blade.php`, bouton rond et carré
nommé par `aria-label`, `images/trash.svg` en `<img>` décoratif (`loading="lazy"`,
`fetchpriority="low"`), slot `icon` pour le remplacer, slot vide pour le retirer ; la vue texte est
inchangée. Classe du pied renommée `meilifacetsDrawerFooter` (une classe, pas un crochet : aucun
changement de contrat).

**Mesuré** (393 px) : pied sur une ligne, reset 48 × 48, rond, « Appliquer » 283 × 48, pleine
largeur restante. En `submit` : cocher ne cherche rien (0 requête), X = 2 sur l'ouvreur et sur
« Appliquer », puis « Appliquer » fait 1 requête, ferme le tiroir et rend le focus à l'ouvreur. En
`immediate` : « Appliquer » ferme le tiroir, 0 requête (réseau filtré sur l'origine du moteur).
1440 px : « Appliquer » en fin de rangée, sur la même ligne que les pills, reset du pied masqué par
le thème.

**Suite du 2026-09-25** (Louis) :
- **Morph poubelle ↔ « Appliquer »** (393 px, rAF) : cocher → poubelle `display` posé, opacité 0 →
  1, `scale(0.9)` → 1 et flou 2 → 0 px en ~200 ms ; « Appliquer » 329 → 265 px en ~250–290 ms. Vider →
  poubelle à 0 et `display: none` en ~155 ms, « Appliquer » 265 → 329 px en ~245 ms. Images ≤ 16,7 ms en
  `submit` ; mouvement réduit : largeur instantanée, poubelle en fondu seul.
- **« Appliquer » sheet-only en `immediate`** : `data-only="sheet"` (`Apply::onlyInSheet()`), masqué
  hors du sheet. 1024/1280/1440 px : `immediate` → `display: none` ; `submit` → visible (115 px), et
  « Appliquer » referme le panneau flottant ouvert (`?marque=globex`, 0 panneau ouvert). Test Feature.
- « Tout effacer » en mots : pastille (rayon 999px, padding `--meili-control-inline`).
- **Finitions** (Louis, 2026-09-25) : `<x-meilifacets::reset icon />` devient `shape="icon"`
  (`ResetShape::Icon`, attribut `icon` retiré, slot `icon` inchangé) ; après « Tout effacer » hors
  tiroir, le focus va au premier « Appliquer » visible du listing (rangée desktop en `submit`), sinon
  à la racine ; une seule durée de survol, `--meili-duration-hover` (150 ms), pour toutes les
  transitions de survol (tests `stylesheet.test.ts` « the duration of a hover »).

**Reste pour l'étape 6** : UX-4 (nom « Appliquer 2 filtres »).

**Finitions du 2026-09-25** (Louis, commitées dans `860d58c`) :
- **`visible-in-drawer`** remplace l'attribut booléen d'`apply` (`Apply::$visibleInDrawer`, vue, tests,
  docs, composition du thème). `submit` : visible partout ; `immediate` : seulement dans le tiroir en
  sheet, où il ferme sans rechercher ; absent en desktop. Documenté sur la classe et dans
  `configuration.md`.
- **Focus après la poubelle** (`ResetFocus`) : « Appliquer » du même tiroir, sinon titre du sheet,
  sinon racine du listing (`tabindex="-1"` posé alors), jamais `body` ; un focus déjà ailleurs ne bouge
  pas. Mesuré (393 px, deux modes) : souris et Entrée → `apply` du tiroir. 1440 px, « Tout effacer »
  en pastille → racine du listing (aucun « Appliquer » voisin). 4 tests TS.
- **`reset shape="pill"`** (`ResetShape`, `data-shape="pill"`) : le lot avait **remplacé** le dessin
  du bouton texte ; il revient à celui de `25a5aa3`, la pastille devient une variante. Audit des
  briques de la colonne (même gabarit que `25a5aa3`, feuille actuelle contre feuille `25a5aa3`
  substituée dans Playwright, 17 propriétés calculées sur `reset`, `active-value`, `sort-trigger`,
  pastille cochée et non cochée, rangée, `more`, `apply`, `page`, `facet`, `facets`, hauteurs exclues) :
  écarts trouvés et rendus — `reset` rayon 999px/padding 24 px, `active-value` padding 24 px et gap
  `0.5rem`, `sort-trigger` padding 24 px, pastille bordure `currentColor`, cochée inversée, appui
  `scale(0.96)`, `apply` appui `scale(0.97)` et `transform` en transition, rangées et « Voir plus » à
  150 ms. Après correction : **0 écart** à 393 et 1440 px, `immediate` et `submit`. Les styles neufs
  vivent sous une variante (`collapsible`, `shape`, composant `apply`) ; le thème repose les 24 px de
  ses pastilles actives.


### R-174 · 🟡 · **fermé le 2026-09-25** (étape 4b, `7b971d8`) · ouvert le 2026-09-24 — le tri ne se présente pas comme une section

Rattaché à `R-48`, étape 4b (C-4). Demandé pendant la 5a par Louis : la liste déroulante détonnait
au milieu des sections du tiroir.

**Livré.** Enum `SortWidget` (`listbox` par défaut, `radios`). Nouveaux crochets `sort-choices` et
`sort-choice`, autorisés au § 5 de l'architecture, additifs, `Contract::VERSION` inchangé. Règles de
contrat : `sort-choices` exige `sort-choice`, et un `panel` quand il tient un `toggle`. Vue
`sort-radios.blade.php` avec le même `toggle.blade.php` et la même `Disclosure`. Badge jamais
rempli : un tri est un ordre, pas un filtre. Client `sort/sort-radios.ts` : choisir trie tout de
suite dans les deux modes (`D-10`), et une option filtrante qui ne garderait rien est masquée, comme
dans la listbox. La garde `placeSort()` couvre les deux widgets (test). La feuille étend les règles
de section par `:is([data-meili="facet"], [data-meili="sort-choices"])`, à spécificité égale.

**Mesuré** : dans le tiroir, section « Trier par » identique aux autres ; choisir « Prix croissant »
donne 1 requête, `?sort=price_asc`, et le tiroir reste ouvert. 1440 px : pill « Trier par », panneau
flottant, un seul ouvert.

**Suite du 2026-09-25 (Louis) : la pill ne disait pas quel tri était en force.** Le déclencheur lit
maintenant « Trier par : Pertinence ». Clé `Sort by: :choice`, fr `Trier par\u00a0: :choice`
(espace insécable). `SortSummary` découpe le motif autour de la valeur : libellé
(`.meilifacetsFacetToggleLabel`), séparateur, valeur (`.meilifacetsSortChoice`, nouveau crochet
`sort-chosen`, optionnel, additif, `Contract::VERSION` inchangé) — la vue n'écrit que des propriétés.
`toggle.blade.php` enveloppe libellé et slot dans `.meilifacetsFacetToggleName`, pour que le `gap`
du bouton ne s'insère pas avant le séparateur. Pas d'`aria-label` : le texte **est** le nom, libellé
en tête (WCAG 2.5.3). Mobile first : la valeur est masquée hors de vue (clip, pas `display: none`)
dans la section du tiroir, affichée à partir de `48em`. `SortRadios.show()` réécrit la valeur à
chaque état (choix, `popstate`). Badge toujours vide.

**Mesuré dans Playwright** (`immediate` et `submit`, config restaurée, `cmp` = 0) : 1440 px, bouton
« Trier par : Pertinence » → « Prix croissant » → nom « Trier par : Prix croissant », URL
`?sort=price_asc` ; rendu serveur de `?sort=price_asc` juste ; `immediate`, retour arrière →
« Pertinence », avant → « Prix croissant ». 393 px, tiroir : section « Trier par » seule, valeur
`clip-path: inset(50%)` sur 1 px, nom « Trier par : Pertinence », U+00A0 dans le DOM. En `submit`,
le tri remplace l'entrée d'historique (`#keepsHistory`) : pas de `popstate` propre à y mesurer,
couvert par le test TS.

### R-173 · 🟡 · **fermé le 2026-09-25** (étape 5a, `40822fd`, `d62e8d9`) · ouvert le 2026-09-25 — pas de tiroir mobile

Rattaché à `R-48`, étape 5a de [chantier-filtres.md](chantier-filtres.md) : option A (conteneur
ordinaire promu en dialogue), architecture Q-1 → Q-4.

**Livré.**
- `<x-meilifacets::drawer>` et `<x-meilifacets::drawer-opener>`, crochets `drawer`, `drawer-title`,
  `drawer-close`, `drawer-open` (règle : `drawer` exige titre et ✕).
- `ts/drawer/` : `drawer.ts`, `inert-page.ts`, `drawer-gesture.ts`, `sheet-height.ts` ;
  `ts/collapsible/panel-motion.ts`.
- Refonte **mobile first** des repliables : section en ligne en base, panneau flottant à partir de
  `48em`, et `DisclosureGroup` qui décide par la feuille.
- Mécanismes et coûts : voir `decisions.md` « En attente de validation ».

**Suites de consignes intégrées au lot** :
- ouvreur « Filters » avec pastille et icône `filters.svg` ;
- `--meili-control` à 3rem avec plancher tactile, padding latéral 24 px ;
- survols unifiés : teinte 8 %, 150 ms, `--meili-ease` ; en-tête de section sans aplat, par la
  couleur ;
- flash de tap supprimé sur les en-têtes de section ;
- poignée, glisser pour fermer ;
- hauteur qui suit le contenu ;
- sections animées (principes du Family drawer) ;
- encre du thème de test (`#xxxxxx`) des pastilles cochées.

**Causes trouvées en route**, par la mesure :
- une `<legend>` interrompait le filet de séparation et avalait le padding, d'où la légende flottée ;
- une marge ne survit pas à `clear` sous un flottant, d'où un padding ;
- un `border-box` du thème rognait les rangées à 29 px ;
- l'effet bizarre au tap venait de la cible de 48 px (marges négatives autour d'une ligne de 24),
  que l'aplat `:active` et `-webkit-tap-highlight-color` peignaient en entier ;
- la poignée héritait des styles du ✕ (boîte de 48 px en desktop, `scale(0.75)` à l'appui) ;
- le geste rapide était calculé sur tout le geste, si bien qu'un glisser de 72 px en 540 ms fermait ;
  il l'est maintenant sur 100 ms ;
- tirer vers le haut depuis la poignée annulait le geste.

**Mesuré dans Playwright** (390/393 et 1440 px, `submit` et `immediate`, `config/meilifacets.php`
restauré après chaque passage, `cmp` = 0) :
- **ouvreur** : 48 px, bordure encre du thème, badge 17,8 × 17,8 px (21,9 px à « 12 »), identique au
  badge d'un déclencheur ; nom « Filtres », description « 1 » ;
- **tiroir fermé** : 60 Tab sans jamais y entrer, absent de l'arbre d'accessibilité ;
- **tiroir ouvert** : `dialog "Filtres"`, 32 à 34 nœuds `inert`, aucun sur ses ancêtres, `html`
  `overflow: hidden`, défilement de la page bloqué (molette : 0 → 0), focus sur le titre, Tab
  cantonné au tiroir ;
- **fermeture** : ✕, voile, Échap (transition coupée, `data-instant`), poignée, « Appliquer » ;
  `inert` entièrement retiré à chaque fois ; élargie à 1440 px ouverte, le tiroir est rendu
  non modal, sans `inert`, et le focus reste où il était ;
- **mouvement** :
  - entrée 350 ms et sortie 250 ms en `cubic-bezier(0.32, 0.72, 0, 1)` ; voile en opacité, noir
    50 %, sans `backdrop-filter` ;
  - hauteur 518 → 654 px en 270 ms ;
  - section de 140 px ouverte en 270 ms (WAAPI), sortie en 203 ms ;
  - frames pendant l'ouverture d'une section : ≤ 16,8 ms pour la première, ≤ 9,4 ms ensuite ;
  - mouvement réduit : `transform: none`, fondu de 150 ms ;
- **glisser** (souris) :
  - 80 px puis immobile : la feuille revient ;
  - vers le haut de 120 px : −11 px, puis retour ;
  - 200 px : ferme ;
  - geste rapide de 60 px : ferme ;
  - le voile suit (0,85 à 80 px) ;
  - corps défilé : pas de glisser ;
- **sans JS** : filtres en ligne, ouvreur masqué ;
- **tri dans le tiroir** : Échap ferme la liste, puis le tiroir ;
- zéro erreur console.

**Rythme du tiroir à 393 px**, comparé aux valeurs MCP de Figma (`17:754`, relu par `get_design_context`
le 2026-09-25) — le plafond à 24 px de la première livraison était un malentendu, corrigé :

| Écart | Figma | Mesuré (2026-09-25) |
| --- | --- | --- |
| En-tête → corps → pied | 40 | 40 (padding du corps, haut et bas) |
| En-tête (padding) | 24 × 32 | 24 × 32 |
| Marges latérales du corps | 32 | 32 (+ 15 de barre de défilement dans Chromium de bureau) |
| Entre deux sections, filet compris | 24 + 1 + 24 | 24 + 1 + 24 |
| Titre de section → liste | 16 | 16 (ligne de titre de 24 ; le déclencheur de 48 déborde de 12) |
| Entre valeurs | 8 | 8 (pastilles, en ligne et en colonne) ; 32 d'un haut de case à l'autre, soit rangées de 24 + 8 |
| Pastilles, hauteur | 48 | 48 |
| Pied | 16 × 32 | 16 × 32 ; « Appliquer » 329 px seul, 265 px avec la poubelle |

**Relevé à côté, corrigé ici** : `ActiveValuesComponentTest` codait « Retirer le filtre 10,00 » en dur
alors que le catalogue dit « Entre :min et :max » depuis `25a5aa3`. Le test lit maintenant le
catalogue par `__()`.

**Questions ouvertes** : élargie au-delà du seuil tiroir ouvert, la page peut garder plusieurs
panneaux flottants ouverts jusqu'au clic suivant. Un contenu repeint par une recherche n'a pas de
fondu (la hauteur, elle, glisse).

**Suite du 2026-09-25** (consignes de Louis, commitée dans `d62e8d9`) :
- **Neutralité** : `--meili-ink` supprimée ; pastille cochée en `CanvasText`/`Canvas`, bordure des
  pastilles en `currentColor`, trait de `filters.svg` en `currentColor`. Le thème pose sa couleur de texte
  (pastilles, hors `forced-colors`), la couleur du sheet et une icône « filtres » en `currentColor`
  (slot `icon`). Test `StylesheetNeutralityTest` (aucune couleur écrite par sa valeur, aucune police).
- **Repos sans transition** (bug « le tiroir descend au chargement ») : transitions de sortie sous
  `[data-closing]` seulement. Chargement enregistré image par image sur 600 ms, froid et avec cache :
  393 px, tiroir `hidden`, `translateY(550px)` sur toutes les images, voile 0, aucune animation dans
  le listing ; 1440 px, aucune animation ; CLS 0,000–0,003, aucune source dans le tiroir. Feuille du
  module dans le `<head>` (render-blocking). Passage du seuil tiroir fermé : plus aucun mouvement ; les
  couleurs des déclencheurs changeaient (texte `CanvasText` du sheet contre l'encre de la page) —
  réglé par la couleur du sheet posée par le thème.
- **Sections refermées après la sortie** : ✕, Échap, voile, glisser, « Appliquer », dans les deux
  modes : 60 ms après le geste, `data-closing` et 5 sections encore ouvertes ; à 560 ms, 0 ouverte,
  focus sur l'ouvreur ; à la réouverture, 0 ouverte. Échap : repli immédiat (transition coupée).
- **Valeurs gardées en place** (`immediate`, Marque et Contenance ouvertes, « odessa » cochée) : les 10
  pastilles de Contenance aux mêmes coordonnées avant/après, 7 passées à « 0 résultat », `disabled`,
  opacité 0,4 ; tiroir fermé : 3 pastilles restent, aucune `disabled`.
- **Appuis** (souris, `transform` calculé pendant l'appui) : ouvreur et « Appliquer » `scale(0.97)`
  atteint en ~150 ms, poubelle `scale(0.94)` en ~150 ms, pastille `scale(0.96)` en ~115 ms.
- **Badge** : 0 → 1, animation WAAPI de 150 ms (`cubic-bezier(0.23, 1, 0.32, 1)`), opacité 0 et
  `scale(0.9)` → 1 ; 1 → 2 : aucune animation.
- **Panneaux desktop** (1024, 1280, 1440 px, deux modes) : Trier, Catégorie, Marque 288 px ; Contenance
  448 px, 10 pastilles sur 2 rangées ; Prix 320 px, piste 273 px ; aucun ne déborde, `data-align-end`
  jamais nécessaire.
- **Boucle des tests** : `node --test` sur `disclosure-group.test.ts` restait 150 s à 100 % CPU (142 Go
  vus par Louis). Pas de boucle dans le code livré : le test « stays open with the focus » tombait
  désormais sur une case `disabled` (réponse factice sans compte → valeur gardée en place), et
  l'`assert.equal` en échec entre deux éléments happy-dom faisait inspecter à Node tout le graphe du
  DOM pour son message. Corrigé : la réponse factice porte des comptes, l'identité se compare par
  `assert.ok(a === b)`. Prouvé par des lancements bornés (groupe de processus tué à l'échéance,
  `--test-timeout`, `--max-old-space-size=1024`) : le fichier passe en 0,1 s, aucun `node --test`
  survivant. Navigateur : aucune boucle observée (images de cadence ≤ 16,7 ms hors repeinte de grille).

**Questions ouvertes** (état au premier passage) : focus de la poubelle sur `body`, case `disabled`
qui perd le focus, image de ~117 ms pendant le morph en `immediate` — traitées ci-dessous.

**Finitions du 2026-09-25** (Louis, commitées dans `40822fd` et `d62e8d9`) :
- **`aria-disabled`** au lieu de `disabled` pour une valeur gardée en place : la case garde le focus
  et est annoncée ; clic et Espace annulés côté client (`FacetsView::refuses()`), `change` refusé ;
  une case cochée reste décochable. Mesuré (`immediate`, 393 et 1440 px, Contenance ouverte, « globex »
  cochée) : 7 valeurs sur 10 passent `aria-disabled`, restent visibles (opacité 0,4) ; la case qui
  avait le focus le garde ; arbre d'accessibilité `checkbox "400ml" [disabled]` ; Espace puis clic :
  toujours décochée, **0 recherche** ; décocher la marque : 1 recherche. 4 tests TS + 2 de vue.
- **`filters.svg`** : trait `#000` explicite (comme `trash.svg`), tracé inchangé ; publié.
- **Styles en ligne du sheet** (bug de Louis : ~800 px gardés après un passage desktop ↔ mobile) : la
  hauteur en ligne est retirée à la fin de la sortie et au passage du seuil, jamais écrite en
  desktop, remesurée depuis zéro à l'ouverture ; `DrawerGesture::cancel()` retire position, voile et
  `data-dragging`. Mesuré, deux modes : ouvert 393 px `750px` = contenu (plafond 780) ; élargi à
  1440 px `''` (hauteur 48) ; revenu à 393 fermé `''` ; rouvert `750px` ; fermé `style=""` ; chargé à
  1440 puis 393 et ouvert `550px` = contenu ; Échap `''`. 5 tests TS + 1 du geste.
- **Grille différée** (`HeldPaint`, `ListingDrawers`) : tiroir ouvert ou sortant, grille et
  pagination attendent ; la dernière réponse est peinte l'image après la sortie. Mesuré en
  `immediate` : grille inchangée tant que le sheet est ouvert, puis celle de la dernière recherche
  (`?marque=globex`, 3 articles). Émulation 393 px, CPU ×4, avant (bundle sans report, substitué) /
  après, deux passages : **INP** 64–104 ms avant, 88–112 ms après au cocher ; 64–120 / 64–72 ms à la
  fermeture ; en `submit` 64–104 ms. Scripts longs (LoAF) au cocher : `onchange` 40–57 ms dans les deux
  variantes ; la repeinte de la grille n'apparaît plus comme script long dans aucune (images
  interceptées). ⚠️ Des images de 200–275 ms subsistent **avant comme après, dans les deux modes**,
  sans script ni style (`render` ≤ 16 ms) : rendu logiciel de Chromium sans tête sous ×4. L'objectif
  « aucune image > 50 ms » n'est donc pas démontrable ici ; INP < 200 ms l'est.

### R-172 · 🟡 · **fermé le 2026-09-24** (étape 4c) · ouvert le 2026-09-24 — le prix ne peut pas se replier en panneau

Rattaché à `R-48`, étape 4c de [chantier-filtres.md](chantier-filtres.md) ; suite de `R-170`, qui
l'excluait (`Facets::collapses()`) au motif que la piste mesure 0 px dans un panneau `hidden` (`R-121`,
`R-142`).

**La prémisse, relue dans le code : rien n'était mis en cache.** Les poignées et le remplissage sont
placés en fraction de la piste, en CSS (`left: calc(var(--at) * 100%)`, dégradé sur `--from`/`--to`),
écrits par `PriceSlider::paint()` sans aucune largeur. La seule mesure est
`SliderDrag::ratioAt()`, un `getBoundingClientRect()` relu à **chaque** `pointermove`. Fermée, la piste
mesure bien 0 px, mais personne ne la lit ; ouverte, la première mesure est la bonne, et un
redimensionnement panneau ouvert est lu au mouvement suivant. Ni `ResizeObserver` ni lien
`DisclosureGroup` → prix : aucun code de mesure n'a changé. Un test tient ce fait : la piste de la
fixture mesure 0 px tant que son panneau est `hidden`, et le glissé après ouverture doit tomber juste —
une mesure prise à la construction de `SliderDrag` le fait échouer (mutation tuée).

**Livré.** `collapsible` sur `<x-meilifacets::price>`, relayé tel quel par `<x-meilifacets::facets
collapsible>` (`Facets::collapses()` et son exclusion supprimés). Même markup que la facette :
`toggle.blade.php` dans une `<legend>` toujours visible (sans `collapsible`, la légende d'un prix à
curseur reste `meilifacetsHidden`), panneau `hidden` + `panel`, `View\Disclosure` préparé par
`Price::disclosure()`, id du panneau par `Price::panelId()` (la vue ne lit plus `$ids`). Badge :
`ListingState::priceFilterCount()`, en PHP et en TS, 1 si une plage est tenue (`R-123`), réutilisé par
`activeFilterCount()`. Côté client, `SelectedCountView` ne connaît plus `FacetsView` : il demande à
chaque `SelectionHolder` (`FacetsView`, `PriceControl`) ce que tient le bloc du badge, `undefined` pour
un bloc qui n'est pas le sien ; `FacetsView::taxonomyIn()` redevient privé. Aucun CSS ajouté : les
règles de la 4a (`:has([data-meili="toggle"])`, `panel`, `data-align-end`) suffisent.

**HTML sans `collapsible` identique à l'octet**, mesuré contre la vue d'avant sur le prix du projet de test :
2 929 o sans plage, 2 955 o avec `min_price=15&max_price=30`, md5 identiques. Test durable
`CollapsiblePriceTest::it_differs_from_the_plain_price_by_the_legend_and_the_closed_panel_only`.

**Navigateur** (Playwright, `/boutique`, bornes 2–50 €, **`apply_mode=immediate`** : le
`config/meilifacets.php` du projet de test porte cette valeur, modification non commitée antérieure à ce
point — le mode `submit` n'est couvert que par les tests TS). 1440 px : quatre pills, « Prix »
comprise ; fermé, piste et poignées à 0 px ; ouvert, panneau 196 px, piste 187,7–343,2, poignées
centrées à 187,7 et 343,2 (bornes exactes) ; « Marque » ouverte ferme « Prix » ; poignée max glissée à
mi-piste → 26, centre 265,5 (`--at` 0,5), `?max_price=26`, 62 → 41 articles, badge « 1 », pastille
« Jusqu'à 26,00 € » ; Échap ferme et rend le focus ; rouverte, poignée à 265,5 ; flèche gauche → 25,
`--at` 0,4792, centre 262,2 = 187,7 + 155,5 × 0,4792, 39 articles. 390 px : mêmes relevés décalés
(piste 36,3–191,8, 26 → 114, 25 → 110,8). Chargement direct de `?max_price=25` : badge « 1 » rendu par
le serveur, poignée à 110,8 (390) et 262,2 (1440) dès la première ouverture. UX-2 : pill poussée au bord
à 390 px → `data-align-end`, panneau 159–355, bord droit sur celui de la pill (355) ; remise en place →
attribut retiré. Zéro erreur console.

**Relevés à côté, non traités ici** : à 1440 px la colonne fait passer les pills sur deux rangées, et un
panneau ouvert sur la première (« Marque ») recouvre la pill « Prix » de la seconde — un clic visant
« Prix » coche une marque (disposition 4a, toutes pills confondues) ; dans le panneau, l'en-tête du
curseur répète « Prix » (`aria-hidden`) sous la pill du même nom ; piste de 155,5 px dans un panneau de
`14em`.

**Les cinq passes.** *Lisibilité* : `Price::disclosure()` et `Facet::disclosure()` se ressemblent (même
`Disclosure`, ids de la même famille) ; laissés côte à côte, chacun lit sa propre sélection — un
parent commun ne servirait que ces deux lignes. Noms comparés : `priceFilterCount()` à côté
d'`activeFilterCount()`, `SelectionHolder::heldIn()` pour « ce que tient un bloc ». *Commentaires* :
deux supprimés (l'exclusion de `Facets::collapses()`, celui du test du groupe), un ajouté (sémantique
d'`undefined` sur l'interface). *Performance* : un `closest()` et deux appels de détenteur par badge et
par changement d'état, badges bornés au nombre de filtres ; aucune mesure ajoutée. *Sécurité* : le badge
reçoit un entier, en `textContent` et par `{{ }}`. *Contexte* : sans WooCommerce, pas de prix donc pas de
bloc ; sans bloc prix, `PriceControl::heldIn()` rend `undefined` et le badge n'est pas touché (test).
Aucune chaîne ajoutée.

**Tests** : Feature `CollapsiblePriceTest` (6), `CollapsibleFacetTest` : `the_group_collapses_every_filter_it_renders`
remplace le test d'exclusion ; ts `collapsible-price.test.ts` (4 : exclusivité, glissé mesuré après
ouverture, positions en fraction, clavier) et 3 cas dans `selected-count-view.test.ts`. Deux mutations
tuées (mesure figée à la construction, prix retiré des détenteurs). `composer check` vert (Unit 297,
client 356) ; suite `Modules` : `OK (478 tests, 1505 assertions)`.

### R-171 · 🟠 · **fermé le 2026-09-24** · ouvert le 2026-09-24 — les réglages d'index repoussés en local perdent les attributs du prix

**Constat.** `wp meiliscout index` (avec ou sans `--clear`) et toute sauvegarde de produit faite par la
suite `Feature` (`AnonymousIndexingTest`, `ProductPriceProjectionTest`, `ShownPriceProjectionTest`)
repoussaient sur `posts` des `filterableAttributes` sans `metas._price`, `metas._stock_status`,
`price.*`, et des `sortableAttributes` réduits à `post_date`, `post_title` : `price.min is not
filterable`, `/boutique` en vue « indisponible », suite `Modules` rouge (8 échecs + 1 erreur). Second
symptôme, passé inaperçu : les cartes indexées n'avaient plus de `card.price`.

**Cause, prouvée.** L'instant de résolution, pas la configuration. Pollora résout les instances des
`#[Filter]` d'un coup, à l'`apply()` de la découverte — et `PluginRegistrar::register()`, appelé par le
plugin d'expéditions du projet **pendant le chargement des plugins**, relance cet `apply()` pour tous les
hooks découverts (`ModuleDiscoveryOrchestrator::discover()`). Ce plugin se charge avant
`woocommerce` dans `active_plugins` : `MeiliScoutBridge` est construit à `plugins_loaded=0`, sans
`wc_get_product`, et fige `EmptyIndexAttributes` et `DefaultCardProjector` pour toute la requête
(trace `afterResolving` sous WP-CLI). Avec `--skip-plugins=<ce plugin>`, le même pont est
construit au chargement du thème, WooCommerce présent. Le garde `WooCommerce::isActive()` était bien
dans la closure du binding, mais une closure évaluée à la construction d'un hook reste trop tôt. Options
MeiliScout vérifiées, hors de cause : `indexed_post_types = [post, product]`, `indexed_meta_keys = []`
(les métas prix viennent du module, pas de l'option). Lien avec l'import de la base du matin **déduit,
non prouvé** (aucun état antérieur de `active_plugins` conservé) : l'ordre de chargement dépend de
cette option et le code n'a pas changé depuis `b04922a`, vert le matin même.

**Correction.** `DeferredIndexAttributes` et `DeferredCardProjector` : le provider les lie toujours, ils
demandent à `WooCommerce::isActive()` à chaque lecture, comme le font déjà `ProductPriceProjector` et
`ShopTaxLocation`. Tests : `DeferredIndexAttributesTest` (unitaire), `DeferredCardProjectorTest` —
câblés avant le plugin, lus après. Isolation : trait `KeepsTheIndexOut` (`meiliscout/skip_indexing`,
filtre de MeiliScout) sur les trois tests qui enregistrent des produits — ils lisent les projecteurs
directement, rien ne dépendait de l'indexation à la sauvegarde. `PriceComponentTest` demandait
`55–120 €` en dur, hors d'un catalogue publié désormais à `12–42 €` : les bornes sont lues sur la piste.

**Observé.** Pont accroché sous WP-CLI : `metas._price, metas._stock_status, price.min, price.max,
price.onsale`. Réindexation `--clear` : 20 documents (15 produits publiés + 5 articles), 15 avec `price`,
`card.price` revenu. `/boutique` : 15 cartes ; `?min_price=30&max_price=50` : « 4 articles », égal au
filtre direct. Suite `Modules` deux fois de suite : `OK (472 tests, 1200 assertions)`, réglages intacts
après chaque passage, aucune tâche Meilisearch créée par la suite. `composer check` vert. Navigateur non
ouvert : Playwright et Chrome reçoivent `ERR_CONNECTION_RESET` sur l'hôte local du projet depuis cet
environnement, alors que `curl` répond ; la clé de recherche publique filtre et trie sur `price.*` (`:7701`).

**Reste ouvert.** Côté Pollora (upstream) : `PluginRegistrar::register()` réapplique **toutes** les
découvertes, pas seulement celles du plugin — tout hook d'un module qui injecte un service dépendant
d'un plugin est exposé au même piège.

**Vérifié dans le navigateur le 2026-09-24**, après `ddev restart` (le routeur refusait les navigateurs,
`curl` répondait) : `/boutique` 15 articles et 15 cartes, bornes 12,00 € – 42,00 €, aucune erreur
console ; `?min_price=30&max_price=50` → 4 articles, pastille « 30,00 € – 50,00 € ». **Précision sur
la cause** : `PluginRegistrar::register()` n'est appelé qu'une fois, mais passe par le moteur de
découverte singleton (`DiscoveryServiceProvider.php:84`) : `addLocation()` ajoute le chemin du plugin
aux emplacements déjà connus, puis `discover()->apply()` rescanne et réapplique **tous** les
emplacements. **Question ouverte** avant de proposer le correctif à Pollora : cette réapplication
branche-t-elle deux fois des hooks déjà branchés ?

### R-170 · 🟡 · **fermé le 2026-09-24** (étape 4a) · ouvert le 2026-09-24 — une facette ne peut pas se replier en panneau

Rattaché à `R-48`, étape 4a de [chantier-filtres.md](chantier-filtres.md) ; `R-89` demandait déjà un
fieldset en menu déroulant (`<details>` écarté). Attribut `collapsible` sur `<x-meilifacets::facet>`,
relayé par `<x-meilifacets::facets collapsible>` à toutes ses facettes **sauf le prix** (piste mesurée à
0 px dans un panneau fermé, étape 4c : `Facets::collapses()`).

**Markup** (motif disclosure APG) : la `<legend>` contient `toggle.blade.php` — `<button>`
`aria-expanded="false"`, `aria-controls` → panneau existant (`ElementId::facetPanel`), `aria-describedby`
→ badge `selected-count` (`ElementId::facetSelectedCount`), `aria-hidden`, masqué **et vidé** à zéro ;
chevron en `::after`, comme le tri. Le panneau prend `hidden` et `data-meili="panel"`. Données préparées
par `View\Disclosure` (`Facet::disclosure()`), vue sans calcul. Contrat : `facet` qui tient un `toggle`
exige un `panel` ; `Contract::VERSION` inchangé.

**Arbre d'accessibilité mesuré** (Chromium, CDP) : groupe « Marque », bouton « Marque », réduit,
description « 2 ». Le badge nommé aurait donné « Marque 2 » au bouton et au groupe ; vidé à zéro parce
qu'un nœud masqué que `aria-describedby` désigne décrit quand même. **Aucune chaîne ajoutée** : « 2
sélectionnées » exigerait un motif pluriel publié dans la description, question laissée à Louis.

**Client** : `collapsible/disclosure-group.ts` (exclusivité, Échap depuis le déclencheur ou le panneau
→ ferme et rend le focus, clic extérieur par `composedPath()`, focus parti ailleurs que le déclencheur
ou son panneau → ferme ; `relatedTarget` nul — fenêtre quittée, case masquée par un repeint — ne ferme
pas), le tout seulement hors d'un conteneur `[aria-modal="true"]` (`#floats()`, point d'extension de
l'étape 5). UX-2 : à l'ouverture, `data-align-end` si le panneau dépasse `clientWidth`, la feuille le
pend à droite. `collapsible/selected-count-view.ts` : `state.selected(taxonomie)`, attente comprise.

**Vérifié** : HTML de `/boutique?marque=globex` identique à l'octet sans `collapsible` (218 382 o avant et
après) ; test durable `it_differs_from_the_plain_facet_by_the_trigger_and_the_closed_panel_only`.
Playwright (1440 px, `submit`) : Tab → déclencheur, Entrée ouvre, Tab → case, Espace coche (badge 1 puis
2, focus immobile), Échap ferme et rend le focus ; A puis B ferme A ; clic dans le panneau garde, clic sur
le `h1` ferme ; Tab hors de la dernière case ferme ; « Appliquer » → `?marque=globex,initech`, 15 → 6
articles, badge « 2 », panneau fermé par le clic. Déclencheur 36 px, rayon 999px, 14px dans la police du thème, 600,
bordure 1px `--meili-edge` ; badge 17,8 px noir sur blanc ; panneau `absolute`, `z-index` 10, fond blanc,
3,5 px sous le déclencheur, 197 px de large. UX-2 à 900 px : aucun panneau ne déborde naturellement ;
dernière pastille poussée au bord (droite 865 / 885) → `data-align-end`, panneau 669–865, bord droit sur
celui de la pastille ; revenue en place → attribut retiré. Zéro erreur console.

**Relecture du 2026-09-24 avec Louis** : `z-index` du panneau exposé en `--meili-layer` (défaut 10, surchargeable par le thème) ; badge resserré (`padding: 0 0.3em`, `line-height: 1`) — mesuré : rond
17,8 × 17,8 px pour un chiffre, 21,9 × 17,8 px pour deux, pilule au-delà. Prix volontairement non
repliable jusqu'à 4c (`R-121`). Hauteur de 24 px signalée par Louis à l'ouverture des devtools : non
reproduite (36 px mesurés de 390 à 1440 px, au survol, au focus, en impression, en couleurs forcées).

**Suite** : le prix repliable, exclu ici, est livré sous `R-172` (étape 4c, 2026-09-24).

### R-169 · ⚪ · ouvert · 2026-09-24 — `results.blade.php` prépare encore une donnée

`@php($pastTheEnd = $listing->pagination()->isPastTheEnd())` : la vue interroge le listing et
calcule, au lieu d'appeler une méthode du composant `Results`. Même défaut que celui retiré de
`facet.blade.php` pendant la 3b du chantier « barre de filtres » (`R-151`), relevé par Louis en revue.
Hors chantier ; piste : `Results::isPastTheEnd()`, la vue appelle `$isPastTheEnd()`.

### R-168 · ⚪ · **fermé le 2026-09-24** · ouvert le 2026-09-24 — le bouton « Voir plus » n'a aucun style de base

`[data-meili="more"]` (`meilifacetsFacetMore`) n'avait aucune règle dans `meilifacets.css` : bouton
natif du navigateur, marge nulle, collé à la liste. Contraire à « Style par défaut d'une brique ».
Relevé par Louis pendant l'étape 3b.

**Fermé le 2026-09-24**, sur le crochet (`R-128`), markup intact. Le bouton est posé **comme une rangée
de valeur de plus** : même marge latérale `-0.4em` et même padding `0.25em 0.4em` que le `<label>`
d'une valeur, marge haute nulle. La liste n'espace pas ses rangées, donc le bouton tombe à un pas de
rangée sous la dernière valeur visible, texte à l'aplomb des cases. `font: inherit`,
`font-size: var(--meili-ui)`, `color: inherit`, ni fond ni bordure natifs, rayon et transition des
rangées. Il rejoint les listes de ses sœurs : `cursor: pointer`, `:focus-visible` (contour
`currentColor`), survol `--meili-tint` sous `(hover: hover) and (pointer: fine)`, `:active`,
`-webkit-tap-highlight-color`, `prefers-reduced-motion`, et `min-height: var(--meili-control)` au
pointeur grossier (44 px). Pas de `forced-colors` : `reset` et `page` n'y figurent pas non plus.

**Vérifié** (Chromium) : bouton de 28 px (44 px au pointeur grossier), x 34,4 (texte à 40, comme les
cases), haut à 987,4 = bas du `<label>` de la dernière valeur visible ; 14 px, police du thème, 400,
comme `reset` ; fond transparent, bordure 0 ; survol teinté, contour de focus 2 px, dépliage OK.
Test `stylesheet` : `more` dans la liste des commandes à curseur, taille et police égales à `reset`,
marge et padding déclarés égaux à ceux d'une rangée, ni bordure ni fond.

**Corrigé le 2026-09-24, relevé par Louis** : la marge haute était restée nulle. Le bouton prend
`margin-top: 0.5em`, le même écart que celui qui sépare la liste de sa légende
(`[data-meili="facet"] ul`) ; un test de la feuille vérifie l'égalité.

### R-164 · 🟡 · **fermé le 2026-09-24** (étape 3a) · ouvert le 2026-09-24 — une facette ne peut pas déclarer comment ses valeurs se présentent

Toutes les valeurs de facette sortent en cases à cocher (ou en radios, selon `SelectionMode`) ; rien
ne permet de dire qu'une facette se présente en pastilles type « 15 ML », sinon en surchargeant
`facet.blade.php` pour tout le listing. La maquette de la barre de filtres mêle les deux. Relevé par
la passe de conformité du chantier « barre de filtres » ; `D-07` l'excluait à la lettre (« le module
ne sait pas que ce sont des pastilles »).

**Tranché le 2026-09-24** (C-5) : enum `Presentation` (`Control`/`Pill`, sans `Radio` — `R-10`),
déclarée sur la facette dans `ProductFacets`, surchargeable par attribut ; une facette `Control` doit
sortir le HTML actuel à l'octet près. Renversement de `D-07` noté à sa place. Rattaché au chantier
`R-48`, étape 3a de [chantier-filtres.md](chantier-filtres.md).

**Précisé le 2026-09-24 par Louis** : la présentation est un ensemble **ouvert**. Contrat
`Contracts\ValuePresentation` (`name()`, valeur de `data-presentation` ; `allowsSingleSelection()`,
qui porte la garde `R-10`) ; l'enum `Presentation` du module l'implémente (`Control` l'autorise,
`Pill` non). Un thème déclare ses propres présentations (un enum qui implémente le contrat) dans
`ProductFacets` et les habille par `[data-presentation="…"]`.

**Fermé le 2026-09-24.** `Facet` reçoit `presentation:` (défaut `Presentation::Control`, relayé tel
quel par `ChildTermsFacet`, qui hérite du constructeur). **Garde `R-10`** au plus tôt : dans le
constructeur de la déclaration, donc au boot du listing, par `Facet::presentedAs()` — une facette
`Single` sous une présentation qui ne l'autorise pas lève `InvalidArgumentException` (« Facet "x"
holds one value at a time, so its values cannot be presented as "pill": they would be radios, which
cannot be unchecked… »). La surcharge du composant passe par la même méthode : `presentation="pill"`
(chaîne → `Presentation::from()`, cas du module seulement) ou `:presentation="$objet"` (présentation
d'un thème). Pas d'attribut sur `<x-meilifacets::price>` : le prix n'a pas de valeurs à cocher.
La vue ajoute `data-presentation="{nom}"` pour toute présentation sauf `Control`, collé après
`{{ $scrollMark() }}` : un `@endif` en fin de ligne aurait avalé le saut de ligne suivant (PHP mange
le `\n` après `?>`) et changé le HTML des facettes `Control` — c'est ce que le test attrape.
CSS du module pour `pill` seulement (rangée qui passe à la ligne, pastille bordée `--meili-edge`,
rayon 999px, `min-height: var(--meili-control)`, cochée = bord `currentColor` + fond `--meili-press`,
focus par `:has(:focus-visible)`, input natif masqué visuellement, `forced-colors` en
`SelectedItem`, transitions coupées sous `prefers-reduced-motion`).

**Limite assumée** : aucun markup propre à une présentation (pas de vue partielle par présentation) ;
on l'ouvrira quand un projet en aura besoin. Le compteur `(n)` reste dans la pastille, markup
inchangé (`R-151`, étape 3b — *fermé depuis : compteur sorti du libellé, masqué en pastille*).

**Vérifié** : HTML de `/boutique` identique à l'octet avant/après sur les quatre facettes, en
`Control` (seuls les `?ver=` de WP Rocket changent) ; après déclaration de Contenance en `Pill` dans
le projet de test, une seule ligne diffère (` data-presentation="pill"`). La comparaison avec une copie figée de la vue d'avant a servi de preuve une
fois puis a été retirée en revue (elle aurait cassé à la première évolution légitime de la vue) ;
reste le test durable `it_marks_pills_with_one_attribute_and_nothing_else` : une facette `Control`
ne porte aucun `data-presentation`, une pastille n'en diffère que par cet attribut. Contrat renommé
en revue : `name()` → `slug()`, pour ne pas cohabiter avec `->name` des enums. Playwright : Tab + Espace et clic cochent, focus et état
coché visibles, « Appliquer » filtre (`?contenance=15ml,400ml`, 74 → 9 articles), marque et
catégorie inchangées (input `static` 14px), zéro erreur console. Pastille relevée = pastille active :
14px, police héritée du thème, 36px, rayon 999px, padding `0 11.9px`, `line-height` 14px ;
seule différence, la bordure 1px (0 sur la pastille active). `composer check` vert (Unit 294,
TS 324), suite `Modules` 456 tests verts.

### R-167 · 🟡 · **fermé le 2026-09-24** · ouvert le 2026-09-24 — `ResolvedListing` porte le registre de placement

`app/Listing/ResolvedListing.php` (357 lignes, 30 méthodes publiques, 7 dépendances, 11 champs
mutables) mêle quatre responsabilités : exécuter et mémoïser la recherche, résoudre facettes et
valeurs, **tenir le registre de ce qui est rendu sur la page**, relayer la configuration du listing.
`CLAUDE.md` § 2-3 : un fichier qui fait deux choses, c'est deux fichiers. Relevé par Louis en revue
de l'étape 2c du chantier `R-48`, qui l'a aggravé : la garde du tri (`R-162`) a posé un booléen
`$sortRendered` à côté du tableau `$rendered`. Traité entre l'étape 2c et l'étape 3, avant `R-164`.

**Fermé le 2026-09-24**, sans changement de comportement. Le registre est sorti dans
`Listing/PagePlacement` (`place()`, `placeApart()`, `placeSort()`, `remaining()`), que
`ResolvedListing` construit avec le nom du listing — une instance par listing résolu, donc la même
durée de vie qu'avant. Le rendu est un seul tableau indexé par `Enums\PlacedControl` (`Facet`,
`Sort`) puis par nom : une facette nommée `sort`, ou du nom du listing, ne peut plus se confondre
avec le tri. `ResolvedListing` garde la résolution par nom et la garde de genre dans `placing()`, et
trois relais (`placing()`, `placeSort()`, `remainingFacets()`) : les composants ne voient toujours
qu'elle. `place()` et `placeApart()` quittent sa surface publique — seuls des tests les appelaient ;
ils passent désormais par `placing()`, le chemin réel des composants. 357 → 327 lignes,
30 → 28 méthodes publiques, 11 → 8 champs mutables. Message du tri corrigé (« Render
<x-meilifacets::sort> once per page. », la mention de deux dispositions datait de l'architecture v1
abandonnée). Non fait : fusionner `sorts()`/`currentSort()`/`sortMatches()` pour `Sort` — `sorts()`
reste appelé par des tests et `ListingDescription`, et une méthode qui rendrait des `SortChoice`
ferait dépendre `Listing/` de `View/`. **Vérifié** : `composer check` vert (Unit 285, TS 319), suite
`Modules` 442 tests verts, `/boutique` 200 après `view:clear`.

**Complété le 2026-09-24, en revue avec Louis** : `placing()` renommé `placeFacet()` (en écho à
`placeSort()`), avec ses deux chemins écrits en clair — une déclaration reste dans le groupe, un nom
est placé à part — et la vérification du type sortie dans `ofKind()`. `facetNamed()` passe privé :
seuls des tests l'appelaient de l'extérieur, ils passent maintenant par `placeFacet()`.

### R-166 · 🟡 · ouvert · 2026-09-24 — deux `<x-meilifacets::listing>` du même nom sur une page ne sont gardés nulle part

`listing-page.ts` lie une instance par racine, donc deux écritures d'historique, et `ElementId` ne
distingue que par nom de listing, donc des identifiants dupliqués. Détaché de `R-162` le 2026-09-24
(étape 2c du chantier « barre de filtres ») : c'est une garde sur la racine, pas un problème de
placement. Hors chantier — la maquette n'a qu'un listing.

Piste : `Listing` refuse un second rendu du même nom, comme `ResolvedListing::placeSort()` le fait
pour le tri.

### R-165 · ⚪ · **fermé le 2026-09-24** · ouvert le 2026-09-24 — un faux moteur survivait à `PriceComponentTest`

La suite `Modules` partage une seule application : `PriceComponentTest` liait un faux `SearchEngine`
sans le rétablir, et la classe suivante rendait ses composants contre ce faux. Révélé par
`TotalComponentTest` (étape 2a), qui lisait un total de 0.

**Fermé le 2026-09-24** : `tearDown()` rétablit la liaison d'origine, comme `SortComponentTest` le
faisait déjà. Suite `Modules` : 421 tests verts.

### R-163 · 🟡 · **fermé le 2026-09-24** · ouvert le 2026-09-24 — le listing n'annonce pas son nombre de résultats

Aucun composant ne rend le total (« 88 articles ») et aucune vue ne porte de région `aria-live`
(lecture de `resources/views/components/`) : après un filtrage, rien ne dit combien de produits
restent, et un lecteur d'écran n'apprend même pas que la grille a changé. La maquette de la barre le
montre en desktop comme en mobile. Relevé par la passe de conformité du chantier « barre de
filtres » ; voisin de `R-50`, qui est la même donnée pour les moteurs.

Rattaché au chantier `R-48`, étape 2a de [chantier-filtres.md](chantier-filtres.md) :
`<x-meilifacets::total>`, crochet `total`, région `aria-live="polite"`.

**Fermé le 2026-09-24** (étape 2a). `<x-meilifacets::total>` rend un `<p aria-live="polite"
aria-atomic="true">` au crochet `total`, rempli par le serveur : le total vient de la recherche qui
rend déjà la grille (`ResolvedListing::total()`), sans requête de plus. `TotalView` repeint chaque
compteur (`contract.all()`, `R-162`) et ne réécrit un texte que s'il change, pour que la région ne
reparle pas à chaque page. Motif `:count item|:count items` (« article(s) »), distinct de
`countPattern`. Style par défaut aligné sur les sœurs (`--meili-ui`), relevé par Louis.
Observé : `/boutique` « 74 articles », « cheveux » + « Appliquer » → « 14 articles » et 14 cartes,
aucune erreur console ; `composer check` vert, suite `Modules` 421 tests verts. Reste ouvert à
l'étape 6 : deux compteurs sur une page font deux annonces. `R-50` non touché.

### R-162 · 🟠 · **fermé le 2026-09-24** · ouvert le 2026-09-24 — le doublon silencieux n'est gardé que pour les facettes

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

**Tranché par l'architecture v2** (chantier, § 1) : un élément d'**état** peut apparaître deux fois
(`reset`, compteurs), un **contrôle** jamais (`R-95`).

**Fermé le 2026-09-24 pour la première puce** (étape 2c). `FilterSummaryView` peint chaque `reset` et
chaque `active-filters` (`contract.all()`), comme `TotalView` et `ActiveValuesView`. Nouveau crochet
optionnel `active-count` (`Hook::ActiveCount`, additif, `Contract::VERSION` inchangé, aucune règle de
contrat) : un nombre nu, peint sur chaque exemplaire par `listing/selection-count-view.ts`, masqué à zéro.
Il suit **la sélection courante, en attente comprise** — le même état que le badge `active-filters`,
repeint sur l'événement `change`, et ce que dit C-3 (« X = nombre de valeurs cochées ») ; la plage de
prix compte pour un, comme `activeFilterCount()` des deux côtés. À l'inverse des pastilles, qui
montrent l'état appliqué : ce n'est pas une contradiction, les pastilles sont des ordres, le compteur
annonce ce que « Appliquer » emportera. **Aucune vue ne le rend encore** : sa place est l'ouvreur (5a)
et `<x-meilifacets::apply>` (5b) ; l'ajouter au bouton de `facets.blade.php` aurait imposé un attribut
que 5b remplace. La valeur serveur est déjà servie par `ResolvedListing::activeFilterCount()`. Le tri
est un contrôle : `ResolvedListing::placeSort()`, appelé par `Sort`, lève au second rendu du même
listing (« The sort of listing "products" is rendered twice on this page… »). Observé (Chromium,
`/boutique`, doublons temporaires de `reset` et `active-filters` dans le thème, deux `active-count`
injectés) : « cheveux » cochée → les deux badges « 1 filtre actif », les deux « Tout effacer » visibles,
les deux compteurs « 1 », aucune recherche ; « Appliquer » → 14 articles, idem ; « Tout effacer » du
second exemplaire → tout masqué, 74 articles ; aucune erreur console. Second `<x-meilifacets::sort />`
→ 500 avec le message dans `laravel.log`. Doublons retirés. `composer check` vert (Unit 281, TS 319),
suite `Modules` 438 tests verts.

**Seconde puce détachée le 2026-09-24 dans `R-166`** (deux `<x-meilifacets::listing>` du même nom) :
ce n'est pas un élément rendu deux fois mais une racine, et sa garde se pose dans
`Listing`/`listing-page.ts`, pas dans le placement.

### R-161 · ⚪ · ouvert · 2026-09-23 — une recherche sans type de contenu est comptée comme listing sans en rendre aucun

Relevé par la passe de conformité de `R-158`. `/?s=creme`, sans `post_type=product`, rend le gabarit de
recherche du thème : zéro carte, aucune description publiée. `Http\ListingPage::isCurrent()` répond
pourtant vrai (`is_search()`), donc la page est traitée comme une page de listing par la politique
d'indexation et par le chargement du client. Sans conséquence aujourd'hui — le cœur pose déjà le
`noindex` sur une recherche — mais la garde ment sur ce qu'elle garde.

### R-160 · 🟡 · corrigé dans l'arbre (remplacement du drapeau à valider par Louis, en attente de commit) · ouvert le 2026-09-23 — sur une recherche, le module n'exclut pas ce que WooCommerce exclut

Relevé par la passe de conformité de `R-158`, lu dans la source. Sur une recherche, WooCommerce écarte
les produits marqués `exclude-from-search` (`class-wc-query.php:929`, appliqué depuis `:424-437`), alors
que le module écarte toujours `exclude-from-catalog`, quelle que soit la page
(`ProductListing.php`, `HIDDEN_FROM_CATALOG`). Depuis `R-158`, la recherche produit est servie par le
moteur : elle rend donc un ensemble qui n'est pas celui de WooCommerce. Sur le projet de test, un produit est
`exclude-from-search` seulement (#400, mesuré au lot prix) : il reste trouvable ici, alors que la
recherche native le cacherait.

**Au 2026-09-25** (`R-181`, non commité) : sur une recherche, le filtre de base exclut `exclude-from-search`
**au lieu de** `exclude-from-catalog`, comme `class-wc-query.php:929` (lu dans la source : WooCommerce échange le
drapeau, il n'en ajoute pas un). Test : `ProductSearchTest`. Aucun produit local ne porte `exclude-from-search` (compte 0
relevé le 2026-09-25) : pas de mesure sur données réelles sans modifier la base.

### R-159 · 🟡 · **fermé le 2026-10-01** (option (a), Louis) · ouvert le 2026-09-23 — un terme que le moteur ne tokenise pas sert tout le catalogue

Mesuré pendant les passes de `R-158` : `/?s=%3F%28&post_type=product` rend **16 produits** — le
catalogue entier — quand `WP_Query` en trouve **0**. Le moteur ne reconnaît aucun mot dans `?(` et
répond comme à une recherche vide. Le défaut n'est pas propre au terme de la page : `?q=%3F%28` fait la
même chose, il préexiste donc sur le paramètre du module. À trancher : traiter « le moteur n'a rien
reconnu » comme zéro résultat, ou l'écrire comme limite. Lot 5, avec la pertinence.

Même famille, mesuré au second tour de `R-158` : `trim()` en PHP ne coupe pas les blancs Unicode, `.trim()`
en JavaScript si. `/?s=%C2%A0&post_type=product` publie donc une espace insécable comme terme de page et sert
les 16 produits, là où le client aurait lu une chaîne vide.

Côté listing (`q`), traité par `R-201` le 2026-09-30 : un terme sans lettre ni chiffre compte comme aucun terme, des deux
côtés. Restent ouverts le terme routé (`s`) et les blancs Unicode.

**Au 2026-10-01** (audit, mesuré, **rien changé : décision à prendre**). `/?s=!&post_type=product` et
`/?s=%3F%28&post_type=product` servent **63 articles**, le catalogue entier, comme `/boutique` ; `/boutique?q=!` aussi
(63, voulu : « compte comme aucun terme », `R-201`) ; `/?s=!` sans type rend la recherche WordPress, « Aucun élément ne
correspond à votre recherche ». Chemin : `ProductListing::baseQuery()` ne passe pas par la règle `\p{L}\p{N}` de
`StateReader` ; le terme `!` part tel quel en `q`, côté serveur (`QueryPlan::searchTerm()`) comme côté client
(`ListingQuery.#searchTerm()`, `baseQuery` de la description) — les deux chemins sont d'accord entre eux, et le moteur
ne reconnaissant aucun mot sert tout l'index sous la portée de recherche. **Appliquer la règle « compte comme `''` » au
terme routé ne change pas le total** : `baseQuery()` vide fait parcourir le catalogue (63, portée `exclude-from-catalog`
au lieu de `exclude-from-search`). La seule issue qui ne serve pas tout le catalogue est « zéro résultat », proposée par
`chantier-recherche.md` (D-2, case « terme sans lettre ni chiffre = zéro résultat ») mais jamais validée, et que ce
constat laisse lui-même « à trancher ». Choix à faire par Louis : (a) `s` sans mot = aucun terme, catalogue parcouru —
cohérent avec `q`, contraire à WordPress (0 sur `/?s=!`) ; (b) `s` sans mot = zéro résultat — cohérent avec WordPress
et le panneau (qui n'envoie rien), demande un état « recherche vide » dans `QueryPlan`, `ListingQuery` et un cas
partagé dans `search-scope-cases.json`, sans toucher `q`.


**Tranché le 2026-10-01 (Louis) : option (a).** Un `s` sans lettre ni chiffre compte comme aucun terme, comme `q` :
`?s=!&post_type=product` sert le catalogue entier. Aucun code ; écrit dans `docs/listing/README.md`. L'option (b),
zéro résultat comme WordPress, reste possible si le besoin apparaît.

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
comme administrateur (créé et supprimé par le test, sur décision de Louis ; vérifié avant : plugin d'envoi de mails inactif,
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
le projet de test décrit comme liant son propre `CardProjector` ; la règle de `Contract::VERSION` donnée
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
- la carte de l'archive, carte produit du thème décidée, `<x-meilifacets::card>` rendue (`Q-10`, `R-45`) ;
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

### R-151 · 🟡 · **fermé le 2026-09-24** (étape 3b) · ouvert le 2026-09-22 — le compteur d'une valeur de facette entre dans le nom de sa case

`architecture.md` pose que le compteur est **décrit** (`aria-describedby`) et jamais **nommé**, pour
qu'un filtrage ne renomme pas la case sous le curseur. La vue ne le tient qu'à moitié :
`facet.blade.php` rend le `<span data-meili="count">` **dans** le `<label>` qui enveloppe l'`<input>`,
donc dans le nom accessible que le navigateur calcule depuis ce label — et il est en plus décrit. Le
test (`FacetComponentTest`) vérifie `aria-describedby` et l'absence d'`aria-labelledby`, pas le nom.
Lu dans la vue et le calcul du nom accessible ; non écouté sur un lecteur d'écran. Le correctif touche
une vue surchargeable : à décider avec Louis.

**Fermé le 2026-09-24** (chantier `R-48`, étape 3b), structure C, retenue par Louis. Le compteur
**reste dans le `<label>`**, à sa place, sans `aria-hidden` : un clic dessus coche la case
nativement. Le libellé est enveloppé d'un `<span id>` (`ElementId::facetValueLabel()`, même famille
que `facetCount()`), et l'input porte `aria-labelledby` vers ce `<span>` en plus de
`aria-describedby` vers le compteur. `aria-labelledby` l'emporte sur le `<label>` dans le calcul du
nom : nom = « Initech », description = « 12 résultats ».

Deux structures écartées en chemin :
- **A** — compteur sorti du `<label>`, libellé étiré sur la rangée par un `label::after`. Elle
  fonctionnait (mesurée identique au pixel), mais au prix d'une couche CSS fragile : le compteur,
  peint au-dessus du libellé étiré par son `opacity`, captait le clic et demandait
  `pointer-events: none`, la case un `z-index` contenu par `isolation`, et les styles de rangée
  migraient du `<label>` à la `facet-value`. Tout thème qui retouche la rangée pouvait la casser.
- **B** — compteur laissé dans le `<label>` en `aria-hidden="true"`. Le nom est juste, mais
  `aria-hidden` retire le texte de l'arbre : absent en mode lecture, et VoiceOver iOS, qui n'annonce
  pas les descriptions sans les indications activées, ne le lirait jamais.

Diff exact d'une valeur contre `e6c146a` sur `/boutique` : une ligne ajoutée à l'input,
`aria-labelledby="meilifacets-products-facet-category--label-cheveux"`, et un `id` du même nom sur
`<span class="meilifacetsFacetName">`. Rien d'autre sur la page, hors `?ver=` de WP Rocket (38 valeurs,
76 lignes). Seul autre écart, d'espaces seulement : l'indentation de chaque `<li>`, qui portait celle
du `@php` supprimé (`diff -w` : zéro ligne hors les deux ci-dessus).

**Vue sans calcul, à la demande de Louis** : `facet.blade.php` ne contient plus ni `@php`, ni `$ids->`,
ni `$listing->`. Le composant `Facet` expose `inputName()`, `panelId()`, `labelId(FacetValue)` et
`countId(FacetValue)` — même forme que `inputType()` et `countLabel(FacetValue)` qu'il portait déjà —,
qui délèguent à `ElementId` et au listing. Un thème qui copie la vue ne fige plus ces calculs.
`it_leaves_every_computation_to_the_component` le garde. `results.blade.php` (`@php($pastTheEnd = …)`)
a le même défaut, ouvert à part.

CSS : `font-variant-numeric: tabular-nums` sur le compteur (réécrit à chaque recherche) et, en
pastille, le compteur masqué visuellement par la règle de l'input natif — présent dans l'arbre, ni
`display: none` ni `aria-hidden`. Masquage **validé par Louis** (maquette « 15 ML »). Le thème le
réaffiche par `[data-presentation="pill"] [data-meili="count"]`.

**Contrat** : aucun crochet ajouté, renommé ni retiré — le libellé est trouvé par son id.
`Contract::VERSION` inchangé (`R-116`). `FacetsView` n'écrit que dans le crochet `count` ; un test
le garantit. Fixture `tests/ts/dom.ts` alignée (`R-105` ; `R-108` toujours différé). Le projet de test ne
surcharge pas `facet.blade.php` et aucune règle du thème ne vise compteur ni libellé : rien à adapter.
Une vue surchargée à l'ancienne (`R-72`) garde le compteur dans le nom jusqu'à ce qu'elle reprenne
`aria-labelledby`.

**Vérifié** (Chromium, 1440 px, pointeur fin puis grossier par émulation tactile) : case, libellé et
rangée aux mêmes coordonnées que `e6c146a` sur les 14 valeurs `Control` (case x=40, libellé x=61,
28 px, 37,8 px au pointeur grossier) ; fin du compteur inchangée (352,3 px), son début bouge de
−2,5 px à +0,3 px sous l'effet des chiffres tabulaires, seul écart voulu. Arbre d'accessibilité :
`checkbox "Initech"`, description « 12 résultats », `StaticText "12 résultats"` présent et non ignoré ;
`checkbox "15ml"`, description « 5 résultats », nœud du compteur non ignoré malgré le masquage. Clic
sur le compteur → cochée, sur le libellé → décochée, sur la case → cochée ; pastille cochée au clic.
Initech + Appliquer : Cheveux passe de « 14 résultats » à « 2 résultats », nom inchangé. Zéro erreur
console.

**Tests** : `FacetComponentTest` — `it_names_a_value_with_its_label_alone` (`aria-labelledby` pointe,
dans la même valeur, un élément dont le texte est exactement le libellé résolu par le listing),
`it_describes_a_value_with_the_count_its_label_holds` (`aria-describedby` pointe un compteur du même
`<label>`), `it_gives_every_name_and_every_count_its_own_id`, `it_leaves_every_computation_to_the_component` ;
les deux premiers échouent sur la vue de `e6c146a`. `ElementIdTest` : l'id du libellé entre dans la recherche de collisions. TS : `facets-view`
— écrire un compteur laisse le `<span>` du libellé en place, même nœud, même texte (échoue si la vue
écrit dans le `<label>`) ; `stylesheet` — chiffres tabulaires, compteur masqué mais présent en
pastille, visible hors pastille. `composer check` vert (Unit 294, TS 328), suite `Modules` 458 verts.

**Passes.** Lisibilité : `facetValueLabel()` suit `facetCount()`, les méthodes du composant suivent
`inputType()`/`countLabel()` ; l'assistant de test `shownLabelOf()`
lit le libellé côté listing (`array_find`, sans saut de boucle). Commentaires : aucun dans la vue ni
la feuille ; docblocks de test de contexte seulement. Performance : un attribut et un id par valeur,
aucun JavaScript. Sécurité : l'id reprend le slug déjà échappé du compteur. Contexte et i18n : aucune
chaîne ; ajout neutre en RTL.

### R-150 · 🟡 · ouvert · 2026-09-22 — MeiliScout teste une constante en majuscules et la lit en minuscules

`Config::get()` (`meiliscout/src/Config/Config.php:24-26`) teste `defined(strtoupper($key))` puis
renvoie `constant($key)`, la clé d'origine en minuscules. Une constante `MEILISCOUT_ASYNC_INDEXING`
définie ferait donc lever `Undefined constant` au lieu d'activer l'indexation différée. Lu dans la
source, non mesuré en requête ; `configuration.md` le signale. Correctif amont d'une ligne
(`constant($constKey)`), à proposer sur `feat/meilifacets` comme `resolveIndexable()` avant lui.

### R-149 · 🟠 · **fermé le 2026-09-23** · ouvert le 2026-09-17 — une archive de marque affiche tout le catalogue

`/marque/globex` rend 16 cartes sur 5 pages et une plage de 0 à 199 €, comme `/boutique` ; `/boutique?marque=globex`
en rend 10, de 9 à 47 € (mesuré par `curl` le 2026-09-17). `ProductListing::currentAisle()` ne lit que
`product_cat` : sur une archive de marque, le filtre de base ne porte pas le terme du chemin. Trouvé par la
passe de conformité des retours de la PR #2.

**Étendue mesurée le 2026-09-23** : le défaut ne touche pas que les marques. Toute archive produit
qui n'est pas une catégorie servait le catalogue entier — `/marque/umbrella` 76 produits au lieu de 5,
`/marque/globex` au lieu de 10, `/selection/nouveautes` au lieu de 14,
`/mise-en-avant/meilleures-ventes` au lieu de 3 ; seule la catégorie était juste. Les
compteurs de facettes et les bornes du curseur suivaient : 0–199 € sur `/marque/globex` au lieu de
9–47 €.

**Depuis quand** : le 2026-09-04, jour où l'archive a été confiée au module — `76e60f7` côté module,
`400eed4` côté thème, qui place le listing dans `archive-product.blade.php`, gabarit de **toutes** les
archives produit. `currentAisle()` n'a jamais lu que `product_cat`. Avant cette date, la grille venait
de la requête WordPress, qui filtrait juste. Le périmètre, lui, était déjà écrit : « sur les archives
produit, `ProductListing` aurait dû toutes les déclarer, puisque le projet de test y rend le listing partout »
(`decisions.md`, 2026-09-22).

**Corrigé** : `currentAisle(): ?string` devient `browsedTerm(): ?WP_Term` — `is_tax()` sur n'importe
quelle taxonomie, `get_queried_object()`, et une garde `is_object_in_taxonomy('product', …)` : un
produit ne porte aucun champ pour une taxonomie qui n'est pas la sienne, et filtrer dessus viderait le
listing. `baseFilter()` écrit `facets.<taxonomie> = <slug>`.

**Tranché par Louis le 2026-09-23** : une facette dont le chemin épingle la taxonomie n'est plus
offerte. Mesuré avant décision sur `/marque/umbrella` : la facette Marque ne proposait plus que « Umbrella
(5 résultats) », et `?marque=globex` y rendait zéro produit. `Facet::narrowsUnder()` répond non quand
sa taxonomie est celle du chemin, `ChildTermsFacet` répond oui — c'est ce pour quoi il existe (`D-07`
intact, mesuré : la catégorie propose toujours ses enfants). `ProductListing::facets()` filtre là-dessus,
`filters()` non : la facette **sort du plan de requête** mais reste plaçable par son nom, sans quoi un
gabarit qui l'appelle explicitement lèverait « No facet named … ». Le choix porte sur le volume, à la
demande de Louis : le moteur ne compte plus sa distribution sur la recherche principale **ni** sur la
requête non filtrée, et ses libellés ne sont plus lus — sur le projet de test l'écart est sous le plancher de
mesure (0–1 ms), il ne l'est pas sur un catalogue de milliers d'entrées. Effet de bord souhaitable :
`/marque/umbrella?marque=globex` rend les 5 produits de la marque au lieu d'une page vide, le paramètre
contradictoire n'étant plus lu.

**Recette, mesurée après correctif** : 5, 10, 14, 3 produits sur les quatre archives ci-dessus ; 1 sur
`/categorie-produit/maquillage/accessoires` et 16 par page sur `/boutique`, tous deux inchangés ; bornes
9–47 € sur `/marque/globex` ; facette Marque masquée sur son archive, catégorie intacte sur la sienne.

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
ligne `_price`. Produits variables et groupés non concernés. Aucun produit touché sur le projet de test. Tranché par
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
deux produits groupés du projet de test (#444, #445) n'ont aucun enfant gratuit.

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
partout. Aucun effet sur le projet de test (aucun widget de prix natif).

**Tranché par Louis, deux fois.** Le 2026-09-17 : chaque listing déclarerait ses pages. Le 2026-09-22, jamais
codée, cette décision est renversée : le composant peut être posé sur n'importe quelle page, WooCommerce ne
filtre que la requête principale des archives produit, et `ProductListing` aurait dû toutes les déclarer —
une méthode de plus dans le contrat pour une portée identique (`decisions.md`, « Le filtrage natif reste
désarmé sur toutes les archives produit »). La passe de conformité avait mesuré que les sept pages du
projet de test qui rendent le listing sont toutes des archives produit.

**Corrigé — sans changement de comportement** : `NativeFiltering` rend toujours `false`. Son docblock passe de
huit lignes, dont une portée fausse (« inert on every other listing »), à une exacte. `configuration.md` dit la vraie portée — archives produit seulement
(`class-wc-query.php:381-452`), crochet `posts_clauses` jamais retiré (`:588`) et question reposée à chaque
`WP_Query` suivante sans `suppress_filters` —, corrige deux erreurs de plus (`filter_*` n'est désarmé qu'avec la
table de correspondance des attributs, active sur le projet de test, `Filterer.php:70-72`, sinon `tax_query` à
`:915-917` ; `rating_filter` est une `tax_query` sur les termes `rated-N`, pas une `meta_query`) et remplace
l'exemple `__return_true` par un filtre qui réarme une seule archive, avec l'avertissement : ni
`price_filter_post_clauses()` ni `filter_by_attribute_post_clauses()` ne regardent le type de contenu.

Tests (`NativeFilteringTest`, réécrit) : le filtre est interrogé comme WooCommerce l'interroge, avec une
`WP_Query` posée en requête principale, et non plus avec `null` ; le retour arrière documenté réarme une
catégorie et laisse la boutique désarmée ; ignoré sans WooCommerce ou sans catégorie. Mutations : le module
qui rend `true`, ou qui passe après le projet (priorité 30), fait échouer un test.

**Cinq passes** (`module-review`). *Lisibilité* : l'exemple visait `product_tag`, sans terme sur le projet de test, et le
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
(`R-137` « Déjà connus », `R-121`). Dormant sur le projet de test (taxes désactivées).

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
session, comme la carte le fait déjà ; aucune sur le projet de test.

**Vérifié le 2026-09-22 sur le projet de test** (taxes désactivées) : en mémoire, par `MeiliScoutBridge::addPrice()`,
les 76 produits publiés ont le même prix indexé qu'avant (`===`), sans aucune écriture en base ; index
reconstruit (`ddev wp meiliscout index --clear`) avec le nouveau code : les 76 prix identiques produit par
produit ; `/boutique` 16 cartes, plage 0–199 €, `?max_price=10` 6 cartes, `?marque=globex` 10 cartes, 9–47 €.
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
supposait une locale — « 234,00 € » était trouvé dans « 1 234,00 € », le séparateur de milliers du projet de test
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
avant) et `/marque/globex` répondent 200. Aucun JavaScript touché. Reste ouvert à côté : `R-154`.

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
| catégories sous `?marque=umbrella` | 0 · 1 · 1 · 1 · 2 | — | identiques |
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
redirection pointe vers un hôte inexistant (`projet.ddev.site` et `boutique` collés), que la garde anti-chaîne
annule. Côté module, `pagePath` écrit déjà `/boutique` ; correctif à proposer en amont (§2). Ne couvre pas `//?s=…`,
que `redirect_canonical` ignore. Trouvé par la passe de conformité des reliquats de `R-137`.

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
de ses taxonomies de produits (`categories`, `brands`, `filter_product_highlight`…), que `get_taxonomies()` ne
connaît qu'après `init`. La liste lue avant `init` n'est pas gardée ; celle d'après est dédoublonnée (117
entrées filtrées, 95 noms) et gardée pour l'instance. Le motif des bornes de prix est testé avant les query
vars, pour rester « lu dans `$_GET` par WooCommerce » maintenant qu'elles y figurent. Sur une requête,
`pageQuery` est inchangé ; sur le projet de test la commande passe toujours (`min_price`, `max_price` acceptées par
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
le projet de test les six chaînes sortent identiques (`APP_LOCALE=fr`, WordPress en `fr_FR`) ; sur un hôte resté en
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
désormais `langue_RÉGION`. Ce repli n'est pas testable là où `ext-intl` est chargé. Aucun texte visible ne change sur le projet de test ;
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
| 1 | Le client ignore `/page/N` : le serveur lit `paged`, le client seulement `pg` | `/boutique/page/2`, cocher une marque : l'URL devient `/boutique/page/2?marque=globex`, recharger affiche « Il n'y a rien sur cette page » ; Retour ramène la page 1 au lieu de la 2 |
| 2 | Une valeur de facette absente du premier rendu ne revient jamais (le client ne crée pas de nœud) | `/boutique?marque=globex` puis « Tout effacer » : 4 catégories sur 5, 7 contenances sur 24, plus de « Voir plus » — contredit par la décision « le client révèle, il n'en crée aucun », qui prévoyait de rouvrir « si le catalogue réel le montre » |
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

**Déjà connus** : taxes retirées côté serveur seulement (`D-e`, sans effet sur le projet de test, taxes
désactivées — fermé par `R-146`, le prix indexé étant désormais le prix affiché) ; `alt` `'0'`.

**Identiques** (vérifiés en exécutant) : échappement, assemblage et ordre des clauses, recherches à part,
tri, lecture des valeurs de facette, requête texte, page (hors cas ci-dessus), prix (hors cas ci-dessus),
formatage des bornes, compteurs, pagination, contrôle de prix et ses attributs, tri, badge, cartes.

**Corrigé le 2026-09-16**, sans décision à prendre (« corrige ceux où tu n'as pas besoin de moi ») —
chaque correction a un test, vérifié en échec sans elle :

| # | Correction | Test | Vu dans Chrome |
| --- | --- | --- | --- |
| 1 | *Première version :* `ListingUrl` lisait `/page/N` avec un motif `/page/` en dur. *Depuis le 2026-09-17 :* le serveur publie le chemin de la première page (`pagePath`, `Http\PageAddress::path()`, soit `get_pagenum_link(1)`), qui suit le vrai nom du segment de pagination et retire les barres de tête ; le client écrit ce chemin puis ses paramètres, la page en `pg` | `PageAddressTest` (segment renommé, `//boutique/page/2`, `//?s=creme`, permaliens fixés en mémoire), `ListingDescriptionTest`, `listing.test.ts`, `listing-url.test.ts` | `/boutique/page/2` : page 2 marquée ; Suivant puis Retour ramène la page 2 ; cocher une marque donne `/boutique?marque=globex`, page 1 |
| 3 | `FacetsView` ne replie plus une valeur cochée, qui garde sa place avant le pli (*rectifié le 2026-09-17 : la première rédaction disait qu'elle ne comptait plus dans la place, ce que contredit le test « counts a held value among the places before the fold »*) | `facets-view.test.ts` | `/categorie-produit/visage`, trois contenances repliées cochées : 13 valeurs, aucun bouton, comme le serveur ; trois visibles cochées : 10 valeurs et « Voir plus » des deux côtés |
| 5 | Blade rend les deux messages, chacun sous son crochet (`no-results`, `past-the-end`), l'un masqué ; `ResultsView` révèle celui de `PageWindow.isPastTheEnd`, copie de `Pagination::isPastTheEnd()` ajoutée aux cas partagés `tests/pagination-cases.json`. Le client n'écrit aucun texte : une première version réécrivait le contenu de `empty` et aurait effacé le markup d'une vue surchargée (passe « contexte ») | `ResultsComponentTest`, `listing-binding.test.ts`, `page-window.test.ts`, `PaginationTest` | `/boutique?pg=99` puis prix mini 1000 : « Aucun résultat n'a été trouvé. » ; retour sur `?pg=99` : « Il n'y a rien sur cette page. » |
| 6 | *Première version, retirée le 2026-09-17 :* tout paramètre que le listing ne possède pas était recopié, `add-to-cart` et `utm_*` compris. *Depuis :* seuls les paramètres que WordPress a lus pour construire la page, publiés par le serveur tels qu'envoyés (`Http\PageAddress`), hors ceux du listing et `paged`. Une version intermédiaire lisait `request()->query()`, déjà rogné et vidé par les middlewares : `/?s=&post_type=product` perdait `s=` et le premier geste servait la boutique (trouvé par les cinq passes). Trois relectures au total : la deuxième a fait tester l'exclusion d'un nom de facette devenu query var publique, que rien ne protégeait, rendu `pageQuery` obligatoire et suivi `arg_separator.input` ; la troisième, sur ces corrections, trois retouches de commentaires et de test. Mutations : 7, toutes tuées | `PageAddressTest`, `ListingDescriptionTest`, `listing-url.test.ts`, `listing.test.ts` | `/boutique?add-to-cart=999999&utm_source=news`, cocher une marque : `/boutique?marque=globex` ; `/?s=creme&post_type=product&utm_source=news`, trier : `/?sort=price_asc&s=creme&post_type=product` (16 cartes) ; `/?s=&post_type=product&utm_source=news`, trier : `/?sort=price_asc&s=&post_type=product`, toujours une recherche au rechargement ; `/boutique?min_price=10&utm_source=x` publie `pageQuery` vide |
| 7 | `StateReader` écarte une valeur qui n'est pas de l'UTF-8 (*la moitié client, une valeur remplacée par U+FFFD, a disparu avec `url-text.ts` : le client ne lit plus l'URL*) | `StateReaderTest` | `/boutique?marque=%FF` : 200, 16 cartes ; `?marque=globex,%FF` filtre sur globex |
| 8–10 | *Première version, retirée le 2026-09-17 :* le client lisait l'URL avec une copie en TypeScript des règles de PHP (`url-text.ts`). *Depuis :* le client ne tire plus aucun état d'une URL, il part de l'état publié par le serveur (`state` dans la description) ; valeurs triées par octet côté serveur (`SORT_STRING`) ; `-0` tenu pour `0` | `StateReaderTest` (18 URL tapées à la main, `assertSame`), `ListingDescriptionTest`, `tests/url-writing-cases.json` lu des deux côtés | `/boutique?marque=nord-sel,globex,globex&pg=abc&sort=inconnu` : état publié `globex, nord-sel`, page 1, pas de tri ; cocher `umbrella` écrit `?marque=globex,umbrella,nord-sel` |
| 11 | Retour et Suivant : l'état est rangé dans l'entrée d'historique, sous le nom du listing, et restauré sans réécrire l'entrée ; une entrée qu'aucun listing n'a écrite reçoit l'état affiché à la même adresse, recharge la page sinon ; pas de recherche vers un état déjà affiché | `browser-history.test.ts` (10 cas, onglet simulé), `listing.test.ts` | `/boutique/page/2`, Suivant, tri, Retour : adresse `/boutique/page/2` gardée, page 2, Pertinence ; lien d'évitement `#main`, Retour, Suivant : ni rechargement ni recherche ; entrée d'un autre script à une autre adresse : rechargement, le serveur relit `?marque=globex` |

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
→ regroupée dans `PageAddress`, des tests qui ne passaient que grâce à la structure de permaliens du
projet de test → structure fixée en mémoire (`RequestsAnAddress`). **Incident** : pour vérifier les permaliens
simples, l'agent de revue a enregistré une structure vide en base (~3 min) ; restaurés : l'option, la
config WP Rocket, les 24 indexables Yoast (`ddev wp yoast index`). Vérifié dans Chrome : `//boutique`, filtrer
→ `/boutique?marque=globex` ; `/boutique/page/2`, Suivant → `/boutique?pg=3`, Retour → `/boutique/page/2`.

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
bloquée par le watchdog et relancée). Vu dans Chrome : `/boutique?marque=globex` rend 5 catégories et 24
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
  *Sécurité* : rien — par `curl`, `?marque=%FF` rend 200 et `?marque=globex,%FF` publie l'état `globex`.
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
`/boutique?marque=globex&max_price=44` : grille du serveur (plan PHP) identique à celle de la première
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
`card.image_alt ?? card.title` : un `image_alt` indexé vide — le cas de « Crème hydratante » — donne
le titre au premier rendu et un `alt` vide dès la première recherche. L'image perd son nom pour un lecteur
d'écran.

**Corrigé** (demandé par Louis) : le rendu du module suit `CardImage::from()` — `image_alt ?: titre` — et
`CardView` fait désormais de même (`image_alt || titre`). Écart résiduel assumé : PHP tient aussi la chaîne
`'0'` pour vide, pas JavaScript. Test ajouté, qui échoue sur l'ancienne règle. Vérifié dans Chrome sur
`/boutique?marque=globex` : « Crème hydratante » garde son `alt` après deux tris, aucune image de la
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
vient du code. *Contexte* : rien sur le projet de test ; deux constats refusés ci-dessous.

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

Mesuré sur le projet de test :

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
`immediate` dans `config/meilifacets.php` du projet de test le temps de la mesure, puis remis à `submit`. Dix
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

Vérifié dans Chromium sur `/categorie-produit/parfum`, où Umbrella n'a qu'un produit, à 38,00 € : cocher
Umbrella masque le bloc sans recharger ; décocher le réaffiche ; la même URL rendue par le serveur le masque
aussi ; et depuis ce rendu masqué — bornes vides dans le HTML — décocher Umbrella rend la piste à 19–199 €,
poignées aux extrémités. Un test PHP et un test JS : 20–20 masqué, 20–20,40 dessiné en 20–21.

**Passes.** Lisibilité : une condition de plus à l'endroit qui décide déjà qu'il n'y a rien à dessiner.
Commentaires : aucun ajouté. Performance : aucune. Sécurité : aucune. Contexte : une plage tenue dans
l'URL sur un listing à prix unique n'est plus modifiable depuis le bloc masqué ; « Tout effacer » reste
disponible — même limite chez WooCommerce.

---

**Confirmé le 2026-09-25 (Louis, étape 4d)** : on garde cette limite — une plage de prix tenue aux bornes vides masque le bloc prix, elle reste retirable par sa pastille et par « Tout effacer ».

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

Vérifié dans Chromium sur `?min_price=0.0000001&max_price=60`, puis Globex coché : filtre envoyé
`price.max >= 0`, aucune réponse d'erreur du moteur, URL réécrite `?marque=globex&min_price=0&max_price=60`.
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

Vérifié dans Chromium : plage seule → « 1 filtre actif » ; plage et Globex → « 2 filtres actifs » ; la
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
les deux ramenées au bord → URL vide ; saisie de 199 dans « À » → URL vide ; sous Globex, poignée haute
poussée à 47 → `?marque=globex`. Trois tests JS, dont le mode champs seuls ; un test existant attendait
`[0, 89]` et attend désormais `[null, 89]`, ce qui est exactement le changement.

**Passes.** Lisibilité : `#commitFields()` délègue à `#asked()`, 10 lignes ; `#number()` factorise la
lecture tolérante des deux attributs. Commentaires : une ligne, la raison des bornes ouvertes. Performance :
une comparaison par borne à la validation. Sécurité : la valeur saisie reste relue par `ListingState`,
qui refuse ce qui n'est pas un prix. Contexte : aucun.

---

### R-121 · 🟠 · **fermé le 2026-09-16** · ouvert le 2026-09-16 — le client ne remesure jamais les bornes de prix

Reproduit dans Chromium : sur `/boutique`, cocher Contoso puis appliquer. La grille se
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

Vérifié dans Chromium sur le code publié : Contoso → piste et champs à **43 – 199 €** sans
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

Vérifié dans Chromium sur `?marque=globex` (prix de 9,50 à 46,40 €) : piste **9 – 47 €**, poignées
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

**Mesure, produit 362 « Huile de nuit », promotion programmée qui démarre :**

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
`ShopFacets`, que le projet de test binde par-dessus `ProductFacets`
(`AppServiceProvider:30`). Le `WooCommerceFacets` du module, lui, déclare deux facettes et n'en
nomme aucune : leurs noms sont `product_cat` et `product_brand`. **Ajouter une quatrième facette à
la boutique cassait la suite du module** — l'inverse de la règle du `CLAUDE.md` : « A local project is its
test bed, not its owner. »

Correctif en deux temps :

- les tests `Feature` lisent noms et comptes **à l'exécution** — `$this->first()`,
  `$this->declaredCount()`, `$listing->name()` — au lieu de les recopier ;
- les règles de placement elles-mêmes ont quitté la suite `Feature` pour `Unit\FacetPlacementTest`,
  qui monte un `ResolvedListing` sur des facettes **qu'il déclare lui-même** (`FakeListing`), sans
  conteneur, sans Blade et sans le projet de test. Ce qui reste en `Feature` ne prouve qu'une chose : que le
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

La suite partage une seule application pour tout le run (consigné dans les notes du projet de test), donc
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

Relevé par Louis sur capture, cause confirmée par lui puis mesurée : **les métriques de la police du thème**.
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

Un texte sans jambage laisse vides les 3 px que la police réserve en bas et remonte d'autant ; le même
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
2026-09-06, voir D-01.** Module Pollora générique, le projet de test en banc d'essai. Le module porte le
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
*Répondue le 2026-09-30 (`R-203`) : celle du thème, comme `decisions.md` le disait ; le module la rend possible par la liaison d'attributs, sans rien connaître d'elle.*

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
| T-02 | Trancher Q-05, Q-06, Q-10 (forme du listing) | R-10, R-11, R-45 | fait — Q-05 et Q-06 réglées par D-07, Q-10 par `R-203` (2026-09-30) |
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
| T-08 | Filtres actifs en puces retirables | R-47 | **fait le 2026-09-24** — étape 2b du chantier `R-48` |
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
vérifierait alors ses vues partout, et non seulement dans le projet de test. Ouvert par T-38.

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
  chercher à l'écrire sans dépendre du projet de test a fait apparaître R-55 — le module n'a ni
  `require-dev`, ni `phpunit.xml`, ni `pint.json`, et deux de ses tests dépendent du `TestCase` du
  projet. Total : 55 constats, 5 décisions, 30 questions, 38 tâches.
- **2026-09-06** — T-38 livré, R-55 fermé. Le module a ses propres `require-dev`, `phpunit.xml`,
  `pint.json`, `rector.php`, `tests/bootstrap.php` et scripts Composer ; `composer check` est vert
  et ne dépend d'aucun chemin. Rector appliqué sur trois fichiers, quatre de ses règles écartées
  nommément. Boucle de retour : 38 ms pour 103 tests autonomes, contre 2,9 s via le projet. La
  suite du projet reste verte (121 tests). I-09 ouvert. D-06 : le repli `__()` du bootstrap de
  test est assumé et documenté, après vérification qu'aucun précédent n'existe dans le projet —
  l'autre module du projet n'a aucun test, et un plugin du projet fait le choix inverse en logeant
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
  thème ne remet pas les polices de formulaire à plat — le projet de test le fait, d'où l'illusion.
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
  hériter des 18px du projet de test donnait des facettes plus grosses que ce qu'elles filtrent. Mesuré
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
