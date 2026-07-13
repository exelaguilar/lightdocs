<?php

declare(strict_types=1);

namespace Lightdocs\App\Library;

final readonly class RenderedDocument
{
    public function __construct(
        public string $html,
        public array $headings,
        public string $plainText,
    ) {
    }
}
