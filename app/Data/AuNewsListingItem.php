<?php

namespace App\Data;

class AuNewsListingItem
{
    public function __construct(
        public string $title,
        public string $url,
        public ?string $type,
        public ?string $excerpt,
        public ?string $publishedDate,
    ) {}
}
