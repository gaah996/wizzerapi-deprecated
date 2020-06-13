<?php

use Illuminate\Database\Seeder;

use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        //Adds the test user
//        DB::table('users')->insert([
//            'name' => 'Wizzer Test Account',
//            'email' => 'wizzer@wizzer.com.br',
//            'profile_type' => 1,
//            'password' => bcrypt('abcd1234'),
//            'cpf_cnpj' => '123.456.789-00',
//            'phone' => '(35) 98765-4321'
//        ]);

        //Adds the test plan rule
        DB::table('plan_rules')->insert([
            'adverts_number' => 1,
            'images_per_advert' => 25,
            'description' => 'Plano pessoa física de 30 dias',
            'profile_type' => 0,
            'price' => 49.90,
            'validity' => 30,
            'renewable' => 0
        ]);

        DB::table('plan_rules')->insert([
            'adverts_number' => 1,
            'images_per_advert' => 25,
            'description' => 'Plano pessoa física de 60 dias',
            'profile_type' => 0,
            'price' => 59.90,
            'validity' => 60,
            'renewable' => 0
        ]);

        DB::table('plan_rules')->insert([
            'adverts_number' => 1,
            'images_per_advert' => 25,
            'description' => 'Plano pessoa física de 90 dias',
            'profile_type' => 0,
            'price' => 69.90,
            'validity' => 90,
            'renewable' => 0
        ]);

        DB::table('plan_rules')->insert([
            'adverts_number' => 1,
            'images_per_advert' => 25,
            'description' => 'Plano pessoa física de 180 dias',
            'profile_type' => 0,
            'price' => 79.90,
            'validity' => 180,
            'renewable' => 0
        ]);

        DB::table('plan_rules')->insert([
            'adverts_number' => 10,
            'images_per_advert' => 55,
            'description' => '["E67FEC8F16164233341A7F89D93F427F", "F51816848A8ADE3004452FADDA533A26"]',
            'discount_code' => '["bemvindo", "BEMVINDO"]',
            'profile_type' => 1,
            'price' => 79.90,
            'validity' => 30,
            'renewable' => 1
        ]);

        DB::table('plan_rules')->insert([
            'adverts_number' => 25,
            'images_per_advert' => 55,
            'description' => '["F6447F9E4B4B530DD44DAF94756F1E28", "7F69C0EAA8A89AA664DAAF8546C6E0CE"]',
            'discount_code' => '["bemvindo", "BEMVINDO"]',
            'profile_type' => 1,
            'price' => 99.90,
            'validity' => 30,
            'renewable' => 1
        ]);

        DB::table('plan_rules')->insert([
            'adverts_number' => 50,
            'images_per_advert' => 55,
            'description' => '["C8492AD5F1F151FEE4672FA7A3A0DE79", "CF64BF6073732798845B5F9225E634A2"]',
            'discount_code' => '["bemvindo", "BEMVINDO"]',
            'profile_type' => 1,
            'price' => 119.90,
            'validity' => 30,
            'renewable' => 1
        ]);

        DB::table('plan_rules')->insert([
            'adverts_number' => 100,
            'images_per_advert' => 55,
            'description' => '["8490B679A4A4414FF40C2F87641A8C98", "19DD2FD3DCDC831774E67F84C7571732"]',
            'discount_code' => '["bemvindo", "BEMVINDO"]',
            'profile_type' => 1,
            'price' => 149.90,
            'validity' => 30,
            'renewable' => 1
        ]);

//        //Adds the test plan
//        DB::table('plans')->insert([
//            'user_id' => 1,
//            'plan_rule_id' => 1,
//            'payment_id' => 'Teste',
//            'payment_status' => 3,
//            'signature_date' => new DateTime
//        ]);
//
//        //Adds the test properties
//        for($i = 0; $i < 300; $i++){
//            DB::table('properties')->insert([
//                'user_id' => 1,
//                'property_type' => '["' . ['apartment', 'house', 'condo', 'farmhouse', 'roof', 'flat', 'studio', 'land', 'warehouse', 'commercial_set', 'farm', 'store', 'commercial_room', 'commercial_building'][mt_rand(0,13)] . '"]',
//                'description' => 'Propriedade de teste',
//                'cep' => '37031215',
//                'number' => 65,
//                'street' => 'Rua Juvenal Cardoso',
//                'neighborhood' => 'Residencial Belo Horizonte',
//                'city' => 'Varginha',
//                'state' => 'MG',
//                'lat' => ((mt_rand(-15000000, -5000000))/1000000),
//                'lng' => ((mt_rand(-50000000, -40000000))/1000000),
//                'rooms' => mt_rand(1,10),
//                'bathrooms' => mt_rand(1,10),
//                'parking_spaces' => mt_rand(1,10),
//                'area' => mt_rand(100,400),
//                'picture' => '["properties/img1.jpg"]'
//            ]);
//        }
//
//        //Adds the test adverts
//        for($i = 0; $i < 300; $i++){
//            DB::table('adverts')->insert([
//                'plan_id' => 1,
//                'property_id' => $i+1,
//                'user_id' => 1,
//                'price' => mt_rand(200000,300000),
//                'condo' => mt_rand(100,500),
//                'transaction' => ['vender', 'alugar'][mt_rand(0,1)],
//                'status' => 1,
//                'phone' => '(35) 98765-4321',
//                'email' => 'wizzer@wizzer.com.br',
//                'view_count' => mt_rand(1,10),
//                'message_count' => mt_rand(1,10),
//                'call_count' => mt_rand(1,10)
//            ]);
//        }
    }
}
