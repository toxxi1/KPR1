<?php

use App\Models\SbisAccount;
use Illuminate\Support\Facades\Artisan;
use App\Services\Ftp;
use App\Services\Sbis\Nomenclature;
use App\Services\Sbis\Price;
use App\Services\Sbis\CashShift;
use Illuminate\Support\Facades\Log;

//В крон команду на каждые 30 сек
Artisan::command('ftp', function (Ftp $ftpService) {
    if ( ! SbisAccount::hasActiveCustomLicense()) {
        Log::info('Лицензия "custom" истекла');
    }else{
        $ftpService->checkFile();
    }
});

// Экспорт каталога СБИС в БД
Artisan::command('nomenclature', function (Nomenclature $nomenclatureServices) {
    if ( ! SbisAccount::hasActiveCustomLicense()) {
        Log::info('Лицензия "custom" истекла');
    }else{
        $nomenclatureServices->getNomenclature();
    }
});

// Экспорт прайсов из СБИС в БД
Artisan::command('prices', function (Price $priceService){
    if ( ! SbisAccount::hasActiveCustomLicense()) {
        Log::info('Лицензия "custom" истекла');
    }else{
        $priceService->getPrices();
    }
});

//Выгрузка смен
Artisan::command('cash_shift', function (CashShift $cashShiftService){
    if ( ! SbisAccount::hasActiveCustomLicense()) {
        Log::info('Лицензия "custom" истекла');
    }else{
        $cashShiftService->getListShifts();
    }
});