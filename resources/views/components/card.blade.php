{{-- カード（白い面・角丸・影） --}}
<div {{ $attributes->merge(['class' => 'rounded-xl bg-surface-container-lowest p-6 shadow-sm ring-1 ring-outline-variant/40']) }}>{{ $slot }}</div>
