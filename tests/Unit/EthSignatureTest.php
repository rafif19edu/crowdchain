<?php

use App\Support\EthSignature;

// Signature produced by ethers.js Wallet.signMessage (same as MetaMask personal_sign)
// with Hardhat's public test account #0. Never used for real funds.
const VECTOR_ADDRESS = '0xf39Fd6e51aad88F6F4ce6aB8827279cffFb92266';
const VECTOR_MESSAGE = "CrowdChain wallet link\nAccount: donor@example.test\nNonce: 0123456789abcdef0123456789abcdef\nIssued: 2026-11-28T10:00:00+00:00";
const VECTOR_SIGNATURE = '0x9a8ed44875d3fb806e595288de40af71eaf86b65be17a95a30910ccb9dad8a5250091da5f77d8f40a838ef8e2529a21d0a63e0857d521eeb1534b55ffa7e04621b';

it('recovers the signer address of a MetaMask personal_sign signature', function () {
    expect(EthSignature::recoverAddress(VECTOR_MESSAGE, VECTOR_SIGNATURE))
        ->toBe(strtolower(VECTOR_ADDRESS));
});

it('verifies regardless of address letter case', function () {
    expect(EthSignature::verify(VECTOR_MESSAGE, VECTOR_SIGNATURE, VECTOR_ADDRESS))->toBeTrue();
});

it('rejects a signature over a different message', function () {
    expect(EthSignature::verify(VECTOR_MESSAGE.' tampered', VECTOR_SIGNATURE, VECTOR_ADDRESS))->toBeFalse();
});

it('rejects malformed signatures', function (string $signature) {
    expect(EthSignature::verify(VECTOR_MESSAGE, $signature, VECTOR_ADDRESS))->toBeFalse();
})->with([
    'too short' => ['0x1234'],
    'not hex' => ['0x'.str_repeat('zz', 65)],
    'bad v' => [substr(VECTOR_SIGNATURE, 0, -2).'05'],
]);

it('validates address format', function () {
    expect(EthSignature::isAddress(VECTOR_ADDRESS))->toBeTrue()
        ->and(EthSignature::isAddress('0x123'))->toBeFalse();
});
