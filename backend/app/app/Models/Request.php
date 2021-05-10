<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Request extends Model {
    use HasFactory;

    protected $fillable = [
        'description',
        'validation',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function responses() {
        return $this->hasMany(Response::class);
    }

    public function upvotes() {
        return $this->hasMany(RequestUpvote::class);
    }
}
