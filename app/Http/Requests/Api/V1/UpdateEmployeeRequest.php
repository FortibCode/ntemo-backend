<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasPermission('employees.create_edit');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'required', 'string', 'max:100'],
            'last_name' => ['sometimes', 'required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            'gender' => ['nullable', 'in:M,F'],
            'birth_date' => ['nullable', 'date'],
            'birth_place' => ['nullable', 'string', 'max:100'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'marital_status' => ['nullable', 'in:SINGLE,MARRIED,DIVORCED,WIDOWED'],
            'dependent_children' => ['nullable', 'integer', 'min:0'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'national_id_number' => ['nullable', 'string', 'max:50'],
            'social_security_number' => ['nullable', 'string', 'max:50'],
            'department_id' => ['nullable', 'uuid', 'exists:departments,id'],
            'position_id' => ['nullable', 'uuid', 'exists:positions,id'],
            'manager_id' => ['nullable', 'uuid', 'exists:employees,id'],
            'hire_date' => ['sometimes', 'required', 'date'],
            'probation_end_date' => ['nullable', 'date'],
            'termination_date' => ['nullable', 'date'],
            'status' => ['sometimes', 'required', 'in:ACTIVE,ON_LEAVE,SUSPENDED,TERMINATED'],
        ];
    }
}

