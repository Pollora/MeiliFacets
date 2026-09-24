#!/usr/bin/env bash
set -uo pipefail

module_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
hook_input="$(cat)"

if [[ "$hook_input" != *'.php"'* ]]; then
    exit 0
fi

edited_file="$(php -r '$input = json_decode(stream_get_contents(STDIN), true); echo $input["tool_input"]["file_path"] ?? "";' <<< "$hook_input")"

case "$edited_file" in
    "$module_root"/vendor/* | "$module_root"/node_modules/*) exit 0 ;;
    "$module_root"/*.php) ;;
    *) exit 0 ;;
esac

if [ ! -f "$edited_file" ] || [ ! -x "$module_root/vendor/bin/pint" ]; then
    exit 0
fi

cd "$module_root" || exit 0
fingerprint_before="$(shasum "$edited_file")"
vendor/bin/pint --quiet "$edited_file" >/dev/null 2>&1 || exit 0

if [ "$(shasum "$edited_file")" != "$fingerprint_before" ]; then
    php -r 'echo json_encode(["hookSpecificOutput" => ["hookEventName" => "PostToolUse", "additionalContext" => "Pint reformatted {$argv[1]} after this edit: read it again before the next edit."]], JSON_UNESCAPED_SLASHES), PHP_EOL;' "${edited_file#"$module_root"/}"
fi
exit 0
