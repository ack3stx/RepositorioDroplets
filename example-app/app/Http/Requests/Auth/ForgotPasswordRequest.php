<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'g-recaptcha-response' => ['required', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'email.required' => 'El email es requerido.',
            'email.email' => 'El email debe ser válido.',
            'g-recaptcha-response.required' => 'Debes completar el captcha.',
        ];
    }

    /**
     * Verify reCAPTCHA token with Google API (v2).
     */
    public function verifyRecaptcha(): bool
    {
        $token = $this->input('g-recaptcha-response');
        $secretKey = config('services.recaptcha.secret_key');

        $client = new \GuzzleHttp\Client();
        $response = $client->post('https://www.google.com/recaptcha/api/siteverify', [
            'form_params' => [
                'secret' => $secretKey,
                'response' => $token,
            ]
        ]);

        $body = json_decode((string)$response->getBody());

        return $body->success ?? false;
    }
}
