{{-- Marca mapdevice: nó de rede com ondas de sinal, sobre escudo --}}
<svg {{ $attributes->merge(['class' => 'brand-mark']) }} viewBox="0 0 40 40" aria-hidden="true">
    <defs>
        <linearGradient id="md-g" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="#e7c574"/>
            <stop offset="1" stop-color="#a77d2a"/>
        </linearGradient>
    </defs>
    <path d="M20 2.5 35 8v11.2c0 9-6.3 15.6-15 18.3C11.3 34.8 5 28.2 5 19.2V8z" fill="url(#md-g)"/>
    <path d="M20 6.2 31.6 10.5v8.7c0 7-4.8 12.2-11.6 14.6-6.8-2.4-11.6-7.6-11.6-14.6v-8.7z" fill="#0d1424"/>
    <circle cx="20" cy="22" r="2.6" fill="url(#md-g)"/>
    <path d="M14.3 16.3a8 8 0 0 1 11.4 0M16.8 18.8a4.5 4.5 0 0 1 6.4 0" stroke="url(#md-g)" stroke-width="1.9" stroke-linecap="round" fill="none"/>
</svg>
