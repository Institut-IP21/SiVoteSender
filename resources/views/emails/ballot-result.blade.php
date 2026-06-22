@component('mail::message', ['personalization' => $personalization])

{{ $template }}

@component('mail::button', ['url' => $resultLink, 'brandColor' => $personalization?->brand_color])
{{ __('emails.result.link') }}
@endcomponent

@endcomponent