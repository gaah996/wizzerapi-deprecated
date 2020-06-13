<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class LostPassword extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $name;
    public $resetCode;

    public function __construct($name, $resetCode)
    {
        $this->name = $name;
        $this->resetCode = $resetCode;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('no-reply@wizzer.com.br', 'Wizzer')
            ->subject('Recupere a sua senha')
            ->view('emails.lostpassword');
    }
}
