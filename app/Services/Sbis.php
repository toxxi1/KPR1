<?php

namespace App\Services;

use App\Models\Document;
use App\Models\Module;
use App\Models\SbisAccount;
use Carbon\Carbon;
use Carbon\Traits\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Sbis
{
    private SbisAccount $sbisAccount;
    private string $sid;
    private string $token;
    // TODO переделать $apiUrl на 2 переменные: рабочий домен + строка запроса
    private string $apiUrl = 'https://online.sbis.ru/service/';
    private bool $debug = false;
    private bool $is_fix = false;

    public const SBIS_PERMISSIONS_ERROR = 'Sbis permissions error';
    private const SBIS_NOTOKEN_ERROR = 'NOTOKEN';

    // Список внутренних методов
    private array $internalMethods = [
        'Lead.LeadsList',                  // Список лидов
        'СписокРекламаций.ListGeneral',    // Список обращений
        'Лид.ПрочитатьДляУчастника',       // Чтение лида
        'Наряд.СписокПитон',               // Список нарядов
        'СписокРекламаций.Прочитать',      // Чтение обращения
        'СчетСкИсх.ПрочитатьДляУчастника', // Чтение исходящего счета
        'РеалИсх.ПрочитатьДляУчастника',   // Чтение реализации
        'Employee.ReadBlock',              //
        'СчетИсх.СписокХраним',            // Список Исходящих счетов / Поиск по номеру документа
        'ДокОтгрИсх.СписокХраним',         // Список Реализаций / Поиск по номеру документа
        'Контрагент.ПоИННКППКФ',           // Видимо поиск контрагента по ИНН и КПП
        'CRMEvent.List',                   // Список событий. // Updated. Replace CRMEvent.list
        'CallInfo.List',                   // Список звонков // Updated. Replace TelFacade.CallList
        'Документ.Timeline',               // Лента документа
        'Nomenclature.List',               // Список номенклатуры
        'CRMClients.ListClientsOnline',    // Список клиентов
        'CRMClients.SaveCustomer',         // Создать контрагента физическое лицо
        'РеалИсх.DocNomListForUser',       // Чтение табличной части документа реализация
        'СчетСкИсх.DocNomListForUser',     // Чтение табличной части документа счет
        'Наряд.DocNomListForUser',         // Табличная часть наряда
        'Staff.WasabyList',                // Список сотрудников
        'WorkType.ListPython',             // Cписок "Вид работ"
        'Payment.List',                    // Список чеков
        'SaleOrder.Read',                  // прочитать чек
        'PlaceMap.Read',                   // Получить источник у лида
        'AdStruct.ReadNew',                // Чтение всех источников
        'СлужЗап.Создать',                 // Создать служебную записку
        'СлужЗап.WriteForParticipant',     // Записать данные в служебную записку
        'Наряд.ПрочитатьДляУчастника',     // Прочитать наряд
        'ClientContact.List',              // Прочитать контакты клиента
        'Проект.СписокРу',                 // Список проектов
        'Веха.СписокВех',                  // Список вех
        'ВыпускВерсии.Состояние',          // Получить задачи в вехе
        'Проект.ПрочитатьДляУчастника',    // Прочитать проект
        'СлужЗап.ПрочитатьДляУчастника',   // Прочитать задачу
        'Работа.РабочееВремяПоДокументу',  // Время выполнения задачи
        'Этап.ЭтапыПроектаList',           // Этап проекта
        'CRMEvent.listWithDocumentsEDO',   // Список событий лида
        'Проект.СписокАктивныхЗадач',      // Задачи проекта
        'CRMTheme.ReadTheme',              // Прочитать тему CRM
        'ДокОтгрВх.СписокХраним',          // Список поступлений (входящих реализаций)
        'РеалВх.DocNomListForUser',        // Чтение табличной части документа поступление
        'РеалВх.ПрочитатьДляУчастника',    // Прочитать входящую реализацию
        'Nomenclature.NomenclatureRead',   // Получить характеристики
        'Nomenclature.NomenclatureCreate', // Создать пустую номенклатуру
        'Nomenclature.NomenclatureUpdate', // Обновить номенклатуру
        'NomCodes.isCodeExistsWithName',   // Проверка существования кода упаковок в номенклатуре
        'ТипНоменклатуры.LinearListIterator',     // Список типов номенклатуры
        'Nomenclature.Move',               // Переместить номеклатуру в папку
        'ДвиженияПоНоменклатуре.ПолучитьДанные',  //Движение номенклатуры
        'Пересорт.Создать',                // Создать документ Пересортица
        'Пересорт.Записать',               // Сохранить пересорт
        'Warehouse.GetList',               // Список складов
        'Пересорт.BindAttachments',        // Загрузить xml в пересорт
        'АктИнв.СписокХраним',             // Список инвентаризации
        'АктИнв.DocNomListForUser',        // Прочитать инвентаризацию
        'АктИнв.Создать',                  // Инвентаризация создать
        'АктИнв.Записать',                 // Сохранить инвентаризацию
        'АктИнв.CustomDocNomUpdate',       // Добавить табличную часть в инвентаризацию
        'АктИнв.DocNomCreate',             // Создать табличную часть в инвентаризацию
        'АктИнв.BindAttachments',          // Добавить файл в инвентаризацию
        'SerialNumber.CustomList',         // Список кодов для документов инвентаризации
        'Пересорт.BindAttachments',                     // Загрузить xml в пересорт
        'PrintingTemplateController.PrepareForPrint',   // Установка параметров отчета по потребностям
        'LongRequests.List',                            // Лист длительных операций для формирования отчетов
        'PersonProfile.PersonalInfo',                   // PersonalInfo пользователя
        'PersonProfile.GeneralInfo',                    // GeneralInfo пользователя
        'CRMClients.GetFoldersAndTagsByIDClient',       // Чтение папок и тегов клиента
        'ContractorReader.Get',                         // Чтение информации о клиенте
        'CallCenter.GetCallCenterListByPerson',         // Звонковые группы (кол-центры)
        'Документ.ПолучитьИерархиюСвязанныхДокументов', // Получить Иерархию Связанных Документов
        'Персона.СОтправить',                           // Отправить в группу сообщение
        'Документ.ПолучитьИерархиюСвязанныхДокументов', // Получить Иерархию Связанных Документов
        'PriceListPosition.List',                       // Получить прайс листы
        'PriceList.List',                               // Список прайс листов
        'PriceList.Create',                             // Создать пустой прайс лист
        'PriceList.NewSignWrite',                       // Обновить прайс лист
        'PriceListPosition.UpdatePosition',             // Добавить / обновить позицию в прайс листе
        'ЗаказСкВх.Создать',                            // Создать заказ от покупателя
        'ЗаказСкВх.Записать',                           // Обновить заказ от покупателя
        'Delivery.Create',                              // Создать новый заказ на доставку
        'Delivery.Write',                               // Сохранить заказ с данными
        'РеестрВходящих.СписокСобытий',                  // Получить все документы с ЭДО
        'RetailStats.SalesPointsListPrefetch',
        'Контрагент.History_Of_Instance',               // История изменения данных у клиента
        'Shift.TreeList',                               // Получить кассовые смены
        'Payment.List',                                 // Получить информацию по чеку
        'CRMClients.ReadClient',                        // Получить информацию по клиенту с чека
        'SaleOrder.Read',                               //  Получить информацию по клиенту с чека
        'РеестрВходящих.СписокСобытий',                 // Получить все документы с ЭДО
        'RetailStats.SalesPointsListPrefetch',
        'Bonus.GetClientListWithStats',                 // Получить список клиентов участвующих в бонусной системе
        'CRMClients.ReadMiniCard',                      // Получить клиента
        'CRMClients.CreateClient',                      // Создать клиента
        'CRMClients.SaveClient',                        // Сохранить клиента
        'ClientContactNew.Create',                      // Создать контакт
        'ClientContactNew.Save',                        // Сохранить контакт
        'CRMClients.SaveCustomer',                      // Сохранить покупателя
        'OurOrg.ListOrgs',                              // Список точек продаж
        'OurOrg.listWithBranches',                      // Список точек продаж
        'OurOrg.SabyReadCard',                          // Карточка точки продаж
        'Geo.saveCoordinates',                          // Информация об адресе + координаты
        'Warehouse.ReadCard',                           // Карточка склада
        'ДиалогОстатков.GetPanelData',                  // Прочитать остатки
        'Документ.ЗаписатьПометки',                     // Создать пометку для наряда
        'BoundContact.List',                            // Прочитать контакты из наряда
        'Чат.Список',                                   // получить список чатов для пользователя
        'PersonalRights.RoleList',                      // Список ролей пользователя
        'Документ.Post',                                // Провести Акт выпуска
        'АктВыпуска.СписокХраним',                      // Получить список Акт выпуска
        'PriceList.SmartAddPositions',                  // Добавить номенклатуру в прайс
        'Наряд.Записать',                               // добавить комментарий в ленту наряда
        'PriceList.Read',                               // Прочитать прайс
        'Наряд.AttachList',                             // Прочитать вложения наряда
        'ЗаказВх.EventStoredList',                      // Поиск заказа
        'Персона.СОтправить',                           // Отправка сообщения сотруднику
        'Документ.UpdateCurrentState',
        'PriceListPosition.CreatePosition',             // Получить UUID в прайсе
        'PriceListPosition.Write',                      // Записать данные в прайс
        'PriceListPosition.ExecuteMassOperation',       // Удалить товары с прайса
        'Shift.NomenclatureList',                       // Получить номенклатуру из смены
        'Payment.MiniList',                             // Получить список продаж в смене
    ];

    public function __construct($sbisAccount /*$app_client_id, $app_secret, $secret_key*/)
    {
        if (empty($sbisAccount->app_client_id)) {
            abort('403', 'Не найден аккаунт СБИС');
        }

        $this->sbisAccount = $sbisAccount;
        // if ( strpos(url('/'), 'fix.') !== false )
        //     $this->apiUrl = 'https://fix-online.sbis.ru/service/';

        if ($sbisAccount->is_fix) {
            $this->fixEnable();
        }

        if (empty($sbisAccount->sid) || empty($sbisAccount->token)) {
            $sbisData = $this->getAndUpdateToken(
                $sbisAccount->app_client_id,
                $sbisAccount->app_secret,
                $sbisAccount->secret_key
            );
            if (!empty($sbisData->sid) && !empty($sbisData->token)) {
                $sbisAccount->sid = $sbisData->sid;
                $sbisAccount->token = $sbisData->token;
                if (!empty($sbisAccount->user_id)) {
                    $sbisAccount->save();
                }
            } else {
                abort(403, 'Ошибка авторизации в СБИС. Проверьте ключи доступа');
            }
        }

        $this->sid = $sbisAccount->sid;
        $this->token = $sbisAccount->token;
    }

    public function getAppClientId()
    {
        return $this->sbisAccount->app_client_id;
    }

    public function getSbisAccountId()
    {
        return $this->sbisAccount->id;
    }

    public function getSbisAccountCompanyId()
    {
        return $this->sbisAccount->sbis_account_id;
    }

    public function getAndUpdateToken($app_client_id, $app_secret, $secret_key)
    {
        $url = $this->is_fix ? 'https://fix-online.sbis.ru/oauth/service/' : 'https://online.sbis.ru/oauth/service/';
        $response = Http::post($url, [
            'app_client_id' => $app_client_id,
            'app_secret' => $app_secret,
            'secret_key' => $secret_key
        ]);

        if (!$response->ok()) {
            return [
                'success' => false,
                'errors' => 'Проверьте реквизиты доступа sbis.ru',
                'status' => $response->status()
            ];
        }

        return $response->object();
    }

    public function getToken()
    {
        return $this->token;
    }

    public function sendMessage($leadId, $respId, $comment)
    {
        $leadData = $this->getLead($leadId);

        Log::info(
            'Sbis sendMessage 1 - ' . json_encode(['lead' => $leadId, 'resp' => $respId, 'comment' => $comment],
                JSON_UNESCAPED_UNICODE)
        );
        $response = $this->send('CRMEvent.AddEvent', [
            'd' => [
                'EventType' => 3,
                'LeadID' => $leadId,
                'RespID' => $respId,
                'Comment' => $comment,
                'KindOfContact' => 0,
                'ContactType' => 1,
                //'CurrentPhaseId' => 47058608,
                'CurrentPhaseId' => $leadData['Лид.Фаза'] ?? null,
                //'PhaseDirectionId' => 47058615,
                //'PhaseDirectionId' => 47238559,
                'PhaseDirectionId' => $leadData['Лид.Переход'] ?? null,
                //'DateTime' => $date_time,
            ],
            's' => [
                'EventType' => 'Число целое',
                'LeadID' => 'Число целое',
                'RespID' => 'Число целое',
                'Comment' => 'Строка',
                'KindOfContact' => 'Число целое',
                'ContactType' => 'Число целое',
                'CurrentPhaseId' => 'Число целое',
                'PhaseDirectionId' => 'Число целое',
                //'DateTime' => 'Дата и время',
            ],
            '_type' => 'record',
            'f' => 0
        ], 'EventData');

        if (!$response->ok()) {
            Log::info('Sbis sendMessage - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            //$response->throw();
            return $response->object();
        }

        return $response->object();
        /*
         * Регламент(параметры: CurrentPhaseId|PhaseDirectionId)
         * не актуально Возможные значения:
         * 161304 - Коммерческое предложение |?Знакомство
         * 161430 - Заинтересованы
         * 161431 - Разговор не состоялся
         * 161436 - Еще думают
         * ---
         * если лид завершен:
         * +"message": "Лид завершён: нельзя создать итоговый контакт!"
         * +"details": "Ошибка в обработчике "ПередСоздать": Лид завершён: нельзя создать итоговый контакт!"
         * ---
         */

        $response = $this->send('CRMEvent.AddEvent', [

            'd' => [
                'EventType' => 3,
                'LeadID' => $leadId,
                'RespID' => $respId,
                'Comment' => $comment,
                'KindOfContact' => 0,
                'ContactType' => 1,
                //'CurrentPhaseId' => 47058608,
                //'PhaseDirectionId' => 47058615,
                //'DateTime' => $date_time,
            ],
            's' => [
                'EventType' => 'Число целое',
                'LeadID' => 'Число целое',
                'RespID' => 'Число целое',
                'Comment' => 'Строка',
                'KindOfContact' => 'Число целое',
                'ContactType' => 'Число целое',
                //'CurrentPhaseId' => 'Число целое',
                //'PhaseDirectionId' => 'Число целое',
                //'DateTime' => 'Дата и время',
            ]

        ], 'EventData');
        /*
          if (!$response->ok()){
              $response = $this->send('CRMEvent.AddEvent', [

                'd' => [
                    'EventType' => 3,
                    'LeadID' => $leadId,
                    'RespID' => $respId,
                    'Comment' => $comment,
                    'KindOfContact' => 1,
                    'ContactType' => 1,
                    //'CurrentPhaseId' => 47058608,
                    //'PhaseDirectionId' => 47058615,
                    //'DateTime' => $date_time,
                ],
                's' => [
                    'EventType' => 'Число целое',
                    'LeadID' => 'Число целое',
                    'RespID' => 'Число целое',
                    'Comment' => 'Строка',
                    'KindOfContact' => 'Число целое',
                    'ContactType' => 'Число целое',
                    //'CurrentPhaseId' => 'Число целое',
                    //'PhaseDirectionId' => 'Число целое',
                    //'DateTime' => 'Дата и время',
                ]

            ], 'EventData');
          }*/

        if (!$response->ok()) {
            //$response->throw();
            return $response;
        }

        return $response->object();
    }

    private function send($method, $params, $type = false)
    {
        if ($type) {
            $locParams = [
                $type => $params
            ];
        } else {
            $locParams = $params;
        }

        $postParams = [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $locParams,
            'id' => Str::uuid()
        ];

        if (in_array($method, $this->internalMethods)) {
            $postParams['protocol'] = 6;
        } elseif (strpos($this->apiUrl, 'api.sbis.ru') !== false) {
            $postParams['protocol'] = 3;
        }

        $apiUrl = $this->is_fix ? str_replace(
            '//online.sbis.ru/',
            '//fix-online.sbis.ru/',
            $this->apiUrl
        ) : $this->apiUrl;

        $headers = [
            'X-SBISAccessToken' => $this->token,
            'Content-Type' => 'application/json-rpc; charset=utf-8',
            'Accept' => 'application/json-rpc'
        ];
        if ($method === 'СБИС.СписокАккаунтов') {
            $headers['X-SBISSessionID'] = $this->sid;
        }

        if ($this->debug === true) {
            $result = Http::log()->withHeaders($headers)->post($apiUrl, $postParams);
        } else {
            $result = Http::withHeaders($headers)->post($apiUrl, $postParams);
        }

        if (!empty($result->object()->error) && ($result->object()->error === 'Not authorized.' || $result->object()->error === self::SBIS_NOTOKEN_ERROR)) {
            $sbisAccount = $this->sbisAccount;
            $temp = $this->getAndUpdateToken(
                $sbisAccount->app_client_id,
                $sbisAccount->app_secret,
                $sbisAccount->secret_key
            );
            $sbisAccount->update([
                'sid' => $temp->sid,
                'token' => $temp->token,
            ]);
        }

        $this->checkSbisPermissions($result);


        return $result;
    }

    public function debug($debug_status)
    {
        if (!empty($debug_status) && $debug_status === true) {
            $this->debug = true;
        } else {
            $this->debug = false;
        }
        return $this;
    }

    public function fixEnable()
    {
        $this->is_fix = true;
    }

    public function fixDisable()
    {
        $this->is_fix = false;
    }

    private function setApiUrl($url)
    {
        $this->apiUrl = $url;
    }

    public function createLead($theme, $fio, $phone, $resp_id)
    {
        $theme = $this->getTheme($theme);
        $params = [
            'd' => [
                'Регламент' => $theme['result']['d']['Регламент'],
                'КонтактноеЛицо' => [
                    'd' => [
                        'ФИО' => $fio,
                        'Телефон' => $phone,
                        'ID' => $resp_id
                    ],
                    's' => [
                        'ФИО' => 'Строка',
                        'Телефон' => 'Строка',
                        'ID' => 'Число целое'
                    ],
                ],
                'Примечание' => 'Лид с wazzup'
            ],
            's' => [
                'Регламент' => 'Число целое',
                'КонтактноеЛицо' => 'Запись',
                'Примечание' => 'Строка',
            ],
            '_type' => 'record',
            'f' => 0
        ];

        $response = $this->send('CRMLead.insertRecord', $params, 'Лид');

        return $response->collect();
    }

    public function getTheme($name)
    {
        $params = [
            'НаименованиеТемы' => $name
        ];

        $response = $this->send('CRMLead.getCRMThemeByName', $params);

        return $response->collect()->toArray();
    }

    public function addPartner($inn, $name, $phone, $email, $city, $parentPartnerInn)
    {
        $note = "ФИО: " . $name . "\n Телефон: " . $phone . "\n E-mail: " . $email . "\n ";

        if ($inn) {
            $note .= "ИНН: " . $inn . "\n";
        }
        if ($parentPartnerInn) {
            $note .= "ИНН пригласившего партнера: " . $parentPartnerInn . "\n";
        }

        $note .= "Город: " . $city . "\n";

        $xw = xmlwriter_open_memory();
        xmlwriter_set_indent($xw, 1);
        $res = xmlwriter_set_indent_string($xw, ' ');

        xmlwriter_start_document($xw, '1.0', 'windows-1251');

        // Первый элемент
        xmlwriter_start_element($xw, 'Файл');

        xmlwriter_start_attribute($xw, 'ИдФайл');
        xmlwriter_text($xw, sha1(date(DATE_ATOM, mktime(0, 0, 0, 7, 1, 2000))));
        xmlwriter_end_attribute($xw);

        xmlwriter_start_attribute($xw, 'ВерсияФормата');
        xmlwriter_text($xw, '1');
        xmlwriter_end_attribute($xw);

        xmlwriter_start_attribute($xw, 'Формат');
        xmlwriter_text($xw, 'СлужебнаяЗапискаЗагрузка');
        xmlwriter_end_attribute($xw);

        // Создаём дочерний элемент
        xmlwriter_start_element($xw, 'Документ');

        xmlwriter_start_element($xw, 'Описание');
        xmlwriter_text($xw, $note);
        xmlwriter_end_element($xw);

        xmlwriter_end_document($xw);

        $xml = xmlwriter_output_memory($xw);

        $params = [
            "Документ" => [
                "Регламент" => [
                    "Название" => "Новый партнер"
                ],
                "Тип" => "СлужЗап",
                /*"Ответственный" => [
                    "Фамилия" => "Бекмамбетов",
                    "Имя" => "Артур",
                    "Отчество" => "Майорович",
                ],*/
                'Вложение' => [
                    'Файл' => [
                        'Имя' => 'ON_SLUZHZAP_' . date('Y') . date('m') . date('d') . '_' . sha1(
                                date(DATE_ATOM, mktime(0, 0, 0, 7, 1, 2000))
                            ) . '.xml',
                        'ДвоичныеДанные' => base64_encode($xml)
                    ],
                    'Служебный' => 'Да'
                ],
            ],

        ];

        $response = $this->send('СБИС.ЗаписатьДокумент', $params);
        //return $response = $response->collect()->toArray();

        // Выполнить
        $params = [
            "Документ" => [
                "Идентификатор" => $response["result"]["Идентификатор"],
                "Этап" => [
                    "Название" => "Выполнение",
                    "Действие" => [
                        "Название" => "Первичное общение",
                    ],

                ],
            ]
        ];

        $response = $this->send('СБИС.ВыполнитьДействие', $params);

        return $response->collect()->toArray();
    }

    public function addClient($inn)
    {
        $params = [
            'd' => [
                $inn
                //'772301001',
                //'ООО "Тестовая Компанияфыв"',
            ],
            's' => [
                [
                    't' => 'Строка',
                    'n' => 'ИНН'
                ],
                //['t' => 'Строка',
                //'n' => 'КПП'],
                //['t' => 'Строка',
                //'n' => 'Название'],
            ],

            '_type' => 'record',
            'f' => 0
        ];


        $response = $this->send('Контрагент.ПоИННКППКФ', $params, 'params');

        return $response->collect()->toArray();
    }

    public function findClient()
    {
        $params = [
            "requisites" => "7717119443",
            "ИНН" => "7717119443",
            "page" => "0",
            "size" => "20",
        ];


        $response = $this->send('Contractor.Find', $params, 'params');

        return $response->collect()->toArray();
    }

    public function getClient($inn)
    {
        $params = [
            "Участник" => [
                "СвЮЛ" => [
                    "ИНН" => $inn,
                ]
            ]
        ];

        $response = $this->send('СБИС.ИнформацияОКонтрагенте', $params);

        return $response->collect()->toArray();
    }

    public function getRewards($inn)
    {
        $params = [
            "Фильтр" => [
                //"ДатаС" => $end_date,
                //"ДатаПо" => $start_date,
                "Тип" => 'ДоговорИсх',
                "Контрагент" => [
                    "СвЮЛ" => [
                        "ИНН" => $inn,
                    ],
                ]
            ]
        ];

        $response = $this->send('СБИС.СписокДокументов', $params);

        if ($response->ok()) {
            return $response->collect()->toArray();
        }

        return false;
    }

    public function getRewardsNoCompany($inn)
    {
        $params = [
            "Фильтр" => [
                //"ДатаС" => $end_date,
                //"ДатаПо" => $start_date,
                "Тип" => 'ДоговорИсх',
                "Контрагент" => [
                    "СвФЛ" => [
                        "ИНН" => $inn,
                    ],
                ]
            ]
        ];

        $response = $this->send('СБИС.СписокДокументов', $params);

        if ($response->ok()) {
            return $response->collect()->toArray();
        }

        return false;
    }

    public function createLeadFromPartner(
        $theme,
        $name,
        $phone,
        $email,
        $contragentID,
        $partnerName,
        $currentPartnerContragentID
    )
    {
        $theme = $this->getTheme($theme);
        $params = [
            'd' => [
                'Регламент' => $theme['result']['d']['Регламент'],
                'КонтактноеЛицо' => [
                    'd' => [
                        'ФИО' => $name,
                        'Телефон' => $phone,
                        'email' => $email
                    ],
                    's' => [
                        'ФИО' => 'Строка',
                        'Телефон' => 'Строка',
                        'email' => 'Строка'
                    ],
                ],
                'Контрагент' => [
                    'd' => [
                        '@Лицо' => $contragentID,
                    ],
                    's' => [
                        '@Лицо' => 'Строка',
                    ],
                ],
                //'КаналТрафика' => 'Реферальная программа',
                'Примечание' => 'Заявка от партнера: ' . $partnerName,
                'ЗначенияДопПолей' => [
                    'KanalTrafika' => ['lk_partners'],
                    'partner' => [$currentPartnerContragentID]
                ],
            ],
            's' => [
                'Регламент' => 'Число целое',
                'КонтактноеЛицо' => 'Запись',
                'Контрагент' => 'Запись',
                //'КаналТрафика' => 'Строка',
                'Примечание' => 'Строка',
                'ЗначенияДопПолей' => 'JSON-объект',
            ],
            '_type' => 'record',
            'f' => 0
        ];

        $response = $this->send('CRMLead.insertRecord', $params, 'Лид');

        return $response->collect();
    }

    public function getUserInfo()
    {
        $params = [
            "Параметр" => []
        ];

        $response = $this->send('СБИС.ИнформацияОТекущемПользователе', $params);

        return $response->collect()->toArray();
    }

    public function getThemes()
    {
        $response = $this->send('CRMTheme.GetList', [
            "d" => [
                'HideDeleted' => true,
                'WithGroup' => true,
                'CalcCol' => null
            ],
            "s" => [
                'HideDeleted' => 'Логическое',
                'WithGroup' => 'Логическое',
                'CalcCol' => 'Строка'
            ]
        ], 'Param');

        return $response->object();
    }

    public function getThemesArray()
    {
        $sbisData = $this->getThemes();
        $sbisDataArray = json_decode(json_encode($sbisData), true);
        $sbisDataArray['result']['_type'] = 'recordset';
        return parseSbisDataToArray($sbisDataArray['result']) ?? [];
    }

    public function readStaff()
    {
        $params = [
            "Сотрудник" => [
                "ИдентификаторИС" => "Softjet"
            ]
        ];
        $response = $this->send('СБИС.ПрочитатьСотрудника', $params, 'Параметр');
        return $response->object();
    }

    /*public function readParticipant($doc_id)
    {
        $params = [
            'ИдО' => $doc_id,
            'ИмяМетода' => 'Лид.Список',
            'ДопПоля' => [
                'ИмяОбъекта' => 'Лид'
            ]
        ];
        $response = $this->send('ПрочитатьДляУчастника', $params, 'Параметр');
        //dd($response->collect());
        dd($response->json());
    }*/

    public function getLeadStatus($leadUUID)
    {
        $params = [
            'ИдентификаторДокумента' => $leadUUID,
        ];
        $response = $this->send('CRMLead.getLeadStatus', $params);

        if (!$response->ok()) {
            Log::info('Sbis getLeadStatus - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
            //return $response->object();
        }

        return $response->object();
    }

    /* Получить список сотрудников */
    public function getEmployeesList($limit = 1, $page = 0)
    {
        // Максимальный лимит 500
        $params = [
            'Навигация' => [
                'РазмерСтраницы' => (int)$limit,
                'Страница' => (int)$page,
            ]
        ];
        $response = $this->send('СБИС.СписокСотрудников', $params, 'Параметр');

        if (!$response->ok()) {
            Log::info('Sbis getEmployeesList - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        return $response->object();
    }

    /* Получить информацию о сотруднике по Sbis ID */
    public function getEmployee($id)
    {
        $params = [
            "Сотрудник" => [
                "Идентификатор" => (string)$id
            ]
        ];
        $response = $this->send('СБИС.ПрочитатьСотрудника', $params, 'Параметр');

        if (!$response->ok()) {
            $this->checkSbisPermissions($response);
            Log::info('Sbis getEmployee - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
            //return $response->object();
        }
        return $response->object()->result->Сотрудник;
    }

    /*
    public function employeeReadBlock($employee)
    {
        $params = [
            "Ids" => [
                "d" => [
                    "2021-11-30",
                    "2021-11-01",
                    42754,
                    -2,
                    "0861416d-ec0e-4046-aca5-06eabf44de76",
                    0,
                    false,
                    313,
                    7821227,
                    null,
                    null,
                    42727,
                    null,
                    null
                ],
                "s" => [
                    ["t" => "Дата", "n" => "КонецМесяца"],
                    ["t" => "Дата", "n" => "НачалоМесяца"],
                    ["n" => "PrivatePerson", "t" => "Число целое"],
                    ["n" => "CurrentOrganization", "t" => "Число целое"],
                    ["n" => "Person", "t" => "UUID"],
                    ["n" => "Type", "t" => "Число целое"],
                    ["n" => "IsDraft", "t" => "Логическое"],
                    ["n" => "UserLink", "t" => "Число целое"],
                    ["n" => "User", "t" => "Число целое"],
                    ["n" => "Employee", "t" => "Число целое"],
                    ["n" => "EmployeePosition", "t" => "Число целое"],
                    ["n" => "ManagementDepartment", "t" => "Число целое"],
                    ["n" => "Organization", "t" => "Число целое"],
                    ["n" => "LegalDepartment", "t" => "Число целое"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Item" => "GeneralInfo"
        ];
        $response = $this->send('Employee.ReadBlock', $params);

        dd( $response->object() );

        if (!$response->ok()){
            Log::info('Sbis Employee.ReadBlock - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        return $response->object();
    }
    */

    // Получение списка лидов | ВНУТРЕННИЙ МЕТОД
    public function getLeadsList($start_date = null, $limit = 25, $direction = 'forward', $reglament_arr = null)
    {
        if ($direction !== 'backward') {
            $direction = 'forward';
        }

        if (!empty($start_date)) {
            $start_date = Carbon::parse($start_date);
        } else {
            $start_date = Carbon::now();
        }

        $d = [
            (object)[],
            null,
            null,
            [
                "LeadMainInfo",
                "LeadRegion",
                "LeadResponsible",
                "LeadReglsInfo",
                "LeadInvoice",
                "LeadFixPhoneExistence",
                "LeadFixDelegate",
                "LeadSetNullFields"
            ],
            null,
            /*[
                -5
            ],*/
            [
                'dateStart' => '2020-01-01',
                'dateEnd' => Carbon::now()->endOfMonth()->format('Y-m-d'),
                'period' => ['custom'],
            ],
            Carbon::now()->endOfMonth()->format('Y-m-d'),
            '2020-01-01',
        ];

        $s = [
            ['t' => 'JSON-объект', 'n' => 'FilterAddFields'],
            ['t' => 'Строка', 'n' => 'Phase'],
            ['t' => 'Строка', 'n' => 'Reglament'],
            ['t' => ['n' => 'Массив', 't' => 'Строка'], 'n' => 'Requisites'],
            ['t' => 'Число целое', 'n' => 'Responsible'],
            //['t'=>['n'=>'Массив','t'=>'Число целое'],'n'=>'Result'], // Статус по умолчанию отключаем
            ['t' => 'JSON-объект', 'n' => 'dateRange'],
            ['t' => 'Дата', 'n' => 'ДатаКнц'],
            ['t' => 'Дата', 'n' => 'ДатаНач'],
        ];

        if (is_array($reglament_arr) && count($reglament_arr) === 2) {
            $d[2] = $reglament_arr[0];
            $d[] = $reglament_arr[1];
            $s[] = ['t' => 'Строка', 'n' => 'ReglamentName'];
        }

        $params = [
            'Фильтр' => [
                'd' => $d,
                's' => $s,
                '_type' => 'record',
            ],
            'Сортировка' => null,
            'Навигация' => [
                'd' => [
                    $direction,
                    true,
                    $limit,
                    [
                        'd' => [
                            '{"d":[{"d":["BASE","' . $start_date . '"],"s":[{"n":"Flag","t":"Строка"},{"n":"Date","t":"Дата и время"}],"f":0,"_type":"record"}],"s":[{"n":"CurrPage","t":"Запись"}],"f":1,"_type":"record"}'
                        ],
                        's' => [
                            ['t' => 'Строка', 'n' => '@Документ']
                        ],
                        '_type' => 'record',
                        'f' => 1,
                    ]
                ],
                's' => [
                    ['t' => 'Строка', 'n' => 'Direction'],
                    ['t' => 'Логическое', 'n' => 'HasMore'],
                    ['t' => 'Число целое', 'n' => 'Limit'],
                    ['t' => 'Запись', 'n' => 'Position'],
                ],
                '_type' => 'record',
                'f' => 0,
            ],
            'ДопПоля' => [],
        ];

        $response = $this->send('Lead.LeadsList', $params);

        if (!$response->ok()) {
            $this->checkSbisPermissions($response);
            Log::info('Sbis Lead.LeadsList - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();

        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
            //return $json['result']['d'];
        }

        return false;
    }

    // Прочитать лид // ВНУТРЕННИЙ МЕТОД
    public function getLead($id)
    {
        $params = [
            "ИдО" => (string)$id,
            "ИмяМетода" => "Лид.ListCard",
            "ДопПоля" => [
                "ИмяОбъекта" => "Лид"
            ]
        ];

        $response = $this->send('Лид.ПрочитатьДляУчастника', $params);

        if (!$response->ok()) {
            Log::info('Sbis Лид.ПрочитатьДляУчастника - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();

        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }

        return false;
    }

    // Получение списка событий | ВНУТРЕННИЙ МЕТОД
    public function getCrmEventList($start_date = null, $limit = 25)
    {
        if (!empty($start_date)) {
            $start_date = Carbon::parse($start_date);
        } else {
            $start_date = Carbon::now()->addHours(10);
        }

        $d = [
            null,
            Carbon::now()->endOfMonth()->format('Y-m-d'), //null,//"2021-11-30",
            '2020-01-01', //null,//"2021-11-01",
            //"Выполненные контакты",
            null,
            "За период",
            null,
        ];

        $s = [
            ["t" => "Строка", "n" => "Reglament"],
            ["t" => "Дата", "n" => "ДатаКнц"],
            ["t" => "Дата", "n" => "ДатаНач"],
            //["t" => "Строка","n" => "Отбираются"],
            ["t" => "Число целое", "n" => "Ответственный"],
            ["t" => "Строка", "n" => "ПериодДат"],
            ["t" => "Строка", "n" => "Регламент"],
        ];

        if (1) {
            $d[] = [
                "d" => [
                    [
                        "d" => [
                            $start_date
                        ],
                        "s" => [
                            ["n" => "DateTime", "t" => "Дата и время"]
                        ],
                        "_type" => "record"
                    ]
                ],
                "_type" => "record",
                "s" => [
                    ["n" => "EventPage", "t" => "Запись"]
                ]
            ];
            $s[] = ["t" => "Запись", "n" => "EventPage"];
        }

        $params = [
            'Фильтр' => [
                'd' => $d,
                's' => $s,
                "_type" => "record",
            ],
            'Сортировка' => null,
            'Навигация' => null /*[
                'd' => [
                    'forward',
                    true,
                    ++$limit,
                    [
                        'd'=> [
                            '{"d":[{"d":["BASE","'.$start_date.'"],"s":[{"n":"Flag","t":"Строка"},{"n":"Date","t":"Дата и время"}],"f":0,"_type":"record"}],"s":[{"n":"CurrPage","t":"Запись"}],"f":1,"_type":"record"}'
                        ],
                        's' => [
                            ['t'=>'Строка', 'n'=>'@Документ']
                        ],
                        '_type' => 'record',
                        'f' => 1,
                    ]
                ],
                's' => [
                    ['t'=>'Строка', 'n'=>'Direction'],
                    ['t'=>'Логическое', 'n'=>'HasMore'],
                    ['t'=>'Число целое', 'n'=>'Limit'],
                    ['t'=>'Запись', 'n'=>'Position'],
                ],
                '_type' => 'record',
                'f' => 0,
            ]*/,
            'ДопПоля' => [],
        ];

        $response = $this->send('CRMEvent.list', $params);

        if (!$response->ok()) {
            Log::info('Sbis CRMEvent.list - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();

        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }

        return false;
    }

    // Получение списка событий v2 | ВНУТРЕННИЙ МЕТОД
    public function getCrmEventList_2(
        $limit = 25,
        $date_start = null,
        $date_end = null,
        $planned = false,
        $direction = 'forward'
    )
    {
        if ($direction !== 'backward') {
            $direction = 'forward';
        }

        if (empty($date_start)) {
            $position = null;
            $position_type = 'Строка';
        } else {
            $position_type = 'Запись';
            $position = [
                "d" => [
                    "{\"d\":[\"" . Carbon::parse(
                        $date_start
                    ) . "\"],\"s\":[{\"n\":\"DateTime\",\"t\":\"Дата и время\"}],\"f\":0,\"_type\":\"record\"}"
                ],
                "s" => [
                    ["t" => "Строка", "n" => "@Событие"]
                ],
                "_type" => "record",
                "f" => 1
            ];
        }

        $params = [
            "Фильтр" => [
                "d" => [
                    [
                        Carbon::parse($date_end)->format('Y-m-d') ?? "2020-01-01",
                        Carbon::now()->endOfDay()->format('Y-m-d')
                    ],
                    $planned,
                    [
                        "ResponsibleFIO",
                        "ReglsInfoCalculate",
                        "CrmEventsInfo",
                        "ClientRegion",
                        "EventResponsibleIcon"
                    ]
                ],
                "s" => [
                    ["t" => ["n" => "Массив", "t" => "Дата"], "n" => "DateRange"],
                    ["t" => "Логическое", "n" => "Planned"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "Requisites"]
                ],
                "_type" => "record"
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    $direction,
                    true,
                    ++$limit,
                    $position
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => $position_type, "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];

        $response = $this->send('CRMEvent.List', $params);

        if (!$response->ok()) {
            $this->checkSbisPermissions($response);
            Log::info('Sbis CRMEvent.List 2 - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();

        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        } else {
            Log::error('Sbis CRMEvent.List 2 Error - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
        }

        return false;
    }

    // Получение списка исходящих счетов (документов)
    public function getOutBills($limit = 2, $page = 0, $start_date = null, $end_date = null)
    {
        return $this->getDocumentsList(
            'СчетИсх',
            $limit,
            $page,
            $start_date ?? date('d.m.Y'),
            $end_date ?? '01.01.2020'
        );
    }

    // Получение списка исходящих счетов внутрениим методом
    public function getOutBillsByInsideMethod(
        $limit = 18,
        $date_start = null,
        $date_end = null,
        $next_position_json = null,
        $onlyDeleted = false
    )
    {
        $date_start_type = !empty($date_start) ? 'Дата' : 'Строка';
        $date_end_type = !empty($date_end) ? 'Дата' : 'Строка';

        $d = [
            true,
            true,
            true,
            true,
            "СчетИсх",
            true,
            "Все",
            $date_end, // "2019-12-31",
            $date_start, // "2019-01-01",
            "-2",
            "Нет",
            [],
            []
        ];
        $s = [
            ["t" => "Логическое", "n" => "iterative_list"],
            ["t" => "Логическое", "n" => "new_navigation"],
            ["t" => "Логическое", "n" => "position_on_today"],
            ["t" => "Логическое", "n" => "rp_doc"],
            ["t" => "Строка", "n" => "НазваниеТипаДокумента"],
            ["t" => "Логическое", "n" => "УчитыватьИерархиюНО"],
            ["t" => "Строка", "n" => "ФильтрВладелец"],
            ["t" => $date_end_type, "n" => "ФильтрДатаП"],
            ["t" => $date_start_type, "n" => "ФильтрДатаС"],
            ["t" => "Строка", "n" => "ФильтрОрганизацияФилиал"],
            ["t" => "Строка", "n" => "ФильтрПоЛицуИзСписка"],
            ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "ФильтрПометкиИскл"],
            ["t" => "JSON-объект", "n" => "searchSettings"],
        ];

        if ($onlyDeleted) {
            $d[] = true;
            $s[] = ["t" => "Логическое", "n" => "ФильтрУдаленные"];
        }

        if (
            !empty($next_position_json) &&
            is_array(json_decode($next_position_json)) &&
            count(json_decode($next_position_json)) === 2
        ) {
            $position = [
                "d" => json_decode($next_position_json),
                //[
                //"2563466",
                //"2019-12-31"
                //],
                "s" => [
                    ["t" => "Строка", "n" => "@Документ"],
                    ["t" => "Строка", "n" => "Дата"]
                ],
                "_type" => "record",
                "f" => 1
            ];
        }
        $params = [
            "Фильтр" => [
                "d" => $d,
                "s" => $s,
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    "forward",
                    true,
                    $limit, // 18 default
                    $position ?? null
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Запись", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];

        $response = $this->send('СчетИсх.СписокХраним', $params);

        if (!$response->ok()) {
            $this->checkSbisPermissions($response);
            Log::info('Sbis СчетИсх.СписокХраним - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();

        if (isset($json['result']) && is_array($json['result'])) {
            return $json['result'];
        }

        return false;
    }

    // Получение списка реализаций (документов)
    public function getRealizations($limit = 2, $page = 0, $start_date = null, $end_date = null)
    {
        return $this->getDocumentsList(
            'ДокОтгрИсх',
            $limit,
            $page,
            $start_date ?? date('d.m.Y'),
            $end_date ?? '01.01.2020'
        );
    }

    // Получение списка реализаций внутрениим методом
    public function getRealizationsByInsideMethod(
        $limit = 18,
        $date_start = null,
        $date_end = null,
        $next_position_json = null,
        $onlyDeleted = false
    )
    {
        $date_start_type = !empty($date_start) ? 'Дата' : 'Строка';
        $date_end_type = !empty($date_end) ? 'Дата' : 'Строка';

        $d = [
            true,
            true,
            true,
            true,
            "ДокОтгрИсх",
            true,
            "Все",
            $date_end, // "2019-12-31",
            $date_start, // "2019-01-01",
            "-2",
            [],
            null,
            "Нет",
            []
        ];
        $s = [
            ["t" => "Логическое", "n" => "iterative_list"],
            ["t" => "Логическое", "n" => "new_navigation"],
            ["t" => "Логическое", "n" => "position_on_today"],
            ["t" => "Логическое", "n" => "rp_doc"],
            ["t" => "Строка", "n" => "НазваниеТипаДокумента"],
            ["t" => "Логическое", "n" => "УчитыватьИерархиюНО"],
            ["t" => "Строка", "n" => "ФильтрВладелец"],
            ["t" => $date_end_type, "n" => "ФильтрДатаП"],
            ["t" => $date_start_type, "n" => "ФильтрДатаС"],
            ["t" => "Строка", "n" => "ФильтрОрганизацияФилиал"],
            ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "ФильтрПометкиИскл"],
            ["t" => "Строка", "n" => "ФильтрСуммаДо"],
            ["t" => "Строка", "n" => "ФильтрПоЛицуИзСписка"],
            ["t" => "JSON-объект", "n" => "searchSettings"]
        ];

        if ($onlyDeleted) {
            $d[] = true;
            $s[] = ["t" => "Логическое", "n" => "ФильтрУдаленные"];
        }

        if (
            !empty($next_position_json) &&
            is_array(json_decode($next_position_json)) &&
            count(json_decode($next_position_json)) === 2
        ) {
            $position = [
                "d" => json_decode($next_position_json),
                //[
                //"2563466",
                //"2019-12-31"
                //],
                "s" => [
                    ["t" => "Строка", "n" => "@Документ"],
                    ["t" => "Строка", "n" => "Дата"]
                ],
                "_type" => "record",
                "f" => 1
            ];
        }
        $params = [
            "Фильтр" => [
                "d" => $d,
                "s" => $s,
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    "forward",
                    true,
                    $limit, // 18 default
                    $position ?? null
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Запись", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];

        $response = $this->send('ДокОтгрИсх.СписокХраним', $params);

        if (!$response->ok()) {
            $this->checkSbisPermissions($response);
            Log::info('Sbis ДокОтгрИсх.СписокХраним - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();

        if (isset($json['result']) && is_array($json['result'])) {
            return $json['result'];
        }

        return false;
    }

    // Получение поступлений (входящих реализаций) внутренним методом
    public function getRealizationsIncomingByInsideMethod(
        $limit = 25,
        $date_start = null,
        $date_end = null,
        $next_position_json = null,
        $onlyDeleted = false
    )
    {
        $date_start_type = !empty($date_start) ? 'Дата' : 'Строка';
        $date_end_type = !empty($date_end) ? 'Дата' : 'Строка';

        $d = [
            true,
            true,
            true,
            true,
            "ДокОтгрВх",
            true,
            "Все",
            $date_end, // "2022-12-31",
            $date_start, // "2022-12-01",
            "-2",
            "Нет",
            [],
            null,
            null
        ];
        $s = [
            ["t" => "Логическое", "n" => "iterative_list"],
            ["t" => "Логическое", "n" => "new_navigation"],
            ["t" => "Логическое", "n" => "position_on_today"],
            ["t" => "Логическое", "n" => "rp_doc"],
            ["t" => "Строка", "n" => "НазваниеТипаДокумента"],
            ["t" => "Логическое", "n" => "УчитыватьИерархиюНО"],
            ["t" => "Строка", "n" => "ФильтрВладелец"],
            ["t" => $date_end_type, "n" => "ФильтрДатаП"],
            ["t" => $date_start_type, "n" => "ФильтрДатаС"],
            ["t" => "Строка", "n" => "ФильтрОрганизацияФилиал"],
            ["t" => "Строка", "n" => "ФильтрПоЛицуИзСписка"],
            [
                "t" => [
                    "n" => "Массив",
                    "t" => "Строка"
                ],
                "n" => "ФильтрПометкиИскл"
            ],
            ["t" => "Строка", "n" => "ФильтрПоставка"],
            ["t" => "Строка", "n" => "ФильтрСуммаДо"]
        ];

        if ($onlyDeleted) {
            $d[] = true;
            $s[] = ["t" => "Логическое", "n" => "ФильтрУдаленные"];
        }

        if (
            !empty($next_position_json) &&
            is_array(json_decode($next_position_json)) &&
            count(json_decode($next_position_json)) === 2
        ) {
            $positionType = 'Запись';
            $position = [
                "d" => json_decode($next_position_json),
                //[
                //"2563466",
                //"2019-12-31"
                //],
                "s" => [
                    ["t" => "Строка", "n" => "@Документ"],
                    ["t" => "Строка", "n" => "Дата"]
                ],
                "_type" => "record",
                "f" => 1
            ];
        }

        $params = [
            "Фильтр" => [
                "d" => $d,
                "s" => $s,
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    "forward",
                    true,
                    $limit, // 25 default
                    $position ?? null
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => $positionType ?? 'Строка', "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];
        $response = $this->send('ДокОтгрВх.СписокХраним', $params);
        if (!$response->ok()) {
            $this->checkSbisPermissions($response);
            Log::info('Sbis ДокОтгрВх.СписокХраним - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']) && is_array($json['result'])) {
            return $json['result'];
        }
        return false;
    }

    // Прочитать документ
    public function getDocument($id)
    {
        $params = [
            "Документ" => [
                "Идентификатор" => $id,
                "ДопПоля" => "ДополнительныеПоля",
            ]
        ];

        $response = $this->send('СБИС.ПрочитатьДокумент', $params);

        if (!$response->ok()) {
            Log::info('Sbis СБИС.ПрочитатьДокумент - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();

        if (isset($json['result']) && is_array($json['result'])) {
            return $json['result'];
        }

        return false;
    }

    // Прочитать Исходящий счет | ВНУТРЕННИЙ МЕТОД
    public function getOutbill($id)
    {
        $params = [
            "ИдО" => $id,
            "ИмяМетода" => "СчетСкИсх.ListLite",
            "ДопПоля" => [
                "ИмяОбъекта" => "СчетСкИсх"
            ]
        ];

        $response = $this->send('СчетСкИсх.ПрочитатьДляУчастника', $params);

        if (!$response->ok()) {
            Log::info(
                'Sbis СчетСкИсх.ПрочитатьДляУчастника - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE)
            );
            $response->throw();
        }

        $json = $response->json();

        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }

        return false;
    }

    // Получить исходящий счет по номеру | ВНУТРЕННИЙ МЕТОД
    public function getDocumentByNumber($number, $type, $guid = null)
    {
        $number_to_find = trim(str_replace('№', '', $number));

        if (!in_array($type, ['СчетИсх', 'ДокОтгрИсх'])) {
            return false;
        }

        $d = [
            true,
            true,
            true,
            $type, //"СчетИсх",
            true,
            "Все",
            null,
            null,
            "-2",
            "Нет",
            (string)$number_to_find,
            []
        ];

        $s = [
            ["t" => "Логическое", "n" => "iterative_list"],
            ["t" => "Логическое", "n" => "new_navigation"],
            ["t" => "Логическое", "n" => "rp_doc"],
            ["t" => "Строка", "n" => "НазваниеТипаДокумента"],
            ["t" => "Логическое", "n" => "УчитыватьИерархиюНО"],
            ["t" => "Строка", "n" => "ФильтрВладелец"],
            ["t" => "Строка", "n" => "ФильтрДатаП"],
            ["t" => "Строка", "n" => "ФильтрДатаС"],
            ["t" => "Строка", "n" => "ФильтрОрганизацияФилиал"],
            ["t" => "Строка", "n" => "ФильтрПоЛицуИзСписка"],
            ["t" => "Строка", "n" => "ФильтрПоМаске"],
            [
                "t" => [
                    "n" => "Массив",
                    "t" => "Строка"
                ],
                "n" => "ФильтрПометкиИскл"
            ]
        ];

        if ($type === 'ДокОтгрИсх') {
            $d[] = null;
            $s[] = ["t" => "Строка", "n" => "ФильтрСуммаДо"];
        }

        $params = [
            "Фильтр" => [
                "d" => $d,
                "s" => $s,
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    "bothways",
                    true,
                    1000,
                    null
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Строка", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];

        $response = $this->send($type . '.СписокХраним', $params);

        if (!$response->ok()) {
            Log::info('Sbis ' . $type . '.СписокХраним - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();

        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            $out_list = parseSbisDataToArray($json['result']) ?? null;
            if (is_array($out_list)) {
                foreach ($out_list as $document_data_2) {
                    if (empty($guid) && count($out_list) === 1) {
                        if ($number === $document_data_2['Номер']) {
                            return $document_data_2;
                        }
                    } else {
                        if ($number === $document_data_2['Номер'] && $guid === $document_data_2['ИдентификаторДокумента']) {
                            return $document_data_2;
                        }
                    }
                }
            }
            return null;
        }

        return false;
    }

    // Прочитать Реализацию | ВНУТРЕННИЙ МЕТОД
    public function getRealization($id)
    {
        $params = [
            "ИдО" => $id,
            "ИмяМетода" => "РеалИсх.ListLite",
            "ДопПоля" => [
                "ИмяОбъекта" => "РеалИсх"
            ]
        ];

        $response = $this->send('РеалИсх.ПрочитатьДляУчастника', $params);

        if (!$response->ok()) {
            Log::info(
                'Sbis РеалИсх.ПрочитатьДляУчастника - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE)
            );
            $response->throw();
        }

        $json = $response->json();

        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }

        return false;
    }

    // Прочитать Входящую реализацию
    public function getRealizationIncoming($id)
    {
        $params = [
            "ИдО" => $id,
            "ИмяМетода" => "РеалВх.ListLite",
            "ДопПоля" => [
                "ИмяОбъекта" => "РеалВх"
            ]
        ];
        $response = $this->send('РеалВх.ПрочитатьДляУчастника', $params);
        if (!$response->ok()) {
            Log::info(
                'Sbis РеалВх.ПрочитатьДляУчастника - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE)
            );
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Получение списка документов по типу документа
    public function getDocumentsList($type, $limit = 2, $page = 0, $start_date, $end_date)
    {
        if (!in_array($type, Document::$document_types)) {
            return false;
        }

        $params = [
            "Фильтр" => [
                "ДатаС" => $end_date,
                "ДатаПо" => $start_date,
                "Тип" => $type,
                'Навигация' => [
                    'РазмерСтраницы' => (int)$limit,
                    'Страница' => (int)$page,
                ]
            ]
        ];
        $response = $this->send('СБИС.СписокДокументов', $params);

        if (!$response->ok()) {
            Log::info('Sbis СБИС.СписокДокументов - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        return $response->object();
    }

    // Получение списка обращений | ВНУТРЕННИЙ МЕТОД
    public function getComplaintList($nextArr = null, $limit = 10, $start_date = null, $end_date = '2020-01-01')
    {
        if (!empty($nextArr)) {
            $prev_date = Carbon::parse($nextArr->date);
            $prev_id = $nextArr->id;

            $navigation = [
                "d" => [
                    [
                        "d" => [
                            $prev_date,
                            $prev_id,
                        ],
                        "_type" => "record",
                        "s" => [
                            [
                                "n" => "ДатаВремяНач",
                                "t" => "Дата и время"
                            ],
                            [
                                "n" => "@Рекламация",
                                "t" => "Число целое"
                            ]
                        ],
                        "f" => 2
                    ]
                ],
                "s" => [
                    [
                        "t" => "Запись",
                        "n" => "cursor"
                    ]
                ],
                "_type" => "record",
                "f" => 1
            ];
        } else {
            $navigation = null;
        }

        $params = [
            "Фильтр" => [
                "d" => [
                    1,
                    true,
                    true,
                    true,
                    [
                        null
                    ],
                    "РекламацияВх",
                    null,
                    true,
                    null,
                    [
                        $end_date,
                        $start_date ?? Carbon::now()->format('Y-m-d')
                    ], // preriod
                    null,
                    null,
                    null,
                    null,
                    0,
                    null,
                    null,
                    "РекламацияВх"
                ],
                "s" => [
                    ["t" => "Число целое", "n" => "FilterState"],
                    ["t" => "Логическое", "n" => "WithMain"],
                    ["t" => "Логическое", "n" => "WithoutResponsibleInfo"],
                    ["t" => "Логическое", "n" => "add_employee_data"],
                    [
                        "t" => [
                            "n" => "Массив",
                            "t" => "Строка"
                        ],
                        "n" => "contracts"
                    ],
                    ["t" => "Строка", "n" => "doc_type_name"],
                    ["t" => "Строка", "n" => "folder_id"],
                    ["t" => "Логическое", "n" => "include_main_priority"],
                    ["t" => "Строка", "n" => "includewg"],
                    [
                        "t" => [
                            "n" => "Массив",
                            "t" => "Дата"
                        ],
                        "n" => "period"
                    ],
                    ["t" => "Строка", "n" => "search"],
                    ["t" => "Строка", "n" => "Исполнитель"],
                    ["t" => "Строка", "n" => "Отбираются"],
                    ["t" => "Строка", "n" => "Приоритет"],
                    ["t" => "Число целое", "n" => "Статус"],
                    ["t" => "Строка", "n" => "СтруктураПредприятия"],
                    ["t" => "Строка", "n" => "СтруктураПредприятияИсполнителя"],
                    ["t" => "Строка", "n" => "ТипДокументаНазвание"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => [
                "d" => [
                    [
                        true,
                        "ДатаВремяНач",
                        false
                    ]
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "l"],
                    ["t" => "Строка", "n" => "n"],
                    ["t" => "Логическое", "n" => "o"]
                ],
                "_type" => "recordset",
                "f" => 0
            ],
            "Навигация" => [
                "d" => [
                    "forward",
                    true,
                    $limit,
                    $navigation
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Запись", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];

        $response = $this->send('СписокРекламаций.ListGeneral', $params);

        if (!$response->ok()) {
            $this->checkSbisPermissions($response);
            Log::info(
                'Sbis СписокРекламаций.ListGeneral - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE)
            );
            $response->throw();
        }

        $json = $response->json();

        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }

        return false;
    }

    // Получение обращения | ВНУТРЕННИЙ МЕТОД
    public function getComplaint($id)
    {
        $params = [
            "ИдО" => $id,
            "ИмяМетода" => "СписокРекламаций.Список",
        ];

        $response = $this->send('СписокРекламаций.Прочитать', $params);

        if (!$response->ok()) {
            $this->checkSbisPermissions($response);
            Log::info('Sbis СписокРекламаций.Прочитать - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();

        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }

        return false;
    }

    // Получение списка нарядов | ВНУТРЕННИЙ МЕТОД
    public function getServiceOrdersList($page = 0, $limit = 20, $start_date = null, $end_date = '2020-01-01')
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    [
                        "РП.GeoData",
                        "РП.ПрикрепленСкан",
                        "РП.СуммаЗП",
                        "РП.СостояниеОплаты",
                        "РП.Автор",
                        "РП.Документ",
                        "РП.ОсновнаяИнформация",
                        "РП.ДополнительнаяИнформация",
                        "РП.Резерв"
                    ],
                    $start_date ?? Carbon::now()->format('Y-m-d'), //Carbon::now()->endOfMonth()->format('Y-m-d'),
                    "Период", //"Месяц",
                    "За период", //"01.01.21 - " . Carbon::now()->endOfMonth()->format('d.m.y'), //"За последний месяц",
                    $end_date, //"2020-01-01",
                    [],
                    null,
                    "ПоДатеНачалаРабот",
                    "-1",
                ],
                "s" => [
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "Рассчитать"],
                    ["t" => "Дата", "n" => "ФильтрДатаП"],
                    ["t" => "Строка", "n" => "ФильтрДатаПериод"],
                    ["t" => "Строка", "n" => "ФильтрДатаПериодТекст"],
                    ["t" => "Дата", "n" => "ФильтрДатаС"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "ФильтрИсключитьИд"],
                    ["t" => "Строка", "n" => "ФильтрОбъектРемонта"],
                    ["t" => "Строка", "n" => "ФильтрОтобрать"],
                    ["t" => "Строка", "n" => "ФильтрПоКраткомуСостоянию"],
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    true,
                    $limit,
                    $page
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "ЕстьЕще"],
                    ["t" => "Число целое", "n" => "РазмерСтраницы"],
                    ["t" => "Число целое", "n" => "Страница"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];

        $response = $this->send('Наряд.СписокПитон', $params);

        if (!$response->ok()) {
            $this->checkSbisPermissions($response);
            Log::info('Sbis Наряд.СписокПитон - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();

        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }

        return false;
    }

    // Получение списка телефонных звонков | ВНУТРЕННИЙ МЕТОД
    public function getCallsList($start_date = null, $limit = 20)
    {
        if (!empty($start_date)) {
            $start_date = Carbon::parse($start_date);
        } else {
            $start_date = Carbon::now();
        }

        $params = [
            "Фильтр" => [
                "d" => [
                    [
                        5,
                        2,
                        1
                    ],
                    "period",
                    "2019-01-01 00:00:00+05",
                    Carbon::now()->endOfMonth()->format('Y-m-d') . " 23:59:59.999+05",
                    null,
                    null,
                    "+05"
                ],
                "s" => [
                    [
                        "t" => ["n" => "Массив", "t" => "Число целое"],
                        "n" => "call_type"
                    ],
                    ["t" => "Строка", "n" => "dateText"],
                    ["t" => "Строка", "n" => "datetime_begin"],
                    ["t" => "Строка", "n" => "datetime_end"],
                    ["t" => "Логическое", "n" => "is_inner"],
                    ["t" => "Строка", "n" => "is_outgoing"],
                    ["t" => "Строка", "n" => "tz"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => [
                "d" => [
                    [
                        false,
                        "time",
                        true
                    ]
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "l"],
                    ["t" => "Строка", "n" => "n"],
                    ["t" => "Логическое", "n" => "o"]
                ],
                "_type" => "recordset",
                "f" => 0
            ],
            "Навигация" => [
                "d" => [
                    "forward",
                    true,
                    $limit,
                    [
                        "d" => [
                            $start_date //"2021-11-12 11:42:23"
                        ],
                        "s" => [
                            ["t" => "Строка", "n" => "datetime_last"]
                        ],
                        "_type" => "record",
                        "f" => 1
                    ]
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Запись", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];

        $response = $this->send('CallInfo.List', $params);

        if (!$response->ok()) {
            $this->checkSbisPermissions($response);
            Log::info('Sbis CallInfo.List - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        return $response->json();
        /*$json = $response->json();
        dd($json);
        if ( isset($json['result']['d']) && is_array($json['result']['d']) ) {
            return parseSbisDataToArray($json['result']);
        }
        return false;*/
    }

    // Получение ленты документа. Внутренний метод
    public function getTimeline($id, $guid, $type)
    {
        if (empty($id) || empty($guid) || empty($type)) {
            return false;
        }

        $params = [
            "document" => [
                "id" => (string)$id,
                "msgServiceId" => $guid,
                "redactions" => [
                    (string)$id
                ]
            ],
            "settings" => [
                "objectName" => $type
            ],
            "documentInfo" => null
        ];

        $response = $this->send('Документ.Timeline', $params);

        if (!$response->ok()) {
            Log::info('Sbis Документ.Timeline - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();

        if (isset($json['result']) && is_array($json['result'])) {
            return $json['result'];
        }

        return false;
    }

    // Получение номенклатуры (каталога) | ВНУТРЕННИЙ МЕТОД
    public function getNomenclatureList($folder_id = null, $limit = 1000)
    {
        if (is_numeric($folder_id)) {
            $folder_id = $folder_id * 1;
        }

        $params = [
            "Фильтр" => [
                "d" => [
                    1,
                    null,
                    null,
                    null,
                    false,
                    "online",
                    $folder_id ?? null,
                    $folder_id ?? null,
                    0,
                    null,
                    null,
                    null,
                    ["Catalog"],
                    [
                        "d" => [
                            true
                        ],
                        "s" => [
                            [
                                "t" => "Логическое",
                                "n" => "HideConditionalProduct"
                            ]
                        ],
                        "_type" => "record",
                        "f" => 1
                    ],
                    true,
                    null,
                    null,
                    null
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Archival"],
                    ["t" => "Строка", "n" => "Balance"],
                    ["t" => "Строка", "n" => "BalanceForOrganization"],
                    ["t" => "Строка", "n" => "BarcodeExist"],
                    ["t" => "Логическое", "n" => "CheckFolderExists"],
                    ["t" => "Строка", "n" => "Endpoint"],
                    ["t" => "Число целое", "n" => "Folder"],
                    ["t" => "Число целое", "n" => "GetColumnsFromSettings"],
                    ["t" => "Число целое", "n" => "GetPath"],
                    ["t" => "Строка", "n" => "MarkColor"],
                    ["t" => "Строка", "n" => "NodeType"],
                    ["t" => "Строка", "n" => "PublicationSaleState"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "ScopesAreas"],
                    ["t" => "Запись", "n" => "ServiceOption"],
                    ["t" => "Логическое", "n" => "TranslitSearchString"],
                    ["t" => "Строка", "n" => "Type"],
                    ["t" => "Строка", "n" => "Vat"],
                    ["t" => "Строка", "n" => "Warehouse"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => [
                "d" => [
                    [
                        true,
                        "Custom",
                        false
                    ]
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "l"],
                    ["t" => "Строка", "n" => "n"],
                    ["t" => "Логическое", "n" => "o"]
                ],
                "_type" => "recordset",
                "f" => 0
            ],
            "Навигация" => [
                "d" => [
                    true,
                    $limit,
                    0
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "ЕстьЕще"],
                    ["t" => "Число целое", "n" => "РазмерСтраницы"],
                    ["t" => "Число целое", "n" => "Страница"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => [
                "Name",
                "NameBase",
                "NameShort",
                "Identifier",
                "Image",
                "Number",
                "ImageObject",
                "TypeObject",
                "VAT",
                "DescriptionSimple",
                "MarketplacePrice",
                "Price",
                "MUP",
                "Code",
                "BalanceObject",
                "FolderContents",
                "Accounting",
                "PublicationSaleState",
                "Deleted",
                "ContainsFolders",
                "ContainsAnyItem",
                "IsUnderOrder",
                "CodeAlternative",
                "IsFolderUI",
                "MarkColor"
            ]
        ];
        $response = $this->send('Nomenclature.List', $params);

        if (!$response->ok()) {
            Log::info('Sbis Nomenclature.List - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();

        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            $result = parseSbisDataToArray($json['result']);
            try {
                if (count($result) === $limit) {
                    send2Dev(
                        "Достигнут лимит получения номенклатуры у пользователя {$this->sbisAccount->user_id} "
                        . get_domen()
                    );
                }
            } catch (\Exception $e) {
                Log::error($e->getMessage());
            }
            return $result;
        }

        return false;
    }

    // Удаление документа
    public function deleteDocument($id)
    {
        $params = [
            "Документ" => [
                "Идентификатор" => $id,
            ]
        ];

        $response = $this->send('СБИС.УдалитьДокумент', $params);
        if (!$response->ok()) {
            Log::info('Sbis СБИС.УдалитьДокумент - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']) && is_array($json['result'])) {
            return $json['result'];
        }
        return false;
    }

    // Создание счета на оплату
    public function createOutbill($sum, $org_type, $title, $inn, $kpp = null)
    {
        if (empty($inn)) {
            $getInfoOrg = $this->getPointSale();
            $inn = $getInfoOrg[0]['ИНН'];
            $kpp = $getInfoOrg[0]['КПП'];
        }
        $params = [
            "Документ" => [
                "Тип" => 'СчетИсх',
                'НашаОрганизация' => [
                    'СвЮЛ' => [
                        'ИНН' => $inn,
                        'КПП' => $kpp,
                    ],
                ],
                "Сумма" => (int)$sum,
                "Примечание" => "Услуги по настройке коннектора Тавиат",
            ]
        ];
        if ($org_type === 'ooo') {
            $params['Документ']['Контрагент'] = [
                'СвЮЛ' => [
                    'ИНН' => $inn,
                    'КПП' => $kpp,
                    'Название' => $title,
                ]
            ];
        } elseif ($org_type === 'ip') {
            $params['Документ']['Контрагент'] = [
                'СвФЛ' => [
                    'ИНН' => $inn,
                    'Название' => $title,
                ]
            ];
        }
        if (!is_production()) {
            $params['Документ']['Ответственный'] = [
                'Фамилия' => 'Орлов',
                'Имя' => 'Александр',
                'Отчество' => 'Олегович',
            ];
        }

        $response = $this->send('СБИС.ЗаписатьДокумент', $params);

        if (!$response->ok()) {
            Log::info('Sbis СБИС.ЗаписатьДокумент - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();

        if (isset($json['result']) && is_array($json['result'])) {
            return $json['result'];
        }

        return false;
    }

    // Добавление табличной части документа к счету на оплату
    public function addXmlToOutbill(
        $outbill_id,
        $outbill_number,
        $sum,
        $org_type,
        $title,
        $inn,
        $kpp = null,
        $addr = '',
        $modules = null
    )
    {
        $sum = (integer)$sum * 1;
        $xml_filename = 'ON_SCHET_4345112697434501001_' . $inn . '_' . date('Ymd') . '_' . Str::uuid();

        $title = str_replace('"', '&quot;', $title);
        $addr = str_replace('"', '&quot;', $addr);

        if ($org_type === 'ooo') {
            $customerData = '
                <ИдСв>
                  <СвЮЛ ИННЮЛ="' . $inn . '" КПП="' . $kpp . '" НаимОрг="' . $title . '"/>
                </ИдСв>
                <Адрес>
                  <АдрИно АдрТекст="' . $addr . '" КодСтр="643"/>
                </Адрес>
            ';
        } elseif ($org_type === 'ip') {
            $customerData = '
                <ИдСв>
                  <СвФЛ ИННФЛ="' . $inn . '" Название="' . $title . '"/>
                </ИдСв>
                <Адрес>
                  <АдрИно АдрТекст="' . $addr . '" КодСтр="643"/>
                </Адрес>
            ';
        }

        if (!empty($modules)) {
            $productsData = '';
            foreach ($modules as $module_slug => $module_count) {
                if ($module_count > 0) {
                    $module = Module::whereSlug($module_slug)->first();
                    if (!empty($module->price_month)) {
                        $price = $module->price_month;
                        $sumProduct = $price * $module_count;
                    } elseif (!empty($module->price_year)) {
                        $price = round($module->price_year / 12, 2);
                        $sumProduct = $price * $module_count;
                    }
                    $productsData .= '<СведТов ЕдИзм="шт" ИнфПолСтр="&lt;Данные>&lt;Реквизит Имя=&quot;КодПоставщика&quot; Значение=&quot;' . $module->nomenclature_code . '&quot;/>&lt;Реквизит Имя=&quot;НазваниеПоставщика&quot; Значение=&quot;' . $module->nomenclature_title . '&quot;/>&lt;Реквизит Имя=&quot;ПоляНоменклатуры&quot; Значение=&quot;&amp;quot;Type&amp;quot;:&amp;quot;Услуги&amp;quot;,&amp;quot;Category&amp;quot;:&amp;quot;Услуги и работы&amp;quot;&quot;/>&lt;/Данные>" Категория="1" КодТов="' . $module->nomenclature_code . '" КолТов="' . $module_count . '" НаимТов="' . $module->nomenclature_title . '" НомСтр="1" ОКЕИ_Тов="796" СтТовБезНДС="' . $sumProduct . '" СтТовУчНал="' . $sumProduct . '.00" ЦенаТов="' . $price . '" ЦенаТовСНДС="' . $price . '">
                        <НалСт НалСтВел="без НДС" НалСтТип="текст"/>
                        <СумНал СумНДС="без НДС"/>
                      </СведТов>';
                }
            }
        } else {
            $productsData = '<СведТов ЕдИзм="шт" ИнфПолСтр="&lt;Данные>&lt;Реквизит Имя=&quot;КодПоставщика&quot; Значение=&quot;' . config(
                    'taviat.main_nomenclature_code'
                ) . '&quot;/>&lt;Реквизит Имя=&quot;НазваниеПоставщика&quot; Значение=&quot;Услуги по настройке коннектора Тавиат&quot;/>&lt;Реквизит Имя=&quot;ПоляНоменклатуры&quot; Значение=&quot;&amp;quot;Type&amp;quot;:&amp;quot;Услуги&amp;quot;,&amp;quot;Category&amp;quot;:&amp;quot;Услуги и работы&amp;quot;&quot;/>&lt;/Данные>" Категория="1" КодТов="' . config(
                    'taviat.main_nomenclature_code'
                ) . '" КолТов="1" НаимТов="Услуги по настройке коннектора Тавиат" НомСтр="1" ОКЕИ_Тов="796" СтТовБезНДС="' . $sum . '.00" СтТовУчНал="' . $sum . '.00" ЦенаТов="' . $sum . '.00" ЦенаТовСНДС="' . $sum . '.00">
		        <НалСт НалСтВел="без НДС" НалСтТип="текст"/>
		        <СумНал СумНДС="без НДС"/>
		      </СведТов>';
        }

        $xml = '<?xml version="1.0" encoding="WINDOWS-1251" ?>
		<Файл ВерсФорм="5.01" ИдФайл="' . $xml_filename . '">

		  <Документ>
		    <СвСчет ДатаСчет="' . date('d.m.Y') . '" КодОКВ="643" НомерСчет="' . $outbill_number . '">
		      <Основание НаимОсн="-"/>
		      <СвПрод>
		        <ИдСв>
		          <СвЮЛ ИННЮЛ="1841007528" КПП="184101001" НаимОрг="Общество с ограниченной ответственностью &quot;Тензор-Удмуртия&quot;"/>
		        </ИдСв>
		        <Адрес>
		          <АдрИно АдрТекст="426077, г. Ижевск, ул. Пушкинская, д. 165, лит. Б, пом. 95-111" КодСтр="643"/>
		        </Адрес>
		        <БанкРекв НомерСчета="40702810527000005419">
		          <СвБанк БИК="043304609" КорСчет="30101810500000000609" НаимБанк="КИРОВСКОЕ ОТДЕЛЕНИЕ N8612 ПАО СБЕРБАНК КИРОВ"/>
		        </БанкРекв>
		      </СвПрод>
              <СвПокуп>' . $customerData . '</СвПокуп>
		      <ИнфПол ТекстИнф="&lt;Данные>&lt;Реквизит Имя=&quot;Формироватьдоговорспечатью&quot; Значение=&quot;False&quot;/>&lt;Реквизит Имя=&quot;ФормироватьдокументыДоговоркуплипродажиспечатью&quot; Значение=&quot;False&quot;/>&lt;Реквизит Имя=&quot;Формироватьсчетспечатью&quot; Значение=&quot;False&quot;/>&lt;Реквизит Имя=&quot;ФормироватьдокументыСублицензионныйконтракт&quot; Значение=&quot;False&quot;/>&lt;Реквизит Имя=&quot;Примечание&quot; Значение=&quot;Услуги по настройке коннектора Тавиат&quot;/>&lt;Реквизит Имя=&quot;ИнфПередТабл&quot; Значение=&quot;Услуги по настройке коннектора Тавиат&quot;/>&lt;Реквизит Имя=&quot;ДатаГен&quot; Значение=&quot;' . date(
                'd.m.Y H:i:s'
            ) . '&quot;/>&lt;Реквизит Имя=&quot;ИдентификаторВИ&quot; Значение=&quot;eb95720e-74b1-487e-8673-dd59b8cf481d&quot;/>&lt;/Данные>"/>
		    </СвСчет>
		    <ТаблСчет>' . $productsData . '<ВсегоОпл СтТовБезНДСВсего="' . $sum . '.00" СтТовУчНалВсего="' . $sum . '.00"/></ТаблСчет>
		  </Документ>

		</Файл>
		';

        $params = [
            "Документ" => [
                "Идентификатор" => $outbill_id,
                'Вложение' => [
                    'Тип' => 'ЭДОСч',
                    'Подтип' => 'TENSOR_1',
                    'ВерсияФормата' => '5.01',
                    'Название' => 'Счет на оплату ' . date(
                            'd.m.Y'
                        ) . ' № ' . $outbill_number . ' на сумму ' . $sum . ' р., без НДС',
                    'Сумма' => $sum,
                    'СуммаБезНДС' => $sum,
                    'Номер' => $outbill_number,
                    'Дата' => date('d.m.Y'),
                    'Файл' => [
                        'Имя' => $xml_filename . '.xml',
                        'ДвоичныеДанные' => base64_encode(iconv("UTF-8", "windows-1251", $xml)),
                    ],
                ]
            ]
        ];
        $response = $this->send('СБИС.ЗаписатьВложение', $params);

        if (!$response->ok()) {
            Log::info('Sbis СБИС.ЗаписатьВложение - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        return $response->object();
    }

    // Получение списка клиентов
    public function getClientsList($clients_folder_id, $limit = 10, $nextClientRecord = null, $search = null)
    {
        $limit = $limit ?? 10;
        $next = null;
        if ($nextClientRecord) {
            $next = [
                "d" => [$nextClientRecord],
                "s" => [
                    ["t" => "Строка", "n" => "Cursor"]
                ],
                "_type" => "record",
                "f" => 1
            ];
        }

        $d = [
            "All",
            [
                "d" => [
                    "All",
                    false,
                    "." . $clients_folder_id . ".."
                ],
                "s" => [
                    ["t" => "Строка", "n" => "ClientType"],
                    ["t" => "Логическое", "n" => "IncludeRemoved"],
                    ["t" => "Строка", "n" => "Раздел"]
                ],
                "_type" => "record"
            ],
            [
                4,
                8
            ],
            25,
            [
                "ClientFolders",
                "ClientTags",
                "ClientContactData",
                "ResponsibleFIO",
                "ClientResponsible",
                "FolderResponsible",
                "ClientRegion",
                "SelectionFilterDescription"
            ],
            null,
            "Clients",
            "Name",
            null,
            false,
            "." . $clients_folder_id . ".."
        ];
        $s = [
            ["t" => "Строка", "n" => "ClientType"],
            ["t" => "Запись", "n" => "ClientsExists"],
            [
                "t" => [
                    "n" => "Массив",
                    "t" => "Число целое"
                ],
                "n" => "KindOf"
            ],
            ["t" => "Число целое", "n" => "Limit"],
            [
                "t" => [
                    "n" => "Массив",
                    "t" => "Строка"
                ],
                "n" => "Requisites"
            ],
            ["t" => "Число целое", "n" => "Responsible"],
            ["t" => "Строка", "n" => "ShowOnly"],
            ["t" => "Строка", "n" => "Sorting"],
            ["t" => "Строка", "n" => "Tags"],
            ["t" => "Логическое", "n" => "WithoutFolder"],
            ["t" => "Строка", "n" => "Раздел"]
        ];

        if (!empty($search)) {
            $d[] = $search;
            $s[] = ["t" => "Строка", "n" => "SearchString"];
        }

        $params = [
            "Фильтр" => [
                "d" => $d,
                "s" => $s,
                "_type" => "record"
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    "forward",
                    true,
                    ++$limit,
                    $next
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Запись", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];

        $response = $this->send('CRMClients.ListClientsOnline', $params);

        if (!$response->ok()) {
            Log::info(
                'Sbis CRMClients.ListClientsOnline - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE)
            );
            $response->throw();
        }

        $json = $response->json();

        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return $json['result'];
        }

        return false;
    }

    // Табличная часть реализации | ВНУТРЕННИЙ МЕТОД
    public function getRealizationTabularPart($id)
    {
        if (empty($id) || !is_numeric($id)) {
            return false;
        }

        $params = [
            "Фильтр" => [
                "d" => [
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    false,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    "WHDCore/Controls/nomList:columns-OutflowMainList-v1",
                    true,
                    true,
                    false,
                    true,
                    true,
                    true,
                    false,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    "all",
                    $id
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "add_foreign_info"],
                    ["t" => "Логическое", "n" => "add_nom_ipack"],
                    ["t" => "Логическое", "n" => "add_rnpt_data_by_state"],
                    ["t" => "Логическое", "n" => "calc_actual"],
                    ["t" => "Логическое", "n" => "calc_auto_excise_info"],
                    ["t" => "Логическое", "n" => "calc_category"],
                    ["t" => "Логическое", "n" => "calc_category_totals"],
                    ["t" => "Логическое", "n" => "calc_char_groups"],
                    ["t" => "Логическое", "n" => "calc_characteristic_params"],
                    ["t" => "Логическое", "n" => "calc_content"],
                    ["t" => "Логическое", "n" => "calc_exp_date"],
                    ["t" => "Логическое", "n" => "calc_ext_params"],
                    ["t" => "Логическое", "n" => "calc_full_sn_totals"],
                    ["t" => "Логическое", "n" => "calc_gift"],
                    ["t" => "Логическое", "n" => "calc_have_import_nom_totals"],
                    ["t" => "Логическое", "n" => "calc_init_measure"],
                    ["t" => "Логическое", "n" => "calc_is_production_configured"],
                    ["t" => "Логическое", "n" => "calc_item_type"],
                    ["t" => "Логическое", "n" => "calc_markup_by_self_cost"],
                    ["t" => "Логическое", "n" => "calc_modifiers"],
                    ["t" => "Логическое", "n" => "calc_mu_localization"],
                    ["t" => "Логическое", "n" => "calc_only_manual_parts"],
                    ["t" => "Логическое", "n" => "calc_outflow_filter_totals"],
                    ["t" => "Логическое", "n" => "calc_outflow_product_id"],
                    ["t" => "Логическое", "n" => "calc_parts"],
                    ["t" => "Логическое", "n" => "calc_producer_info"],
                    ["t" => "Логическое", "n" => "calc_product_info"],
                    ["t" => "Логическое", "n" => "calc_prt_price"],
                    ["t" => "Логическое", "n" => "calc_serial_number"],
                    ["t" => "Логическое", "n" => "calc_serial_number_inv"],
                    ["t" => "Строка", "n" => "column_config_id"],
                    ["t" => "Логическое", "n" => "create_composition_service_fields"],
                    ["t" => "Логическое", "n" => "extract_gtin_and_article"],
                    ["t" => "Логическое", "n" => "init_balance"],
                    ["t" => "Логическое", "n" => "init_currency_data"],
                    ["t" => "Логическое", "n" => "init_mercury_fields"],
                    ["t" => "Логическое", "n" => "init_nom"],
                    ["t" => "Логическое", "n" => "init_nom_first_barcode"],
                    ["t" => "Логическое", "n" => "init_nom_product"],
                    ["t" => "Логическое", "n" => "init_recorded_characteristics_sets"],
                    ["t" => "Логическое", "n" => "init_selected_nom_set_data"],
                    ["t" => "Логическое", "n" => "new_list"],
                    ["t" => "Логическое", "n" => "outflow_list"],
                    ["t" => "Логическое", "n" => "recalculate_invariants"],
                    ["t" => "Логическое", "n" => "set_category_in_totals"],
                    ["t" => "Логическое", "n" => "set_default_prices_for_present"],
                    ["t" => "Логическое", "n" => "set_have_expire_date_in_part"],
                    ["t" => "Строка", "n" => "Наименования"],
                    ["t" => "Число целое", "n" => "СкладскойДокумент"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    "bothways",
                    true,
                    5000,
                    [
                        "d" => [
                            0
                        ],
                        "s" => [
                            [
                                "t" => "Число целое",
                                "n" => "@НоменклатураДокумента"
                            ]
                        ],
                        "_type" => "record",
                        "f" => 1
                    ]
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Запись", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];

        $response = $this->send('РеалИсх.DocNomListForUser', $params);

        if (!$response->ok()) {
            Log::info('Sbis РеалИсх.DocNomListForUser - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();

        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }

        return false;
    }

    // Табличная часть исходящего счета | ВНУТРЕННИЙ МЕТОД
    public function getOutbillTabularPart($id)
    {
        if (empty($id) || !is_numeric($id)) {
            return false;
        }

        $params = [
            "Фильтр" => [
                "d" => [
                    true,
                    true,
                    true,
                    true,
                    true,
                    "WHDCore/Controls/nomList:columns-OutBillMainList-v1",
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    $id
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "calc_gift"],
                    ["t" => "Логическое", "n" => "calc_item_type"],
                    ["t" => "Логическое", "n" => "calc_modifiers"],
                    ["t" => "Логическое", "n" => "calc_mu_localization"],
                    ["t" => "Логическое", "n" => "calc_shipped_full"],
                    ["t" => "Строка", "n" => "column_config_id"],
                    ["t" => "Логическое", "n" => "init_balance"],
                    ["t" => "Логическое", "n" => "init_currency_data"],
                    ["t" => "Логическое", "n" => "init_nom_first_barcode"],
                    ["t" => "Логическое", "n" => "init_selected_nom_set_data"],
                    ["t" => "Логическое", "n" => "new_list"],
                    ["t" => "Логическое", "n" => "set_default_prices_for_present"],
                    ["t" => "Число целое", "n" => "СкладскойДокумент"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    "bothways",
                    true,
                    5000,
                    [
                        "d" => [
                            0
                        ],
                        "s" => [
                            [
                                "t" => "Число целое",
                                "n" => "@НоменклатураДокумента"
                            ]
                        ],
                        "_type" => "record",
                        "f" => 1
                    ]
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Запись", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];

        $response = $this->send('СчетСкИсх.DocNomListForUser', $params);

        if (!$response->ok()) {
            Log::info('Sbis СчетСкИсх.DocNomListForUser - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();

        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }

        return false;
    }

    // Табличная часть реализации входящей (поступления) | ВНУТРЕННИЙ МЕТОД
    public function getRealizationIncomingTabularPart($id)
    {
        if (empty($id) || !is_numeric($id)) {
            return false;
        }
        $params = [
            "Фильтр" => [
                "d" => [
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    false,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    false,
                    $id,
                    10,
                    true
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "add_analytics"],
                    ["t" => "Логическое", "n" => "add_rnpt_data"],
                    ["t" => "Логическое", "n" => "calc_actual"],
                    ["t" => "Логическое", "n" => "calc_exp_date"],
                    ["t" => "Логическое", "n" => "calc_ext_params"],
                    ["t" => "Логическое", "n" => "calc_in_nom_factor_data"],
                    ["t" => "Логическое", "n" => "calc_init_measure"],
                    ["t" => "Логическое", "n" => "calc_mu_localization"],
                    ["t" => "Логическое", "n" => "calc_pics"],
                    ["t" => "Логическое", "n" => "extract_gtin_and_article"],
                    ["t" => "Логическое", "n" => "fill_expense_items"],
                    ["t" => "Логическое", "n" => "init_cost_vat_wo_recalc"],
                    ["t" => "Логическое", "n" => "init_currency_data"],
                    ["t" => "Логическое", "n" => "init_linked_docs_totals"],
                    ["t" => "Логическое", "n" => "new_list"],
                    ["t" => "Логическое", "n" => "spread_import"],
                    ["t" => "Число целое", "n" => "СкладскойДокумент"],
                    ["t" => "Число целое", "n" => "Тип"],
                    ["t" => "Логическое", "n" => "ЭтоРасходы"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    "bothways",
                    true,
                    5000,
                    [
                        "d" => [
                            0
                        ],
                        "s" => [
                            [
                                "t" => "Число целое",
                                "n" => "@НоменклатураДокумента"
                            ]
                        ],
                        "_type" => "record",
                        "f" => 1
                    ]
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Запись", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];

        $response = $this->send('РеалВх.DocNomListForUser', $params);
        if (!$response->ok()) {
            Log::info('Sbis РеалВх.DocNomListForUser - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Список сотрудников | ВНУТРЕННИЙ МЕТОД
    public function getStaffList($parent_id = null)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    ["WorkState", "FiredDate", "Contact", "Motivation"],
                    false,
                    -2,
                    $parent_id ?? null,
                    ["Сотрудники"],
                    ["FIO", "DepName", "PHONE"],
                    [
                        "d" => [
                            "All",
                            null,
                            null,
                            null
                        ],
                        "s" => [
                            ["t" => "Строка", "n" => "Status"],
                            ["t" => "Дата", "n" => "From"],
                            ["t" => "Дата", "n" => "To"],
                            ["t" => "Строка", "n" => "ContractType"]
                        ],
                        "_type" => "record",
                        "f" => 1
                    ]
                ],
                "s" => [
                    [
                        "t" => [
                            "n" => "Массив",
                            "t" => "Строка"
                        ],
                        "n" => "CalcFields"
                    ],
                    ["t" => "Логическое", "n" => "EmptyActual"],
                    ["t" => "Число целое", "n" => "Organization"],
                    ["t" => "Строка", "n" => "Parent"],
                    [
                        "t" => [
                            "n" => "Массив",
                            "t" => "Строка"
                        ],
                        "n" => "ScopesAreas"
                    ],
                    [
                        "t" => [
                            "n" => "Массив",
                            "t" => "Строка"
                        ],
                        "n" => "Wanted"
                    ],
                    ["t" => "Запись", "n" => "Working"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    true,
                    1000,
                    0
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "ЕстьЕще"],
                    ["t" => "Число целое", "n" => "РазмерСтраницы"],
                    ["t" => "Число целое", "n" => "Страница"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];

        $response = $this->send('Staff.WasabyList', $params);

        if (!$response->ok()) {
            $this->checkSbisPermissions($response);
            Log::info('Sbis Staff.WasabyList - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();

        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }

        return false;
    }

    // Получение видов работ
    public function typework($work_type_id = null)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    null,
                    $work_type_id
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "used"],
                    ["t" => "Число целое", "n" => "Раздел"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    true,
                    100,
                    0
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "ЕстьЕще"],
                    ["t" => "Число целое", "n" => "РазмерСтраницы"],
                    ["t" => "Число целое", "n" => "Страница"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => [],
        ];
        $response = $this->send('WorkType.ListPython', $params);
        if (!$response->ok()) {
            Log::info('Sbis WorkType.ListPython - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Список тегов в разделе Клиенты
    public function getClientTags($compositeKey = null)
    {
        $id = null;
        if (!empty($compositeKey)) {
            $id = '.' . $compositeKey . '..';
        }

        $params = [
            "Фильтр" => [
                "d" => [
                    [
                        1,
                        4,
                        7
                    ],
                    null,
                    [
                        "BreadCrumbs"
                    ],
                    "Folders",
                    [
                        "d" => [
                            [],
                            [],
                            "all",
                            true,
                        ],
                        "s" => [
                            ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "marked"],
                            ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "excluded"],
                            ["t" => "Строка", "n" => "type"],
                            ["t" => "Логическое", "n" => "recursive"]
                        ],
                        "_type" => "record"
                    ],
                    $id
                ],
                "s" => [
                    ["t" => ["n" => "Массив", "t" => "Число целое"], "n" => "KindOf"],
                    ["t" => "Строка", "n" => "Limit"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "Requisites"],
                    ["t" => "Строка", "n" => "ShowOnly"],
                    ["t" => "Запись", "n" => "entries"],
                    ["t" => "Строка", "n" => "Раздел"]
                ],
                "_type" => "record"
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    "bothways",
                    true,
                    1000,
                    null
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Строка", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];

        $response = $this->send('CRMClients.ListClientsOnline', $params);
        if (!$response->ok()) {
            Log::info(
                'Sbis CRMClients.ListClientsOnline - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE)
            );
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Получение источника у лида
    public function getLeadSource($id)
    {
        $this->setApiUrl('https://online.sbis.ru/crm-service/service/');

        $params = [
            "ObjectId" => $id,
            "Type" => 0,
        ];

        $response = $this->send('PlaceMap.Read', $params);
        if (!$response->ok()) {
            Log::info('Sbis PlaceMap.Read - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Чтение всех источников
    public function readLeadSource($AdObject = null)
    {
        $this->setApiUrl('https://online.sbis.ru/crm-service/service/');

        $place = !empty($AdObject) ? $AdObject . '/' . $AdObject : null;

        $ids = range(0, 1000);

        $params = [
            "Фильтр" => [
                "d" => [
                    true,
                    $ids,
                    [
                        "IconPreview"
                    ],
                    $place,
                    true,
                    1,
                    [
                        "Billed"
                    ],
                    $ids,
                    true
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "ByPlace"],
                    ["t" => ["n" => "Массив", "t" => "Число целое"], "n" => "ChildrenTypes"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "ExtraSettings"],
                    ["t" => "Строка", "n" => "ParentId"],
                    ["t" => "Логическое", "n" => "PlaceIsAdId"],
                    ["t" => "Число целое", "n" => "ReturnObjects"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "StatFields"],
                    ["t" => ["n" => "Массив", "t" => "Число целое"], "n" => "Type"],
                    ["t" => "Логическое", "n" => "WithDNT"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => [
                "d" => [
                    [
                        false,
                        "Billed",
                        true
                    ]
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "l"],
                    ["t" => "Строка", "n" => "n"],
                    ["t" => "Логическое", "n" => "o"]
                ],
                "_type" => "recordset",
                "f" => 0
            ],
            "Навигация" => [
                "d" => [
                    "forward",
                    true,
                    5000,
                    null
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Строка", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];

        $response = $this->send('AdStruct.ReadNew', $params);
        if (!$response->ok()) {
            Log::info('Sbis AdStruct.ReadNew- ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    //Создать служебную записку (Низкая оценка CSAT ресепшн)
    public function createCSATTask($receptionGrade, $city, $window, $inn = null, $kpp = null)
    {
        $xml_filename = 'ON_SLUZHZAP_' . date('Y') . date('m') . date('d') . '_' . sha1(
                date(DATE_ATOM, mktime(0, 0, 0, 7, 1, 2000))
            ) . rand();
        $xml = '<?xml version="1.0" encoding="WINDOWS-1251" ?>
		    <Файл ИдФайл="' . $xml_filename . '" Формат="СлужебнаяЗапискаЗагрузка" ВерсияФормата="1">
             <Документ Дата="' . date('d.m.Y') . '" Номер="' . rand() . '">
                <Описание>Оценка: ' . $receptionGrade . '  в г.' . $city . ', окно: ' . $window . '</Описание>
                 <Исполнители>
                  <ДанныеИсполнителя ФИО="Манохина Наталья"/>
                </Исполнители>
              </Документ>
            </Файл>
		';
        if (empty($inn)) {
            $getInfoOrg = $this->getPointSale();
            $inn = $getInfoOrg[0]['ИНН'];
            $kpp = $getInfoOrg[0]['КПП'];
        }
        $params = [
            "Документ" => [
                "Дата" => date('d.m.Y'),
                "Тип" => "СлужЗап",
                'НашаОрганизация' => [
                    'СвЮЛ' => [
                        'ИНН' => $inn,
                        'КПП' => $kpp,
                    ],
                ],
                "Регламент" => [
                    "Название" => "Низкая оценка CSAT ресепшн"
                ],
                "Ответственный" => [
                    "Фамилия" => "Гришин",
                    "Имя" => "Павел",
                    "Отчество" => "Викторович"
                ],
                'Вложение' => [
                    'Файл' => [
                        'Имя' => $xml_filename . '.xml',
                        'ДвоичныеДанные' => base64_encode(iconv("UTF-8", "windows-1251", $xml))
                    ],
                ],
            ]
        ];

        $response = $this->send('СБИС.ЗаписатьДокумент', $params);
        if (!$response->ok()) {
            Log::info('СБИС.ЗаписатьДокумент (Низкая оценка CSAT ресепшн) - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();

        //Передвинуть на этап выполнить
        $params = [
            "Документ" => [
                "Идентификатор" => $json['result']['Идентификатор'],
                "Этап" => [
                    "Название" => "На выполнение"
                ]
            ]
        ];
        $response = $this->send('СБИС.ВыполнитьДействие', $params);
        if (!$response->ok()) {
            Log::info('СБИС.ВыполнитьДействие (Низкая оценка CSAT ресепшн) - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        return $json;
    }

    // Создать служебную записку
    public function createSbisTask($name, $phone, $email, $cityName, $inn, $parentPartnerInn)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    [
                        "binding" => [
                            "destroy" => "Документ.УдалитьДокументы",
                            "format" => "СлужЗап.Список",
                            "query" => "Список",
                            "read" => "ПрочитатьДляУчастника",
                            "update" => "WriteForParticipant"
                        ],
                        "endpoint" => ["contract" => "СлужЗап"],
                        "readMetaData" => ["ИмяОбъекта" => "СлужЗап"],
                        "updateMetaData" => ["ИмяОбъекта" => "СлужЗап"]
                    ],
                    "EDWS3/Tasks/taskDialog:Dialog",
                    "09598764-edc7-4719-94b0-73ef967bf109",
                    true,
                    true,
                    true,
                    "811d0e35-351a-4213-ad4a-1c5ca97516bc",
                    null, //id сотрудника
                    null,
                    false,
                    true,
                    true,
                    210646,
                    "Новый партнер",
                    null, //id сотрудника
                    901,
                    "EDWS3/Tasks/taskDialog:Dialog",
                    null,
                    null,
                    null,
                    "СлужЗап",
                    null,
                    date('Y-m-d'),
                    date("H:i:s"),
                ],
                "s" => [
                    ["t" => "JSON-объект", "n" => "source"],
                    ["t" => "Строка", "n" => "template"],
                    ["t" => "Строка", "n" => "ВизуальноеПредставление"],
                    ["t" => "Логическое", "n" => "ВключитьСписокИсполнителей"],
                    ["t" => "Логическое", "n" => "ВнешняяИерархия"],
                    ["t" => "Логическое", "n" => "ВызовИзБраузера"],
                    ["t" => "Строка", "n" => "ИдРегламента"],
                    ["t" => "Число целое", "n" => "ЛицоСоздал"],
                    ["t" => "Число целое", "n" => "Подразделение"],
                    ["t" => "Логическое", "n" => "ПоказИерархии"],
                    ["t" => "Логическое", "n" => "ПростыеВД"],
                    ["t" => "Логическое", "n" => "РассчитатьФото"],
                    ["t" => "Число целое", "n" => "Регламент"],
                    ["t" => "Строка", "n" => "РегламентНазвание"],
                    ["t" => "Число целое", "n" => "Сотрудник"],
                    ["t" => "Число целое", "n" => "ТипДокумента"],
                    ["t" => "Строка", "n" => "ТипДокумента.ИмяДиалога"],
                    ["t" => "Строка", "n" => "ТипДокумента.ИмяОбъекта"],
                    ["t" => "Строка", "n" => "ТипДокумента.НазваниеКраткое"],
                    ["t" => "Строка", "n" => "ТипДокумента.ПодТипДокумента"],
                    ["t" => "Строка", "n" => "ТипДокумента.ТипДокумента"],
                    ["t" => "Строка", "n" => "ФильтрРегламент"],
                    ["t" => "Дата", "n" => "Дата"],
                    ["t" => "Время", "n" => "Время"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ИмяМетода" => "СлужЗап.Список"
        ];
        $response = $this->send('СлужЗап.Создать', $params);

        if (!$response->ok()) {
            Log::info('Sbis СлужЗап.Создать - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            try {
                $document = parseSbisDataToArray($json['result']);
                $taskId = $document['@Документ'];
                $d = $document['РП.Документ'];
                $this->changeTaskInfo($taskId, $d, $name, $phone, $email, $cityName, $inn, $parentPartnerInn);
                $result = $this->startTask($document['ДокументРасширение.ИдентификаторВИ'], "Первичное общение");
            } catch (\Exception $e) {
                Log::error('Sbis СлужЗап.WriteForParticipant - не удалось обновить служебную записку');
                Log::error($e);
            }
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Записать данные в служебную записку
    public function changeTaskInfo($id, $d, $name, $phone, $email, $cityName, $inn = '', $parentPartnerInn = '')
    {
        $d['d'][18] = ['Город' => $cityName];
        if (!empty($inn)) {
            $innp = "[\"p\",\"ИНН: " . $inn . "\"]";
        } else {
            $innp = "[\"p\",\"ИНН: - \"]";
        }
        if (!empty($parentPartnerInn)) {
            $innpartner = "[\"p\",\"ИНН пригласившего партнера: " . $parentPartnerInn . "\"]";
        } else {
            $innpartner = "[\"p\",\"ИНН пригласившего партнера: - \"]";
        }
        $params = [
            "Запись" => [
                "d" => [
                    $id,
                    "[[\"p\",{\"version\":\"2\"},\"ФИО: " . $name . "\"],[\"p\",\"Телефон: " . $phone . "\"],[\"p\",\"E-mail: " . $email . "\"]," . $innp . "," . $innpartner . "]",
                    $d,
                    true
                ],
                "s" => [
                    ["t" => "Число целое", "n" => "@Документ"],
                    ["t" => "Строка", "n" => "РазличныеДокументы.ИнформацияJSON"],
                    ["t" => "Запись", "n" => "РП.Документ"],
                    ["t" => "Логическое", "n" => "РучноеСохранение"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => ["ИмяОбъекта" => "СлужЗап"]
        ];
        $response = $this->send('СлужЗап.WriteForParticipant', $params);

        if (!$response->ok()) {
            Log::info('Sbis СлужЗап.WriteForParticipant - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Старт задачи по партнерам
    public function startTask($id, $actionName)
    {
        $params = [
            "Документ" => [
                "Идентификатор" => $id,
                "Этап" => [
                    "Название" => "Выполнение",
                    "Действие" => [
                        "Название" => $actionName,
                    ],
                ],
            ]
        ];

        $response = $this->send('СБИС.ВыполнитьДействие', $params);

        return $response->collect()->toArray();
    }

    // Прочитать наряд
    public function getServiceOrder($orderId)
    {
        $params = [
            'ИдО' => $orderId,
            'ИмяМетода' => 'Order.Список',
            'ДопПоля' => [
                'ИмяОбъекта' => 'Наряд'
            ]
        ];
        $response = $this->send('Наряд.ПрочитатьДляУчастника', $params);

        if (!$response->ok()) {
            Log::info('Sbis Наряд.ПрочитатьДляУчастника - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return $json['result'];
        }

        return false;
    }

    // Табличная часть наряда V2 2023 (после изменений в СБИС)
    public function getServiceOrderTabularPart(int $serviceOrderId)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    false,
                    false,
                    true,
                    true,
                    false,
                    "WHDNomList/grid:config-v1-columns-Works",
                    true,
                    true,
                    true,
                    false,
                    true,
                    $serviceOrderId,
                    null,
                    $serviceOrderId,
                    true,
                    true
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "calc_category"],
                    ["t" => "Логическое", "n" => "calc_category_totals"],
                    ["t" => "Логическое", "n" => "calc_exp_date"],
                    ["t" => "Логическое", "n" => "calc_item_type"],
                    ["t" => "Логическое", "n" => "calc_mu_localization"],
                    ["t" => "Строка", "n" => "column_config_id"],
                    ["t" => "Логическое", "n" => "has_not_sync_nom"],
                    ["t" => "Логическое", "n" => "init_selected_nom_set_data"],
                    ["t" => "Логическое", "n" => "new_list"],
                    ["t" => "Логическое", "n" => "set_category_in_totals"],
                    ["t" => "Логическое", "n" => "wasaby_dn_list"],
                    ["t" => "Число целое", "n" => "Наряд"],
                    ["t" => "Строка", "n" => "Раздел"],
                    ["t" => "Число целое", "n" => "СкладскойДокумент"],
                    ["t" => "Логическое", "n" => "ЭтоНовыйКомпонентТЧ"],
                    ["t" => "Логическое", "n" => "ЭтоСписокРабот"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    true,
                    5000,
                    0
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "ЕстьЕще"],
                    ["t" => "Число целое", "n" => "РазмерСтраницы"],
                    ["t" => "Число целое", "n" => "Страница"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];
        $response = $this->send('Наряд.DocNomListForUser', $params);
        if (!$response->ok()) {
            Log::info('Sbis Наряд.DocNomListForUser - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Поиск реквизитов по инн
    public function searchRequisitesByInn($inn)
    {
        $this->setApiUrl('https://api.sbis.ru/spp-rest-api/service/');
        $params = [
            'ogrn' => null,
            'inn' => $inn,
        ];
        $response = $this->send('SppAPI.Requisites', $params);
        if (!$response->ok()) {
            Log::info('SppAPI.Requisites - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        //dd($json);
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return $json['result'];
        }
        return false;
    }

    // Проверка прав доступа
    private function checkSbisPermissions($response)
    {
        if (
            !empty($response->object()->error->message) &&
            (
                strpos($response->object()->error->message, 'аточно прав') !== false ||
                strpos($response->object()->error->message, 'запрещен, согласно настройке прав') !== false
            )
        ) {
            throw new \ErrorException(self::SBIS_PERMISSIONS_ERROR);
        }
        if (!empty($response->object()->error) && $response->object()->error === self::SBIS_NOTOKEN_ERROR) {
            throw new \ErrorException(self::SBIS_NOTOKEN_ERROR);
        }
    }

    // Список контактов клиента
    public function getClientContactList($clientId, $sppId)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    ["phone_score"],
                    2,
                    $clientId,
                    $sppId,
                    []
                ],
                "s" => [
                    [
                        "t" => [
                            "n" => "Массив",
                            "t" => "Строка"
                        ],
                        "n" => "AdditionalData"
                    ],
                    ["t" => "Число целое", "n" => "AssigneeContactsLimit"],
                    ["t" => "Число целое", "n" => "ClientID"],
                    ["t" => "Число целое", "n" => "SPPID"],
                    [
                        "t" => [
                            "n" => "Массив",
                            "t" => "Строка"
                        ],
                        "n" => "Types"
                    ]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    "forward",
                    true,
                    5000,
                    null
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Запись", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];

        $response = $this->send('ClientContact.List', $params);

        if (!$response->ok()) {
            Log::info('Sbis ClientContact.List - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Получение списка проектов
    public function getProjectList($folderId = null)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    $folderId,
                    "Раздел",
                    true,
                    "Проект.ПорНомер",
                    false,
                    "С узлами и листьями",
                    "ДокументРасширение.Название",
                    true,
                    true,
                    "Без разворота",
                    true,
                    -1,
                    null,
                    [],
                    null
                ],
                "s" => [
                    ["t" => "Число целое", "n" => "Раздел"],
                    ["t" => "Строка", "n" => "HierarchyField"],
                    ["t" => "Логическое", "n" => "SchedulingObject"],
                    ["t" => "Строка", "n" => "__ПорНомер__"],
                    ["t" => "Логическое", "n" => "_ЕстьДочерние"],
                    ["t" => "Строка", "n" => "ВидДерева"],
                    ["t" => "Строка", "n" => "ЗаголовокИерархии"],
                    ["t" => "Логическое", "n" => "ПозиционированиеПоОтветственному"],
                    ["t" => "Логическое", "n" => "ПутьКУзлу"],
                    ["t" => "Строка", "n" => "Разворот"],
                    ["t" => "Логическое", "n" => "РазделитьМоиПроекты"],
                    ["t" => "Число целое", "n" => "ФильтрДокументНашаОрганизация"],
                    ["t" => "Строка", "n" => "ФильтрЛицо2"],
                    [
                        "t" => [
                            "n" => "Массив",
                            "t" => "Строка"
                        ],
                        "n" => "ФильтрОтветственноеЛицо"
                    ],
                    ["t" => "Строка", "n" => "ФильтрУдаленные"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    true,
                    1000,
                    0
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "ЕстьЕще"],
                    ["t" => "Число целое", "n" => "РазмерСтраницы"],
                    ["t" => "Число целое", "n" => "Страница"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];

        $response = $this->send('Проект.СписокРу', $params);

        if (!$response->ok()) {
            Log::info('Sbis Проект.СписокРу - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Прочитать проект
    public function getProject($projectId)
    {
        $params = [
            "ИдО" => $projectId . '',
            "ИмяМетода" => "Проект.Список",
            "ДопПоля" => [
                "ИмяОбъекта" => "Проект"
            ]
        ];

        $response = $this->send('Проект.ПрочитатьДляУчастника', $params);

        if (!$response->ok()) {
            Log::info(
                'Sbis Проект.ПрочитатьДляУчастника - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE)
            );
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Прочитать этап
    public function getProjectStage($projectId, $stageId)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    false,
                    "Название",
                    $projectId,
                    $projectId,
                    true,
                    $stageId,
                    true
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "ДляПечати"],
                    ["t" => "Строка", "n" => "ЗаголовокИерархии"],
                    ["t" => "Число целое", "n" => "ИдПроекта"],
                    ["t" => "Число целое", "n" => "КорневойРаздел"],
                    ["t" => "Логическое", "n" => "ПутьКУзлу"],
                    ["t" => "Число целое", "n" => "Раздел"],
                    ["t" => "Логическое", "n" => "ФильтрБезРазделаПроекта"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => null,
            "ДопПоля" => []
        ];

        $response = $this->send('Этап.ЭтапыПроектаList', $params);

        if (!$response->ok()) {
            Log::info('Sbis Этап.ЭтапыПроектаList - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Получение списка вех
    public function getMilestonesList()
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    true,
                    true,
                    true,
                    "Только листья",
                    -1,
                    now()->addYears(1)->format('Y') . "-01-01",
                    "2021-01-01",
                    [],
                    "0,1,2,3,4,5"
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "iterative_list"],
                    ["t" => "Логическое", "n" => "new_navigation"],
                    ["t" => "Логическое", "n" => "position_on_today"],
                    ["t" => "Строка", "n" => "ВидДерева"],
                    ["t" => "Число целое", "n" => "Раздел"],
                    ["t" => "Строка", "n" => "ФильтрДатаКнц"],
                    ["t" => "Строка", "n" => "ФильтрДатаНач"],
                    [
                        "t" => [
                            "n" => "Массив",
                            "t" => "Строка"
                        ],
                        "n" => "ФильтрОтветственный"
                    ],
                    ["t" => "Строка", "n" => "ФильтрСостояниеВехи"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => [
                "d" => [
                    [
                        false,
                        "Дата",
                        true
                    ]
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "l"],
                    ["t" => "Строка", "n" => "n"],
                    ["t" => "Логическое", "n" => "o"]
                ],
                "_type" => "recordset",
                "f" => 0
            ],
            "Навигация" => [
                "d" => [
                    "bothways",
                    true,
                    1000,
                    null
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Запись", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];

        $response = $this->send('Веха.СписокВех', $params);

        if (!$response->ok()) {
            Log::info('Sbis Веха.СписокВех - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Получение списка задач
    public function getMilestoneTasksList($milestoneId, $folderId = null, $level = null)
    {
        switch ($level) {
            case 1:
                $levelText = 'ReglsRecs';
                break;
            case 2:
                $levelText = 'RootRecs';
                break;
            case 3:
                $levelText = 'StageRecs';
                break;
        }
        if (!empty($levelText)) {
            $meta = [
                $folderId . '',
                $levelText,
            ];
        } else {
            $meta = null;
        }

        $params = [
            "Фильтр" => [
                "d" => [
                    true,
                    true,
                    true,
                    false,
                    false,
                    $milestoneId,
                    false,
                    $folderId,
                    "Все",
                    [],
                    $meta
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "iterative_list"],
                    ["t" => "Логическое", "n" => "new_navigation"],
                    ["t" => "Логическое", "n" => "position_on_today"],
                    ["t" => "Логическое", "n" => "БезОтделов"],
                    ["t" => "Логическое", "n" => "ЗакончилДокументооборот"],
                    ["t" => "Число целое", "n" => "ИдВерсии"],
                    ["t" => "Логическое", "n" => "ПоказатьПросроченные"],
                    ["t" => "Строка", "n" => "Раздел"],
                    ["t" => "Строка", "n" => "ФильтрКонкретныйИсполнитель"],
                    [
                        "t" => [
                            "n" => "Массив",
                            "t" => "Строка"
                        ],
                        "n" => "ФильтрПометкиИскл"
                    ],
                    [
                        "t" => [
                            "n" => "Массив",
                            "t" => "Строка"
                        ],
                        "n" => "ИдДокумента"
                    ]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    true,
                    1000,
                    0
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "ЕстьЕще"],
                    ["t" => "Число целое", "n" => "РазмерСтраницы"],
                    ["t" => "Число целое", "n" => "Страница"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];

        $response = $this->send('ВыпускВерсии.Состояние', $params);

        if (!$response->ok()) {
            Log::info('Sbis ВыпускВерсии.Состояние - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Список задач проекта
    public function getProjectTasks($projectId)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    true,
                    true,
                    true,
                    0,
                    $projectId,
                    [],
                    [
                        -1
                    ]
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "iterative_list"],
                    ["t" => "Логическое", "n" => "new_navigation"],
                    ["t" => "Логическое", "n" => "position_on_today"],
                    ["t" => "Число целое", "n" => "ЗадачиКоманды"],
                    ["t" => "Число целое", "n" => "ИдПроекта"],
                    [
                        "t" => [
                            "n" => "Массив",
                            "t" => "Строка"
                        ],
                        "n" => "ФильтрИсполнитель"
                    ],
                    [
                        "t" => [
                            "n" => "Массив",
                            "t" => "Число целое"
                        ],
                        "n" => "ФильтрПоСостояниюДО"
                    ]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    true,
                    1000,
                    0
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "ЕстьЕще"],
                    ["t" => "Число целое", "n" => "РазмерСтраницы"],
                    ["t" => "Число целое", "n" => "Страница"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];

        $response = $this->send('Проект.СписокАктивныхЗадач', $params);

        if (!$response->ok()) {
            Log::info('Sbis Проект.СписокАктивныхЗадач - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Получение задачи
    public function getTask($id)
    {
        $params = [
            "ИдО" => $id . '',
            "ИмяМетода" => "СлужЗап.Список",
            "ДопПоля" => [
                "ИмяОбъекта" => "СлужЗап"
            ]
        ];
        $response = $this->send('СлужЗап.ПрочитатьДляУчастника', $params);

        if (!$response->ok()) {
            Log::info(
                'Sbis СлужЗап.ПрочитатьДляУчастника - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE)
            );
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Получение связанных документов
    public function getTaskLinkedDocs($id, $docType)
    {
        $params = [
            "ИдО" => $id . '',
            "Фильтр" => [
                "d" => [
                    "up",
                    [],
                    [],
                    $docType,
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Route"],
                    ["t" => ["n" => "Массив", "t" => "Число целое"], "n" => "ВидСвязиВкл"],
                    ["t" => ["n" => "Массив", "t" => "Число целое"], "n" => "ВидСвязиИскл"],
                    ["t" => "Строка", "n" => "ИмяОбъекта"],
                ],
                "_type" => "record",
                "f" => 0
            ]
        ];

        $response = $this->send('Документ.ПолучитьИерархиюСвязанныхДокументов', $params);

        if (!$response->ok()) {
            Log::info(
                'Sbis Документ.ПолучитьИерархиюСвязанныхДокументов - ' . json_encode(
                    $response->object(),
                    JSON_UNESCAPED_UNICODE
                )
            );
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Получить список событий лида (плановые контакты)
    public function getLeadCrmEvents($leadId)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    null,
                    false,
                    false,
                    null,
                    false,
                    null,
                    true,
                    null,
                    null,
                    true,
                    true,
                    false,
                    "online",
                    $leadId,
                    null,
                    "",
                    null,
                    null,
                    null
                ],
                "s" => [
                    ["t" => "Строка", "n" => "NoNameCard"],
                    ["t" => "Логическое", "n" => "OnlyCRM"],
                    ["t" => "Логическое", "n" => "WithoutCRM"],
                    ["t" => "Строка", "n" => "call_dtime"],
                    ["t" => "Логическое", "n" => "isFakeClient"],
                    ["t" => "Строка", "n" => "is_phone_card"],
                    ["t" => "Логическое", "n" => "iterative_list"],
                    ["t" => "Строка", "n" => "link_data"],
                    ["t" => "Строка", "n" => "phone"],
                    ["t" => "Логическое", "n" => "position_on_today"],
                    ["t" => "Логическое", "n" => "rp_doc"],
                    ["t" => "Логическое", "n" => "showClientTitle"],
                    ["t" => "Строка", "n" => "ИмяМетода"],
                    ["t" => "Число целое", "n" => "Лид"],
                    ["t" => "Число целое", "n" => "Регламент"],
                    ["t" => "Строка", "n" => "СтрокаПоиска"],
                    ["t" => "Строка", "n" => "Тип"],
                    ["t" => "Строка", "n" => "ТипКонтрагента"],
                    ["t" => "Число целое", "n" => "ФильтрЛицо1"]
                ],
                "_type" => "record"
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    "forward",
                    true,
                    1000,
                    null
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Строка", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];
        $response = $this->send('CRMEvent.listWithDocumentsEDO', $params);

        if (!$response->ok()) {
            Log::info(
                'Sbis CRMEvent.listWithDocumentsEDO - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE)
            );
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    public function getCrmTheme($themeId, $themeUUID)
    {
        $params = [
            "ID" => $themeId,
            "UUID" => $themeUUID,
            "Parameters" => null
        ];
        $response = $this->send('CRMTheme.ReadTheme', $params);

        if (!$response->ok()) {
            Log::info('Sbis CRMTheme.ReadTheme - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Получить характеристики номенклатуры
    public function getNomenclatureCharachteristic($idNom)
    {
        $params = [
            "ИдО" => $idNom,
            "ИмяМетода" => "Номенклатура.FormatCard"
        ];
        $response = $this->send('Nomenclature.NomenclatureRead', $params);
        if (!$response->ok()) {
            Log::info(
                'Sbis Nomenclature.NomenclatureRead - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE)
            );
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Получить движение номенклатуры
    public function movementsByNomenclature($id)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    1,
                    "ДвиженияПоНоменклатуре.ПолучитьДанныеБезКэширования",
                    false,
                    10000,
                    true,
                    null,
                    null,
                    "ВсеДвижения",
                    [$id],
                    [null, null],
                    []
                ],
                "s" => [
                    ["t" => "Число целое", "n" => "PrefetchLevels"],
                    ["t" => "Строка", "n" => "PrefetchMethod"],
                    ["t" => "Логическое", "n" => "PrefetchOnlyCache"],
                    ["t" => "Число целое", "n" => "PrefetchPages"],
                    ["t" => "Логическое", "n" => "ГлобальныйФильтр"],
                    ["t" => "Строка", "n" => "ПериодКонец"],
                    ["t" => "Строка", "n" => "ПериодНачало"],
                    ["t" => "Строка", "n" => "ФильтрАналитика"],
                    ["t" => ["n" => "Массив", "t" => "Число целое"], "n" => "ФильтрНоменклатура"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "ФильтрПериод"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "ФильтрСклад"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    "forward",
                    true,
                    50,
                    [
                        "d" => [
                            "",
                            0,
                            "2100-01-01"
                        ],
                        "s" => [
                            ["t" => "Строка", "n" => "Id"],
                            ["t" => "Число целое", "n" => "RowCount"],
                            ["t" => "Строка", "n" => "ДатаДвижения"]
                        ],
                        "_type" => "record",
                        "f" => 1
                    ]
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Запись", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];
        $response = $this->send('ДвиженияПоНоменклатуре.ПолучитьДанные', $params);

        if (!$response->ok()) {
            Log::info(
                'Sbis ДвиженияПоНоменклатуре.ПолучитьДанные - ' . json_encode(
                    $response->object(),
                    JSON_UNESCAPED_UNICODE
                )
            );
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Создать документ пересортица
    public function substitution()
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    false,
                    false,
                    [],
                    true,
                    [
                        "binding" => [
                            "destroy" => "Документ.УдалитьДокументы",
                            "format" => "Пересорт.ListLite",
                            "read" => "ПрочитатьДляУчастника"
                        ],
                        "endpoint" => [
                            "contract" => "Пересорт"
                        ],
                        "keyProperty" => "@Документ",
                        "readMetaData" => [
                            "ИмяОбъекта" => "Пересорт"
                        ]
                    ],
                    "WHD/Docs/Resorting/dialog:Dialog",
                    "70475301-966a-4ac1-8884-bfccd05c58ad",
                    true,
                    "1386b2d6-740b-4f3b-9f71-2e7bae698053",
                    null,
                    144578,
                    "Пересортица",
                    null,
                    240,
                    "Warehouse/API/DialogAsOpener",
                    "Пересорт",
                    "Пересортица",
                    "Пересорт",
                    "Пересорт",
                    null,
                    date("Y-m-d"),
                    date("H:i:s")
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "allowAdaptive"],
                    ["t" => "Логическое", "n" => "allowSwitchMode"],
                    ["t" => "JSON-объект", "n" => "cacheData"],
                    ["t" => "Логическое", "n" => "createWithCache"],
                    ["t" => "JSON-объект", "n" => "source"],
                    ["t" => "Строка", "n" => "template"],
                    ["t" => "Строка", "n" => "ВизуальноеПредставление"],
                    ["t" => "Логическое", "n" => "ВызовИзБраузера"],
                    ["t" => "Строка", "n" => "ИдРегламента"],
                    ["t" => "Число целое", "n" => "ЛицоСоздал"],
                    ["t" => "Число целое", "n" => "Регламент"],
                    ["t" => "Строка", "n" => "РегламентНазвание"],
                    ["t" => "Число целое", "n" => "Сотрудник"],
                    ["t" => "Число целое", "n" => "ТипДокумента"],
                    ["t" => "Строка", "n" => "ТипДокумента.ИмяДиалога"],
                    ["t" => "Строка", "n" => "ТипДокумента.ИмяОбъекта"],
                    ["t" => "Строка", "n" => "ТипДокумента.НазваниеКраткое"],
                    ["t" => "Строка", "n" => "ТипДокумента.ПодТипДокумента"],
                    ["t" => "Строка", "n" => "ТипДокумента.ТипДокумента"],
                    ["t" => "Строка", "n" => "ФильтрРегламент"],
                    ["t" => "Дата", "n" => "Дата"],
                    ["t" => "Время", "n" => "Время"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ИмяМетода" => "Пересорт.ListLite"
        ];
        $response = $this->send('Пересорт.Создать', $params);
        if (!$response->ok()) {
            Log::info('Sbis Пересорт.Создать - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Сохранить документ пересорт
    public function substitutionSave($idDoc)
    {
        $params = [
            "Запись" => [
                "d" => [
                    $idDoc,
                    null
                ],
                "s" => [
                    ["t" => "Число целое", "n" => "@Документ"],
                    ["t" => "Строка", "n" => "ДокументРасширение.Название"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => [
                "ИмяОбъекта" => "Пересорт"
            ]
        ];
        $response = $this->send('Пересорт.Записать', $params);
        if (!$response->ok()) {
            Log::info('Пересорт.Записать - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Список складов
    public function getWarehouses()
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    "current",
                    [
                        "Name"
                    ],
                    null,
                    null,
                    null,
                    true,
                    [
                        -1,
                        -2
                    ],
                    "-2",
                    [
                        "Warehouse"
                    ],
                    -1
                ],
                "s" => [
                    ["t" => "Строка", "n" => "BalanceRegistry"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "Fields"],
                    ["t" => "Строка", "n" => "Folder"],
                    ["t" => "Строка", "n" => "FolderCompilation"],
                    ["t" => "Строка", "n" => "GetColumnsFromSettings"],
                    ["t" => "Логическое", "n" => "GetPath"],
                    ["t" => ["n" => "Массив", "t" => "Число целое"], "n" => "Ids"],
                    ["t" => "Строка", "n" => "OurCompany"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "ScopesAreas"],
                    ["t" => "Число целое", "n" => "ServiceFolder"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    true,
                    99,
                    0
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "ЕстьЕще"],
                    ["t" => "Число целое", "n" => "РазмерСтраницы"],
                    ["t" => "Число целое", "n" => "Страница"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => [
                "Name",
                "BalanceObject",
                "ContainsFolders",
                "IsUsing",
                "Contractor"
            ]
        ];
        $response = $this->send('Warehouse.GetList', $params);
        if (!$response->ok()) {
            Log::info('Warehouse.GetList - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // XML пересортица
    public function addXmlInSubstitution($nomenclatures, $sortingPrefix)
    {
        $urls = [];
        $warehouses = $this->getWarehouses();
        $xml_filename = 'ON_PeReSort_1841007528184101001_' . date('Ymd') . '_' . Str::uuid();
        foreach ($nomenclatures as $key => $row) {
            $nomenclature = '';
            $i = 1;
            $needWarehouses = 0;
            foreach ($warehouses as $warehouse) {
                if ($warehouse['Id'] == $key) {
                    $needWarehouses = $warehouse['Name'];
                }
            }
            foreach ($row as $val) {
                if ($val['volume'] <= 0) {
                    continue;
                }
                $nomenclature .= '<СтрТабл ЕдИзм="' . $val['unitMeasurement'] . '" КодСпис="' . $val['nomenclature_code'] . '" КолВо="' . $val['volume'] . '" ПорНомер="' . $i . '" СписНаим="' . $val['name'] . '">
                    <ОпрСтрТабл КодОприход="" ОприходНаим=" ' . $sortingPrefix . ' ' . $val['name'] . '"/>
                  </СтрТабл>';
                $i++;
            }
            $xml = '<?xml version="1.0" encoding="WINDOWS-1251" ?>
            <Файл ВерсияФормата="3.02" Формат="Пересорт">

              <Документ Дата="' . date('d.m.Y') . '" Дополнение="Создано автоматически" Номер="1">
                <ТаблДок>
                  <ИтогТабл Сумма=""/>
                   ' . $nomenclature . '
                </ТаблДок>
                <Организация Название="">
                  <СвЮЛ ИНН="" КПП="" Название="" ОКПО=""/>
                  <Представители>
                    <Ответственный>
                      <ФИО Имя="api"/>
                    </Ответственный>
                  </Представители>
                  <Склад Название="' . $needWarehouses . '"/>
                </Организация>
                <ИнфПол>
                  <ТекстИнф Значен="Создано автоматически" Идентиф="Примечание"/>
                  <ТекстИнф Значен="Создано автоматически" Идентиф="ИнфПередТабл"/>
                  <ТекстИнф Значен="' . date("d.m.Y H:i:s") . '" Идентиф="ДатаГен"/>
                </ИнфПол>
              </Документ>

            </Файл>

            ';
            $res = $this->substitution();
            $this->substitutionSave($res['@Документ']);
            $urls[] = $res['ИдентификаторДокумента'];
            $params = [
                "Документ" => [
                    "Идентификатор" => $res['ДокументРасширение.ИдентификаторВИ'],
                    'Вложение' => [
                        'Название' => 'Пересортица ' . date('d.m.Y'),
                        'Дата' => date('d.m.Y'),
                        'Файл' => [
                            'Имя' => $xml_filename . '.xml',
                            'ДвоичныеДанные' => base64_encode(iconv("UTF-8", "windows-1251", $xml)),
                        ],
                    ]
                ]
            ];
            $response = $this->send('СБИС.ЗаписатьВложение', $params);
        }

        if (!$response->ok()) {
            Log::info('Sbis СБИС.ЗаписатьВложение - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        return $urls;
    }

    // Список документов инвенторизации
    public function getInventoryList()
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    true,
                    true,
                    true,
                    true,
                    "АктИнв",
                    "АктИнв",
                    true,
                    null,
                    null,
                    null,
                    "-2",
                    []
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "iterative_list"],
                    ["t" => "Логическое", "n" => "new_navigation"],
                    ["t" => "Логическое", "n" => "position_on_today"],
                    ["t" => "Логическое", "n" => "rp_doc"],
                    ["t" => "Строка", "n" => "НазваниеПодТипаДокумента"],
                    ["t" => "Строка", "n" => "НазваниеТипаДокумента"],
                    ["t" => "Логическое", "n" => "УчитыватьИерархиюНО"],
                    ["t" => "Строка", "n" => "ФильтрДатаП"],
                    ["t" => "Строка", "n" => "ФильтрДатаС"],
                    ["t" => "Строка", "n" => "ФильтрИзмененные"],
                    ["t" => "Строка", "n" => "ФильтрОрганизацияФилиал"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "ФильтрПометкиИскл"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    "bothways",
                    true,
                    50,
                    null
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Строка", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];
        $response = $this->send('АктИнв.СписокХраним', $params);
        if (!$response->ok()) {
            Log::info('АктИнв.СписокХраним - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Прочитать документ инвенторизации
    public function getInventory($idDoc, $idLastNomenclature = 0)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    true,
                    true,
                    false,
                    true,
                    true,
                    true,
                    true,
                    true,
                    "WHDCore/Controls/nomList =>columns-InventoryMainList-v1",
                    true,
                    true,
                    "Все наименования",
                    $idDoc
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "calc_ext_params"],
                    ["t" => "Логическое", "n" => "calc_full_sn_totals"],
                    ["t" => "Логическое", "n" => "calc_mu_localization"],
                    ["t" => "Логическое", "n" => "calc_nom_composition"],
                    ["t" => "Логическое", "n" => "calc_serial_number"],
                    ["t" => "Логическое", "n" => "calc_serial_number_inv"],
                    ["t" => "Логическое", "n" => "calc_sn_accounting"],
                    ["t" => "Логическое", "n" => "calc_totals"],
                    ["t" => "Строка", "n" => "column_config_id"],
                    ["t" => "Логическое", "n" => "init_characteristics"],
                    ["t" => "Логическое", "n" => "new_list"],
                    ["t" => "Строка", "n" => "Наименования"],
                    ["t" => "Число целое", "n" => "СкладскойДокумент"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    "forward",
                    true,
                    50,
                    [
                        "d" => [$idLastNomenclature],
                        "s" => [["t" => "Число целое", "n" => "@НоменклатураДокумента"]],
                        "_type" => "record",
                        "f" => 1
                    ]
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Запись", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];
        $response = $this->send('АктИнв.DocNomListForUser', $params);
        if (!$response->ok()) {
            Log::info('АктИнв.DocNomListForUser - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return $json['result'];
        }
        return false;
    }

    // Создать документ инвентаризации
    public function creatInventoryDoc()
    {
        $params = [

            "Фильтр" => [
                "d" => [
                    false,
                    false,
                    [],
                    true,
                    [
                        "binding" => [
                            "destroy" => "Документ.УдалитьДокументы",
                            "format" => "АктИнв.ListLite",
                            "read" => "ПрочитатьДляУчастника"
                        ],
                        "endpoint" => [
                            "contract" => "АктИнв"
                        ],
                        "keyProperty" => "@Документ",
                        "readMetaData" => [
                            "ИмяОбъекта" => "АктИнв"
                        ]
                    ],
                    "WHD/Docs/Inventory/dialog => Dialog",
                    "7482feca-7392-4335-b679-ad353e321986",
                    true,
                    "abb543d0-d6af-11e4-b5c4-00505691577e",
                    null,             //ЛицоСоздал
                    122087,
                    "Инвентаризация",
                    null,             //Сотрудник
                    218,
                    "Warehouse/API/DialogAsOpener",
                    "АктИнв",
                    "Инвентаризация",
                    "АктИнв",
                    "АктИнв",
                    null,
                    date("Y-m-d"),
                    date("H:i:s")
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "allowAdaptive"],
                    ["t" => "Логическое", "n" => "allowSwitchMode"],
                    ["t" => "JSON-объект", "n" => "cacheData"],
                    ["t" => "Логическое", "n" => "createWithCache"],
                    ["t" => "JSON-объект", "n" => "source"],
                    ["t" => "Строка", "n" => "template"],
                    ["t" => "Строка", "n" => "ВизуальноеПредставление"],
                    ["t" => "Логическое", "n" => "ВызовИзБраузера"],
                    ["t" => "Строка", "n" => "ИдРегламента"],
                    ["t" => "Число целое", "n" => "ЛицоСоздал"],
                    ["t" => "Число целое", "n" => "Регламент"],
                    ["t" => "Строка", "n" => "РегламентНазвание"],
                    ["t" => "Число целое", "n" => "Сотрудник"],
                    ["t" => "Число целое", "n" => "ТипДокумента"],
                    ["t" => "Строка", "n" => "ТипДокумента.ИмяДиалога"],
                    ["t" => "Строка", "n" => "ТипДокумента.ИмяОбъекта"],
                    ["t" => "Строка", "n" => "ТипДокумента.НазваниеКраткое"],
                    ["t" => "Строка", "n" => "ТипДокумента.ПодТипДокумента"],
                    ["t" => "Строка", "n" => "ТипДокумента.ТипДокумента"],
                    ["t" => "Строка", "n" => "ФильтрРегламент"],
                    ["t" => "Дата", "n" => "Дата"],
                    ["t" => "Время", "n" => "Время"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ИмяМетода" => "АктИнв.ListLite"
        ];
        $response = $this->send('АктИнв.Создать', $params);
        if (!$response->ok()) {
            Log::info('АктИнв.Создать - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // excel инвентаризаця официальный метод
    public function addExcelInInventoryOff($xls, $inn = null, $kpp = null)
    {
        if (empty($inn)) {
            $getInfoOrg = $this->getPointSale();
            $inn = $getInfoOrg[0]['ИНН'];
            $kpp = $getInfoOrg[0]['КПП'];
        }

        $params = [
            "Документ" => [
                "Тип" => "АктИнв",
                'НашаОрганизация' => [
                    'СвЮЛ' => [
                        'ИНН' => $inn,
                        'КПП' => $kpp,
                    ],
                ],
                'Вложение' => [
                    'Файл' => [
                        'Имя' => 'Инвентаризация.xls',
                        'ДвоичныеДанные' => $xls,
                    ],
                ],
            ],

        ];
        $response = $this->send('СБИС.ЗаписатьДокумент', $params);

        if (!$response->ok()) {
            Log::info('СБИС.ЗаписатьДокумент - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Список кодов для документов инвенторизации
    public function getMarkingInventoryNom($doc)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    $doc,
                    true,
                    null,
                    true,
                    false,
                    false
                ],
                "s" => [
                    ["t" => "Число целое", "n" => "DocumentNomenclature"],
                    ["t" => "Логическое", "n" => "PackGrouping"],
                    ["t" => "Строка", "n" => "Parent"],
                    ["t" => "Логическое", "n" => "WasabyList"],
                    ["t" => "Логическое", "n" => "WithExtIndication"],
                    ["t" => "Логическое", "n" => "WithPieceMarkIndication"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    "forward",
                    true,
                    99,
                    [
                        "d" => [0],
                        "s" => [["t" => "Число целое", "n" => "@SerialNumber"]],
                        "_type" => "record",
                        "f" => 1
                    ]
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Запись", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];
        $response = $this->send('SerialNumber.CustomList', $params);
        if (!$response->ok()) {
            Log::info('SerialNumber.CustomList - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Отчет о потребностях - старт формирования отчета
    public function getPrintingTemplateControllerPrepareForPrint(
        $modalOperationToken,
        $filterDepthOfAnalysis,
        $filterOrderForAPeriod
    )
    {
        $params = [
            "Data" => [
                "_type" => "record",
                "d" => [
                    "СкладОтч_Запас",
                    [
                        "d" => [
                            [
                                "Номенклатура.Название детализации",
                                "Номенклатура.Единица измерения",
                                "Номенклатура.Продажи в день",
                                "Номенклатура.Количество",
                                "Номенклатура.Запасы минимальные",
                                "Номенклатура.Запасы остаток",
                                "Номенклатура.Запасы максимальные",
                                "Номенклатура.Запасы ожидается",
                                "Номенклатура.Запасы в днях",
                                "Номенклатура.Заказанное количество",
                                "Номенклатура.Сумма заказанного количества",
                                "Номенклатура.Код номенклатуры",
                                "Номенклатура.Артикул",
                                "Номенклатура.Штрихкод",
                                "Номенклатура.Раздел",
                                "ТипНоменклатуры.Категория",
                                "Номенклатура.Запасы резерв",
                                "Номенклатура.Список кодов",
                                "Номенклатура.Список кодов.Тип",
                                "Номенклатура.Список кодов.Код"
                            ],
                            50010,
                            [],
                            null,
                            [
                                "_type" => "recordset",
                                "d" => [],
                                "s" => [
                                    [
                                        "n" => "l",
                                        "t" => "Логическое"
                                    ],
                                    [
                                        "n" => "n",
                                        "t" => "Строка"
                                    ],
                                    [
                                        "n" => "o",
                                        "t" => "Логическое"
                                    ]
                                ]
                            ],
                            [
                                "d" => [
                                    "1",
                                    //PrefetchLevels
                                    "АнализЗапасов.ПолучитьДанныеБезКэширования",
                                    //PrefetchMethod
                                    333,
                                    //PrefetchPages
                                    "P30DT0H0M0S",
                                    //PrefetchSessionLiveTime
                                    $modalOperationToken,
                                    //modalOperationToken
                                    true,
                                    //pathEnable
                                    "Warehouse_Stats/VDWarehouseReports/WarehouseReport/Reports/BalancesAnalytic/BalancesAnalyticReport =>openNewWindow",
                                    //resultMethod
                                    [
                                        "Склад",
                                        //детализация по складу
                                        "Номенклатура"
                                        //Детализация по номенклатуре
                                    ],
                                    true,
                                    //ИерархияНоменклатура
                                    null,
                                    //ПериодКонец
                                    null,
                                    //ПериодНачало
                                    "С разворотом",
                                    //Разворот
                                    null,
                                    //Раздел
                                    $filterDepthOfAnalysis,
                                    //ФильтрГлубинаАнализа
                                    $filterOrderForAPeriod,
                                    //ФильтрЗаказНаПериод
                                    [],
                                    //ФильтрНоменклатура
                                    true,
                                    //ФильтрПечать
                                    [],
                                    //ФильтрСклад
                                    "1"
                                    //ФильтрУчетРезерв
                                ],
                                "s" => [
                                    [
                                        "t" => "Строка",
                                        "n" => "PrefetchLevels"
                                    ],
                                    [
                                        "t" => "Строка",
                                        "n" => "PrefetchMethod"
                                    ],
                                    [
                                        "t" => "Число целое",
                                        "n" => "PrefetchPages"
                                    ],
                                    [
                                        "t" => "Строка",
                                        "n" => "PrefetchSessionLiveTime"
                                    ],
                                    [
                                        "t" => "Строка",
                                        "n" => "modalOperationToken"
                                    ],
                                    [
                                        "t" => "Логическое",
                                        "n" => "pathEnable"
                                    ],
                                    [
                                        "t" => "Строка",
                                        "n" => "resultMethod"
                                    ],
                                    [
                                        "t" => [
                                            "n" => "Массив",
                                            "t" => "Строка"
                                        ],
                                        "n" => "Детализация"
                                    ],
                                    [
                                        "t" => "Логическое",
                                        "n" => "ИерархияНоменклатура"
                                    ],
                                    [
                                        "t" => "Строка",
                                        "n" => "ПериодКонец"
                                    ],
                                    [
                                        "t" => "Строка",
                                        "n" => "ПериодНачало"
                                    ],
                                    [
                                        "t" => "Строка",
                                        "n" => "Разворот"
                                    ],
                                    [
                                        "t" => "Строка",
                                        "n" => "Раздел"
                                    ],
                                    [
                                        "t" => "Строка",
                                        "n" => "ФильтрГлубинаАнализа"
                                    ],
                                    [
                                        "t" => "Число целое",
                                        "n" => "ФильтрЗаказНаПериод"
                                    ],
                                    [
                                        "t" => [
                                            "n" => "Массив",
                                            "t" => "Строка"
                                        ],
                                        "n" => "ФильтрНоменклатура"
                                    ],
                                    [
                                        "t" => "Логическое",
                                        "n" => "ФильтрПечать"
                                    ],
                                    [
                                        "t" => [
                                            "n" => "Массив",
                                            "t" => "Строка"
                                        ],
                                        "n" => "ФильтрСклад"
                                    ],
                                    [
                                        "t" => "Строка",
                                        "n" => "ФильтрУчетРезерв"
                                    ]
                                ],
                                "_type" => "record"
                            ]
                        ],
                        "s" => [
                            [
                                "t" => [
                                    "n" => "Массив",
                                    "t" => "Строка"
                                ],
                                "n" => "__LIST_PROPERTIES_SELECTED_COLUMNS"
                            ],
                            [
                                "t" => "Число целое",
                                "n" => "__MAX_DATA_ROWS_COUNT"
                            ],
                            [
                                "t" => [
                                    "n" => "Массив",
                                    "t" => "Строка"
                                ],
                                "n" => "ДопПоля"
                            ],
                            [
                                "t" => "Строка",
                                "n" => "Навигация"
                            ],
                            [
                                "t" => "Выборка",
                                "n" => "Сортировка"
                            ],
                            [
                                "t" => "Запись",
                                "n" => "Фильтр"
                            ]
                        ],
                        "_type" => "record"
                    ],
                    [
                        "-1"
                    ],
                    null,
                    "89d62ad3-240b-47a1-ac2a-2f1c77b8d96e"
                ],
                "s" => [
                    [
                        "t" => "Строка",
                        "n" => "Endpoint"
                    ],
                    [
                        "t" => "Запись",
                        "n" => "ExactValues"
                    ],
                    [
                        "t" => [
                            "n" => "Массив",
                            "t" => "Строка"
                        ],
                        "n" => "Instances"
                    ],
                    [
                        "t" => "UUID",
                        "n" => "Source"
                    ],
                    [
                        "t" => "UUID",
                        "n" => "Template"
                    ]
                ]
            ],
            "Options" => [
                "d" => [""],
                "s" => [
                    [
                        "t" => "Строка",
                        "n" => "Description"
                    ]
                ],
                "_type" => "record"
            ]
        ];

        $response = $this->send('PrintingTemplateController.PrepareForPrint', $params);

        if (!$response->ok()) {
            Log::info(
                'Sbis PrintingTemplateController.PrepareForPrint - ' . json_encode(
                    $response->object(),
                    JSON_UNESCAPED_UNICODE
                )
            );
            $response->throw();
        }

        $json = $response->json();
        if ($json['result'] === null) {
            return true;
        }
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Отчет о потребностях - проверка готовности отчета
    public function getLongRequestsList($appId)
    {
        $this->setApiUrl('https://retail.sbis.ru/long-requests/service/');

        $params = [
            "Фильтр" => [
                "d" => [$appId],
                "s" => [
                    [
                        "t" => "Число целое",
                        "n" => "UserId"
                    ]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => [
                "d" => [
                    [
                        true,
                        "StartTime",
                        false
                    ],
                    [
                        false,
                        "State",
                        true
                    ]
                ],
                "s" => [
                    [
                        "t" => "Логическое",
                        "n" => "l"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "n"
                    ],
                    [
                        "t" => "Логическое",
                        "n" => "o"
                    ]
                ],
                "_type" => "recordset",
                "f" => 0
            ],
            "Навигация" => [
                "d" => [
                    true,
                    20,
                    0
                ],
                "s" => [
                    [
                        "t" => "Логическое",
                        "n" => "ЕстьЕще"
                    ],
                    [
                        "t" => "Число целое",
                        "n" => "РазмерСтраницы"
                    ],
                    [
                        "t" => "Число целое",
                        "n" => "Страница"
                    ]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];

        $response = $this->send('LongRequests.List', $params);

        if (!$response->ok()) {
            Log::info('Sbis LongRequests.List - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Получение PersonProfile.PersonalInfo
    public function getPersonProfilePersonalInfo($uuid)
    {
        $params = [
            'Person' => $uuid,
        ];
        $response = $this->send('PersonProfile.PersonalInfo', $params);
        if (!$response->ok()) {
            Log::info('Sbis PersonProfile.PersonalInfo - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    //PersonProfile.GeneralInfo
    public function getPersonProfileGeneralInfo($uuid)
    {
        $params = [
            'Person' => $uuid,
        ];
        $response = $this->send('PersonProfile.GeneralInfo', $params);
        if (!$response->ok()) {
            Log::info('Sbis PersonProfile.GeneralInfo - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Чтение папок и тегов клиента
    public function getFoldersAndTagsByIDClient($id)
    {
        $params = [
            "ClientIDs" => $id,
        ];
        $response = $this->send('CRMClients.GetFoldersAndTagsByIDClient', $params);

        if (!$response->ok()) {
            Log::info(
                'CRMClients.GetFoldersAndTagsByIDClient - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE)
            );
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Чтение информации о контрагенте
    public function ContractorReaderGet($ids, $columns)
    {
        $params = [
            "Ids" => $ids,
            "Columns" => $columns,
        ];
        $response = $this->send('ContractorReader.Get', $params);

        if (!$response->ok()) {
            Log::info('ContractorReader.Get - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Чтение информации о контрагенте
    public function callCenterGetCallCenterList($parentFolderId = null)
    {
        $parent = null;
        $parentType = "Строка";
        if (!empty($parentFolderId)) {
            $parent = $parentFolderId;
            $parentType = "Число целое";
        }

        $params = [
            "Фильтр" => [
                "d" => [
                    false,
                    $parent
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "composite"],
                    ["t" => $parentType, "n" => "parent"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => null,
            "ДопПоля" => []
        ];
        $response = $this->send('CallCenter.GetCallCenterListByPerson', $params);

        if (!$response->ok()) {
            Log::info(
                'CallCenter.GetCallCenterListByPerson - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE)
            );
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    } // callCenterGetCallCenterList

    // Рабочее время по задаче
    public function getDocWorkTime($taskId)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    true,
                    (string)$taskId,
                    null
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "detailed_view"],
                    ["t" => "Строка", "n" => "Документ"],
                    ["t" => "Строка", "n" => "Раздел"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => null,
            "ДопПоля" => []
        ];
        $response = $this->send('Работа.РабочееВремяПоДокументу', $params);

        if (!$response->ok()) {
            Log::info('Работа.РабочееВремяПоДокументу - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    } // getDocWorkTime

    // Получить прайсы
    public function getPrice($idPrice, $folder = null)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    2,
                    null,
                    true,
                    "cloud",
                    "online",
                    $folder,
                    $folder,
                    -$idPrice,
                    0,
                    null,
                    null,
                    false,
                    [
                        $idPrice
                    ],
                    null,
                    [
                        "Catalog"
                    ],
                    "",
                    [
                        "d" => [
                            true,
                            $idPrice,
                            true
                        ],
                        "s" => [
                            ["t" => "Логическое", "n" => "HideConditionalProduct"],
                            ["t" => "Число целое", "n" => "PriceBase"],
                            ["t" => "Логическое", "n" => "ShowEmptyFolder"]
                        ],
                        "_type" => "record",
                        "f" => 1
                    ],
                    true,
                    date('d.m.Y')
                ],
                "s" => [
                    ["t" => "Число целое", "n" => "Archival"],
                    ["t" => "Строка", "n" => "BalanceForOrganization"],
                    ["t" => "Логическое", "n" => "CheckFolderExists"],
                    ["t" => "Строка", "n" => "DataPriority"],
                    ["t" => "Строка", "n" => "Endpoint"],
                    ["t" => "Строка", "n" => "Folder"],
                    ["t" => "Строка", "n" => "FolderCompilation"],
                    ["t" => "Строка", "n" => "GetColumnsFromSettings"],
                    ["t" => "Число целое", "n" => "GetPath"],
                    ["t" => "Строка", "n" => "NodeType"],
                    ["t" => "Строка", "n" => "PriceList"],
                    ["t" => "Логическое", "n" => "PriceListBasePrice"],
                    ["t" => ["n" => "Массив", "t" => "Число целое"], "n" => "PriceListHierarchy"],
                    ["t" => "Строка", "n" => "PublicationSaleState"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "ScopesAreas"],
                    ["t" => "Строка", "n" => "SearchString"],
                    ["t" => "Запись", "n" => "ServiceOption"],
                    ["t" => "Логическое", "n" => "TranslitSearchString"],
                    ["t" => "Строка", "n" => "Дата"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => [
                "d" => [
                    [
                        true,
                        "Custom",
                        false
                    ]
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "l"],
                    ["t" => "Строка", "n" => "n"],
                    ["t" => "Логическое", "n" => "o"]
                ],
                "_type" => "recordset",
                "f" => 0
            ],
            "Навигация" => [
                "d" => [
                    true,
                    9999,
                    0
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "ЕстьЕще"],
                    ["t" => "Число целое", "n" => "РазмерСтраницы"],
                    ["t" => "Число целое", "n" => "Страница"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => [
                "Name",
                "IsFolderUI",
                "PublicationSaleState",
                "Image",
                "BalanceObject",
                "MUP",
                "ContainsFolders",
                "FolderContents",
                "TypeObject",
                "PriceList",
                "Deleted",
                "DescriptionRaw",
                "ImageObject",
                "PriceOffersAll",
                "Price",
                "Type",
                "DescriptionSimple"
            ]
        ];
        $response = $this->send('PriceListPosition.List', $params);

        if (!$response->ok()) {
            Log::info('PriceListPosition.List - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    } // getDocWorkTime

    // Отправить сообщение в чат по НПС
    public function sendToChatRetail($text, $groupId)
    {
        $params = [
            "Получатели" => [],
            "Текст" => $text,
            "Диалог" => $groupId,
            "Документ" => null,
            "Сообщение" => null,
            "Файлы" => [],
            "Опции" => [
                "d" => [
                    null,
                    null,
                    false,
                    null,
                    true,
                    true,
                    true,
                    false,
                    null,
                    null,
                    null,
                    [],
                    [],
                    null,
                    null,
                    null,
                    [
                        "d" => [],
                        "s" => [
                            ["t" => "UUID", "n" => "Person"],
                            ["t" => "UUID", "n" => "Chanel"],
                            ["t" => "Логическое", "n" => "isHidden"],
                            ["t" => "JSON-объект", "n" => "ChanelExt"]
                        ],
                        "_type" => "recordset",
                        "f" => 1
                    ],
                    null,
                    4,
                    [],
                    null,
                    false,
                    null,
                    $text,
                    false,
                    false,
                    null,
                    null,
                    false,
                    false,
                    false,
                    null
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Папка"],
                    ["t" => "Строка", "n" => "ТипСообщения"],
                    ["t" => "Логическое", "n" => "СлитьСПредыдущим"],
                    ["t" => "Число целое", "n" => "СлитьСПредыдущимПериодПрочтения"],
                    ["t" => "Логическое", "n" => "ВсемУчастникамТемы"],
                    ["t" => "Логическое", "n" => "ВсемУчастникамДокумента"],
                    ["t" => "Логическое", "n" => "ДобавитьВУчастникиЧата"],
                    ["t" => "Логическое", "n" => "БыстрыйОтвет"],
                    ["t" => "Число целое", "n" => "ТипДокумента"],
                    ["t" => "UUID", "n" => "Документ"],
                    ["t" => ["n" => "Массив", "t" => "UUID"], "n" => "ИсключитьПолучателей"],
                    ["t" => "JSON-объект", "n" => "РедакцииФайлов"],
                    ["t" => "JSON-объект", "n" => "ОткрепитьФайлыОтТем"],
                    ["t" => "UUID", "n" => "SenderChanel"],
                    ["t" => "Строка", "n" => "SenderName"],
                    ["t" => "Строка", "n" => "SenderMember"],
                    ["t" => "Выборка", "n" => "Recipients"],
                    ["t" => ["n" => "Массив", "t" => "UUID"], "n" => "Теги"],
                    ["t" => "Число целое", "n" => "TextFormat"],
                    ["t" => "JSON-объект", "n" => "МассоваяРассылка"],
                    ["t" => "Строка", "n" => "message_id"],
                    ["t" => "Логическое", "n" => "ПодклеитьИсториюВП"],
                    ["t" => "Строка", "n" => "UsedSpaceSDAttachId"],
                    ["t" => "Строка", "n" => "НативныйТекст"],
                    ["t" => "Логическое", "n" => "ПубличныйДокумент"],
                    ["t" => "Логическое", "n" => "notifyIsChannel"],
                    ["t" => "JSON-объект", "n" => "ПересылаемоеСообщение"],
                    ["t" => "UUID", "n" => "UseDraftMessage"],
                    ["t" => "Логическое", "n" => "CreateDraftMessage"],
                    ["t" => "Логическое", "n" => "ИсключитьВнешниеКонтакты"],
                    ["t" => "Логическое", "n" => "translation"],
                    ["t" => "Строка", "n" => "Title"]
                ],
                "_type" => "record",
                "f" => 0
            ]
        ];

        $response = $this->send('Персона.СОтправить', $params);
        if (!$response->ok()) {
            Log::info('Sbis Персона.СОтправить - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return $json['result'];
        }
        return false;
    } // sendToChatRetail

    // Создать пустую номенклатуру
    public function nomenclatureCreate(
        $title,             //Наименование номенклатуры
        $description,
        $price,
        $suitabilityQuantity = null, // срок годности значение
        $suitabilityDimension = null, // срок годности ед. измерения
        $countryCode = null,
        $addFieldsData = null,
        $packsData = null,
        $nomenclatureType = 1,
        $article = null,
        $baseUnitCode = 796,
        $cardExtData = null,
        $costPrice = null,
        $productionText = null,
        $productionTime = null,
        $productionVolume = null,
    )
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    true,
                    true,
                    null,
                    false,
                    "",
                    [
                        "d" => [
                            $nomenclatureType
                        ],
                        "s" => [
                            [
                                "t" => "Число целое",
                                "n" => "id"
                            ]
                        ],
                        "_type" => "record",
                        "f" => 1
                    ],
                    $nomenclatureType,
                    null,
                    true,
                    null
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "CodeTypeDefine"],
                    ["t" => "Логическое", "n" => "NoWriteCodes"],
                    ["t" => "Строка", "n" => "baseUnitCode"],
                    ["t" => "Логическое", "n" => "isFolder"],
                    ["t" => "Строка", "n" => "name"],
                    ["t" => "Запись", "n" => "nomenclatureType"],
                    ["t" => "Число целое", "n" => "nomenclatureTypeId"],
                    ["t" => "Строка", "n" => "vatRate"],
                    ["t" => "Логическое", "n" => "ВызовИзБраузера"],
                    ["t" => "Строка", "n" => "Склад"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ИмяМетода" => "Номенклатура.FormatCard"
        ];

        $response = $this->send('Nomenclature.NomenclatureCreate', $params);
        if (!$response->ok()) {
            Log::info('Nomenclature.NomenclatureCreate - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            $data = parseSbisDataToArray($json['result']);
            return $this->nomenclatureUpdate(
                $data['id'],
                $title,
                $data['codes']['d'][0][0],
                $description,
                $price,
                $suitabilityQuantity,
                $suitabilityDimension,
                $countryCode,
                $addFieldsData,
                array_key_first($data['addFieldsStructure']) ?? null,
                $packsData,
                $article,
                $baseUnitCode,
                $cardExtData,
                $costPrice,
                $productionText,
                $productionTime,
                $productionVolume,
            );
        }
        return false;
    } // nomenclatureCreate

    // Обновить номенклатуру
    public function nomenclatureUpdate(
        $id,
        $title,
        $code,
        $description,
        $price,
        $suitabilityQuantity = null, // срок годности значение
        $suitabilityDimension = null, // срок годности ед. измерения
        $countryCode = null,
        $addFieldsData = null,
        $fieldsPrefix = null,
        $packsData = null,
        $article = null,
        $baseUnitCode = null,
        $cardExtData = null,
        $costPrice = null,
        $productionText = null,
        $productionTime = null,
        $productionVolume = null,
    )
    {
        $d = [
            $id,
            [
                [
                    "p",
                    [
                        "version" => "2"
                    ],
                    $description
                ]
            ],
            $title,
            $title,
            [
                "quantity" => $suitabilityQuantity,
                "dimension" => $suitabilityDimension
            ],
            [
                "d" => [
                    null,
                    "Цена",
                    false,
                    $price,
                    false,
                    null,
                    null,
                    null
                ],
                "s" => [
                    [
                        "n" => "PriceListId",
                        "t" => "Число целое"
                    ],
                    [
                        "n" => "PriceListName",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "IsAutoPrice",
                        "t" => "Логическое"
                    ],
                    [
                        "n" => "Price",
                        "t" => [
                            "n" => "Деньги",
                            "p" => 2
                        ]
                    ],
                    [
                        "n" => "HasHistory",
                        "t" => "Логическое"
                    ],
                    [
                        "n" => "Warehouse",
                        "t" => "Число целое"
                    ],
                    [
                        "n" => "WholesaleMRP",
                        "t" => [
                            "n" => "Деньги",
                            "p" => 2
                        ]
                    ],
                    [
                        "n" => "RetailMRP",
                        "t" => [
                            "n" => "Деньги",
                            "p" => 2
                        ]
                    ]
                ],
                "_type" => "record",
                "f" => 55
            ],
            true
        ];
        $s = [
            ["t" => "Число целое", "n" => "id"],
            ["t" => "JSON-объект", "n" => "description"],
            ["t" => "Строка", "n" => "name"],
            ["t" => "Строка", "n" => "nameBase"],
            ["t" => "JSON-объект", "n" => "suitability"],
            ["t" => "Запись", "n" => "firstPrice"],
            ["t" => "Логическое", "n" => "needSync"],
        ];

        // Код страны
        if (!empty($countryCode) && is_numeric($countryCode)) {
            $d[] = (string)$countryCode;
            $s[] = ["t" => "Строка", "n" => "countryCode"];
        }

        // Характеристики
        if (!empty($addFieldsData) && is_array($addFieldsData) && !empty($fieldsPrefix)) {
            $data = [];
            foreach ($addFieldsData as $fieldData) {
                $data[] = [
                    'name' => $fieldsPrefix . '_' . $fieldData['name'],
                    'label' => $fieldData['name'],
                    'val' => $fieldData['value'],
                ];
            }
            $d[] = $data;
            $s[] = ["t" => "JSON-объект", "n" => "addFieldsData"];
        }

        // Для кодов упаковок и артикула
        $packCodesData = [];

        // Единица измерения по умолчанию
        $baseUnitDataArr = [
            "base" => [
                "code" => "796",
                "abbr" => 'шт',
                "name" => 'Штука',
                "qty" => 1
            ]
        ];

        // Базовая единица измерения
        if (!empty($baseUnitCode) && is_numeric($baseUnitCode)) {
            $baseUnitDataArr = [
                "base" => [
                    "code" => (string)$baseUnitCode,
                    "abbr" => '',
                    "name" => '',
                    "qty" => 1
                ]
            ];
            $d[] = $baseUnitDataArr;
            $s[] = ["t" => "JSON-объект", "n" => "baseUnit"];
        }

        $packsArr = [
            json_encode($baseUnitDataArr),
        ];

        // Упаковка
        if (!empty($packsData) && is_array($packsData)) {
            $packCodesIndex = -100;
            foreach ($packsData as $pack) {
                $packJson = "{\"base\":{\"abbr\":\"шт\",\"code\":\"796\",\"name\":\"Штука\",\"qty\":1},\"pack\":{\"qty\":" . $pack['count'] . ",\"name\":\"" . $pack['name'] . "\"}}";
                $packsArr[] = $packJson;
                if (!empty($pack['code'])) {
                    $packCodesData[] = [
                        $pack['code'],
                        $packCodesIndex--,
                        null,
                        null,
                        $packJson,
                        null,
                        null
                    ];
                }
            }
        }

        // remainder (Коды упаковок, артикул, базовая единица измерения и т.п.)
        $d[] = [
            "d" => [
                0,
                0,
                0,
                false,
                $packsArr,
                true
            ],
            "s" => [
                [
                    "n" => "remainder",
                    "t" => "Число вещественное"
                ],
                [
                    "n" => "freeRemainder",
                    "t" => "Число вещественное"
                ],
                [
                    "n" => "reserve",
                    "t" => "Число вещественное"
                ],
                [
                    "n" => "withWarehouse",
                    "t" => "Логическое"
                ],
                [
                    "n" => "measureUnits",
                    "t" => "JSON-объект"
                ],
                [
                    "n" => "baseCanBeEdit",
                    "t" => "Логическое"
                ]
            ],
            "_type" => "record",
            "f" => 88
        ];
        $s[] = ["t" => "Запись", "n" => "remainder"];

        // Артикул
        if (!empty($article)) {
            $packCodesData[] = [
                $article,
                -2,
                -2,
                0,
                null,
                null,
                null
            ];
        }

        // Codes
        if (!empty($packCodesData)) {
            $d[] = [
                "d" => array_merge([
                    [
                        $code,
                        -1,
                        -1,
                        0,
                        null,
                        null,
                        null
                    ]
                ], $packCodesData),
                "s" => [
                    ["n" => "code", "t" => "Строка"],
                    ["n" => "id", "t" => "Число целое"],
                    ["n" => "type", "t" => "Число целое"],
                    ["n" => "packing", "t" => "Число целое"],
                    ["n" => "packImage", "t" => "Строка"],
                    ["n" => "partyCode", "t" => "Строка"],
                    ["n" => "accountCode", "t" => "JSON-объект"]
                ],
                "_type" => "recordset",
                "f" => 77
            ];
            $s[] = ["t" => "Выборка", "n" => "codes"];
        }

        if (!empty($cardExtData) && isset($cardExtData['d'][0]['d'][0]['d'][0][10])) {
            $cardExtData['d'][0]['d'][0]['d'][0][10] = $id;

            $d[] = $cardExtData;
            $s[] = ["t" => "Запись", "n" => "CardExtData"];
        }

        $params = [
            "Запись" => [
                "d" => $d,
                "s" => $s,
                "_type" => "record",
                "f" => 0
            ]
        ];
        $response = $this->send('Nomenclature.NomenclatureUpdate', $params);
        if (!$response->ok()) {
            Log::info('Nomenclature.NomenclatureUpdate - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']) && is_numeric($json['result'])) {
            return $json['result'];
        }
        return false;
    } // nomenclatureUpdate

    public function updateNomenclaturePrice($nomenclatureId, $nomenclaturePrice)
    {
        $params = [
            "Запись" => [
                "d" => [
                    (int)$nomenclatureId,
                    ["d" => [
                        null,
                        "Цена",
                        false,
                        (float)$nomenclaturePrice,
                        true,
                        null,
                        null,
                        null,
                        true,
                        null
                    ],
                        "s" => [
                            [
                                "n" => "PriceListId",
                                "t" => "Число целое"
                            ],
                            [
                                "n" => "PriceListName",
                                "t" => "Строка"
                            ],
                            [
                                "n" => "IsAutoPrice",
                                "t" => "Логическое"
                            ],
                            [
                                "n" => "Price",
                                "t" => [
                                    "n" => "Деньги",
                                    "p" => 2
                                ]
                            ],
                            [
                                "n" => "HasHistory",
                                "t" => "Логическое"
                            ],
                            [
                                "n" => "Warehouse",
                                "t" => "Число целое"
                            ],
                            [
                                "n" => "WholesaleMRP",
                                "t" => [
                                    "n" => "Деньги",
                                    "p" => 2
                                ]
                            ],
                            [
                                "n" => "RetailMRP",
                                "t" => [
                                    "n" => "Деньги",
                                    "p" => 2
                                ]
                            ],
                            [
                                "n" => "CanEdit",
                                "t" => "Логическое"
                            ],
                            [
                                "n" => "Packing",
                                "t" => "JSON-объект"
                            ]
                        ],
                        "_type" => "record", "f" => 1
                    ],
                    true
                ],
                "s" => [
                    ["t" => "Число целое", "n" => "id"],
                    ["t" => "Запись", "n" => "firstPrice"],
                    ["t" => "Логическое", "n" => "needSync"]
                ],
                "_type" => "record",
                "f" => 0
            ]
        ];
        $response = $this->send('Nomenclature.NomenclatureUpdate', $params);
        if (!$response->ok()) {
            Log::info('Nomenclature.NomenclatureUpdate - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']) && is_numeric($json['result'])) {
            return $json['result'];
        }
        return false;
    }

    // Записать коды номенклатуры
    public function updateNomenclatureCodes($nomenclatureId, $sbisCode, $article = null, $gtins = [], $ean13 = [])
    {
        $codes = [
            [
                $sbisCode,
                -1,
                -1,
                0,
                null,
                null,
                null
            ],
        ];
        if (!empty($article)) {
            $codes[] = [
                (string)$article,
                -2,
                -2,
                0,
                null,
                null,
                null
            ];
        }
        $startId = -100;
        foreach ($gtins as $code) {
            $codes[] = [
                (string)$code,
                $startId--,
                8, // GTIN
                0,
                null,
                null,
                null
            ];
        }
        foreach ($ean13 as $code) {
            $codes[] = [
                (string)$code,
                $startId--,
                2, // EAN13
                0,
                null,
                null,
                null
            ];
        }
        $params = [
            "Запись" => [
                "d" => [
                    (int)$nomenclatureId,
                    [
                        "d" => $codes,
                        "s" => [
                            [
                                "n" => "code",
                                "t" => "Строка"
                            ],
                            [
                                "n" => "id",
                                "t" => "Число целое"
                            ],
                            [
                                "n" => "type",
                                "t" => "Число целое"
                            ],
                            [
                                "n" => "packing",
                                "t" => "Число целое"
                            ],
                            [
                                "n" => "packImage",
                                "t" => "Строка"
                            ],
                            [
                                "n" => "partyCode",
                                "t" => "Строка"
                            ],
                            [
                                "n" => "accountCode",
                                "t" => "JSON-объект"
                            ]
                        ],
                        "_type" => "recordset",
                        "f" => 1
                    ],
                    true
                ],
                "s" => [
                    ["t" => "Число целое", "n" => "id"],
                    ["t" => "Выборка", "n" => "codes"],
                    ["t" => "Логическое", "n" => "needSync"]
                ],
                "_type" => "record",
                "f" => 0
            ]
        ];

        $response = $this->send('Nomenclature.NomenclatureUpdate', $params);
        if (!$response->ok()) {
            Log::info('Nomenclature.NomenclatureUpdate - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']) && is_numeric($json['result'])) {
            return $json['result'];
        }
        return false;
    }

    // Проверка кодов упаковок номенклатуры
    public function nomCodesIsCodeExistsWithName($nomId, $code)
    {
        $params = [
            "nomId" => $nomId,
            "code" => (string)$code,
            "codeType" => 5
        ];

        $response = $this->send('NomCodes.isCodeExistsWithName', $params);
        if (!$response->ok()) {
            Log::info('NomCodes.isCodeExistsWithName - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Получить список типов номенклатуры
    public function getNomenclatureTypes()
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    true,
                    false,
                    [
                        0,
                        1,
                        2,
                        3,
                        4,
                        9
                    ],
                    null
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "TranslitSearchString"],
                    ["t" => "Логическое", "n" => "WithoutCategories"],
                    [
                        "t" => [
                            "n" => "Массив",
                            "t" => "Число целое"
                        ],
                        "n" => "Категория"
                    ],
                    ["t" => "Строка", "n" => "Раздел"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => [
                "d" => [
                    [
                        true,
                        "Custom",
                        false
                    ]
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "l"],
                    ["t" => "Строка", "n" => "n"],
                    ["t" => "Логическое", "n" => "o"]
                ],
                "_type" => "recordset",
                "f" => 0
            ],
            "Навигация" => [
                "d" => [
                    false,
                    1000,
                    0
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "ЕстьЕще"],
                    ["t" => "Число целое", "n" => "РазмерСтраницы"],
                    ["t" => "Число целое", "n" => "Страница"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];

        $response = $this->send('ТипНоменклатуры.LinearListIterator', $params);
        if (!$response->ok()) {
            Log::info(
                'ТипНоменклатуры.LinearListIterator - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE)
            );
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Переместить номенклатуру
    public function nomenclatureMove($nomenclatureId, $folderId = null)
    {
        $params = [
            "filter" => [
                "d" => [
                    null,
                    null,
                    true,
                    null,
                    null,
                    true,
                    "online",
                    null,
                    null,
                    0,
                    1,
                    null,
                    null,
                    null,
                    ["Catalog"],
                    [
                        "d" => [
                            true
                        ],
                        "s" => [
                            [
                                "t" => "Логическое",
                                "n" => "HideConditionalProduct"
                            ]
                        ],
                        "_type" => "record",
                        "f" => 1
                    ],
                    true,
                    [],
                    null,
                    null,
                    [
                        "d" => [
                            [],
                            [(string)$nomenclatureId],
                            false,
                            [(string)$nomenclatureId],
                            "all"
                        ],
                        "s" => [
                            ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "excluded"],
                            ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "marked"],
                            ["t" => "Логическое", "n" => "recursive"],
                            ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "selected"],
                            ["t" => "Строка", "n" => "type"]
                        ],
                        "_type" => "record",
                        "f" => 2
                    ]
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Archival"],
                    ["t" => "Строка", "n" => "Balance"],
                    ["t" => "Логическое", "n" => "BalanceEmptyFolder"],
                    ["t" => "Строка", "n" => "BalanceForOrganization"],
                    ["t" => "Строка", "n" => "BarcodeExist"],
                    ["t" => "Логическое", "n" => "CheckFolderExists"],
                    ["t" => "Строка", "n" => "Endpoint"],
                    ["t" => "Строка", "n" => "Folder"],
                    ["t" => "Строка", "n" => "GetColumnsFromSettings"],
                    ["t" => "Число целое", "n" => "GetPath"],
                    ["t" => "Число целое", "n" => "Link"],
                    ["t" => "Строка", "n" => "MarkColor"],
                    ["t" => "Строка", "n" => "NodeType"],
                    ["t" => "Строка", "n" => "PublicationSaleState"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "ScopesAreas"],
                    ["t" => "Запись", "n" => "ServiceOption"],
                    ["t" => "Логическое", "n" => "TranslitSearchString"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "Type"],
                    ["t" => "Строка", "n" => "Vat"],
                    ["t" => "Строка", "n" => "Warehouse"],
                    ["t" => "Запись", "n" => "selection"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "folder_id" => $folderId,
            "calculate_nutrition" => false
        ];

        $response = $this->send('Nomenclature.Move', $params);
        if (!$response->ok()) {
            Log::info('Nomenclature.Move - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (!empty($json['result'])) {
            return $json['result'];
        }
        return false;
    }

    // Список прайслистов
    public function getPriceLists()
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    null,
                    null,
                    null,
                    null,
                    null,
                    true,
                    [
                        "used" => 1,
                        "date" => null
                    ]
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Data"],
                    ["t" => "Строка", "n" => "Date"],
                    ["t" => "Строка", "n" => "Folder"],
                    ["t" => "Строка", "n" => "Status"],
                    ["t" => "Строка", "n" => "Used"],
                    ["t" => "Логическое", "n" => "WithAllPriceLists"],
                    ["t" => "JSON-объект", "n" => "ИспользуетсяНаДату"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => [
                "d" => [
                    [
                        true,
                        "IndexNumber",
                        false
                    ]
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "l"],
                    ["t" => "Строка", "n" => "n"],
                    ["t" => "Логическое", "n" => "o"]
                ],
                "_type" => "recordset",
                "f" => 0
            ],
            "Навигация" => [
                "d" => [
                    true,
                    1000,
                    0
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "ЕстьЕще"],
                    ["t" => "Число целое", "n" => "РазмерСтраницы"],
                    ["t" => "Число целое", "n" => "Страница"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];

        $response = $this->send('PriceList.List', $params);
        if (!$response->ok()) {
            Log::info('PriceList.List - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    } // getPriceLists

    // Создать пустой прайс лист
    public function createPriceList()
    {
        $params = [
            "Filter" => [
                "d" => [
                    true,
                    null
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "ВызовИзБраузера"],
                    ["t" => "Строка", "n" => "Раздел"]
                ],
                "_type" => "record",
                "f" => 0
            ]
        ];

        $response = $this->send('PriceList.Create', $params);
        if (!$response->ok()) {
            Log::info('PriceList.Create - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return $json['result'];
        }
        return false;
    } // createPriceList

    // Обновить прайс лист
    public function updatePriceList($priceList)
    {
        $params = [
            "Record" => $priceList
        ];

        $response = $this->send('PriceList.NewSignWrite', $params);
        if (!$response->ok()) {
            Log::info('PriceList.NewSignWrite - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result'])) {
            return $json['result'];
        }
        return false;
    } // updatePriceList

    // Обновить позицию в прайс листе
    public function updatePriceListPosition($priceListId, $nomenclatureId, $price, $sbisFolderId)
    {
        /*
        $params = [
            "Запись" => [
                "d" => [
                    null,
                    $sbisFolderId,
                    null,
                    '',
                    [
                        "d" => [
                            $nomenclatureId,
                            $price,
                            $priceListId,
                            null
                        ],
                        "s" => [
                            [
                                "n" => "NomenclatureId",
                                "t" => "Число целое"
                            ],
                            [
                                "n" => "Price",
                                "t" => [
                                    "n" => "Деньги",
                                    "p" => 2
                                ]
                            ],
                            [
                                "n" => "PriceList",
                                "t" => "Число целое"
                            ],
                            [
                                "n" => "MeasureUnit",
                                "t" => "JSON-объект"
                            ]
                        ],
                        "_type" => "record",
                        "f" => 1
                    ],
                    [
                        "d" => [
                            [
                                "d" => [
                                    "Штука",
                                    "796",
                                    "шт",
                                    1
                                ],
                                "s" => [
                                    [
                                        "n" => "Name",
                                        "t" => "Строка"
                                    ],
                                    [
                                        "n" => "Code",
                                        "t" => "Строка"
                                    ],
                                    [
                                        "n" => "Abbr",
                                        "t" => "Строка"
                                    ],
                                    [
                                        "n" => "Qty",
                                        "t" => "Число вещественное"
                                    ]
                                ],
                                "_type" => "record",
                                "f" => 3
                            ],
                            [
                                "[\"base\" => [\"abbr\" => \"шт\", \"code\" => \"796\", \"name\" => \"Штука\", \"qty\" => 1.0]]"
                            ]
                        ],
                        "s" => [
                            [
                                "n" => "BaseMeasureUnit",
                                "t" => "Запись"
                            ],
                            [
                                "n" => "MUP_RAW",
                                "t" => [
                                    "n" => "Массив",
                                    "t" => "Текст"
                                ]
                            ]
                        ],
                        "_type" => "record",
                        "f" => 2
                    ],
                    [
                        "d" => [
                            1,
                            null,
                            "Товар",
                            0,
                            null,
                            [
                                "allowed_shipment" => true,
                                "characteristics" => [
                                    "data" => [],
                                    "order" => [],
                                    "structure" => []
                                ],
                                "special_acc" => [
                                    [
                                        "account" => null,
                                        "account_name" => "",
                                        "allowed_shipment" => true,
                                        "income_type" => null,
                                        "income_type_name" => "",
                                        "organizations" => [],
                                        "owner" => null,
                                        "owner_name" => "",
                                        "warehouses" => []
                                    ]
                                ]
                            ],
                            0,
                            null
                        ],
                        "s" => [
                            [
                                "n" => "TypeId",
                                "t" => "Число целое"
                            ],
                            [
                                "n" => "TypeUuid",
                                "t" => "UUID"
                            ],
                            [
                                "n" => "TypeName",
                                "t" => "Строка"
                            ],
                            [
                                "n" => "TypeCategory",
                                "t" => "Число целое"
                            ],
                            [
                                "n" => "TypeFlags",
                                "t" => [
                                    "n" => "Массив",
                                    "t" => "Логическое"
                                ]
                            ],
                            [
                                "n" => "TypeParameters",
                                "t" => "JSON-объект"
                            ],
                            [
                                "n" => "Accounting",
                                "t" => "Число целое"
                            ],
                            [
                                "n" => "AccountingSubType",
                                "t" => "Число целое"
                            ]
                        ],
                        "_type" => "record",
                        "f" => 4
                    ]
                ],
                "k" => "Id",
                "s" => [
                    ["n" => "Id", "t" => "Число целое"],
                    ["n" => "Folder", "t" => "Число целое"],
                    ["n" => "IsFolder", "t" => "Логическое"],
                    ["n" => "Name", "t" => "Строка"],
                    ["n" => "PriceListHierarchy", "t" => "Запись"],
                    ["t" => "Запись", "n" => "MUP"],
                    ["t" => "Запись", "n" => "TypeObject"]
                ],
                "_type" => "record",
                "f" => 0
            ]
        ];
        */

        $params = [
            "Запись" => [
                "d" => [
                    null,
                    null,
                    ["d" => [
                        2,
                        null
                    ],
                        "_type" => "record", "s" => [
                        [
                            "n" => "Current",
                            "t" => "Число целое"
                        ],
                        [
                            "n" => "Folder",
                            "t" => "Число целое"
                        ]
                    ],
                        "f" => 1
                    ],
                    null,
                    ["d" => [
                        [
                            "d" => [
                                "Штука",
                                "796",
                                "шт",
                                1
                            ],
                            "_type" => "record",
                            "s" => [
                                [
                                    "n" => "Name",
                                    "t" => "Строка"
                                ],
                                [
                                    "n" => "Code",
                                    "t" => "Строка"
                                ],
                                [
                                    "n" => "Abbr",
                                    "t" => "Строка"
                                ],
                                [
                                    "n" => "Qty",
                                    "t" => "Число вещественное"
                                ]
                            ],
                            "f" => 3
                        ],
                        [
                            "[\"base\" => [\"abbr\" => \"шт\", \"code\" => \"796\", \"name\" => \"Штука\", \"qty\" => 1.0]]"
                        ]
                    ],
                        "_type" => "record", "s" => [
                        [
                            "n" => "BaseMeasureUnit",
                            "t" => "Запись"
                        ],
                        [
                            "n" => "MUP_RAW",
                            "t" => [
                                "n" => "Массив",
                                "t" => "Текст"
                            ]
                        ]
                    ],
                        "f" => 2
                    ],
                    null,
                    null,
                    ["d" => [
                        1,
                        null,
                        "Товар",
                        0,
                        null,
                        [
                            "allowed_shipment" => true,
                            "characteristics" => [
                                "data" => [],
                                "order" => [],
                                "structure" => []
                            ],
                            "special_acc" => [
                                [
                                    "account" => null,
                                    "account_name" => "",
                                    "agent_type" => null,
                                    "allowed_shipment" => true,
                                    "income_type" => null,
                                    "income_type_name" => "",
                                    "organizations" => [
                                        [
                                            "id" => 31604,
                                            "name" => "Гришин Павел Валерьевич, ИП"
                                        ]
                                    ],
                                    "owner" => null,
                                    "owner_name" => "",
                                    "warehouses" => []
                                ]
                            ]
                        ],
                        0,
                        null,
                        113,
                        68,
                        false,
                        null
                    ],
                        "_type" => "record", "s" => [
                        [
                            "n" => "TypeId",
                            "t" => "Число целое"
                        ],
                        [
                            "n" => "TypeUuid",
                            "t" => "UUID"
                        ],
                        [
                            "n" => "TypeName",
                            "t" => "Строка"
                        ],
                        [
                            "n" => "TypeCategory",
                            "t" => "Число целое"
                        ],
                        [
                            "n" => "TypeFlags",
                            "t" => [
                                "n" => "Массив",
                                "t" => "Логическое"
                            ]
                        ],
                        [
                            "n" => "TypeParameters",
                            "t" => "JSON-объект"
                        ],
                        [
                            "n" => "Accounting",
                            "t" => "Число целое"
                        ],
                        [
                            "n" => "AccountingSubType",
                            "t" => "Число целое"
                        ],
                        [
                            "n" => "IncomeType",
                            "t" => "Число целое"
                        ],
                        [
                            "n" => "Debt",
                            "t" => "Число целое"
                        ],
                        [
                            "n" => "WorkOrder",
                            "t" => "Логическое"
                        ],
                        [
                            "n" => "Agent",
                            "t" => "Число целое"
                        ]
                    ],
                        "f" => 4
                    ],
                    false,
                    null,
                    null,
                    $price,
                    1,
                    null,
                    null,
                    null,
                    false,
                    ["d" => [
                        $nomenclatureId,
                        null,
                        $price,
                        $priceListId,
                        null,
                        null
                    ],
                        "_type" => "record", "s" => [
                        [
                            "n" => "NomenclatureId",
                            "t" => "Число целое"
                        ],
                        [
                            "n" => "NomenclatureFolder",
                            "t" => "Число целое"
                        ],
                        [
                            "n" => "Price",
                            "t" => [
                                "n" => "Деньги",
                                "p" => 2
                            ]
                        ],
                        [
                            "n" => "PriceList",
                            "t" => "Число целое"
                        ],
                        [
                            "n" => "MeasureUnit",
                            "t" => "JSON-объект"
                        ],
                        [
                            "t" => "Число целое",
                            "n" => "PriceInputValue"
                        ]
                    ],
                        "f" => 5
                    ],
                    null, //$sbisFolderId, //22701,
                    $sbisFolderId, //null,
                    null
                ],
                "s" => [
                    ["n" => "Name", "t" => "Строка"],
                    ["n" => "IsFolderUI", "t" => "Логическое"],
                    ["n" => "PublicationSaleState", "t" => "Запись"],
                    ["n" => "Image", "t" => "Строка"],
                    ["n" => "MUP", "t" => "Запись"],
                    ["n" => "ContainsFolders", "t" => "Логическое"],
                    ["n" => "FolderContents", "t" => "Выборка"],
                    ["n" => "TypeObject", "t" => "Запись"],
                    ["n" => "Deleted", "t" => "Логическое"],
                    ["n" => "DescriptionRaw", "t" => "Строка"],
                    ["n" => "ImageObject", "t" => "Выборка"],
                    ["n" => "Price", "t" => [
                        "n" => "Деньги",
                        "p" => 2
                    ]
                    ],
                    ["n" => "Type", "t" => "Число целое"],
                    ["n" => "DescriptionSimple", "t" => "Строка"],
                    ["n" => "IsNested", "t" => "Логическое"],
                    ["n" => "EssentialMedicines", "t" => "Логическое"],
                    ["n" => "ReadOnly", "t" => "Логическое"],
                    ["n" => "PriceListHierarchy", "t" => "Запись"],
                    ["n" => "Id", "t" => "Число целое"],
                    ["n" => "Folder", "t" => "Число целое"],
                    ["n" => "IsFolder", "t" => "Логическое"]
                ],
                "_type" => "record",
                "f" => 0
            ]
        ];

        $response = $this->send('PriceListPosition.UpdatePosition', $params);
        if (!$response->ok()) {
            Log::info('PriceListPosition.UpdatePosition - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            Log::info($params);
            Log::info([
                $priceListId,
                $nomenclatureId,
                $price,
                $sbisFolderId,
            ]);
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result'])) {
            return $json['result'];
        }
        return false;
    } // updatePriceListPosition

    // Поиск по номенклатуре
    public function searchNomenclature($search)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    null,
                    null,
                    null,
                    null,
                    true,
                    "online",
                    null,
                    null,
                    0,
                    1,
                    null,
                    null,
                    ["Catalog"],
                    $search,
                    [
                        "d" => [
                            null
                        ],
                        "s" => [
                            [
                                "t" => "Строка",
                                "n" => "FolderPriority"
                            ]
                        ],
                        "_type" => "record",
                        "f" => 1
                    ],
                    [
                        "d" => [
                            true
                        ],
                        "s" => [
                            [
                                "t" => "Логическое",
                                "n" => "HideConditionalProduct"
                            ]
                        ],
                        "_type" => "record",
                        "f" => 2
                    ],
                    true,
                    null,
                    null,
                    null,
                    "full",
                    "С разворотом"
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Archival"],
                    ["t" => "Строка", "n" => "Balance"],
                    ["t" => "Строка", "n" => "BalanceForOrganization"],
                    ["t" => "Строка", "n" => "BarcodeExist"],
                    ["t" => "Логическое", "n" => "CheckFolderExists"],
                    ["t" => "Строка", "n" => "Endpoint"],
                    ["t" => "Строка", "n" => "Folder"],
                    ["t" => "Строка", "n" => "GetColumnsFromSettings"],
                    ["t" => "Число целое", "n" => "GetPath"],
                    ["t" => "Число целое", "n" => "Link"],
                    ["t" => "Строка", "n" => "NodeType"],
                    ["t" => "Строка", "n" => "PublicationSaleState"],
                    [
                        "t" => [
                            "n" => "Массив",
                            "t" => "Строка"
                        ],
                        "n" => "ScopesAreas"
                    ],
                    ["t" => "Строка", "n" => "SearchString"],
                    ["t" => "Запись", "n" => "SearchStringOption"],
                    ["t" => "Запись", "n" => "ServiceOption"],
                    ["t" => "Логическое", "n" => "TranslitSearchString"],
                    ["t" => "Строка", "n" => "Type"],
                    ["t" => "Строка", "n" => "Vat"],
                    ["t" => "Строка", "n" => "Warehouse"],
                    ["t" => "Строка", "n" => "usePages"],
                    ["t" => "Строка", "n" => "Разворот"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => [
                "d" => [
                    [
                        true,
                        "Custom",
                        false
                    ]
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "l"],
                    ["t" => "Строка", "n" => "n"],
                    ["t" => "Логическое", "n" => "o"]
                ],
                "_type" => "recordset",
                "f" => 0
            ],
            "Навигация" => [
                "d" => [
                    true,
                    1000,
                    0
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "ЕстьЕще"],
                    ["t" => "Число целое", "n" => "РазмерСтраницы"],
                    ["t" => "Число целое", "n" => "Страница"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => [
                "Name",
                "NameBase",
                "NameShort",
                "Identifier",
                "Image",
                "Number",
                "ImageObject",
                "TypeObject",
                "VAT",
                "DescriptionSimple",
                "MarketplacePrice",
                "Price",
                "MUP",
                "Code",
                "BalanceObject",
                "FolderContents",
                "Accounting",
                "PublicationSaleState",
                "Deleted",
                "ContainsFolders",
                "ContainsAnyItem",
                "IsUnderOrder",
                "CodeAlternative",
                "IsFolderUI",
                "MarkColor",
                "Link",
                "Logistics",
                "CertificateAmount",
                "IsNested"
            ]
        ];
        $response = $this->send('Nomenclature.List', $params);

        if (!$response->ok()) {
            Log::info('Nomenclature.List - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    } // searchNomenclature

    // Список чеков
    public function getOfdPayments($limit = 50, $nextDateText = null, $dateStart = null, $dateEnd = null)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    true,
                    null,
                    null,
                    $dateEnd, // DateEnd: "2023-01-31",
                    $dateStart, // DateStart: "2023-01-01",
                    false,
                    [
                        1,
                        3
                    ],
                    false,
                    null,
                    [
                        1,
                        2,
                        5,
                        6
                    ],
                    null,
                    true,
                    false
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "Closed"],
                    ["t" => "Строка", "n" => "ComplexId"],
                    ["t" => "Строка", "n" => "Customer"],
                    ["t" => "Строка", "n" => "DateEnd"],
                    ["t" => "Строка", "n" => "DateStart"],
                    ["t" => "Логическое", "n" => "Deleted"],
                    [
                        "t" => [
                            "n" => "Массив",
                            "t" => "Число целое"
                        ],
                        "n" => "DocumentTypes"
                    ],
                    ["t" => "Логическое", "n" => "HasDeleted"],
                    ["t" => "Строка", "n" => "KKM"],
                    [
                        "t" => [
                            "n" => "Массив",
                            "t" => "Число целое"
                        ],
                        "n" => "OperationTypes"
                    ],
                    ["t" => "Строка", "n" => "SellerOrTeller"],
                    ["t" => "Логическое", "n" => "ShowAll"],
                    ["t" => "Логическое", "n" => "ShowHistory"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => [
                "d" => [
                    [
                        false,
                        "NextClosedWTZText",
                        true
                    ]
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "l"],
                    ["t" => "Строка", "n" => "n"],
                    ["t" => "Логическое", "n" => "o"]
                ],
                "_type" => "recordset",
                "f" => 0
            ],
            "Навигация" => [
                "d" => [
                    "forward",
                    true,
                    $limit,
                    [
                        "d" => [
                            $nextDateText, //"2023-07-12 08:36:00.000000"
                        ],
                        "s" => [
                            [
                                "t" => "Строка",
                                "n" => "NextClosedWTZText"
                            ]
                        ],
                        "_type" => "record",
                        "f" => 1
                    ]
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Запись", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];
        $response = $this->send('Payment.List', $params);
        if (!$response->ok()) {
            Log::info('Payment.List - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        return $response->json();
    } // getOfdPayments

    // Прочитать чек
    public function getOfdPayment($id)
    {
        $params = [
            "ИдО" => $id,
            "ИмяМетода" => '{"show_placemap":true}'
        ];
        $response = $this->send('SaleOrder.Read', $params);
        if (!$response->ok()) {
            Log::info('SaleOrder.Read - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    } // getOfdPayment

    // Создать заказ от покупателя
    public function createOrderBuyer()
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    null,
                    null,
                    false,
                    false,
                    [],
                    true,
                    true,
                    [
                        "binding" => [
                            "destroy" => "Документ.УдалитьДокументы",
                            "format" => "ЗаказСкВх.ListLite",
                            "read" => "ПрочитатьДляУчастника"
                        ],
                        "endpoint" => [
                            "contract" => "ЗаказСкВх"
                        ],
                        "keyProperty" => "@Документ",
                        "readMetaData" => [
                            "ИмяОбъекта" => "ЗаказСкВх"
                        ]
                    ],
                    "WHD/Docs/InOrder/dialog =>Dialog",
                    "0b00dee2-c8ae-4a0b-b435-cf5ce652cd24",
                    true,
                    "84d2ffa6-7517-11e2-b613-33dff47f9e94",
                    683,
                    170,
                    120826,
                    "Заказ от покупателя",
                    683,
                    218,
                    "Warehouse/API/DialogAsOpener",
                    "ЗаказВх",
                    "Заказ",
                    null,
                    "ЗаказВх",
                    null
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Face1Spp"],
                    ["t" => "Строка", "n" => "PublicGroupId"],
                    ["t" => "Логическое", "n" => "allowAdaptive"],
                    ["t" => "Логическое", "n" => "allowSwitchMode"],
                    ["t" => "JSON-объект", "n" => "cacheData"],
                    ["t" => "Логическое", "n" => "createWithCache"],
                    ["t" => "Логическое", "n" => "rp_doc"],
                    ["t" => "JSON-объект", "n" => "source"],
                    ["t" => "Строка", "n" => "template"],
                    ["t" => "Строка", "n" => "ВизуальноеПредставление"],
                    ["t" => "Логическое", "n" => "ВызовИзБраузера"],
                    ["t" => "Строка", "n" => "ИдРегламента"],
                    ["t" => "Число целое", "n" => "ЛицоСоздал"],
                    ["t" => "Число целое", "n" => "Подразделение"],
                    ["t" => "Число целое", "n" => "Регламент"],
                    ["t" => "Строка", "n" => "РегламентНазвание"],
                    ["t" => "Число целое", "n" => "Сотрудник"],
                    ["t" => "Число целое", "n" => "ТипДокумента"],
                    ["t" => "Строка", "n" => "ТипДокумента.ИмяДиалога"],
                    ["t" => "Строка", "n" => "ТипДокумента.ИмяОбъекта"],
                    ["t" => "Строка", "n" => "ТипДокумента.НазваниеКраткое"],
                    ["t" => "Строка", "n" => "ТипДокумента.ПодТипДокумента"],
                    ["t" => "Строка", "n" => "ТипДокумента.ТипДокумента"],
                    ["t" => "Строка", "n" => "ФильтрРегламент"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ИмяМетода" => "ЗаказСкВх.ListLite"
        ];
        $response = $this->send('ЗаказСкВх.Создать', $params);

        if (!$response->ok()) {
            Log::info('ЗаказСкВх.Создать - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Создать заказ от покупателя КИНЗА
    public function createOrderKinza()
    {
        $params = [
            "Warehouse" => 172 // 172 main Kinza
        ];
        $response = $this->send('Delivery.Create', $params);

        if (!$response->ok()) {
            Log::info('Delivery.Create - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Сохранить заказ от покупателя КИНЗА
    public function saveOrderKinza($id, $address, $nomenclature, $name, $phone)
    {
        $adress = json_encode($address);
        $params = [
            "Sale" => [
                "d" => [
                    $id,
                    date('Y-m-d H:i:s'),
                    [
                        "d" =>
                            $nomenclature
                        ,
                        "s" => [
                            ["t" => "Число целое", "n" => "SaleNomenclature"],
                            ["t" => "Число целое", "n" => "Nomenclature"],
                            ["t" => "Число вещественное", "n" => "Quantity"],
                            ["t" => "Число вещественное", "n" => "VATRate"],
                            ["t" => "Деньги", "n" => "TotalVAT"],
                            ["t" => "Число целое", "n" => "Person"],
                            ["t" => "Число целое", "n" => "Chair"],
                            ["t" => "Число целое", "n" => "Priority"],
                            ["t" => "Деньги", "n" => "CatalogPrice"],
                            ["t" => "Деньги", "n" => "ManualPrice"],
                            ["t" => "Деньги", "n" => "CheckPrice"],
                            ["t" => "Деньги", "n" => "CheckSum"],
                            ["t" => "Деньги", "n" => "CheckDiscount"],
                            ["t" => "Деньги", "n" => "TotalDiscount"],
                            ["t" => "Дата и время", "n" => "ClosedWTZ"],
                            ["t" => "Строка", "n" => "Name"],
                            ["t" => "Число целое", "n" => "Packing"],
                            ["t" => "JSON-объект", "n" => "Package"],
                            ["t" => "Строка", "n" => "Unit"],
                            ["t" => "Строка", "n" => "UnitCode"],
                            ["t" => "Число вещественное", "n" => "Fraction"],
                            ["t" => "Число целое", "n" => "PriceList"],
                            ["t" => "Строка", "n" => "Comment"],
                            ["t" => "Число целое", "n" => "SerialType"],
                            ["t" => "Строка", "n" => "SerialNumber"],
                            ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "SerialNumbers"],
                            ["t" => "Строка", "n" => "FullNumber"],
                            ["t" => "Строка", "n" => "Barcode"],
                            ["t" => "Число вещественное", "n" => "ReturnLimit"],
                            ["t" => "Деньги", "n" => "TotalPrice"],
                            ["t" => "Логическое", "n" => "UseTotalPrice"],
                            ["t" => "Выборка", "n" => "Positions"],
                            ["t" => "Запись", "n" => "MarkingInfo"],
                            ["t" => "JSON-объект", "n" => "Properties"],
                            ["t" => "Число целое", "n" => "NomenclatureCategory"],
                            ["t" => "Число целое", "n" => "NomenclatureKind"],
                            ["t" => "Логическое", "n" => "HasOwner"],
                            ["t" => "Число целое", "n" => "KitSettings"],
                            ["t" => "UUID", "n" => "Key"],
                            ["t" => "Строка", "n" => "ModifiersListId"],
                            ["t" => "Число целое", "n" => "Gift"],
                            ["t" => "UUID", "n" => "RecordKey"],
                            ["t" => "Строка", "n" => "НомНомер"],
                            ["t" => "Строка", "n" => "ТипНоменклатуры"],
                            ["t" => "Строка", "n" => "Img"],
                            ["t" => "JSON-объект", "n" => "MarkingCodeCheckInfo"],
                            ["t" => "Запись", "n" => "NomenclatureTypeInfo"],
                            ["t" => "Выборка", "n" => "Modifiers"]
                        ],
                        "_type" => "recordset",
                        "f" => 1
                    ],
                    [
                        "d" => [
                            null,
                            null,
                            $name,
                            [
                                $phone // номер телефона
                            ],
                            [],
                            null,
                            false
                        ],
                        "s" => [
                            ["t" => "Число целое", "n" => "CustomerID"],
                            ["t" => "Число целое", "n" => "Contractor"],
                            ["t" => "Строка", "n" => "FullName"],
                            ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "Phone"],
                            ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "Email"],
                            ["t" => "Выборка", "n" => "ContactData"],
                            ["t" => "Логическое", "n" => "SoftUpdate"]
                        ],
                        "_type" => "record",
                        "f" => 3
                    ],
                    [
                        "d" => [
                            "",
                            0,
                            $name,
                            $phone,
                            1,
                            11238,
                            null,
                            "2a0734d5-936b-4755-88d9-53592c5407a0",
                            null,
                            $adress
                        ],
                        "s" => [
                            ["t" => "Строка", "n" => "Comment"],
                            ["t" => "Число целое", "n" => "State"],
                            ["t" => "Строка", "n" => "ClientName"],
                            ["t" => "Строка", "n" => "ClientPhone"],
                            ["t" => "Число целое", "n" => "ClientCount"],
                            ["t" => "Число целое", "n" => "District"],
                            ["t" => "Число целое", "n" => "CourierID"],
                            ["t" => "UUID", "n" => "GeoCoords"],
                            ["t" => "Дата и время", "n" => "DateTime"],
                            ["t" => "Строка", "n" => "Address"]
                        ],
                        "_type" => "record",
                        "f" => 4
                    ],
                    1,
                    $name,
                    true,
                    $phone,
                    null,
                    [
                        "delivery" => [
                            "payment_type" => 0,
                            "change_amount" => null
                        ]
                    ],
                    true,
                    11
                ],
                "s" => [
                    ["t" => "Число целое", "n" => "Sale"],
                    ["t" => ["n" => "Дата и время", "tz" => false], "n" => "ReservedWTZ"],
                    ["t" => "Выборка", "n" => "Nomenclatures"],
                    ["t" => "Запись", "n" => "CustomerData"],
                    ["t" => "Запись", "n" => "Delivery"],
                    ["t" => "Число целое", "n" => "BookPersonNumber"],
                    ["t" => "Строка", "n" => "BookCustomerName"],
                    ["t" => "Логическое", "n" => "CustomerModified"],
                    ["t" => "Строка", "n" => "BookCustomerPhone"],
                    ["t" => "Логическое", "n" => "Cook"],
                    ["t" => "JSON-объект", "n" => "Properties"],
                    ["t" => "Логическое", "n" => "NeedRecalc"],
                    ["t" => "Число целое", "n" => "State"]
                ],
                "_type" => "record",
                "f" => 0
            ]
        ];
        $response = $this->send('Delivery.Write', $params);
        if (!$response->ok()) {
            Log::info('Delivery.Write - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Получить историю изменения данных у клиента
    public function getInfoInClientHistory($id)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    null,
                    [],
                    [],
                    "Все действия",
                    $id,
                    ["Контрагент"]
                ],
                "s" => [
                    ["t" => "Строка", "n" => "GUID"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "Period"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "user_id"],
                    ["t" => "Строка", "n" => "Действие"],
                    ["t" => "Число целое", "n" => "ИдО"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "Объект"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    "forward",
                    true,
                    1000,
                    null
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Строка", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];
        $response = $this->send('Контрагент.History_Of_Instance', $params);
        if (!$response->ok()) {
            Log::info('Контрагент.History_Of_Instance - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Получить кассовые смены
    public function getShifts($folderId = null, $limit = 3, $page = 0, $start_date = null, $end_date = null)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    null,
                    false,
                    $folderId,
                    [],
                    null,
                    null,
                    [
                        $end_date,
                        $start_date
                    ]
                ],
                "s" => [
                    ["t" => "Строка", "n" => "ComplexId"],
                    ["t" => "Логическое", "n" => "Deleted"],
                    ["t" => "Строка", "n" => "Folder"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "KKMComplexIds"],
                    ["t" => "Строка", "n" => "Product"],
                    ["t" => "Строка", "n" => "Workplace"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "period"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    true,
                    $limit,
                    $page
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "ЕстьЕще"],
                    ["t" => "Число целое", "n" => "РазмерСтраницы"],
                    ["t" => "Число целое", "n" => "Страница"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];
        $response = $this->send('Shift.TreeList', $params);
        if (!$response->ok()) {
            Log::info('Shift.TreeList - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Получить информацию по чеку
    public function getShiftСheks($id)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    true,
                    false,
                    [
                        1,
                        3
                    ],
                    true,
                    true,
                    [
                        1,
                        2,
                        5,
                        6
                    ],
                    null,
                    $id,
                    true,
                    false,
                    null
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "Closed"],
                    ["t" => "Логическое", "n" => "Deleted"],
                    ["t" => ["n" => "Массив", "t" => "Число целое"], "n" => "DocumentTypes"],
                    ["t" => "Логическое", "n" => "GetEGAISList"],
                    ["t" => "Логическое", "n" => "GetMarkingList"],
                    ["t" => ["n" => "Массив", "t" => "Число целое"], "n" => "OperationTypes"],
                    ["t" => "Строка", "n" => "Shift"],
                    ["t" => "Строка", "n" => "ShiftPathId"],
                    ["t" => "Логическое", "n" => "ShowAll"],
                    ["t" => "Логическое", "n" => "ShowHistory"],
                    ["t" => "Строка", "n" => "Workplace"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => [
                "d" => [
                    [
                        false,
                        "NextClosedWTZText",
                        true
                    ]
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "l"],
                    ["t" => "Строка", "n" => "n"],
                    ["t" => "Логическое", "n" => "o"]
                ],
                "_type" => "recordset",
                "f" => 0
            ],
            "Навигация" => [
                "d" => [
                    "forward",
                    true,
                    501,
                    null
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Строка", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];
        $response = $this->send('Payment.List', $params);
        if (!$response->ok()) {
            Log::info('Payment.List - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    //  Получить информацию по клиенту с чека
    public function getShiftClient($id)
    {
        $params = [
            "ИдО" => $id,
            "ИмяМетода" => "online"
        ];
        $response = $this->send('CRMClients.ReadClient', $params);
        if (!$response->ok()) {
            Log::info('CRMClients.ReadClient - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Получить информацию по номенклатуре с чека
    public function getShiftNomenclature($id)
    {
        $params = [
            "ИдО" => $id,
            "ИмяМетода" => "{\"show_placemap\":true}"
        ];
        $response = $this->send('SaleOrder.Read', $params);
        if (!$response->ok()) {
            Log::info('SaleOrder.Read - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Получить список документов по ЭДО
    public function getDocEdo()
    {
        $params = [

            "Фильтр" => [
                "d" => [
                    true,
                    true,
                    true,
                    true,
                    false,
                    "Все",
                    null,
                    null,
                    "Нет",
                    [],
                    "-1"
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "iterative_list"],
                    ["t" => "Логическое", "n" => "new_navigation"],
                    ["t" => "Логическое", "n" => "position_on_today"],
                    ["t" => "Логическое", "n" => "ws4_registry"],
                    ["t" => "Логическое", "n" => "УчитыватьИерархиюНО"],
                    ["t" => "Строка", "n" => "ФильтрВладелец"],
                    ["t" => "Строка", "n" => "ФильтрДатаП"],
                    ["t" => "Строка", "n" => "ФильтрДатаС"],
                    ["t" => "Строка", "n" => "ФильтрИзСписка"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "ФильтрПометкиИскл"],
                    ["t" => "Строка", "n" => "ФильтрСостояние"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    "forward",
                    true,
                    21,
                    [
                        "d" => [
                            null,
                            null
                        ],
                        "s" => [
                            ["t" => "Число целое", "n" => "@Событие"],
                            ["t" => "Строка", "n" => "StartPosition"]
                        ],
                        "_type" => "record",
                        "f" => 1
                    ]
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Запись", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];
        $response = $this->send('РеестрВходящих.СписокСобытий', $params);
        if (!$response->ok()) {
            Log::info(
                'Delivery.Write - РеестрВходящих.СписокСобытий' . json_encode(
                    $response->object(),
                    JSON_UNESCAPED_UNICODE
                )
            );
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Получить список документов по ЭДО оффициальный
    public function getDocEdoOff($list, $date)
    {
        $params = [
            "Фильтр" => [
                "ДатаС" => $date, // формат 01.05.2023
                "Тип" => "ДокОтгрВх",
                "Навигация" => [
                    "РазмерСтраницы" => 50,
                    "Страница" => $list,
                ]
            ]
        ];
        $response = $this->send('СБИС.СписокДокументов', $params);
        if (!$response->ok()) {
            Log::info(
                'Delivery.Write - СБИС.СписокДокументов' . json_encode($response->object(), JSON_UNESCAPED_UNICODE)
            );
            $response->throw();
        }
        $json = $response->json();

        return $json;
    }

    // Получить список документов по ЭДО внутрений
    public function getDocEdoo($position = null)
    {
        $date = Carbon::now()->startOfMonth()->subMonths(3)->format('Y-m-d');
        $dateNow = Carbon::now()->format('Y-m-d');
        $params = [
            "Фильтр" => [
                "d" => [
                    true,
                    true,
                    true,
                    true,
                    false,
                    "ДокОтгрВх",
                    true,
                    "Все",
                    $dateNow, //по
                    $date, //с
                    "-1",
                    "Нет",
                    [],
                    true,
                    null,
                    /*[
                        "filterCurrentOurOrganisation" => 9049,
                        "filterDocTypes" => [
                            "1001"
                        ],
                        "iteration_sql_mode" => false
                    ]*/
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "iterative_list"],
                    ["t" => "Логическое", "n" => "new_navigation"],
                    ["t" => "Логическое", "n" => "position_on_today"],
                    ["t" => "Логическое", "n" => "rp_doc"],
                    ["t" => "Логическое", "n" => "translit_search"],
                    ["t" => "Строка", "n" => "НазваниеТипаДокумента"],
                    ["t" => "Логическое", "n" => "УчитыватьИерархиюНО"],
                    ["t" => "Строка", "n" => "ФильтрВладелец"],
                    ["t" => "Дата", "n" => "ФильтрДатаП"],
                    ["t" => "Дата", "n" => "ФильтрДатаС"],
                    ["t" => "Строка", "n" => "ФильтрОрганизацияФилиал"],
                    ["t" => "Строка", "n" => "ФильтрПоЛицуИзСписка"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "ФильтрПометкиИскл"],
                    ["t" => "Логическое", "n" => "ФильтрПроведенные"],
                    ["t" => "Строка", "n" => "ФильтрСуммаДо"],
                    //["t" => "JSON-объект", "n" => "searchSettings"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    "forward",
                    true,
                    101,
                    $position
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Запись", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];
        $response = $this->send('ДокОтгрВх.СписокХраним', $params);
        if (!$response->ok()) {
            Log::info(
                'Delivery.Write - ДокОтгрВх.СписокХраним' . json_encode($response->object(), JSON_UNESCAPED_UNICODE)
            );
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Создать пустую папку
    /*public function folderCreate($title, $nomNumber)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    true,
                    true,
                    null,
                    true,
                    "$title",
                    null,
                    true,
                    null
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "CodeTypeDefine"],
                    ["t" => "Логическое", "n" => "NoWriteCodes"],
                    ["t" => "Строка", "n" => "baseUnitCode"],
                    ["t" => "Логическое", "n" => "isFolder"],
                    ["t" => "Строка", "n" => "name"],
                    ["t" => "Строка", "n" => "vatRate"],
                    ["t" => "Логическое", "n" => "ВызовИзБраузера"],
                    ["t" => "Строка", "n" => "Склад"]
                    "forward",
                    true,
                    501,
                    null
                ],
                "s" => [
        ["t" => "Строка", "n" => "Direction"],
        ["t" => "Логическое", "n" => "HasMore"],
        ["t" => "Число целое", "n" => "Limit"],
        ["t" => "Строка", "n" => "Position"]
    ],
                "_type" => "record",
                "f" => 0
            ],
            "ИмяМетода" => "Номенклатура.FormatCard"
        ];

        $response = $this->send('Nomenclature.NomenclatureCreate', $params);
        if (!$response->ok()){
            Log::info('Nomenclature.NomenclatureCreate - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if ( isset($json['result']['d']) && is_array($json['result']['d']) ) {
            $data = parseSbisDataToArray($json['result']);
            return $this->folderUpdate($data['id'], $title, $nomNumber);
        }
        return false;
    }*/

    // Создать папку v2
    public function createNomenclatureFolder($title, $parentFolder = null)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    true,
                    null,
                    $title,
                    true,
                    null,
                    $parentFolder,
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "ВызовИзБраузера"],
                    ["t" => "Строка", "n" => "КодЕдиницаИзмерения"],
                    ["t" => "Строка", "n" => "Наименование"],
                    ["t" => "Логическое", "n" => "Раздел@"],
                    ["t" => "Строка", "n" => "Склад"],
                    ["t" => "Число целое", "n" => "folder"],
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ИмяМетода" => "Номенклатура.FormatCard"
        ];
        $response = $this->send('Nomenclature.NomenclatureCreate', $params);
        if (!$response->ok()) {
            Log::info('Nomenclature.NomenclatureCreate - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Заполнить пустую папку
    public function folderUpdate($id, $title, $nomNumber)
    {
        $d = [
            $id,
            [],
            $title,
            $title,
            $nomNumber,
            [],
            [
                "d" => [],
                "s" => [
                    ["n" => "PriceListId", "t" => "Число целое"],
                    ["n" => "PriceListName", "t" => "Строка"],
                    ["n" => "IsAutoPrice", "t" => "Логическое"],
                    ["n" => "Price", "t" => ["n" => "Деньги", "p" => 2]],
                    ["n" => "HasHistory", "t" => "Логическое"],
                    ["n" => "Warehouse", "t" => "Число целое"],
                    ["n" => "WholesaleMRP", "t" => ["n" => "Деньги", "p" => 2]],
                    ["n" => "RetailMRP", "t" => ["n" => "Деньги", "p" => 2]]
                ],
                "_type" => "record",
                "f" => 55
            ],
        ];
        $s = [
            ["t" => "Число целое", "n" => "id"],
            ["t" => "JSON-объект", "n" => "description"],
            ["t" => "Строка", "n" => "name"],
            ["t" => "Строка", "n" => "nameBase"],
            ["t" => "Строка", "n" => "nomNumber"],
            ["t" => "JSON-объект", "n" => "suitability"],
            ["t" => "Запись", "n" => "firstPrice"],
        ];

        $params = [
            "Запись" => [
                "d" => $d,
                "s" => $s,
                "_type" => "record",
                "f" => 0
            ]
        ];
        $response = $this->send('Nomenclature.NomenclatureUpdate', $params);
        if (!$response->ok()) {
            Log::info('Nomenclature.NomenclatureUpdate - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']) && is_numeric($json['result'])) {
            return $json['result'];
        }
        return false;
    } // folderUpdate

    // Создать контрагента физическое лицо
    public function cardContractorCreate(
        $surname,
        $name,
        $patronymic,
        $address,
    )
    {
        $d = [
            $surname,
            $name,
            $patronymic,
            $address,
        ];

        $s = [
            ["t" => "Строка", "n" => "Surname"],
            ["t" => "Строка", "n" => "Name"],
            ["t" => "Строка", "n" => "Patronymic"],
            ["t" => "Строка", "n" => "Address"],
        ];

        $params = [
            "CustomerData" => [
                "d" => $d,
                "s" => $s,
                "_type" => "record",
                "f" => 0
            ]
        ];
        $response = $this->send('CRMClients.SaveCustomer', $params);
        if (!$response->ok()) {
            Log::info('CRMClients.SaveCustomer - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']) && is_numeric($json['result'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    //СБИС Записать документ
    public function createDocuments($documentData, $inn = null, $kpp = null)
    {
        if (empty($inn)) {
            $getInfoOrg = $this->getPointSale();
            $inn = $getInfoOrg[0]['ИНН'];
            $kpp = $getInfoOrg[0]['КПП'];
        }

        $params = [
            "Документ" => [
                "Тип" => $documentData['type'],
                'НашаОрганизация' => [
                    'СвЮЛ' => [
                        'ИНН' => $inn,
                        'КПП' => $kpp,
                    ],
                ],
                "Сумма" => (int)$documentData['sum'],
                "Примечание" => $documentData['comment'],
            ]
        ];
        if ($documentData['org_type'] === 'ooo') {
            $params['Документ']['Контрагент'] = [
                'СвЮЛ' => [
                    'ИНН' => $documentData['inn'],
                    'КПП' => $documentData['kpp'],
                    'Название' => $documentData['title'],
                ]
            ];
        } elseif ($documentData['org_type'] === 'ip') {
            $params['Документ']['Контрагент'] = [
                'СвФЛ' => [
                    'ИНН' => $documentData['inn'],
                    'Название' => $documentData['title'],
                ]
            ];
        }

        if (!empty($documentData['number'])) {
            $params['Документ']['Номер'] = $documentData['number'];
        }
//        if (!is_production()) {
//            $params['Документ']['Ответственный'] = [
//                'Фамилия' => $documentData['ответственныйФамилия'],
//                'Имя' => $documentData['ответственныйИмя'],
//                'Отчество' => $documentData['ответственныйОтчество'],
//            ];
//        }

        $response = $this->send('СБИС.ЗаписатьДокумент', $params);

        if (!$response->ok()) {
            Log::info('Sbis СБИС.ЗаписатьДокумент - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']) && is_array($json['result'])) {
            $sbisDocData = parseSbisDataToArray($json['result']);
            if (!empty($documentData['name'])) {
                return $this->addXmlToDocSync($documentData, $sbisDocData);
            } else {
                return $sbisDocData;
            }
        }
        return false;
    }

    // Добавление табличной части документа
    public function addXmlToDocSync($documentData, $sbisDocData)
    {
        $params = [
            "Документ" => [
                "Идентификатор" => $sbisDocData['id'] ?? $sbisDocData['Идентификатор'],
                'Вложение' => [
                    'Тип' => $documentData['type_doc']/*'ЭДОСч'*/,
                    'Подтип' => $documentData['sub_type_doc'],
                    'ВерсияФормата' => $documentData['docFormatVersion'],
                    'Название' => $documentData['name'],
                    'Сумма' => $documentData['sum'],
                    'СуммаБезНДС' => $documentData['sum'],
                    'Номер' => $documentData['docNumber'],
                    'Дата' => $documentData['date']/*date('d.m.Y')*/,
                    'Файл' => [
                        'Имя' => $documentData['xml_fileName'] . '.xml',
                        'ДвоичныеДанные' => base64_encode(iconv("UTF-8", "windows-1251", $documentData['xml'])),
                    ],
                ]
            ]
        ];
        $response = $this->send('СБИС.ЗаписатьВложение', $params);

        if (!$response->ok()) {
            Log::info('Sbis СБИС.ЗаписатьВложение - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        return $response->object();
    }

    // Установить контрагента к документу по внутреннему Id
    public function setClientIdToDocument($docType, $docId, $clientId)
    {
        $params = [
            "Запись" => [
                "d" => [
                    $docId,
                    '',
                    $clientId,
                    false,
                    false
                ],
                "s" => [
                    ["t" => "Число целое", "n" => "@Документ"],
                    ["t" => "Строка", "n" => "Лицо1.Название"],
                    ["t" => "Связь", "n" => "Лицо1"],
                    ["t" => "Логическое", "n" => "РучноеСохранение"],
                    ["t" => "Логическое", "n" => "СброситьЧерновик"]
                ],
                "_type" => "record",
                "f" => 0
            ]
        ];
        $response = $this->send($docType . '.Записать', $params); // ЗаказСкВх
        if (!$response->ok()) {
            Log::info($docType . '.Записать - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        return $json['result'] ?? false;
    }

    public function test()
    {
        $params = [
            "Документ" => [
                "Идентификатор" => "6ac913a7-122f-4231-9c4b-c1cb6f4088d2",
                "Примечание" => "Услуги по настройке коннектора Тавиат 123",
            ]
        ];
        /*if ($org_type === 'ooo') {
            $params['Документ']['Контрагент'] = [
                'СвЮЛ' => [
                    'ИНН' => $inn,
                    'КПП' => $kpp,
                    'Название' => $title,
                ]
            ];
        } elseif ($org_type === 'ip') {
            $params['Документ']['Контрагент'] = [
                'СвФЛ' => [
                    'ИНН' => $inn,
                    'Название' => $title,
                ]
            ];
        }*/
        /*$params['Документ']['Ответственный'] = [
            'Идентификатор' => "47a57c92-ef0a-4b42-b155-ff89e278b77b",
            'Фамилия' => 'Орлов',
            'Имя' => 'Александр',
            'Отчество' => 'Олегович',
        ];*/
        $params['Документ']['Контрагент']['СвФЛ'] = [
            'Идентификатор' => 180085,
            //'Фамилия' => 'Максимова',
            //'Имя' => 'Ирина',
            //'Отчество' => 'Николаевна',
            //'ЧастноеЛицо' => 'Да',
            //'ИНН' => '123456789000',
        ];

        $response = $this->send('СБИС.ЗаписатьДокумент', $params);
        dd($response->json());
    }

    public function createSbisLead(
        $theme,
        $name,
        $phone,
        $question,
        $page = null,
        $email = null,
        $utm = null,
        $responsible = null,
        $contragentID = null
    )
    {
        $params = [
            'd' => [
                'Регламент' => $theme,
                'Ответственный' => (string)$responsible,
                'КонтактноеЛицо' => [
                    'd' => [
                        'ФИО' => $name,
                        'Телефон' => $phone,
                        'email' => $email
                    ],
                    's' => [
                        'ФИО' => 'Строка',
                        'Телефон' => 'Строка',
                        'email' => 'Строка'
                    ],
                ],
                'Контрагент' => [
                    'd' => ['@Лицо' => $contragentID,],
                    's' => ['@Лицо' => 'Строка',],
                ],
                'КаналТрафика' => 'Реферальная программа',
                'Примечание' => "Заявка со страницы : " . $page . "\nФорма захвата: " . $question . "",
                'ЗначенияДопПолей' => [
                    'KanalTrafika' => 'TaviatSite',
                    'КатегорияКлиента' => ['position_1'],
                    'utm' => $utm,
                ],
            ],
            's' => [
                'Регламент' => 'Число целое',
                'Ответственный' => 'Строка',
                'КонтактноеЛицо' => 'Запись',
                'Контрагент' => 'Запись',
                'КаналТрафика' => 'Строка',
                'Примечание' => 'Строка',
                'ЗначенияДопПолей' => 'JSON-объект',
            ],
            '_type' => 'record',
            'f' => 0
        ];

        $response = $this->send('CRMLead.insertRecord', $params, 'Лид');

        if (!$response->ok()) {
            Log::info('Sbis CRMLead.insertRecord - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return $json['result'];
        }
        return false;
    }

    public function fetchSalesPoints()
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    date("Y-m-") . "31",
                    date("Y-m-") . "01",
                    "WithHierarchy",
                    "SalePointFolder",
                    "SalePointId",
                    1,
                    "RetailStats.SalesPointsList",
                    333,
                    "2b9b4da6-d4dd-498a-8363-7a0b0c9ff9c4",
                    "P30DT0H0M0S",
                    null,
                    "C170",
                    true,
                    true,
                    null,
                    "modalOperations_c5a3016b-3d1b-4be3-8af4-4c6dd7993d2c",
                    true,
                    [
                        date("Y-m-") . "01",
                        date("Y-m-") . "31",
                    ]
                ],
                "s" => [
                    [
                        "t" => "Дата",
                        "n" => "DateEnd"
                    ],
                    [
                        "t" => "Дата",
                        "n" => "DateStart"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "HierarchySalesPoint"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "PrefetchHierarchyColumn"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "PrefetchIdColumn"
                    ],
                    [
                        "t" => "Число целое",
                        "n" => "PrefetchLevels"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "PrefetchMethod"
                    ],
                    [
                        "t" => "Число целое",
                        "n" => "PrefetchPages"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "PrefetchSessionId"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "PrefetchSessionLiveTime"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "Product"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "SalePointFolder"
                    ],
                    [
                        "t" => "Логическое",
                        "n" => "UseTranslitListCall"
                    ],
                    [
                        "t" => "Логическое",
                        "n" => "WithHierarchy"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "WithHierarchyByPlaces"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "modalOperationToken"
                    ],
                    [
                        "t" => "Логическое",
                        "n" => "pathEnable"
                    ],
                    [
                        "t" => [
                            "n" => "Массив",
                            "t" => "Дата"
                        ],
                        "n" => "ФильтрПериод"
                    ]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => [
                "d" => [
                    [
                        false,
                        "Sales",
                        true
                    ]
                ],
                "s" => [
                    [
                        "t" => "Логическое",
                        "n" => "l"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "n"
                    ],
                    [
                        "t" => "Логическое",
                        "n" => "o"
                    ]
                ],
                "_type" => "recordset",
                "f" => 0
            ],
            "Навигация" => [
                "d" => [
                    true,
                    30,
                    0
                ],
                "s" => [
                    [
                        "t" => "Логическое",
                        "n" => "ЕстьЕще"
                    ],
                    [
                        "t" => "Число целое",
                        "n" => "РазмерСтраницы"
                    ],
                    [
                        "t" => "Число целое",
                        "n" => "Страница"
                    ]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];

//        RetailStats.SalesPointsList
        $response = $this->send('RetailStats.SalesPointsListPrefetch', $params);


        if (!$response->ok()) {
            Log::info('Sbis CRMLead.insertRecord - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    public function fetchNomenclatureList($categoryId = null)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    null,
                    null,
                    true,
                    null,
                    null,
                    true,
                    [
                        "d" => [
                            "desktop"
                        ],
                        "s" => [
                            [
                                "t" => "Строка",
                                "n" => "Device"
                            ]
                        ],
                        "_type" => "record",
                        "f" => 1
                    ],
                    "online",
                    null,
                    $categoryId,
                    null,
                    null,
                    0,
                    false,
                    1,
                    null,
                    true,
                    null,
                    null,
                    [
                        "Catalog"
                    ],
                    [
                        "d" => [
                            true
                        ],
                        "s" => [
                            [
                                "t" => "Логическое",
                                "n" => "HideConditionalProduct"
                            ]
                        ],
                        "_type" => "record",
                        "f" => 2
                    ],
                    true,
                    null,
                    null,
                    null
                ],
                "s" => [
                    [
                        "t" => "Строка",
                        "n" => "Archival"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "Balance"
                    ],
                    [
                        "t" => "Логическое",
                        "n" => "BalanceEmptyFolder"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "BalanceForOrganization"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "BarcodeExist"
                    ],
                    [
                        "t" => "Логическое",
                        "n" => "CheckFolderExists"
                    ],
                    [
                        "t" => "Запись",
                        "n" => "ConfigurationOption"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "Endpoint"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "EssentialMedicines"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "Folder"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "FolderCompilation"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "GetColumnsFromSettings"
                    ],
                    [
                        "t" => "Число целое",
                        "n" => "GetPath"
                    ],
                    [
                        "t" => "Логическое",
                        "n" => "HideEmptyFolder"
                    ],
                    [
                        "t" => "Число целое",
                        "n" => "Link"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "MarkColor"
                    ],
                    [
                        "t" => "Логическое",
                        "n" => "NewConfiguration"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "NodeType"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "PublicationSaleState"
                    ],
                    [
                        "t" => [
                            "n" => "Массив",
                            "t" => "Строка"
                        ],
                        "n" => "ScopesAreas"
                    ],
                    [
                        "t" => "Запись",
                        "n" => "ServiceOption"
                    ],
                    [
                        "t" => "Логическое",
                        "n" => "TranslitSearchString"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "Type"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "Vat"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "Warehouse"
                    ]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => [
                "d" => [
                    [
                        true,
                        "Custom",
                        false
                    ]
                ],
                "s" => [
                    [
                        "t" => "Логическое",
                        "n" => "l"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "n"
                    ],
                    [
                        "t" => "Логическое",
                        "n" => "o"
                    ]
                ],
                "_type" => "recordset",
                "f" => 0
            ],
            "Навигация" => [
                "d" => [
                    true,
                    10000,
                    0
                ],
                "s" => [
                    [
                        "t" => "Логическое",
                        "n" => "ЕстьЕще"
                    ],
                    [
                        "t" => "Число целое",
                        "n" => "РазмерСтраницы"
                    ],
                    [
                        "t" => "Число целое",
                        "n" => "Страница"
                    ]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => [
                "Name",
                "NameBase",
                "NameShort",
                "Identifier",
                "Image",
                "Number",
                "ImageObject",
                "TypeObject",
                "VAT",
                "DescriptionSimple",
                "MarketplacePrice",
                "Price",
                "MUP",
                "Code",
                "BalanceObject",
                "FolderContents",
                "Accounting",
                "PublicationSaleState",
                "Deleted",
                "ContainsFolders",
                "ContainsAnyItem",
                "IsUnderOrder",
                "CodeAlternative",
                "IsFolderUI",
                "MarkColor",
                "Link",
                "Logistics",
                "EssentialMedicines",
                "CourseEvent",
                "CertificateAmount",
                "IsNested"
            ]
        ];

        $response = $this->send('Nomenclature.List', $params);

        if (!$response->ok()) {
            Log::info('Nomenclature.List - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    public function fetchActualNomenclatureList($salesPointId, $categoryId = null)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    null,
                    2,
                    true,
                    null,
                    $salesPointId,
                    null,
                    true,
                    [
                        "d" => [
                            "desktop"
                        ],
                        "s" => [
                            [
                                "t" => "Строка",
                                "n" => "Device"
                            ]
                        ],
                        "_type" => "record",
                        "f" => 1
                    ],
                    "online",
                    null,
                    $categoryId,
                    $categoryId,
                    $categoryId,
                    0,
                    false,
                    1,
                    null,
                    true,
                    null,
                    null,
                    [
                        "Catalog"
                    ],
                    [
                        "d" => [
                            true
                        ],
                        "s" => [
                            [
                                "t" => "Логическое",
                                "n" => "HideConditionalProduct"
                            ]
                        ],
                        "_type" => "record",
                        "f" => 2
                    ],
                    true,
                    null,
                    null
                ],
                "s" => [
                    [
                        "t" => "Строка",
                        "n" => "Archival"
                    ],
                    [
                        "t" => "Число целое",
                        "n" => "Balance"
                    ],
                    [
                        "t" => "Логическое",
                        "n" => "BalanceEmptyFolder"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "BalanceForOrganization"
                    ],
                    [
                        "t" => "Число целое",
                        "n" => "BalanceForWarehouse"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "BarcodeExist"
                    ],
                    [
                        "t" => "Логическое",
                        "n" => "CheckFolderExists"
                    ],
                    [
                        "t" => "Запись",
                        "n" => "ConfigurationOption"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "Endpoint"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "Envd"
                    ],
                    [
                        "t" => "Число целое",
                        "n" => "Folder"
                    ],
                    [
                        "t" => "Число целое",
                        "n" => "FolderCompilation"
                    ],
                    [
                        "t" => "Число целое",
                        "n" => "GetColumnsFromSettings"
                    ],
                    [
                        "t" => "Число целое",
                        "n" => "GetPath"
                    ],
                    [
                        "t" => "Логическое",
                        "n" => "HideEmptyFolder"
                    ],
                    [
                        "t" => "Число целое",
                        "n" => "Link"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "MarkColor"
                    ],
                    [
                        "t" => "Логическое",
                        "n" => "NewConfiguration"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "NodeType"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "PublicationSaleState"
                    ],
                    [
                        "t" => [
                            "n" => "Массив",
                            "t" => "Строка"
                        ],
                        "n" => "ScopesAreas"
                    ],
                    [
                        "t" => "Запись",
                        "n" => "ServiceOption"
                    ],
                    [
                        "t" => "Логическое",
                        "n" => "TranslitSearchString"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "Type"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "Vat"
                    ]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => [
                "d" => [
                    [
                        true,
                        "Custom",
                        false
                    ]
                ],
                "s" => [
                    [
                        "t" => "Логическое",
                        "n" => "l"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "n"
                    ],
                    [
                        "t" => "Логическое",
                        "n" => "o"
                    ]
                ],
                "_type" => "recordset",
                "f" => 0
            ],
            "Навигация" => [
                "d" => [
                    true,
                    30,
                    0
                ],
                "s" => [
                    [
                        "t" => "Логическое",
                        "n" => "ЕстьЕще"
                    ],
                    [
                        "t" => "Число целое",
                        "n" => "РазмерСтраницы"
                    ],
                    [
                        "t" => "Число целое",
                        "n" => "Страница"
                    ]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => [
                "Name",
                "NameBase",
                "NameShort",
                "Identifier",
                "Image",
                "Number",
                "ImageObject",
                "TypeObject",
                "VAT",
                "DescriptionSimple",
                "MarketplacePrice",
                "Price",
                "MUP",
                "Code",
                "BalanceObject",
                "FolderContents",
                "Accounting",
                "PublicationSaleState",
                "Deleted",
                "ContainsFolders",
                "ContainsAnyItem",
                "IsUnderOrder",
                "CodeAlternative",
                "IsFolderUI",
                "MarkColor",
                "Link",
                "Logistics",
                "EssentialMedicines",
                "CertificateAmount",
                "IsNested"
            ]
        ];

        $response = $this->send('Nomenclature.List', $params);

        if (!$response->ok()) {
            Log::info('Nomenclature.List - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    public function getPointAddress()
    {
        $params = [
            "includeArchived" => false,
            "organizationId" => "-1",
            "status" => "all"
        ];

        $response = $this->send('KKT.Tree', $params);

        if (!$response->ok()) {
            Log::info('KKT.Tree' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    public function getClientsListWithPoints()
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    [
                        "2023-08-01",
                        "2023-08-31"
                    ],
                    false,
                    false
                ],
                "s" => [
                    [
                        "t" => [
                            "n" => "Массив",
                            "t" => "Дата"
                        ],
                        "n" => "Period"
                    ],
                    [
                        "t" => "Логическое",
                        "n" => "WithBonuses"
                    ],
                    [
                        "t" => "Логическое",
                        "n" => "WithPurchases"
                    ]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    "forward",
                    true,
                    30,
                    null
                ],
                "s" => [
                    [
                        "t" => "Строка",
                        "n" => "Direction"
                    ],
                    [
                        "t" => "Логическое",
                        "n" => "HasMore"
                    ],
                    [
                        "t" => "Число целое",
                        "n" => "Limit"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "Position"
                    ]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];

        $response = $this->send('Bonus.GetClientListWithStats', $params);

        if (!$response->ok()) {
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    public function getClientById($id)
    {
        $params = [
            "Params" => [
                "d" => [
                    $id,
                    [
                        date("Y-m-") . "31T20:00:00.000Z",
                        date("Y-m-") . "01T20:00:00.000Z",
                    ]
                ],
                "s" => [
                    [
                        "t" => "Число целое",
                        "n" => "ClientID"
                    ],
                    [
                        "t" => [
                            "n" => "Массив",
                            "t" => "Дата и время"
                        ],
                        "n" => "DatePeriod"
                    ]
                ],
                "_type" => "record",
                "f" => 0
            ]
        ];

        $response = $this->send('CRMClients.ReadMiniCard', $params);

        if (!$response->ok()) {
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    public function createClient()
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    true,
                    null,
                    2
                ],
                "s" => [
                    [
                        "t" => "Логическое",
                        "n" => "ВызовИзБраузера"
                    ],
                    [
                        "t" => "Строка",
                        "n" => "ИдентификаторФизЛица"
                    ],
                    [
                        "t" => "Число целое",
                        "n" => "Раздел"
                    ]
                ],
                "_type" => "record"
            ],
            "ИмяМетода" => "online"
        ];

        $response = $this->send('CRMClients.CreateClient', $params);

        if (!$response->ok()) {
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    public function createContact($id, $contractorId)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    "person",
                    $id,
                    10,
                    true,
                    $contractorId
                ],
                "s" => [
                    [
                        "t" => "Строка",
                        "n" => "ContactOwner"
                    ],
                    [
                        "t" => "Число целое",
                        "n" => "PrivatePerson"
                    ],
                    [
                        "t" => "Число целое",
                        "n" => "Type"
                    ],
                    [
                        "t" => "Логическое",
                        "n" => "ВызовИзБраузера"
                    ],
                    [
                        "t" => "Число целое",
                        "n" => "Контрагент"
                    ]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ИмяМетода" => null
        ];

        $response = $this->send('ClientContactNew.Create', $params);

        if (!$response->ok()) {
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    public function saveContact($id, $contractorId)
    {
        $params = [
            "Запись" => [
                "d" => [
                    "person",
                    null,
                    $contractorId,
                    null,
                    "",
                    "",
                    null,
                    "",
                    [
                        "d" => [
                            [
                                null,
                                null,
                                "88005553537",
                                null,
                                false,
                                true,
                                "",
                                null,
                                $contractorId, // ЧастноеЛицо
                                $id, // Контрагент
                                [],
                                [],
                                13,
                                null,
                                null
                            ]
                        ],
                        "s" => [
                            [
                                "n" => "@Contact",
                                "t" => "Число целое"
                            ],
                            [
                                "n" => "FixationType",
                                "t" => "Логическое"
                            ],
                            [
                                "n" => "Value",
                                "t" => "Строка"
                            ],
                            [
                                "n" => "OrderNumber",
                                "t" => "Число целое"
                            ],
                            [
                                "n" => "Prefer",
                                "t" => "Логическое"
                            ],
                            [
                                "n" => "Active",
                                "t" => "Логическое"
                            ],
                            [
                                "n" => "Comment",
                                "t" => "Строка"
                            ],
                            [
                                "n" => "Access",
                                "t" => "Число целое"
                            ],
                            [
                                "n" => "Contractor",
                                "t" => [
                                    "n" => "Связь",
                                    "t" => "Лицо"
                                ]
                            ],
                            [
                                "n" => "PrivatePerson",
                                "t" => [
                                    "n" => "Связь",
                                    "t" => "Лицо"
                                ]
                            ],
                            [
                                "n" => "Bind",
                                "t" => [
                                    "n" => "Массив",
                                    "t" => "Текст"
                                ]
                            ],
                            [
                                "n" => "SupportedActions",
                                "t" => [
                                    "n" => "Массив",
                                    "t" => "Текст"
                                ]
                            ],
                            [
                                "n" => "Type",
                                "t" => "Число целое"
                            ],
                            [
                                "n" => "AddNumber",
                                "t" => "Строка"
                            ],
                            [
                                "t" => "Логическое",
                                "n" => "Removed"
                            ]
                        ],
                        "_type" => "recordset",
                        "f" => 1
                    ],
                    null,
                    [],
                    null,
                    null,
                    null,
                    null,
                    null,
                    [],
                    null,
                    null,
                    true,
                    null,
                    null,
                    null,
                    null,
                    null,
                    false,
                    null
                ],
                "s" => [
                    [
                        "n" => "ContactOwner",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "@Представитель",
                        "t" => "Число целое"
                    ],
                    [
                        "n" => "Контрагент",
                        "t" => "Число целое"
                    ],
                    [
                        "n" => "ЧастноеЛицо",
                        "t" => "Число целое"
                    ],
                    [
                        "n" => "ФИО",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "Должность",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "ЛПР",
                        "t" => "Логическое"
                    ],
                    [
                        "n" => "Примечание",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "ContactData",
                        "t" => "Выборка"
                    ],
                    [
                        "n" => "idSPP",
                        "t" => "Число целое"
                    ],
                    [
                        "n" => "MobileArray",
                        "t" => [
                            "n" => "Массив",
                            "t" => "Текст"
                        ]
                    ],
                    [
                        "n" => "Имя",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "Отчество",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "Фамилия",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "ДатаРождения",
                        "t" => "Дата"
                    ],
                    [
                        "n" => "Пол",
                        "t" => "Число целое"
                    ],
                    [
                        "n" => "SupportedActions",
                        "t" => [
                            "n" => "Массив",
                            "t" => "Текст"
                        ]
                    ],
                    [
                        "n" => "Название",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "ParentID",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "ВызовИзБраузера",
                        "t" => "Логическое"
                    ],
                    [
                        "n" => "WarnWhenDouble",
                        "t" => "Логическое"
                    ],
                    [
                        "n" => "ID",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "ИдентификаторФизЛица",
                        "t" => "UUID"
                    ],
                    [
                        "n" => "SelectedThemes",
                        "t" => [
                            "n" => "Массив",
                            "t" => "Число целое"
                        ]
                    ],
                    [
                        "n" => "ExcludedThemes",
                        "t" => [
                            "n" => "Массив",
                            "t" => "Число целое"
                        ]
                    ],
                    [
                        "n" => "MoreThanOneTheme",
                        "t" => "Логическое"
                    ],
                    [
                        "t" => "Логическое",
                        "n" => "somethingChanged"
                    ]
                ],
                "_type" => "record",
                "f" => 0
            ]
        ];

        $response = $this->send('ClientContactNew.Save', $params);

        if (!$response->ok()) {
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    public function saveClient($id, $contactorId, $guid)
    {
        $params = [
            "Запись" => [
                "d" => [
                    $contactorId,
                    [
                        null,
                        null,
                        true,
                        true
                    ],
                    null,
                    null,
                    null,
                    "111",
                    "222",
                    "333",
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    [
                        null
                    ],
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    21,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    "Россия",
                    null,
                    null,
                    "643",
                    4,
                    null,
                    [
                        null,
                        null,
                        null,
                        null,
                        null,
                        null,
                        null,
                        null,
                        null,
                        null
                    ],
                    null,
                    null,
                    $id,
                    "\"ЧастноеЛицо\"",
                    "",
                    "{}",
                    $id,
                    null,
                    null,
                    null,
                    0,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    null,
                    "(1,88)",
                    "(35,32)",
                    "(1,115)",
                    null,
                    $id,
                    $contactorId,
                    $guid,
                    null,
                    $guid,
                    [
                        "d" => [
                            null,
                            null
                        ],
                        "s" => [
                            [
                                "n" => "Меркурий ХС",
                                "t" => "Строка"
                            ],
                            [
                                "n" => "Меркурий Пл",
                                "t" => "Строка"
                            ]
                        ],
                        "_type" => "record",
                        "f" => 1
                    ],
                    2,
                    null,
                    null,
                    [
                        "d" => [],
                        "s" => [],
                        "_type" => "recordset",
                        "f" => 2
                    ],
                    [
                        "d" => [],
                        "s" => [
                            [
                                "n" => "CardId",
                                "t" => "Число целое"
                            ],
                            [
                                "n" => "CardNumber",
                                "t" => "Строка"
                            ],
                            [
                                "n" => "CardStartDate",
                                "t" => "Строка"
                            ],
                            [
                                "n" => "EmissionId",
                                "t" => "Число целое"
                            ],
                            [
                                "n" => "EmissionName",
                                "t" => "Строка"
                            ],
                            [
                                "n" => "Locked",
                                "t" => "Логическое"
                            ],
                            [
                                "n" => "RequiredClientData",
                                "t" => [
                                    "n" => "Массив",
                                    "t" => "Логическое"
                                ]
                            ],
                            [
                                "t" => "Число целое",
                                "n" => "CardTypeId"
                            ],
                            [
                                "t" => "Строка",
                                "n" => "CardTypeName"
                            ]
                        ],
                        "_type" => "recordset",
                        "f" => 3
                    ],
                    [
                        "d" => [],
                        "s" => [
                            [
                                "n" => "@ВидЦены",
                                "t" => "Число целое"
                            ],
                            [
                                "n" => "Название",
                                "t" => "Строка"
                            ],
                            [
                                "n" => "Тип",
                                "t" => "Число целое"
                            ],
                            [
                                "n" => "Используется",
                                "t" => "Число целое"
                            ],
                            [
                                "n" => "ТипСвязи",
                                "t" => "Число целое"
                            ]
                        ],
                        "_type" => "recordset",
                        "f" => 4
                    ],
                    null,
                    2,
                    true,
                    null,
                    [],
                    [],
                    false,
                    false,
                    [
                        "d" => [
                            [
                                "2.folder",
                                null,
                                $contactorId,
                                null,
                                1,
                                "Клиенты",
                                null,
                                null,
                                null,
                                "folder",
                                null,
                                null,
                                null,
                                2,
                                null,
                                "Исключить из папки",
                                4
                            ]
                        ],
                        "s" => [
                            [
                                "n" => "Key",
                                "t" => "Строка"
                            ],
                            [
                                "n" => "ClientUUID",
                                "t" => "UUID"
                            ],
                            [
                                "n" => "ClientID",
                                "t" => "Число целое"
                            ],
                            [
                                "n" => "Tag",
                                "t" => "UUID"
                            ],
                            [
                                "n" => "Type",
                                "t" => "Число целое"
                            ],
                            [
                                "n" => "Name",
                                "t" => "Строка"
                            ],
                            [
                                "n" => "Author",
                                "t" => "UUID"
                            ],
                            [
                                "n" => "DateTime",
                                "t" => "Дата и время"
                            ],
                            [
                                "n" => "IsHashtag",
                                "t" => "Логическое"
                            ],
                            [
                                "n" => "Style",
                                "t" => "Строка"
                            ],
                            [
                                "n" => "CanEdit",
                                "t" => "Логическое"
                            ],
                            [
                                "n" => "CanDelete",
                                "t" => "Логическое"
                            ],
                            [
                                "n" => "CanShare",
                                "t" => "Логическое"
                            ],
                            [
                                "n" => "FolderID",
                                "t" => "Число целое"
                            ],
                            [
                                "n" => "Icon",
                                "t" => "Строка"
                            ],
                            [
                                "n" => "IconTitle",
                                "t" => "Строка"
                            ],
                            [
                                "n" => "KindOf",
                                "t" => "Число целое"
                            ]
                        ],
                        "_type" => "recordset",
                        "f" => 5
                    ]
                ],
                "s" => [
                    [
                        "n" => "@Лицо",
                        "t" => "Число целое"
                    ],
                    [
                        "n" => "Состояния",
                        "t" => [
                            "n" => "Флаги",
                            "s" => [
                                "0" => "Ранее нигде не работал",
                                "1" => "Отправлено приглашение",
                                "2" => "Надежный клиент",
                                "3" => "Разрешены рассылки"
                            ],
                            "sl" => [
                                "0" => "Ранее нигде не работал",
                                "1" => "Отправлено приглашение",
                                "2" => "Надежный клиент",
                                "3" => "Разрешены рассылки"
                            ]
                        ]
                    ],
                    [
                        "n" => "ОКАТОМестраРождения",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "СтранаМестаРождения",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "Примечание",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "Фамилия",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "Имя",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "Отчество",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "Photo",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "OriginalPhoto",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "EngSurname",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "EngName",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "EngPatronymic",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "LangCode",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "LangMain",
                        "t" => "Идентификатор",
                        "s" => "Иерархия"
                    ],
                    [
                        "n" => "LangMain$",
                        "t" => "Логическое",
                        "s" => "Иерархия"
                    ],
                    [
                        "n" => "Пол",
                        "t" => [
                            "n" => "Перечисляемое",
                            "s" => [
                                "0" => "Мужской",
                                "1" => "Женский",
                                "null" => null
                            ],
                            "sl" => [
                                "0" => "Мужской",
                                "1" => "Женский",
                                "null" => null
                            ]
                        ]
                    ],
                    [
                        "n" => "ДатаРождения",
                        "t" => "Дата"
                    ],
                    [
                        "n" => "АдресПрописки",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "АдресФакт",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "АдресМестаРождения",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "ДокументВид",
                        "t" => [
                            "n" => "Перечисляемое",
                            "s" => [
                                "1" => "Паспорт гражданина СССР",
                                "2" => "Загранпаспорт гражданина СССР",
                                "3" => "Свидетельство о рождении",
                                "4" => "Удостоверение личности офицера",
                                "5" => "Справка об освобождении из места лишения свободы",
                                "6" => "Паспорт Минморфлота",
                                "7" => "Военный билет солдата (матроса, сержанта, старшины)",
                                "8" => "Временное удостоверение, выданное взамен военного билета",
                                "9" => "Дипломатический паспорт гражданина РФ",
                                "10" => "Иностранный паспорт",
                                "11" => "Свидетельство о регистрации ходатайства иммигранта о признании его беженцем",
                                "12" => "Вид на жительство",
                                "13" => "Удостоверение беженца в РФ",
                                "14" => "Временное удостоверение личности гражданина РФ",
                                "15" => "Разрешение на временное проживание в РФ",
                                "18" => "Свидетельство о предоставлении временного убежища на территории РФ (до 2013г.)",
                                "19" => "Свидетельство о предоставлении временного убежища на территории РФ",
                                "21" => "Паспорт гражданина РФ",
                                "22" => "Загранпаспорт гражданина РФ",
                                "23" => "Свидетельство о рождении, выданное уполномоченным органом иностранного государства",
                                "24" => "Удостоверение личности военнослужащего РФ",
                                "26" => "Паспорт моряка",
                                "27" => "Военный билет офицера запаса",
                                "60" => "Документы, подтверждающие факт регистрации по месту жительства (пребывания)",
                                "61" => "Свидетельство о регистрации по месту жительства",
                                "62" => "Вид на жительство иностранного гражданина",
                                "63" => "Свидетельство о регистрации по месту пребывания",
                                "81" => "Свидетельство о смерти",
                                "91" => "Иные документы, выдаваемые органами МВД",
                                "93" => "Служебный паспорт гражданина Российской Федерации",
                                "103" => "Виза",
                                "104" => "Дипломатический паспорт иностранного гражданина",
                                "106" => "Иностранное свидетельство о рождении",
                                "107" => "Удостоверение личности гражданина государств - участников СНГ",
                                "108" => "Служебный паспорт",
                                "109" => "Вид на жительство ЛБГ",
                                "110" => "Иной документ, удостоверяющий личность",
                                "111" => "Вид на жительство ЛБГ(с биометрией)",
                                "112" => "Разрешение на временное проживание ЛБГ(штамп)",
                                "113" => "Удостоверение личности гражданина республики Казахстан",
                                "116" => "Справка о рассмотрении заявления предоставлении временного убежища на территории РФ",
                                "117" => "Национальный загранпаспорт",
                                "118" => "Паспорт негражданина",
                                "120" => "Свидетельство о возвращении",
                                "121" => "Удостоверение лица без гражданства",
                                "125" => "Разрешение на временное проживание ЛБГ (бланк)",
                                "126" => "Удостоверение вынужденного переселенца (номер 6 знаков)",
                                "127" => "Свидетельство о рассмотрении ходатайства о признании беженцем по существу",
                                "129" => "Вид на жительство ЛБГ образца СССР",
                                "131" => "Справка о рассмотрении заявления о предоставлении временного убежища на территории РФ серии ВУ-РУ",
                                "204" => "Загранпаспорт нового поколения (с биометрией)",
                                "213" => "Удостоверение беженца в Российской Федерации старого образца",
                                "null" => null
                            ],
                            "sl" => [
                                "1" => "Паспорт гражданина СССР",
                                "2" => "Загранпаспорт гражданина СССР",
                                "3" => "Свидетельство о рождении",
                                "4" => "Удостоверение личности офицера",
                                "5" => "Справка об освобождении из места лишения свободы",
                                "6" => "Паспорт Минморфлота",
                                "7" => "Военный билет солдата (матроса, сержанта, старшины)",
                                "8" => "Временное удостоверение, выданное взамен военного билета",
                                "9" => "Дипломатический паспорт гражданина РФ",
                                "10" => "Иностранный паспорт",
                                "11" => "Свидетельство о регистрации ходатайства иммигранта о признании его беженцем",
                                "12" => "Вид на жительство",
                                "13" => "Удостоверение беженца в РФ",
                                "14" => "Временное удостоверение личности гражданина РФ",
                                "15" => "Разрешение на временное проживание в РФ",
                                "18" => "Свидетельство о предоставлении временного убежища на территории РФ (до 2013г.)",
                                "19" => "Свидетельство о предоставлении временного убежища на территории РФ",
                                "21" => "Паспорт гражданина РФ",
                                "22" => "Загранпаспорт гражданина РФ",
                                "23" => "Свидетельство о рождении, выданное уполномоченным органом иностранного государства",
                                "24" => "Удостоверение личности военнослужащего РФ",
                                "26" => "Паспорт моряка",
                                "27" => "Военный билет офицера запаса",
                                "60" => "Документы, подтверждающие факт регистрации по месту жительства (пребывания)",
                                "61" => "Свидетельство о регистрации по месту жительства",
                                "62" => "Вид на жительство иностранного гражданина",
                                "63" => "Свидетельство о регистрации по месту пребывания",
                                "81" => "Свидетельство о смерти",
                                "91" => "Иные документы, выдаваемые органами МВД",
                                "93" => "Служебный паспорт гражданина Российской Федерации",
                                "103" => "Виза",
                                "104" => "Дипломатический паспорт иностранного гражданина",
                                "106" => "Иностранное свидетельство о рождении",
                                "107" => "Удостоверение личности гражданина государств - участников СНГ",
                                "108" => "Служебный паспорт",
                                "109" => "Вид на жительство ЛБГ",
                                "110" => "Иной документ, удостоверяющий личность",
                                "111" => "Вид на жительство ЛБГ(с биометрией)",
                                "112" => "Разрешение на временное проживание ЛБГ(штамп)",
                                "113" => "Удостоверение личности гражданина республики Казахстан",
                                "116" => "Справка о рассмотрении заявления предоставлении временного убежища на территории РФ",
                                "117" => "Национальный загранпаспорт",
                                "118" => "Паспорт негражданина",
                                "120" => "Свидетельство о возвращении",
                                "121" => "Удостоверение лица без гражданства",
                                "125" => "Разрешение на временное проживание ЛБГ (бланк)",
                                "126" => "Удостоверение вынужденного переселенца (номер 6 знаков)",
                                "127" => "Свидетельство о рассмотрении ходатайства о признании беженцем по существу",
                                "129" => "Вид на жительство ЛБГ образца СССР",
                                "131" => "Справка о рассмотрении заявления о предоставлении временного убежища на территории РФ серии ВУ-РУ",
                                "204" => "Загранпаспорт нового поколения (с биометрией)",
                                "213" => "Удостоверение беженца в Российской Федерации старого образца",
                                "null" => null
                            ]
                        ]
                    ],
                    [
                        "n" => "ДокументСерия",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "ДокументНомер",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "ДокументКемВыдан",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "ДокументКодПодразделения",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "НомерСтраховогоСвидетельства",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "ДокументДатаВыдачи",
                        "t" => "Дата"
                    ],
                    [
                        "n" => "ИНН",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "СтатусРезидента",
                        "t" => [
                            "n" => "Перечисляемое",
                            "s" => [
                                "1" => "налогоплательщик является налоговым резидентом Российской Федерации",
                                "2" => "налогоплательщик не является налоговым резидентом Российской Федерации",
                                "3" => "налогоплательщик не является налоговым резидентом Российской Федерации, но признается в качестве высококвалифицированного специалиста",
                                "4" => "участник программы по переселению соотечественников в РФ (член судна, плавающего под флагом РФ)",
                                "5" => "беженец (временное убежище в РФ)",
                                "6" => "иностранный гражданин (патент)",
                                "null" => null
                            ],
                            "sl" => [
                                "1" => "налогоплательщик является налоговым резидентом Российской Федерации",
                                "2" => "налогоплательщик не является налоговым резидентом Российской Федерации",
                                "3" => "налогоплательщик не является налоговым резидентом Российской Федерации, но признается в качестве высококвалифицированного специалиста",
                                "4" => "участник программы по переселению соотечественников в РФ (член судна, плавающего под флагом РФ)",
                                "5" => "беженец (временное убежище в РФ)",
                                "6" => "иностранный гражданин (патент)",
                                "null" => null
                            ]
                        ]
                    ],
                    [
                        "n" => "Гражданство",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "КодАдресаПрожРез",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "АдресПрожРез",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "ГражданствоКод",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "Тип",
                        "t" => [
                            "n" => "Перечисляемое",
                            "s" => [
                                "0" => "Сотрудник",
                                "1" => "Представитель",
                                "2" => "Удаленный сотрудник",
                                "3" => "Служебный пользователь",
                                "4" => "Получатель платежей",
                                "5" => "Кандидат",
                                "6" => "Сотрудник сторонней организации",
                                "20" => "Локализованные данные частного лица"
                            ],
                            "sl" => [
                                "0" => "Сотрудник",
                                "1" => "Представитель",
                                "2" => "Удаленный сотрудник",
                                "3" => "Служебный пользователь",
                                "4" => "Получатель платежей",
                                "5" => "Кандидат",
                                "6" => "Сотрудник сторонней организации",
                                "20" => "Локализованные данные частного лица"
                            ]
                        ]
                    ],
                    [
                        "n" => "ИдентификаторФизЛица",
                        "t" => "UUID"
                    ],
                    [
                        "n" => "Категория",
                        "t" => [
                            "n" => "Флаги",
                            "s" => [
                                "0" => "Менеджер",
                                "1" => "Менеджер с должности",
                                "2" => "Инженер",
                                "3" => "Инженер с должности",
                                "4" => "Опубликован ДР",
                                "5" => "Фото закреплено",
                                "6" => "Онлайн запись",
                                "7" => "Участник инвест проекта КО",
                                "8" => "Публикация",
                                "9" => "Склонять ФИО в печатных формах"
                            ],
                            "sl" => [
                                "0" => "Менеджер",
                                "1" => "Менеджер с должности",
                                "2" => "Инженер",
                                "3" => "Инженер с должности",
                                "4" => "Опубликован ДР",
                                "5" => "Фото закреплено",
                                "6" => "Онлайн запись",
                                "7" => "Участник инвест проекта КО",
                                "8" => "Публикация",
                                "9" => "Склонять ФИО в печатных формах"
                            ]
                        ]
                    ],
                    [
                        "n" => "ИдентификаторКаталога",
                        "t" => "UUID"
                    ],
                    [
                        "n" => "ПредыдущаяФамилия",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "Лицо.@Лицо",
                        "t" => "Число целое"
                    ],
                    [
                        "n" => "Лицо.Лицо_",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "Лицо.Название",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "Лицо.params",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "ЧастноеЛицоРасширение.@Лицо",
                        "t" => "Число целое"
                    ],
                    [
                        "n" => "ЧастноеЛицоРасширение.НомерМашины",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "ЧастноеЛицоРасширение.СемейноеПоложение",
                        "t" => [
                            "n" => "Перечисляемое",
                            "s" => [
                                "0" => "Никогда не состоял (не состояла в браке)",
                                "1" => "Состоит в зарегистрированном браке",
                                "2" => "Состоит в незарегистрированном браке",
                                "3" => "Вдовец (вдова)",
                                "4" => "Разведен (разведена)",
                                "5" => "Разошелся (разошлась)",
                                "null" => null
                            ],
                            "sl" => [
                                "0" => "Никогда не состоял (не состояла в браке)",
                                "1" => "Состоит в зарегистрированном браке",
                                "2" => "Состоит в незарегистрированном браке",
                                "3" => "Вдовец (вдова)",
                                "4" => "Разведен (разведена)",
                                "5" => "Разошелся (разошлась)",
                                "null" => null
                            ]
                        ]
                    ],
                    [
                        "n" => "ЧастноеЛицоРасширение.ВоинскийУчет",
                        "t" => [
                            "n" => "Перечисляемое",
                            "s" => [
                                "0" => "Военнообязанный",
                                "1" => "Невоеннообязанный",
                                "2" => "Военнослужащий",
                                "3" => "Призывник",
                                "null" => null
                            ],
                            "sl" => [
                                "0" => "Военнообязанный",
                                "1" => "Невоеннообязанный",
                                "2" => "Военнослужащий",
                                "3" => "Призывник",
                                "null" => null
                            ]
                        ]
                    ],
                    [
                        "n" => "ЧастноеЛицоРасширение.НалоговыйСтатус",
                        "t" => [
                            "n" => "Перечисляемое",
                            "s" => [
                                "0" => "Не требует расчета",
                                "1" => "Без особого статуса",
                                "2" => "Высококвалифицированный специалист",
                                "3" => "Временное убежище",
                                "4" => "Программа переселения соотечественников",
                                "5" => "Беженец",
                                "6" => "Гражданин страны члена ЕАЭС",
                                "null" => null
                            ],
                            "sl" => [
                                "0" => "Не требует расчета",
                                "1" => "Без особого статуса",
                                "2" => "Высококвалифицированный специалист",
                                "3" => "Временное убежище",
                                "4" => "Программа переселения соотечественников",
                                "5" => "Беженец",
                                "6" => "Гражданин страны члена ЕАЭС",
                                "null" => null
                            ]
                        ]
                    ],
                    [
                        "n" => "ЧастноеЛицоРасширение.ФармЛицензияНомер",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "ЧастноеЛицоРасширение.ФармЛицензияДата",
                        "t" => "Дата"
                    ],
                    [
                        "n" => "ЧастноеЛицоРасширение.NorthStart",
                        "t" => "Дата"
                    ],
                    [
                        "n" => "ЧастноеЛицоРасширение.ВодительскоеУдостоверение",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "ЧастноеЛицоРасширение.ВодительскоеУдостоверениеДата",
                        "t" => "Дата"
                    ],
                    [
                        "n" => "ЧастноеЛицоРасширение.DrivingLicenseIssueDate",
                        "t" => "Дата"
                    ],
                    [
                        "n" => "ЧастноеЛицоРасширение.РабочееМесто",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "ЧастноеЛицоРасширение.НомерСвидетельстваТС",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "ЧастноеЛицоРасширение.Config",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "ЧастноеЛицоРасширение.Params",
                        "t" => "JSON-объект"
                    ],
                    [
                        "n" => "ctid",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "ctid_Лицо",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "ctid_ЧастноеЛицоРасширение",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "NumberSNILS",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "ЧастноеЛицо",
                        "t" => "Число целое"
                    ],
                    [
                        "n" => "Контрагент",
                        "t" => "Число целое"
                    ],
                    [
                        "n" => "LocalGUID",
                        "t" => "UUID"
                    ],
                    [
                        "n" => "ExternalId",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "ContractorUUID",
                        "t" => "UUID"
                    ],
                    [
                        "n" => "ExtSystemCodes",
                        "t" => "Запись"
                    ],
                    [
                        "n" => "Раздел",
                        "t" => "Число целое"
                    ],
                    [
                        "n" => "SuggestActivationFlag",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "ResponsibleData",
                        "t" => "Запись"
                    ],
                    [
                        "n" => "ContactData",
                        "t" => "Выборка"
                    ],
                    [
                        "n" => "DiscountCards",
                        "t" => "Выборка"
                    ],
                    [
                        "n" => "DiscountsForBuyer",
                        "t" => "Выборка"
                    ],
                    [
                        "n" => "Удостоверения",
                        "t" => "Выборка"
                    ],
                    [
                        "n" => "Type",
                        "t" => "Число целое"
                    ],
                    [
                        "n" => "HasPersonalData",
                        "t" => "Логическое"
                    ],
                    [
                        "n" => "НеОтображается",
                        "t" => "Логическое"
                    ],
                    [
                        "n" => "ЗначенияДопПолей",
                        "t" => "JSON-объект"
                    ],
                    [
                        "n" => "ДопПоля",
                        "t" => "JSON-объект"
                    ],
                    [
                        "n" => "Recommendations",
                        "t" => "Логическое"
                    ],
                    [
                        "n" => "Chats",
                        "t" => "Логическое"
                    ],
                    [
                        "n" => "TagsAndFolders",
                        "t" => "Выборка"
                    ]
                ],
                "_type" => "record",
                "f" => 0
            ]
        ];

        $response = $this->send('CRMClients.SaveClient', $params);

        if (!$response->ok()) {
            dd($response->body());
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    public function saveCustomer($name, $surname, $lastname, $phone)
    {
        $params = [
            "CustomerData" => [
                "d" => [
                    $surname,
                    $name,
                    $lastname,
                    [
                        [
                            'd' => [
                                $phone,
                                13
                            ],
                            's' => [
                                [
                                    "n" => "value",
                                    "t" => "Строка"
                                ],
                                [
                                    "n" => "type",
                                    "t" => "Число целое"
                                ],
                            ]
                        ]
                    ]
                ],
                "s" => [
                    [
                        "n" => "Surname",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "Name",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "Patronymic",
                        "t" => "Строка"
                    ],
                    [
                        "n" => "ContactData",
                        "t" => "Выборка"
                    ]
                ],
                "_type" => "record",
                "f" => 0
            ]
        ];
//        $params = [
//            "CustomerData" => [
//                "d" => [
//                    $surname,
//                    $name,
//                    $lastname,
//                    0,
//                    "Адрес физического лица"
//                ],
//                "s" => [
//                    [
//                        "n" => "Surname",
//                        "t" => "Строка"
//                    ],
//                    [
//                        "n" => "Name",
//                        "t" => "Строка"
//                    ],
//                    [
//                        "n" => "Patronymic",
//                        "t" => "Строка"
//                    ],
//                    [
//                        "n" => "Gender",
//                        "t" => "Число целое"
//                    ],
//                    [
//                        "n" => "Address",
//                        "t" => "Строка"
//                    ]
//                ],
//                "_type" => "record",
//                "f" => 0
//            ]
//        ];

        $response = $this->send('CRMClients.SaveCustomer', $params);

        if (!$response->ok()) {
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }

        return false;
    }

    // Закрыть лида/сделку (для Taviat.online)
    public function closeLead($leadId, $comment, $phaseDirectionId)
    {
        $leadData = $this->getLead($leadId);
        $params = [
            'd' => [
                'EventType' => 3,
                'LeadID' => $leadId,
                'RespID' => $leadData['Сотрудник'],
                'Comment' => $comment,
                'KindOfContact' => 0,
                'ContactType' => 1,
                //'CurrentPhaseId' => 47058608,
                //'CurrentPhaseId' => $leadData['Лид.Фаза'] ?? null,
                //'PhaseDirectionId' => 47058615,
                //'PhaseDirectionId' => 47238559,
                'PhaseDirectionId' => $phaseDirectionId, // id перехода
                //'DateTime' => $date_time,
            ],
            's' => [
                'EventType' => 'Число целое',
                'LeadID' => 'Число целое',
                'RespID' => 'Число целое',
                'Comment' => 'Строка',
                'KindOfContact' => 'Число целое',
                'ContactType' => 'Число целое',
                'CurrentPhaseId' => 'Число целое',
                'PhaseDirectionId' => 'Число целое',
                //'DateTime' => 'Дата и время',
            ],
            '_type' => 'record',
            'f' => 0
        ];
        $response = $this->send('CRMEvent.AddEvent', $params, 'EventData');
        if (!$response->ok()) {
            Log::info('Sbis sendMessage - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        return $response->object();
    }

    // Выгрузка заказов в СБИС
    public function ImportOrderInSbis($xml, $inn = null, $kpp = null)
    {
        if (empty($inn)) {
            $getInfoOrg = $this->getPointSale();
            $inn = $getInfoOrg[0]['ИНН'];
            $kpp = $getInfoOrg[0]['КПП'];
        }
        $params = [
            "Документ" => [
                "Тип" => "ЗаказВх",
                'НашаОрганизация' => [
                    'СвЮЛ' => [
                        'ИНН' => $inn,
                        'КПП' => $kpp,
                    ],
                ],
                'Вложение' => [
                    'Файл' => [
                        'Имя' => 'Заказ с сайта.xml',
                        'ДвоичныеДанные' => base64_encode(iconv("UTF-8", "windows-1251//IGNORE", $xml)),
                    ],
                ],
            ],
        ];
        $response = $this->send('СБИС.ЗаписатьДокумент', $params);
        if (!$response->ok()) {
            Log::info('СБИС.ЗаписатьДокумент - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return $json;
    }

    // Получить список номенклатуры из документов по ЭДО внутрений
    public function getNomInDocEdoo($id)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    false,
                    false,
                    true,
                    true,
                    true,
                    true,
                    false,
                    false,
                    true,
                    true,
                    true,
                    "WHDCore/Controls/nomList =>columns-InflowMainList-v1",
                    true,
                    true,
                    false,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    false,
                    true,
                    null,
                    true,
                    $id,
                    0,
                    false
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "add_nom_ipack"],
                    ["t" => "Логическое", "n" => "add_rnpt_data"],
                    ["t" => "Логическое", "n" => "calc_actual"],
                    ["t" => "Логическое", "n" => "calc_category"],
                    ["t" => "Логическое", "n" => "calc_category_totals"],
                    ["t" => "Логическое", "n" => "calc_category_wo_union"],
                    ["t" => "Логическое", "n" => "calc_char_groups"],
                    ["t" => "Логическое", "n" => "calc_characteristic_params"],
                    ["t" => "Логическое", "n" => "calc_doc_links_changed"],
                    ["t" => "Логическое", "n" => "calc_exp_date"],
                    ["t" => "Логическое", "n" => "calc_full_sn_totals"],
                    ["t" => "Логическое", "n" => "calc_has_deviations"],
                    ["t" => "Логическое", "n" => "calc_in_nom_factor_data"],
                    ["t" => "Логическое", "n" => "calc_init_measure"],
                    ["t" => "Логическое", "n" => "calc_item_type"],
                    ["t" => "Логическое", "n" => "calc_markup_by_self_cost"],
                    ["t" => "Логическое", "n" => "calc_mu_localization"],
                    ["t" => "Логическое", "n" => "calc_not_sync"],
                    ["t" => "Логическое", "n" => "calc_pics"],
                    ["t" => "Логическое", "n" => "calc_product_info"],
                    ["t" => "Логическое", "n" => "calc_prt_price"],
                    ["t" => "Логическое", "n" => "calc_rp_add_fields_simple"],
                    ["t" => "Логическое", "n" => "calc_rp_add_fields_ui_view"],
                    ["t" => "Логическое", "n" => "calc_serial_number_inv"],
                    ["t" => "Логическое", "n" => "calc_totals"],
                    ["t" => "Логическое", "n" => "calc_traceability_visible"],
                    ["t" => "Строка", "n" => "column_config_id"],
                    ["t" => "Логическое", "n" => "extract_gtin_and_article"],
                    ["t" => "Логическое", "n" => "fill_expense_items"],
                    ["t" => "Логическое", "n" => "include_sn_pack_in_count"],
                    ["t" => "Логическое", "n" => "init_base_markup_data"],
                    ["t" => "Логическое", "n" => "init_cost_vat_wo_recalc"],
                    ["t" => "Логическое", "n" => "init_currency_data"],
                    ["t" => "Логическое", "n" => "init_last_self_cost"],
                    ["t" => "Логическое", "n" => "init_linked_docs_totals"],
                    ["t" => "Логическое", "n" => "init_recorded_characteristics_sets"],
                    ["t" => "Логическое", "n" => "init_selected_nom_set_data"],
                    ["t" => "Логическое", "n" => "new_list"],
                    ["t" => "Строка", "n" => "parentId"],
                    ["t" => "Логическое", "n" => "set_category_in_totals"],
                    ["t" => "Число целое", "n" => "СкладскойДокумент"],
                    ["t" => "Число целое", "n" => "Тип"],
                    ["t" => "Логическое", "n" => "ЭтоРасходы"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    "bothways",
                    true,
                    501,
                    [
                        "d" => [
                            0
                        ],
                        "s" => [["t" => "Число целое", "n" => "@НоменклатураДокумента"]],
                        "_type" => "record",
                        "f" => 1
                    ]
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Запись", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []

        ];
        $response = $this->send('РеалВх.DocNomListForUser', $params);
        if (!$response->ok()) {
            Log::info(
                'Delivery.Write - РеалВх.DocNomListForUser' . json_encode($response->object(), JSON_UNESCAPED_UNICODE)
            );
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // СБИС.СписокАккаунтов
    public function getAccountList()
    {
        $this->setApiUrl('https://online.sbis.ru/auth/service/');
        $response = $this->send('СБИС.СписокАккаунтов', []);
        $json = $response->json();
        $list = $json['result']['Список'] ?? [];
        if (!empty($list) && is_array($list) && count($list) > 1) {
            send2Dev('Более 2 аккаунтов в подключении. User id = ' . $this->sbisAccount->user_id . ', ' . get_domen());
        }
        return $json;
    }

    //Получить список организаций и точек продаж
    public function getPointSale($id = null)
    {
        if (empty($id)) {
            $metod = 'OurOrg.ListOrgs';
            $params = [
                "Фильтр" => [
                    "d" => [
                        99,
                        true,
                        null,
                        [
                            "Наши компании"
                        ]
                    ],
                    "s" => [
                        ["t" => "Число целое", "n" => "Limit"],
                        ["t" => "Логическое", "n" => "OnlyRoot"],
                        ["t" => "Строка", "n" => "RootId"],
                        ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "ScopesAreas"]
                    ],
                    "_type" => "record",
                    "f" => 0
                ],
                "Сортировка" => null,
                "Навигация" => null,
                "ДопПоля" => null

            ];
        } else {
            $metod = 'OurOrg.listWithBranches';
            $params = [
                "Фильтр" => [
                    "d" => [
                        null,
                        ["Наши компании"],
                        true,
                        true,
                        $id,
                        true,
                        true,
                        true,
                        true,
                        0,
                        true,
                        true
                    ],
                    "s" => [
                        ["t" => "Строка", "n" => "HierarchyUniqueId"],
                        ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "ScopesAreas"],
                        ["t" => "Логическое", "n" => "add_data_from_spp"],
                        ["t" => "Логическое", "n" => "cutZipCode"],
                        ["t" => "Число целое", "n" => "head_our_org_id"],
                        ["t" => "Логическое", "n" => "loadCashbox"],
                        ["t" => "Логическое", "n" => "loadTotalEmployees"],
                        ["t" => "Логическое", "n" => "loadWorkplace"],
                        ["t" => "Логическое", "n" => "load_data_head_org"],
                        ["t" => "Число целое", "n" => "orgType"],
                        ["t" => "Логическое", "n" => "return_folder"],
                        ["t" => "Логическое", "n" => "withAffiliates"]
                    ],
                    "_type" => "record",
                    "f" => 0
                ],
                "Сортировка" => null,
                "Навигация" => [
                    "d" => [
                        true,
                        30,
                        0
                    ],
                    "s" => [
                        ["t" => "Логическое", "n" => "ЕстьЕще"],
                        ["t" => "Число целое", "n" => "РазмерСтраницы"],
                        ["t" => "Число целое", "n" => "Страница"]
                    ],
                    "_type" => "record",
                    "f" => 0
                ],
                "ДопПоля" => []

            ];
        }
        $response = $this->send($metod, $params);
        if (!$response->ok()) {
            Log::info($metod . ' - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Список наших организаций
    public function getOurOrgListWithBranches(string $hierarchyUniqueId = '')
    {
        $hierarchyUniqueId = !empty($hierarchyUniqueId) ? $hierarchyUniqueId : null;
        $params = [
            "Фильтр" => [
                "d" => [
                    1,
                    [
                        "Контрагент.Название",
                        "АдресЮридический",
                        "АдресФактический",
                        "ИНН",
                        "КПП",
                        "CommercialName",
                        "НазваниеПолное",
                        "ShortName",
                        "ОКПО"
                    ],
                    $hierarchyUniqueId,
                    true,
                    4,
                    ["Наши компании"],
                    false,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    false,
                    true,
                    true,
                    0,
                    null,
                    true,
                    true,
                    true
                ],
                "s" => [
                    ["t" => "Число целое", "n" => "CountOrgInMore"],
                    [
                        "t" => [
                            "n" => "Массив",
                            "t" => "Строка"
                        ],
                        "n" => "FindFields"
                    ],
                    ["t" => "Строка", "n" => "HierarchyUniqueId"],
                    ["t" => "Логическое", "n" => "LoadDirector"],
                    ["t" => "Число целое", "n" => "NameType"],
                    [
                        "t" => [
                            "n" => "Массив",
                            "t" => "Строка"
                        ],
                        "n" => "ScopesAreas"
                    ],
                    ["t" => "Логическое", "n" => "addHead"],
                    ["t" => "Логическое", "n" => "addTypeTaxation"],
                    ["t" => "Логическое", "n" => "add_data_from_spp"],
                    ["t" => "Логическое", "n" => "addressCommasDecorate"],
                    ["t" => "Логическое", "n" => "checkEmptyRegistry"],
                    ["t" => "Логическое", "n" => "cutZipCode"],
                    ["t" => "Логическое", "n" => "folderIsOrg"],
                    ["t" => "Логическое", "n" => "isTile"],
                    ["t" => "Логическое", "n" => "loadRegion"],
                    ["t" => "Логическое", "n" => "load_data_head_org"],
                    ["t" => "Число целое", "n" => "orgType"],
                    ["t" => "Строка", "n" => "regionsCode"],
                    ["t" => "Логическое", "n" => "returnPath"],
                    ["t" => "Логическое", "n" => "withAffiliates"],
                    ["t" => "Логическое", "n" => "DisclosureIfSmallNumberCompanies"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    "bothways",
                    true,
                    1001,
                    null
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Строка", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];
        $response = $this->send('OurOrg.listWithBranches', $params);
        if (!$response->ok()) {
            Log::info('OurOrg.listWithBranches' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Прочитать точку продаж
    public function readSalePoint($id)
    {
        $params = [
            "methodName" => "CardData",
            "params" => [
                "id" => $id,
                "additionalFields" => [
                    "addTZRecordSet" => true,
                    "addCountryCodeRs" => true,
                    "addShortLink" => true,
                    "addRespPersons" => true,
                    "addCashBoxInfo" => true,
                    "addWhInfo" => true,
                    "addContacts" => true,
                    "addCorrections" => true,
                    "compareAddress" => true,
                    "addBankAccountsCount" => true
                ]
            ]
        ];

        $response = $this->send('OurOrg.SabyReadCard', $params);

        if (!$response->ok()) {
            Log::info('OurOrg.SabyReadCard' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Информация об адресе + координаты
    public function getAddrCoordinates($addr)
    {
        $params = [
            "Address" => $addr,
            "UseProxy" => true
        ];

        $response = $this->send('Geo.saveCoordinates', $params);

        if (!$response->ok()) {
            Log::info('Geo.saveCoordinates' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    public function getWarehousesSalePoint($id = null)
    {
        $params = [
            'Id' => $id,
        ];
        $response = $this->send('Warehouse.ReadCard', $params);
        if (!$response->ok()) {
            Log::info('Warehouse.ReadCard' . ' - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Получить остатки по складам
    public function getWarehousesBalanceNom($id = null)
    {

        $params = [
            "Фильтр" => [
                "d" => [
                    date('Y-m-d'),
                    $id
                ],
                "s" => [
                    ["t" => "Дата", "n" => "ФильтрДата"],
                    ["t" => "Число целое", "n" => "ФильтрНоменклатура"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => null,
            "ДопПоля" => []

        ];
        $response = $this->send('ДиалогОстатков.GetPanelData', $params);
        if (!$response->ok()) {
            Log::info('ДиалогОстатков.GetPanelData' . ' - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }


    // Установить пометки для наряда
    public function addServiceOrdersNotes($serviceOrderId, $noteName)
    {
        $params = [
            "ИдО" => $serviceOrderId,
            "Пометки" => [
                $noteName === "Важный",
                $noteName === "Плюс",
                $noteName === "Красные",
                $noteName === "Зеленые",
                $noteName === "Синие",
                $noteName === "Желтые",
                $noteName === "Закрыто от изменений"
            ]
        ];
        $response = $this->send('Документ.ЗаписатьПометки', $params);
        if (!$response->ok()) {
            $this->checkSbisPermissions($response);
            Log::info('Sbis Документ.ЗаписатьПометки - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return $response->json();
    }

    // Импорт документов в наряд
    public function addAttachmentToDoc($serviceOrderId, $documentContent, $fileName)
    {
        $params = [
            "Документ" => [
                "Идентификатор" => $serviceOrderId,
                'Вложение' => [
                    'Дата' => date('d.m.Y'),
                    'Файл' => [
                        'Имя' => $fileName,
                        'ДвоичныеДанные' => $documentContent,
                    ]
                ],
            ]
        ];

        $response = $this->send('СБИС.ЗаписатьВложение', $params);

        if (!$response->ok()) {
            Log::info('Sbis СБИС.ЗаписатьВложение - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return $json;
    }

    // Прочитать контакты из наряда
    public function getServiceOrdersContact($serviceOrderId, $serviceOrderClientId)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    [
                        "actions",
                        "phone_score",
                        "phone_status",
                        "assignee_contacts_key"
                    ],
                    10,
                    $serviceOrderClientId,
                    $serviceOrderId,
                    "Наряд",
                    null,
                    false
                ],
                "s" => [
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "AdditionalData"],
                    ["t" => "Число целое", "n" => "AssigneeContactsLimit"],
                    ["t" => "Число целое", "n" => "ClientID"],
                    ["t" => "Число целое", "n" => "LinkedObjectID"],
                    ["t" => "Строка", "n" => "LinkedObjectName"],
                    ["t" => "Строка", "n" => "ParentID"],
                    ["t" => "Логическое", "n" => "WithFolders"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => null,
            "ДопПоля" => []
        ];


        $response = $this->send('BoundContact.List', $params);
        if (!$response->ok()) {
            $this->checkSbisPermissions($response);
            Log::info('Sbis - BoundContact.List' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return $json;
    }

    // Получить список чатов для пользователя(не востребован сейчас)
    public function getSbisChannels()
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    true,
                    100,
                    1,
                    3,
                    false
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "AddGroupMembersAvailable"],
                    ["t" => "Логическое", "n" => "CalcCanCreateForumTheme"],
                    ["t" => "Логическое", "n" => "GetGroupQuitInfo"],
                    ["t" => "Логическое", "n" => "outcomeAppendFavoriteChats"],
                    ["t" => "Логическое", "n" => "outcomeAppendMessagesList"],
                    ["t" => "Логическое", "n" => "outcomeAppendPersons"],
                    ["t" => "Логическое", "n" => "outcomeAppendUnreadCounts"],
                    ["t" => "Логическое", "n" => "БезИзбранных"],
                    ["t" => "Число целое", "n" => "РазмерСпискаУчастников"],
                    ["t" => "Число целое", "n" => "Реестр"],
                    ["t" => "Число целое", "n" => "СортировкаУчастников"],
                    ["t" => "Логическое", "n" => "ФотоЧерезПревьювер"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => ["bothways", true, 20, null],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Строка", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];


        $response = $this->send('Чат.Список', $params);
        if (!$response->ok()) {
            Log::info('Sbis - Чат.Список' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Роли сотрудника
    public function getEmployeeRoles($userId, $legalId = null)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    true,
                    $legalId, //248,
                    $legalId, //248,
                    true,
                    null,
                    $userId, //26718416,
                    true
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "DefaultRole"],
                    ["t" => "Число целое", "n" => "LegalId"],
                    ["t" => "Число целое", "n" => "MngtId"],
                    ["t" => "Логическое", "n" => "OnlyAssigned"],
                    ["t" => "Строка", "n" => "Roles"],
                    ["t" => "Число целое", "n" => "UserId"],
                    ["t" => "Логическое", "n" => "WithConstraints"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => null,
            "ДопПоля" => []
        ];
        $response = $this->send('PersonalRights.RoleList', $params);
        if (!$response->ok()) {
            Log::info('Sbis - PersonalRights.RoleList' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Занести в номенклатуру товары для производства
    public function updateNomProd($id, $name, $nomenclatures)
    {

        $sendNomeclatures = [];
        $i = 2;
        foreach ($nomenclatures as $sbisId => $nomenclature) {
            $sendNomeclatures[] = [
                $i, //RowId
                1, //ContentId
                null,
                null,
                null,
                1,
                null,
                $nomenclature['quantity'], //Qty
                null,
                null,
                $sbisId, //NomenclatureId
                $nomenclature['name'], //NomenclatureName
                0,
                null,
                null,
                null,
                "796",
                null,
                "шт",
                1,
                null,
                [
                    null
                ],
                null,
                null,
                $nomenclature['price'], //CostPrice
                $nomenclature['price'],//CostPrice
                $nomenclature['quantity'], //NetQty
                $nomenclature['quantity'], //NetQty
                null,
                null,
                false,
                null,
                1,
                0,
                0,
                0,
                "{\"base\": {\"abbr\": \"шт\", \"code\": \"796\", \"name\": \"Штука\", \"qty\": 1.0}}",
                [
                    "{\"base\": {\"abbr\": \"шт\", \"code\": \"796\", \"name\": \"Штука\", \"qty\": 1.0}}"
                ],
                null,
                null,
                null,
                null,
                false,
                null,
                false,
                null,
                null,
                $nomenclature['quantity'], //Qty
                0,
                $nomenclature['quantity'], //Qty
                null,
                null,
                0,
                0
            ];
            $i++;
        }
        $mainNomenclature[] = [
            $i,
            1,
            "Основной",
            false,
            true,
            2,
            null,
            null,
            null,
            null,
            $id,
            $name,
            0,
            0,
            0,
            null,
            null,
            null,
            null,
            0,
            null,
            [
                null
            ],
            true,
            false,
            0,
            null,
            null,
            0,
            null,
            null,
            true,
            null,
            0,
            0,
            0,
            0,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            null,
            0,
            null,
            0,
            0,
            0,
            null,
            null,
            0,
            0
        ];
        $d = array_merge($mainNomenclature, $sendNomeclatures);
        $params = [
            "Запись" => [
                "d" => [
                    $id, // id new nomenclature
                    true,
                    [
                        "d" => [
                            [
                                "d" => [
                                    [
                                        "d" => $d,
                                        "s" => [
                                            ["t" => "Число целое", "n" => "RowId"],
                                            ["t" => "Число целое", "n" => "ContentId"],
                                            ["t" => "Строка", "n" => "ContentName"],
                                            ["t" => "Логическое", "n" => "Deleted"],
                                            ["t" => "Логическое", "n" => "IsDefaultContent"],
                                            ["t" => "Число целое", "n" => "OrdinalNumber"],
                                            ["t" => "Число целое", "n" => "ContentElementId"],
                                            ["t" => "Число вещественное", "n" => "Qty"],
                                            ["t" => "Строка", "n" => "Note"],
                                            ["t" => "Логическое", "n" => "Required"],
                                            ["t" => "Число целое", "n" => "Nomenclature"],
                                            ["t" => "Строка", "n" => "NomenclatureName"],
                                            ["t" => "Число целое", "n" => "nomAccounting"],
                                            ["t" => "Число целое", "n" => "nomSubAccounting"],
                                            ["t" => "Число целое", "n" => "nomMarkingInfo"],
                                            ["t" => "Строка", "n" => "nomNumber"],
                                            ["t" => "Строка", "n" => "MeasurementUnit"],
                                            ["t" => "Строка", "n" => "EIU2"],
                                            ["t" => "Строка", "n" => "MeasurementUnitName"],
                                            ["t" => "Число вещественное", "n" => "MeasurementUnitFactor"],
                                            ["t" => "Строка", "n" => "MeasurementUnitOrig"],
                                            ["t" => "Идентификатор", "n" => "Folder"],
                                            ["t" => "Логическое", "n" => "Folder@"],
                                            ["t" => "Логическое", "n" => "Folder$"],
                                            ["t" => "Число вещественное", "n" => "CostPrice"],
                                            ["t" => "Число вещественное", "n" => "CostPriceUnit"],
                                            ["t" => "Число вещественное", "n" => "NetQty"],
                                            ["t" => "Число вещественное", "n" => "AltQty"],
                                            ["t" => "Строка", "n" => "AltUnit"],
                                            ["t" => "Строка", "n" => "AltUnitName"],
                                            ["t" => "Логическое", "n" => "IsContents"],
                                            ["t" => "Логическое", "n" => "IsCycled"],
                                            ["t" => "Число целое", "n" => "Depth"],
                                            ["t" => "Число вещественное", "n" => "productionVolume"],
                                            ["t" => "Число целое", "n" => "Host"],
                                            ["t" => "Число вещественное", "n" => "baseFactor"],
                                            ["t" => "Строка", "n" => "EIU"],
                                            ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "NOM_EIU"],
                                            ["t" => "Строка", "n" => "ProcessingType"],
                                            ["t" => "UUID", "n" => "ProcessingUUID"],
                                            ["t" => "UUID", "n" => "Nomenclature_UUID"],
                                            ["t" => "Логическое", "n" => "UseAnalog"],
                                            ["t" => "Логическое", "n" => "IsArchival"],
                                            ["t" => "Строка", "n" => "Article"],
                                            ["t" => "Логическое", "n" => "ProcessingBguExist"],
                                            ["t" => "Число вещественное", "n" => "GramInMeasureUnit"],
                                            ["t" => "JSON-объект", "n" => "ProcessingTypes"],
                                            ["t" => "Число вещественное", "n" => "Output"],
                                            ["t" => "Число целое", "n" => "TempOrdinalNumber"],
                                            ["t" => "Число вещественное", "n" => "КоличествоУп"],
                                            ["t" => "Дата", "n" => "FromDate"],
                                            ["t" => "Дата", "n" => "ToDate"],
                                            ["t" => "Число целое", "n" => "Organization"],
                                            ["t" => "Число вещественное", "n" => "ProcessingLoss"]
                                        ],
                                        "r" => [
                                            "f" => 0,
                                            "d" => [
                                                true
                                            ],
                                            "s" => [
                                                [
                                                    "n" => "self_cost_visibility",
                                                    "t" => "Логическое"
                                                ]
                                            ],
                                            "_type" => "record"
                                        ],
                                        "_type" => "recordset",
                                        "f" => 3
                                    ]
                                ],
                                "s" => [
                                    ["n" => "ProductionData", "t" => "Выборка"]
                                ],
                                "_type" => "record",
                                "f" => 2
                            ]
                        ],
                        "s" => [
                            ["n" => "Production", "t" => "Запись"],
                        ],
                        "_type" => "record",
                        "f" => 1
                    ]
                ],
                "s" => [
                    ["t" => "Число целое", "n" => "id"],
                    ["t" => "Логическое", "n" => "needSync"],
                    ["t" => "Запись", "n" => "CardExtData"]
                ],
                "_type" => "record",
                "f" => 0
            ]

        ];
        $response = $this->send('Nomenclature.NomenclatureUpdate', $params);
        if (!$response->ok()) {
            Log::info('Nomenclature.NomenclatureUpdate' . ' - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Выгрузка заказов в СБИС
    public function SbisWriteDocument($type, $xml, $inn = null, $kpp = null)
    {
        if (empty($inn)) {
            $getInfoOrg = $this->getPointSale();
            $inn = $getInfoOrg[0]['ИНН'];
            $kpp = $getInfoOrg[0]['КПП'];
        }

        $params = [
            "Документ" => [
                "Тип" => $type,
                'НашаОрганизация' => [
                    'СвЮЛ' => [
                        'ИНН' => $inn,
                        'КПП' => $kpp,
                    ],
                ],
                'Вложение' => [
                    'Файл' => [
                        'Имя' => 'ON_DOC_' . date('Y') . date('m') . date('d') . '_' . sha1(
                                date(DATE_ATOM, mktime(0, 0, 0, 7, 1, 2000))
                            ) . '.xml',
                        'ДвоичныеДанные' => base64_encode(iconv("UTF-8", "windows-1251", $xml)),
                    ],
                ],
            ],
        ];
        $response = $this->send('СБИС.ЗаписатьДокумент', $params);
        if (!$response->ok()) {
            Log::info('СБИС.ЗаписатьДокумент - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Провести акт выпуска
    public function ActOfRelease($id)
    {
        $params = [
            "docRec" => [
                "d" => [
                    $id
                ],
                "s" => [["t" => "Число целое", "n" => "@Документ"]],
                "_type" => "record",
                "f" => 0
            ],
            "eventRec" => null
        ];
        $response = $this->send('Документ.Post', $params);
        if (!$response->ok()) {
            Log::info('Документ.Post - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Получить список акт выпуска
    public function getActOfRelease()
    {
        $day = Carbon::now()->format('Y-m-d');
        $params = [
            "Фильтр" => [
                "d" => [
                    true,
                    true,
                    true,
                    true,
                    false,
                    "АктВыпуска",
                    "АктВыпуска",
                    true,
                    $day,
                    $day,
                    "-2",
                    [],
                    [],
                    false
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "iterative_list"],
                    ["t" => "Логическое", "n" => "new_navigation"],
                    ["t" => "Логическое", "n" => "position_on_today"],
                    ["t" => "Логическое", "n" => "rp_doc"],
                    ["t" => "Логическое", "n" => "translit_search"],
                    ["t" => "Строка", "n" => "НазваниеПодТипаДокумента"],
                    ["t" => "Строка", "n" => "НазваниеТипаДокумента"],
                    ["t" => "Логическое", "n" => "УчитыватьИерархиюНО"],
                    ["t" => "Дата", "n" => "ФильтрДатаП"],
                    ["t" => "Дата", "n" => "ФильтрДатаС"],
                    ["t" => "Строка", "n" => "ФильтрОрганизацияФилиал"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "ФильтрПометкиВкл"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "ФильтрПометкиИскл"],
                    ["t" => "Логическое", "n" => "ФильтрПроведенные"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    "bothways",
                    true,
                    18,
                    null
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Строка", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];
        $response = $this->send('АктВыпуска.СписокХраним', $params);
        if (!$response->ok()) {
            Log::info('АктВыпуска.СписокХраним - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    // Проверка прав доступа для изменения нарядов
    public function testPermissionServiceOrder($orderId, $Description)
    {
        $params = [
            "Запись" => [
                "d" => [
                    $orderId,
                    $Description,
                    true
                ],
                "s" => [
                    ["t" => "Число целое", "n" => "@Документ"],
                    ["t" => "Строка", "n" => "Наряд.Описание"],
                    ["t" => "Логическое", "n" => "РучноеСохранение"]
                ],
                "_type" => "record",
                "f" => 0
            ]
        ];
        $response = $this->send('Наряд.Записать', $params);

        if (!$response->ok()) {
            $this->checkSbisPermissions($response);
            Log::info('Sbis Наряд.Записать - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return $json['result'];
        }
        return $json;
    }

    public function getPriceById($id)
    {
        $params = [
            "Ids" => $id
        ];
        $response = $this->send('PriceList.Read', $params);

        if (!$response->ok()) {
            $this->checkSbisPermissions($response);
            Log::info('PriceList.Read - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return $json['result'];
        }
        return $json;

    }

    // Прочитать вложения в наряде
    public function getServiceOrderAttachList($id)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    "$id",
                    null
                ],
                "s" => [["t" => "Строка", "n" => "catalogId"], ["t" => "Строка", "n" => "folder"]],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    true,
                    100,
                    0
                ],
                "s" => [["t" => "Логическое", "n" => "ЕстьЕще"], ["t" => "Число целое", "n" => "РазмерСтраницы"], ["t" => "Число целое", "n" => "Страница"]],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];
        $response = $this->send('Наряд.AttachList', $params);

        if (!$response->ok()) {
            $this->checkSbisPermissions($response);
            Log::info('Наряд.AttachList - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return $json['result'];
        }
        return $json;

    }

    //Найти заказ в СБИС
    public function eventStoredList($idDoc)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    true,
                    true,
                    true,
                    false,
                    true,
                    "ЗаказВх",
                    "Все",
                    true,
                    "Все",
                    null,
                    null,
                    "Нет",
                    "-2",
                    $idDoc,
                    [],
                    [],
                    null
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "iterative_list"],
                    ["t" => "Логическое", "n" => "new_navigation"],
                    ["t" => "Логическое", "n" => "rp_doc"],
                    ["t" => "Логическое", "n" => "translit_search"],
                    ["t" => "Логическое", "n" => "ws4_registry"],
                    ["t" => "Строка", "n" => "НазваниеТипаДокумента"],
                    ["t" => "Строка", "n" => "СостояниеСобытий"],
                    ["t" => "Логическое", "n" => "УчитыватьИерархиюНО"],
                    ["t" => "Строка", "n" => "ФильтрВладелец"],
                    ["t" => "Строка", "n" => "ФильтрДатаП"],
                    ["t" => "Строка", "n" => "ФильтрДатаС"],
                    ["t" => "Строка", "n" => "ФильтрИзСписка"],
                    ["t" => "Строка", "n" => "ФильтрОрганизацияФилиал"],
                    ["t" => "Строка", "n" => "ФильтрПоМаске"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "ФильтрПометкиВкл"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "ФильтрПометкиИскл"],
                    ["t" => "Строка", "n" => "ФильтрСрокП"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    "forward",
                    true,
                    25,
                    null
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => "Строка", "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];
        $response = $this->send('ЗаказВх.EventStoredList', $params);

        if (!$response->ok()) {
            $this->checkSbisPermissions($response);
            Log::info('ЗаказВх.EventStoredList - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return false;
    }

    //Изменить склад в заказе
    public function changeWarehouseInOrder($data)
    {
        $params = [
            "Запись" => [
                "d" => [
                    $data['idDocument'],   //id Document
                    $data['warehouseName'], //warehouse name
                    $data['idWarehouse'], //id warehouse
                    false
                ],
                "s" => [
                    ["t" => "Число целое", "n" => "@Документ"],
                    ["t" => "Строка", "n" => "СкладскойДокумент.Склад.Название"],
                    ["t" => "Связь", "n" => "СкладскойДокумент.Склад"],
                    ["t" => "Логическое", "n" => "СброситьЧерновик"]
                ],
                "_type" => "record",
                "f" => 0
            ]

        ];

        $response = $this->send('ЗаказСкВх.Записать', $params);

        if (!$response->ok()) {
            $this->checkSbisPermissions($response);
            Log::info('ЗаказСкВх.Записать - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return $json['result'];
        }
        return $json;
    }

    //Поиск определенной номенклатуры в прайсе
    public function searchProductInPrice($price, $nameProduct)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    -(int)$price,
                    [(int)$price],
                    $nameProduct,
                    true,
                    "Только листья",
                    [
                        "d" => [
                            true,
                            10,
                            true
                        ],
                        "s" => [
                            ["t" => "Логическое", "n" => "HideConditionalProduct"],
                            ["t" => "Число целое", "n" => "PriceBase"],
                            ["t" => "Логическое", "n" => "ShowEmptyFolder"]
                        ],
                        "_type" => "record",
                        "f" => 1
                    ]
                ],
                "s" => [
                    ["t" => "Строка", "n" => "GetColumnsFromSettings"],
                    ["t" => ["n" => "Массив", "t" => "Число целое"], "n" => "PriceListHierarchy"],
                    ["t" => "Строка", "n" => "SearchString"],
                    ["t" => "Логическое", "n" => "SquashNomNames"],
                    ["t" => "Строка", "n" => "ВидДерева"],
                    ["t" => "Запись", "n" => "ServiceOption"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => null,
            "Навигация" => [
                "d" => [
                    true,
                    10,
                    0
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "ЕстьЕще"],
                    ["t" => "Число целое", "n" => "РазмерСтраницы"],
                    ["t" => "Число целое", "n" => "Страница"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => [
                "Name",
                "IsFolderUI",
                "PublicationSaleState",
                "Image",
                "BalanceObject",
                "MUP",
                "ContainsFolders",
                "FolderContents",
                "TypeObject",
                "PriceList",
                "Deleted",
                "DescriptionRaw",
                "ImageObject",
                "PriceOffersAll",
                "Price",
                "Type",
                "DescriptionSimple",
                "IsNested"
            ]

        ];

        $response = $this->send('PriceListPosition.List', $params);

        if (!$response->ok()) {
            $this->checkSbisPermissions($response);
            Log::info('PriceListPosition.List - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return $json;
    }

    //Поиск определенного каталога в прайсе
    public function searchCategoryInPrice($price, $nameCatalog)
    {
        $params = [

            "Фильтр" => [
                "d" => [
                    2,
                    null,
                    true,
                    [
                        "d" => [
                            "desktop"
                        ],
                        "s" => [
                            ["t" => "Строка", "n" => "Device"]
                        ],
                        "_type" => "record",
                        "f" => 1
                    ],
                    date('Y-m-d '),
                    "online",
                    null,
                    -(int)$price,
                    0,
                    true,
                    null,
                    3,
                    null,
                    false,
                    [
                        $price
                    ],
                    null,
                    $nameCatalog,
                    [
                        "d" => [
                            true,
                            $price,
                            true
                        ],
                        "s" => [
                            ["t" => "Логическое", "n" => "HideConditionalProduct"],
                            ["t" => "Число целое", "n" => "PriceBase"],
                            ["t" => "Логическое", "n" => "ShowEmptyFolder"]
                        ],
                        "_type" => "record",
                        "f" => 2
                    ],
                    true
                ],
                "s" => [
                    ["t" => "Число целое", "n" => "Archival"],
                    ["t" => "Строка", "n" => "BalanceForOrganization"],
                    ["t" => "Логическое", "n" => "CheckFolderExists"],
                    ["t" => "Запись", "n" => "ConfigurationOption"],
                    ["t" => ["n" => "Дата и время", "tz" => false], "n" => "DateTime"],
                    ["t" => "Строка", "n" => "Endpoint"],
                    ["t" => "Строка", "n" => "Folder"],
                    ["t" => "Строка", "n" => "GetColumnsFromSettings"],
                    ["t" => "Число целое", "n" => "GetPath"],
                    ["t" => "Логическое", "n" => "NewConfiguration"],
                    ["t" => "Строка", "n" => "NodeType"],
                    ["t" => "Число целое", "n" => "PriceCalcType"],
                    ["t" => "Строка", "n" => "PriceList"],
                    ["t" => "Логическое", "n" => "PriceListBasePrice"],
                    ["t" => ["n" => "Массив", "t" => "Число целое"], "n" => "PriceListHierarchy"],
                    ["t" => "Строка", "n" => "PublicationSaleState"],
                    ["t" => "Строка", "n" => "SearchString"],
                    ["t" => "Запись", "n" => "ServiceOption"],
                    ["t" => "Логическое", "n" => "TranslitSearchString"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => [
                "d" => [
                    [true, "Custom", false]
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "l"],
                    ["t" => "Строка", "n" => "n"],
                    ["t" => "Логическое", "n" => "o"]
                ],
                "_type" => "recordset",
                "f" => 0
            ],
            "Навигация" => [
                "d" => [
                    true,
                    1000,
                    0
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "ЕстьЕще"],
                    ["t" => "Число целое", "n" => "РазмерСтраницы"],
                    ["t" => "Число целое", "n" => "Страница"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => [
                "Name",
                "IsFolderUI",
                "PublicationSaleState",
                "Image",
                "BalanceObject",
                "MUP",
                "ContainsFolders",
                "FolderContents",
                "TypeObject",
                "PriceList",
                "Deleted",
                "DescriptionRaw",
                "ImageObject",
                "PriceOffersAll",
                "Price",
                "Type",
                "DescriptionSimple",
                "IsNested"
            ]

        ];

        $response = $this->send('PriceListPosition.List', $params);

        if (!$response->ok()) {
            $this->checkSbisPermissions($response);
            Log::info('PriceListPosition.List - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return $json;
    }


    //Отправка сообщений пользователю в документе.
    //Метод ожидает 3 основные переменные, такие как длинный id пользователя, длинный id документа и текст сообщения
    //При необходимости позволяет указать ряд дополнительных параметров.
    public function documentSendMessage($message, $idDoc, $idRecipient)
    {
        $params = [
            "Получатели" => [],
            "Текст" => $message,
            "Диалог" => $idDoc,
            "Документ" => $idDoc,
            "Сообщение" => null,
            "Файлы" => [],
            "Опции" => [
                "d" => [
                    null,       //Папка
                    null,       //Тип сообщения
                    true,       //Слить с Предыдущим
                    null,       //Слить с предыдущим период прочтения
                    false,      //Всем участникам темы
                    false,      //Всем участникам документа
                    true,       //Добавить в участники чата
                    false,      //Быстрый ответ
                    null,       //Тип документа
                    $idDoc,        //Документ
                    null,       //Исключить получателей
                    [],         //РедакцииФайлов
                    [],         //ОткрепитьФайлыОтТем
                    null,       //SenderChanel
                    null,       //SenderName
                    null,       //SenderNameType
                    null,       //SenderMember
                    [
                        "d" => [],
                        "s" => [
                            ["t" => "UUID", "n" => "Person"],
                            ["t" => "UUID", "n" => "Chanel"],
                            ["t" => "Логическое", "n" => "isHidden"],
                            ["t" => "JSON-объект", "n" => "ChanelExt"]
                        ],
                        "_type" => "recordset",
                        "f" => 1
                    ],      //Recipients
                    null,       //Теги
                    4,          //TextFormat
                    [
                        [
                            "Персоны" => [
                                $idRecipient
                            ]
                        ]
                    ],      //МассоваяРассылка
                    $idDoc,        //message_id
                    false,      //ПодклеитьИсториюВП
                    null,       //UsedSpaceSDAttachId
                    $message,       //НативныйТекст
                    false,      //ПубличныйДокумент
                    false,      //notifyIsChannel
                    null,       //ПересылаемоеСообщение
                    false,      //ИсключитьВнешниеКонтакты
                    false,      //translation
                    null,       //createThread
                    null,        //Title
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Папка"],
                    ["t" => "Строка", "n" => "ТипСообщения"],
                    ["t" => "Логическое", "n" => "СлитьСПредыдущим"],
                    ["t" => "Число целое", "n" => "СлитьСПредыдущимПериодПрочтения"],
                    ["t" => "Логическое", "n" => "ВсемУчастникамТемы"],
                    ["t" => "Логическое", "n" => "ВсемУчастникамДокумента"],
                    ["t" => "Логическое", "n" => "ДобавитьВУчастникиЧата"],
                    ["t" => "Логическое", "n" => "БыстрыйОтвет"],
                    ["t" => "Число целое", "n" => "ТипДокумента"],
                    ["t" => "UUID", "n" => "Документ"],
                    ["t" => ["n" => "Массив", "t" => "UUID"], "n" => "ИсключитьПолучателей"],
                    ["t" => "JSON-объект", "n" => "РедакцииФайлов"],
                    ["t" => "JSON-объект", "n" => "ОткрепитьФайлыОтТем"],
                    ["t" => "UUID", "n" => "SenderChanel"],
                    ["t" => "Строка", "n" => "SenderName"],
                    ["t" => "Строка", "n" => "SenderNameType"],
                    ["t" => "Строка", "n" => "SenderMember"],
                    ["t" => "Выборка", "n" => "Recipients"],
                    ["t" => ["n" => "Массив", "t" => "UUID"], "n" => "Теги"],
                    ["t" => "Число целое", "n" => "TextFormat"],
                    ["t" => "JSON-объект", "n" => "МассоваяРассылка"],
                    ["t" => "Строка", "n" => "message_id"],
                    ["t" => "Логическое", "n" => "ПодклеитьИсториюВП"],
                    ["t" => "Строка", "n" => "UsedSpaceSDAttachId"],
                    ["t" => "Строка", "n" => "НативныйТекст"],
                    ["t" => "Логическое", "n" => "ПубличныйДокумент"],
                    ["t" => "Логическое", "n" => "notifyIsChannel"],
                    ["t" => "JSON-объект", "n" => "ПересылаемоеСообщение"],
                    ["t" => "Логическое", "n" => "ИсключитьВнешниеКонтакты"],
                    ["t" => "Логическое", "n" => "translation"],
                    ["t" => "Строка", "n" => "createThread"],
                    ["t" => "Строка", "n" => "Title"]
                ],
                "_type" => "record",
                "f" => 0
            ]
        ];

        $response = $this->send('Персона.СОтправить', $params);
        if (!$response->ok()) {
            $this->checkSbisPermissions($response);
            Log::info('Персона.СОтправить - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return $json['result'];
        }
        return $json;
    }

    public function updateCurrentState($docId, $commentText)
    {
        $params = [
            "params" => [
                "d" => [
                    null,
                    $docId,
                    null,
                    null,
                    null,
                    $commentText,
                    null
                ],
                "s" => [
                    ["t" => "Строка", "n" => "eventId"],
                    ["t" => "Строка", "n" => "docId"],
                    ["t" => "Выборка", "n" => "face"],
                    ["t" => "Число целое", "n" => "faceId"],
                    ["t" => "Дата", "n" => "startDate"],
                    ["t" => "Строка", "n" => "text"],
                    ["t" => "Выборка", "n" => "attachments"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "objectName" => "Наряд"
        ];

        $response = $this->send('Документ.UpdateCurrentState', $params);
        if (!$response->ok()) {
            $this->checkSbisPermissions($response);
            Log::info('Документ.UpdateCurrentState - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return $json['result'];
        }
        return $json;
    }

    public function getUuid($priceId)
    {
        $params = [
            "NewRecord" => [
                "d" => [
                    true,
                    $priceId
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "IsFolder"],
                    ["t" => "Число целое", "n" => "PriceList"]
                ],
                "_type" => "record",
                "f" => 0
            ]
        ];

        $response = $this->send('PriceListPosition.CreatePosition', $params);
        if (!$response->ok()) {
            Log::info('PriceListPosition.CreatePosition - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return parseSbisDataToArray($json['result']);
        }
        return $json;
    }

    public function writePrice($name, $uuid, $priceId, $folderId = null)
    {
        $params = [
            "record" => [
                "d" => [
                    null,
                    $folderId,
                    true,
                    $name,
                    null,
                    null,
                    [
                        "d" => [
                            null,
                            null,
                            null,
                            null
                        ],
                        "s" => [
                            ["n" => "NomenclatureId", "t" => "Число целое"],
                            ["n" => "Price", "t" => ["n" => "Деньги", "p" => 2]],
                            ["n" => "PriceList", "t" => "Число целое"],
                            ["n" => "MeasureUnit", "t" => "JSON-объект"]
                        ],
                        "_type" => "record",
                        "f" => 1
                    ],
                    null,
                    [
                        "d" => [],
                        "s" => [
                            ["n" => "id", "t" => "Строка"],
                            ["n" => "md", "t" => "Строка"],
                            ["n" => "attachId", "t" => "Строка"],
                            ["n" => "sbisDiskId", "t" => "Строка"],
                            ["n" => "originalFileId", "t" => "Строка"],
                            ["n" => "redactionId", "t" => "Строка"],
                            ["n" => "fileName", "t" => "Строка"],
                            ["n" => "date", "t" => "Строка"],
                            ["n" => "url", "t" => "Строка"],
                            ["n" => "dominantColor", "t" => "Строка"],
                            ["n" => "isDarkTheme", "t" => "Логическое"],
                            ["n" => "width", "t" => "Число целое"],
                            ["n" => "height", "t" => "Число целое"],
                            ["n" => "size", "t" => "Число целое"],
                            ["n" => "sbisDisk", "t" => "Логическое"],
                            ["n" => "sbisDiskRedactionId", "t" => "Строка"],
                            ["n" => "canEdit", "t" => "Логическое"],
                            ["n" => "canDelete", "t" => "Логическое"],
                            ["n" => "coords", "t" => "Строка"],
                            ["n" => "UrlVideo", "t" => "Строка"]
                        ],
                        "_type" => "recordset",
                        "f" => 2
                    ],
                    $priceId,
                    null,
                    null,
                    $uuid,
                    null,
                    null,
                    false
                ],
                "k" => "Id",
                "s" => [
                    ["n" => "Id", "t" => "Число целое"],
                    ["n" => "Folder", "t" => "Число целое"],
                    ["n" => "IsFolder", "t" => "Логическое"],
                    ["n" => "Name", "t" => "Строка"],
                    ["n" => "Packing", "t" => "JSON-объект"],
                    ["n" => "Price", "t" => ["n" => "Деньги", "p" => 2]],
                    ["n" => "PriceListHierarchy", "t" => "Запись"],
                    ["n" => "Description", "t" => "JSON-объект"],
                    ["n" => "Picture", "t" => "Выборка"],
                    ["n" => "PriceList", "t" => "Число целое"],
                    ["n" => "Nomenclature", "t" => "Число целое"],
                    ["n" => "Path", "t" => "Выборка"],
                    ["n" => "Uuid", "t" => "UUID"],
                    ["n" => "Compilation", "t" => "Запись"],
                    ["n" => "Published", "t" => "Логическое"],
                    ["t" => "Логическое", "n" => "IsChangePublication"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ИмяМетода" => ""

        ];

        $response = $this->send('PriceListPosition.Write', $params);
        if (!$response->ok()) {
            Log::info('PriceListPosition.Write - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return $json['result'];
        }
        return $json;
    }

    public function addNomenclatureInPrice($idPrice, $nomenclature_id, $idFolder)
    {
        $params = [
            "PriceList" => (string)$idPrice,
            "MethodName" => "Nomenclature.List",
            "MethodFilter" => [
                "d" => [
                    null,
                    null,
                    null,
                    null,
                    0,
                    2,
                    null,
                    true,
                    null,
                    null,
                    [],
                    true,
                    ["d" => [true], "s" => [["t" => "Логическое", "n" => "HideConditionalProduct"]], "_type" => "record",
                        "f" => 1
                    ],
                    ["LC"],
                    true,
                    null,
                    null,
                    null,
                    "LC",
                    ["d" => [[(string)$nomenclature_id], [], "all", true],
                        "s" => [
                            ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "marked"],
                            ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "excluded"],
                            ["t" => "Строка", "n" => "type"],
                            ["t" => "Логическое", "n" => "recursive"]
                        ],
                        "_type" => "record",
                        "f" => 2
                    ],
                    true
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Balance"],
                    ["t" => "Строка", "n" => "BalanceForOrganization"],
                    ["t" => "Строка", "n" => "BalanceForWarehouse"],
                    ["t" => "Строка", "n" => "BarcodeExist"],
                    ["t" => "Число целое", "n" => "GetPath"],
                    ["t" => "Число целое", "n" => "Link"],
                    ["t" => "Строка", "n" => "MarkColor"],
                    ["t" => "Логическое", "n" => "NewConfiguration"],
                    ["t" => "Строка", "n" => "NomenclatureType"],
                    ["t" => "Строка", "n" => "PublicationSaleState"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "ScopesAreas"],
                    ["t" => "Логическое", "n" => "SelectionHiddenNode"],
                    ["t" => "Запись", "n" => "ServiceOption"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "Source"],
                    ["t" => "Логическое", "n" => "TranslitSearchString"],
                    ["t" => "Строка", "n" => "Type"],
                    ["t" => "Строка", "n" => "Vat"],
                    ["t" => "Строка", "n" => "Warehouse"],
                    ["t" => "Строка", "n" => "currentTab"],
                    ["t" => "Запись", "n" => "selection"],
                    ["t" => "Логическое", "n" => "NeedLinkLC"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "FieldMap" => [
                "PrimaryKey" => "Id",
                "NomenclatureId" => "Id",
                "Pack" => "",
                "Price" => "Price",
                "PublicationState" => "PublicationSaleState",
                "FolderName" => "Name",
                "FolderPicture" => "ImageObject",
                "ParentKey" => "Folder",
                "NodeType" => "IsFolder",
                "FolderDescription" => "Description"
            ],
            "Options" => [
                "d" => [
                    true,
                    [
                        "d" => [
                            [
                                "d" => [
                                    null,
                                    null,
                                    null,
                                    null,
                                    null,
                                    0,
                                    2,
                                    null,
                                    true,
                                    null,
                                    null,
                                    [],
                                    true,
                                    [
                                        "d" => [
                                            true
                                        ],
                                        "s" => [
                                            [
                                                "t" => "Логическое",
                                                "n" => "HideConditionalProduct"
                                            ]
                                        ],
                                        "_type" => "record",
                                        "f" => 3
                                    ],
                                    [
                                        "LC"
                                    ],
                                    true,
                                    null,
                                    null,
                                    null,
                                    "LC",
                                    true
                                ],
                                "s" => [
                                    ["t" => "Строка", "n" => "Balance"],
                                    ["t" => "Строка", "n" => "BalanceForOrganization"],
                                    ["t" => "Строка", "n" => "BalanceForWarehouse"],
                                    ["t" => "Строка", "n" => "BarcodeExist"],
                                    ["t" => "Строка", "n" => "GetColumnsFromSettings"],
                                    ["t" => "Число целое", "n" => "GetPath"],
                                    ["t" => "Число целое", "n" => "Link"],
                                    ["t" => "Строка", "n" => "MarkColor"],
                                    ["t" => "Логическое", "n" => "NewConfiguration"],
                                    ["t" => "Строка", "n" => "NomenclatureType"],
                                    ["t" => "Строка", "n" => "PublicationSaleState"],
                                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "ScopesAreas"],
                                    ["t" => "Логическое", "n" => "SelectionHiddenNode"],
                                    ["t" => "Запись", "n" => "ServiceOption"],
                                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "Source"],
                                    ["t" => "Логическое", "n" => "TranslitSearchString"],
                                    ["t" => "Строка", "n" => "Type"],
                                    ["t" => "Строка", "n" => "Vat"],
                                    ["t" => "Строка", "n" => "Warehouse"],
                                    ["t" => "Строка", "n" => "currentTab"],
                                    ["t" => "Логическое", "n" => "NeedLinkLC"]
                                ],
                                "_type" => "record",
                                "f" => 2
                            ]
                        ],
                        "s" => [["t" => "Запись", "n" => "FolderContentFilter"]],
                        "_type" => "record",
                        "f" => 1
                    ],
                    [
                        "Name",
                        "Description",
                        "Price",
                        "PublicationSaleState",
                        "ImageObject"
                    ],
                    $idFolder,
                    [
                        "FileId" => "Identifier",
                        "RedactionId" => "",
                        "FileName" => "Title",
                        "FileSize" => "",
                        "RelativeUrl" => "Url",
                        "IsDarkTheme" => "IsDarkTheme",
                        "DominantColor" => "DominantColor",
                        "Width" => "Width",
                        "Height" => "Height"
                    ],
                    false,
                    "Custom"
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "AddEmptyFolders"],
                    ["t" => "Запись", "n" => "AddFoldersContent"],
                    ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "AdditionalFields"],
                    ["t" => "Число целое", "n" => "Folder"],
                    ["t" => "JSON-объект", "n" => "ImageConfig"],
                    ["t" => "Логическое", "n" => "ImagesInSbisDisk"],
                    ["t" => "Строка", "n" => "Sorting"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "RebuiltOptions" => true
        ];
        $response = $this->send('PriceList.SmartAddPositions', $params);
        if (!$response->ok()) {
            Log::info('PriceList.SmartAddPositions - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }
        $json = $response->json();

        return $json;
    }

    public function deleteNomenclatureInPrice($idPrice)
    {
        $params = [
            "Action" => [
                "d" => [
                    "Delete",
                    [
                        "d" => [
                            false
                        ],
                        "s" => [
                            ["t" => "Логическое", "n" => "isLRS"]
                        ],
                        "_type" => "record",
                        "f" => 1
                    ]
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Name"],
                    ["t" => "Запись", "n" => "Params"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "RegistryFilter" => [
                "d" => [
                    2,
                    null,
                    true,
                    [
                        "d" => [
                            "desktop"
                        ],
                        "s" => [
                            ["t" => "Строка", "n" => "Device"]
                        ],
                        "_type" => "record",
                        "f" => 1
                    ],
                    Carbon::now()->format('Y-m-d H:i:s.v'),
                    null,
                    0,
                    false,
                    true,
                    null,
                    3,
                    null,
                    false,
                    [
                        $idPrice
                    ],
                    null,
                    [
                        "d" => [
                            true,
                            $idPrice,
                            true
                        ],
                        "s" => [
                            ["t" => "Логическое", "n" => "HideConditionalProduct"],
                            ["t" => "Число целое", "n" => "PriceBase"],
                            ["t" => "Логическое", "n" => "ShowEmptyFolder"]
                        ],
                        "_type" => "record",
                        "f" => 2
                    ],
                    true,
                    [
                        "d" => [
                            [
                                null
                            ],
                            [
                                null
                            ],
                            true,
                            [
                                null
                            ],
                            "all"
                        ],
                        "s" => [
                            ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "excluded"],
                            ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "marked"],
                            ["t" => "Логическое", "n" => "recursive"],
                            ["t" => ["n" => "Массив", "t" => "Строка"], "n" => "selected"],
                            ["t" => "Строка", "n" => "type"]
                        ],
                        "_type" => "record",
                        "f" => 3
                    ]
                ],
                "s" => [
                    ["t" => "Число целое", "n" => "Archival"],
                    ["t" => "Строка", "n" => "BalanceForOrganization"],
                    ["t" => "Логическое", "n" => "CheckFolderExists"],
                    ["t" => "Запись", "n" => "ConfigurationOption"],
                    ["t" => ["n" => "Дата и время", "tz" => false], "n" => "DateTime"],
                    ["t" => "Строка", "n" => "GetColumnsFromSettings"],
                    ["t" => "Число целое", "n" => "GetPath"],
                    ["t" => "Логическое", "n" => "HideEmptyFolder"],
                    ["t" => "Логическое", "n" => "NewConfiguration"],
                    ["t" => "Строка", "n" => "NodeType"],
                    ["t" => "Число целое", "n" => "PriceCalcType"],
                    ["t" => "Строка", "n" => "PriceList"],
                    ["t" => "Логическое", "n" => "PriceListBasePrice"],
                    ["t" => ["n" => "Массив", "t" => "Число целое"], "n" => "PriceListHierarchy"],
                    ["t" => "Строка", "n" => "PublicationSaleState"],
                    ["t" => "Запись", "n" => "ServiceOption"],
                    ["t" => "Логическое", "n" => "TranslitSearchString"],
                    ["t" => "Запись", "n" => "selection"]
                ],
                "_type" => "record",
                "f" => 0
            ]
        ];

        $response = $this->send('PriceListPosition.ExecuteMassOperation', $params);
        if (!$response->ok()) {
            Log::info('PriceListPosition.ExecuteMassOperation - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return $json['result'];
        }
        return $json;
    }

    public function nomenclatureInShift($id, $page = 0)
    {
        $params = [
            "Фильтр" => [
                "d" => [
                    true,
                    null,
                    null,
                    $id,
                    true,
                    null,
                    null
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "GroupByNomType"],
                    ["t" => "Строка", "n" => "HasShiftOpened"],
                    ["t" => "Строка", "n" => "Product"],
                    ["t" => "Число целое", "n" => "Shift"],
                    ["t" => "Логическое", "n" => "ShowKind"],
                    ["t" => "Строка", "n" => "Warehouse"],
                    ["t" => "Строка", "n" => "Workplace"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => [
                "d" => [
                    [
                        true,
                        "NomenclatureCategory",
                        false
                    ],
                    [
                        true,
                        "Name",
                        false
                    ],
                    [
                        true,
                        "VATRate",
                        false
                    ]
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "l"],
                    ["t" => "Строка", "n" => "n"],
                    ["t" => "Логическое", "n" => "o"]
                ],
                "_type" => "recordset",
                "f" => 0
            ],
            "Навигация" => [
                "d" => [
                    true,
                    100,
                    $page,
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "ЕстьЕще"],
                    ["t" => "Число целое", "n" => "РазмерСтраницы"],
                    ["t" => "Число целое", "n" => "Страница"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => []
        ];

        $response = $this->send('Shift.NomenclatureList', $params);
        if (!$response->ok()) {
            Log::info('Shift.NomenclatureList - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return $json['result'];
        }
        return $json;
    }

    public function getPaymentMiniList(int $shiftId, int $limit = 35, $nexPosition = null)
    {
        if (empty($nexPosition)) {
            $position = null;
            $position_type = 'Строка';
        } else {
            $position_type = 'Запись';
            $position = [
                "d" => [
                    $nexPosition
                ],
                "s" => [
                    ["t" => "Строка", "n" => "NextClosedWTZText"]
                ],
                "_type" => "record",
                "f" => 1
            ];
        }

        $params = [
            "Фильтр" => [
                "d" => [
                    true,
                    false,
                    [
                        1,
                        3
                    ],
                    [
                        1,
                        2,
                        5,
                        6
                    ],
                    $shiftId, // Id смены
                    true,
                    false,
                    null
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "Closed"],
                    ["t" => "Логическое", "n" => "Deleted"],
                    ["t" => [
                        "n" => "Массив",
                        "t" => "Число целое"
                    ],
                        "n" => "DocumentTypes"],
                    ["t" => [
                        "n" => "Массив",
                        "t" => "Число целое"
                    ],
                        "n" => "OperationTypes"],
                    ["t" => "Число целое", "n" => "Shift"],
                    ["t" => "Логическое", "n" => "ShowAll"],
                    ["t" => "Логическое", "n" => "ShowHistory"],
                    ["t" => "Строка", "n" => "Workplace"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "Сортировка" => [
                "d" => [
                    [
                        false,
                        "NextClosedWTZText",
                        true
                    ]
                ],
                "s" => [
                    ["t" => "Логическое", "n" => "l"],
                    ["t" => "Строка", "n" => "n"],
                    ["t" => "Логическое", "n" => "o"]
                ],
                "_type" => "recordset",
                "f" => 0
            ],
            "Навигация" => [
                "d" => [
                    "forward",
                    true,
                    $limit,
                    $position
                ],
                "s" => [
                    ["t" => "Строка", "n" => "Direction"],
                    ["t" => "Логическое", "n" => "HasMore"],
                    ["t" => "Число целое", "n" => "Limit"],
                    ["t" => $position_type, "n" => "Position"]
                ],
                "_type" => "record",
                "f" => 0
            ],
            "ДопПоля" => [
                "Icon",
                "IconTitle",
                "Sale",
                "CompanyName",
                "PayCash",
                "PayBank",
                "PayCertificate",
                "Amount",
                "PayMethod",
                "Paid",
                "KKTNumber",
                "СНО",
                "TaxSystemCode",
                "TerminalError",
                "Overpaid",
                "CorrectionType",
                "BankType",
                "Nonfiscal",
                "IsPartialPay",
                "OpenedWTZ",
                "PaidPrice",
                "TotalPrice",
                "VATRate",
                "TotalVAT",
                "PrePaySum",
                "PostPaySum",
                "ProvisionSum",
                "FiscalSign",
                "Teller",
                "TellerPosition",
                "Taxes",
                "Key"
            ]
        ];

        $response = $this->send('Payment.MiniList', $params);
        if (!$response->ok()) {
            Log::info('Payment.MiniList - ' . json_encode($response->object(), JSON_UNESCAPED_UNICODE));
            $response->throw();
        }

        $json = $response->json();
        if (isset($json['result']['d']) && is_array($json['result']['d'])) {
            return $json['result'];
        }
        return $json;
    }
}
