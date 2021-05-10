<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Response extends Model {
    use HasFactory;

    protected $fillable = [
        'description',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function request() {
        return $this->belongsTo(Request::class);
    }

    public function files() {
        return $this->hasMany(File::class);
    }

    public function upvotes() {
        return $this->hasMany(ResponseUpvote::class);
    }
}
