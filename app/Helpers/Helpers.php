<?php

use App\Models\DbConnection;
use App\Models\SbisAccount;
use App\Models\Schedule;
use App\Models\ScheduleCommand;
use App\Models\User;
use App\Models\UserInvoice;
use App\Services\Sbis;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

// Преозазование Json строки
function meta_str_to_array($data, $unset_fields = null)
{
    $str = '{' . str_replace('=>', ':', $data) . '}';
    $str = str_replace('"[', '[', $str);
    $str = str_replace(']"', ']', $str);
    $str = str_replace('\'', '"', $str);

    $out = (array)json_decode($str, JSON_UNESCAPED_UNICODE);

    if (!empty($unset_fields)) {
        if (is_array($unset_fields) && count($unset_fields)) {
            foreach ($unset_fields as $k_name) {
                unset($out[$k_name]);
            }
        } else {
            unset($out[$unset_fields]);
        }
    }

    foreach ($out as $k => $v) {
        if (empty($v)) {
            unset($out[$k]);
        }
    }

    return $out;
}

// Получить доп поля из РП.Документ
function getMetaFromRpDocument($rpData)
{
    $resultMetaData = [];

    try {
        $rp = parseSbisDataToArray($rpData);
        $reglament = parseSbisDataToArray($rp['Регламент']);
        $metaValues = $rp['ЗначенияДопПолей'];
        $metaFieldsData = $reglament['ДопПоля'];
        // v1 перестала работать
        //$metaFieldsArr = array_shift($metaFieldsData);
        // v2
        $metaFieldsArr = [];
        foreach ($metaFieldsData as $group => $array) {
            $metaFieldsArr = array_merge($metaFieldsArr, $array);
        }

        foreach ($metaValues as $metaK => $metaV) {
            foreach ($metaFieldsArr as $metaField) {
                if (!empty($metaField['name']) && $metaField['name'] === $metaK) {
                    $finishValue = null;
                    switch ($metaField['type']) {
                        case 'string':
                        case 'text':
                        case 'time':
                        case 'date':
                        case 'boolean':
                            $finishValue = (string)$metaV;
                            break; // switch
                        case 'list':
                            $values = [];
                            foreach ($metaField['editorOptions']['items'] as $item) {
                                foreach ($metaV as $valueId) {
                                    if ($item['id'] === $valueId) {
                                        $values[] = $item['caption'];
                                    }
                                }
                            }
                            if (!empty($values)) {
                                $finishValue = implode(',', $values);
                            }
                            break; // switch
                    }
                    if (isset($finishValue) && $finishValue !== null) {
                        $resultMetaData[$metaK] = [
                            'sbis_type' => $metaField['type'],
                            'name' => $metaK,
                            'caption' => $metaField['caption'],
                            'value' => $finishValue,
                        ];
                    }
                    break; // foreach
                }
            }
        }
    } catch (Exception $e) {
        Log::error($e);
    }

    return $resultMetaData;
}

// Проверка данных на Json
function isJson($string)
{
    json_decode($string);
    return json_last_error() === JSON_ERROR_NONE;
}

// Формирование ссылки на документ
function get_sbis_opendoc_link($guid)
{
    return 'https://online.sbis.ru/opendoc.html?guid=' . $guid;
}

// Получение колонки
function get_meta_column($item)
{
    $metas = $item->meta;

    $out = '';
    if ($metas->count()) {
        $temp = [];
        foreach ($metas as $row) {
            $temp[$row['key']] = $row['value'];
        }

        foreach ($temp as $k => $v) {
            if (isJson($v)) {
                $temp = json_decode($v);
                if (is_array($temp)) {
                    $out .= '<p class="mb-2">' . $k . ' - ' . implode(', ', $temp) . '</p>';
                } elseif (is_object($temp)) {
                    $temp_2 = '';
                    foreach ($temp as $obj_k => $obj_v) {
                        $temp_2 .= $obj_k . ' - ' . $obj_v . '<br>';
                    }
                    $out .= '<p class="mb-2">' . $k . ':<br>' . $temp_2 . '</p>';
                } else {
                    $out .= '<p class="mb-2">' . $k . ' - ' . $temp . '</p>';
                }
            } else {
                $out .= '<p class="mb-2">' . $k . ' - ' . $v . '</p>';
            }
        }
    }
    return '<small>' . $out . '</small>';
}

// Получить id всех администраторов // admin@sbisvyatka.com, Орлов и Ленточкин на продакшене // TODO удалить
function getAdminsIds(): array
{
    return [3, 21, 856];
}

// Получить текущий домен
function get_domen()
{
    $out = url('/');
    $out = str_replace(['https://', 'http://'], '', $out);
    $out = str_replace('/', '', $out);
    return $out;
}

// Проверка на lk сервер (integration)
function is_production()
{
    return env('APP_ENV') === 'production';
}

// Проверка на lk сервер (integration). Дубль функции is_production()
// TODO удалить либо эту функцию, либо is_production
function is_integration()
{
    return is_production();
}

// Проверка на fix сервер
function is_fix()
{
    return get_domen() === 'fix.taviat.ru';
}

// Проверка на dev сервер
function is_dev()
{
    return get_domen() === 'dev.taviat.ru';
}

// Проверка на env local
function is_localhost()
{
    return env('APP_ENV') === 'local' && !is_dev() && !is_fix() && !is_integration();
}

// Проверка на VPS
function is_vps()
{
    return is_production() || is_fix() || is_dev() || is_import_server();
}

// Проверка на Import Server
function is_import_server()
{
    return strpos(get_domen(), 'vps') !== false;
}

// Получить локальный ip vps по основному ip
function getVpsLocalIp($ip)
{
    foreach (config('taviat.vps_list') as $ipKey => $vps) {
        if ($ipKey === $ip) {
            return $vps['local_ip'];
        }
    }
    return $ip;
}

// Получить домен vps сервера по внешнему ip
function getVpsDomenByIp($ip)
{
    $vpsList = config('taviat.vps_list');
    return $vpsList[$ip]['domen'] ?? '';
}

// Преобразовываем XML в массив
function xml_to_array($data)
{
    $xml = simplexml_load_string($data);
    $json = json_encode($xml);
    $array = json_decode($json, true);
    return $array;
}

// Получение классов для HTML тега BODY
function getBodyClasses()
{
    $arr[] = Auth()->check() ? 'auth' : 'not-auth';
    return trim(implode(' ', $arr));
}

// Преобразовываем объект СБИС в читаемый формат
function parseSbisDataToArray($data, $custom_s = null)
{
    if (empty($data['_type'])) {
        return $data ?? [];
    }

    $newArr = [];
    switch ($data['_type']) {
        case 'recordset':
            foreach ($data['d'] as $k_row => $row) {
                foreach ($row as $k => $value) {
                    $s = $custom_s ?? $data['s'];
                    $newArr[$k_row][$s[$k]['n']] = $value;
                }
            }
            return $newArr;
        case 'record':
            foreach ($data['d'] as $k => $value) {
                $s = $custom_s ?? $data['s'];
                $newArr[$s[$k]['n']] = $value;
            }
            return $newArr;
        default:
            return $data;
    }
    return $data['d'];
}

function parseSbisDataToArrayRecursive($data)
{
    $parsedData = parseSbisDataToArray($data);

    foreach ($parsedData as $key => $value) {
        if (is_array($value) && isset($value['_type']) && isset($value['s'])) {
            $parsedData[$key] = parseSbisDataToArrayRecursive($value);
        }
    }

    return $parsedData;
}


// Парсим GUID из строки
function parseGUID($string)
{
    preg_match('/guid=([A-Za-z0-9\-]{36})/', $string, $matches);
    if (!empty($matches[1])) {
        $guid = $matches[1];
    } else {
        preg_match('/doc\/([A-Za-z0-9\-]{36})/', $string, $matches2);
        if (!empty($matches2[1])) {
            $guid = $matches2[1];
        }
    }

    return $guid ?? null;
}

// Получение document_id по ссылке на документ
function getSbisIdByDocLink($link, $sbis_token)
{
    $response = Http::withHeaders([
        'X-SBISAccessToken' => $sbis_token,
    ])->get($link);
    $page_text = $response->body();

    preg_match("~,\"d\":\[\"(\d{1,30})\",\"~", $page_text, $matches);
    if (!empty($matches[1]) && is_numeric($matches[1])) {
        $document_id = $matches[1];
    }

    return $document_id ?? null;
}

// Скачивание PDF счета из СБИС
function downloadInvoicePdf($link, $number, $document_id, $uuid)
{
    if (!is_dir(storage_path(env('PDF_PATH', UserInvoice::PDF_PATH)))) {
        mkdir(storage_path(env('PDF_PATH', UserInvoice::PDF_PATH)));
    }
    $fileName = getDownloadPdfFileName($number, $document_id, $uuid);
    $tempName = storage_path(env('PDF_PATH', UserInvoice::PDF_PATH) . $fileName);
    $response = Http::sink($tempName)
        ->withHeaders([
            'Content-Type' => 'application/pdf',
            'X-SBISAccessToken' => User::whereEmail(config('taviat.sbisvyatka_email'))
                    ->first()->sbis->token ?? '',
        ])
        ->get($link);
    if (strpos($response->body(), 'error_code') !== false) {
        return false;
    }
    return $fileName;
}

// Генерация имени файла для PDF счета
function getDownloadPdfFileName($number, $document_id, $uuid)
{
    return $number . '_' . $document_id . '_' . $uuid . '.pdf';
}

// Отправка сообщений разработчику в Телеграм (всех) и на E-mail (с ошибками)
function send2Dev($text, $e = null, $subject = null, $toEmail = false)
{
    if (!empty($e) && is_array($e)) {
        $e = print_r($e, 1);
    }
    $data = [
        'text' => $text,
        'e' => $e
    ];
    // TODO пофиксить отправку в тг
    /*try {
        sendTgBotMessage($text);
    } catch (Exception $e) {
        Log::error($e);
    }*/
    $toEmail = 1;
    if ($toEmail) {
        try {
            Mail::send('email.to_dev', $data, function ($message) use ($subject) {
                $message->to(env('DEV_EMAIL', 'taviat-dev@mail.ru'));
                $message->subject($subject ?? 'Служебное сообщение с ' . get_domen());
            });
            return !Mail::failures();
        } catch (Exception $e) {
            Log::error($e);
        }
    }
    return true;
}

// Отправка сообщения ботом. В основном сервисные сообщения
function sendTgBotMessage($message, $chat_id = 807232063)
{
    $telegram = new \Telegram\Bot\Api(env('TELEGRAM_BOT_KEY'));
    $telegram->sendMessage([
        'chat_id' => $chat_id,
        'text' => $message,
    ]);
}

// Кастомная функция перевода первого символа в верхний регистр
if (!function_exists('mb_ucfirst') && function_exists('mb_substr')) {
    function mb_ucfirst($string)
    {
        $string = mb_strtoupper(mb_substr($string, 0, 1)) . mb_substr($string, 1);
        return $string;
    }
}

// Получение типа input поля пользовательских таблиц
function getInputTypeByUserColType($columnType)
{
    switch ($columnType) {
        case 'INTEGER':
            return 'number';
        case 'DATE':
            return 'date';
    }
    return 'text';
}

// Перевод байт в читаемый формат для пользовательских таблиц
function bytesFormat($bytes)
{
    if ($bytes >= 1073741824) {
        $bytes = number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        $bytes = number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        $bytes = number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        $bytes = $bytes . ' байты';
    } elseif ($bytes == 1) {
        $bytes = $bytes . ' байт';
    } else {
        $bytes = '0 байтов';
    }
    return $bytes;
}

// Прописываем число записей для каждого раздела
function setImportedCount($afterImportClassName, $schedule): bool
{
    // TODO Fix getImportPeriodColName()
    $afterImportClassName = 'App\\Models\\' . $afterImportClassName;
    $import_period_col_name = $afterImportClassName::getImportPeriodColName();

    $arr['imported_count'] = $afterImportClassName::whereSbisAccountId($schedule->getSbisAccountCompanyId())
        ->count() ?? 0;

    if (!in_array($afterImportClassName, ['App\Models\Employee'])) {
        $q1 = $afterImportClassName::whereSbisAccountId($schedule->getSbisAccountCompanyId())
            ->orderBy($import_period_col_name, 'asc');
        $q2 = $afterImportClassName::whereSbisAccountId($schedule->getSbisAccountCompanyId())
            ->orderBy($import_period_col_name, 'desc');
        $arr['period_start'] = $q1->value($import_period_col_name) ?? null;
        $arr['period_end'] = $q2->value($import_period_col_name) ?? null;
    }
    return $schedule->update($arr);
}

// Отправка вебхуков по разделу
function sendWebhooks($afterImportClassName, $schedule, $user)
{
    // TODO Fix getImportPeriodColName()
    $afterImportClassNameFull = 'App\\Models\\' . $afterImportClassName;
    $import_period_col_name = $afterImportClassNameFull::getImportPeriodColName();

    // Отправка webhook со статусом 2
    if (is_production() && !empty($schedule->is_send_webhooks) && $schedule->is_send_webhooks == 1) {
        $q = $afterImportClassNameFull::whereWebhookStatus(2)
            ->with('client')
            ->with('department')
            ->with('meta')
            ->where($import_period_col_name, '>=', \Carbon\Carbon::today())
            ->limit(20)
            ->orderBy($import_period_col_name);
        if (in_array($afterImportClassName, ['Realization', 'Outbill'])) {
            $q->with('tabularPart');
        }
        if ($afterImportClassName !== 'Employee') {
            $q->with('employee');
        }
        if ($afterImportClassName === 'CrmEvent') {
            $q->with(['lead', 'lead.meta', 'crm_theme']);
        }
        $to_send_webhook_arr = $q->get();

        if (!$to_send_webhook_arr->isEmpty()) :
            try {
                $webhook_url = getWebhookUrlByUserId($user->id);
                if (!empty($webhook_url)) {
                    $albatoHook = new App\Services\AlbatoHook($webhook_url);
                    foreach ($to_send_webhook_arr as $model) {
                        // TODO сделать отправку вебхуков через Jobs, а не все разом
                        $albatoHook->sendHook($model, 'Created');
                    }
                }
            } catch (Exception $e) {
                Log::channel('import')->error($e);
            }
        endif;
    }
}

// Получение названия текущего маршрута
function getCurrentRouteName()
{
    return Route::currentRouteName();
}

// Проверка строки на номер мобильного телефона
function isMobilePhone($phoneSrting)
{
    preg_match_all('/((8|\+7|7)[\- ]?){1}(\(?9\d{2}\)?[\- ]?){1}([\d]{7}|[\d\- ]{9})/i', $phoneSrting, $matches);
    return $matches[0][0] ?? false;
}

// Проверка строки на E-mail
function isEmail($email)
{
    return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Поиск E-mail и телефона в контактах лида
function getLeadContacts($leadData)
{
    $phoneFinded = false;
    $emailFinded = false;

    try {
        $CRMPreload = parseSbisDataToArray($leadData['CRMPreload']) ?? null;
        if (!empty($CRMPreload['ContactsList'])) {
            $ContactsList = parseSbisDataToArray($CRMPreload['ContactsList']);
            if (!empty($ContactsList)) {
                $s1 = $ContactsList[0]['Contact']['s'] ?? $ContactsList[0]['AssigneeContacts']['s'];
                $s2 = null;
                foreach ($ContactsList as $contactItem) {
                    if ($contactItem['ContactType'] === 'contact') {
                        $Contact = parseSbisDataToArray($contactItem['Contact'], $s1);
                        if (!empty($Contact['ContactData'])) {
                            if ($s2 === null) {
                                $s2 = !empty($Contact['ContactData']['s']) ? $Contact['ContactData']['s'] : null;
                            }
                            $ContactData = parseSbisDataToArray($Contact['ContactData'], $s2);
                        } elseif (!empty($Contact['@Contact'])) {
                            $ContactData = [$Contact];
                        }
                    } elseif ($contactItem['ContactType'] === 'assignee') {
                        $ContactData = parseSbisDataToArray($contactItem['AssigneeContacts'], $s1);
                    }
                    foreach ($ContactData ?? [] as $dataRow) {
                        if (!$phoneFinded && $dataRow['Type'] === 10) {
                            if (isMobilePhone($dataRow['Value'])) {
                                $phoneFinded = $dataRow['Value'];
                            }
                        }
                        if (!$emailFinded && $dataRow['Type'] === 20) {
                            if (isEmail($dataRow['Value'])) {
                                $emailFinded = $dataRow['Value'];
                            }
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        Log::error($e);
    }

    return [
        'phone' => $phoneFinded,
        'email' => $emailFinded,
    ];
}

// Создание токена для сброса пароля пользователя с записьсью в таблицу password_resets
function createPasswordResetToken($email)
{
    $token = Str::random(60);
    DB::connection('mysql')->table('password_resets')->insert([
        'email' => $email,
        'token' => $token,
        'created_at' => Carbon::now()
    ]);
    return $token;
}

// Получение сссылки для сброса пароля
function getPasswordResetLink($token)
{
    return request()->getSchemeAndHttpHost() . '/setPassword/' . $token;
}

// Получение модели User для служебных запросов к СБИС. (Это пользователь с Email из конфига - admin@sbisvyatka.com)
function getServiceUser()
{
    return User::with('sbis')->whereEmail(config('taviat.sbisvyatka_email'))->firstOrFail();
}

// Парсинг параметра src из iframe для разделы отчета
function parseIframeSrc($iframeHtml)
{
    preg_match('/src="([^"]+)"/', $iframeHtml, $matches);
    return (!empty($matches[1]) && is_string($matches[1])) ? $matches[1] : null;
}

// Преобразовывает объект записи СБИС для использования его в дальнейших запросах
// TODO Функция не закончена, пока не работает
function fixSbisRecordArrayToJson($sbisObject)
{
    // Проблема в массивах, шде числовые ключи должны быть строковыми значениями
    // Гриша делал это в проекте питания Presto
    // Нужно сделать универсальную функцию конвертации
    return $sbisObject;
    //dd($sbisObject);
    /*foreach ($sbisObject['d'] as $d) {
        if (!empty($d['s'])) {
            fixSbisRecordArrayToJson($d);
        }
    }*/
    $sbisObject = arrayToObject($sbisObject);
    foreach ($sbisObject->s as $s) {
        if (!empty($s->t->n) && in_array($s->t->n, ['Перечисляемое', 'Флаги'])) {
            foreach (['s', 'sl'] as $letters) {
                if (!empty($s->t->$letters)) {
                    $temp = $s->t->$letters;
                    $temp = json_encode($temp, JSON_FORCE_OBJECT);
                    $s->t->$letters = json_decode($temp, false);
                }
            }
        }
    }
    return $sbisObject;
}

// Массив в объект
function arrayToObject($arr)
{
    return json_decode(json_encode($arr), false);
}

// Поиск project_id задачи парсингом HTML кода. По-другому никак
function getTaskProjectIdByGuid($guid, $sbis_token)
{
    $response = Http::withHeaders([
        'X-SBISAccessToken' => $sbis_token,
    ])->get(get_sbis_opendoc_link($guid));

    preg_match("~,\[(\d{1,10}),\".{1,100},\d{1,10},\d{1,10},\"Проект\",~", $response->body(), $matches);
    if (!empty($matches[1]) && is_numeric($matches[1])) {
        $projectId = $matches[1] * 1;
    }

    return $projectId ?? null;
}

// Получение ID пользователя подключения mysql_import (для миграций и т.п.)
function getMysqlImportConnectionCurrentUserId()
{
    try {
        $databaseName = config('database.connections.mysql_import.database');
        $connection = DbConnection::whereDbName($databaseName)->first();
    } catch (Exception $e) {
        Log::error($e);
    }
    return $connection->user_id ?? 0;
}

// Создание нового подключения к базе Redmine (для диаграммы Ганта)
function initRedmineConnection()
{
    $connection = new DbConnection();
    $connection->server_name = 'z92252kg.beget.tech';
    $connection->db_login = 'z92252kg_redm2';
    $connection->db_name = 'z92252kg_redm2';
    $connection->db_password = Crypt::encryptString('4Aw%VAad');
    DbConnection::setGlobalConnection('redmine', $connection);
}

// Случайная очередь для laravel worker для быстрого импорта проектов и задач
function getRandomQueueName()
{
    return 'sbis_import';
    /*if (!is_production()) {
        return 'default';
    }
    $queues = [];
    for ($i = 1; $i <= 10; $i++) {
        $queues[] = 'queue_' . $i;
    }
    shuffle($queues);
    return $queues[0];*/
}

// Авторизованный пользователь
function authUser()
{
    return Auth::user();
}

// Id авторизованного пользователя
function authUserId()
{
    return Auth::id();
}

// Id клиента или клиента, к которому принадлежит текущий пользователь (для гостевых доступов и сотрудников)
function getAuthUserParentClienId()
{
    return authUser()->getParentClientUserId() ?? 0;
}

// Хелпер вместо Auth::user()->taviatcan($permission)
function taviatCan($permission)
{
    if (!Auth::check()) {
        return false;
    }
    return authUser()->taviatCan($permission);
}

// Получение Id корневой папки темы отношений
function getRootThemeId($themeId, $themesArr, $level = 0)
{
    $out = null;
    foreach ($themesArr as $theme) {
        if ($themeId === $theme['@Регламент']) {
            if ($level > 0 && empty($theme['РазделИД'])) {
                return $theme['@Регламент'];
            }
            if (!empty($theme['РазделИД'])) {
                $out = getRootThemeId($theme['РазделИД'], $themesArr, ++$level);
            }
            break;
        }
    }
    return $out;
}

// Поиск Id пользователя из исходного кода страницы настроек
function getUserIdByPageCode($sbisToken)
{
    $response = Http::withHeaders([
        'X-SBISAccessToken' => $sbisToken,
    ])->get('https://online.sbis.ru/page/settings-interface');
    preg_match("~UserInfo\":{\"Пользователь\":(\d{1,20}),\"Идентификатор~", $response->body(), $matches);
    if (!empty($matches[1]) && is_numeric($matches[1])) {
        $userId = $matches[1] * 1;
    }
    return $userId ?? null;
}

// Шаблон по умолчанию (fix для перехода с темы 2022 на 2023)
function getDefaultThemeName()
{
    return '2023';
}

// Проверить активен ли шаблон
function isTheme($themeName)
{
    if (!empty(env('THEME_NAME'))) {
        return env('THEME_NAME') == $themeName;
    } else {
        return getDefaultThemeName() == $themeName;
    }
}

// Проверка аккаунта Сбис
function checkSbisAccount($app_client_id, $app_secret, $secret_key)
{
    try {
        $sbisAccount = new SbisAccount();
        $sbisAccount->app_client_id = $app_client_id;
        $sbisAccount->app_secret = $app_secret;
        $sbisAccount->secret_key = $secret_key;
        $sbis = new Sbis($sbisAccount);
    } catch (\Exception $e) {
        return false;
    }
    return true;
}

function getSelectOptionsFromArr($arr)
{
    $new = [];
    foreach ($arr as $row) {
        $new[$row] = $row;
    }
    return $new;
}

function getHelperBox($text, $placement = 'top')
{
    return '<span class="helper"
        data-bs-toggle="popover"
        data-bs-trigger="hover"
        data-bs-placement="' . $placement . '"
        data-bs-content="' . $text . '"></span>';
}

// Получение параметров Person и PrivatePerson
function getPersonAndPrivatePersonIds($sbis_token)
{
    $response = Http::withHeaders([
        'X-SBISAccessToken' => $sbis_token,
    ])->get('https://online.sbis.ru/page/my-general-info');
    $page_text = $response->body();
    $ids = [
        'Person' => null,
        'PrivatePerson' => null,
    ];
    preg_match("~ИдентификаторСервисаПрофилей\":\"(.{36})\",\"~", $page_text, $matchesPerson);
    if (!empty($matchesPerson[1]) && is_string($matchesPerson[1])) {
        $ids['Person'] = $matchesPerson[1];
    }
    preg_match("~\"ЧастноеЛицо\":(\d{1,}),\"~", $page_text, $matchesPrivatePerson);
    if (!empty($matchesPrivatePerson[1]) && is_numeric($matchesPrivatePerson[1])) {
        $ids['PrivatePerson'] = $matchesPrivatePerson[1];
    }
    return $ids;
}

// Получение Id лида из кода страницы по ссылке на лид
function getLeadIdByLeadLink($sbisToken, $link)
{
    $response = Http::withHeaders([
        'X-SBISAccessToken' => $sbisToken,
    ])->get($link);
    preg_match(
        "~d\":\[(\d{1,30}),\"\\\\~",
        $response->body(),
        $matches
    );
    if (!empty($matches[1]) && is_numeric($matches[1])) {
        $leadId = $matches[1] * 1;
    }
    return $leadId ?? null;
}

// Получить адрес вебхука по user_id
function getWebhookUrlByUserId($userId)
{
    return App\Models\AlbatoToken::whereUserId($userId)->first()->webhook_url ?? null;
}

//
function getIdByMyWarehouseMetaData($metaLink)
{
    preg_match(
        "~\/([A-Za-z0-9\-]{36})$~",
        $metaLink,
        $matches
    );
    if (!empty($matches[1])) {
        $metaId = $matches[1];
    }
    return $metaId ?? null;
}

function getUomByUuid($uomData, $uuid)
{
    foreach ($uomData as $uom) {
        if ($uom['id'] === $uuid) {
            return $uom;
        }
    }
    return null;
}

// Удаление папок и файлов
function rrmdir($dir)
{
    foreach (glob($dir . '/*') as $file) {
        if (is_dir($file)) {
            rrmdir($file);
        } else {
            unlink($file);
        }
    }
    rmdir($dir);
}

function parsePhonesFromString($string)
{
    $phones = [];
    foreach (explode(',', $string) ?? [] as $phone) {
        $trimmedPhone = trim($phone);
        if (!empty($trimmedPhone)) {
            $phones[] = $trimmedPhone;
        }
    }
    return $phones;
}

// Фильтрация мобильных
function phoneFilter($phone, string $addToStart = ''): string
{
    $phone = (int)preg_replace('/\D/', '', $phone); // убираем все, кроме цифр
    $phone = preg_replace('/^79/', '9', $phone, 1); // убираем 7 из начала строки
    $phone = preg_replace('/^89/', '9', $phone, 1); // убираем 8 из начала строки
    $phone = $addToStart . $phone; // добавляем в начало символ, если нужно. Например +7, 7 или 8
    return $phone;
}

// Получить id чата из ссылки
function getSbisChatIdByLink($link)
{
    preg_match('/([A-Za-z0-9\-]{36})/', $link, $matches);
    if (!empty($matches[1])) {
        $chatId = $matches[1];
    }

    return $chatId ?? null;
}

// Универсальное получение контактов(передавать массив с контактами)
function getContact($ContactsList)
{
    $contactData = [];
    $s1 = $ContactsList[0]['Contact']['s'] ?? $ContactsList[0]['AssigneeContacts']['s'];
    $s2 = null;
    foreach ($ContactsList as $contactItem) {
        if ($contactItem['ContactType'] === 'assignee') {
            foreach (parseSbisDataToArray($contactItem['AssigneeContacts'], $s1) as $assigneeContact) {
                array_push($contactData, $assigneeContact);
            }
        }
        if ($contactItem['ContactType'] === 'contact') {
            $Contact = parseSbisDataToArray($contactItem['Contact'], $s1);
            if (!empty($Contact['ContactData'])) {
                if ($s2 === null) {
                    $s2 = !empty($Contact['ContactData']['s']) ? $Contact['ContactData']['s'] : null;
                }
                array_push($contactData, parseSbisDataToArray($Contact['ContactData'], $s2));
            } elseif (!empty($Contact['@Contact'])) {
                array_push($contactData, $Contact);
            }
        }
    }
    return $contactData ?? null;
}

// Столбцы таблицы
function getTableColumns($dbname, $tableName, $connectionName = 'mysql')
{
    return DB::connection($connectionName)
        ->table('information_schema.COLUMNS')
        ->where('TABLE_SCHEMA', $dbname)
        ->where('TABLE_NAME', $tableName)
        ->get();
}

// График работы точки продаж в виде строки
function getSalePointSchedule($salePointData)
{
    $out = 'Не указан';

    $OperatingModeData = parseSbisDataToArray($salePointData['OperatingModeData']) ?? [];
    //dump($OperatingModeData);
    $WorkTimeId = parseSbisDataToArray($OperatingModeData['WorkTimeId']) ?? [];
    //dump($WorkTimeId);
    $Days = parseSbisDataToArray($WorkTimeId['Days']) ?? [];
    //dump($Days);
    $Templates = parseSbisDataToArray($WorkTimeId['Templates']) ?? [];
    //dump($Templates);

    $customTemplateS = [];
    foreach ($Days as $day) {
        if (!empty($day['CustomTemplate']['s'])) {
            $customTemplateS = $day['CustomTemplate']['s'];
        }
    }

    //dump('$customTemplateS');
    //dump($customTemplateS);

    $days = [];

    // Круглосуточно
    $dayAndNight = true;

    // Ежедневно
    $everyDayIsWorks = true;

    foreach ($Days as $day) {
        $templateData = [];
        foreach ($Templates as $template) {
            if ($template['TemplateId'] === $day['TemplateId']) {
                $templateData = $template;
                break;
            }
        }
        $slug = '';
        switch ($day['WeekDay']) {
            case 1:
                $slug = 'Пн';
                break;
            case 2:
                $slug = 'Вт';
                break;
            case 3:
                $slug = 'Ср';
                break;
            case 4:
                $slug = 'Чт';
                break;
            case 5:
                $slug = 'Пт';
                break;
            case 6:
                $slug = 'Сб';
                break;
            case 7:
                $slug = 'Вс';
                break;
        }

        // Нестандартный шаблон
        $customTemplate = [];
        if (!empty($day['CustomTemplate'])) {
            $customTemplate = parseSbisDataToArray($day['CustomTemplate'], $customTemplateS);
        }

        $days[$day['WeekDay']] = [
            'slug' => $slug,
            'templateData' => $templateData,
            'customTemplate' => $customTemplate,
            //'template' => $customTemplate ?? $templateData ?? [],
        ];
        // Проверка на круглосуточно и ежедневно
        if ($templateData['Name'] === 'Выходной') {
            $dayAndNight = false;
            $everyDayIsWorks = false;
        }
        if ($templateData['WorkStart'] !== 0 || $templateData['WorkEnd'] !== 1440) {
            $dayAndNight = false;
        }
    }
    //dump($days);

    // Круглосуточно
    if ($dayAndNight) {
        return 'Круглосуточно';
    }

    // Ежедневно
    if ($everyDayIsWorks) {
        $start = now()->startOfDay()->addMinutes($days[1]['templateData']['WorkStart'])->format('H:i');
        if ($days[1]['templateData']['WorkEnd'] === 1440) {
            $end = '24:00';
        } else {
            $end = now()->startOfDay()->addMinutes($days[1]['templateData']['WorkEnd'])->format('H:i');
        }
        return "Ежедневно с {$start} до $end";
    }

    $day1TemplateId = $days[1]['templateData']['TemplateId'];
    $day7TemplateId = $days[7]['templateData']['TemplateId'];
    $day1WorkStart = $days[1]['templateData']['WorkStart'];
    $day1WorkEnd = $days[1]['templateData']['WorkEnd'];
    if (
        // Пн-Пт рабочие, Сб-Вс выходные
        $days[1]['templateData']['Name'] === 'Рабочий'
        // Совпадает TemplateId рабочих дней
        && $day1TemplateId === $days[2]['templateData']['TemplateId']
        && $day1TemplateId === $days[3]['templateData']['TemplateId']
        && $day1TemplateId === $days[4]['templateData']['TemplateId']
        && $day1TemplateId === $days[5]['templateData']['TemplateId']
        // Совпадает WorkStart
        && $day1WorkStart === $days[2]['templateData']['WorkStart']
        && $day1WorkStart === $days[3]['templateData']['WorkStart']
        && $day1WorkStart === $days[4]['templateData']['WorkStart']
        && $day1WorkStart === $days[5]['templateData']['WorkStart']
        // Совпадает WorkEnd
        && $day1WorkEnd === $days[2]['templateData']['WorkEnd']
        && $day1WorkEnd === $days[3]['templateData']['WorkEnd']
        && $day1WorkEnd === $days[4]['templateData']['WorkEnd']
        && $day1WorkEnd === $days[5]['templateData']['WorkEnd']
        // Выходные
        && $days[7]['templateData']['Name'] === 'Выходной'
        // Совпадает TemplateId выходных дней
        && $day7TemplateId === $days[6]['templateData']['TemplateId']
        && $day7TemplateId === $days[7]['templateData']['TemplateId']
        // Если нет customTemplate
        && empty($days[5]['customTemplate'])
    ) {
        $start = now()->startOfDay()->addMinutes($days[1]['templateData']['WorkStart'])->format('H:i');
        $end = now()->startOfDay()->addMinutes($days[1]['templateData']['WorkEnd'])->format('H:i');
        $out = "Пн-Пт с {$start} до $end"; // , Сб-Вс выходной
    } elseif (
        // Пн-Сб рабочие, Вс выходной
        $days[1]['templateData']['Name'] === 'Рабочий'
        // Совпадает TemplateId рабочих дней
        && $day1TemplateId === $days[2]['templateData']['TemplateId']
        && $day1TemplateId === $days[3]['templateData']['TemplateId']
        && $day1TemplateId === $days[4]['templateData']['TemplateId']
        && $day1TemplateId === $days[5]['templateData']['TemplateId']
        && $day1TemplateId === $days[6]['templateData']['TemplateId']
        // Совпадает WorkStart
        && $day1WorkStart === $days[2]['templateData']['WorkStart']
        && $day1WorkStart === $days[3]['templateData']['WorkStart']
        && $day1WorkStart === $days[4]['templateData']['WorkStart']
        && $day1WorkStart === $days[5]['templateData']['WorkStart']
        && $day1WorkStart === $days[6]['templateData']['WorkStart']
        // Совпадает WorkEnd
        && $day1WorkEnd === $days[2]['templateData']['WorkEnd']
        && $day1WorkEnd === $days[3]['templateData']['WorkEnd']
        && $day1WorkEnd === $days[4]['templateData']['WorkEnd']
        && $day1WorkEnd === $days[5]['templateData']['WorkEnd']
        && $day1WorkEnd === $days[6]['templateData']['WorkEnd']
        // Выходные
        && $days[7]['templateData']['Name'] === 'Выходной'
        // Совпадает TemplateId выходных дней
        && $day7TemplateId === $days[7]['templateData']['TemplateId']
        // Если нет customTemplate
        && empty($days[5]['customTemplate'])
    ) {
        $start = now()->startOfDay()->addMinutes($days[1]['templateData']['WorkStart'])->format('H:i');
        $end = now()->startOfDay()->addMinutes($days[1]['templateData']['WorkEnd'])->format('H:i');
        $out = "Пн-Сб с {$start} до $end"; // , Вс выходной
    } else {
        // Если ни один формат не подошел
        /*$temp = [];
        foreach ($days as $day) {
            $start = now()->startOfDay()->addMinutes($day['templateData']['WorkStart'])->format('H:i');
            $end = now()->startOfDay()->addMinutes($day['templateData']['WorkEnd'])->format('H:i');
            $text = "{$start}-{$end}";
            if ($day['templateData']['IsWorkDay'] === false) {
                $text = "выходной";
            }
            $temp[] = "{$day['slug']} {$text}";
        }
        $out = implode(', ', $temp);*/
    }

    return $out;
}

/**
 * Функция проверяет правильность инн
 *
 * @param string $inn
 * @return bool
 */
function isValidInn($inn): bool
{
    if (preg_match('/\D/', $inn)) {
        return false;
    }

    $inn = (string)$inn;
    $len = strlen($inn);

    if ($len === 10) {
        return $inn[9] === (string)(((
                        2 * $inn[0] + 4 * $inn[1] + 10 * $inn[2] +
                        3 * $inn[3] + 5 * $inn[4] + 9 * $inn[5] +
                        4 * $inn[6] + 6 * $inn[7] + 8 * $inn[8]
                    ) % 11) % 10);
    } elseif ($len === 12) {
        $num10 = (string)(((
                    7 * $inn[0] + 2 * $inn[1] + 4 * $inn[2] +
                    10 * $inn[3] + 3 * $inn[4] + 5 * $inn[5] +
                    9 * $inn[6] + 4 * $inn[7] + 6 * $inn[8] +
                    8 * $inn[9]
                ) % 11) % 10);

        $num11 = (string)(((
                    3 * $inn[0] + 7 * $inn[1] + 2 * $inn[2] +
                    4 * $inn[3] + 10 * $inn[4] + 3 * $inn[5] +
                    5 * $inn[6] + 9 * $inn[7] + 4 * $inn[8] +
                    6 * $inn[9] + 8 * $inn[10]
                ) % 11) % 10);

        return $inn[11] === $num11 && $inn[10] === $num10;
    }

    return false;
}


/**
 * Автоматическое создание и включение загрузчиков для всех СБИС аккаунтов пользователя
 * @param User $user
 * @param string $commandName
 * @param string $startDate
 * @param bool $sendWebhooks
 * @return bool
 */
function createNewSchedules(User $user, string $commandName, string $startDate, bool $sendWebhooks = false): bool
{
    $accounts = $user->sbisAccounts ?? [];
    $command = ScheduleCommand::whereCommand($commandName)->first();
    if (empty($command->id)) {
        return false;
    }
    foreach ($accounts as $account) {
        Schedule::updateOrCreate([
            'user_id' => $user->id,
            'schedule_command_id' => $command->id,
            'sbis_account_id' => $account->id,
        ], [
            'start_date' => $startDate,
            'is_send_webhooks' => $sendWebhooks * 1,
            'online_import_start_date' => now()->startOfDay(),
        ]);
    }
    return true;
}