@component('mail::message', ['personalization' => $personalization])

    {{ $template }}

    @component('mail::button', ['url' => $code['access_url']])
        {{ __('emails.invite.btnConfirm') }}
    @endcomponent

@endcomponent
