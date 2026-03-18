<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('¿Olvidaste tu contraseña? Ingresa tu email y enviaremos un código OTP a tu teléfono registrado.') }}
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- reCAPTCHA -->
        <div class="mt-4">
            <div class="g-recaptcha" data-sitekey="{{ config('services.recaptcha.site_key') }}"></div>
            <x-input-error :messages="$errors->get('g-recaptcha-response')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between mt-4">
            <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-gray-900">
                {{ __('Volver al inicio de sesión') }}
            </a>

            <x-primary-button>
                {{ __('Enviar Código OTP') }}
            </x-primary-button>
        </div>
    </form>

    <!-- Script reCAPTCHA -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</x-guest-layout>
