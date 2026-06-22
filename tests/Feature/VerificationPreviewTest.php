<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Personalization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The verification-preview endpoint renders the REAL Verification mailable to HTML, so
 * the app-side preview is byte-for-byte what gets sent (chrome, "Verify now" button,
 * %%LINK%% substitution, owner personalization). It must never send or persist anything.
 */
class VerificationPreviewTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'template' => 'Please confirm your address. Link: %%LINK%%',
            'subject' => 'Confirm your email',
            'url' => 'https://sender.test/verification/confirm/sample',
            'locale' => 'en',
        ], $overrides);
    }

    public function test_renders_the_real_verification_mailable_with_substitution_and_chrome(): void
    {
        $owner = fake()->uuid();

        $res = $this->postJson('/api/ballot/verification-preview', $this->payload(), [
            'Authorization' => $this->token,
            'Owner' => $owner,
        ]);

        $res->assertOk();
        $html = (string) $res->getContent();

        // %%LINK%% substituted exactly as a real send does.
        $this->assertStringContainsString('https://sender.test/verification/confirm/sample', $html);
        // Body text present.
        $this->assertStringContainsString('Please confirm your address', $html);
        // The auto-appended "Verify now" button (the real mailable's, localized).
        $this->assertStringContainsString(__('emails.verification.btnConfirm', [], 'en'), $html);
        // Header chrome: no personalization logo → the default eGlasovanje wordmark.
        $this->assertStringContainsString('Glasovanje', $html);
    }

    public function test_uses_the_owner_personalization_logo_when_present(): void
    {
        $owner = fake()->uuid();
        Personalization::create([
            'owner' => $owner,
            'photo_url' => 'https://cdn.test/logo.png',
        ]);

        $res = $this->postJson('/api/ballot/verification-preview', $this->payload(), [
            'Authorization' => $this->token,
            'Owner' => $owner,
        ]);

        $res->assertOk();
        $this->assertStringContainsString('https://cdn.test/logo.png', (string) $res->getContent());
    }

    public function test_requires_authorization(): void
    {
        $this->postJson('/api/ballot/verification-preview', $this->payload(), ['Owner' => fake()->uuid()])
            ->assertStatus(401);
    }

    public function test_validates_required_fields(): void
    {
        $this->postJson('/api/ballot/verification-preview', ['subject' => 'x'], [
            'Authorization' => $this->token,
            'Owner' => fake()->uuid(),
        ])->assertStatus(422);
    }
}
