@component('mail::message', ['personalization' => $personalization])

{{ $template }}

@component('mail::button', ['url' => $url])
{{ __('emails.verification.btnConfirm') }}
@endcomponent

@endcomponent
