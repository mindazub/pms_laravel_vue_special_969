<?php

namespace App\Http\Requests;

use App\Models\Scrapboard;
use Illuminate\Foundation\Http\FormRequest;

class StoreScrapboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Scrapboard::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'workbook' => ['nullable', 'array'],
            'workbook.activeSheetId' => ['nullable', 'string', 'max:255'],
            'workbook.sheets' => ['nullable', 'array', 'min:1'],
        ];
    }
}
