<?php

use Illuminate\Support\Facades\Auth;

// Заголовок панели по роли пользователя
function getAuthUserPanelTitle()
{
    $default = 'Панель управления';
    if (!Auth::check()) {
        return $default;
    }
    if (Auth()->user()->hasRole(App\Models\User::SUPER_ADMIN)) {
        return 'Панель суперадмин-ра';
    } elseif (Auth()->user()->hasRole(App\Models\User::PARTNER)) {
        return 'Кабинет партнера';
    } elseif (Auth()->user()->hasRole(App\Models\User::MANAGER)) {
        return 'Панель менеджера';
    } elseif (Auth()->user()->hasRole(App\Models\User::EMPLOYEE)) {
        return 'Панель сотрудника';
    } elseif (Auth()->user()->hasRole(App\Models\User::CLIENT)) {
        return 'Панель администратора';
    } elseif (Auth()->user()->hasRole(App\Models\User::PARTNER_MANAGER)) {
        return 'Панель менеджера';
    } elseif (Auth()->user()->hasRole(App\Models\User::PARTNER)) {
        return 'Панель партнера';
    }
    return $default;
}

// Получить все пункты основного меню
function getSidabarMenuItems()
{
    $sidebarMenu = [
        // Менеджер партнерки
        'partner_program.manager.requests' => [
            'title' => 'Заявки',
            'route' => 'pp.requests.index',
            'icon' => 'albums',
        ],
        'partner_program.manager.users' => [
            'title' => 'Сводная',
            'route' => 'pp.users.index',
            'icon' => 'summary',
        ],
        'partner_program.manager.reports' => [
            'title' => 'Отчеты',
            'route' => 'pp.reports.index',
            'icon' => 'reports',
        ],
        'partner_program.manager.ref_links' => [
            'title' => 'Реф. ссылки',
            'title-min' => 'Реф.<br> ссылки',
            'route' => 'pp.ref_links.index',
            'icon' => 'link',
        ],
        // Партнер
        'partner_program.partner.leads' => [
            'title' => 'Регистр. клиента',
            'title-min' => 'Регистр.<br> клиента',
            'route' => 'pp.leads.create',
            'icon' => 'card',
        ],
        'partner_program.partner.users.create' => [
            'title' => 'Регистр. партнера',
            'title-min' => 'Регистр.<br> партнера',
            'route' => 'pp.users.create',
            'icon' => 'create',
        ],
        'partner_program.partner.reports' => [
            'title' => 'Отчеты',
            'icon' => 'reports',
            'dropdown' => [
                [
                    'title' => 'Сделки',
                    'route' => 'pp.reports.partner.leads',
                ],
                [
                    'title' => 'Счета / Оплаты',
                    'route' => 'pp.reports.partner.invoices',
                ],
                [
                    'title' => 'Выплаты / Вознаграждения',
                    'route' => 'pp.reports.partner.rewards',
                ],
            ],
        ],
        'partner_program.partner.ref_links' => [
            'title' => 'Реф. ссылки',
            'title-min' => 'Реф.<br> ссылки',
            'route' => 'pp.ref_links.indexPartner',
            'icon' => 'link',
        ],
        // Суперадмин
        'users' => [
            'title' => 'Клиенты',
            'route' => 'users.index'
        ],
        'managers' => [
            'title' => 'Сотрудники',
            'title-min' => 'Сотр-ки',
            'route' => 'managers.index'
        ],
        // Менеджер
        'modules' => [
            'title' => 'Модули',
            'route' => 'modules.index'
        ],
        // Клиенты
        'connections' => [
            'title' => 'Подключения',
            'title-min' => 'Подкл-ия',
            'dropdown' => [
                [
                    'title' => 'Сбис',
                    'route' => 'sbisAccounts.index',
                    'taviatCan' => 'connections.sbis.view',
                ],
                [
                    'title' => 'Хранилище данных',
                    'route' => 'dbConnections.index',
                    'taviatCan' => 'connections.data_storage.view',
                ],
                [
                    'title' => 'Wazzup24',
                    'route' => 'whatsapp.index',
                    'taviatCan' => 'connections.wazzup24.view',
                ],
                /*[
                    'title' => 'WP франчайзи',
                    'route' => 'sbisSubaccounts.index',
                    'taviatCan' => 'connections.sbis.view',
                ],*/
            ],
        ],
        'data_parsing' => [
            'title' => 'Сбор информации',
            'title-min' => 'Сбор инф-ии',
            'dropdown' => [
                [
                    'title' => 'Пользовательские таблицы',
                    'route' => 'user_tables.index',
                    'taviatCan' => 'data_parsing.user_tables.view',
                ],
                [
                    'title' => 'Сотрудники',
                    'route' => 'employees.index',
                    'taviatCan' => 'data_parsing.employees.view',
                ],
                [
                    'title' => 'Лиды',
                    'route' => 'leads.index',
                    'taviatCan' => 'data_parsing.view',
                ],
                [
                    'title' => 'События',
                    'route' => 'crmEvents.index',
                    'taviatCan' => 'data_parsing.view',
                ],
                [
                    'title' => 'Исходящие счета',
                    'route' => 'outbills.index',
                    'taviatCan' => 'data_parsing.view',
                ],
                [
                    'title' => 'Реализации',
                    'route' => 'realizations.index',
                    'taviatCan' => 'data_parsing.view',
                ],
                [
                    'title' => 'Обращения',
                    'route' => 'complaints.index',
                    'taviatCan' => 'data_parsing.view',
                ],
                [
                    'title' => 'Наряды',
                    'route' => 'serviceOrders.index',
                    'taviatCan' => 'data_parsing.view',
                ],
                [
                    'title' => 'Звонки',
                    'route' => 'calls.index',
                    'taviatCan' => 'data_parsing.view',
                ],
                [
                    'title' => 'Номенклатура',
                    'route' => 'nomenclatures.index',
                    'taviatCan' => 'data_parsing.view',
                ],
                [
                    'title' => 'Виды работ',
                    'route' => 'work_types.index',
                    'taviatCan' => 'data_parsing.view',
                ],
                [
                    'title' => 'Чеки',
                    'route' => 'sale_orders.index',
                    'taviatCan' => 'data_parsing.view',
                ],
                [
                    'title' => 'Настройка импорта данных',
                    'route' => 'schedule.index',
                    'taviatCan' => 'data_parsing.schedules.view',
                ],
                [
                    'title' => 'Сопоставление тем отношений<br> с номенклатурой',
                    'route' => 'themes_nomenclatures.index',
                    'taviatCan' => 'data_parsing.view',
                ],
                [
                    'title' => 'Витрины данных',
                    'route' => 'data_sheets.index',
                    'taviatCan' => 'data_parsing.data_sheets.view',
                ],
            ],
        ],
        'services' => [
            'title' => 'Сервисы',
            'dropdown' => [
                [
                    'title' => 'Сервис сокращения ссылок',
                    'route' => 'links_redirect.index',
                    'taviatCan' => 'services.links_redirect.view',
                ],
                [
                    'title' => 'Списания в пересорт',
                    'route' => 'sorting.index',
                    'taviatCan' => 'astra.view',
                ],
                [
                    'title' => 'Отчет "Потребности"',
                    'route' => 'balAnalReport.index',
                    'taviatCan' => 'auto_reports.needs.view',
                ],
                [
                    'title' => 'Инвентаризация',
                    'route' => 'inventory.index',
                    'taviatCan' => 'inventory.view',
                ],
                /*[
                    'title' => 'NPS',
                    'route' => 'nps.index',
                    'taviatCan' => 'nps.view',
                ],
                [
                    'title' => 'Экспорт ТТК',
                    'route' => 'sbisExportTtk.index',
                    'taviatCan' => 'connections.sbis.view',
                ],*/
                [
                    'title' => 'Менеджер паролей',
                    'route' => 'passwords.index',
                    'taviatCan' => 'passwordsManager.view',
                ],
                [
                    'title' => 'WordPress',
                    'route' => 'wp.index',
                    'taviatCan' => 'wordpress.view',
                ],
                [
                    'title' => 'Интеграция с Iiko',
                    'route' => 'iiko.index',
                    'taviatCan' => 'iiko.view',
                ],
                /*[
                    'title' => 'Мой Склад, интеграция',
                    'route' => 'ms.index',
                    'taviatCan' => 'my_warehouse.view',
                ],*/
                /*[
                    'title' => 'Выгрузка смен и чеков',
                    'route' => 'shifts.index',
                    'taviatCan' => 'shifts.view',
                ],*/
                [
                    'title' => 'Отправка опросного листа',
                    'route' => 'questionnaireKKT.index',
                    'taviatCan' => 'questionKKT.view',
                ],
                [
                    'title' => 'Интеграция с поставщиками шин',
                    'route' => 'fortochki.index',
                    'taviatCan' => 'fortochki.view',
                ],
            ],
        ],
        'event_chains' => [
            'title' => 'Цепочки событий',
            'title-min' => 'Цеп-ки соб-ий',
            'route' => 'eventChains.index'
        ],
        'reports' => [
            'title' => 'Отчеты',
            'route' => 'reports.index'
        ],
        'instructions' => [
            'title' => 'Инструкции',
            'title-min' => 'Инст-ии',
            'dropdown' => [
                [
                    'title' => 'Авторизация',
                    'route' => 'instructions.authorization',
                ],
                [
                    'title' => 'Регистрация',
                    'route' => 'instructions.registration',
                ],
                [
                    'title' => 'Подключение аккаунта СБИС',
                    'route' => 'instructions.sbisAccount',
                    'showInRoutes' => [
                        'sbisAccounts.index',
                    ],
                ],
                [
                    'title' => 'Хранилище данных',
                    'route' => 'instructions.dataStore',
                ],
                [
                    'title' => 'Wazzup24',
                    'route' => 'instructions.wazzup24',
                    'showInRoutes' => [
                        'whatsapp.index',
                    ],
                ],
                [
                    'title' => 'Сбор информации',
                    'route' => 'instructions.parser',
                    'showInRoutes' => [
                        'employees.index',
                        'leads.index',
                        'crmEvents.index',
                        'outbills.index',
                        'realizations.index',
                        'complaints.index',
                        'serviceOrders.index',
                        'calls.index',
                        'nomenclatures.index',
                        'work_types.index',
                    ],
                ],
                [
                    'title' => 'Настройка загрузки данных',
                    'route' => 'instructions.schedule',
                    'showInRoutes' => [
                        'schedule.index',
                    ],
                ],
                [
                    'title' => 'Цепочки событий',
                    'route' => 'instructions.chains',
                    'showInRoutes' => [
                        'eventChains.index',
                    ],
                ],
                [
                    'title' => 'Отчеты',
                    'route' => 'instructions.reports',
                ],
                [
                    'title' => 'Пользовательские таблицы',
                    'route' => 'instructions.user_tables',
                    'showInRoutes' => [
                        'user_tables.index',
                    ],
                ],
                [
                    'title' => 'Оплата',
                    'route' => 'instructions.payment',
                ],
            ],
        ],
        'settings' => [
            'title' => 'Настройки',
            'dropdown' => [
                [
                    'title' => 'Изменить пароль',
                    'route' => 'settings.change_password'
                ],
            ],
        ],
        'account' => [
            'title' => Auth::user()->name ?? 'Аккаунт',
            'dropdown' => [
                [
                    'title' => 'Выйти',
                    'route' => 'logout',
                ],
            ],
        ],
        'login' => [
            'title' => 'Авторизация',
            'route' => 'login',
            'icon' => 'account',
        ],
    ];
    if (Auth::check()) {
        if (Auth::user()->hasRole('Client')) {
            array_unshift($sidebarMenu['settings']['dropdown'], [
                'title' => 'Реквизиты',
                'route' => 'settings.requisites'
            ]);
            array_unshift($sidebarMenu['settings']['dropdown'], [
                'title' => 'Гостевые аккаунты',
                'route' => 'clientGuest.index'
            ]);
            if (Auth::user()->taviatCan('permissions.view')) {
                array_unshift($sidebarMenu['settings']['dropdown'], [
                    'title' => 'Права доступа',
                    'route' => 'permissions.index'
                ]);
            }
            if (Auth::user()->taviatCan('wordpress.view')) {
                array_unshift($sidebarMenu['settings']['dropdown'], [
                    'title' => 'Настройка вебхуков',
                    'route' => 'wp.webhooks'
                ]);
            }
        }
        $checkEmail = Auth::user()->getParentClientUser()->email ?? '';
        foreach ($sidebarMenu['data_parsing']['dropdown'] as $k => $item) {
            if (
                $item['route'] === 'themes_nomenclatures.index'
                && $checkEmail !== 'admin@sbisvyatka.com'
            ) {
                unset($sidebarMenu['data_parsing']['dropdown'][$k]);
            }
        }
    }

    return $sidebarMenu;
}

// Проверка на active class у элементов меню
function checkSidebarItemActiveClass($route)
{
    $check = [
        $route
    ];
    $check[] = str_replace('.index', '.edit', $route);
    $check[] = str_replace('.index', '.show', $route);
    $check[] = str_replace('.index', '.create', $route);
    /*if (strpos($route, '.') !== false) {
        $routeItems = explode('.', $route);
        if (count($routeItems) > 2) {
            return str_starts_with(getCurrentRouteName(), $routeItems[0] . '.' . $routeItems[1]);
        }
    }*/
    return in_array(getCurrentRouteName(), $check);
}

// Отобразить ссылку на инструкцию для данного раздела, если она имеется
function getCurrentSectionInstructionLink()
{
    $sidabarMenuItems = getSidabarMenuItems();
    $link = false;
    foreach ($sidabarMenuItems['instructions']['dropdown'] as $instruction) {
        foreach ($instruction['showInRoutes'] ?? [] as $section) {
            if (checkSidebarItemActiveClass($section)) {
                $link = route($instruction['route']);
            }
        }
    }
    return $link ? '<a href="' . $link . '" class="workspace-header-instruction-link">Справочная информация</a>' : '';
}

// Русификация для раздела "Потребности"
function depthOfAnalysisTranslate($slug)
{
    switch ($slug) {
        case 'lastday':
            return 'День';
        case 'lastmonth':
            return 'Месяц';
        case 'lastquarter':
            return 'Квартал';
        case 'lasthalfyear':
            return 'Полугодие';
        case 'lastyear':
            return 'Год';
    }
    return $slug;
}
