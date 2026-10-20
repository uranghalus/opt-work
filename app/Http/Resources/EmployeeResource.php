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
            'is_banned' => $this->isBanned(),
            'banned_at' => $this->banned_at?->toISOString(),
            // Account status: the linked akun, null-safe — most portal-synced
            // karyawan have none, and the relation may hold null.
            'has_account' => $this->when($this->relationLoaded('user'), fn (): bool => $this->user !== null),
            'user' => $this->when(
                $this->relationLoaded('user') && (bool) $request->user()?->can('akun.read'),
                fn (): ?array => $this->user === null ? null : [
                    'id' => $this->user->id,
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                ],
            ),
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
        ];
    }
}
