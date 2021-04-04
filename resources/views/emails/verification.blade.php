@component('mail::message')

{{ $template }}

@component('mail::button', ['url' => $url])
{{ __('emails.verification.btnConfirm') }}
@endcomponent

@endcomponent
