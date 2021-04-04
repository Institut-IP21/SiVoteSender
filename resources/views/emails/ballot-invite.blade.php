@component('mail::message')

    {{ $template }}

    @component('mail::button', ['url' => $url])
        {{ __('emails.invite.btnConfirm') }}
    @endcomponent

@endcomponent
