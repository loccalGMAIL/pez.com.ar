<?php

namespace App\Models;

use Database\Factories\PushSubscriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Minishlink\WebPush\Subscription;

/**
 * A browser or installed app that accepted notifications. The endpoint is the
 * push service URL (Google, Apple, Mozilla) that delivers to that device.
 */
class PushSubscription extends Model
{
    /** @use HasFactory<PushSubscriptionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'endpoint',
        'public_key',
        'auth_token',
        'content_encoding',
        'user_agent',
    ];

    protected $hidden = [
        'public_key',
        'auth_token',
    ];

    protected static function booted(): void
    {
        static::saving(function (PushSubscription $subscription) {
            $subscription->endpoint_hash = self::hash($subscription->endpoint);
        });
    }

    public static function hash(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    /**
     * @param  Builder<PushSubscription>  $query
     */
    public function scopeForEndpoint(Builder $query, string $endpoint): void
    {
        $query->where('endpoint_hash', self::hash($endpoint));
    }

    public function toWebPush(): Subscription
    {
        return new Subscription($this->endpoint, $this->public_key, $this->auth_token, $this->content_encoding);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
