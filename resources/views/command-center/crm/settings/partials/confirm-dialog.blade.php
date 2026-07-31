<dialog class="w-[calc(100%-2rem)] max-w-md rounded-xl border border-slate-200 bg-white p-0 text-slate-950 shadow-2xl backdrop:bg-slate-950/50 dark:border-slate-700 dark:bg-slate-900 dark:text-white" data-confirm-dialog aria-labelledby="confirm-dialog-title">
    <form method="POST" class="p-6" data-confirm-form>
        @csrf
        <input type="hidden" name="_method" value="DELETE" data-confirm-method>
        <div class="flex items-start gap-4">
            <span class="grid size-10 shrink-0 place-items-center rounded-lg bg-rose-50 text-rose-700 dark:bg-rose-950/60 dark:text-rose-200"><x-icon name="trash" class="size-5" /></span>
            <div class="min-w-0">
                <h2 id="confirm-dialog-title" class="text-base font-semibold" data-confirm-title>Delete this item?</h2>
                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300" data-confirm-message>This action cannot be undone.</p>
            </div>
        </div>
        <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
            <button type="button" class="rounded-lg border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-4 focus:ring-slate-500/15 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800" data-confirm-cancel>Cancel</button>
            <button type="submit" class="rounded-lg bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-rose-700 focus:outline-none focus:ring-4 focus:ring-rose-500/25" data-confirm-submit>Delete</button>
        </div>
    </form>
</dialog>
