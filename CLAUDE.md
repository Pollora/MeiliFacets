# MeiliFacets — working rules

## Before writing code

Design first. Decide what each class or module is responsible for, and where the
boundaries are. A file that does two things is two files.

Prefer fixing a dependency over working around it. MeiliScout has gaps; a small,
upstreamable patch there beats a contortion here. `resolveIndexable()` in
MeiliScout is the reference: three lines upstream instead of a workaround.

## After writing code — review it before handing it over

Never hand over code with "please check". Run these five passes yourself, and
report what each one found.

**Readability.** One level of abstraction per method. No method over ~15 lines
without a reason. No literal string or number that carries meaning — enum for a
closed set, config for an open one. No boolean parameter: write a second method.

**Comments.** Reserved for a business anomaly or an unavoidable technical
workaround, one factual line. No PHPDoc unless the native types cannot express it
— array generics or `@throws`. A comment that restates the signature, justifies a
design choice, or tells a story is deleted.

**Performance.** Count the calls, not the lines. Anything reached on every save,
every request or every document is a hot path: memoize, or move it out. No query
inside a loop. Declare nothing the engine will have to maintain for nothing.

**Security.** Any value reaching a filter, a query or a template comes from the
URL and is hostile until escaped. Escaping order matters. Nothing secret reaches
the browser beyond a search-only key.

**Context and i18n.** Guard what depends on an absent plugin — no WooCommerce, no
price facets. User-facing strings are translatable; identifiers, logs and
diagnostics are English. Nothing assumes a single language.

## Conventions

PHP 8+, `declare(strict_types=1)`, full types. English everywhere in code.
Components over god objects, in PHP as in JavaScript.

Classes, not loose functions — in JavaScript too. A function taking the same
context on every call is a method missing its class. Keep helpers private (`#` in
JS, `private` in PHP) so the public surface stays the one you meant to expose.
