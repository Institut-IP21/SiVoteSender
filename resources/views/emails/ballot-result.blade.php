@component('mail::message', ['personalization' => $personalization])

    {{ $template }}

    @component('mail::button', ['url' => $resultLink])
        {{ __('emails.result.link') }}
    @endcomponent

@endcomponent
