<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectFile extends Model
{
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'project_id',
        'id_padre',
        'name',
        'ruta',
    ];

    public function projects()
    {
        return $this->belongsTo('App\Project');
    }

    public function folder()
    {
        return $this->belongsTo('App\ProjectFolder');
    }
}
