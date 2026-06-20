<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Personalization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The invite-preview endpoint renders the REAL BallotInvite mailable to HTML, so the
 * app-side preview is byte-for-byte what gets sent (chrome, button, substitutions,
 * personalization). It must never send or persist anything.
 */
class BallotInvitePreviewTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'template' => 'Hello! Your code is %%CODE%%. Open: %%LINK%%',
            'subject' => 'Vote now',
            'url' => 'https://engine.test/election/E/ballot/B?code=%%CODE%%',
            'code' => 'AB12-CD34',
            'locale' => 'en',
        ], $overrides);
    }

    public function test_renders_the_real_invite_mailable_with_substitutions_and_chrome(): void
    {
        $owner = fake()->uuid();

        $res = $this->postJson('/api/ballot/invite-preview', $this->payload(), [
            'Authorization' => $this->token,
            'Owner' => $owner,
        ]);

        $res->assertOk();
        $html = $res->getContent();

        // %%CODE%% / %%LINK%% substituted exactly as a real send does.
        $this->assertStringContainsString('AB12-CD34', $html);
        $this->assertStringContainsString('https://engine.test/election/E/ballot/B?code=AB12-CD34', $html);
        // Body text is present.
        $this->assertStringContainsString('Your code is', $html);
        // The auto-appended button (the real mailable's, localized).
        $this->assertStringContainsString(__('emails.invite.btnConfirm', [], 'en'), $html);
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

        $res = $this->postJson('/api/ballot/invite-preview', $this->payload(), [
            'Authorization' => $this->token,
            'Owner' => $owner,
        ]);

        $res->assertOk();
        $this->assertStringContainsString('https://cdn.test/logo.png', $res->getContent());
    }

    public function test_requires_authorization(): void
    {
        $this->postJson('/api/ballot/invite-preview', $this->payload(), ['Owner' => fake()->uuid()])
            ->assertStatus(401);
    }

    public function test_validates_required_fields(): void
    {
        $this->postJson('/api/ballot/invite-preview', ['subject' => 'x'], [
            'Authorization' => $this->token,
            'Owner' => fake()->uuid(),
        ])->assertStatus(422);
    }
}
