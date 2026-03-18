<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Twilio\Rest\Client;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     * Valida credenciales y envía OTP.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'g-recaptcha-response' => ['required', 'string'],
        ], [
            'email.required' => 'El email es requerido.',
            'email.email' => 'El email debe ser válido.',
            'password.required' => 'La contraseña es requerida.',
        ]);

        // Verificar reCAPTCHA
        if (!$request->verifyRecaptcha()) {
            return back()->withErrors([
                'email' => 'Verificación de reCAPTCHA fallida. Intenta de nuevo.',
            ])->onlyInput('email');
        }

        // Validar credenciales SIN autenticar aún
        if (!Auth::validate($request->only('email', 'password'))) {
            return back()->withErrors([
                'email' => 'Las credenciales son incorrectas.',
            ])->onlyInput('email');
        }

        // Obtener el usuario
        $user = \App\Models\User::where('email', $request->email)->first();

        if (!$user) {
            return back()->withErrors([
                'email' => 'Usuario no encontrado.',
            ])->onlyInput('email');
        }

        try {
            // Generar OTP
            $otp = random_int(100000, 999999);
            
            // Generar token temporal para la verificación OTP (válido por 5 minutos)
            $otpToken = \Illuminate\Support\Str::random(60);
            $cacheKey = 'otp_login_' . $request->email;
            Cache::put($cacheKey, [
                'otp' => $otp,
                'user_id' => $user->id,
                'phone' => $user->phone ?? null,
                'token' => $otpToken
            ], now()->addMinutes(5));

            // También guardar el token en cache para validación rápida
            Cache::put('otp_token_' . $otpToken, $request->email, now()->addMinutes(5));

            // Enviar OTP por Twilio SMS
            if ($user->phone) {
                Log::info('Enviando OTP a teléfono: ' . $user->phone);
                $this->sendOtpViaTwilio($user->phone, $otp);
                Log::info('OTP enviado exitosamente');
            } else {
                Log::warning('Usuario sin número de teléfono', ['user_id' => $user->id]);
                return back()->withErrors([
                    'email' => 'No tienes un número de teléfono registrado.',
                ])->onlyInput('email');
            }

            // Guardar email en sesión como respaldo
            $request->session()->put('otp_email', $request->email);

            return redirect()->route('otp.verify.show', ['token' => $otpToken])->with('success', 'OTP enviado a tu teléfono.');

        } catch (\Exception $e) {
            Log::error('Error al enviar OTP: ' . $e->getMessage());
            return back()->withErrors([
                'email' => 'Error al enviar OTP. Intenta de nuevo.',
            ])->onlyInput('email');
        }
    }

    /**
     * Mostrar vista para verificar OTP
     */
    public function showOtpVerify(Request $request): View
    {
        $token = $request->query('token');
        $sessionEmail = session('otp_email');
        $email = null;

        // Intentar obtener email desde el token primero
        if ($token) {
            $email = Cache::get('otp_token_' . $token);
        }

        // Si no hay email desde token, usar el de la sesión
        if (!$email && $sessionEmail) {
            $email = $sessionEmail;
        }

        // Si no hay email, redirigir a login
        if (!$email) {
            return redirect()->route('login');
        }

        // Si hay token, actualizar la sesión para mantenerla fresca
        if ($token) {
            $request->session()->put('otp_email', $email);
        }

        return view('auth.otp-verify', [
            'email' => $email,
            'token' => $token
        ]);
    }

    /**
     * Verificar OTP e iniciar sesión
     */
    public function verifyOtp(Request $request): RedirectResponse
    {
        $request->validate([
            'otp' => ['required', 'numeric', 'digits:6'],
        ], [
            'otp.required' => 'El OTP es requerido.',
            'otp.numeric' => 'El OTP debe contener solo números.',
            'otp.digits' => 'El OTP debe tener 6 dígitos.',
        ]);

        $email = session('otp_email');

        if (!$email) {
            return redirect()->route('login')->withErrors([
                'otp' => 'Sesión expirada. Por favor, inicia sesión nuevamente.',
            ]);
        }

        try {
            $cacheKey = 'otp_login_' . $email;
            $storedData = Cache::get($cacheKey);

            if (!$storedData) {
                session()->forget('otp_email');
                return back()->withErrors([
                    'otp' => 'OTP expirado. Solicita uno nuevo.',
                ]);
            }

            // Verificar que el OTP sea correcto
            if ($storedData['otp'] != $request->otp) {
                return back()->withErrors([
                    'otp' => 'OTP incorrecto. Intenta de nuevo.',
                ])->onlyInput('otp');
            }

            // OTP válido - Autenticar al usuario
            $user = \App\Models\User::find($storedData['user_id']);

            if (!$user) {
                session()->forget('otp_email');
                return redirect()->route('login')->withErrors([
                    'otp' => 'Usuario no encontrado.',
                ]);
            }

            // Eliminar OTP del cache
            Cache::forget($cacheKey);
            session()->forget('otp_email');

            // Iniciar sesión del usuario
            Auth::login($user, $request->boolean('remember'));

            $request->session()->regenerate();

            return redirect()->intended(RouteServiceProvider::HOME);

        } catch (\Exception $e) {
            Log::error('Error al verificar OTP: ' . $e->getMessage());
            return back()->withErrors([
                'otp' => 'Error al verificar OTP. Intenta de nuevo.',
            ])->onlyInput('otp');
        }
    }

    /**
     * Reenviar OTP
     */
    public function resendOtp(Request $request): RedirectResponse
    {
        $email = session('otp_email');

        if (!$email) {
            return redirect()->route('login');
        }

        try {
            // Obtener datos del cache anterior
            $cacheKey = 'otp_login_' . $email;
            $storedData = Cache::get($cacheKey);

            if (!$storedData) {
                session()->forget('otp_email');
                return redirect()->route('login')->withErrors([
                    'otp' => 'Sesión expirada. Por favor, inicia sesión nuevamente.',
                ]);
            }

            // Generar nuevo OTP
            $newOtp = random_int(100000, 999999);

            // Actualizar cache
            Cache::put($cacheKey, [
                'otp' => $newOtp,
                'user_id' => $storedData['user_id'],
                'phone' => $storedData['phone'] ?? null
            ], now()->addMinutes(5));

            // Obtener usuario para enviar SMS
            $user = \App\Models\User::find($storedData['user_id']);
            
            if ($user && $user->phone) {
                $this->sendOtpViaTwilio($user->phone, $newOtp);
                return back()->with('success', 'Nuevo OTP enviado a tu teléfono.');
            } else {
                return back()->withErrors([
                    'otp' => 'Error: número de teléfono no disponible.',
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error al reenviar OTP: ' . $e->getMessage());
            return back()->withErrors([
                'otp' => 'Error al reenviar OTP. Intenta de nuevo.',
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

        Log::info('Credenciales Twilio cargadas', [
            'sid_exists' => !empty($sid),
            'token_exists' => !empty($token),
            'phone_exists' => !empty($phoneNumber),
            'from_phone' => $phoneNumber
        ]);

        // Validar que las credenciales estén configuradas
        if (!$sid || !$token || !$phoneNumber) {
            Log::error('Credenciales de Twilio no configuradas', [
                'sid' => $sid ? 'OK' : 'NULL',
                'token' => $token ? 'OK' : 'NULL',
                'phone' => $phoneNumber ? 'OK' : 'NULL'
            ]);
            throw new \Exception('Twilio no está configurado correctamente');
        }

        try {
            $twilio = new Client($sid, $token);

            Log::info('Cliente Twilio creado exitosamente');

            $message = $twilio->messages->create(
                $phone,
                [
                    'from' => $phoneNumber,
                    'body' => "Tu código OTP para iniciar sesión es: {$otp}. No lo compartas con nadie. Válido por 10 minutos."
                ]
            );

            Log::info('OTP SMS enviado exitosamente', [
                'message_sid' => $message->sid,
                'to' => $phone,
                'from' => $phoneNumber
            ]);
        } catch (\Exception $e) {
            Log::error('Error enviando SMS con Twilio', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'phone' => $phone,
                'exception_class' => get_class($e)
            ]);
            throw $e;
        }
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
