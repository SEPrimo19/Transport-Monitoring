<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * One password policy for every account this console creates.
 *
 * Before this, each request class carried a bare `min:8`, so "P@ssword" and
 * "12345678" both passed on four separate screens that could drift apart. The
 * rule now lives in one place and every call site asks for it by name.
 *
 * WHY THESE SETTINGS
 *   - 12 characters. Length beats composition against offline cracking, and
 *     these are admin-chosen and written down once rather than memorised daily.
 *   - Mixed case + numbers + symbols. Raises the per-character search space for
 *     an attacker who gets the hash, and rules out the dictionary words that
 *     an 8-character minimum quietly permits.
 *   - uncompromised(). Checks the candidate against the Have I Been Pwned
 *     corpus using k-anonymity: only the first five characters of the SHA-1
 *     leave this server, never the password. This is the control that actually
 *     stops credential stuffing, which no amount of composition rules does --
 *     "Summer2024!" satisfies every other requirement here and appears in
 *     breach corpora hundreds of thousands of times. Laravel's verifier fails
 *     OPEN if the API is unreachable, so a field pilot with bad connectivity
 *     degrades to the other rules rather than blocking account creation.
 *
 * Brute force against the login itself is handled separately, by the
 * `throttle:8,1` on POST /login and `throttle:6,1` on the privileged routes.
 * Password strength is the second half of that pair: throttling stops guessing
 * at the door, strength stops a leaked hash being useful.
 */
final class AdminPasswordPolicy
{
    public const MIN_LENGTH = 12;

    /**
     * Validation rules for a new or reset password.
     *
     * @return array<int, mixed>
     */
    public static function rules(bool $confirmed = false): array
    {
        $rules = [
            'required',
            'string',
            Password::min(self::MIN_LENGTH)
                ->mixedCase()
                ->numbers()
                ->symbols()
                ->uncompromised(),
        ];

        if ($confirmed) {
            $rules[] = 'confirmed';
        }

        return $rules;
    }

    /**
     * The same requirements in words, for the screen that asks for the password.
     * Kept beside the rules so the help text cannot describe a policy that is
     * no longer enforced.
     *
     * @return array<int, string>
     */
    public static function requirements(): array
    {
        return [
            'At least '.self::MIN_LENGTH.' characters',
            'An uppercase and a lowercase letter',
            'At least one number',
            'At least one symbol (e.g. ! ? # @)',
            'Not a password seen in a known data breach',
        ];
    }

    public static function hint(): string
    {
        return 'At least '.self::MIN_LENGTH.' characters, with upper and lower case, '
            .'a number and a symbol.';
    }
}
