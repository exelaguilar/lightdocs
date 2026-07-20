<?php
namespace Admin\Controller\Common;

use System\Engine\Controller;

/**
 * Renders the document head and Studio chrome: sidebar navigation, breadcrumb
 * bar, account menu, command menu, and flash notifications. Navigation is
 * controller data filtered with the database-backed route ACL and labelled
 * from the language file.
 *
 * @package Admin\Controller\Common
 */
class Header extends Controller
{
    private const ICONS = [
        'overview' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>',
        'editor' => '<path d="M4 17.5V21h3.5L18.8 9.7l-3.5-3.5L4 17.5Z"/><path d="m14 7 3.5 3.5"/>',
        'settings' => '<path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6 7 7M17 17l1.4 1.4M18.4 5.6 17 7M7 17l-1.4 1.4"/><circle cx="12" cy="12" r="3"/>',
        'users' => '<path d="M16 20v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 18.5V20M10 11a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM17 11a3 3 0 0 0 0-6"/>',
        'external' => '<path d="M14 4h6v6M20 4l-9 9"/>',
        'health' => '<path d="M4 12h3l2-5 4 10 2-5h5"/><circle cx="12" cy="12" r="9"/>',
        'media' => '<rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8" cy="10" r="1.5"/><path d="m5 17 4-4 3 3 2-2 5 3"/>',
        'graph' => '<circle cx="6" cy="17" r="2"/><circle cx="18" cy="7" r="2"/><circle cx="12" cy="12" r="2"/><path d="m7.7 15.8 2.7-2.6M13.7 10.7l2.7-2.5"/>',
        'developer' => '<path d="m8 9-3 3 3 3M16 9l3 3-3 3M14 6l-4 12"/>',
        'export' => '<path d="M12 3v12M7 10l5 5 5-5M4 20h16"/>',
        'extension' => '<path d="M12 3a2 2 0 0 1 2 2v1h3a2 2 0 0 1 2 2v3h1a2 2 0 1 1 0 4h-1v3a2 2 0 0 1-2 2h-3v-1a2 2 0 1 0-4 0v1H7a2 2 0 0 1-2-2v-3H4a2 2 0 1 1 0-4h1V8a2 2 0 0 1 2-2h3V5a2 2 0 0 1 2-2Z"/>',
        'logs' => '<path d="M4 4h16v16H4z"/><path d="M8 9h8M8 13h5M8 17h3"/>',
        'system' => '<rect x="3" y="4" width="18" height="12" rx="2"/><path d="M7 20h10M9 16v4M15 16v4"/><circle cx="8" cy="9" r="0.75" fill="currentColor" stroke="none"/>',
        'broadcast' => '<path d="M4 10v4a1 1 0 0 0 1 1h2l5 4V5L7 9H5a1 1 0 0 0-1 1Z"/><path d="M16.5 8.5a5 5 0 0 1 0 7M19 6a8.5 8.5 0 0 1 0 12"/>',
    ];

    /**
     * Renders the document head and, unless `shell` is disabled (login and
     * standalone pages), the full Studio chrome.
     *
     * @param array{active_nav?: string, shell?: bool, body_class?: string} $context
     */
    public function index(array $context = []): string
    {
        $text = $this->load->language('common');
        $active_nav = (string)($context['active_nav'] ?? 'editor');
        $shell = (bool)($context['shell'] ?? true);

        // Shared assets flow through the Document service so the footer can
        // render versioned script tags in one place.
        $stylesheet = '/admin/view/stylesheet/app.min.css';
        $stylesheet_path = DIR_ROOT . ltrim($stylesheet, '/');
        $this->document->addStyle(is_file($stylesheet_path) ? $stylesheet . '?v=' . filemtime($stylesheet_path) : $stylesheet);
        foreach ((array)($this->config->get('extension_assets')['admin']['styles'] ?? []) as $asset) {
            $this->document->addStyle((string)$asset);
        }
        $this->document->addScript('/admin/view/javascript/admin.js', 'footer', ['defer' => true]);
        foreach ((array)($this->config->get('extension_assets')['admin']['scripts'] ?? []) as $asset) {
            $this->document->addScript((string)$asset, 'footer', ['type' => 'module']);
        }

        $data = [
            'config' => $this->config->all(),
            'text' => $text,
            'shell' => $shell,
            'title' => $this->document->getTitle() !== '' ? $this->document->getTitle() : (string)$text['heading_default'],
            'body_class' => (string)($context['body_class'] ?? ''),
            'styles' => $this->document->getStyles(),
            'notifications' => $this->session?->getNotifications() ?? [],
            'broadcasts' => $shell ? $this->activeBroadcasts() : [],
        ];

        if ($shell) {
            $data['header'] = $this->chrome($text, $active_nav);
        }

        return $this->load->view('common/header', $data);
    }

    /**
     * Builds the ACL-filtered sidebar, breadcrumb, account, and command data.
     *
     * @param array<string, string> $text
     * @return array<string, mixed>
     */
    private function chrome(array $text, string $active_nav): array
    {
        // Sidebar structure: group label key => items. Each item names its MVC
        // route; visibility is decided by the database-backed access ACL.
        $groups = [
            'text_group_workspace' => [
                ['route' => 'common/dashboard', 'label' => 'text_nav_overview', 'icon_name' => 'overview', 'active' => 'dashboard', 'common' => true],
                ['route' => 'editor/editor', 'label' => 'text_nav_editor', 'icon_name' => 'editor', 'active' => 'editor'],
                ['route' => 'common/profile', 'label' => 'text_nav_profile', 'icon_name' => 'users', 'active' => 'profile', 'common' => true],
            ],
            'text_group_content' => [
                ['route' => 'tools/health', 'label' => 'text_nav_health', 'icon_name' => 'health', 'active' => 'health'],
                ['route' => 'tools/graph', 'label' => 'text_nav_graph', 'icon_name' => 'graph', 'active' => 'graph'],
                ['route' => 'tools/media', 'label' => 'text_nav_media', 'icon_name' => 'media', 'active' => 'media'],
                ['route' => 'tools/navigation', 'label' => 'text_nav_navigation', 'icon_name' => 'extension', 'active' => 'navigation'],
                ['route' => 'tools/glossary', 'label' => 'text_nav_glossary', 'icon_name' => 'extension', 'active' => 'glossary'],
                ['route' => 'tools/import', 'label' => 'text_nav_import', 'icon_name' => 'extension', 'active' => 'import'],
            ],
            'text_group_system' => [
                ['route' => 'settings/settings', 'label' => 'text_nav_settings', 'icon_name' => 'settings', 'active' => 'settings'],
                ['route' => 'tools/extensions', 'label' => 'text_nav_extensions', 'icon_name' => 'extension', 'active' => 'extensions'],
                ['route' => 'common/users', 'label' => 'text_nav_users', 'icon_name' => 'users', 'active' => 'users'],
                ['route' => 'common/roles', 'label' => 'text_nav_roles', 'icon_name' => 'users', 'active' => 'roles'],
            ],
            'text_group_tools' => [
                ['route' => 'export/export', 'label' => 'text_nav_export', 'icon_name' => 'export', 'active' => 'export'],
            ],
            'text_group_developer' => [
                ['route' => 'tools/developer', 'label' => 'text_nav_developer', 'icon_name' => 'developer', 'active' => 'developer'],
                ['route' => 'tools/events', 'label' => 'text_nav_events', 'icon_name' => 'extension', 'active' => 'events'],
                ['route' => 'tools/logs', 'label' => 'text_nav_logs', 'icon_name' => 'logs', 'active' => 'logs'],
                ['route' => 'tools/system', 'label' => 'text_nav_system', 'icon_name' => 'system', 'active' => 'system'],
                ['route' => 'tools/broadcast', 'label' => 'text_nav_broadcast', 'icon_name' => 'broadcast', 'active' => 'broadcast'],
            ],
        ];

        // Extension-provided items merge into their declared section. Their
        // manifest routes are pretty URLs; map them back to MVC routes for the
        // ACL check.
        $path_routes = (array)$this->config->get('routes', []);
        $section_keys = ['Workspace' => 'text_group_workspace', 'Content' => 'text_group_content', 'System' => 'text_group_system', 'Tools' => 'text_group_tools', 'Developer' => 'text_group_developer'];
        foreach ((array)$this->config->get('admin_navigation', []) as $section => $items) {
            $group_key = $section_keys[$section] ?? 'text_group_tools';
            foreach ($items as $item) {
                $url = (string)($item['route'] ?? '');
                if ($url === '') continue;
                $groups[$group_key][] = [
                    'url' => $url,
                    'route' => $path_routes[$url] ?? '',
                    'label_text' => (string)($item['label'] ?? 'Extension'),
                    'icon_name' => (string)($item['icon'] ?? 'extension'),
                    'active' => (string)($item['active'] ?? ''),
                ];
            }
        }

        $navigation_groups = [];
        foreach ($groups as $label_key => $items) {
            $visible = [];
            foreach ($items as $item) {
                $route = (string)strtok((string)($item['route'] ?? ''), '.');
                $allowed = !empty($item['common']) || $route === '' || $this->user->hasPermission('access', $route);
                if (!$allowed) continue;
                $visible[] = [
                    'href' => $item['url'] ?? $this->url->link((string)$item['route']),
                    'label' => isset($item['label']) ? (string)($text[$item['label']] ?? $item['label']) : (string)$item['label_text'],
                    'icon_svg' => $this->icon((string)$item['icon_name']),
                    'active' => $active_nav === (string)($item['active'] ?? ''),
                ];
            }
            if ($visible !== []) {
                $navigation_groups[] = ['label' => (string)($text[$label_key] ?? $label_key), 'items' => $visible];
            }
        }

        // Breadcrumb label for the active page.
        $page_labels = [];
        foreach ($text as $key => $value) {
            if (str_starts_with((string)$key, 'text_page_')) {
                $page_labels[substr((string)$key, 10)] = (string)$value;
            }
        }

        // Command menu entries, filtered with the same ACL as the sidebar.
        $commands = [];
        $command_candidates = [
            ['route' => 'editor/editor', 'label' => 'text_command_open_editor'],
            ['route' => 'settings/settings', 'label' => 'text_command_open_settings'],
            ['route' => 'tools/extensions', 'label' => 'text_command_manage_extensions'],
            ['route' => 'tools/events', 'label' => 'text_command_manage_events'],
            ['route' => 'common/profile', 'label' => 'text_command_profile', 'common' => true],
        ];
        foreach ($command_candidates as $candidate) {
            if (empty($candidate['common']) && !$this->user->hasPermission('access', $candidate['route'])) continue;
            $commands[] = ['href' => $this->url->link($candidate['route']), 'label' => (string)($text[$candidate['label']] ?? $candidate['label']), 'external' => false];
        }
        $commands[] = ['href' => '/', 'label' => (string)($text['text_command_view_documentation'] ?? 'View documentation'), 'external' => true];

        $display_name = trim((string)$this->session->get('firstname', '') . ' ' . (string)$this->session->get('lastname', ''))
            ?: (string)$this->session->get('username', 'Administrator');

        return [
            'commands' => $commands,
            'navigation_groups' => $navigation_groups,
            'initial' => mb_strtoupper(mb_substr((string)$this->config->get('name', 'Lightdocs'), 0, 1)),
            'active_nav' => $active_nav,
            'page_labels' => $page_labels,
            'external_icon_svg' => $this->icon('external'),
            'account' => [
                'display_name' => $display_name,
                'initial' => mb_strtoupper(mb_substr($display_name !== '' ? $display_name : 'A', 0, 1)),
                'can_manage_users' => $this->user->hasPermission('access', 'common/users'),
            ],
        ];
    }

    private function icon(string $name): string
    {
        return '<svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . (self::ICONS[$name] ?? self::ICONS['extension']) . '</svg>';
    }

    /**
     * Active broadcast notices for the signed-in user's role, shaped for the
     * dismissible banner in the header template. Each item is client-side
     * dismissed (localStorage) rather than server-tracked per user.
     *
     * @return list<array{id: int, message: string, tone: string}>
     */
    private function activeBroadcasts(): array
    {
        if (!$this->user->isLogged()) {
            return [];
        }

        $this->load->model('tools/broadcast');
        $group_id = (int)($this->session->get('user_group_id') ?? 0);

        return array_map(static function (array $notice): array {
            return [
                'id' => (int)$notice['id'],
                'message' => (string)$notice['message'],
                'tone' => (string)$notice['tone'],
            ];
        }, $this->model_tools_broadcast->getActiveNoticesForGroup($group_id > 0 ? $group_id : null));
    }
}
