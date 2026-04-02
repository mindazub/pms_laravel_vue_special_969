<?php

namespace App\Http\Requests;

use App\Models\Scrapboard;
use Illuminate\Foundation\Http\FormRequest;

class UpdateScrapboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $scrapboard = $this->route('scrapboard');

        return $scrapboard instanceof Scrapboard
            ? ($this->user()?->can('update', $scrapboard) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'workbook' => ['nullable', 'array'],
            'workbook.activeSheetId' => ['nullable', 'string', 'max:255'],
            'workbook.sheets' => ['nullable', 'array', 'min:1'],
        ];
    }
}
