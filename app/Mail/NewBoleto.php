<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class NewBoleto extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $name;
    public $paymentLink;

    public function __construct($name, $paymentLink)
    {
        $this->name = $name;
        $this->paymentLink = $paymentLink;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('no-reply@wizzer.com.br', 'Wizzer')
            ->subject('Geramos seu próximo boleto')
            ->view('emails.newboleto');
    }
}
