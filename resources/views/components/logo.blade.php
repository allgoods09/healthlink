@props(['size' => 'md', 'showText' => true])

@php
    $sizes = [
        'sm' => 'w-6 h-6',
        'md' => 'w-8 h-8',
        'lg' => 'w-12 h-12',
        'xl' => 'w-16 h-16',
    ];
    
    $sizeClass = $sizes[$size] ?? $sizes['md'];
    
    $textSizes = [
        'sm' => 'text-lg',
        'md' => 'text-2xl',
        'lg' => 'text-3xl',
        'xl' => 'text-4xl',
    ];
    
    $textSize = $textSizes[$size] ?? $textSizes['md'];
@endphp

<div class="flex items-center space-x-2">
    <!-- Logo Image - Place your Tubigon logo here -->
    <img src="{{ asset('images/tubigon-logo.png') }}" 
         alt="Tubigon Logo" 
         class="{{ $sizeClass }} object-contain">
    
    @if($showText)
        <span class="font-bold text-gray-900 {{ $textSize }}">HealthLink</span>
    @endif
</div>