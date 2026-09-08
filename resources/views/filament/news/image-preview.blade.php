@if (is_string($url) && preg_match('~^https?://~i', $url))
    <img src="{{ $url }}" alt="News image preview" loading="lazy" style="max-height: 160px; max-width: 100%; border-radius: 8px; object-fit: cover;">
@endif
