<?php

use App\Models\User;
use App\Models\WalletNonce;

// Real MetaMask-style signature (ethers.js) by Hardhat public test account #0.
const W_ADDRESS = '0xf39fd6e51aad88f6f4ce6ab8827279cfffb92266';
const W_NONCE = '0123456789abcdef0123456789abcdef';
const W_MESSAGE = "CrowdChain wallet link\nAccount: donor@example.test\nNonce: 0123456789abcdef0123456789abcdef\nIssued: 2026-11-28T10:00:00+00:00";
const W_SIGNATURE = '0x9a8ed44875d3fb806e595288de40af71eaf86b65be17a95a30910ccb9dad8a5250091da5f77d8f40a838ef8e2529a21d0a63e0857d521eeb1534b55ffa7e04621b';

function nonceFor(User $user, array $overrides = []): WalletNonce
{
    return $user->walletNonces()->create(array_merge([
        'nonce' => W_NONCE,
        'message' => W_MESSAGE,
        'expires_at' => now()->addMinutes(5),
    ], $overrides));
}

function linkPayload(array $overrides = []): array
{
    return array_merge(['address' => W_ADDRESS, 'signature' => W_SIGNATURE, 'nonce' => W_NONCE], $overrides);
}

it('issues a nonce message that names the account', function () {
    $user = donor();

    $this->actingAs($user)->postJson('/api/v1/wallet/nonce')
        ->assertCreated()
        ->assertJsonPath('data.message', fn (string $m) => str_contains($m, 'Account: '.$user->email))
        ->assertJsonStructure(['data' => ['nonce', 'message', 'expires_at']]);
});

it('requires a verified email before linking a wallet', function () {
    $this->actingAs(User::factory()->unverified()->create())->postJson('/api/v1/wallet/nonce')->assertForbidden();
});

it('links the wallet when the signature matches', function () {
    $user = donor(['email' => 'donor@example.test']);
    nonceFor($user);

    $this->actingAs($user)->postJson('/api/v1/wallet/link', linkPayload())
        ->assertOk()
        ->assertJsonPath('data.wallet_address', W_ADDRESS);

    expect(WalletNonce::first()->used_at)->not->toBeNull();
});

it('refuses a signature from a different wallet', function () {
    $user = donor(['email' => 'donor@example.test']);
    nonceFor($user);

    $this->actingAs($user)->postJson('/api/v1/wallet/link', linkPayload(['address' => '0x'.str_repeat('1', 40)]))
        ->assertUnprocessable()->assertJsonValidationErrors('signature');
});

it('refuses expired and already-used nonces', function () {
    $user = donor(['email' => 'donor@example.test']);
    nonceFor($user, ['expires_at' => now()->subMinute()]);

    $this->actingAs($user)->postJson('/api/v1/wallet/link', linkPayload())
        ->assertUnprocessable()->assertJsonValidationErrors('nonce');

    WalletNonce::query()->update(['expires_at' => now()->addMinutes(5), 'used_at' => now()]);

    $this->actingAs($user)->postJson('/api/v1/wallet/link', linkPayload())
        ->assertUnprocessable()->assertJsonValidationErrors('nonce');
});

it("refuses another user's nonce", function () {
    $owner = donor(['email' => 'donor@example.test']);
    nonceFor($owner);

    $this->actingAs(donor())->postJson('/api/v1/wallet/link', linkPayload())
        ->assertUnprocessable()->assertJsonValidationErrors('nonce');
});

it('refuses a wallet already linked to another account', function () {
    User::factory()->withWallet(W_ADDRESS)->create();
    $user = donor(['email' => 'donor@example.test']);
    nonceFor($user);

    $this->actingAs($user)->postJson('/api/v1/wallet/link', linkPayload())
        ->assertUnprocessable()->assertJsonValidationErrors('address');
});

it('unlinks the wallet', function () {
    $user = User::factory()->withWallet()->create();

    $this->actingAs($user)->deleteJson('/api/v1/wallet')
        ->assertOk()
        ->assertJsonPath('data.wallet_address', null);
});
