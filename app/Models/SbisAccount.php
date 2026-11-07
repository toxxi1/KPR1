<?php

namespace App\Models;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class SbisAccount extends Model
{
    const TEST_SBIS = 2;
    const PROD_SBIS = 1;

    public $timestamps = false;
    use HasFactory;

    protected $fillable = [
        'app_client_id',
        'app_secret',
        'secret_key',
        'sid',
        'token',
    ];

    /**
     * Получить информацию об аккаунте с API
     *
     * @return array|null
     */
    public static function getAccountInfo(): ?array
    {
        try {
            $client = new Client([
                'base_uri' => 'https://lk.taviat.ru/api/licenses/',
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . config('services.sbis.token'),
                ],
            ]);

            $response = $client->get('account-info');
            return json_decode($response->getBody()->getContents(), true);
        } catch (\Exception $e) {
            Log::error('Ошибка при получении данных из API: ' . $e->getMessage());
            return null;
        }
    }

    public static function hasActiveCustomLicense(): bool
    {
        $accountInfo = self::getAccountInfo();

        if (!isset($accountInfo['status']) || $accountInfo['status'] !== 'success') {
            Log::warning('Ошибка при проверке лицензии: некорректный статус ответа');
            return false;
        }

        $licenses = $accountInfo['data']['licenses'] ?? [];

        foreach ($licenses as $license) {
            if ($license['module'] === 'custom') {
                $endedAt = Carbon::parse($license['ended_at']);

                if ($endedAt->isFuture()) {
                    return true;
                }
            }
        }

        return false;
    }
}
