# Ready-library choice for flexible Russian address search

## Recommendation

Use the **Fuse family only for bounded, already-materialized collections**: `loilo/fuse` in PHP and Fuse.js in the offline browser shell. Put the address semantics in one small FMonitor-owned `AddressSearchQuery` compiler shared by all server-side surfaces, with an equivalent generated JavaScript fixture/implementation for offline search. For SQL-backed lists, compile that query directly to MariaDB predicates **before** `COUNT`, `ORDER BY`, `LIMIT`, and `OFFSET`; do not load rows into Fuse and filter afterward.

This is the only practical use of a ready library among the candidates that adds real capability without a new service or a second durable index. Fuse provides typo-tolerant text matching and ranked results for the saved OTiZ calculation groups and offline objects, where the complete bounded collection is already in memory. SQL remains the source of candidate selection and pagination for the address register, object lists, completion register, construction control, economy, and older object register.

The shared compiler must keep numeric address components strict. Fuzzy matching is allowed for street/name tokens (`игорная`), but never for house, corpus/building, or entrance numbers. This means `10` must not silently match `11`, and entrance `1` must not match `10`.

## Required query semantics

Normalize Unicode case, `ё` to `е`, punctuation and repeated whitespace. Split attached Russian markers before parsing (`д10к1п1` → `д 10 к 1 п 1`). Recognize at least `д|дом`, `к|корп|корпус`, and `п|под|подъезд`. Preserve digit/letter identifiers as identifiers rather than numbers where the source permits them (for example `10А`).

Interpret the requested examples as follows:

| Input | Street terms | House | Corpus | Entrance |
|---|---|---:|---:|---:|
| `игорная` | `игорная` | — | — | — |
| `игорная 10` | `игорная` | `10` | — | — |
| `д10` | — | `10` | — | — |
| `д10к1п1` | — | `10` | `1` | `1` |
| `игорная 10 1 1` | `игорная` | `10` | `1` | `1` |

The unmarked positional form is safe only under this explicit grammar: after text terms, the first numeric token is house, the second corpus, and the third entrance. More than three unmarked numeric tokens, conflicting marked/unmarked values, or malformed attached markers should fail as an invalid query rather than broaden results.

For FMonitor's current storage, house/corpus may still be embedded in the effective address string while entrance is a separate effective field. The SQL compiler should therefore emit conjunctive predicates: all street terms against the effective address; exact, boundary-aware house/corpus predicates against that address; and exact equality against the effective entrance. The same parsed structure should drive Fuse predicates for in-memory collections: apply strict component predicates first, then use Fuse only for the remaining text terms and ranking.

## Why Fuse is a bounded component, not the SQL search plan

The PHP port is Composer-installable, supports PHP 7.4+, is tested on PHP 8.2+, and aims for Fuse.js API compatibility, so PHP 8.4 is within its declared range. It searches supplied arrays and supports field keys, scoring, thresholds, and extended matching. [loilo/Fuse README](https://github.com/loilo/Fuse)

Fuse.js provides Unicode-aware token search including Cyrillic, logical queries, and explicit exact/contains/prefix operators. Its own performance documentation states that search cost is linear in the number of indexed entries. Those properties suit the offline manifest and already-loaded OTiZ groups, but they make Fuse the wrong first-stage filter for database lists. [Fuse.js token search](https://www.fusejs.io/token-search.html), [extended search](https://www.fusejs.io/extended-search.html), [performance](https://www.fusejs.io/performance.html)

Concrete integration is therefore:

1. Parse once into `streetTerms`, `house`, `corpus`, and `entrance`.
2. On SQL surfaces, bind predicates to the existing effective-value expressions and execute `COUNT` and the page query with the same predicate. Stable existing ordering remains the pagination tie-breaker.
3. On bounded PHP/JS surfaces, reject rows that violate any numeric component, then run Fuse over normalized text fields with a deliberately tight threshold and deterministic identity tie-breaker.
4. Run one cross-runtime corpus of Russian examples against the PHP parser, SQL compiler/result set, and JavaScript implementation so the same input cannot acquire different meanings by screen.

## Candidate comparison

### libpostal

libpostal is the strongest address-specific normalizer. Its primary documentation shows Russian normalization (`ул … д 4, строение 7`), official PHP bindings, and an intended workflow of normalizing both stored addresses and incoming queries before searching an index. It also explicitly says it is not a geocoder or search index. [libpostal README](https://github.com/openvenues/libpostal)

It does not fit the stated operational constraint as a runtime dependency. The default model is about **1.8 GB**, requires a compiled C library plus model data, and the documented REST variants are separate services; the README notes one server needs about 4 GB RAM. A PHP binding does not remove those native/model requirements. It also cannot by itself preserve FMonitor's SQL pagination contract.

There is also delivery risk on PHP 8.4: the core project calls the PHP binding officially supported, but the binding's own published build instructions/compatibility notes do not establish a PHP 8.4 test lane. That would require a native-extension proof in FMonitor's exact container before adoption, in addition to the model footprint.

The Russian dictionary data is still useful design evidence for abbreviations, and libpostal is MIT-licensed, but copying a few dictionary entries would be project-owned normalization rather than integrating libpostal as a library. If FMonitor later accepts a native model dependency and adds normalized address columns maintained at every write/import seam, libpostal becomes a credible ingestion/query normalizer. It should not be introduced for this bounded delivery.

### loilo/Fuse and Fuse.js

This pair is the recommended ready component. It is small, local, and does not create a service or durable synchronization problem. It materially owns typo tolerance and ranking in the bounded PHP and offline JavaScript collections. It cannot select a MariaDB page without first receiving the rows, so applying it after a SQL page would miss matches and applying it after full rich hydration would create the prohibited full scan. Numeric fields also need project-owned exact predicates because Fuse's fuzzy scoring is unsuitable for identifiers.

### TNTSearch

TNTSearch is a real PHP full-text engine with fuzzy search, Unicode tokenization, and SQLite/MySQL/Redis index backends. Its current release notes include multibyte-aware Levenshtein handling for Cyrillic. [TNTSearch README](https://github.com/teamtnt/tntsearch), [TNTSearch releases](https://github.com/teamtnt/tntsearch/releases)

It is a poor fit here because it introduces a second index lifecycle. FMonitor would need to update/rebuild it for legacy imports, effective object-detail edits, offline manifests, saved calculations, and every other source of searchable projection data, then join returned document IDs back into each authoritative SQL query before pagination. A missed update produces stale search results. The default engine also adds writable SQLite storage; the MySQL engine avoids a separate process but still duplicates indexed state and synchronization logic. TNTSearch does not supply Russian address component semantics, so the strict house/corpus/entrance parser is still required.

### MariaDB FULLTEXT

MariaDB FULLTEXT is not an address parser. Its documented defaults exclude partial words and short InnoDB tokens below three characters, while boolean prefix matching only allows a wildcard at the end. That is a poor natural fit for `д`, `к`, `п`, attached forms, and exact numeric components. [MariaDB FULLTEXT overview](https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes/full-text-indexes/full-text-index-overview)

It may later accelerate street-name candidate retrieval from a maintained normalized search column, but it does not replace the address grammar. For the present scale and requirement, conjunctive bound predicates are simpler and preserve the current transactionally current projection. Any performance change should be driven by measured query plans and corpus size rather than adding FULLTEXT pre-emptively.

## Acceptance boundaries

- SQL-backed screens must filter the full authoritative set before count and pagination; no post-page search and no rich full-set hydration.
- The same source mutation must be searchable immediately without an asynchronous or separately rebuilt index.
- Fuzzy tolerance applies only to text. House, corpus/building, entrance, registration numbers, and other identifiers remain exact after normalization.
- Results retain each surface's existing deterministic ordering unless the product explicitly chooses relevance ordering; Fuse scores must never be the sole tie-breaker.
- A shared corpus must cover attached markers, positional numerics, Cyrillic case/`ё`, punctuation, literal `%`/`_`/backslash SQL escaping, ambiguous input rejection, and near-number non-matches (`10` versus `11`, entrance `1` versus `10`).

This yields one user-visible address language across all surfaces while keeping MariaDB authoritative and using a ready fuzzy-search library where its in-memory execution model is actually sound.
