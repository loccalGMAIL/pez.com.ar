<?php

namespace App\Http\Controllers\FollowUps;

use App\Enums\FollowUpStatus;
use App\Http\Controllers\Controller;
use App\Models\FollowUp;
use Illuminate\Http\RedirectResponse;

/**
 * Opened from the panel in a new tab: marks the follow-up as sent and hands
 * over to WhatsApp with the message written. Sending it is still up to the user.
 */
class SendFollowUpOnWhatsApp extends Controller
{
    public function __invoke(FollowUp $followUp): RedirectResponse
    {
        abort_unless(in_array($followUp->status, [FollowUpStatus::Pending, FollowUpStatus::Sent], true), 404);

        $followUp->markAsSent();

        return redirect()->away($followUp->whatsappUrl());
    }
}
