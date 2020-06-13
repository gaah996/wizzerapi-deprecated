<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class RequestUnderReview extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;
    public $price;
    public $advertsNumber;

    public function __construct($user,$price,$advertsNumber)
    {
        $this->user = $user;
        $this->price = $price;
        $this->advertsNumber = $advertsNumber;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $email =  $this->from('no-reply@wizzer.com.br')
            ->subject('Estamos cuidando do seu plano personalizado')
            ->view('emails.requestunderreview');

        return $email;
    }
}
