<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class SubFiles extends Model
{
    protected $table = 'DMS.dbo.subfiles';
    public $timestamps = false;
    protected $fillable = ['sfid', 'fid', 'filename', 'filePath', 'hash', 'contents'];
}
