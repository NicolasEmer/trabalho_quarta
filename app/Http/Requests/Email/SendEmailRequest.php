<?php
// app/Http/Requests/Email/SendEmailRequest.php
namespace App\Http\Requests\Email;

use Illuminate\Foundation\Http\FormRequest;

class SendEmailRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            // Destinatários
            'to'   => ['required'],
            'cc'   => ['sometimes','array'],
            'cc.*' => ['email'],
            'bcc'  => ['sometimes','array'],
            'bcc.*'=> ['email'],

            // Conteúdo
            'subject' => ['required','string','max:255'],
            'html'    => ['nullable','string'],
            'text'    => ['nullable','string'],

            'headers' => ['sometimes','array'],

            // Anexos base64
            'attachments'           => ['sometimes','array'],
            'attachments.*.filename'=> ['required_with:attachments','string','max:255'],
            'attachments.*.content' => ['required_with:attachments','string'], // base64
            'attachments.*.mime'    => ['sometimes','string','max:100'],

            // Fila
            'queue'   => ['sometimes','boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Aceita "to" como string separada por vírgula ou array
        $to = $this->input('to');
        if (is_string($to)) {
            // separa por vírgula e limpa espaços
            $to = array_values(array_filter(array_map('trim', explode(',', $to))));
        }
        $this->merge(['to' => $to]);
    }
}
