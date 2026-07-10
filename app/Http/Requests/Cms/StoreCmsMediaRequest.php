<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class StoreCmsMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:20480'],
            'name' => ['nullable', 'string', 'max:255'],
            'folder_id' => ['nullable', 'integer', 'exists:cms_media_folders,id'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ];
    }
}
