<x-wide-guest-layout>
    <div class="flex min-h-screen">
        <!-- Left: Logo + Centered Login Block -->
        <div class="bg-gray-200 w-full md:w-2/3 flex flex-col items-center justify-center bg-white p-8 space-y-6">
            
            <!-- Logo (outside login box) -->
            <div class="mb-2">
                <img src="{{ asset('images/logo.png') }}" alt="Logo" class="h-28 w-auto">
            </div>

            <!-- Login Block -->
            <div class="w-full max-w-xl bg-white rounded-lg shadow-lg p-10">
                <!-- Session Status -->
                <x-auth-session-status class="mb-4" :status="session('status')" />

                <h2 class="text-3xl font-bold text-gray-800 mb-6 text-center">{{ __('Login') }}</h2>

                <form method="POST" action="{{ route('login') }}" class="space-y-6">
                    @csrf

                    <!-- Username -->
                    <div>
                        <x-input-label for="username" :value="__('Username')" />
                        <x-text-input id="username" class="block mt-1 w-full" type="text" name="username"
                            :value="old('username')" required autofocus autocomplete="username" />
                        <x-input-error :messages="$errors->get('username')" class="mt-2" />
                    </div>

                    <!-- Password -->
                    <div>
                        <x-input-label for="password" :value="__('Password')" />
                        <x-text-input id="password" class="block mt-1 w-full" type="password" name="password"
                            required autocomplete="current-password" />
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <!-- Remember Me -->
                    <div class="flex items-center justify-between">
                        <label for="remember_me" class="flex items-center">
                            <input id="remember_me" type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                name="remember">
                            <span class="ml-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
                        </label>

                        {{-- @if (Route::has('password.request'))
                            <a class="text-sm text-indigo-600 hover:underline"
                                href="{{ route('password.request') }}">
                                {{ __('Forgot your password?') }}
                            </a>
                        @endif --}}
                    </div>

                    <div>
                        <x-primary-button class="w-full justify-center">
                            {{ __('Log in') }}
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right: Image -->
        <div class="hidden md:block md:w-1/3 h-screen bg-cover bg-center"
             style="background-image: url('/images/sliver.jpg');">
        </div>
    </div>
</x-wide-guest-layout>
