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
(R-35). Écrire la décision avant de la vérifier a produit un corpus qu'il faut désormais auditer
comme du code.

Un point d'attention qui n'est écrit nulle part : **le transport direct navigateur → moteur rend
cosmétique tout filtre de sécurité posé côté serveur** (R-28). `post_status = "publish"` sera une
chaîne dans une requête que le visiteur contrôle.

Aucun de ces points ne remet en cause l'architecture. Le découpage en sept lots reste bon ; c'est
l'ordre à l'intérieur du lot 3 qui a été pris à l'envers — le contrat de liaison a été livré avant
que le rendu qu'il contractualise soit juste.

---

## 0. Décisions prises pendant la revue — 2026-09-06

Quatre réponses données en séance. Elles ferment ou réorientent les questions citées.

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
passe aujourd'hui de placeholder — `trans_choice()` est employé pour les pluriels, et son seul
appel est dans `Facets`, qui relève de la suite `Feature`. Le jour où un test autonome portera sur
une chaîne à placeholder, c'est I-10 qu'il faudra ouvrir, pas étendre le repli à l'aveugle.

### D-07 — La catégorie reste une facette, mais contextuelle : `ChildTermsFacet`

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

### R-03 · ⚪ · ouvert · 2026-09-06 — `Contract` n'est pas une énumération et vit dans `app/Enums/`

`Modules\MeiliFacets\Enums\Contract` est une `final readonly class`. Elle appartient à
`app/View/` ou à un `app/Contract/` dédié, avec `Hook`.

### R-04 · 🟡 · **fermé le 2026-09-07** · ouvert le 2026-09-06 — service locator dans les composants Blade

`ListingComponent::listing()` fait `app(CurrentListing::class)` et `Results::itemList()` fait
`app(RobotsPolicy::class)`. Les composants Blade de Laravel résolvent depuis le conteneur tout
paramètre de constructeur qui n'est pas passé en attribut : l'injection est disponible, elle n'est
pas utilisée. Effet direct : ces deux composants ne se testent qu'avec une application bootée.


**Fermé le 2026-09-07** — par R-62, sans que ce constat soit mis à jour. Relevé par la passe de
conformité de la documentation : le code qu'il décrit n'existe plus.
### R-05 · 🟠 · **fermé le 2026-09-07** · ouvert le 2026-09-06 — la configuration est lue depuis les objets de domaine

`ApplyMode::fromConfig()`, `UrlParameters::fromConfig()`, `Results::eagerCards()`,
`ProductListing::applyMode()`, `Card` (rien) : quatre points où `config()` est appelé depuis un
objet qui n'a rien à voir avec le conteneur. Une énumération qui lit la configuration est une
dépendance globale déguisée en valeur.

Le contournement du piège nwidart (ne rien déclarer dans `config/config.php`, lire le défaut dans
le code) est juste — c'est **l'endroit** de la lecture qui est discutable. Le provider est le seul
qui devrait lire `config()`, comme il le fait déjà bien pour `DefaultCardProjector`.


**Fermé le 2026-09-07** — par R-62, sans que ce constat soit mis à jour. Relevé par la passe de
conformité de la documentation : le code qu'il décrit n'existe plus.
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

### R-07 · 🟠 · ouvert · 2026-09-06 — `facets()` est appelée six fois par requête, sans mémoïsation

Sites d'appel relevés en lecture, pour un seul rendu : `StateReader::facets()`,
`QueryPlan::fieldsCountedOnMain()`, `QueryPlan::facetClauses()` (une fois pour la requête
principale, plus une par facette comptée à part), `DisjunctiveFacetCounter::queries()`,
`ListingSearch::distributions()`, et la vue `facets.blade.php`. Chaque appel de
`ProductListing::facets()` refait un `is_tax()`, trois `__()` et trois `new Facet`. `sorts()` :
trois sites.

Rien de dramatique en volume absolu, mais c'est la règle « compter les appels, pas les lignes » du
`CLAUDE.md` du module qui n'est pas tenue par le module lui-même. Une mémoïsation dans
`ResolvedListing` (ou dans le `QueryPlan` de R-01) supprime le problème et le rend impossible à
réintroduire.

### R-08 · 🟡 · ouvert · 2026-09-06 — le provider fait six choses

`MeiliFacetsServiceProvider` porte les bindings, l'enregistrement des listings, la déclaration de
la discovery, la cascade de vues du thème, la publication des assets et les commandes. 147 lignes,
lisibles, mais c'est le fichier que personne n'ose plus toucher. Trois providers (bindings,
listings, vues/assets) coûteraient moins cher à faire évoluer.

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

### R-35 · ⚪ · ouvert · 2026-09-06 — `config/config.php` existe pour ne rien déclarer

Le fichier ne porte plus que `'name' => 'MeiliFacets'`, dont `configuration.md` dit lui-même que
c'est une « clé de nwidart, sans usage dans le module ». Il reste utile comme porte-commentaire de
la règle « un réglage déclaré ici n'est pas surchargeable » — à dire explicitement, ou à supprimer.

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

Ce point n'est pas une commodité : c'est ce qui empêche aujourd'hui de dire « voilà ce qui est
livré ».

### R-39 · 🟠 · ouvert · 2026-09-06 — `amphibee/meiliscout` pointe une branche non mergée

`dev-feat/meilifacets`, commit `1c59a05`. Rappel : sans le correctif `resolveIndexable()`, les
facettes cassent à la première sauvegarde de contenu.

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

### R-43 · 🟠 · à trancher (Q-08) · 2026-09-06 — aucune facette prix ni disponibilité

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

### R-48 · 🟡 · ouvert · 2026-09-06 — rien pour le mobile

Pas de composant de bascule, pas de tiroir de facettes, pas de crochet prévu. Sur un thème
e-commerce, c'est la moitié du trafic, et la colonne de facettes de
`archive-product.blade.php` (`lg:col-span-1`) est simplement empilée au-dessus de la grille en
dessous de `lg`.

### R-49 · 🟡 · ouvert · 2026-09-06 — le cul-de-sac « zéro résultat » est atteignable en deux clics

Quand la recherche ne rend rien, toutes les distributions sont vides, donc tous les `<fieldset>`
sont `hidden` (`@if ($values === [])`), donc **il ne reste que « Tout effacer »**. Le comptage
disjonctif couvre le cas où l'on relâche une valeur de la facette qui contraint ; il ne couvre pas
le croisement de deux facettes. C'est documenté comme « limite assumée » — mais aucune mesure ne
dit à quelle fréquence le cas est atteint sur un vrai catalogue, et un message « aucun résultat »
sans aucune facette visible est un mur.

### R-50 · ⚪ · ouvert · 2026-09-06 — `ItemList` ne publie pas `numberOfItems`

Détail SEO, une clé.

### R-51 · 🟡 · ouvert · 2026-09-06 — le mode `submit` par défaut est peut-être le mauvais défaut ici

`apply_mode` vaut `submit` par défaut, justifié par « un gros catalogue, où chaque case cochée
coûterait une recherche ». Le catalogue de ce projet compte 76 produits publiés (vérifié). Sur ce
volume, `immediate` est probablement le bon réglage — et le bouton « Appliquer les filtres » est
aujourd'hui rendu et inerte.

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


### R-70 · 🟠 · ouvert · 2026-09-07 — seul le point d'entrée du client est versionné ; les dix-sept autres fichiers sont figés dans le navigateur

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

**À décider — hors du lot 3c-2.** T-39.

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

### R-78 · ⚪ · ouvert · 2026-09-07 — la règle du pluriel est anglaise des deux côtés, les chaînes ne le sont pas

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

### R-87 · 🟠 · ouvert · 2026-09-08 — `DisplayOrder::Name` classe mal les libellés accentués

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

Deux réponses, non exclusives :

- **passer catégorie et marque à `DisplayOrder::Declared`** — zéro ligne, la collation de la base
  range le français correctement, et un ordre posé dans l'admin serait honoré au passage
  (WooCommerce rend `product_cat` triable par glisser-déposer) ;
- **réparer `Name`** avec `Collator` quand `intl` est disponible, repli sur `strnatcasecmp` sinon.
  `Name` existe pour le cas numérique (`10ml` avant `500ml`), que `Declared` ne sait faire que si
  l'attribut est réglé sur *Nom (numérique)*. `intl` n'étant pas garanti sur un hébergement
  quelconque, il faut un `class_exists('Collator')` et deux comportements documentés.

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
propre `Listing` pour choisir ses facettes. `R-89` (ce qu'un gabarit rend) reste ouvert.

### R-89 · 🟠 · ouvert · 2026-09-08 — un gabarit ne peut pas choisir les facettes qu'il rend

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

**Q-05 · Que devient une facette mono-sélection ?**
Trois issues à R-10 : (a) comptage disjonctif pour toutes les facettes, mono comprise ; (b) la
catégorie cesse d'être une facette et devient une navigation par liens sur le chemin — ce qui est
déjà à moitié le cas puisque la facette se retire sur une archive de catégorie ; (c) on garde le
comportement actuel et on l'écrit comme une limite. Ma préférence : (b) pour la catégorie,
(a) comme règle générale.

**Q-06 · La facette catégorie doit-elle restituer la hiérarchie ?**
Si oui, le modèle éprouvé est un champ par niveau (`facets.product_cat_lvl0/1/2`), ce qui change
la projection d'indexation — donc c'est une décision de lot 1, à prendre avant d'écrire le client.
Si non, il faut assumer une liste plate plafonnée à 30 sur plus de cent termes. *Cite : R-11.*

**Q-07 · `ProductListing` reste-t-il dans le module ?**
S'il en sort, R-09 disparaît, la dépendance WooCommerce du module aussi, et le lot 4 devient un
travail de projet. S'il reste, il faut une garde que la découverte respecte
(`Listing::isAvailable()`).

**Q-08 · Prix et disponibilité : facettes tout de suite sur les produits simples, ou après le lot 4 ?**
Les attributs sont déjà filtrables et triables. Livrer une facette de tranches de prix et une case
« en stock » maintenant donne de la valeur immédiate ; le risque est de la refaire quand les
variations arriveront. *Cite : R-43.*

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

**Q-14 · (réorientée par D-01) Le contrat `data-meili` est acquis — comment le rend-on fiable
sans le rendre cher ?**
La surcharge par le thème est une exigence, donc le contrat reste. Ce qui est encore ouvert : sa
vérification tourne-t-elle en production ou seulement en `WP_DEBUG` ? Comment garantit-on que les
deux listes ne divergent pas (R-25, voir I-05) ? Et que fait-on des attributs hors contrat que le
client lit déjà — `data-taxonomy`, `data-apply`, `data-listing`, `data-value` (R-23) ?

**Q-15 · Accepte-t-on définitivement les deux implémentations du plan de requête, ou le serveur
émet-il le plan que le client rejoue ?**
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

---

## 11. Roadmap proposée

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
| T-02 | Trancher Q-05, Q-06, Q-10 (forme du listing) | R-10, R-11, R-45 | à faire |
| T-03 | Trancher Q-03 et Q-11 (modèle de sécurité) | R-26, R-27, R-28 | à faire |
| T-04 | Fixer le calendrier de montée de version du moteur (Q-12) | R-41 | à faire |
| T-31 | Fixer la ligne fonctionnement / apparence de la feuille de style (Q-28) | R-53 | **fait** — Q-28 répondue, R-53 fermé |
| T-32 | Fixer le critère de recette du socle (Q-04b) | — | à faire |
| T-33 | Mesurer le coût WordPress d'une URL de listing (Q-29, Q-30) | R-54 | à faire |
| T-35 | Outiller la méthode : `CLAUDE.md` v2, agents `conformity` et `module-review` | — | **fait** — D-05 |
| T-38 | Rendre le module vérifiable seul : `require-dev`, `phpunit.xml`, `pint.json`, scripts | R-55 | **fait** — `composer check` vert, sans chemin |
| T-36 | Hook `Stop` exécutant `composer check` | R-55 | **fait** — `.claude/settings.json` du module, versionné, sans chemin machine |
| T-37 | Purger les commentaires que la règle réécrite condamne | D-05 | **fait** sur le lot 3c-2 — 86 → 49 lignes de prose, plus aucun bloc de plus d'une ligne |

### Chantier B — rendre le socle serveur juste

| Id | Tâche | Ferme |
| --- | --- | --- |
| T-05 | Comptage disjonctif selon la décision de Q-05 ; radio décochable ou navigation par liens | R-10 | **fait** |
| T-06 | Facette catégorie : hiérarchie ou limite écrite | R-11 | **fait** |
| T-07 | Bouton de dépliage : crochet, vue, contrat, version | R-46 | **fait** — R-46 fermé, plus R-82 à R-86 relevés en chemin |
| T-08 | Filtres actifs en puces retirables | R-47 |
| T-09 | Réindexation sur `edited_term`, `delete_term`, `set_object_terms` | R-12 |
| T-10 | Timeout explicite sur le client Meilisearch + client construit par le module, pas par `ClientFactory` | R-19 |
| T-11 | `IndexingPolicy` ne décide qu'en présence d'un listing | R-16 | **fait** |
| T-12 | `Hook::from()` tolérant ; `array_combine` protégé ; hit sans `card` journalisé | R-17, R-14, R-13 |
| T-13 | `countId()` porte le nom du listing | R-15 | **fait** |
| T-14 | Tests du câblage : bridge, indexable, moteur, découverte, listing résolu | R-30, R-31 |
| T-15 | `preconnect` vers `MEILI_PUBLIC_URL` | R-52 | **fait** |
| T-34 | Feuille de style du module, selon la ligne fixée en T-31 — **tri, facettes, boutons et mise en colonnes des résultats livrés** | R-53 |

### Chantier C — livrer le client (ex-lot 3c)

| Id | Tâche | Ferme |
| --- | --- | --- |
| T-16 | Décider la forme du contrat de données serveur → navigateur (Q-15) | R-21 | **fait** |
| T-17 | Sérialiser connexion et description du listing | R-21 | **fait** |
| T-18 | Point d'entrée, inscription du script, démarrage sur vérification du contrat | R-20 | **fait** |
| T-19 | Les cinq gestes, le repeint, `popstate` | R-20 | **fait** |
| T-20 | Aligner `toggle()` sur la sélection mono, dédoublonner et plafonner côté JS | R-22 | **fait** |
| T-21 | Test croisé `Hook` / `contract.js` et `QueryPlan` / `ListingQuery` | R-25, R-32 |
| T-22 | Comportement après échec, simple et unique (Q-21) | — |
| T-23 | Retirer `Hook::PageTemplate` ou le rendre | R-24 | **fait** |

### Puis, dans l'ordre des lots

- **Lot 4** — prix, stock, variations. À rouvrir avec Q-08 : les produits simples peuvent être
  servis avant.
- **Lot 5** — recherche et suggestions. Prérequis : Q-09, et `searchableAttributes` (R-27).
- **Lot 6** — diagnostics. `meilifacets:doctor`, canal de log, messages avec `errorCode` /
  `errorLink`.
- **Lot 7** — réutilisabilité. Dépend entièrement de Q-01.

### Nettoyage, à faire au fil de l'eau

| Id | Tâche | Ferme |
| --- | --- | --- |
| T-24 | Supprimer les déclarations jamais lues | R-33 | **fait** |
| T-25 | Supprimer les `.gitkeep` et `.playwright-mcp` | R-34 |
| T-26 | Corriger les quatre affirmations fausses de la doc | R-36 |
| T-27 | Fermer la dette `product_tag => tag` (déjà corrigée en config) | R-36 |
| T-28 | Extraire un `QueryPlan` instanciable ; mémoïser `facets()` et `sorts()` | R-01, R-07 |
| T-29 | Objet `SearchRequest` typé à la place du tableau de plan | R-02 |
| T-30 | Déplacer `Contract` hors de `Enums` | R-03 |
| T-39 | Versionner les modules ES importés : un bundle au nom haché | R-70 | décidé le 2026-09-07, à faire |
| T-40 | Ramener le regard en haut du listing après pagination et tri | R-73 | **fait** |
| T-41 | Rendre l'ordre d'une facette réglable en configuration | Q-07 (partiel) | **différé après livraison** — décidé le 2026-09-08 |

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

**I-03 · Facette hiérarchique par niveau.**
`facets.product_cat_lvl0/1/2` à l'indexation, façon Algolia. Rend R-11 solvable, permet de ne
montrer que le niveau courant et ses enfants, et supprime le mélange de niveaux. Décision de lot 1,
à prendre avant le client.

**I-04 · `searchCutoffMs` côté moteur.**
Réglage d'index, non posé aujourd'hui (vérifié : `null`). Il borne le temps de recherche du moteur
lui-même et rend une réponse dégradée plutôt qu'une attente. Complément naturel du timeout PHP de
T-10.

**I-05 · Un test qui compare les deux contrats.**
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
