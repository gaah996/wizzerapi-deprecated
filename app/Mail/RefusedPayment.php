<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class RefusedPayment extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $name;

    public function __construct($name)
    {
        $this->name;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('no-reply@wizzer.com.br', 'Wizzer')
            ->subject('Não conseguimos identificar o seu pagamento')
            ->view('emails.refusedpayment');
    }
}
