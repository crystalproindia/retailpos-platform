<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class BarcodeTemplateRequest extends FormRequest
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
            'description' => ['nullable', 'string'],
            'industry_type' => ['nullable', 'string', 'max:255'],
            'paper_size' => ['nullable', 'string', 'max:255'],
            'label_width_mm' => ['required', 'numeric', 'min:5'],
            'label_height_mm' => ['required', 'numeric', 'min:5'],
            'columns' => ['required', 'integer', 'min:1', 'max:12'],
            'rows' => ['nullable', 'integer', 'min:1', 'max:50'],
            'gap_horizontal_mm' => ['nullable', 'numeric', 'min:0'],
            'gap_vertical_mm' => ['nullable', 'numeric', 'min:0'],
            'margin_top_mm' => ['nullable', 'numeric', 'min:0'],
            'margin_right_mm' => ['nullable', 'numeric', 'min:0'],
            'margin_bottom_mm' => ['nullable', 'numeric', 'min:0'],
            'margin_left_mm' => ['nullable', 'numeric', 'min:0'],
            'barcode_type' => ['required', 'string', 'max:50'],
            'barcode_width_mm' => ['nullable', 'numeric', 'min:1'],
            'barcode_height_mm' => ['nullable', 'numeric', 'min:1'],
            'font_size' => ['required', 'integer', 'min:6', 'max:24'],
            'custom_css' => ['nullable', 'string'],
            'show_product_name' => ['nullable', 'boolean'],
            'show_sku' => ['nullable', 'boolean'],
            'show_barcode_text' => ['nullable', 'boolean'],
            'show_price' => ['nullable', 'boolean'],
            'show_mrp' => ['nullable', 'boolean'],
            'show_offer_price' => ['nullable', 'boolean'],
            'show_brand' => ['nullable', 'boolean'],
            'show_category' => ['nullable', 'boolean'],
            'show_size' => ['nullable', 'boolean'],
            'show_color' => ['nullable', 'boolean'],
            'show_batch' => ['nullable', 'boolean'],
            'show_expiry' => ['nullable', 'boolean'],
            'show_company_name' => ['nullable', 'boolean'],
            'show_logo' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
