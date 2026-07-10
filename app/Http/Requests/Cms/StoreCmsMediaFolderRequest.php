<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class StoreCmsMediaFolderRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:cms_media_folders,id'],
        ];
    }
}
