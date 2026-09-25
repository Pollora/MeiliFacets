import {themes as prismThemes} from 'prism-react-renderer';
import type {Config} from '@docusaurus/types';
import type * as Preset from '@docusaurus/preset-classic';

const repository = 'https://github.com/Pollora/MeiliFacets';

const config: Config = {
  title: 'MeiliFacets',
  tagline: 'Faceted search for Pollora projects, powered by Meilisearch.',

  future: {
    v4: true,
  },

  url: 'https://pollora.github.io',
  baseUrl: '/MeiliFacets/',
  organizationName: 'Pollora',
  projectName: 'MeiliFacets',
  // GitHub Pages redirects `/page` to `/page/` unless the choice is explicit.
  trailingSlash: false,

  onBrokenLinks: 'throw',

  i18n: {
    defaultLocale: 'en',
    locales: ['en'],
  },

  presets: [
    [
      'classic',
      {
        docs: {
          routeBasePath: '/',
          sidebarPath: './sidebars.ts',
          editUrl: `${repository}/edit/main/website/`,
          showLastUpdateTime: true,
        },
        blog: false,
        theme: {
          customCss: './src/css/custom.css',
        },
      } satisfies Preset.Options,
    ],
  ],

  themeConfig: {
    colorMode: {
      respectPrefersColorScheme: true,
    },
    navbar: {
      title: 'MeiliFacets',
      items: [
        {
          href: repository,
          label: 'GitHub',
          position: 'right',
        },
      ],
    },
    footer: {
      style: 'dark',
      copyright: 'MeiliFacets is released under the GPL-2.0-or-later licence.',
    },
    prism: {
      theme: prismThemes.github,
      darkTheme: prismThemes.dracula,
      additionalLanguages: ['php', 'bash'],
    },
  } satisfies Preset.ThemeConfig,
};

export default config;
