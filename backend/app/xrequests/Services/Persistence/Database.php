<?php

namespace Xrequests\Services\Persistence;

use Illuminate\Support\Facades\DB;

class Database {
    function create_password_reset_token($email): string {
        $token = uniqid();

        DB::table('password_resets')->insert([
            'email' => $email,
            'token' => $token,
            'created_at' => new \DateTime()
        ]);

        return $token;
    }

    function find_password_reset_token($token) {
        return DB::table('password_resets')->where('token', $token)->first();
    }

    function delete_password_reset_token($token) {
        DB::table('password_resets')->where('token', $token)->delete();
    }

    function unique_value_for($modelClass, $column) {
        do {
            $unique = uniqid();
        } while ($modelClass::where($column, $unique)->first());

        return $unique;
    }

    function foo($user, $request) {
        return DB::table('request_upvotes')
            ->where('user_id', $user->id)
            ->where('request_id', $request->id)
            ->first();
    }
}
