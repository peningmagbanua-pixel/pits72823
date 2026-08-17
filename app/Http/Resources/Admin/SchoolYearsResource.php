<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\SchoolYearStatus;
use App\Enums\BadgeColor;

class SchoolYearsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        
        $status = $this->is_active;
        $status_text = $status === SchoolYearStatus::Active->value ? SchoolYearStatus::Active->name : SchoolYearStatus::Inactive->name;
        $status_color = $status === SchoolYearStatus::Active->value ? BadgeColor::Success->value : BadgeColor::Danger->value;

        return [
            'created_at' => date("Y-m-d H:i:s", strtotime($this->created_at)),
            'id' => $this->id,
            'school_year_start' => $this->start,
            'school_year_end' => $this->end,
            'school_year_name' => $this->name,
            'is_active' => $status,
            'status_text' => $status_text,
            'status_color' => $status_color,
            'application_start' => $this->application_start,
            'application_deadline' => $this->application_deadline,
            'exam_date' => $this->exam_date,
            'guidelines' => $this->guidelines,
        ];
    }
}
