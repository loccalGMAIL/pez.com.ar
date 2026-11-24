<?php

namespace Database\Factories;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PushSubscription>
 */
class PushSubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/'.fake()->unique()->uuid(),
            // A real P-256 public key: the payload is encrypted with it before sending.
            'public_key' => self::publicKey(),
            'auth_token' => self::base64Url(random_bytes(16)),
            'content_encoding' => 'aes128gcm',
        ];
    }

    private static function publicKey(): string
    {
        $key = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $details = openssl_pkey_get_details($key);

        return self::base64Url("\x04".$details['ec']['x'].$details['ec']['y']);
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
