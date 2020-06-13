<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class ShareAdvert extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $name;
    public $link;
    public $messages;
    public $street;
    public $number;
    public $neighborhood;
    public $city;
    public $state;
    public $cep;
    public $price;
    public $transaction;
    public $picture;

    public function __construct($name, $link, $messages, $advert)
    {
        $this->name = $name;
        $this->link = $link;
        $this->messages = $messages;

        $this->street = $advert->property->street;
        $this->number = $advert->property->number;
        $this->neighborhood = $advert->property->neighborhood;
        $this->city = $advert->property->city;
        $this->state = $advert->property->state;
        $this->cep = substr($advert->property->cep, 0, 5) . '-' . substr($advert->property->cep, 5);

        $this->price = 'R$' . number_format($advert->price, 2, ',', '.');
        $this->transaction = ($advert->transaction == 'vender') ? 'À venda' : 'Para alugar';
        $this->picture = json_decode($advert->property->picture)[0];
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('no-reply@wizzer.com.br', $this->name)
            ->subject($this->name . ' compartilhou um imóvel com você')
            ->view('emails.shareadvert');
    }
}
