<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SimpleNotification extends Notification
{
    use Queueable;

    protected $text;
    protected $assetRequestId;

    public function __construct($text, $assetRequestId = null)
    {
        $this->text = $text;
        $this->assetRequestId = $assetRequestId;
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toArray($notifiable)
    {
        return [
            'text' => $this->text,
            'asset_request_id' => $this->assetRequestId,
        ];
    }
}
