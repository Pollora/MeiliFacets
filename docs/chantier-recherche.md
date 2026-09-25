# MeiliFacets — chantier « recherche du site »

Voir aussi : [chantier-filtres.md](chantier-filtres.md) · [revue.md](revue.md) · [decisions.md](decisions.md) · [lots.md](lots.md)

Notes de cadrage du 2026-09-25, **avant tout code**. Ce document consigne le cahier des charges, ce qui
existe déjà (vérifié dans le code), une proposition d'architecture et les décisions à prendre. Il sera
transformé en plan d'avancement (sur le modèle de `chantier-filtres.md`) une fois les décisions prises,
après la passe de conformité imposée par `CLAUDE.md` § 1. Le chantier démarre après l'étape 8 des
filtres. Correspond au **lot 5** de [lots.md](lots.md) (« Recherche et suggestions »).

---

## Cahier des charges (Pluralia)

> La recherche recherche les produits et les articles.
>
> Le moteur de recherche s'appuie sur Meilisearch afin de garantir une recherche rapide et pertinente
> dans le catalogue et dans les articles du journal.
>
> Au clic de la barre de recherche, l'utilisateur peut saisir la donnée de son choix. Après avoir saisi
> plusieurs caractères, la recherche s'active pour proposer des résultats pertinents.
>
> AmphiBee affichera (s'il le peut) les 4 produits les plus pertinents avec le contenu saisi ainsi que
> 4 articles de journal les plus pertinents avec le contenu saisi.
>
> Si le moteur juge qu'il y a moins de 4 résultats pertinents alors il en affiche moins. Il est possible
> de n'afficher aucun résultat. Dans ce cas, AmphiBee affichera un message informatif « Aucun élément ne
> correspond à votre recherche ».
>
> Pour les produits et les articles, le moteur de recherche va comptabiliser le nombre de résultat
> associé à la saisie. Le bouton « voir tous les produits » affichera une page de liste présentant les
> résultats. L'intégration de la page sera similaire à la page de liste « Catégorie ». AmphiBee retirera
> la section hero, les filtres et les blocs de contenus pour n'afficher que les produits résultats et la
> pagination éventuelle.

### Lecture du cahier des charges

| Exigence | Traduction technique |
| --- | --- |
| Recherche hors listing, depuis la barre de recherche | une **modale** ouverte depuis l'en-tête, indépendante de tout `<x-meilifacets::listing>` |
| « après plusieurs caractères » | seuil minimal de saisie avant la première requête (valeur à trancher) |
| 4 produits + 4 articles « s'il le peut », moins si moins pertinents, aucun possible | une requête **multi-search** : deux sous-requêtes (`post_type = product`, `post_type = post`), `limit: 4` chacune |
| « comptabiliser le nombre de résultats » pour produits et articles | le **total par type** (`estimatedTotalHits` de chaque sous-requête), affiché dans chaque groupe |
| « Aucun élément ne correspond à votre recherche » | message quand les deux groupes sont vides (libellé traduisible, surchargé par le thème) |
| « voir tous les produits » → page de liste façon « Catégorie », sans hero, filtres ni blocs de contenu | une page de résultats **produits** : grille + pagination seulement |

---

## Ce qui existe déjà (vérifié le 2026-09-25)

| Élément | État | Source |
| --- | --- | --- |
| `/?s=terme&post_type=product` | **déjà servi par Meilisearch** via le listing produit : `ProductListing::baseQuery()` lit le `s` de WordPress quand `is_search()` | `app/Listing/ProductListing.php:102-104`, `R-158` |
| `/?s=terme` sans type de contenu | recherche **native** WordPress, gabarit de recherche du thème ; `ListingPage::isCurrent()` la compte pourtant comme listing | `R-161` |
| Paramètre `q` du module | lu, borné à 200 caractères et envoyé au moteur, mais **aucun champ ne le saisit ni ne l'affiche** | `R-44`, question `Q-09` |
| Pertinence | `searchableAttributes: ["*"]` : pas de hiérarchie des champs, et les métadonnées internes restent **interrogeables** par la clé publique | `R-27` |
| Exclusions WooCommerce | un produit `exclude-from-search` reste trouvable par le module | `R-160` |
| Latence serveur | `ClientFactory` de MeiliScout fait un `checkdnsrr` puis un `GET /health` devant la première recherche de chaque processus | `R-19` |
| Contenus indexés | option `meiliscout/indexed_post_types` = `post`, `product` (pas de pages) ; `meiliscout/indexed_taxonomies` **vide** depuis l'import de base du 2026-09-24 | options WordPress |
| Interception de `WP_Query` par MeiliScout | `QueryIntegration` (`posts_pre_query`) n'agit que si la requête porte `use_meilisearch`, appelle `search('', …)` (**ignore le terme recherché**) et reconstruit les `WP_Post` depuis l'index, qui n'expose plus que `ID` et `card` : **inutilisable telle quelle** pour une recherche texte | `meiliscout/src/Query/QueryIntegration.php:50-111` |
| Rôle de MeiliScout | décision validée : **indexation seule** | `decisions.md` « Validées » |
| Briques réutilisables du module | client Meilisearch navigateur, clé de recherche seule publiée, annulation des requêtes obsolètes, description JSON, `CssTiming`, `Entrance`, tokens de mouvement, cartes produit (`card`) | chantier filtres |

---

## Proposition d'architecture

### 1. Modale de recherche (client)

- La loupe de l'en-tête ouvre une modale. Un **`<dialog>` natif** convient ici (piège du focus, Échap,
  couche supérieure) : contrairement au tiroir des filtres, son contenu n'a pas à exister ailleurs dans
  la page.
- Au-delà du seuil de saisie, le navigateur interroge Meilisearch **directement** (clé de recherche
  seule, aucun proxy PHP, comme le listing), après une courte temporisation (~120 ms), en **annulant**
  les requêtes dépassées.
- Une requête **multi-search** : produits (`limit: 4`, carte avec vignette et prix depuis `card`) et
  articles (`limit: 4`, titre, extrait, image), chacun avec son **total**. Termes surlignés
  (`_formatted`).
- « Voir tous les produits (N) » mène à la page de résultats produits. Côté articles : voir la
  décision D-3 ci-dessous.
- Accessibilité : motif APG « combobox + listbox » (ou liste de liens groupés, à arbitrer à l'étape
  d'accessibilité), flèches, Entrée, Échap, **annonce** du nombre de résultats (`aria-live`), message
  « Aucun élément ne correspond à votre recherche ».
- Performance : le code de la modale est **chargé à la première ouverture** (voire au survol / focus de
  la loupe), rien sur les autres pages. Objectif du lot 5 : latence frappe → affichage mesurée et tenue
  sous un seuil (proposition : 100 ms au 95ᵉ centile en local).
- Mouvement : entrée/sortie selon les tokens du module et les principes déjà appliqués au tiroir
  (Emil), variante mouvement réduit.
- Module neutre, thème qui habille (mêmes règles que le chantier filtres).

### 2. Page « voir tous les produits »

- URL : `/?s=terme&post_type=product` — **déjà servie par le listing produit** (`R-158`).
- Le thème lui donne un gabarit dédié : grille + pagination seulement, **sans hero, sans filtres, sans
  blocs de contenu**, sur le modèle de la page « Catégorie » (cahier des charges).
- Restent à régler pour cette page : `R-160` (exclusions `exclude-from-search`), `R-44`/`Q-09` (un seul
  paramètre de terme : `s`), la pertinence (§ 3).

### 3. Pertinence (commune à la modale et à la page)

- `searchableAttributes` **ordonnés** (titre, marque, catégorie, SKU, extrait, contenu) — corrige aussi
  la fuite `R-27`.
- `rankingRules` réglées ; tolérance aux fautes, désactivée sur les SKU et les nombres courts ;
  **synonymes** et **mots vides** français.
- Réglages déclarés par le projet (contrat), le module restant générique.
- Filtres de base : publié uniquement, exclusions WooCommerce respectées.

### 4. Latence serveur

- Régler `R-19` (contrôle DNS + `/health` devant la première recherche).

---

## Décisions à prendre

| # | Question | Proposition |
| --- | --- | --- |
| D-1 | Où vit le code : dans MeiliFacets (fonctionnalité « recherche » à côté du listing), un nouveau module, ou un correctif MeiliScout ? | **MeiliFacets** : client, clé, description, briques de mouvement et d'accessibilité déjà là ; lot 5 de son plan. MeiliScout reste « indexation seule ». |
| D-2 | Seuil de saisie (« plusieurs caractères ») | 2 caractères |
| D-3 | « Voir tous les articles » : le cahier des charges ne cite que les produits. Lien vers une page de résultats articles, ou rien ? | à trancher avec AmphiBee |
| D-4 | Paramètre d'URL du terme : `s` seul (on retire `q`, réponse à `Q-09`) ou les deux ? | **`s` seul** |
| D-5 | Catégories et marques dans la modale ? (demande de remplir `indexed_taxonomies`) | hors cahier des charges : **non** pour ce chantier |
| D-6 | Pages WordPress cherchables ? | hors cahier des charges : **non** |
| D-7 | Moteur en panne : la modale affiche un message et la touche Entrée mène à la recherche native ? (exception à « aucun repli ») | **oui** pour la modale ; la page produits garde la vue « indisponible » du listing |
| D-8 | Synonymes : liste dans le code du projet ou éditable depuis l'admin ? | à trancher |
| D-9 | Maquettes Figma de la modale et de la page de résultats | liens de nœuds à fournir, lus par le MCP Figma |

---

## Suite

1. Réponses aux décisions D-1 à D-9.
2. Passe de conformité (`CLAUDE.md` § 1) sur les décisions validées et le registre.
3. Transformation de ce document en plan d'avancement par étapes (modale, page produits, pertinence,
   accessibilité, animations, habillage Pluralia), chaque étape fermée selon `CLAUDE.md` § 5.
