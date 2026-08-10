<?php

namespace App\Domain\Communications;

use App\Models\Message;
use Illuminate\Support\Facades\Mail;

final class MailMessageProvider implements MessageProvider
{
    public function send(Message $message): MessageDeliveryResult
    {
        if (! config('services.notifications.email_enabled', false)) {
            return MessageDeliveryResult::failed('email', 'provider_not_configured');
        }

        Mail::raw($message->body, function ($mail) use ($message): void {
            $mail->to($message->recipient)->subject($message->subject ?? config('app.name'));
        });

        return MessageDeliveryResult::sent('email', 'email-'.$message->public_id);
    }
}
