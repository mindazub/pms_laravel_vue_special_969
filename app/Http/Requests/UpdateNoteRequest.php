<?php

namespace App\Http\Requests;

use App\Models\Note;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $note = $this->route('note');

        return $note instanceof Note
            ? ($this->user()?->can('update', $note) ?? false)
            : false;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'content' => ['sometimes', 'nullable', 'string'],
            'clipboard_text' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', Rule::in(Note::STATUSES)],
            'progress' => ['sometimes', Rule::in([0, 25, 50, 75, 100])],
            'estimated_time_hours' => ['sometimes', 'nullable', 'numeric', 'min:0.25', 'max:24'],
            'attachments' => ['sometimes', 'array'],
            'attachments.*' => ['file', 'max:10240'],
            'assignee_ids' => ['sometimes', 'array'],
            'assignee_ids.*' => ['integer', 'exists:users,id'],
            'mentions' => ['sometimes', 'array'],
            'mentions.*.type' => ['required_with:mentions', Rule::in(['User', 'Team', 'Customer'])],
            'mentions.*.id' => ['nullable', 'integer'],
            'mentions.*.name' => ['required_with:mentions', 'string', 'max:255'],
        ];
    }
}
