<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'Command Center') - {{ config('app.name', 'RetailPOS Platform') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-slate-100 text-slate-950 antialiased dark:bg-slate-950 dark:text-slate-100 {{ request()->routeIs('cms.*') ? 'cms-light-workspace' : '' }}">
        @php
            $user = auth()->user();
            $moduleGroups = app(\App\Support\Modules\ModuleRegistry::class)->grouped($user?->role);
            $saasNavigation = app(\App\Support\Navigation\SaasNavigationRegistry::class);
            $platformSaasItems = $saasNavigation->platformItems($user);
            $tenantSubscriptionItems = $saasNavigation->tenantSubscriptionItems($user);
            $unreadNotificationCount = $user?->unreadNotifications()->count() ?? 0;
            $recentNotifications = $user?->notifications()->latest()->limit(5)->get() ?? collect();
        @endphp

        <div class="min-h-screen lg:grid lg:grid-cols-[auto_1fr]">
            <div class="fixed inset-0 z-30 hidden bg-slate-950/50 backdrop-blur-sm lg:hidden" data-sidebar-overlay></div>

            <aside id="command-center-sidebar" class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-slate-200 bg-white transition-all duration-200 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0 dark:border-slate-800 dark:bg-slate-900" data-sidebar>
                <div class="flex h-16 items-center justify-between border-b border-slate-200 px-4 dark:border-slate-800" data-sidebar-brand>
                    <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-3">
                        <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-slate-950 text-sm font-semibold text-white dark:bg-teal-300 dark:text-slate-950">RP</span>
                        <span class="min-w-0" data-sidebar-label>
                            <span class="block truncate text-sm font-semibold text-slate-950 dark:text-white">RetailPOS</span>
                            <span class="block truncate text-xs text-slate-500 dark:text-slate-400">Command Center</span>
                        </span>
                    </a>
                    <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-950 lg:hidden dark:hover:bg-slate-800 dark:hover:text-white" data-sidebar-close aria-label="Close sidebar">
                        <x-icon name="x" class="size-5" />
                    </button>
                </div>

                <nav class="flex-1 space-y-5 overflow-y-auto p-3">
                    @foreach ($moduleGroups as $category => $modules)
                        <div class="space-y-1">
                            <p class="px-3 text-[0.68rem] font-semibold uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500" data-sidebar-label>{{ $category }}</p>

                            @foreach ($modules as $module)
                                @php
                                    $isActive = $module->isActive()
                                        || collect($module->children)->contains(fn ($child) => $child->isActive());

                                    $badgeTone = $module->badge['tone'] ?? 'neutral';
                                    $badgeClass = match ($badgeTone) {
                                        'success' => 'bg-teal-100 text-teal-700 dark:bg-teal-900 dark:text-teal-200',
                                        'warning' => 'bg-amber-100 text-amber-700 dark:bg-amber-900 dark:text-amber-200',
                                        'info' => 'bg-sky-100 text-sky-700 dark:bg-sky-900 dark:text-sky-200',
                                        default => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
                                    };
                                @endphp

                                <a href="{{ $module->url() }}"
                                    class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ $isActive ? 'bg-slate-950 text-white shadow-sm dark:bg-teal-300 dark:text-slate-950' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}"
                                    title="{{ $module->name }}">
                                    <x-icon :name="$module->icon" class="size-5 shrink-0" />
                                    <span class="min-w-0 flex-1 truncate" data-sidebar-label>{{ $module->name }}</span>
                                    @if ($module->badge)
                                        <span class="rounded-full px-2 py-0.5 text-[0.65rem] font-semibold {{ $badgeClass }}" data-sidebar-label>{{ $module->badge['label'] }}</span>
                                    @endif
                                </a>

                                @if ($module->children)
                                    <div class="ml-8 space-y-1" data-sidebar-label>
                                        @foreach ($module->children as $child)
                                            <a href="{{ $child->url() }}"
                                                class="block rounded-md px-3 py-2 text-sm transition {{ $child->isActive() ? 'bg-slate-100 font-medium text-slate-950 dark:bg-slate-800 dark:text-white' : 'text-slate-500 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                                                {{ $child->name }}
                                            </a>
                                        @endforeach
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endforeach

                    @if ($platformSaasItems)
                        <div class="space-y-1">
                            <p class="px-3 text-[0.68rem] font-semibold uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500" data-sidebar-label>SaaS Management</p>
                            @foreach ($platformSaasItems as $item)
                                <a href="{{ $saasNavigation->url($item) }}" class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ $saasNavigation->isActive($item) ? 'bg-slate-950 text-white shadow-sm dark:bg-teal-300 dark:text-slate-950' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}">
                                    <x-icon :name="$item['icon']" class="size-5 shrink-0" /><span class="min-w-0 flex-1 truncate" data-sidebar-label>{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    @elseif ($tenantSubscriptionItems)
                        <div class="space-y-1">
                            <p class="px-3 text-[0.68rem] font-semibold uppercase tracking-[0.16em] text-slate-400 dark:text-slate-500" data-sidebar-label>Subscription</p>
                            @foreach ($tenantSubscriptionItems as $item)
                                <a href="{{ $saasNavigation->url($item) }}" class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ $saasNavigation->isActive($item) ? 'bg-slate-950 text-white shadow-sm dark:bg-teal-300 dark:text-slate-950' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}"><x-icon :name="$item['icon']" class="size-5 shrink-0" /><span class="min-w-0 flex-1 truncate" data-sidebar-label>{{ $item['label'] }}</span></a>
                            @endforeach
                            @if (app(\App\Services\Saas\EntitlementService::class)->allows($user->company, 'white_label'))
                                <a href="{{ route('account.subscription.white-label.edit') }}" class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition {{ request()->routeIs('account.subscription.white-label.*') ? 'bg-slate-950 text-white shadow-sm dark:bg-teal-300 dark:text-slate-950' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white' }}"><x-icon name="palette" class="size-5 shrink-0" /><span class="min-w-0 flex-1 truncate" data-sidebar-label>White-label Settings</span></a>
                            @endif
                        </div>
                    @endif
                </nav>

                <div class="border-t border-slate-200 p-4 dark:border-slate-800" data-sidebar-label>
                    <div class="rounded-lg bg-slate-50 p-3 dark:bg-slate-800/70">
                        <p class="truncate text-sm font-medium text-slate-900 dark:text-white">{{ $user?->company?->name ?? 'RetailPOS' }}</p>
                        <p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">{{ $user?->branch?->name ?? 'Primary branch' }}</p>
                    </div>
                </div>
            </aside>

            <div class="min-w-0">
                <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-slate-950/90">
                    <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                        <div class="flex min-w-0 items-center gap-3">
                            <button type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-950 lg:hidden dark:hover:bg-slate-800 dark:hover:text-white" data-sidebar-open aria-controls="command-center-sidebar" aria-expanded="false" aria-label="Open sidebar">
                                <x-icon name="menu" class="size-5" />
                            </button>
                            <button type="button" class="hidden rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-950 lg:inline-flex dark:hover:bg-slate-800 dark:hover:text-white" data-sidebar-collapse aria-label="Collapse sidebar">
                                <x-icon name="menu" class="size-5" />
                            </button>

                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-slate-950 dark:text-white">@yield('page-title', 'Command Center')</p>
                                <nav class="mt-1 hidden items-center gap-2 text-xs text-slate-500 sm:flex dark:text-slate-400" aria-label="Breadcrumb">
                                    <a href="{{ route('dashboard') }}" class="hover:text-slate-950 dark:hover:text-white">Dashboard</a>
                                    @yield('breadcrumbs')
                                </nav>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <div class="relative">
                                <button type="button" class="relative rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-950 dark:hover:bg-slate-800 dark:hover:text-white" data-dropdown-button="notifications-menu" aria-label="Notifications">
                                    <x-icon name="bell" class="size-5" />
                                    @if ($unreadNotificationCount > 0)
                                        <span class="absolute right-1 top-1 grid min-h-4 min-w-4 place-items-center rounded-full bg-teal-500 px-1 text-[0.62rem] font-bold leading-none text-white">{{ $unreadNotificationCount > 9 ? '9+' : $unreadNotificationCount }}</span>
                                    @endif
                                </button>
                                <div id="notifications-menu" class="absolute right-0 z-30 mt-2 hidden w-[calc(100vw-2rem)] max-w-80 rounded-lg border border-slate-200 bg-white p-2 shadow-lg dark:border-slate-800 dark:bg-slate-900">
                                    <div class="flex items-center justify-between px-3 py-2">
                                        <p class="text-sm font-semibold text-slate-950 dark:text-white">Notifications</p>
                                        <a href="{{ route('notifications.index') }}" class="text-xs font-semibold text-teal-700 hover:text-teal-900 dark:text-teal-300">View all</a>
                                    </div>
                                    <div class="space-y-1">
                                        @forelse ($recentNotifications as $notification)
                                            <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                                @csrf
                                                <input type="hidden" name="redirect_to" value="{{ $notification->data['action_url'] ?? route('notifications.index') }}">
                                                <button type="submit" class="block w-full rounded-md px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-800">
                                                <div class="flex items-start gap-2">
                                                    @unless ($notification->read_at)
                                                        <span class="mt-1.5 size-2 shrink-0 rounded-full bg-teal-500"></span>
                                                    @endunless
                                                    <span class="min-w-0">
                                                        <span class="flex items-center justify-between gap-2">
                                                            <span class="block truncate text-sm font-medium">{{ $notification->data['title'] ?? str($notification->data['event_key'] ?? 'Notification')->replace('.', ' ')->headline() }}</span>
                                                            <span class="shrink-0 rounded-full bg-slate-100 px-1.5 py-0.5 text-[0.62rem] font-semibold uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-300">{{ $notification->data['severity'] ?? 'info' }}</span>
                                                        </span>
                                                        <span class="mt-1 block line-clamp-2 text-xs text-slate-500 dark:text-slate-400">{{ $notification->data['message'] ?? 'Open Command Center for details.' }}</span>
                                                        <span class="mt-1 block text-[0.68rem] text-slate-400 dark:text-slate-500">{{ $notification->created_at->diffForHumans() }}</span>
                                                    </span>
                                                </div>
                                                </button>
                                            </form>
                                        @empty
                                            <div class="rounded-md px-3 py-4 text-sm text-slate-500 dark:text-slate-400">No notifications yet.</div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>

                            <div class="relative">
                                <button type="button" class="flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-sm font-medium text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800" data-dropdown-button="user-menu">
                                    <span class="grid size-8 place-items-center rounded-md bg-slate-950 text-xs font-semibold text-white dark:bg-teal-300 dark:text-slate-950">{{ str($user?->name ?? 'U')->substr(0, 1)->upper() }}</span>
                                    <span class="hidden max-w-32 truncate sm:block">{{ $user?->name }}</span>
                                    <x-icon name="chevron-down" class="size-4 text-slate-400" />
                                </button>
                                <div id="user-menu" class="absolute right-0 z-30 mt-2 hidden w-56 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-800 dark:bg-slate-900">
                                    <div class="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                                        <p class="truncate text-sm font-semibold text-slate-950 dark:text-white">{{ $user?->name }}</p>
                                        <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $user?->email }}</p>
                                    </div>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="flex w-full items-center gap-2 px-4 py-3 text-left text-sm text-slate-600 hover:bg-slate-50 hover:text-slate-950 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white">
                                            <x-icon name="logout" class="size-4" />
                                            Logout
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>

                <main class="px-4 py-6 sm:px-6 lg:px-8 {{ request()->routeIs('cms.*') ? 'cms-workspace-main' : '' }}">
                    @if (session('status'))
                        <div class="mb-6 rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900 dark:border-teal-800 dark:bg-teal-950 dark:text-teal-100">
                            {{ session('status') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-100">
                            {{ session('error') }}
                        </div>
                    @endif

                    @yield('content')
                </main>
            </div>
        </div>
    </body>
</html>
