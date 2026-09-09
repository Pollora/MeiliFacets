# Filtrer par prix — cadrage

Document de cadrage pour `R-43` / `Q-08`. **Rien n'est décidé ici** : il rassemble ce qui a été
mesuré le 2026-09-09 sur le catalogue de recette, les cas à couvrir, et les questions à trancher
avant d'écrire une ligne.

---

## 1. Ce qui existe aujourd'hui, vérifié

**Dans l'index** — réglages relevés sur `posts` :

```
filterable : metas._price, metas._stock_status
sortable   : metas._price, post_date, post_title
displayed  : ID, card
```

`metas._price` est bien numérique : `metas._price >= 40 AND metas._price <= 70` rend 9 résultats.
La note de `ProductMeta.php` (« `_price` is stored as a number, so range filters need no typed
projection ») est donc exacte.

**Dans la carte** — `WooCommerceCardProjector` projette `get_price_html()`, c'est-à-dire du **HTML
figé au moment de l'indexation** (`decisions.md:42`). Ce n'est pas un nombre : la carte affiche ce
que WooCommerce affichait ce jour-là.

**Ce qui n'existe pas** : aucune facette de prix, aucune facette de disponibilité. Deux tris de prix
seulement. `_sale_price`, `_sale_price_dates_from` et `_sale_price_dates_to` **ne sont pas indexés**.

---

## 2. Ce que fait WooCommerce, à lire avant de concevoir

Il ne filtre **pas par tranches**. Il filtre par **min/max**, via `?min_price=&max_price=`, et sa
clause est un test de **chevauchement d'intervalles** (`class-wc-query.php:816`) :

```sql
AND NOT (%f < wc_product_meta_lookup.min_price OR %f > wc_product_meta_lookup.max_price)
```

Deux conséquences :

- il compare à l'**intervalle** du produit (`min_price`/`max_price` de la table de correspondance),
  jamais à un prix unique — parce qu'un produit peut couvrir une plage ;
- son widget dimensionne son curseur avec `SELECT MIN(min_price), MAX(max_price)` **sur l'ensemble
  filtré** (`class-wc-widget-price-filter.php:176`). L'équivalent Meilisearch est `facetStats`, que
  le module ne demande jamais aujourd'hui.

### Comment il calcule `min_price` et `max_price`

Lu dans `class-wc-product-data-store-cpt.php:2495-2505` — c'est plus simple qu'on ne l'imagine :

```php
$price_meta = (array) get_post_meta( $id, '_price', false );   // toutes les lignes
'min_price' => reset( $price_meta ),
'max_price' => end( $price_meta ),
'onsale'    => $sale_price && $price === $sale_price ? 1 : 0,
```

C'est valide parce que `sync_price()` (`class-wc-product-variable-data-store-cpt.php:883`) écrit les
prix des **enfants visibles**, dédoublonnés et **triés numériquement**, une ligne `_price` par prix
distinct. Le cœur le dit dans un commentaire : « *To allow sorting and filtering by multiple values,
we have no choice but to store child prices in this manner.* »

Deux conséquences fidèles :

- **`min_price`/`max_price` ne sont pas une agrégation savante**, c'est la première et la dernière
  ligne d'un tableau trié. Le port est direct ;
- **`onsale` est un drapeau figé** dans la table, calculé `_sale_price && _price === _sale_price` —
  **jamais depuis les dates**. La couche de filtrage de WooCommerce gèle donc l'état promotionnel
  exactement comme un index le ferait, et le rafraîchit quand `_price` bascule.

### Chevauchement, pas « une valeur dans la plage »

La distinction est fine et elle décide du résultat. Sur un produit `[10, 100]` filtré 40–70 :

| approche | résultat |
| --- | --- |
| WooCommerce — chevauchement `NOT (70 < 10 OR 40 > 100)` | **trouvé** |
| indexer le tableau des prix et tester « une valeur dans 40–70 » | **absent** |

Indexer le tableau reproduirait l'astuce *MySQL* de WooCommerce, pas sa *sémantique*. Être fidèle
veut dire : deux champs, et un test de chevauchement.

Il **retire la taxe** des bornes saisies quand la boutique affiche TTC et que les prix sont stockés
HT (`class-wc-query.php:800-810`). Dormant ici — la boutique de recette a les taxes désactivées,
prix stockés HT, affichage HT — mais pas en production.

> Proposer « une facette de tranches de prix », comme le fait `R-43`, reviendrait à inventer une
> forme que la plateforme résout autrement. C'est le travers de `MeasureOrder` (`R-81`).

---

## 3. Les cas, et ce qui casse

Catalogue de recette : **64 simples, 8 variables, 2 groupés, 2 externes**.

| cas | état | ce qui se passe |
| --- | --- | --- |
| **produit simple, prix fixe** | ✅ | `metas._price` porte le prix ; filtre et tri corrects |
| **produit variable** | ❌ | **seul le prix le plus bas est indexé** |
| **produit groupé** | ❌ | même défaut : `_price` = `[19.50, 39.90]`, un seul indexé |
| **produit externe** | ✅ | un seul prix, rien de particulier |
| **promo en cours** | ⚠️ | `_price` porte le prix promo, donc filtre et tri sont justes — mais **rien ne permet de filtrer « en promotion »** |
| **promo planifiée** | ⚠️ | voir § 4 |
| **promo terminée** | ⚠️ | voir § 4 |
| **taxes** | ⚠️ | dormant ici (taxes désactivées, prix HT, affichage HT), à reprendre en production |
| **prix à 0** | ⚠️ | 1 produit. Un curseur dont la borne basse est > 0 l'exclut |
| **prix absent** | ⚠️ | aucun aujourd'hui, mais `metas._price >= x` **exclut tout document sans le champ** — le piège déjà connu du module |
| **hors catalogue** | ✅ | 3 produits en visibilité `hidden`/`search` sont **dans l'index** mais absents du listing : le filtre de base les écarte. Vérifié — mais `facetStats` les compterait si on ne lui passe pas ce filtre |
| **stock d'une variation** | ❌ | voir § 4 bis |

**La preuve du défaut variable/groupé**, sur le produit #416 « Sérum Hydratant » dont la base porte
`_price = [28.00, 35.20, 62.00]` :

```
metas._price = 28                          →  #416 trouvé
metas._price = 62                          →  #416 ABSENT
metas._price = 35.2                        →  #416 ABSENT
metas._price >= 40 AND metas._price <= 70   →  #416 ABSENT
```

Un visiteur filtrant 40–70 € ne verrait pas un produit vendu de 28 à 62 €. **10 produits sur 76**
sont dans ce cas.

---

## 4. Les promotions planifiées — ce qui est vrai et ce qui ne l'est pas

**L'inquiétude de départ** : une promo définie aujourd'hui pour dans deux jours ne s'afficherait
jamais, faute d'être indexée.

**Ce que la mesure dit** : la chaîne existe et fonctionne.

- WooCommerce planifie un événement **par produit** dans Action Scheduler. Relevé en base :
  `wc_product_start_scheduled_sale` le **2026-09-14 13:27**, `wc_product_end_scheduled_sale` le
  **2026-10-14** — exactement la fenêtre du produit #361 ;
- le balai quotidien `woocommerce_scheduled_sales` tourne : `complete` les 05, 07 et 08/09,
  `pending` le 09/09. (Il n'est **pas** dans le cron WP — `wp cron event list` ne le montre pas,
  c'est Action Scheduler qui le porte. Chercher au mauvais endroit fait conclure à tort qu'il
  n'existe pas.) ;
- à la bascule, `wc_apply_sale_state_for_product()` appelle `save()`, qui écrit `_price` et déclenche
  `save_post` + `updated_post_meta` — précisément les hooks que MeiliScout écoute
  (`SingleIndexingServiceProvider:89-98`). **L'indexation suit donc la bascule.**

**Vérifié sur un cas réel passé** : #362 « Huile Régénérante Nuit », promo terminée le 2026-08-25.
`_price` est revenu à `46.00`, et l'index affiche `46,00 €`. La bascule de fin a bien eu lieu et a
bien réindexé.

**Donc, en une phrase** : l'affichage se corrige tout seul au moment de la bascule. Ce qui manque
n'est pas la fraîcheur, c'est que **le prix promo et ses dates ne sont pas indexés du tout** — donc
aucune facette « en promotion », aucun tri sur la remise, aucun filtre « promos qui commencent
bientôt ».

**Les risques résiduels, réels mais différents :**

1. **Action Scheduler dépend du trafic.** Sur une boutique sans visite à 13:27, la bascule est
   tardive. WooCommerce a le même retard que le listing — ils ne divergent pas, ils attendent
   ensemble.
2. **La carte est du HTML figé.** Tout ce qui change un prix sans passer par `save()` laisse la carte
   périmée. À inventorier : un changement de devise, une remise par rôle, un import direct en base.
3. **Un prix affiché par rôle ou par géolocalisation devient impossible** — déjà consigné
   (`decisions.md:384`), le prix étant formaté à l'indexation.

---

## 4 bis. Le stock d'une variation — le cas le plus concret

`pa_contenance`, **l'une des trois facettes en production**, pilote les variations. Le produit #416 :

```
parent #416   stock = instock    _price indexé = 28.00
  variation 15ml   28.00   instock
  variation 30ml   35.20   instock
  variation 50ml   62.00   OUTOFSTOCK
```

Le module indexe le `_stock_status` du **parent**. Donc un visiteur qui filtre sur « 50ml » voit ce
produit, et une future case « en stock » le montrerait disponible — alors que le 50ml qu'il vient de
demander est en rupture.

C'est la bonne pratique que la littérature nomme explicitement : *« A jacket may appear when a
shopper filters by "size medium" even if every medium jacket has sold out. To avoid this,
variant-level stock data needs to feed into the facets alongside the product data. »*

**Mesuré : 2 produits en stock portent 2 variations indisponibles.** Peu, mais le mécanisme est là et
la facette concernée est déjà livrée.

Corollaire : **une case « en stock » n'est pas gratuite**, contrairement à ce que je disais en
séance. Elle l'est pour les 64 produits simples ; elle ment pour les variables dès qu'elle croise une
facette de variation.

Et le stock a **trois** valeurs, pas deux : `instock 70 · outofstock 4 · onbackorder 2` — auxquelles
s'ajoute, relevé par Louis le 2026-09-09 et **différé**, le mode « gestion par quantité » :
`_manage_stock` à `yes` et `_stock` porteur d'un nombre, avec un seuil de rupture faible
(`woocommerce_notify_low_stock_amount` = 2) et une politique de réassort par produit
(`_backorders`). Mesuré ici : **1 produit** gère une quantité (3 en stock), aucun n'accepte le
réassort. Ni `_manage_stock`, ni `_stock`, ni `_backorders` ne sont indexés — seul `_stock_status`
l'est. À reprendre quand la disponibilité sera le sujet ; ce document reste sur le prix. FacetWP
expose d'ailleurs les deux formes au choix — un facet « Stock Status » à deux valeurs qui range les
réassorts avec les disponibles, ou `_stock_status` à trois valeurs. C'est un choix de produit, pas
un détail technique. (`woocommerce_hide_out_of_stock_items` vaut `no` ici : rien n'est masqué en
amont.)

---

## 4 ter. Ce que font les autres

**FacetWP** — la référence du filtrage WooCommerce. Filtre le prix, le stock, l'état « en
promotion », et s'appuie sur `wc_product_meta_lookup`, la table plate que WooCommerce maintient avec
`min_price` et `max_price` **indexés par produit**, précisément pour qu'un filtre de plage soit un
`BETWEEN` sur des colonnes indexées.

**Algolia + WooCommerce** — un cas publié qui est notre défaut, mot pour mot : les pages catégorie
n'affichaient que le prix minimum des produits variables, parce que `min_price` et `max_price`
manquaient à l'index. Leur correctif :

- indexer `min_price` et `max_price` ;
- indexer `sale_start_date` et `sale_end_date` **plutôt qu'un drapeau `is_on_sale`**, et laisser le
  rendu calculer l'état promotionnel. **Recommandation écartée après lecture du cœur** — voir § 4
  quater : c'est un contournement d'un déclencheur cassé chez eux, et il produirait chez nous une
  divergence entre ce que le listing promet et ce que le panier applique ;
- ils ont buté sur « les promos planifiées ne déclenchaient pas de synchronisation Algolia ». Notre
  chaîne diffère : MeiliScout écoute `save_post` **et** `updated_post_meta`, et
  `wc_apply_sale_state_for_product()` appelle `save()`. C'est pour ça que la bascule nous suit — mais
  cela ne tient qu'à ces hooks-là.

**Deux détails d'implémentation qu'ils citent** et qui nous concernent : des décimales rognées
(`108.5` au lieu de `108.50`) et des prix invalides ou à zéro. Nous avons déjà un produit à 0 €.

**Elasticsearch** est cité pour ses agrégations, qui calculent des plages de prix dynamiques. C'est
l'équivalent de `facetStats` chez Meilisearch, que nous ne demandons pas encore.

---

## 4 quater. Les fuseaux, et pourquoi « en promotion » doit rester un drapeau

**Les dates de promo sont stockées en horodatage UTC.** `set_date_prop()`
(`abstract-wc-data.php`) le dit : un nombre est traité comme UTC, une chaîne comme heure locale du
site puis convertie. L'admin force `Y-m-d 00:00:00` et `Y-m-d 23:59:59` **en heure locale**, donc
une promo « du 14 » commence à minuit heure du site, pas à minuit UTC.

WooCommerce planifie la bascule **exactement à la borne** de la promo — mesuré à 0 seconde d'écart.
Un piège de mesure guette qui vérifierait ce point : voir `pieges.md`, « Deux colonnes de date dans
Action Scheduler ».

**WooCommerce a deux mécanismes, et ils ne répondent pas à la même question :**

| | évalué | gouverne |
| --- | --- | --- |
| `is_on_sale()` | **à la volée**, `dates > time()`, UTC des deux côtés | le badge, le prix barré |
| `_price` et `onsale` de la table | **à la bascule** Action Scheduler | le panier, le tri, le filtre |

Le filtrage de WooCommerce s'appuie sur le **drapeau figé**, pas sur les dates. Donc un booléen
dérivé à l'indexation n'est pas un pis-aller : **c'est ce que fait la plateforme**. Il périme au même
rythme que `_price`, c'est-à-dire à la latence d'Action Scheduler près — la même que WooCommerce
s'impose à lui-même. Listing et boutique restent d'accord.

Indexer les dates et calculer au rendu ferait l'inverse : le listing annoncerait une remise avant que
`_price` n'ait basculé, donc **une remise que le panier n'appliquerait pas**. Et si le calcul était
fait dans le navigateur, il comparerait un horodatage UTC à une horloge locale, potentiellement
décalée.

---

## 5. Ce qu'il faudrait décider

**D-a · La forme du filtre.** Min/max façon WooCommerce, en réutilisant `min_price`/`max_price`, ou
tranches déclarées par le projet ? La première suit la plateforme et hérite de son vocabulaire
d'URL ; la seconde est plus simple à rendre mais n'existe nulle part en amont.

**D-b · Le préalable d'indexation.** Un filtre juste suppose d'indexer un **intervalle** par produit
(`min_price`/`max_price`), comme la table `wc_product_meta_lookup` le fait déjà. Sans ce préalable,
le filtre est faux pour 1 produit sur 8. Est-ce dans le lot, ou est-ce que le filtre attend ?

**D-c · La borne du curseur.** `facetStats` de Meilisearch rend min et max sur l'ensemble filtré,
l'équivalent exact du `MIN()/MAX()` de WooCommerce. Le module ne le demande pas encore ; l'ajouter
touche `ListingSearch` et la description JSON.

**D-d · La facette « en promotion ».** Un **drapeau** dérivé de `is_on_sale()` à l'indexation, à
l'image de la colonne `onsale` de `wc_product_meta_lookup` — pas les dates (§ 4 quater). Besoin
distinct du filtre par prix : à traiter séparément ou pas du tout.

**D-e · Les taxes.** Reprendre la conversion de WooCommerce, ou décider que le module ne filtre que
sur le prix stocké et documenter l'écart en boutique TTC.

**D-f · La disponibilité.** `metas._stock_status` est déjà filtrable et personne ne s'en sert. Deux
formes possibles, comme chez FacetWP : deux valeurs (le réassort compte comme disponible) ou trois.
Livrable sans préalable **pour les produits simples** ; pour les variables, elle hérite du § 4 bis.

**D-g · Le stock au niveau de la variation.** Faut-il indexer la disponibilité par variation, pour
qu'une facette de variation ne propose pas une valeur épuisée ? C'est ce que la littérature appelle
la bonne pratique, et cela change la forme du document indexé, pas seulement ses champs.

---

## 6. Ce qui est déjà tranché et qu'il ne faut pas rouvrir sans le dire

- le prix de la carte est du **HTML non filtré**, décidé et argumenté (`decisions.md:42`, `R-26`,
  `Q-11`) ;
- `displayedAttributes` est restreint à `ID` et `card` (`decisions.md:53`) — un filtre prix n'a pas
  besoin de le rouvrir, `metas._price` est filtrable sans être affiché ;
- **une couture s'ouvre quand un projet en a besoin** (`CLAUDE.md` § 2). Les tranches de prix par
  projet seraient une couture ; le min/max n'en est pas une.

---

## Sources

- [WooCommerce | FacetWP](https://facetwp.com/help-center/using-facetwp-with/woocommerce/)
- [Using FacetWP with Stock status and Catalog visibility settings](https://facetwp.com/help-center/using-facetwp-with/woocommerce/using-facetwp-with-stock-status-and-catalog-visibility-settings/)
- [Fixing variable product price range display in WooCommerce + Algolia](https://freshysites.com/resources/woocommerce-algolia-variable-price-fix/)
- [WooCommerce product filters: attribute, price and faceted filtering that scales](https://stackharbor.com/en/knowledge-base/woo-product-filters-widgets/)
- [Faceted Search Best Practices for E-commerce Retailers](https://voyado.com/resources/blog/faceted-search-ecommerce/)
- [Sieve – Faceted Filter for WooCommerce](https://wordpress.org/plugins/sieve/)

Le reste — clauses SQL, hooks, Action Scheduler — vient de la lecture du code de WooCommerce et de
MeiliScout installés, et des mesures sur le catalogue de recette.

