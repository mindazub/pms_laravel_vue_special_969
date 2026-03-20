<?php

namespace App\Http\Requests;

use App\Models\Note;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Note::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['nullable', 'string'],
            'clipboard_text' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in(Note::STATUSES)],
            'progress' => ['nullable', Rule::in([0, 25, 50, 75, 100])],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
            'assignee_ids' => ['nullable', 'array'],
            'assignee_ids.*' => ['integer', 'exists:users,id'],
            'mentions' => ['nullable', 'array'],
            'mentions.*.type' => ['required_with:mentions', Rule::in(['User', 'Team', 'Customer'])],
            'mentions.*.id' => ['nullable', 'integer'],
            'mentions.*.name' => ['required_with:mentions', 'string', 'max:255'],
        ];
    }
}
