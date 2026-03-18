<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Twilio\Rest\Client;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset request.
     * Valida email, captcha y envía OTP.
     */
    public function store(ForgotPasswordRequest $request): RedirectResponse
    {
        // Verificar reCAPTCHA
        if (!$request->verifyRecaptcha()) {
            return back()->withErrors([
                'email' => 'Verificación de reCAPTCHA fallida. Intenta de nuevo.',
            ])->onlyInput('email');
        }

        // Obtener el usuario
        $user = \App\Models\User::where('email', $request->email)->first();

        if (!$user) {
            return back()->withErrors([
                'email' => 'No encontramos una cuenta con ese email.',
            ])->onlyInput('email');
        }

        if (!$user->phone) {
            return back()->withErrors([
                'email' => 'No tienes un número de teléfono registrado.',
            ])->onlyInput('email');
        }

        try {
            // Generar OTP
            $otp = random_int(100000, 999999);
            
            // Guardar en cache (válido por 5 minutos)
            $cacheKey = 'otp_reset_' . $request->email;
            Cache::put($cacheKey, [
                'otp' => $otp,
                'user_id' => $user->id,
                'phone' => $user->phone
            ], now()->addMinutes(5));

            // Enviar OTP por Twilio SMS
            Log::info('Enviando OTP para reset a teléfono: ' . $user->phone);
            $this->sendOtpViaTwilio($user->phone, $otp);
            Log::info('OTP para reset enviado exitosamente');

            // Guardar email en sesión para verificación posterior
            $request->session()->put('reset_email', $request->email);

            return redirect()->route('password.otp.show')->with('success', 'OTP enviado a tu teléfono.');

        } catch (\Exception $e) {
            Log::error('Error al enviar OTP para reset: ' . $e->getMessage());
            return back()->withErrors([
                'email' => 'Error al enviar OTP. Intenta de nuevo.',
            ])->onlyInput('email');
        }
    }

    /**
     * Mostrar vista para verificar OTP del reset
     */
    public function showOtpVerify(\Illuminate\Http\Request $request): View
    {
        if (!session('reset_email')) {
            return redirect()->route('password.request');
        }

        $email = session('reset_email');
        $attemptsKey = 'otp_attempts_' . $email;
        
        // Obtener el número de intentos fallidos
        $attempts = session($attemptsKey, 0);
        $shouldShowCaptcha = $attempts >= 4; // Mostrar captcha a partir del 5º intento

        return view('auth.password-reset-otp', [
            'email' => $email,
            'shouldShowCaptcha' => $shouldShowCaptcha
        ]);
    }

    /**
     * Verificar OTP del reset y mostrar formulario de nueva contraseña
     */
    public function verifyOtp(\Illuminate\Http\Request $request): RedirectResponse|View
    {
        $request->validate([
            'otp' => ['required', 'numeric', 'digits:6'],
        ], [
            'otp.required' => 'El OTP es requerido.',
            'otp.numeric' => 'El OTP debe contener solo números.',
            'otp.digits' => 'El OTP debe tener 6 dígitos.',
        ]);

        $email = session('reset_email');

        if (!$email) {
            return redirect()->route('password.request')->withErrors([
                'otp' => 'Sesión expirada. Por favor, intenta de nuevo.',
            ]);
        }

        $attemptsKey = 'otp_attempts_' . $email;
        $attempts = session($attemptsKey, 0);
        
        // Si hay 4 o más intentos fallidos, requerir captcha
        if ($attempts >= 4) {
            if (!$request->input('g-recaptcha-response')) {
                return redirect()->route('password.otp.show')
                    ->withErrors(['captcha' => 'Debes completar el captcha para continuar.'])
                    ->onlyInput('otp');
            }

            // Verificar captcha
            if (!$this->verifyRecaptcha($request)) {
                // Incrementar intentos por captcha fallido
                $request->session()->put($attemptsKey, $attempts + 1);
                return redirect()->route('password.otp.show')
                    ->withErrors(['captcha' => 'Verificación de reCAPTCHA fallida.'])
                    ->onlyInput('otp');
            }
        }

        try {
            $cacheKey = 'otp_reset_' . $email;
            $storedData = Cache::get($cacheKey);

            if (!$storedData) {
                session()->forget('reset_email');
                return back()->withErrors([
                    'otp' => 'OTP expirado. Solicita uno nuevo.',
                ]);
            }

            // Verificar que el OTP sea correcto
            if ($storedData['otp'] != $request->otp) {
                // Incrementar contador de intentos fallidos
                $newAttempts = $attempts + 1;
                $request->session()->put($attemptsKey, $newAttempts);
                
                return redirect()->route('password.otp.show')
                    ->withErrors(['otp' => 'OTP incorrecto. Intenta de nuevo.'])
                    ->onlyInput('otp');
            }

            // OTP válido - Limpiar intentos
            session()->forget($attemptsKey);

            // Mostrar formulario de nueva contraseña
            $resetToken = \Illuminate\Support\Str::random(60);
            
            // Guardar token temporal para el reset
            Cache::put('reset_token_' . $resetToken, [
                'email' => $email,
                'user_id' => $storedData['user_id']
            ], now()->addMinutes(5));

            // Limpiar OTP del cache
            Cache::forget($cacheKey);

            return view('auth.reset-password-form', [
                'email' => $email,
                'token' => $resetToken
            ]);

        } catch (\Exception $e) {
            Log::error('Error al verificar OTP de reset: ' . $e->getMessage());
            return back()->withErrors([
                'otp' => 'Error al verificar OTP. Intenta de nuevo.',
            ])->onlyInput('otp');
        }
    }

    /**
     * Verificar reCAPTCHA token
     */
    private function verifyRecaptcha(\Illuminate\Http\Request $request): bool
    {
        $token = $request->input('g-recaptcha-response');
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

    /**
     * Guardar la nueva contraseña
     */
    public function storeNewPassword(\Illuminate\Http\Request $request): RedirectResponse
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed',
        ], [
            'password.required' => 'La contraseña es requerida.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $token = $request->input('token');
        $email = $request->input('email');

        // Validar que el token existe y es válido
        $resetData = Cache::get('reset_token_' . $token);

        if (!$resetData) {
            return redirect()->route('password.request')->withErrors([
                'token' => 'Token expirado. Por favor intenta de nuevo.',
            ]);
        }

        // Verificar que el email coincida
        if ($resetData['email'] !== $email) {
            return redirect()->route('password.request')->withErrors([
                'email' => 'Email inválido.',
            ]);
        }

        try {
            // Obtener el usuario
            $user = \App\Models\User::find($resetData['user_id']);

            if (!$user) {
                return redirect()->route('password.request')->withErrors([
                    'email' => 'Usuario no encontrado.',
                ]);
            }

            // Actualizar la contraseña
            $user->update([
                'password' => \Illuminate\Support\Facades\Hash::make($request->password)
            ]);

            // Eliminar el token del cache
            Cache::forget('reset_token_' . $token);
            session()->forget('reset_email');

            Log::info('Contraseña resetada para usuario: ' . $user->email);

            return redirect()->route('login')->with('status', 'Contraseña actualizada exitosamente. Por favor inicia sesión.');

        } catch (\Exception $e) {
            Log::error('Error al resetear contraseña: ' . $e->getMessage());
            return back()->withErrors([
                'password' => 'Error al actualizar contraseña. Intenta de nuevo.',
            ]);
        }
    }

    /**
     * Enviar OTP vía Twilio SMS
     */
    private function sendOtpViaTwilio($phone, $otp): void
    {
        $sid = config('services.twilio.account_sid');
        $token = config('services.twilio.auth_token');
        $phoneNumber = config('services.twilio.phone_number');

        if (!$sid || !$token || !$phoneNumber) {
            Log::warning('Credenciales de Twilio no configuradas');
            throw new \Exception('Twilio no está configurado correctamente');
        }

        $twilio = new Client($sid, $token);

        $message = $twilio->messages->create(
            $phone,
            [
                'from' => $phoneNumber,
                'body' => "Tu código OTP para resetear contraseña es: {$otp}. No lo compartas con nadie."
            ]
        );

        Log::info('OTP de reset enviado: ' . $message->sid);
    }
}
