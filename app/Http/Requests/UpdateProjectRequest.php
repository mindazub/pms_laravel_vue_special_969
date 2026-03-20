<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $project = $this->route('project');

        return $project instanceof Project
            ? ($this->user()?->can('update', $project) ?? false)
            : false;
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
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'mentions' => ['nullable', 'array'],
            'mentions.*.type' => ['required_with:mentions', Rule::in(['User', 'Team', 'Customer'])],
            'mentions.*.id' => ['nullable', 'integer'],
            'mentions.*.name' => ['required_with:mentions', 'string', 'max:255'],
            'selected_project_id' => ['nullable', 'integer'],
            'team_members' => ['nullable', 'array'],
            'team_members.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function prepareForValidation(): void
    {
        $managerId = $this->input('project_manager_id');
        $startDate = $this->input('start_date');
        $endDate = $this->input('end_date');

        if ($managerId === '') {
            $this->merge(['project_manager_id' => null]);
        }

        if ($startDate === '') {
            $this->merge(['start_date' => null]);
        }

        if ($endDate === '') {
            $this->merge(['end_date' => null]);
        }
    }
}
