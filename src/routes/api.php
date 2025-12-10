<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LoginController;
use App\Http\Controllers\Api\ViewUserController;
use App\Http\Controllers\Api\UserManagementController;
use App\Http\Controllers\Api\Db\PerumahanDbController;
use App\Http\Controllers\Api\Psu\ControllerPsuUsulanFisik;
use App\Http\Controllers\Api\Sanpam\SAPDIndividualController;
use App\Http\Controllers\Api\Sanpam\SAPDFasilitasUmumController;
use App\Http\Controllers\Api\Sanpam\SAPDLahanMasyarakatController;
use App\Http\Controllers\Api\Sanpam\SAPDFileController;
use App\Http\Controllers\Api\Psu\PSUFileController;
use App\Http\Controllers\Api\Db\PerumahanFileController;
use App\Http\Controllers\Api\Rutilahu\RutilahuController;
use App\Http\Controllers\Api\Rutilahu\RutilahuFileController;
use App\Http\Controllers\Api\Permukiman\PermukimanController;
use App\Http\Controllers\Api\Permukiman\PermukimanFileController;
use App\Http\Controllers\Api\Pengawasan\PengawasanController;
use App\Http\Controllers\Api\Psu\PSUSerahTerimaController;
use App\Http\Controllers\Api\Psu\PSUUsulanFisikBSLController;
use App\Http\Controllers\Api\Psu\PSUUsulanFisikPerumahanController;
use App\Http\Controllers\Api\Psu\PSUUsulanFisikPJLController;
use App\Http\Controllers\Api\Psu\PSUUsulanFisikTPUController;
use App\Http\Controllers\Api\Pembangunan\PembangunanController;
use App\Http\Controllers\Api\getDataPribadi\MySubmissionsController;
use App\Http\Controllers\Api\Perencanaan\PerencanaanController;
use App\Http\Controllers\Api\Db\PokirController;






/**
 * route "/register"
 * @method "POST"
 */


 //auth
Route::post('/register', App\Http\Controllers\Api\RegisterController::class)->name('register');
Route::post('/login', LoginController::class);

//usermanagement

Route::middleware('auth:api')->group(function () {
    Route::post('/logout', [UserManagementController::class, 'logout']);
  Route::post('/users/{id}', [UserManagementController::class, 'update']);                 // {id} = "me" atau UUID
    Route::post('/users/changePassword/{id}', [UserManagementController::class, 'updatePassword']); // {id} = "me" atau UUID
    Route::delete('/users/{id}', [UserManagementController::class, 'destroy']);     
});


//View User

Route::middleware('auth:api')->group(function () {
    Route::get('/users', [ViewUserController::class, 'index']); 
    Route::get('/users/pengawas', [ViewUserController::class, 'indexPengawas']);     // View all user Admin only
    Route::get('/profile', [ViewUserController::class, 'profile']); // View profile of the Authenticated user
    Route::get('/users/{id}', [ViewUserController::class, 'show']); //View one user by admin
    Route::delete('/users/{id}', [UserManagementController::class, 'deleteUser']); //Delete User Admin Only

    //Get Data Pribadi
    Route::get('/myData', [MySubmissionsController::class, 'index']);
});


Route::middleware('auth:api')->group(function () {

    //db_pokir
     Route::get('/pokir', [PokirController::class, 'index']);
    Route::get('/pokir/{uuid}', [PokirController::class, 'show']);
    Route::post('/pokir/create', [PokirController::class, 'store']);
    Route::post('/pokir/update/{uuid}', [PokirController::class, 'update']); // <<< pakai POST
    Route::delete('/pokir/{uuid}', [PokirController::class, 'destroy']);

//DataSanpam Individual
 Route::post('/sanpam/upload', [SAPDIndividualController::class, 'upload']);
    Route::post('/sanpam/individual/create', [SAPDIndividualController::class, 'submit']);
    Route::get('/sanpam/individual', [SAPDIndividualController::class, 'index']);
    Route::post('/sanpam/individual/update/{uuid}', [SAPDIndividualController::class, 'update'])->whereUuid('uuid');
Route::delete('/sanpam/individual/{uuid}', [SAPDIndividualController::class, 'destroy'])->whereUuid('uuid');
    Route::get('/sanpam/individual/{uuid}', [SAPDIndividualController::class, 'show'])
        ->whereUuid('uuid'); // ensure it's a valid UUID;

    //DataSanpam fasum
    Route::post('/sanpam/fasum/create', [SAPDFasilitasUmumController::class, 'submit']);
    Route::get('/sanpam/fasum', [SAPDFasilitasUmumController::class, 'index']);
    Route::get('/sanpam/fasum/{uuid}', [SAPDFasilitasUmumController::class, 'show']);
    Route::post('/sanpam/fasum/update/{uuid}', [SAPDFasilitasUmumController::class, 'update'])->whereUuid('uuid');
    Route::delete('/sanpam/fasum/{uuid}', [SAPDFasilitasUmumController::class, 'destroy'])->whereUuid('uuid');
    
//DataSanpam Lahan Masyarakat
Route::post('/sanpam/sarana-air/create', [SAPDLahanMasyarakatController::class, 'submit']);
Route::post('/sanpam/sarana-air/update/{uuid}', [SAPDLahanMasyarakatController::class, 'update']);
Route::delete('/sanpam/sarana-air/{uuid}', [SAPDLahanMasyarakatController::class, 'destroy']);
Route::get('/sanpam/sarana-air', [SAPDLahanMasyarakatController::class, 'index']);
Route::get('/sanpam/sarana-air/{uuid}', [SAPDLahanMasyarakatController::class, 'show']);
Route::get('/sanpam/file/{uuid}', [SAPDFileController::class, 'show'])
        ->name('sapd.file.show');
Route::get('/sanpam/file/preview/{uuid}', [SAPDFileController::class, 'preview'])
    ->name('sapd.file.preview');
//Data SerahTerimaPSU
   Route::post('/psu/file/upload',       [PSUSerahTerimaController::class, 'upload']);
Route::post('/psu/serahterima/create',   [PSUSerahTerimaController::class, 'store']);
Route::get ('/psu/serahterima',          [PSUSerahTerimaController::class, 'index']);
Route::get ('/psu/serahterima/{uuid}',   [PSUSerahTerimaController::class, 'show']);

// optional (kalau perlu edit/hapus juga)
Route::post   ('/psu/serahterima/update/{uuid}', [PSUSerahTerimaController::class, 'update']);
Route::delete('/psu/serahterima/{uuid}', [PSUSerahTerimaController::class, 'destroy']);

// file show: tetap
Route::get('/psu/file/{uuid}', [PSUFileController::class, 'show'])->name('psu.file.show');
Route::get('/psu/file/preview/{uuid}', [PSUFileController::class, 'preview'])
    ->name('psu.file.preview');

//Perencanaan
Route::get('/perencanaan', [PerencanaanController::class, 'index']);
Route::post('/perencanaan/create', [PerencanaanController::class, 'store']);
Route::post('/perencanaan/update/{id}', [PerencanaanController::class, 'update']);

// Upload & file endpoints (HARUS sebelum {id})
Route::post('/perencanaan/upload', [PerencanaanController::class, 'upload']);
Route::get('/perencanaan/file/{uuid}', [PerencanaanController::class, 'fileShow']);
Route::delete('/perencanaan/file/{uuid}', [PerencanaanController::class, 'fileDestroy']);
Route::post('/perencanaan/file/{uuid}/delete', [PerencanaanController::class, 'fileDestroy']); // alternatif via POST

Route::delete('/perencanaan/{id}', [PerencanaanController::class, 'destroy']);
Route::get('/perencanaan/{id}', [PerencanaanController::class, 'show']);

//pembangunan
Route::post('/pembangunan/create', [PembangunanController::class, 'store']);
Route::post('/pembangunan/clone', [PembangunanController::class, 'storeCloneFromExisting']);
    Route::post('/pembangunan/update/{id}', [PembangunanController::class, 'update'])->whereUuid('id');
    Route::get('/pembangunan', [PembangunanController::class, 'index']);
    Route::get('/pembangunan/dropdownspk', [PembangunanController::class, 'indexDedupSpk']);
    Route::get('/pembangunan/{id}', [PembangunanController::class, 'show'])->whereUuid('id'); // optional

//Pengawasan
// Pengawasan
Route::get('/pengawasan', [PengawasanController::class, 'index']);
Route::get('/pengawasan/mydata', [PengawasanController::class, 'pembangunanIndexSimple']);

// Uploads (temp/final + preview) — LETAKKAN SEBELUM /pengawasan/{id}
Route::post('/pengawasan/upload', [PengawasanController::class, 'upload']);
Route::get('/pengawasan/file/{uuid}', [PengawasanController::class, 'fileShow']);
Route::get('/pengawasan/file/preview/{uuid}', [PengawasanController::class, 'filePreview']);
Route::delete('/pengawasan/file/{uuid}', [PengawasanController::class, 'fileDestroy']);

// CRUD Pengawasan
Route::get('/pengawasan/{id}', [PengawasanController::class, 'show']);
Route::post('/pengawasan/create', [PengawasanController::class, 'store']);
Route::post('/pengawasan/update/{id}', [PengawasanController::class, 'update']);
Route::delete('/pengawasan/{id}', [PengawasanController::class, 'destroy']);


// PSU Usulan Fisik BSL
Route::post('/psu/usulan-fisik-bsl/create', [PSUUsulanFisikBSLController::class, 'store']);
Route::get ('/psu/usulan-fisik-bsl',        [PSUUsulanFisikBSLController::class, 'index']);
Route::get ('/psu/usulan-fisik-bsl/{uuid}', [PSUUsulanFisikBSLController::class, 'show']);
Route::post ('/psu/usulan-fisik-bsl/edit/{uuid}', [PSUUsulanFisikBSLController::class, 'update']);
Route::delete('/psu/usulan-fisik-bsl/{uuid}',[PSUUsulanFisikBSLController::class, 'destroy']);

// PSU Usulan Fisik PJL
Route::get ('/psu/usulan-fisik-pjl',              [PSUUsulanFisikPJLController::class, 'index']);
Route::get ('/psu/usulan-fisik-pjl/{uuid}',       [PSUUsulanFisikPJLController::class, 'show']);
Route::post('/psu/usulan-fisik-pjl/create',       [PSUUsulanFisikPJLController::class, 'store']);
Route::post('/psu/usulan-fisik-pjl/update/{uuid}',[PSUUsulanFisikPJLController::class, 'update']);
Route::delete('/psu/usulan-fisik-pjl/{uuid}',     [PSUUsulanFisikPJLController::class, 'destroy']);

// PSU Usulan Fisik TPU
Route::get ('/psu/usulan-fisik-tpu',            [PSUUsulanFisikTPUController::class, 'index']);
Route::post('/psu/usulan-fisik-tpu/create',     [PSUUsulanFisikTPUController::class, 'store']);
Route::get ('/psu/usulan-fisik-tpu/{uuid}',     [PSUUsulanFisikTPUController::class, 'show']);
Route::post('/psu/usulan-fisik-tpu/update/{uuid}', [PSUUsulanFisikTPUController::class, 'update']); // atau PUT/PATCH
Route::delete('/psu/usulan-fisik-tpu/{uuid}',   [PSUUsulanFisikTPUController::class, 'destroy']);

// PSU Usulan Fisik Perumahan
Route::get   ('/psu/usulan-fisik-perumahan',              [PSUUsulanFisikPerumahanController::class, 'index']);
Route::post  ('/psu/usulan-fisik-perumahan/create',       [PSUUsulanFisikPerumahanController::class, 'store']);
Route::get   ('/psu/usulan-fisik-perumahan/{uuid}',       [PSUUsulanFisikPerumahanController::class, 'show']);
Route::post  ('/psu/usulan-fisik-perumahan/update/{uuid}',[PSUUsulanFisikPerumahanController::class, 'update']); // boleh PUT/PATCH
Route::delete('/psu/usulan-fisik-perumahan/{uuid}',       [PSUUsulanFisikPerumahanController::class, 'destroy']);

    //dbPerumahan
    Route::post('/perumahan-db/create', [PerumahanDbController::class, 'store']);
   // Route::get('/perumahan-db/showData/{id}', [PerumahanDbController::class, 'psuSerahTerimaByPerumahan']);
    Route::get('/perumahan-db/all', [PerumahanDbController::class, 'index']);
    Route::get('/perumahan-db/list', [PerumahanDbController::class, 'listNamaDanId']);
    Route::get('/perumahan-db/{id}', [PerumahanDbController::class, 'show']);
    Route::post('/perumahan-db/upload', [PerumahanDbController::class, 'upload']);
    Route::delete('/perumahan-db/{id}', [PerumahanDbController::class, 'destroy']);
    Route::post('/perumahan-db/update/{id}', [PerumahanDbController::class, 'update']);
    Route::post('/perumahan-db/perumahan',[PerumahanDbController::class, 'store']); 
    Route::get('/perumahan-db/file/{uuid}',       [PerumahanFileController::class, 'showByUuid'])->whereUuid('uuid');

    //Rutilahu
    Route::post('/perumahan/upload', [RutilahuController::class, 'upload']); // temp upload (private)
    Route::post('/perumahan/create',  [RutilahuController::class, 'store']);
    Route::get('/perumahan/{id}',     [RutilahuController::class, 'show']);
    Route::get('/perumahan',          [RutilahuController::class, 'index']);
    Route::post('/perumahan/update/{uuid}', [RutilahuController::class, 'update'])->whereUuid('uuid');
    Route::delete('/perumahan/{uuid}',      [RutilahuController::class, 'destroy'])->whereUuid('uuid');
     Route::get('/perumahan/file/{uuid}',      [RutilahuFileController::class, 'show']);
     Route::get('/perumahan/file/preview/{uuid}', [RutilahuFileController::class, 'preview'])
    ->name('rutilahu.file.preview');
     Route::post('/perumahan/file/delete/{uuid}', [RutilahuFileController::class, 'destroy']);

    //permukiman
Route::post('/permukiman/upload', [PermukimanController::class, 'upload']);
Route::post('/permukiman/create', [PermukimanController::class, 'store']);
Route::post('/permukiman/update/{id}', [PermukimanController::class, 'update'])->whereUuid('id');
Route::get('/permukiman', [PermukimanController::class, 'index']);

// rute file (beri name jika mau dipanggil via route())
Route::get('/permukiman/file/{uuid}', [PermukimanFileController::class, 'show'])
    ->whereUuid('uuid')
    ->name('permukiman.file.show');
    
Route::get('/permukiman/file/preview/{uuid}', [PermukimanFileController::class, 'preview'])
    ->name('permukiman.file.preview');

// --- terakhir: rute dinamis by UUID untuk show data ---
Route::get('/permukiman/{uuid}', [PermukimanController::class, 'show'])
    ->whereUuid('uuid');

    Route::delete('/permukiman/{uuid}', [PermukimanController::class, 'destroy']);

        
});
