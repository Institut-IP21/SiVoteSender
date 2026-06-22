<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Personalization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The result-preview endpoint renders the REAL BallotResult mailable to HTML, so the
 * app-side preview is byte-for-byte what gets sent (chrome, results-link button,
 * personalization). It must never send or persist anything.
 */
class BallotResultPreviewTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, string> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'template' => 'The results for the AGM are in. **Thank you for voting.**',
            'subject' => 'Voting results',
            'resultLink' => 'https://engine.test/election/E/ballot/B/result',
            'locale' => 'en',
        ], $overrides);
    }

    public function test_renders_the_real_result_mailable_with_body_and_link_button(): void
    {
        $res = $this->postJson('/api/ballot/result-preview', $this->payload(), [
            'Authorization' => $this->token,
            'Owner' => fake()->uuid(),
        ]);

        $res->assertOk();
        $html = $res->getContent();

        // The organizer's body is rendered (Markdown → HTML).
        $this->assertStringContainsString('The results for the AGM are in', $html);
        $this->assertStringContainsString('Thank you for voting', $html);
        // The auto-appended "view results" button points at the results link.
        $this->assertStringContainsString('https://engine.test/election/E/ballot/B/result', $html);
        $this->assertStringContainsString(__('emails.result.link', [], 'en'), $html);
    }

    public function test_uses_the_owner_brand_colour_on_the_action_button(): void
    {
        $owner = fake()->uuid();
        Personalization::create([
            'owner' => $owner,
            'photo_url' => 'https://cdn.test/logo.png',
            'brand_color' => '#ff0000',
        ]);

        $res = $this->postJson('/api/ballot/result-preview', $this->payload(), [
            'Authorization' => $this->token,
            'Owner' => $owner,
        ]);

        $res->assertOk();
        $html = (string) $res->getContent();

        // The results button must carry the org brand colour inline (same as the
        // invite button), with an adaptive readable text colour.
        $this->assertMatchesRegularExpression('/<a [^>]*class="[^"]*button[^"]*"/', $html);
        $this->assertStringContainsString('background-color: #ff0000', $html);
        $this->assertStringContainsString('color: #11161a', $html);
    }

    public function test_requires_authorization(): void
    {
        $this->postJson('/api/ballot/result-preview', $this->payload())->assertUnauthorized();
    }

    public function test_validates_required_fields(): void
    {
        $this->postJson('/api/ballot/result-preview', ['subject' => 'x'], [
            'Authorization' => $this->token,
            'Owner' => fake()->uuid(),
        ])->assertStatus(422);
    }
}
