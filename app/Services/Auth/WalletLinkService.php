<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\WalletNonce;
use App\Support\EthSignature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Links a MetaMask wallet to an account by checking a signed, single-use
 * nonce message (FR-UM-13, NFR-SEC-10, docs/16 §3). No gas is spent.
 */
class WalletLinkService
{
    public function issueNonce(User $user): WalletNonce
    {
        $nonce = Str::lower(Str::random(32));
        $issued = now();

        return $user->walletNonces()->create([
            'nonce' => $nonce,
            'message' => implode("\n", [
                'CrowdChain wallet link',
                'Account: '.$user->email,
                'Nonce: '.$nonce,
                'Issued: '.$issued->toIso8601String(),
            ]),
            'expires_at' => $issued->copy()->addMinutes(config('crowdchain.wallet_nonce_minutes')),
        ]);
    }

    public function link(User $user, string $address, string $signature, string $nonce): User
    {
        $address = strtolower($address);

        return DB::transaction(function () use ($user, $address, $signature, $nonce) {
            /** @var WalletNonce|null $record */
            $record = $user->walletNonces()->where('nonce', $nonce)->lockForUpdate()->first();

            if (! $record || ! $record->isUsable()) {
                throw ValidationException::withMessages(['nonce' => 'This signing request has expired. Please try again.']);
            }

            if (! EthSignature::verify($record->message, $signature, $address)) {
                throw ValidationException::withMessages(['signature' => 'The signature does not match this wallet address.']);
            }

            $takenByOther = User::withTrashed()
                ->where('wallet_address', $address)
                ->whereKeyNot($user->getKey())
                ->exists();

            if ($takenByOther) {
                throw ValidationException::withMessages(['address' => 'This wallet is already linked to another account.']);
            }

            $this->assertReplaceable($user, $address);

            $record->forceFill(['used_at' => now()])->save();
            $user->forceFill(['wallet_address' => $address, 'wallet_linked_at' => now()])->save();

            return $user;
        });
    }

    public function unlink(User $user): User
    {
        $this->assertReplaceable($user, null);
        $user->forceFill(['wallet_address' => null, 'wallet_linked_at' => null])->save();

        return $user;
    }

    /**
     * FR-UM-14 / D-57: a wallet cannot change while a fundraiser has an active
     * campaign or a donor has an unclaimed refund. Campaigns and refunds arrive
     * in stages S2 and S4, which extend this check.
     */
    protected function assertReplaceable(User $user, ?string $newAddress): void
    {
        if ($user->wallet_address === null || $user->wallet_address === $newAddress) {
            return;
        }

        foreach (app()->tagged('wallet.replacement_guards') as $guard) {
            if ($message = $guard($user)) {
                throw ValidationException::withMessages(['address' => $message]);
            }
        }
    }
}
