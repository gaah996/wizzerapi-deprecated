<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanGeocodeTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'geocode:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clears the geocode table after one month that the query has last been searched';

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
        DB::table('transient_geocode')->where('updated_at', '<', Carbon::now()->subMonth())->delete();
    }
}
