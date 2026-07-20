<?php
// config/frontend.php — public reader (frontend) context configuration.

return [
    'app_context' => 'frontend',
    'context' => 'public',

    // Paths (dir_* keys become constants)
    'dir_template' => dirname(__DIR__, 2) . '/frontend/view/template' . DIRECTORY_SEPARATOR,
    'dir_language' => dirname(__DIR__, 2) . '/frontend/language' . DIRECTORY_SEPARATOR,

    // Actions
    'action_default' => 'common/reader.page',
    'action_error' => 'error/not_found',

    // Pre-Actions (startup middleware, executed in order)
    'pre_actions' => [
        'startup/router',
        'startup/setting',
        'startup/session',
        'startup/event',
    ],

    // Pretty URL → route map. Dynamic paths (uploads, markdown, llms
    // sections) are resolved by the router pre-action's pattern rules.
    'routes' => [
        '/healthz' => 'common/reader.health',
        '/feedback' => 'common/reader.feedback',
        '/preview' => 'common/reader.sharedPreview',
        '/search' => 'common/reader.search',
        '/glossary' => 'common/reader.glossary',
        '/graph' => 'common/reader.graph',
        '/glossary.md' => 'common/reader.glossaryMarkdown',
        '/search-index.json' => 'common/reader.searchIndex',
        '/sitemap.xml' => 'common/reader.sitemap',
        '/inventory' => 'common/reader.inventory',
        '/llms.txt' => 'common/reader.llms',
        '/llms-full.txt' => 'common/reader.llms',
    ],
];
