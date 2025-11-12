<?php
// app/Http/Requests/EventRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Já foi feita política de autorização
        return true;
    }

    public function rules(): array
    {
        return [
            'title'       => ['required','string','max:255'],
            'description' => ['nullable','string'],
            'location'    => ['nullable','string','max:255'],
            'start_at'    => ['required','date'],
            'end_at'      => ['nullable','date','after_or_equal:start_at'],
            'capacity'    => ['nullable','integer','min:0'],
            'is_all_day'  => ['sometimes','boolean'],
            'is_public'   => ['sometimes','boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Normaliza booleans vindos como "on", "1", true, etc.
        $this->merge([
            'is_all_day' => (bool) $this->boolean('is_all_day'),
            'is_public'  => (bool) $this->boolean('is_public'),
        ]);
    }
}
