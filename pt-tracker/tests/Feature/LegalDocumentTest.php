<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LegalDocumentTest extends TestCase
{
    private string $policyRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policyRoot = storage_path('framework/testing/policies-'.Str::uuid());
        File::ensureDirectoryExists($this->policyRoot);
        config(['stage-ready.policies_path' => $this->policyRoot]);
    }

    protected function tearDown(): void
    {
        $root = realpath($this->policyRoot);
        $parent = realpath(storage_path('framework/testing'));
        if ($root !== false && $parent !== false && str_starts_with($root, $parent.DIRECTORY_SEPARATOR)) {
            File::deleteDirectory($root);
        }
        parent::tearDown();
    }

    public function test_unpublished_or_blank_policies_do_not_claim_to_be_published(): void
    {
        $this->get('/privacy')->assertNotFound();
        File::put($this->policyRoot.'/terms.md', '   ');
        $this->get('/terms')->assertNotFound();
    }

    public function test_published_markdown_strips_raw_html_and_unsafe_links(): void
    {
        File::put($this->policyRoot.'/privacy.md', "## Your information\n\n**Reviewed policy**\n\n<script>alert('unsafe')</script>\n\n[Unsafe](javascript:alert(1))");
        $this->withoutVite()->get('/privacy')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('LegalDocument')->where('title', 'Privacy policy')
            ->where('content', fn (string $html) => str_contains($html, '<strong>Reviewed policy</strong>')
                && ! str_contains($html, '<script') && ! str_contains($html, 'javascript:')));
    }

    public function test_public_policy_and_support_props_do_not_include_provider_secrets(): void
    {
        config(['stage-ready.support_email' => 'support@stage-ready.co.uk', 'stage-ready.privacy_url' => 'https://stage-ready.co.uk/privacy',
            'services.stripe.secret' => 'private-stripe-sentinel', 'mail.mailers.smtp.password' => 'private-smtp-sentinel']);
        $this->withoutVite()->get('/register')->assertOk()->assertDontSee('private-stripe-sentinel')->assertDontSee('private-smtp-sentinel')
            ->assertInertia(fn (Assert $page) => $page->where('business.support_email', 'support@stage-ready.co.uk')
                ->where('business.privacy_url', 'https://stage-ready.co.uk/privacy'));
    }
}
