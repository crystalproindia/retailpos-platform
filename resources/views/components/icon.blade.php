@props(['name', 'class' => 'size-5'])

@php
    $stroke = 'none';
@endphp

@switch($name)
    @case('menu')
        <svg {{ $attributes->merge(['class' => $class, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8']) }}>
            <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
        </svg>
        @break

    @case('x')
        <svg {{ $attributes->merge(['class' => $class, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8']) }}>
            <path stroke-linecap="round" d="m6 6 12 12M18 6 6 18" />
        </svg>
        @break

    @case('bell')
        <svg {{ $attributes->merge(['class' => $class, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8']) }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.5 17h-7a2 2 0 0 1-1.95-2.45l.55-2.4a5 5 0 0 1 9.8 0l.55 2.4A2 2 0 0 1 15.5 17Z" />
            <path stroke-linecap="round" d="M10 19a2 2 0 0 0 4 0" />
        </svg>
        @break

    @case('activity')
        <svg {{ $attributes->merge(['class' => $class, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8']) }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 12h4l2.5-6 5 12 2.5-6h4" />
        </svg>
        @break

    @case('server')
        <svg {{ $attributes->merge(['class' => $class, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8']) }}>
            <path stroke-linejoin="round" d="M5 4h14a2 2 0 0 1 2 2v4H3V6a2 2 0 0 1 2-2Zm-2 10h18v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4Z" />
            <path stroke-linecap="round" d="M7 7h.01M7 17h.01M11 7h6M11 17h6" />
        </svg>
        @break

    @case('calendar')
        <svg {{ $attributes->merge(['class' => $class, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8']) }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M7 3v3M17 3v3M4 9h16M6 5h12a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Z" />
        </svg>
        @break

    @case('chevron-down')
        <svg {{ $attributes->merge(['class' => $class, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8']) }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
        </svg>
        @break

    @case('dashboard')
        <svg {{ $attributes->merge(['class' => $class, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8']) }}>
            <path stroke-linejoin="round" d="M4 13h7V4H4v9Zm9 7h7V4h-7v16ZM4 20h7v-5H4v5Z" />
        </svg>
        @break

    @case('layout')
        <svg {{ $attributes->merge(['class' => $class, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8']) }}>
            <path stroke-linejoin="round" d="M4 5.5A1.5 1.5 0 0 1 5.5 4h13A1.5 1.5 0 0 1 20 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 4 18.5v-13Z" />
            <path stroke-linecap="round" d="M4 9h16M9 20V9" />
        </svg>
        @break

    @case('settings')
        <svg {{ $attributes->merge(['class' => $class, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8']) }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 8.5a3.5 3.5 0 1 1 0 7 3.5 3.5 0 0 1 0-7Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 13.5v-3l-2.05-.35a6.9 6.9 0 0 0-.75-1.8l1.2-1.7-2.1-2.1-1.7 1.2a6.9 6.9 0 0 0-1.8-.75L11.5 3h-3l-.35 2.05a6.9 6.9 0 0 0-1.8.75l-1.7-1.2-2.1 2.1 1.2 1.7a6.9 6.9 0 0 0-.75 1.8L1 10.5v3l2.05.35c.18.64.43 1.24.75 1.8l-1.2 1.7 2.1 2.1 1.7-1.2c.56.32 1.16.57 1.8.75l.35 2.05h3l.35-2.05c.64-.18 1.24-.43 1.8-.75l1.7 1.2 2.1-2.1-1.2-1.7c.32-.56.57-1.16.75-1.8L19 13.5Z" transform="translate(2)" />
        </svg>
        @break

    @case('users')
    @case('customers')
    @case('employees')
        <svg {{ $attributes->merge(['class' => $class, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8']) }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M16 19a4 4 0 0 0-8 0M12 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm6 8a3.5 3.5 0 0 0-3-3.45M17 8.5a2.5 2.5 0 0 1-1.5 2.3" />
        </svg>
        @break

    @case('branches')
    @case('company')
        <svg {{ $attributes->merge(['class' => $class, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8']) }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M5 21V6.5L12 3l7 3.5V21M8.5 10h.01M12 10h.01M15.5 10h.01M8.5 14h.01M12 14h.01M15.5 14h.01M10 21v-3h4v3" />
        </svg>
        @break

    @case('audit')
        <svg {{ $attributes->merge(['class' => $class, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8']) }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 6h8M8 10h8M8 14h4M6 3h12a2 2 0 0 1 2 2v15l-4-2-4 2-4-2-4 2V5a2 2 0 0 1 2-2Z" />
        </svg>
        @break

    @case('logout')
        <svg {{ $attributes->merge(['class' => $class, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8']) }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h4M14 8l4 4-4 4M18 12H9" />
        </svg>
        @break

    @default
        <svg {{ $attributes->merge(['class' => $class, 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8']) }}>
            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 7.5A3 3 0 0 1 7.5 4.5h9a3 3 0 0 1 3 3v9a3 3 0 0 1-3 3h-9a3 3 0 0 1-3-3v-9Z" />
            <path stroke-linecap="round" d="M8.5 12h7" />
        </svg>
@endswitch
