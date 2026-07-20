<?php
namespace Admin\Controller\Tools;

use System\Engine\Action;
use System\Engine\Controller;
use Throwable;

/**
 * Glossary terms: list, add, edit, and remove definitions.
 *
 * @package Admin\Controller\Tools
 */
class Glossary extends Controller
{
    public function index(): mixed
    {
        $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_tools_glossary'));

        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            if (!$this->user->hasPermission('modify', 'tools/glossary')) {
                return new Action('error/permission');
            }

            try {
                $this->glossary->delete((string)$this->request->post('slug', 'string'));
                $this->announceChange(['action' => 'glossary.delete', 'slug' => (string)$this->request->post('slug', 'string')]);
                $this->session->addNotification('success', 'Glossary term removed.');
            } catch (Throwable $exception) {
                $this->session->addNotification('danger', $exception->getMessage());
            }

            $this->response->redirect($this->url->link('tools/glossary'));
        }

        $url = $this->url;

        $terms = array_map(static fn(array $term): array => $term + [
                'url' => '/glossary#' . rawurlencode($term['slug']),
                'edit_url' => $url->link('tools/glossary.edit', ['term' => $term['slug']]),
            ], $this->glossary->all());

        $data = [
            'terms' => $terms,
            'stats' => [
                'total' => count($terms),
                'aliases' => array_sum(array_map(static fn(array $term): int => count((array)($term['aliases'] ?? [])), $terms)),
                'characters' => array_sum(array_map(static fn(array $term): int => mb_strlen((string)($term['definition'] ?? '')), $terms)),
            ],
            'message' => '',
            'error' => '',
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'glossary']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('tools/glossary', $data));
        return null;
    }

    public function edit(): mixed
    {
        $this->load->language('common');
        $this->document->setTitle($this->language->get('heading_tools_glossary_form'));

        $source_slug = strtolower(trim((string)($this->request->get['term'] ?? $this->request->post['source_slug'] ?? '')));
        $existing = $source_slug === '' ? null : $this->glossary->find($source_slug);

        if ($source_slug !== '' && $existing === null) {
            return new Action('error/not_found');
        }

        $error = '';

        if (strtoupper((string)($this->request->server['REQUEST_METHOD'] ?? 'GET')) === 'POST') {
            if (!$this->user->hasPermission('modify', 'tools/glossary')) {
                return new Action('error/permission');
            }

            try {
                $aliases = preg_split('/\r?\n|,/', (string)$this->request->post('aliases', 'string')) ?: [];
                $slug = $this->glossary->save((string)$this->request->post('slug', 'string'), (string)$this->request->post('term', 'string'), (string)$this->request->post('definition', 'string'), $aliases, $source_slug === '' ? null : $source_slug);
                $this->announceChange(['action' => 'glossary.save', 'slug' => $slug]);
                $this->session->addNotification('success', 'Glossary term saved.');
                $this->response->redirect($this->url->link('tools/glossary'));
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
                $existing = ['slug' => (string)$this->request->post('slug', 'string'), 'term' => (string)$this->request->post('term', 'string'), 'definition' => (string)$this->request->post('definition', 'string'), 'aliases' => $aliases ?? []];
            }
        }

        $data = [
            'term' => $existing ?? ['slug' => '', 'term' => '', 'definition' => '', 'aliases' => []],
            'source_slug' => $source_slug,
            'is_new' => $source_slug === '',
            'error' => $error,
            'message' => '',
            'config' => $this->config->all(),
            'csrf' => (string)$this->session->get('csrf_token', ''),
        ];
        $data['header'] = $this->load->controller('common/header', ['active_nav' => 'glossary']);
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('tools/glossary_form', $data));
        return null;
    }

    /** Announces a canonical-content change with the acting user attached. */
    private function announceChange(array $payload): void
    {
        $payload['actor_id'] = (int)($this->session->get('user_id') ?? 0);
        $payload['actor'] = (string)$this->session->get('username', 'admin');

        $event_args = [&$payload];
        $this->event->trigger('content.changed', $event_args);
    }
}
