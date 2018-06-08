<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Log;
use DB;
use Storage;
use App\Files;
use Carbon\Carbon;

class WatchController extends Controller
{
    /*
        Note :
            Action Create : ให้ไปทำการ create file ตาม name เลย
            Action Rename : ให้ทำการเอา md5 ของไฟล์ไป check ใน DB แล้สทำการ Rename ใน DB | แก้แต่ชื่อ
            Action Change : (check ว่าเป็นไฟล์ไม่ใช่ dir ดูจากมี . ไหม) แล้วให้เอา name ไป search ใน DB แล้วแก้ซะ | แก้ข้อมูล
    */

    private $timestamp;

    private function getPathWithoutFilename($strPath){
        $exp = explode('\\', $strPath);
        array_pop($exp);
        return implode('\\', $exp) . '\\';
    }

    private function getFilename($strPath){
        $filename = explode('\\', $strPath);
        return $filename[count($filename) - 1];
    }

    private function getFoldername($strPath){
        $folderName = explode('\\', $strPath);
        return $folderName[count($folderName) - 1] . '\\';
    }

    private function splitPath($strPath){
        return [$this->getFilename($strPath), $this->getPathWithoutFilename($strPath)];
    }

    private function md5File($path){
        //TODO: Check file is exists in disk
        $fileRealPath = Storage::disk('document')->path($path);
        $pathWithoutFilename = $this->getPathWithoutFilename($fileRealPath);     //Get only path without fileName
        return md5(md5_file($fileRealPath) . $pathWithoutFilename);
    }

    private function isFile($path){
        $fileRealPath = Storage::disk('document')->path($path);
        return is_file($fileRealPath);
    }

    private function isDir($path){
        $fileRealPath = Storage::disk('document')->path($path);
        return is_dir($fileRealPath);
    }

    private function getContents($path){
        //return Storage::disk('document')->get($path);
        $t = base64_decode(Storage::disk('document')->get($path));
        //return iconv('UCS-2', 'UTF-8', substr($t, 0, -1));
        //return mb_convert_encoding(Storage::disk('document')->get($path), 'UTF-8', 'UCS-2LE');
        //return mb_convert_encoding(Storage::disk('document')->get($path), 'UCS-2', 'ISO-8859-11');
        return utf8_encode(Storage::disk('document')->get($path));
    }

    private function create_action($filePath, $deletedPath){
        try{

            // File
            if($this->isFile($filePath)){
                list($filename, $path) = $this->splitPath($filePath);
                $md5File = $this->md5File($filePath);
                $hasFile = Files::where('hash', $md5File)->count('filename');
                if(!$hasFile){
                    //TODO: Check file is exists in disk
                    $f = new Files();
                    $f->fid = md5(Carbon::now() . $filePath);
                    $f->filename = $filename;
                    $f->path = $path;
                    $f->hash = $this->md5File($filePath);
                    $f->contents = $this->getContents($filePath);
                    $f->save();
                    Log::info($filePath . ' created');
                }else{
                    Log::warning('File in database is exists');
                }
            }

            // Move Folder
            if($this->isDir($filePath)){
                
                $folderName = $this->getFoldername($filePath);
                $folderDeletedName = $this->getFoldername($deletedPath);

                if($folderDeletedName == $folderName){
                    $files = Files::withTrashed()->where('path', 'like', '%' . $folderName)->get();
                    if(count($files) > 0){
                        $restorePath = "";
                        foreach($files as $file){
                            $checkPath = $file->path;
                            if(!Storage::disk('document')->has($checkPath)){
                                $restorePath = $checkPath;
                                break;
                            }
                        }
                        Files::withTrashed()->where('path', $restorePath)->restore();                
                        Files::where('path', $restorePath)->update(['path' => $path]);

                        Log::info($filePath . ' move finished');
                    }
                }else{
                    Log::info('this action is create folder not move');
                }
            }
            
        }catch(Exception $e){
            Log::error($e);
        }
    }

    private function delete_action($filePath){
        try{
            
            $type = Files::where('path', $filePath)->count() > 0 ? 'folder' : 'file';

            // File
            if($type === 'file'){
                list($filename, $path) = $this->splitPath($filePath);
                Log::info($filename . ' | ' . $path);
                Files::where('filename', $filename)->where('path', $path)->forceDelete();
                Log::info('File ' . $filePath . ' deleted');
            }

            // Folder
            if($type === 'folder'){
                $files = Files::where('path', 'like', $filePath . '%')->delete();
                Log::info('Folder ' . $filePath . ' deleted');
            }
            
        }catch(Exception $e){
            Log::error($e);
        }
    }

    private function change_action($filePath){
        //Check is File
        if($this->isFile($filePath)){
            //TODO: Check file exists, OCR()
            list($filename, $path) = $this->splitPath($filePath);
            Files::where('filename', $filename)->where('path', $path)->update(['contents' => $this->getContents($filePath)]);
            Log::info($filePath . ' changed');            
        }
    }

    private function rename_action($filePath, $oldFilePath){
        // File
        if($this->isFile($filePath)){
            list($name, $path) = $this->splitPath($filePath);        
            list($oldName, $oldPath) = $this->splitPath($oldFilePath);
            Files::where('filename', $oldName)->where('path', $oldPath)->update([
                'filename' => $name,
                'path' => $path
            ]);
            Log::info('file ' . $filePath . ' rename');            
        }

        // Folder
        if($this->isDir($filePath)){
            Files::where('path', 'like', $oldFilePath . '%')->update([
                'path' => DB::raw("REPLACE([path], '" . $oldFilePath . "', '" . $filePath . "')")
            ]);
            Log::info('folder ' . $filePath . ' renamed from '. $oldFilePath);
        }
        /*$newHash = $this->md5File($filePath);
        Files::where('hash', $newHash)->update([
            'filename' => $filename,
            'path' => $path
        ]);*/
    }

    public function actions(Request $request){
        $action = $request->input('action');
        $filePath = $request->input('name');
        $this->timestamp = $request->input('timestamp');

        switch($action){
            case 'Created' :
                $deletedPath = $request->input('deletedName');
                $this->create_action($filePath, $deletedPath);
            break;
            case 'Deleted' :
                $this->delete_action($filePath);
            break;
            case 'Changed' :
                $this->change_action($filePath);
            break;
            case 'Renamed' :
                $oldFilePath = $request->input('oldName');
                $this->rename_action($filePath, $oldFilePath);
            break;
        }
    }
}
