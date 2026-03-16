<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['required', 'string', 'digits:10', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'g-recaptcha-response' => ['required', 'string'],
        ], [
            'phone.required' => 'El teléfono es requerido.',
            'phone.digits' => 'El teléfono debe tener exactamente 10 dígitos.',
            'phone.unique' => 'Este teléfono ya está registrado.',
        ]);

        // Verificar reCAPTCHA
        if (!$this->verifyRecaptcha($request->input('g-recaptcha-response'))) {
            return back()->withErrors([
                'email' => 'Verificación de reCAPTCHA fallida. Intenta de nuevo.',
            ])->onlyInput('email', 'name', 'phone');
        }

        // Agregar "+52" automáticamente
        $phone = '+52' . $request->phone;

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $phone,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(RouteServiceProvider::HOME);
    }

    /**
     * Verify reCAPTCHA token with Google API (v2).
     */
    private function verifyRecaptcha(string $token): bool
    {
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
