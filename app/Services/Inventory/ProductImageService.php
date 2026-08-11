<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Product;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImageService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function replace(Product $product, User $user, UploadedFile $file): Product
    {
        abort_unless($product->company_id === $user->company_id, 404);
        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => abort(422, 'Unsupported product image type.'),
        };
        $directory = "companies/{$product->company_id}/products/{$product->id}";
        $path = $file->storeAs($directory, Str::uuid().'.'.$extension, 'local');
        abort_unless(is_string($path), 500, 'Product image could not be stored.');

        $previous = $product->image;
        $product->update(['image' => $path]);
        if ($previous && $previous !== $path && $this->isOwnedPath($product, $previous)) {
            Storage::disk('local')->delete($previous);
        }

        $this->audit->record($previous ? 'inventory.product.image_replaced' : 'inventory.product.image_uploaded', $product, $previous ? 'Product image replaced' : 'Product image uploaded', [
            'company_id' => $product->company_id,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return $product->refresh();
    }

    public function remove(Product $product, User $user): Product
    {
        abort_unless($product->company_id === $user->company_id, 404);
        $path = $product->image;
        if (! $path) {
            return $product;
        }

        $product->update(['image' => null]);
        if ($this->isOwnedPath($product, $path)) {
            Storage::disk('local')->delete($path);
        }
        $this->audit->record('inventory.product.image_removed', $product, 'Product image removed', ['company_id' => $product->company_id]);

        return $product->refresh();
    }

    public function response(Product $product): StreamedResponse
    {
        $path = $product->image;
        abort_unless($path && $this->isOwnedPath($product, $path), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function isOwnedPath(Product $product, string $path): bool
    {
        return str_starts_with($path, "companies/{$product->company_id}/products/{$product->id}/")
            && ! str_contains($path, '..');
    }
}
