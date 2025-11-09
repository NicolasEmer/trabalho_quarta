<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class AuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cpf'      => ['required', 'cpf'],
            'password' => ['nullable', 'string', 'min:3'],
            'device'   => ['nullable', 'string', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'cpf.required' => 'Informe o CPF.',
            'cpf.cpf'      => 'O CPF informado é inválido.',
        ];
    }
}

