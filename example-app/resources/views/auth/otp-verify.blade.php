<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <div class="mb-4 text-center">
        <h2 class="text-2xl font-bold text-gray-900">Verificación de OTP</h2>
        <p class="text-sm text-gray-600 mt-2">
            Hemos enviado un código de 6 dígitos a tu teléfono.
        </p>
        @if ($email)
            <p class="text-sm text-gray-500 mt-1">
                📧 <span class="font-semibold">{{ $email }}</span>
            </p>
        @endif
    </div>

    <form method="POST" action="{{ route('otp.verify') }}">
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

        <!-- Remember Me (opcional) -->
        <div class="block mt-4">
            <label for="remember" class="inline-flex items-center">
                <input id="remember" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-gray-600">{{ __('Recuérdame en este dispositivo') }}</span>
            </label>
        </div>

        <!-- Buttons -->
        <div class="flex items-center justify-between mt-6">
            <x-primary-button>
                {{ __('Verificar') }}
            </x-primary-button>
        </div>

        <!-- Volver al login -->
        <div class="text-center mt-4">
            <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-gray-900">
                {{ __('Volver al inicio de sesión') }}
            </a>
        </div>
    </form>

    <!-- Formulario de reenvío (fuera del formulario principal) -->
    <div class="text-center mt-6">
        <form method="POST" action="{{ route('otp.resend') }}" class="inline">
            @csrf
            <button type="submit" class="text-sm text-indigo-600 hover:text-indigo-900 underline">
                {{ __('¿No recibiste el código? Reenviar') }}
            </button>
        </form>
    </div>

    <!-- Timer (opcional - para mejor UX) -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const otpInput = document.getElementById('otp');
            
            // Auto-format: solo números
            otpInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
            });

            // Auto-submit cuando se complete (opcional)
            otpInput.addEventListener('input', function(e) {
                if (this.value.length === 6) {
                    // Opcional: descomentar para auto-enviar
                    // this.form.submit();
                }
            });
        });
    </script>
</x-guest-layout>

