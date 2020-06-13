<?php

namespace App\Console\Commands;

use App\Advert;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DeleteOldAdverts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'adverts:delete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes all adverts that are deactivated for more than 2 months straight';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $adverts = Advert::where('status', '0')->get();

        foreach($adverts as $advert) {
            $lastUpdate = new Carbon($advert->updated_at);

            if($lastUpdate < Carbon::now()->subMonths(2)){
                //Development delete
                if($advert->advert_type == 'development') {
                    //Deletes the properties
                    foreach($advert->development->properties as $property) {
                        $property->delete();
                    }

                    //Deletes the development
                    $advert->development()->delete();

                    //Deletes the advert
                    $advert->delete();

                    //Deletes the advert image folder
                    Storage::delete('public/developments/' . $advert->development->id);
                } else {
                    //Deletes the property
                    $advert->property->delete();

                    //Deletes the advert
                    $advert->delete();

                    //Deletes the advert image folder
                    Storage::delete('public/properties/' . $advert->property->property_id);
                }
            }
        }
    }
}
