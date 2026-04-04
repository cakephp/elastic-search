import baseConfig from '@cakephp/docs-skeleton/config'
import { createRequire } from 'module'

const require = createRequire(import.meta.url)
const tocEn = require('./toc_en.json')
const tocJa = require('./toc_ja.json')

const versions = {
  text: '5.x',
  items: [
    { text: '5.x (current)', link: 'https://book.cakephp.org/elasticsearch/5/', target: '_self' },
    { text: '4.x', link: 'https://book.cakephp.org/elasticsearch/4/', target: '_self' },
    { text: '3.x', link: 'https://book.cakephp.org/elasticsearch/3/', target: '_self' },
  ],
}

export default {
  extends: baseConfig,
  srcDir: '.',
  title: 'ElasticSearch',
  description: 'CakePHP ElasticSearch Documentation',
  base: '/elasticsearch/5/',
  rewrites: {
    'en/:slug*': ':slug*',
    'ja/:slug*': 'ja/:slug*',
  },
  sitemap: {
    hostname: 'https://book.cakephp.org/elasticsearch/5/',
  },
  themeConfig: {
    siteTitle: false,
    pluginName: 'ElasticSearch',
    socialLinks: [
      { icon: 'github', link: 'https://github.com/cakephp/elastic-search' },
    ],
    editLink: {
      pattern: 'https://github.com/cakephp/elastic-search/edit/5.x/docs/:path',
      text: 'Edit this page on GitHub',
    },
    sidebar: tocEn,
    nav: [
      { text: 'CakePHP', link: 'https://cakephp.org' },
      { text: 'API', link: 'https://api.cakephp.org/elasticsearch/' },
      { ...versions },
    ],
  },
  locales: {
    root: {
      label: 'English',
      lang: 'en',
      themeConfig: {
        sidebar: tocEn,
      },
    },
    ja: {
      label: 'Japanese',
      lang: 'ja',
      link: '/ja/',
      themeConfig: {
        sidebar: tocJa,
      },
    },
  },
}
