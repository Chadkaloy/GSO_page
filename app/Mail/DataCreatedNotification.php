<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DataCreatedNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $id;
    public $data;
    public $url;

    /**
     * Create a new message instance.
     */
    public function __construct($id, $data, $url)
    {
        $this->id = $id;
        $this->data = $data->toArray();
        $this->url = $url;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject('New Data Created')
                    ->markdown('mail.data-created-notification');
    }
}