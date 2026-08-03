@php
    $assessmentFields = [
        'client_receptiveness_rating' => [
            'label' => 'Client receptiveness',
            'guidance' => '1 = Unresponsive or negative. 5 = Very positive and engaging.',
        ],
        'buying_interest_rating' => [
            'label' => 'Buying interest',
            'guidance' => '1 = Low or unclear interest. 5 = Strong purchase intent.',
        ],
        'follow_up_urgency_rating' => [
            'label' => 'Follow-up urgency',
            'guidance' => '1 = Can follow up later. 5 = Immediate follow-up required.',
        ],
    ];
@endphp

<section class="mt-6 rounded-lg border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div>
        <h2 class="text-base font-semibold text-slate-950 dark:text-white">Conversation Assessment</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Internal staff assessment from the latest conversation. It is not an AI prediction and is never shown to customers.</p>
    </div>

    <div class="mt-5 grid gap-5 lg:grid-cols-3">
        @foreach ($assessmentFields as $field => $definition)
            @php
                $selected = old($field, $lead->{$field});
                $selected = $selected === null || $selected === '' ? null : (int) $selected;
            @endphp
            <fieldset data-conversation-rating class="rounded-lg border border-slate-200 p-4 dark:border-slate-800">
                <legend class="font-medium text-slate-900 dark:text-white">{{ $definition['label'] }}</legend>
                <p class="mt-1 min-h-10 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $definition['guidance'] }}</p>
                <div class="mt-3 flex flex-wrap gap-1.5" role="radiogroup" aria-label="{{ $definition['label'] }} rating">
                    @foreach (range(1, 5) as $rating)
                        <input id="{{ $field }}_{{ $rating }}" type="radio" name="{{ $field }}" value="{{ $rating }}" class="sr-only" @checked($selected === $rating)>
                        <label for="{{ $field }}_{{ $rating }}" data-conversation-rating-star="{{ $rating }}" title="{{ $rating }} out of 5" class="flex h-10 w-10 cursor-pointer items-center justify-center rounded-lg border border-slate-300 text-sm font-semibold text-slate-500 transition hover:border-amber-400 hover:bg-amber-50 focus-within:ring-2 focus-within:ring-teal-500 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-amber-950/30">
                            <span aria-hidden="true">★</span><span class="sr-only">{{ $rating }} out of 5</span>
                        </label>
                    @endforeach
                    <input id="{{ $field }}_clear" type="radio" name="{{ $field }}" value="" class="sr-only" @checked($selected === null)>
                    <label for="{{ $field }}_clear" data-conversation-rating-clear class="ml-1 flex h-10 cursor-pointer items-center rounded-lg border border-slate-300 px-3 text-xs font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Clear</label>
                </div>
                <p class="mt-3 text-sm font-semibold text-slate-800 dark:text-slate-100" aria-live="polite" data-conversation-rating-value>{{ $selected ? $selected.' / 5' : 'Not rated' }}</p>
                @error($field)<p class="mt-2 text-sm text-rose-600">{{ $message }}</p>@enderror
            </fieldset>
        @endforeach
    </div>
</section>
