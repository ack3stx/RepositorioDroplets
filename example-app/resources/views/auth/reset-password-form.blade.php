<x-guest-layout>
    <div class="mb-4 text-center">
        <h2 class="text-2xl font-bold text-gray-900">Establecer Nueva Contraseña</h2>
        <p class="text-sm text-gray-600 mt-2">
            Ingresa tu nueva contraseña
        </p>
    </div>

    <form method="POST" action="{{ route('password.reset.store') }}">
        @csrf

        <!-- Token -->
        <input type="hidden" name="token" value="{{ $token }}">

        <!-- Email -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input 
                id="email" 
                class="block mt-1 w-full" 
                type="email" 
                name="email" 
                value="{{ $email }}" 
                readonly 
            />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Nueva Contraseña')" />
            <x-text-input 
                id="password" 
                class="block mt-1 w-full" 
                type="password" 
                name="password" 
                required 
                autofocus 
                autocomplete="new-password" 
            />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirmar Contraseña')" />
            <x-text-input 
                id="password_confirmation" 
                class="block mt-1 w-full" 
                type="password" 
                name="password_confirmation" 
                required 
                autocomplete="new-password" 
            />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button>
                {{ __('Resetear Contraseña') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
