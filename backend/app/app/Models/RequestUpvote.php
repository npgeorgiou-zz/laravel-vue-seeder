<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestUpvote extends Model {
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'model_type',
        'model_id',
    ];

    public function user() {
        return $this->belongsTo(User::class);
    }

    public function request() {
        return $this->belongsTo(Request::class);
    }
}
