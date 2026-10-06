---
description: Prepare a MeiliFacets release — checks, version, changelog, upgrade notes and release notes — then stop for approval before anything public.
argument-hint: "[version, e.g. 0.3.0 — optional]"
---

Prepare the next MeiliFacets release. Requested version: $ARGUMENTS (if empty, propose one).

Nothing public happens without the user's explicit approval, given for that action in this conversation: no commit, tag,
push, merge or GitHub release. Write user-facing explanations in the user's language; everything written to the
repository stays in English.

## 1. Check

Report each result; stop at the first failure and say why.

1. On `main`, up to date with `origin/main`, nothing uncommitted (`git status -sb`, `git fetch`). The pull requests the
   release ships are merged.
2. `composer check` is green from the module root. Then, from the host project root,
   `ddev exec vendor/bin/phpunit --testsuite Modules` — never the host's full suite, whose unguarded tests write the
   development index (`R-215`).
3. `resources/assets/dist/` matches the sources (`composer check` runs `build:check`).
4. Neutrality: the module names no host project. The host is the directory above `Modules/`
   (`basename "$(git -C ../.. rev-parse --show-toplevel)"`): `git grep -i` its name across the module, `docs/internal`
   included, and read the release's pull request texts and review replies on GitHub for its URLs, slugs and product
   names. Replace each with a neutral one (« the test project », `/pa_volume/…`, « the shop page »).
5. The register holds no entry opened for this release and left without a decision (`docs/internal/revue.md`).

## 2. Choose the version

List the commits since the last tag (`git describe --tags --abbrev=0`, then `git log <tag>..HEAD --oneline`).
Semantic Versioning, still below 1.0: a minor bump for features, a behaviour change or a required reindex; a patch
bump for fixes only. `Contract::VERSION` moves only when the browser contract changes, on both sides (`CLAUDE.md`, § 6).
Propose the number with its reason and wait for the user's answer.

## 3. Prepare the files

- `CHANGELOG.md`: rename `[Unreleased]` to `[x.y.z](https://github.com/Pollora/MeiliFacets/releases/tag/x.y.z) -
  YYYY-MM-DD` under a one-paragraph introduction, and open an empty `[Unreleased]` above it. Check every `fix` and
  `feat` commit since the last tag has its line. Carry over the « Known issues » still open (`gh issue list`) and add
  the limits the release's pull requests file as known.
- `docs/upgrading.md`: rename `Unreleased` to the version; update the sentence that names the current release.
- `docs/installation.md` and `README.md`: the `composer require` constraint and the current release.
- Release notes, in the scratchpad, on the model of the previous release (`gh release view <last tag>`): the beta
  notice, what is in it, the upgrade steps, the known issues, the link to the changelog at the new tag.
- `composer check` again.

## 4. Stop and show

Show the diff summary and the release notes, then ask for approval of each public step, in this order:

1. commit `docs: release x.y.z as a beta` on `main`, then push;
2. annotated tag `x.y.z` (« MeiliFacets x.y.z (beta) ») on that commit, then push the tag;
3. `gh release create x.y.z --prerelease --title "x.y.z (beta)" --notes-file <notes>`.

After publishing, give the release URL and say what was not done.
