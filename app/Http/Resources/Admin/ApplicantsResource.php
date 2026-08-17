<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\BadgeColor;
use App\Traits\FormatsTrait;

class ApplicantsResource extends JsonResource
{
    use FormatsTrait;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $status = $this->application_status ?? 'pending';

        $status_color = match ($status) {
            'approved' => BadgeColor::Success->value,
            'rejected' => BadgeColor::Danger->value,
            default    => BadgeColor::Warning->value,
        };

        return [
            'id'                        => $this->id,
            'created_at'                => date("Y-m-d H:i:s", strtotime($this->created_at)),
            'program'                   => $this->program,
            'name'                      => $this->firstnameLastnameExtFormat($this),
            'lastname'                  => $this->lastname,
            'firstname'                 => $this->firstname,
            'middlename'                => $this->middlename,
            'extname'                   => $this->extname,
            'date_of_birth'             => $this->date_of_birth,
            'place_of_birth'            => $this->place_of_birth,
            'citizenship'               => $this->citizenship,
            'sex'                       => $this->sex,
            'complete_address'          => $this->complete_address,
            'parent_guardian_name'      => $this->parent_guardian_name,
            'contact_number'            => $this->contact_number,
            'elementary_school'         => $this->elementary_school,
            'elementary_school_address' => $this->elementary_school_address,
            'email'                     => $this->email,
            'sf9_file'                  => $this->sf9_file ? route('admin.file.serve', ['path' => $this->sf9_file]) : null,
            'good_moral_file'           => $this->good_moral_file ? route('admin.file.serve', ['path' => $this->good_moral_file]) : null,
            'health_declaration_file'   => $this->health_declaration_file ? route('admin.file.serve', ['path' => $this->health_declaration_file]) : null,
            'ste_declaration_file'      => $this->ste_declaration_file ? route('admin.file.serve', ['path' => $this->ste_declaration_file]) : null,
            'ste_declaration'           => $this->ste_declaration,
            'application_status'        => $status,
            'status_color'              => $status_color,
        ];
    }
}
