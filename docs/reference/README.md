# Reference

Lookup pages: one table per object, taken from the code. Each row links to the page that explains it.

| Page | What it lists |
| --- | --- |
| [Blade components](components.md) | Every `<x-meilifacets::…>` tag and sub-view: attributes, types, defaults, slots, where the attribute bag lands. |
| [`data-meili` hooks](hooks.md) | Every hook of the markup contract, where it is rendered, whether the client requires it, and the other attributes it reads or writes. |
| [CSS custom properties](css-tokens.md) | Every `--meili-*` token of the two stylesheets, with its default and the root it is declared on. |
| [Configuration and environment](configuration.md) | Every `config/meilifacets.php` key with its default, the environment variables and the MeiliScout settings involved. |
| [PHP contracts](contracts.md) | Every interface in `Modules\MeiliFacets\Contracts`, its default implementation and how the module binds it. |
| [WordPress filters and actions](wordpress-hooks.md) | The filter the module exposes, the WordPress filters it reads, the hooks it attaches to, and its asset handles. |
| [Index settings and document fields](index-settings.md) | The Meilisearch settings the module writes and the fields it adds to each document. |
| [Errors and console messages](errors.md) | Every exception, logged report, console line and browser message, with its cause and its fix. |
| [Commands](commands.md) | Artisan, WP-CLI and development commands, with their options. |

## See also

- [Overview](../../README.md)
- [Troubleshooting and FAQ](../troubleshooting.md)
