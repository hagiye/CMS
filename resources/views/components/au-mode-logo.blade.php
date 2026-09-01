@props(['alt' => 'African Union'])

<span {{ $attributes->class(['au-mode-logo']) }}>
    <img
        class="au-mode-logo-light"
        src="{{ asset('images/african-union-logo-color.png') }}"
        alt="{{ $alt }}"
    >
    <img
        class="au-mode-logo-dark"
        src="{{ asset('images/african-union-logo-white.png') }}"
        alt="{{ $alt }}"
    >
</span>
