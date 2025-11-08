<?php
// app/Http/Resources/EventResource.php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'location'    => $this->location,
            'start_at'    => optional($this->start_at)->toISOString(),
            'end_at'      => optional($this->end_at)->toISOString(),
            'is_all_day'  => (bool) $this->is_all_day,
            'is_public'   => (bool) $this->is_public,
            'created_at'  => optional($this->created_at)->toISOString(),
            'updated_at'  => optional($this->updated_at)->toISOString(),
        ];
    }
}
