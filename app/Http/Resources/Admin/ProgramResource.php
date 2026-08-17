<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\ProgramStatus;
use App\Enums\BadgeColor;

class ProgramResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        
        $status = $this->is_active;
        $status_text = $status === ProgramStatus::Active->value ? ProgramStatus::Active->name : ProgramStatus::Inactive->name;
        $status_color = $status === ProgramStatus::Active->value ? BadgeColor::Success->value : BadgeColor::Danger->value;

        return [
            'created_at' => date("Y-m-d H:i:s", strtotime($this->created_at)),
            'id' => $this->id,
            'initials' => $this->initials,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $status,
            'status_text' => $status_text,
            'status_color' => $status_color,
        ];
    }
}
