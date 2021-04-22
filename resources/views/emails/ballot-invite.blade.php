@component('mail::message', ['personalization' => $personalization])

{{ $template }}

@component('mail::button', ['url' => $url])
{{ __('emails.invite.btnConfirm') }}
@endcomponent

@endcomponent
