<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Project::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'clipboard_text' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'project_manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'mentions' => ['nullable', 'array'],
            'mentions.*.type' => ['required_with:mentions', Rule::in(['User', 'Team', 'Customer'])],
            'mentions.*.id' => ['nullable', 'integer'],
            'mentions.*.name' => ['required_with:mentions', 'string', 'max:255'],
            'team_members' => ['nullable', 'array'],
            'team_members.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function prepareForValidation(): void
    {
        $managerId = $this->input('project_manager_id');

        if ($managerId === '') {
            $this->merge(['project_manager_id' => null]);
        }
    }

    public function passedValidation(): void
    {
        if ($this->user() === null) {
            return;
        }

        if ((int) ($this->validated('project_manager_id') ?? 0) === 0) {
            $this->merge(['project_manager_id' => $this->user()->id]);
        }
    }
}
