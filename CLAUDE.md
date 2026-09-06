# MeiliFacets — working rules

A generic Pollora module. Pluralia is its test bed, not its owner: the module carries behaviour,
the theme carries appearance, and every view stays overridable.

**The register is `../../docs/meilifacets/revue.md`.** Every decision (`D-xx`), finding (`R-xx`),
question (`Q-xx`) and task (`T-xx`) has a stable number. Cite them; never restate their content
here.

---

## 1. Before writing — the conformity pass

Never start from the prompt alone. A prompt is a request, not a mandate: it can contradict
something already settled, and settled things are reversed on purpose, never as a side effect.

Run these three checks, in order, and report their answers **before touching a file**:

1. **Is it already decided?** Read `decisions.md` (« Validées ») and `revue.md` section 0. If the
   request contradicts a decision, say which one, say what reversing it would cost, and **stop**.
2. **Is it already in the register?** If a finding covers it, work under its number. If not, open
   one first — a change with no number is a change nobody will find again.
3. **Is a point already open?** One point at a time (`D-03`). Finish, test, document and close the
   current one before opening the next.

Delegate this pass to the `conformity` subagent when the change touches more than one file, or
when the request sounds like a design choice rather than a fix.

Its output, in three lines: what changes · what it closes · what it contradicts.

### Delegate the reading

Anything that means sweeping several files to reach one conclusion — « how is X handled elsewhere
in this project », « where is Y used », « does Z already exist » — goes to a subagent. Keep the
conclusion, not the file dumps: the context is a budget, and a question answered in a subagent
costs a fraction of the same question answered here. Run independent searches in parallel, in a
single message.

Read directly only what you are about to change, or a single fact whose file you already know.

---

## 2. Design first

Decide what each class or module is responsible for, and where the boundaries are. A file that
does two things is two files.

Prefer fixing a dependency over working around it. MeiliScout has gaps; a small, upstreamable
patch there beats a contortion here. `resolveIndexable()` in MeiliScout is the reference: three
lines upstream instead of a workaround.

---

## 3. Conventions

PHP 8.3+, `declare(strict_types=1)`, full types. English everywhere in code, comments and
diagnostics included. Components over god objects, in PHP as in JavaScript.

The module owns its tools — `pint.json`, `rector.php`, `phpunit.xml`, and its own `require-dev`.
Run them from the module, never from the host: `composer check`. A rule Rector proposes that would
paper over a known finding is skipped **by name, with the finding's number**, in `rector.php`.

Classes, not loose functions — in JavaScript too. **A function taking the same context on every
call is a method missing its class**: give it a constructor instead of a parameter. Keep helpers
private (`#` in JS, `private` in PHP) so the public surface stays the one you meant to expose.

No literal string or number that carries meaning — an enum for a closed set, config for an open
one. A hook name, a document field, a query parameter and an index setting are all closed sets.

Overridable settings are read with their default in the **provider**, never declared in
`config/config.php` and never read from a domain object: nwidart's merge makes the module win over
the project, and `config:cache` drops the module's file entirely.

### Comments

One factual line, and only for one of these two reasons:

| Written | Deleted |
| --- | --- |
| a business or upstream anomaly — « `field != value` excludes every document missing the field » | anything that restates the signature |
| an unavoidable technical workaround — « a src-less `<img>` makes browsers request the current URL » | anything that justifies a design choice — that belongs in `decisions.md`, under a number |
| | anything that tells the story of how the code came to be |

If the sentence would still make sense in a design document, it belongs in the design document.

### PHPDoc

- Only what native types cannot express: array shapes and generics, `@throws`.
- **An array shape is annotated once, on the interface.** An implementation never repeats it.
- Never a `@param`/`@return` that only restates a declared type.
- Never a description line in a docblock whose only content is the method name in prose.

---

## 4. Before handing over — the five passes

Never hand over code with « please check ». Run these yourself, and **report what each one found**
— « nothing » is a valid finding, silence is not.

**Readability.** One level of abstraction per method. No method over ~15 lines without a reason.
No boolean parameter: write a second method. A name you introduce is compared to the name of what
it wraps — two names for one concept is how a codebase stops being readable. A test whose name
disagrees with the method it exercises is a finding waiting to be written.

**Comments.** Apply the table above to every line you added. Count what you deleted.

**Performance.** Count the calls, not the lines. Anything reached on every save, every request or
every document is a hot path: memoize, or move it out. No query inside a loop. Declare nothing the
engine will have to maintain for nothing.

**Security.** Any value reaching a filter, a query or a template comes from the URL and is hostile
until escaped. Escaping order matters. Nothing secret reaches the browser beyond a search-only
key — and remember that a filter the browser sends is a filter the browser can rewrite (`R-28`).

**Context and i18n.** Guard what depends on an absent plugin — no WooCommerce, no price facets,
**and no product listing** (`R-09`). User-facing strings are translatable, in `__()` with no text
domain and named placeholders; identifiers, logs and diagnostics are English. Nothing assumes a
single language.

Delegate the five passes to the `module-review` subagent when the change spans more than one file.
Its findings still have to be acted on or refused in writing — a reported finding that is neither
fixed nor answered is the failure mode this whole file exists to prevent.

---

## 5. Definition of done

A point is closed when all of these are true, and not before:

- [ ] the five passes ran, and what each found is written down;
- [ ] `composer check` from the module root is green — formatting, Rector, the standalone tests
      and the browser client, in one command that needs no host project;
- [ ] `ddev exec vendor/bin/phpunit --testsuite Modules` is green from the project root — this is
      the only way to run the `Feature` tests, which render Blade and need an application;
- [ ] the behaviour is covered by a test — a change with no test is a change that will regress;
- [ ] the register entry is updated: state, date, and what was observed rather than « done »;
- [ ] any decision taken along the way is written in `decisions.md`, with its date and its cost.

---

## 6. Never without asking

- reverse or contradict a validated decision;
- open a second point before the current one is closed;
- add a dependency, a route, a database table or a config key;
- create a directory that is not already in the module's structure;
- rename anything that travels in an indexed URL — a URL parameter, a facet value, a slug;
- increment `Contract::VERSION` on one side only.
