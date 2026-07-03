<?php

declare(strict_types=1);

namespace Introibo\Api\Legal;

/**
 * A published policy document (acceptable-use, terms) served as data: a stable slug,
 * a human title, an effective version, and a Markdown body.
 */
final readonly class Policy
{
    public function __construct(
        public string $slug,
        public string $title,
        public string $version,
        public string $body,
    ) {
    }

    /**
     * @return array{slug: string, title: string, version: string, body: string}
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'version' => $this->version,
            'body' => $this->body,
        ];
    }
}
