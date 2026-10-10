<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExamScheduleResource extends JsonResource
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
            'school_year_id' => $this->school_year_id,
            'school_year_name' => $this->whenLoaded('schoolYear', fn () => $this->schoolYear?->name),
            'program_id' => $this->program_id,
            'program' => $this->whenLoaded('program', fn () => $this->program?->initials),
            'program_name' => $this->whenLoaded('program', fn () => $this->program?->name),
            'date' => $this->date?->format('Y-m-d'),
            'time_slot' => $this->time_slot,
            'room' => $this->room,
            'capacity' => $this->capacity,
            'applicants_count' => $this->whenCounted('applicants'),
            'deleted_at' => $this->deleted_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}