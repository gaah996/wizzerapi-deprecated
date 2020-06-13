<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class OldUserEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $name;
    public $oldEmail;
    public $newEmail;

    public function __construct($name, $oldEmail, $newEmail)
    {
        $this->name = $name;
        $this->oldEmail = $oldEmail;
        $this->newEmail = $newEmail;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('no-reply@wizzer.com.br', 'Wizzer')
            ->subject('Seu e-mail de acesso mudou')
            ->view('emails.oldemail');
    }
}
