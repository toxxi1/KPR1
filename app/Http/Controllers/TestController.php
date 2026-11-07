<?php

namespace App\Http\Controllers;

use App\Models\SbisAccount;
use App\Services\Sbis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TestController extends Controller
{
    public function test()
    {
        //$sbis = new Sbis(SbisAccount::find(SbisAccount::PROD_SBIS));

        //$shifts = $sbis->getShifts(null, 100);
        //dd($shifts);

        // PayTypes:
        // Наличка - 1
        // Эквайринг - 2

        // PayMethod
        // 4 - безнал
        //

        // Bank Types:
        // 0 - Банк катой
        // 1 -
        // 2 -
        // 4 - QR-терминал

        //dd($this->ftp());
        //$sbis = new Sbis(SbisAccount::find(SbisAccount::TEST_SBIS));
        //dd($sbis);

        die('test');
    }

    public function ftp()
    {
        // Проверить подключение и список файлов
        //$files = Storage::disk('ftp')->files();
        //dd($files);

        // Загрузить файл на FTP
        //Storage::disk('ftp')->put('example.txt', 'Содержимое файла');

        // Скачать файл с FTP
        //$content = Storage::disk('ftp')->get('example.txt');

        // Удалить файл
        //Storage::disk('ftp')->delete('example.txt');
    }
}
