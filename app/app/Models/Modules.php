<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Modules extends Model
{
    protected $fillable = ['name', 'version', 'status', 'last_checked'];
    public function getLastCheckedAttribute($value)
    {
        return Carbon::parse($value);
    }
}
