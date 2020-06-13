<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class InterestMessage extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $userName;
    public $name;
    public $email;
    public $phone;
    public $messages;
    public $reference;
    public $referenceLine2;

    public function __construct($userName, $name, $email, $phone, $messages, $advert)
    {
        $this->userName = $userName;
        $this->name = $name;
        $this->email = $email;
        $this->phone = $phone;
        $this->messages = $messages;
        $this->reference = $advert->property->street . ', ' . $advert->property->number . ' - ' . $advert->property->neighborhood;
        $this->referenceLine2 = $advert->property->city . ', ' . $advert->property->state . ', ' . substr($advert->property->cep, 0, 5) . '-' . substr($advert->property->cep, 5);
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('no-reply@wizzer.com.br', 'Wizzer')
            ->subject($this->name . ' te enviou uma mensagem')
            ->view('emails.interestmessage');
    }
}
