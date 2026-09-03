<?php

namespace App\Services\Finance;

use App\Models\Finance\ExpenseTransaction;
use App\Models\User;
use App\Services\Outlets\OutletAccessService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Validation\ValidationException;

class ExpenseReceiptService
{
    private const DISK = 'local';

    public function __construct(private readonly OutletAccessService $outlets) {}

    public function replaceDraft(ExpenseTransaction $entry, User $user, UploadedFile $file): ExpenseTransaction
    {
        abort_unless($user->can('finance.expenses.update_draft'), 403);
        $this->authorize($entry, $user);
        if ($entry->status !== ExpenseTransaction::DRAFT) throw ValidationException::withMessages(['receipt' => 'Posted financial evidence cannot be replaced.']);
        $mime = $file->getMimeType();
        if (! $file->isValid() || $file->getSize() > 5 * 1024 * 1024 || ! in_array($mime, ['image/jpeg', 'image/png', 'application/pdf'], true)) throw ValidationException::withMessages(['receipt' => 'Upload a JPG, PNG, or PDF receipt up to 5 MB.']);
        $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'][$mime];
        $path = sprintf('companies/%d/expense-receipts/%s.%s', $entry->company_id, (string) Str::uuid(), $extension);
        if (! Storage::disk(self::DISK)->put($path, $file->getContent())) {
            throw ValidationException::withMessages(['receipt' => 'The receipt could not be stored. Please try again.']);
        }

        $old = $entry->receipt_path;
        try {
            $entry->update(['receipt_path' => $path]);
        } catch (\Throwable $exception) {
            Storage::disk(self::DISK)->delete($path);
            throw $exception;
        }
        if ($old) Storage::disk(self::DISK)->delete($old);
        return $entry->fresh();
    }

    public function response(ExpenseTransaction $entry, User $user): StreamedResponse
    {
        abort_unless($user->can('finance.expenses.view'), 403); $this->authorize($entry, $user);
        abort_unless($entry->receipt_path && $this->ownedPath($entry, $entry->receipt_path) && Storage::disk(self::DISK)->exists($entry->receipt_path), 404);

        return Storage::disk(self::DISK)->response($entry->receipt_path, null, [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorize(ExpenseTransaction $entry, User $user): void
    {
        if ($entry->company_id !== $user->company_id) abort(404);
        if ($entry->branch_id && ! $this->outlets->canAccess($user, $entry->branch)) abort(403);
        if ($entry->branch_id === null && ! $this->outlets->hasCompanyWideAccess($user)) abort(403);
    }

    private function ownedPath(ExpenseTransaction $entry, string $path): bool
    {
        return str_starts_with($path, "companies/{$entry->company_id}/expense-receipts/") && ! str_contains($path, '..');
    }
}
