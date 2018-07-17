<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use DB;
use Log;
use App\Files;

class FileController extends Controller
{
    /*
        'data' : [
            'Simple root node',
            {
                'text' : 'Root node 2',
                //'state' : {
                //     'opened' : true,
                //     'selected' : true
                // },
                'children' : [
                    { 'text' : 'Child 1' },
                    'Child 2'
                ]
            }
        ]
    */

    private function isFile($path){
        $fileRealPath = Storage::disk('document')->path($path);
        return is_file($fileRealPath);
    }

    private function isDir($path){
        $fileRealPath = Storage::disk('document')->path($path);
        return is_dir($fileRealPath);
    }

    private function getExtension($strFilename){
        $extension = explode('.', $strFilename);
        return strtolower($extension[count($extension) - 1]);
    }

    private function icon($filename){
        $extension = $this->getExtension($filename);
        switch($extension){
            case 'jpeg':
            case 'jpg' :
                return 'far fa-file-image';
            break;
            
            case 'pdf' :
                return 'far fa-file-pdf';
            break;

            default:
                return 'far fa-file';
            break;
        }
    }

    public function treeRoot(){
        $data = [];
        $rootFile = Files::where('path', '\\')->get(['fid', 'path', 'filename']);
        $rootFolder = DB::select(DB::raw(
            "SELECT DISTINCT SUBSTRING(path, 1, CHARINDEX('\', path) - 1) AS folderName 
            FROM DMS.dbo.files 
            WHERE (LEN(path) - LEN(REPLACE(path, '\', '')) >= 1) AND (SUBSTRING(path, 1, CHARINDEX('\', path) - 1) <> '')"
        ));

        foreach($rootFolder as $name){
            array_push($data, ['id' => '\\' . $name->folderName , 'icon' => 'far fa-folder', 'text' => $name->folderName, 'children' => true]);
        }

        foreach($rootFile as $r){
            array_push($data, ['id' => $r->fid , 'icon' => $this->icon($r->filename), 'text' => $r->filename]);
        }

        return response()->json($data);
    }

    public function treeChild($id){
        $id_enc = $id;
        $id = base64_decode($id);
        $data = [];

        if (Cache::has('treeChildData_' . $id_enc) && 0) {
            $data = Cache::get('treeChildData_' . $id_enc);
            Log::info('use cache : ' . 'treeChildData_' . $id_enc);
            return response()->json($data);          
        } else {
            
            if(strpos($id, '|') !== FALSE){
                list($real_id, $page) = explode('|', $id);
                list($skip, $take) = explode('*', $page);
                $skip--;
                $take = $take - $skip;
                $path = preg_replace('/\\\\/', '', $real_id , 1);
                $childFile = Files::where('path', $path . '\\')->skip($skip)->take($take)->orderBy('filename')->get();
                //$childFolder = new \stdClass();
                foreach($childFile as $cf){     
                    array_push($data, ['id' => $cf->fid, 'icon' => $this->icon($cf->filename), 'text' => $cf->filename]);
                }
                
                return response()->json($data);

            } else {
                $path = preg_replace('/\\\\/', '', $id , 1);
                $childFolder = DB::select(DB::raw(
                    "SELECT DISTINCT SUBSTRING(SUBSTRING(path,CHARINDEX('\',path)+1 ,len(path)), 0, CHARINDEX('\', SUBSTRING(path, CHARINDEX('\',path)+1, len(path)))) AS folderName
                    FROM DMS.dbo.files
                    WHERE path LIKE ? + '\%'"
                ), [$path]);
                $childFileCount = Files::where('path', $path . '\\')->count();
                $filePerPage = 200;

                if($childFileCount > $filePerPage){
                    for($i = 0; $i < $childFileCount / $filePerPage; $i++){
                        //TODO: Create folder
                        $folderName = '|' . strval(($i * $filePerPage) + 1) . '*' . strval(($i * $filePerPage) + $filePerPage);
                        $folderLabel = 
                        ($i == floor($childFileCount / $filePerPage))
                        ?
                            '[file] page ' . strval(($i * $filePerPage) + 1) . ' - ' . strval(($i * $filePerPage) + ($childFileCount % $filePerPage))
                        :
                            '[file] page ' . strval(($i * $filePerPage) + 1) . ' - ' . strval(($i * $filePerPage) + $filePerPage);
                            
                        array_push($data, ['id' => $id . $folderName, 'icon' => 'far fa-folder', 'text' => $folderLabel, 'children' => true]);                
                    }
                    foreach($childFolder as $folder){
                        if(!empty($folder->folderName) && strpos($folder->folderName, $path) === FALSE)
                            array_push($data, ['id' => '\\' . $path . '\\' . $folder->folderName, 'icon' => 'far fa-folder', 'text' => $folder->folderName, 'children' => true]);                
                    }
                }else{
                    $childFile = Files::where('path', $path . '\\')->get();

                    foreach($childFolder as $folder){
                        if(!empty($folder->folderName) && strpos($folder->folderName, $path) === FALSE)
                            array_push($data, ['id' => '\\' . $path . '\\' . $folder->folderName, 'icon' => 'far fa-folder', 'text' => $folder->folderName, 'children' => true]);                
                    }
        
                    foreach($childFile as $cf){     
                        array_push($data, ['id' => $cf->fid, 'icon' => $this->icon($cf->filename), 'text' => $cf->filename]);
                    }
                }

                Cache::forever('treeChildData_' . $id_enc, $data);
                Log::info('make cache : ' . 'treeChildData_' . $id_enc);
                return response()->json($data);
            }
            // Cache::forever('treeChildData_' . $id_enc, $data, 5);
            // Log::info('make cache : ' . 'treeChildData_' . $id_enc);
            // return response()->json($data);
        }
    }

    public function content($id){
        $file = Files::where('fid', $id)->first();
        return response()->json($file->contents);
    }
}
