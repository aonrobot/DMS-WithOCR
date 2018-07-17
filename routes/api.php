<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

function getPathWithoutFilename($strPath){
    $exp = explode('\\', $strPath);
    array_pop($exp);
    return implode('\\', $exp) . '\\';
}


Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('api')->post('/watch/action', 'WatchController@actions');

Route::middleware('api')->get('/showFile/{id}', 'WatchController@showFile');

Route::middleware('api')->get('/file/content/{id}', 'FileController@content');
Route::middleware('api')->get('/file/tree/json/root', 'FileController@treeRoot');
Route::middleware('api')->get('/file/tree/json/child/{id}', 'FileController@treeChild');

Route::middleware('api')->get('/subfile/list/{id}', 'SubFileController@index');


Route::middleware('api')->get('/test', function(){

    //echo 'imagick';
    $fileRealPath = Storage::disk('document')->path('thai-char.jpg');
    $imagick = new \Imagick(realpath($fileRealPath));
    $imagick->blurImage(10, 99, 11);
    header("Content-Type: image/jpg");
    echo $imagick->getImageBlob();
    //blurImage($fileRealPath, 10, 10, 1);
    // $fileRealPath = Storage::disk('document')->path('Docker for Windows Installer.exe');
    // $pathWithoutFilename = getPathWithoutFilename($fileRealPath);     //Get only path without fileName
    // return md5(md5_file($fileRealPath) . $pathWithoutFilename);
    //echo md5_file(Storage::disk('public')->path('Thai-Language-Consonants.png'));
    // $data = \App\Files::all();
    // print_r($data);
    // $psScriptPath = "F:\\get-process.ps1";
 
    // // Execute the PowerShell script, passing the parameters:
    // $query = shell_exec("powershell -command $psScriptPath -username 'auttawir' < NUL");
    // echo $query;  
});

Route::middleware('api')->get('/testPDF2PNG', function() {

    $pdfPath = Storage::disk('document')->path('Test_Folder_byKIM/MIT_180633_Engagement_KPMG.pdf');
    $pngPath = Storage::disk('document')->path('.subfile');
    $pngPathExist = is_dir($pngPath);

    //id don't found .subfile folder create first
    if(!$pngPathExist) {
        Storage::disk('document')->makeDirectory('.subfile');
    }

    if(is_file($pdfPath)) {
        $pdf = new Spatie\PdfToImage\Pdf($pdfPath);
        $pageCount = $pdf->getNumberOfPages();
        for($i = 1; $i <= $pageCount; $i++) {
            $pdf->setPage($i)->saveImage($pngPath);
        }
    } else {
        return response()->json(["error" => "dadasdasdsadasda"]);
    }

    echo "i will convert $pdfPath to be $pngPath <br>";
    echo "subfile is $pngPathExist<br>";
});

Route::middleware('api')->get('rmDir', function(){
    $dirSubFilePath = Storage::disk('document')->path('.subfile\\f67361994d4b806548affc9b4bf69196');
    echo $dirSubFilePath;
    $result = Storage::disk('document')->deleteDirectory('.subfile/f67361994d4b806548affc9b4bf69196/');
    print_r($result);
});