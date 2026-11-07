<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SbisAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        DB::table('sbis_accounts')->truncate();
        DB::table('sbis_accounts')->insert([
            'app_client_id' => '9888010340323218',
            'app_secret'    => 'MNTOVPEFEFMOXUDLEWIIMTDT',
            'secret_key'    => 'ZGWxs8Zgog4MmZjc0Sms3UVdYIg1tGjFhzRNlwsRVn3KJFWIf9gEjuu9Tg1M1sCVIq0HQEF9QD81yWnUzdQJ0gt0zCMdHYSmFOFCgbNyygjRqqEp3FcxvT',
            'sid'           => null,
            'token'         => null,
        ]);
    }
}
