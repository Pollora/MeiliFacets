#!/usr/bin/env bash
set -uo pipefail

module_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
hook_input="$(cat)"

cd "$module_root" || exit 0

if [ -z "$(git status --porcelain)" ]; then
    exit 0
fi

if ! command -v composer >/dev/null 2>&1; then
    printf '{"systemMessage": "MeiliFacets stop check skipped: composer is not on the PATH."}\n'
    exit 0
fi

last_green_file="$(git rev-parse --git-path claude-last-green-check)"
working_tree_fingerprint="$(
    {
        git diff HEAD --binary
        git ls-files --others --exclude-standard -z | xargs -0 shasum 2>/dev/null
    } | shasum | cut -d ' ' -f 1
)"

if [ -f "$last_green_file" ] && [ "$(cat "$last_green_file")" = "$working_tree_fingerprint" ]; then
    exit 0
fi

if check_output="$(composer check 2>&1)"; then
    printf '%s\n' "$working_tree_fingerprint" > "$last_green_file"
    exit 0
fi

# A turn already held back once ends on the second failure, so an unfixable check cannot loop forever.
if grep -Eq '"stop_hook_active"[[:space:]]*:[[:space:]]*true' <<< "$hook_input"; then
    printf '{"systemMessage": "MeiliFacets: composer check still fails after a retry; the turn ended anyway."}\n'
    exit 0
fi

# composer returns the failing tool's own exit code, and only 2 keeps Claude from stopping.
{
    printf 'composer check fails in the MeiliFacets module. Fix it before ending the turn.\n\n'
    printf '%s\n' "$check_output" | tail -n 60
} >&2
exit 2
