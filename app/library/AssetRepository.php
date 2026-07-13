<?php

declare(strict_types=1);

namespace Lightdocs\App\Library;

final class AssetRepository
{
    public function __construct(
        private readonly string $root,
        private readonly ContentRepository $content,
    ) {
    }

    /** @return list<array{name:string,url:string,size:int,width:?int,height:?int,usages:list<Page>}> */
    public function all(): array
    {
        if (!is_dir($this->root)) return [];
        $assets = [];
        foreach (scandir($this->root) ?: [] as $name) {
            $path = $this->root . '/' . $name;
            if ($name === '.gitkeep' || !is_file($path)) continue;
            $url = '/uploads/' . rawurlencode($name);
            $usages = [];
            foreach ($this->content->all(true, true) as $page) {
                if (str_contains($page->markdown, '/uploads/' . $name) || str_contains($page->markdown, $url)) {
                    $usages[] = $page;
                }
            }
            $dimensions = @getimagesize($path);
            $assets[] = [
                'name' => $name,
                'url' => $url,
                'size' => (int) filesize($path),
                'width' => is_array($dimensions) ? (int) $dimensions[0] : null,
                'height' => is_array($dimensions) ? (int) $dimensions[1] : null,
                'usages' => $usages,
            ];
        }
        usort($assets, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $assets;
    }
}
