<?php

namespace App\Http\Resources;

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
            'nik_employee' => $this->nik_employee,
            'nama_employee' => $this->nama_employee,
            'email' => $this->email,
            'number' => $this->number,
            'photo_url' => $this->photo_url,
            'created_at' => $this->created_at?->toISOString(),
            'department' => $this->whenLoaded('department', fn () => [
                'id' => $this->department->id,
                'kode_department' => $this->department->kode_department,
                'nama_department' => $this->department->nama_department,
            ]),
            'position' => $this->whenLoaded('position', fn () => [
                'id' => $this->position->id,
                'nama_position' => $this->position->nama_position,
            ]),
            'division' => $this->whenLoaded('division', fn () => [
                'id' => $this->division->id,
                'kode_division' => $this->division->kode_division,
                'nama_division' => $this->division->nama_division,
            ]),
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'is_active' => (bool) $this->user->is_active ?? true,
            ]),
        ];
    }
}
