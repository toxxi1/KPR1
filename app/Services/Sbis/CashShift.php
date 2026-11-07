<?php

namespace App\Services\Sbis;

use App\Mail\JsonReport;
use App\Models\Nomenclature;
use App\Models\SbisAccount;
use App\Models\Shifts;
use App\Services\Sbis;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log as LaravelLog;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class CashShift
{
    private array $shiftIds;

    protected function getSbis(): Sbis
    {
        return new Sbis(SbisAccount::find(SbisAccount::PROD_SBIS));
    }


    public function getListShifts()
    {
        $shiftIds = Shifts::select('shift_id')->distinct()->get()->pluck('shift_id')->toArray();
        $this->shiftIds = $shiftIds ?? [];
        //dd($this->shiftIds);

        $shifts = $this->getSbis()->getShifts(null, 10);
        //dd($shifts[0]);
        $this->processShifts($this->getSbis(), $shifts);
        dump('Обработка чеков завершена');
    }

    private function processShifts($sbis, $shifts)
    {
        foreach ($shifts as $shift) {
            //dump($shift['id']);
            if (!empty($shift['Folder$'])) {
                $subShifts = $sbis->getShifts($shift['id'], 100);
                $this->processShifts($sbis, $subShifts);
            } else {
                $this->getShiftInfo($shift);
            }
        }
    }

    private function getShiftInfo($shift)
    {
        // TODO убрать
        /*if ($shift['ShiftId'] !== 42301) {
            return;
        }*/

        //Проверяем закрыта ли смета
        if ($shift['ClosedWTZ'] === null) {
            return;
        }

        //Проверяем отправляли уже отчет или нет
        //Shifts::where('shift_id', $shift['ShiftId'])->first()
        if (in_array($shift['ShiftId'], $this->shiftIds)) {
            //dump('Чек ' . $shift['ShiftId'] . ' уже есть в базе');
            return;
        }

        //dump('Обрабатываем ' . $shift['ShiftId']);

        // Проверяем название кассы по регулярному выражению [1rep]
        preg_match('/\[(\d{1,})rep\]/', $shift['KKMs'][0]['Name'], $matches);
        if (empty($matches[1])) {
            dump('Не найден номер кассы / ' . $shift['KKMs'][0]['Name'] . ' / ' . $shift['ShiftId']);
            /*dump([
                'Не найден номер кассы',
                $shift['KKMs'],
            ]);*/
            return;
        }

        dump('Обработка чека / ' . $shift['KKMs'][0]['Name'] . ' / ' . $shift['ShiftId']);

        $sbis = $this->getSbis();

        // Обработка всех чеков и подсчет суммы по типам оплаты каждой позиции
        $payBankTotal = 0;

        $paymentsData = [];
        $salesList = $this->getShiftPaymentsList($sbis, $shift['ShiftId']);
        //dd(count($salesList));
        $test = [
            'PayCash' => 0,
            'PayBank' => 0,
            'PayCertificate' => 0,
        ];
        foreach ($salesList as $k => $sale) {
            //dump('Обработка чека ' . $k + 1 . ' - ' . $sale['Sale']);
            $test['PayCash'] += $sale['PayCash'];
            $test['PayBank'] += $sale['PayBank'];
            $test['PayCertificate'] += $sale['PayCertificate'];
            //continue;

            $nomenclaturesPayData = $this->getSaleNomenclatyresPayTypesSum($sbis, $sale['Sale']);

            $shiftPayData = [
                'PayCash' => $sale['PayCash'],
                'PayBank' => $sale['PayBank'],
                'PayCertificate' => $sale['PayCertificate'],
            ];
            $shiftPayDataCalc = [
                'PayCash' => 0,
                'PayBank' => 0,
                'PayCertificate' => 0,
            ];

            foreach ($nomenclaturesPayData as $nomSbisId => $sum) {
                if ($sale['PayCash'] > 0 && $sale['PayBank'] === null && $sale['PayCertificate'] === null) {
                    // Оплата полностью наличными
                    $key = 'PayCash';

                    // TODO временно
                    $shiftPayDataCalc['PayCash'] += round($sum, 2);
                } elseif ($sale['PayCash'] === null && $sale['PayBank'] > 0 && $sale['PayCertificate'] === null) {
                    // Это оплата полностью безналом
                    $key = 'PayBank';

                    $payBankTotal += round($sum, 2);

                    // TODO временно
                    $shiftPayDataCalc['PayBank'] += round($sum, 2);
                } else {
                    $key = 'PayOther';
                }
                if (empty($paymentsData[$nomSbisId][$key])) {
                    $paymentsData[$nomSbisId][$key] = round($sum, 2);
                } else {
                    $paymentsData[$nomSbisId][$key] += round($sum, 2);
                }
            }
            /*dump([
                $shiftPayData,
                $shiftPayDataCalc,
            ]);*/
            if (
                $shiftPayData['PayCash'] != $shiftPayDataCalc['PayCash']
                || $shiftPayData['PayBank'] != $shiftPayDataCalc['PayBank']
            ) {
                // TODO уведомление сделать
                /*dd([
                    'Не сходится',
                    $sale,
                    $shiftPayData,
                    $shiftPayDataCalc,
                ]);*/
            }
        }
        // Обработка всех чеков и подсчет суммы по типам оплаты каждой позиции

        $paymentMethod = [
            'cash' => $shift['PayCash'], // Наличные
            'card' => $shift['PayBank'], // Банк. картой
            'bank' => round($payBankTotal, 2), // Эквайринг (рассчитывается)
            'return' => $shift['Return'],
            'total' => $shift['Revenue'],
        ];

        //dump($test);
        //dd($paymentMethod);

        $nomenclature = $this->getNomenclatures($shift['ShiftId'], $paymentsData);

        $data = [
            'title' => $shift['Title'],
            'point_sell' => $shift['Companies'][0]['Name'],
            'cash_register_number' => 0,
            'date' => $shift['Date'],
            'open' => $shift['OpenedWTZ'],
            'closed' => $shift['ClosedWTZ'],
            'staff' => $shift['CashierName'],
            'payment_methods' => $paymentMethod,
            'nomenclatures' => $nomenclature,
        ];

        //Отправка на сервер
        try {
            //$rawName = $shift['Companies'][0]['Name'];
            //$safeName = Str::slug($rawName);

            // Порядковый номер кассы
            $cashNum = $matches[1];
            $data['cash_register_number'] = $cashNum;

            $jsonContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            //$filename = Carbon::parse($shift['OpenedWTZ'])->format('Y-m-d') . '-' . $safeName . '.json';
            //$filename = $shift['OpenedWTZ'] . '-' . $safeName . '.json';
            $filename = Carbon::parse($shift['ClosedWTZ'])->format('Y-m-d-H-i-s') . '-' . $cashNum . 'rep.json';

            Storage::disk('ftp')->put("staffs/$filename", $jsonContent);
            Storage::disk('ftp')->put("test/$filename", $jsonContent);

            Shifts::create(
                [
                    'sbis_id' => $shift['id'],
                    'shift_id' => $shift['ShiftId'],
                    'point' => $shift['Companies'][0]['Name'],
                    'open' => $shift['OpenedWTZ'],
                    'closed' => $shift['ClosedWTZ'],
                    'staff' => $shift['CashierName'],
                    'data' => json_encode($data),
                ]
            );

            try {
                Mail::to('kpr-irz@ya.ru')->send(new JsonReport($jsonContent));
                Mail::to('79279273277@ya.ru')->send(new JsonReport($jsonContent));
            } catch (\Exception $e) {
                dump($e->getMessage());
            }

            // Обрабатываем только 1 смену и выходим
            dump('Обработка чека ' . $shift['ShiftId'] . ' завершена');
            dd('Done');
        } catch (\Exception $e) {
            LaravelLog::channel('dev')->error($e);
            dump('Ошибка ' . $e->getMessage());
        }
    }

    private function getNomenclatures($id, $paymentsData): array
    {
        //dump('getNomenclatures');
        $goodArray = [];
        $page = 0;
        do {
            //dump('nomenclatureInShift ' . $page);
            $nomenclatures = $this->getSbis()->nomenclatureInShift($id, $page++);
            if (empty($nomenclatures['d']) || !is_array($nomenclatures['d'])) {
                break;
            }
            foreach ($nomenclatures['d'] as $nomenclature) {
                $nomSbisID = $nomenclature[1];
                $baseNomenclature = Nomenclature::where('nomenclature_id_sbis', $nomSbisID)->first();

                $goodArray[] = [
                    'id' => $baseNomenclature->nomenclature_id ?? null,
                    'sbis_id' => $nomenclature[1],
                    'name' => $nomenclature[2],
                    'count' => $nomenclature[6],
                    'price' => $nomenclature[11],
                    'total' => $nomenclature[7],
                    'payment_methods' => [
                        'cash' => $paymentsData[$nomSbisID]['PayCash'] ?? 0,
                        'bank' => $paymentsData[$nomSbisID]['PayBank'] ?? 0,
                        'other' => $paymentsData[$nomSbisID]['PayOther'] ?? 0,
                    ]
                ];
            }
        } while (!empty($nomenclatures['d']));

        return $goodArray;
    }

    private function getShiftPaymentsList($sbis, $shiftId)
    {
        //dump('getShiftPaymentsList');
        $hasMore = true;
        $next = null;
        $paymentsList = [];

        while ($hasMore) {
            //dump('getPaymentMiniList ' . $next);
            $hasMore = false;
            $res = $sbis->getPaymentMiniList($shiftId, 100, $next);
            $list = parseSbisDataToArray($res);
            $paymentsList = array_merge($paymentsList, $list);
            //dump('getPaymentMiniList ' . $next . ' done');
            if (!empty($res['m']['d'][0][0])) {
                $hasMore = true;
                $next = $res['m']['d'][0][0];
            }
            sleep(1);
            //dump('getPaymentMiniList has more ' . $next);
        }
        return $paymentsList;
    }

    private function getSaleNomenclatyresPayTypesSum($sbis, $saleID): array
    {
        $out = [];

        $data = $sbis->getOfdPayment($saleID);
        $nomList = parseSbisDataToArray($data['SaleNomenclatures']);
        foreach ($nomList as $nom) {
            $nomId = $nom['Nomenclature'];
            $sum = $nom['CheckSum'];
            if (empty($out[$nomId])) {
                $out[$nomId] = round($sum, 2);
            } else {
                $out[$nomId] += round($sum, 2);
            }
        }

        return $out;
    }
}
