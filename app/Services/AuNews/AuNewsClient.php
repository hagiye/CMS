<?php

namespace App\Services\AuNews;

use Illuminate\Support\Facades\Http;

class AuNewsClient
{
    public function get(string $url): string
    {
        return Http::withHeaders([
            'User-Agent' => config('au-news.user_agent'),
            'Accept' => 'text/html,application/xhtml+xml',
        ])
            ->connectTimeout(config('au-news.connect_timeout'))
            ->timeout(config('au-news.timeout'))
            ->retry(3, 1000)
            ->get($url)
            ->throw()
            ->body();
    }
}
