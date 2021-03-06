<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class Provider extends Model
{
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'id_detail_requisition',
        'name',
        'unit_price',
    ];
}
