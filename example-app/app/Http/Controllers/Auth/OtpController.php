<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;

class OtpController extends Controller
{
    /**
     * Generar y enviar OTP vía Twilio
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|min:10',
        ]);

        try {
            // Generar un código OTP aleatorio (6 dígitos)
            $otp = random_int(100000, 999999);
            
            // Almacenar el OTP en cache por 10 minutos
            $cacheKey = 'otp_' . $request->phone;
            Cache::put($cacheKey, $otp, now()->addMinutes(10));

            // Enviar OTP vía Twilio
            $this->sendViaTwilio($request->phone, $otp);

            return response()->json([
                'message' => 'OTP enviado exitosamente',
                'success' => true
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al enviar OTP: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al enviar OTP',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verificar el OTP ingresado por el usuario
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|min:10',
            'otp' => 'required|numeric|digits:6',
        ]);

        try {
            $cacheKey = 'otp_' . $request->phone;
            $storedOtp = Cache::get($cacheKey);

            if (!$storedOtp) {
                return response()->json([
                    'message' => 'OTP expirado o no existe',
                    'success' => false
                ], 400);
            }

            if ($storedOtp != $request->otp) {
                return response()->json([
                    'message' => 'OTP incorrecto',
                    'success' => false
                ], 400);
            }

            // OTP verificado correctamente, eliminar del cache
            Cache::forget($cacheKey);

            return response()->json([
                'message' => 'OTP verificado exitosamente',
                'success' => true
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al verificar OTP: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al verificar OTP',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enviar OTP a través de Twilio
     */
    private function sendViaTwilio($phone, $otp)
    {
        $sid = config('services.twilio.account_sid');
        $token = config('services.twilio.auth_token');
        $phoneNumber = config('services.twilio.phone_number');

        // Validar que las credenciales estén configuradas
        if (!$sid || !$token || !$phoneNumber) {
            Log::warning('Credenciales de Twilio no configuradas');
            throw new \Exception('Twilio no está configurado correctamente');
        }

        $twilio = new Client($sid, $token);

        $message = $twilio->messages->create(
            $phone,  // número destinatario
            [
                'from' => $phoneNumber,
                'body' => "Tu código OTP es: {$otp}. No lo compartas con nadie."
            ]
        );

        Log::info('OTP enviado: ' . $message->sid);
        return $message;
    }
}
