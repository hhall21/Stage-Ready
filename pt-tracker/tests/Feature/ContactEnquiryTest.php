<?php

namespace Tests\Feature;

use App\Models\ContactEnquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactEnquiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_contact_enquiry_is_persisted(): void
    {
        $response = $this->postJson('/api/contact-enquiries', [
            'name' => 'Jamie Taylor',
            'email' => 'jamie@example.com',
            'phone' => '+44 7700 900123',
            'service' => 'Personal training',
            'message' => 'I would like to arrange an introductory consultation.',
            'privacy_consent' => true,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'new');

        $this->assertDatabaseHas('contact_enquiries', [
            'name' => 'Jamie Taylor',
            'email' => 'jamie@example.com',
            'status' => 'new',
        ]);

        $this->assertNotNull(ContactEnquiry::firstOrFail()->privacy_consent_at);
    }

    public function test_an_enquiry_requires_valid_contact_details_and_consent(): void
    {
        $response = $this->postJson('/api/contact-enquiries', [
            'name' => '',
            'email' => 'not-an-email',
            'message' => 'Too short',
            'privacy_consent' => false,
            'website' => 'https://spam.example',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'email',
                'message',
                'privacy_consent',
                'website',
            ]);
    }
}
