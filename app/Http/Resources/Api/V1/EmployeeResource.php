<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'matricule' => $this->matricule,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'gender' => $this->gender,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'birth_place' => $this->birth_place,
            'nationality' => $this->nationality,
            'marital_status' => $this->marital_status,
            'dependent_children' => $this->dependent_children,
            'address' => $this->address,
            'city' => $this->city,
            'national_id_number' => $this->national_id_number,
            'social_security_number' => $this->social_security_number,
            'status' => $this->status,
            'hire_date' => $this->hire_date?->format('Y-m-d'),
            'probation_end_date' => $this->probation_end_date?->format('Y-m-d'),
            'termination_date' => $this->termination_date?->format('Y-m-d'),
            'photo_path' => $this->photo_path,
            'department' => $this->department ? [
                'id' => $this->department->id,
                'name' => $this->department->name,
                'code' => $this->department->code,
            ] : null,
            'position' => $this->position ? [
                'id' => $this->position->id,
                'title' => $this->position->title,
                'code' => $this->position->code,
            ] : null,
            'manager' => $this->manager ? [
                'id' => $this->manager->id,
                'matricule' => $this->manager->matricule,
                'full_name' => $this->manager->full_name,
            ] : null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

