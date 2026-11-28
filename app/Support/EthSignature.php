<?php

namespace App\Support;

use Elliptic\EC;
use InvalidArgumentException;
use kornrunner\Keccak;
use Throwable;

/**
 * Recovers the Ethereum address that signed a message with MetaMask's
 * `personal_sign` (EIP-191). Used to prove wallet ownership (FR-UM-13).
 *
 * How it works, in plain words:
 *  1. MetaMask prefixes the text with "\x19Ethereum Signed Message:\n<length>"
 *     and hashes it with Keccak-256.
 *  2. The 65-byte signature is r (32) + s (32) + v (1).
 *  3. From the hash and (r, s, v) the signer's public key can be recovered.
 *  4. The address is the last 20 bytes of Keccak-256(public key).
 */
final class EthSignature
{
    /**
     * @return string lower-case 0x address
     *
     * @throws InvalidArgumentException when the signature is malformed
     */
    public static function recoverAddress(string $message, string $signature): string
    {
        $sig = strtolower(preg_replace('/^0x/i', '', $signature) ?? '');

        if (! preg_match('/^[0-9a-f]{130}$/', $sig)) {
            throw new InvalidArgumentException('Signature must be 65 bytes of hex.');
        }

        $r = substr($sig, 0, 64);
        $s = substr($sig, 64, 64);
        $v = hexdec(substr($sig, 128, 2));

        if ($v >= 27) {
            $v -= 27;
        }

        if ($v !== 0 && $v !== 1) {
            throw new InvalidArgumentException('Invalid recovery id.');
        }

        try {
            $ec = new EC('secp256k1');
            $publicKey = $ec->recoverPubKey(self::hashMessage($message), ['r' => $r, 's' => $s], $v);
            $uncompressed = $publicKey->encode('hex'); // "04" + X (64 hex) + Y (64 hex)
        } catch (Throwable $e) {
            throw new InvalidArgumentException('Signature could not be recovered.', previous: $e);
        }

        return '0x'.substr(Keccak::hash(hex2bin(substr($uncompressed, 2)), 256), -40);
    }

    public static function verify(string $message, string $signature, string $address): bool
    {
        try {
            return self::recoverAddress($message, $signature) === strtolower($address);
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /** EIP-191 personal message hash, hex without 0x. */
    public static function hashMessage(string $message): string
    {
        $prefixed = "\x19Ethereum Signed Message:\n".strlen($message).$message;

        return Keccak::hash($prefixed, 256);
    }

    public static function isAddress(string $address): bool
    {
        return (bool) preg_match('/^0x[0-9a-fA-F]{40}$/', $address);
    }
}
