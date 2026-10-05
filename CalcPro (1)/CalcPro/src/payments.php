<?php
// Backend payment logic only. The frontend never sees card data or API keys.
const PLANS = ['credit' => ['label' => 'Calculation Credit', 'amount' => 300], 'subscription' => ['label' => 'Monthly Pro', 'amount' => 99]];

function charge(string $plan): array {
    if (!isset(PLANS[$plan])) throw new Exception('Unknown plan');
    $provider = getenv('PAYMENT_PROVIDER') ?: 'mock';
    if ($provider === 'mock') // Demo provider: always succeeds, charges nothing.
        return ['id' => 'mock_' . bin2hex(random_bytes(6)), 'status' => 'succeeded', 'amount' => PLANS[$plan]['amount']];
    // Connect Stripe here (see README) using getenv('STRIPE_SECRET_KEY').
    if ($provider === 'stripe') throw new Exception('Stripe provider not implemented yet - see README');
    throw new Exception('Unknown payment provider');
}
