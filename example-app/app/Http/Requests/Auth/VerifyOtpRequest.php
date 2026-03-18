<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
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
            'phone' => 'required|string|min:10',
            'otp' => 'required|numeric|digits:6',
            'g-recaptcha-response' => ['required', 'string'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'phone.required' => 'El teléfono es requerido.',
            'phone.min' => 'El teléfono debe tener al menos 10 dígitos.',
            'otp.required' => 'El OTP es requerido.',
            'otp.numeric' => 'El OTP debe contener solo números.',
            'otp.digits' => 'El OTP debe tener 6 dígitos.',
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
