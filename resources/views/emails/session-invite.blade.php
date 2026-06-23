@component('mail::message', ['personalization' => $personalization])

{{ $template }}

@component('mail::button', ['url' => $code['access_url'], 'brandColor' => $personalization?->brand_color])
    {{ __('emails.invite.btnConfirm') }}
@endcomponent

@endcomponent
