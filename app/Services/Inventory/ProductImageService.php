<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Product;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductImageService
{
    private const PRIMARY_MAX_EDGE = 1600;

    private const THUMBNAIL_MAX_EDGE = 320;

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
        $path = $directory.'/'.Str::uuid().'.'.$extension;
        $thumbnailPath = $this->thumbnailPath($path);

        try {
            $sourceBytes = $file->getContent();
            $source = @imagecreatefromstring($sourceBytes);
            abort_unless($source !== false, 422, 'Product image could not be decoded.');

            try {
                $primaryBytes = $this->resizeAndEncode($source, $extension, self::PRIMARY_MAX_EDGE);
                $thumbnailBytes = $this->resizeAndEncode($source, $extension, self::THUMBNAIL_MAX_EDGE);
            } finally {
                unset($source);
            }

            abort_unless(Storage::disk('local')->put($path, $primaryBytes), 500, 'Product image could not be stored.');
            abort_unless(Storage::disk('local')->put($thumbnailPath, $thumbnailBytes), 500, 'Product thumbnail could not be stored.');
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete([$path, $thumbnailPath]);

            throw $exception;
        }

        $previous = $product->image;
        $product->update(['image' => $path]);
        if ($previous && $previous !== $path && $this->isOwnedPath($product, $previous)) {
            DB::afterCommit(fn () => Storage::disk('local')->delete([$previous, $this->thumbnailPath($previous)]));
        }

        $this->audit->record($previous ? 'inventory.product.image_replaced' : 'inventory.product.image_uploaded', $product, $previous ? 'Product image replaced' : 'Product image uploaded', [
            'company_id' => $product->company_id,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'stored_size' => strlen($primaryBytes),
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
            DB::afterCommit(fn () => Storage::disk('local')->delete([$path, $this->thumbnailPath($path)]));
        }
        $this->audit->record('inventory.product.image_removed', $product, 'Product image removed', ['company_id' => $product->company_id]);

        return $product->refresh();
    }

    public function response(Product $product, bool $thumbnail = false): StreamedResponse
    {
        $path = $product->image;
        abort_unless($path && $this->isOwnedPath($product, $path), 404);
        if ($thumbnail) {
            $path = $this->thumbnailPath($path);
        }
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

    private function thumbnailPath(string $path): string
    {
        return dirname($path).'/thumbnail-'.basename($path);
    }

    private function resizeAndEncode(\GdImage $source, string $extension, int $maxEdge): string
    {
        abort_unless(extension_loaded('gd'), 500, 'Product image processing is unavailable.');
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(1, $maxEdge / max($sourceWidth, $sourceHeight));
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));
        $destination = imagecreatetruecolor($width, $height);
        abort_unless($destination !== false, 500, 'Product image could not be prepared.');

        if (in_array($extension, ['png', 'webp'], true)) {
            imagealphablending($destination, false);
            imagesavealpha($destination, true);
            $transparent = imagecolorallocatealpha($destination, 0, 0, 0, 127);
            imagefill($destination, 0, 0, $transparent);
        }
        imagecopyresampled($destination, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
        ob_start();
        $written = match ($extension) {
            'jpg' => imagejpeg($destination, null, 85),
            'png' => imagepng($destination, null, 6),
            'webp' => imagewebp($destination, null, 85),
        };
        $bytes = ob_get_clean();
        unset($destination);
        abort_unless($written && is_string($bytes), 500, 'Product image could not be encoded.');

        return $bytes;
    }
}
