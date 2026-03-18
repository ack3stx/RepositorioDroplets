<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <div class="mb-4 text-center">
        <h2 class="text-2xl font-bold text-gray-900">Verificación de OTP</h2>
        <p class="text-sm text-gray-600 mt-2">
            Hemos enviado un código de 6 dígitos a tu teléfono para resetear tu contraseña.
        </p>
        @if ($email)
            <p class="text-sm text-gray-500 mt-1">
                📧 <span class="font-semibold">{{ $email }}</span>
            </p>
        @endif
    </div>

    <form method="POST" action="{{ route('password.otp.verify') }}">
        @csrf

        <!-- OTP Input -->
        <div>
            <x-input-label for="otp" :value="__('Código OTP')" />
            <x-text-input 
                id="otp" 
                class="block mt-1 w-full text-center text-2xl tracking-widest" 
                type="text" 
                name="otp" 
                inputmode="numeric"
                pattern="[0-9]{6}"
                maxlength="6"
                placeholder="000000"
                required 
                autofocus 
                autocomplete="off" 
            />
            <x-input-error :messages="$errors->get('otp')" class="mt-2" />
        </div>

        <!-- reCAPTCHA (mostrar a partir del 5º intento) -->
        @if ($shouldShowCaptcha ?? false)
            <div class="mt-4">
                <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
                <x-input-error :messages="$errors->get('captcha')" class="mt-2" />
                <x-input-error :messages="$errors->get('g-recaptcha-response')" class="mt-2" />
            </div>
        @endif

        <!-- Button -->
        <div class="flex items-center justify-center mt-6">
            <x-primary-button>
                {{ __('Verificar') }}
            </x-primary-button>
        </div>

        <!-- Volver -->
        <div class="text-center mt-4">
            <a href="{{ route('password.request') }}" class="text-sm text-gray-600 hover:text-gray-900">
                {{ __('Volver al reseteo de contraseña') }}
            </a>
        </div>
    </form>

    <!-- Auto-format OTP -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const otpInput = document.getElementById('otp');
            
            // Auto-format: solo números
            otpInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
            });
        });
    </script>
</x-guest-layout>
