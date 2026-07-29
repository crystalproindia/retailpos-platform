@extends('layouts.public-signup')

@section('title', 'Start Free RetailPOS')
@section('content')
    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <a href="{{ route('login') }}" class="text-sm font-medium text-slate-600 hover:text-slate-950">Already have an account?</a>
            <span class="rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-800">Free 365</span>
        </div>

        <div>
            <h1 class="text-2xl font-semibold tracking-normal text-slate-950">Start your free Retail POS</h1>
            <p class="mt-2 text-sm leading-6 text-slate-500">No credit card required. Get set up in about one minute.</p>
        </div>

        <ol class="grid grid-cols-3 gap-2 text-center text-xs font-semibold" aria-label="Signup progress">
            @foreach(['Industry', 'Verification', 'Store setup'] as $index => $label)
                @php($active = ! $signup ? $index === 0 : (! $signup->verified_at ? $index <= 1 :  true))
                <li class="rounded-md px-2 py-2 {{ $active ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-500' }}"><span class="mr-1">{{ $index + 1 }}.</span>{{ $label }}</li>
            @endforeach
        </ol>

        @if(session('status'))<div class="rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900" role="status">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert"><p class="font-semibold">Please review the highlighted details.</p><ul class="mt-1 list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        @if(! $signup)
            <form method="POST" action="{{ route('saas.public-signup.begin') }}" class="space-y-6">@csrf
                <input class="hidden" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
                <fieldset>
                    <legend class="text-base font-semibold text-slate-950">What type of business do you run?</legend>
                    <p class="mt-1 text-sm text-slate-500">Choose your industry so we can prepare RetailPOS for your store.</p>
                    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach($industries as $industry)
                            <label class="group relative block cursor-pointer rounded-lg border border-slate-200 bg-white p-3 shadow-sm transition hover:-translate-y-0.5 hover:border-teal-400 hover:shadow-md has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50">
                                <input class="peer sr-only" type="radio" name="industry" value="{{ $industry->key }}" required @checked(old('industry') === $industry->key)>
                                <span class="grid size-8 place-items-center rounded-md bg-slate-100 text-xs font-bold text-slate-700 peer-checked:bg-teal-600 peer-checked:text-white">{{ str($industry->label)->substr(0, 1) }}</span>
                                <span class="mt-3 block text-sm font-semibold leading-5 text-slate-950">{{ $industry->label }}</span>
                                <span class="mt-1 block text-xs leading-4 text-slate-500">{{ $industry->description }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                <fieldset>
                    <legend class="text-base font-semibold text-slate-950">Verify your account</legend>
                    <p class="mt-1 text-sm text-slate-500">We only use this to securely create and protect your account.</p>
                    <div class="mt-4 space-y-3">
                        @if($methods['email'])
                            <label class="flex cursor-pointer gap-3 rounded-lg border border-slate-200 p-4 has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50"><input data-method="email" type="radio" name="verification_method" value="email" @checked(old('verification_method', 'email') === 'email') class="mt-1 text-teal-600"><span><span class="block font-semibold text-slate-950">Email verification</span><span class="mt-1 block text-sm text-slate-500">Receive a one-time code by email.</span></span></label>
                            <label data-method-field="email" class="block text-sm font-medium text-slate-700">Email address<input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 shadow-sm outline-none focus:border-teal-600 focus:ring-4 focus:ring-teal-100"></label>
                        @endif
                        @if($methods['mobile'])
                            <label class="flex cursor-pointer gap-3 rounded-lg border border-slate-200 p-4 has-[:checked]:border-teal-600 has-[:checked]:bg-teal-50"><input data-method="mobile" type="radio" name="verification_method" value="mobile" @checked(old('verification_method') === 'mobile') class="mt-1 text-teal-600"><span><span class="block font-semibold text-slate-950">Mobile verification</span><span class="mt-1 block text-sm text-slate-500">Receive a one-time SMS code.</span></span></label>
                            <label data-method-field="mobile" class="block text-sm font-medium text-slate-700">Mobile number<input type="tel" name="mobile" value="{{ old('mobile') }}" autocomplete="tel" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 shadow-sm outline-none focus:border-teal-600 focus:ring-4 focus:ring-teal-100"></label>
                        @endif
                    </div>
                </fieldset>
                <button class="flex w-full items-center justify-center rounded-lg bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-4 focus:ring-slate-300">Continue to verification</button>
            </form>
        @elseif(! $signup->verified_at)
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">We sent a code to <strong class="font-semibold text-slate-950">{{ $signup->email ?: $signup->mobile }}</strong>. It expires shortly and can only be used once.</div>
            <form method="POST" action="{{ route('saas.public-signup.verify') }}" class="space-y-5">@csrf
                <label class="block text-sm font-medium text-slate-700">Six-digit verification code<input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]*" maxlength="6" required autofocus class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-3 text-center text-lg font-semibold tracking-[0.35em] shadow-sm outline-none focus:border-teal-600 focus:ring-4 focus:ring-teal-100"></label>
                <button class="flex w-full items-center justify-center rounded-lg bg-slate-950 px-4 py-3 text-sm font-semibold text-white">Verify and continue</button>
            </form>
            <div class="flex items-center justify-between gap-4 text-sm"><a href="{{ route('saas.public-signup.show') }}" class="font-medium text-slate-600 hover:text-slate-950">Change contact</a><form method="POST" action="{{ route('saas.public-signup.resend') }}">@csrf<button class="font-semibold text-teal-700 hover:text-teal-900" @disabled($signup->resend_available_at?->isFuture())>Resend code</button></form></div>
        @else
            <div class="rounded-lg border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900"><strong>Verified.</strong> Your {{ $signup->verification_method }} is confirmed.</div>
            <form method="POST" action="{{ route('saas.public-signup.complete') }}" class="space-y-5">@csrf
                <input class="hidden" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
                <label class="block text-sm font-medium text-slate-700">Your name<input name="name" value="{{ old('name') }}" required autocomplete="name" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 shadow-sm outline-none focus:border-teal-600 focus:ring-4 focus:ring-teal-100"></label>
                <label class="block text-sm font-medium text-slate-700">Store or company name <span class="font-normal text-slate-500">(optional)</span><input name="company_name" value="{{ old('company_name') }}" placeholder="Your Store Name" autocomplete="organization" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 shadow-sm outline-none focus:border-teal-600 focus:ring-4 focus:ring-teal-100"><span class="mt-1 block text-xs font-normal text-slate-500">You can change this later from Company Profile.</span></label>
                <label class="block text-sm font-medium text-slate-700">Password<div class="relative mt-2"><input id="signup-password" type="password" name="password" required minlength="12" autocomplete="new-password" class="block w-full rounded-lg border border-slate-300 px-3 py-2.5 pr-20 shadow-sm outline-none focus:border-teal-600 focus:ring-4 focus:ring-teal-100"><button type="button" data-password-toggle class="absolute inset-y-0 right-3 text-xs font-semibold text-slate-600">Show</button></div><span class="mt-1 block text-xs font-normal text-slate-500">Use at least 12 characters. Choose something unique to RetailPOS.</span></label>
                <label class="block text-sm font-medium text-slate-700">Confirm password<input id="signup-password-confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="mt-2 block w-full rounded-lg border border-slate-300 px-3 py-2.5 shadow-sm outline-none focus:border-teal-600 focus:ring-4 focus:ring-teal-100"></label>
                <label class="flex items-start gap-3 text-sm text-slate-600"><input type="checkbox" name="terms" value="1" required class="mt-1 rounded border-slate-300 text-teal-600 focus:ring-teal-600"><span>I agree to the <a class="font-semibold text-slate-950 underline" href="{{ $termsUrl }}" target="_blank" rel="noopener">Terms of Service</a> and <a class="font-semibold text-slate-950 underline" href="{{ $privacyUrl }}" target="_blank" rel="noopener">Privacy Policy</a>.</span></label>
                <button class="flex w-full items-center justify-center rounded-lg bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">Create My Free POS</button>
            </form>
        @endif

        <p class="text-center text-xs leading-5 text-slate-500">Free for 365 days. Includes 25 finalized invoices each month, one outlet, and one user.</p>
    </div>
    <script>
        document.querySelector('[data-password-toggle]')?.addEventListener('click', function () {
            const visible = document.getElementById('signup-password').type === 'text';
            document.getElementById('signup-password').type = visible ? 'password' : 'text';
            document.getElementById('signup-password-confirmation').type = visible ? 'password' : 'text';
            this.textContent = visible ? 'Show' : 'Hide';
        });
        const setMethod = (method) => document.querySelectorAll('[data-method-field]').forEach((field) => {
            const active = field.dataset.methodField === method;
            field.hidden = !active;
            field.querySelector('input').required = active;
        });
        document.querySelectorAll('[data-method]').forEach((input) => input.addEventListener('change', () => setMethod(input.value)));
        const chosenMethod = document.querySelector('[data-method]:checked');
        if (chosenMethod) setMethod(chosenMethod.value);
    </script>
@endsection
