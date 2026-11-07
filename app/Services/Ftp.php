<?php

namespace App\Services;

use App\Models\Log;
use App\Models\Nomenclature;
use App\Models\Price;
use App\Models\SbisAccount;
use Illuminate\Support\Facades\Log as LaravelLog;
use Illuminate\Support\Facades\Storage;

class Ftp
{
    public Log $log;

    public function __construct()
    {
        $this->log = new Log();
    }

    //Проверять доступные файлы
    public function checkFile()
    {
        // LaravelLog::channel('dev')->info('Начинаем искать файлы');
        dump('📂 Начинаем искать файлы');
        // ищем файлы в папке "in"
        $getFiles = Storage::disk('ftp')->files('in');

        if (!$getFiles) {
            return false;
        }

        // Fix названий из-за того, что теперь берем файлы из папки in, а не из корня
        foreach ($getFiles as $k => $fileName) {
            $getFiles[$k] = str_replace('in/', '', $fileName);
        }

        $validPattern = '/^[1-8]in\.txt$/';

        $validFiles = array_filter($getFiles, fn($file) => preg_match($validPattern, $file));
        $invalidFiles = array_diff($getFiles, $validFiles);

        if (!empty($invalidFiles)) {
            dump('⛔ Имеются файлы не того формата, пропускаем их, более подробно в логах');
            $this->log->createLog(
                'ftp',
                'get file',
                false,
                "Имеются файлы не того формата " . json_encode($invalidFiles)
            );
        }

        $getFiles = array_values($validFiles);

        foreach ($getFiles as $fileName) {
            try {
                $this->log->createLog(
                    'ftp',
                    'get file',
                    true,
                    "Найден файл {$fileName}"
                );
                dump('✅ Найден файл: ' . $fileName);
                $this->data($fileName);
            } catch (\Throwable $t) {
                $this->log->createLog(
                    'ftp',
                    'get file',
                    false,
                    $t
                );
            }
        }

        return true;
    }

    public function data($fileName)
    {
        LaravelLog::channel('dev')->info('Начало обработки файла ' . $fileName);
        $getData = Storage::disk('ftp')->get('in/' . $fileName);

        if (!$getData) {
            dump('⛔ Файл пустой! Закрываю его');
            $this->log->createLog(
                'ftp',
                'check file',
                false,
                "Файл {$fileName} пустой"
            );

            return false;
        }

        $getData = mb_convert_encoding($getData, 'UTF-8', 'Windows-1251');
        $lines = explode("\n", $getData);

        $parsedData = [];

        foreach ($lines as $line) {
            // Fix бага с пустыми строками в конце файла. Если в строке не найдена ; пропускаем такую строку
            if (strpos($line, ';') === false) {
                continue;
            }
            $elements = explode(';', $line);

            $parsedData[] = [
                'id' => $elements[0] ?? null,
                'name' => $elements[1] ?? null,
                'id_folder' => $elements[2] ?? null,
                'name_folder' => $elements[3] ?? null,
                'cost' => $elements[4] ?? null,
                'is_folder' => isset($elements[5]) ? filter_var(
                    $elements[5],
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ) : null,
            ];
        }

        //Передаем читабельный массив в СБИС
        $this->sendSbis($parsedData, $fileName);

        if (0) {
            // Удаление файла
            $resDelete = Storage::disk('ftp')->delete('in/' . $fileName);
        } else {
            // Переименование файла для тестов и отладки
            $resDelete = Storage::disk('ftp')
                ->move('in/' . $fileName, 'in/_' . now()->format('Y-m-d-H-i-s') . '_' . $fileName);
        }

        LaravelLog::channel('dev')->info([
            'Собрали данные для передачи в СБИС',
            $fileName,
            $parsedData,
            $resDelete,
        ]);

        return true;
    }

    public function sendSbis($data, $fileName)
    {
        dump("🔄 Данные корректные, передаю в СБИС");

        $getPrice = Price::where('name_file', $fileName)->first();

        LaravelLog::channel('dev')->info([
            'sendSbis',
            $fileName,
            $data,
        ]);

        if (empty($getPrice)) {
            dump("⛔ Прайса нет, обратитесь в ТП");
            $this->log->createLog(
                'ftp',
                'sendSbis',
                false,
                "Файл {$fileName} не определен прайс"
            );

            return false;
        }
        dump("✅ Найден прайс: {$getPrice->name}");

        $sbis = new Sbis(SbisAccount::find(SbisAccount::PROD_SBIS));

        dump("✅ Чистка прайса: {$getPrice->name}");
        $sbis->deleteNomenclatureInPrice($getPrice->sbis_id);

        sleep(3);

        $getUuid = $sbis->getUuid($getPrice->sbis_id);
        // Id созданных папок
        $foldersSbis = [];

        //dump("✅ GUID: {$getUuid['Uuid']}");

        dump("🔄 Создание всех папок: {$getPrice->name}");
        // Работа с папками
        foreach ($data as $value) {
            if (empty($value['id']) || !$value['is_folder']) {
                continue;
            }
            $sbisFolderId = null;

            if (!empty($value['id_folder'])) {
                $index = array_search($value['id_folder'], array_column($foldersSbis, 'id'));

                if ($index !== false) {
                    $sbisFolderId = $foldersSbis[$index]['sbisId'];
                }
            }

            $sbisId = $sbis->writePrice(
                $value['name'],
                $getUuid['Uuid'],
                $getPrice->sbis_id,
                $sbisFolderId
            );

            $foldersSbis[] = [
                'id' => $value['id'],
                'sbisId' => $sbisId['result']
            ];
        }

        dump("✅ Разделы созданы");
        //dump($foldersSbis);

        sleep(5);

        dump("🔄 Занесение новых позиций: {$getPrice->name}");
        foreach ($data as $value) {
            if (empty($value['id']) || $value['is_folder']) {
                continue;
            }
            $sbisFolderId = null;

            if (!empty($value['id_folder'])) {
                $index = array_search($value['id_folder'], array_column($foldersSbis, 'id'));

                if ($index !== false) {
                    $sbisFolderId = $foldersSbis[$index]['sbisId'];
                }
            }

            if (empty($sbisFolderId)) {
                dump('⛔ Не найден id родительской папки');
                dump($value);
                continue;
            }

            try {
                /*if ($value['id'] === '00000108211') {
                    dump($value);
                }*/

                $getSbisNomenclature = Nomenclature::where('nomenclature_id', $value['id'])->first();
                if (empty($getSbisNomenclature)) {
                    $sbisCreatNom = $sbis->nomenclatureCreate(
                        $value['name'],
                        'Позиция создана автоматически!',
                        $value['cost']
                    );
                    $getSbisNomenclature = Nomenclature::updateOrCreate(
                        [
                            'nomenclature_id_sbis' => $sbisCreatNom,
                            'nomenclature_id' => $value['id'],
                        ],
                        [
                            'name' => $value['name'],
                            // TODO создать миграцию на смену типа колонки cost на decimal 16,2!
                            'cost' => str_replace(',', '.', $value['cost']),
                        ]
                    );
                }
                dump("✅ В прайс добавлена позиция:  {$value['name']}");
                $result = $sbis->updatePriceListPosition(
                    $getPrice->sbis_id,
                    $getSbisNomenclature->nomenclature_id_sbis,
                    $value['cost'],
                    $sbisFolderId
                );

                LaravelLog::channel('dev')->info([
                    'В прайс добавлена позиция ' . $value['name'],
                    $result,
                    $getPrice->sbis_id,
                    $getSbisNomenclature->nomenclature_id_sbis,
                    $value['cost'],
                    $sbisFolderId
                ]);
            } catch (\Exception $e) {
                // Заносим ошибку в логи
                \Illuminate\Support\Facades\Log::error('Ошибка добавления позиции в прайс');
                \Illuminate\Support\Facades\Log::info([
                    $data,
                    $value,
                    $fileName,
                ]);
                \Illuminate\Support\Facades\Log::info($e);
                dump('⛔ Неизвестная ошибка, обратитесь к администратору: ' . $value['name']);
            }
        }
    }
}
