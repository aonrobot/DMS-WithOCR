<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Files extends Model
{
    use SoftDeletes;

    protected $table = 'DMS.dbo.files';
    public $timestamps = false;
    protected $fillable = ['fid', 'filename', 'filePath', 'hash', 'contents'];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];
}
