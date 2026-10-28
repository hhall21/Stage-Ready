<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StripeGateway;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Tests\TestCase;

class StripeGatewayPricingTest extends TestCase
{
    private ClientInterface $previousHttpClient;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://stage-ready.example', 'services.stripe.secret' => 'sk_test_example',
            'services.stripe.webhook_secret' => 'whsec_test', 'services.stripe.live_mode' => false,
            'services.stripe.prices.powerlifting' => 'price_powerlifting', 'services.stripe.prices.bodybuilding' => 'price_bodybuilding']);
        $this->previousHttpClient = ApiRequestor::httpClient();
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient($this->previousHttpClient);
        parent::tearDown();
    }

    public static function plans(): array
    {
        return ['powerlifting' => [false, 600, 'price_powerlifting'], 'bodybuilding' => [true, 800, 'price_bodybuilding']];
    }

    #[DataProvider('plans')]
    public function test_checkout_verifies_the_saved_disciplines_price_and_sends_it_to_stripe(bool $bodybuilding, int $amount, string $priceId): void
    {
        $coach = new User(['name' => 'Coach', 'email' => 'coach@example.com']);
        $coach->forceFill(['is_bodybuilding_coach' => $bodybuilding]);
        $coach->id = 42;
        $http = Mockery::mock(ClientInterface::class);
        ApiRequestor::setHttpClient($http);
        $http->expects('request')->withArgs(fn ($method, $url) => $method === 'get' && $url === 'https://api.stripe.com/v1/prices/'.$priceId)
            ->andReturn([json_encode($this->price($priceId, $amount), JSON_THROW_ON_ERROR), 200, []]);
        $http->expects('request')->withArgs(fn ($method, $url) => $method === 'post' && $url === 'https://api.stripe.com/v1/checkout/sessions')
            ->andReturnUsing(function ($method, $url, $headers, $params) use ($priceId) {
                $this->assertSame('subscription', $params['mode']);
                $this->assertSame([['price' => $priceId, 'quantity' => 1]], $params['line_items']);
                $this->assertSame('cus_42', $params['customer']);
                $this->assertSame('42', $params['client_reference_id']);
                $this->assertSame(['metadata' => ['coach_id' => '42']], $params['subscription_data']);

                return [json_encode(['id' => 'cs_test_plan', 'object' => 'checkout.session', 'url' => 'https://checkout.stripe.com/test'], JSON_THROW_ON_ERROR), 200, []];
            });
        $session = (new StripeGateway)->createCheckout($coach, 'cus_42');
        $this->assertSame('cs_test_plan', $session['id']);
    }

    public static function invalidPrices(): array
    {
        return [
            'old ten pound price' => [['unit_amount' => 1000]],
            'powerlifting amount for bodybuilding' => [['unit_amount' => 600]],
            'wrong currency' => [['currency' => 'usd']],
            'inactive' => [['active' => false]],
            'one-off payment' => [['type' => 'one_time']],
            'yearly' => [['recurring' => ['interval' => 'year', 'interval_count' => 1]]],
            'every two months' => [['recurring' => ['interval' => 'month', 'interval_count' => 2]]],
        ];
    }

    #[DataProvider('invalidPrices')]
    public function test_incorrect_stripe_prices_cannot_create_checkout(array $changes): void
    {
        $coach = new User(['is_bodybuilding_coach' => true]);
        $http = Mockery::mock(ClientInterface::class);
        ApiRequestor::setHttpClient($http);
        $http->expects('request')->once()->withArgs(fn ($method, $url) => $method === 'get' && $url === 'https://api.stripe.com/v1/prices/price_bodybuilding')
            ->andReturn([json_encode([...$this->price('price_bodybuilding', 800), ...$changes], JSON_THROW_ON_ERROR), 200, []]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The configured Stripe price for Bodybuilding must be £8 GBP every month.');
        (new StripeGateway)->createCheckout($coach, 'cus_42');
    }

    private function price(string $id, int $amount): array
    {
        return ['id' => $id, 'object' => 'price', 'active' => true, 'type' => 'recurring', 'unit_amount' => $amount,
            'currency' => 'gbp', 'recurring' => ['interval' => 'month', 'interval_count' => 1]];
    }
}
