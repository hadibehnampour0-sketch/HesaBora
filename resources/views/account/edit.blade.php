<x-platform-layout :title="__('Account Settings')">

    <div class="mx-auto max-w-2xl space-y-4 px-4 py-6">

        <div>
            <h1 class="text-2xl font-bold text-base-content">{{ __('Account Settings') }}</h1>
            <p class="mt-1 text-sm text-base-content/60">{{ __('Update your own username and password.') }}</p>
        </div>

        <x-show-message-bags />

        <section class="card border border-base-300 bg-base-100/90 shadow-sm">
            <div class="card-body">
                <h2 class="card-title text-base">{{ __('Display Mode') }}</h2>
                <p class="text-sm text-base-content/60">{{ __('Simple mode offers a guided, step-by-step flow for some screens (starting with Sell Invoice creation), in plain business language. Advanced mode is the full, unchanged accounting view.') }}</p>

                <form method="POST" action="{{ route('account.update-ui-mode') }}" class="mt-3 flex items-center gap-4">
                    @csrf
                    @method('PATCH')

                    @foreach (\App\Enums\UiMode::cases() as $mode)
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="radio" name="ui_mode" value="{{ $mode->value }}" class="radio radio-primary radio-sm"
                                {{ $user->ui_mode === $mode ? 'checked' : '' }} onchange="this.form.submit()" />
                            <span class="text-sm">{{ $mode->label() }}</span>
                        </label>
                    @endforeach
                </form>
            </div>
        </section>

        <section class="card border border-base-300 bg-base-100/90 shadow-sm">
            <div class="card-body">
                <form method="POST" action="{{ route('account.update') }}">
                    @csrf
                    @method('PUT')

                    <x-text-input class="mt-1 w-full" :title="__('Username')" input_name="username"
                        id_input="username" type="text" :input_value="old('username', $user->username)"
                        autocomplete="username" required />

                    <x-text-input class="mt-3 w-full" :title="__('New Password')" input_name="password"
                        id_input="password" type="password" :placeholder="__('Leave blank to keep your current password')"
                        autocomplete="new-password" />

                    <x-text-input class="mt-3 w-full" :title="__('Confirm New Password')"
                        input_name="password_confirmation" id_input="password_confirmation" type="password"
                        autocomplete="new-password" />

                    <div class="divider"></div>

                    <x-text-input class="mt-1 w-full" :title="__('Current Password')" input_name="current_password"
                        id_input="current_password" type="password"
                        :placeholder="__('Required to confirm any change')" autocomplete="current-password" required />

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</x-platform-layout>
