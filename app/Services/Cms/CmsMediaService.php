<?php

namespace App\Services\Cms;

use App\Models\Cms\CmsMedia;
use App\Models\Cms\CmsMediaFolder;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class CmsMediaService
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createFolder(User $user, array $data): CmsMediaFolder
    {
        return CmsMediaFolder::create([
            'company_id' => $user->company_id,
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'slug' => Str::slug($data['slug'] ?? $data['name']),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upload(User $user, UploadedFile $file, array $data): CmsMedia
    {
        $fileName = Str::uuid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('cms/'.$user->company_id, $fileName, 'public');

        $media = CmsMedia::create([
            'company_id' => $user->company_id,
            'folder_id' => $data['folder_id'] ?? null,
            'uploaded_by_user_id' => $user->id,
            'name' => $data['name'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'file_name' => $file->getClientOriginalName(),
            'disk' => 'public',
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'extension' => $file->getClientOriginalExtension(),
            'type' => $this->typeForMime($file->getMimeType()),
            'size' => $file->getSize() ?: 0,
            'alt_text' => $data['alt_text'] ?? null,
            'is_optimized' => false,
        ]);

        $this->auditLogger->record('cms.media.uploaded', $media, 'CMS media uploaded');

        return $media;
    }

    public function delete(CmsMedia $media): void
    {
        $media->delete();
    }

    private function typeForMime(?string $mimeType): string
    {
        foreach (config('cms.media_types') as $type => $mimeTypes) {
            if (in_array($mimeType, $mimeTypes, true)) {
                return $type;
            }
        }

        return 'file';
    }
}
