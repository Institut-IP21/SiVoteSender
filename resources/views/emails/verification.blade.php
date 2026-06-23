@component('mail::message', ['personalization' => $personalization])

{{ $template }}

@component('mail::button', ['url' => $url, 'brandColor' => $personalization?->brand_color])
{{ __('emails.verification.btnConfirm') }}
@endcomponent

@endcomponent
