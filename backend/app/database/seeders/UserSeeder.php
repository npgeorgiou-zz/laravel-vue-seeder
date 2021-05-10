<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder {
    public function run() {

        $user = new User();
        $user->username = 'Anonymous User';
        $user->password = 'password';
        $user->is_anonymous = true;
        $user->save();

        $user = new User();
        $user->username = 'Nikos';
        $user->email = 'nikolaos.konstantinos.pap@gmail.com';
        $user->password = 'password';
        $user->is_backoffice = true;
        $user->save();
    }
}
