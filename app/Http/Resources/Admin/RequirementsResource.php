<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequirementsResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'notice' => $this->notice,
            'programs' => $this->programs
                ->map(fn ($program) => [
                    'id' => $program->id,
                    'initials' => $program->initials,
                    'name' => $program->name,
                ])
                ->values(),
            'field_key' => $this->field_key,
            'is_required' => $this->is_required,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at ? date('Y-m-d H:i:s', strtotime($this->created_at)) : null,
        ];
    }
}
