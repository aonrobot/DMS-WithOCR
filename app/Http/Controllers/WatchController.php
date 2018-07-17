<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Cache;

use Log;
use DB;
use Storage;
use App\Files;
use App\SubFiles;
use Carbon\Carbon;
use thiagoalessio\TesseractOCR\TesseractOCR;

class WatchController extends Controller
{
    /*
        Note :
            Action Create : ให้ไปทำการ create file ตาม name เลย
            Action Rename : ให้ทำการเอา md5 ของไฟล์ไป check ใน DB แล้สทำการ Rename ใน DB | แก้แต่ชื่อ
            Action Change : (check ว่าเป็นไฟล์ไม่ใช่ dir ดูจากมี . ไหม) แล้วให้เอา name ไป search ใน DB แล้วแก้ซะ | แก้ข้อมูล
    */

    private $timestamp;
    private $exclude_extension = ['exe'];
    private $ocr_extension = ['png', 'jpeg', 'jpg'];

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

    private function getRootFolderName($strPath){
        $folderName = explode('\\', $strPath);
        array_pop($folderName);
        return '\\' . implode('\\', $folderName);
    }

    private function getExtension($strFilename){
        $extension = explode('.', $strFilename);
        return strtolower($extension[count($extension) - 1]);
    }

    private function splitPath($strPath){
        return [$this->getFilename($strPath), $this->getPathWithoutFilename($strPath)];
    }

    public function md5File($path){
        //TODO: Check file is exists in disk
        $fileRealPath = Storage::disk('document')->path($path);
        $pathWithoutFilename = $this->getPathWithoutFilename($fileRealPath);     //Get only path without fileName
        return md5(md5_file('wfio://' . $fileRealPath) . $pathWithoutFilename);
    }

    private function isFile($path){
        $fileRealPath = Storage::disk('document')->path($path);
        return is_file('wfio://' . $fileRealPath);
    }

    private function isDir($path){
        $fileRealPath = Storage::disk('document')->path($path);
        return is_dir('wfio://' . $fileRealPath);
    }

    private function checkExtension($strFilename){
        //it won't return .(dot) in extension (ex. .pdf -> pdf)
        return in_array($this->getExtension($strFilename), $this->exclude_extension) ? false : true;
    }

    private function checkSystemFile($strFilename){
        return substr($strFilename, 0, 1) == '.' ? false : true;
    }

    private function checkFile($str, $path = ""){
        //Check file is not in .subfile
        if(substr($path, 0, 1) == '.') return false;
        //Check file extension and file system
        return $this->checkExtension($str) && $this->checkSystemFile($str) ? true : false;
    }

    private function getRealPath($strPath){
        return Storage::disk('document')->path($strPath);
    }

    private function insertSubFileDB($index, $outputPath, $fid){
        $pngPath = $outputPath . $index . '.png';
        $md5File = $this->md5File($pngPath);
        $countFile = SubFiles::where('filename', $index . '.png')->where('path', $outputPath)->count('filename');

        $sfid = md5(Carbon::now() . $pngPath);
        if(!$countFile) {
            //TODO: Check file is exists in disk
            $sf = new SubFiles();
            $sf->sfid = $sfid;
            $sf->fid = $fid;
            $sf->filename = $index . '.png';
            $sf->path = $outputPath;
            $sf->hash = $md5File;
            $sf->contents = $this->getContents($pngPath, $fid);
            $sf->created_at = Carbon::now();
            $sf->updated_at = Carbon::now();
            $sf->save();
            Log::info('Subfile -> ' . $pngPath . ' created');
            return response()->json(['success' => true]);
        } else {
            Log::warning('File in database is exists');
            return response()->json(['warning' => 'File in database is exists']);
        }
    }

    private function getPDFContent($strPath, $fid) {
        Log::info('start convert pdf to png');

        $outputPath = '.subfile/' . $fid . '/';
        $pdfRealPath = Storage::disk('document')->path($strPath);
        $outputRealPath = Storage::disk('document')->path($outputPath);
        $pngPathExist = is_dir("wfio://" . $outputRealPath);

        //id don't found .subfile folder create first
        if(!$pngPathExist) {
            Storage::disk('document')->makeDirectory($outputPath);
        }

        if(is_file("wfio://" . $pdfRealPath)) {
            $pdf = new \Spatie\PdfToImage\Pdf($pdfRealPath);
            $pageCount = $pdf->getNumberOfPages();
            for($i = 1; $i <= $pageCount; $i++) {
                //pdf to png
                $pdf->setPage($i)->setOutputFormat('png')->saveImage($outputRealPath);
                //create db
                $this->insertSubFileDB($i, $outputPath, $fid);
            }
        } else {
            Log::error('can\'t found pdf file');
        }
    }

    private function getContents($strPath, $fid = "") {
        list($filename, $path) = $this->splitPath($strPath);

        //PDF File
        if($this->getExtension($filename) == 'pdf'){
            $this->getPDFContent($strPath, $fid);
            return "";
        }

        //Image File
        if(in_array($this->getExtension($filename), $this->ocr_extension)){
            $text = (new TesseractOCR(Storage::disk('document')->path($strPath)))
            ->lang('eng', 'tha')
            ->run();
            Log::info('OCR Result -> ' . $text);
            return $text;
        }

        //$t = base64_decode(Storage::disk('document')->get($path));
        //return iconv('UCS-2', 'UTF-8', substr($t, 0, -1));
        //return mb_convert_encoding(Storage::disk('document')->get($path), 'UTF-8', 'UCS-2LE');
        //return mb_convert_encoding(Storage::disk('document')->get($path), 'UCS-2', 'ISO-8859-11');

        return utf8_encode(file_get_contents("wfio://" . $this->getRealPath($strPath))); //Storage::disk('document')->get($strPath)
    }

    private function create_action($filePath, $deletedPath){
        try{
            // File
            if($this->isFile($filePath)) {
                list($filename, $path) = $this->splitPath($filePath);
                //Check extenson file is not in exclude
                if($this->checkFile($filename, $path)) {
                    $md5File = $this->md5File($filePath);
                    $countFile = Files::where('filename', $filename)->where('path', $path)->count('filename');
                    Log::warning('File hash : ' . $md5File);
                    $fid = md5(Carbon::now() . $filePath);
                    if(!$countFile) {
                        //TODO: Check file is exists in diskkk
                        $f = new Files();
                        $f->fid = $fid;
                        $f->filename = $filename;
                        $f->path = $path;
                        $f->hash = $md5File;
                        $f->contents = $this->getContents($filePath, $fid);
                        $f->created_at = Carbon::now();
                        $f->updated_at = Carbon::now();
                        $f->save();
                        Log::info($filePath . ' created');
                        return response()->json(['success' => true]);
                    } else {
                        Log::warning('File in database is exists');
                        return response()->json(['warning' => 'File in database is exists']);
                    }
                } else {
                    Log::warning('This file extension is not support');                    
                }
                
            }

            // Move Folder
            if($this->isDir($filePath)) {
                
                $folderName = $this->getFoldername($filePath);
                $folderDeletedName = $this->getFoldername($deletedPath);

                if(Files::onlyTrashed()->where('path', 'like', $deletedPath . '\\%')->count()){
                    if($folderDeletedName == $folderName){
                        Files::onlyTrashed()->where('path', 'like', $deletedPath . '\\%')->restore();
                        Files::where('path', 'like', $deletedPath . '\\%')->update([
                            'path' => DB::raw("REPLACE([path], '" . $deletedPath . "', '" . $filePath . "')")
                        ]);
                        Log::info($deletedPath . ' move finished');
                    }else{
                        Log::info('this action is create folder not move');
                    }
                }else{
                    Log::info('can\'t move unknow ' . $filePath . ' in database');  
                }

            }
            
        }catch(Exception $e){
            Log::error($e);
        }
    }

    private function delete_action($filePath){
        try{
            list($filename, $path) = $this->splitPath($filePath);
            $type = Files::where('path', 'like', $filePath . '\\%')->count() > 0 ? 'folder' : 'file';

            // File
            if($type === 'file'){
                Files::where('filename', $filename)->where('path', $path)->forceDelete();
                //Delete .subfile
                $fid = Files::where('filename', $filename)->where('path', $path)->get(['fid']);
                if(isset($fid[0]->fid)) Storage::disk('document')->deleteDirectory('.subfile/' . $fid . '/');
                Log::info('File ' . $filePath . ' deleted');
            }

            // Folder
            else if($type === 'folder'){
                $files = Files::where('path', 'like', $filePath . '\\%')->delete();
                //TODO: Delete all file in .subfile
                Log::info('Folder ' . $filePath . ' deleted');
            }

            // Unknow
            else{
                Log::info('can\'t delete unknow ' . $filePath . ' in database');      
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
            if($this->checkFile($filename, $path)){
                $fid = Files::where('filename', $filename)->where('path', $path)->get(['fid']);
                if(isset($fid[0]->fid)) {
                    Files::where('filename', $filename)->where('path', $path)->update(['contents' => $this->getContents($filePath, $fid)]);
                }
                else {
                    Files::where('filename', $filename)->where('path', $path)->update(['contents' => $this->getContents($filePath)]);
                }
                Log::info($filePath . ' changed'); 
            } else {
                Log::warning('This file extension is not support');                    
            }
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
            Files::where('path', 'like', $oldFilePath . '\\%')->update([
                'path' => DB::raw("REPLACE([path], '" . $oldFilePath . "', '" . $filePath . "')")
            ]);
            Log::info('folder ' . $filePath . ' renamed from '. $oldFilePath);
        }
    }

    public function actions(Request $request){
        $action = $request->input('action');
        $filePath = $request->input('name');
        $this->timestamp = $request->input('timestamp');

        Log::info($action . ' : ' . $filePath);

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
            default:
                echo 'action ' . $action;
            break;
        }

        $folderName = $this->getRootFolderName($filePath);
        if(Cache::has('treeChildData_' . base64_encode($folderName))){
            Cache::forget('treeChildData_' . base64_encode($folderName));
            Log::info('delete cache : ' . 'treeChildData_' . base64_encode($folderName));
        }

    }

    public function showFile($id){

        $file_detail = Files::where('fid', $id)->first();
        $path = $file_detail->path . $file_detail->filename;

        //$file = Storage::disk('document')->get($path);
        //Fix UTF-8 filename(Thai file name)
        $file = file_get_contents("wfio://" . $this->getRealPath($path));
        $type = Storage::disk('document')->mimeType($path);

        $response = Response::make($file, 200);
        $response->header("Content-Type", $type, true, 200);
        $response->header("Content-Disposition", "inline");
        $response->header("filename", (string) $file_detail->filename);
        $response->header("Content-Location", (string) $path);

        return $response;
    }
}
