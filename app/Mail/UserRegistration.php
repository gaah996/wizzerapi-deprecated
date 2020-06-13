<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class UserRegistration extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function build()
    {
        return $this->from('no-reply@wizzer.com.br', 'Wizzer')
            ->subject('Bem-vindo ao Wizzer')
            ->view('emails.welcome');
    }
}
