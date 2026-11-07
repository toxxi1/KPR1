<?php

namespace App\Services\Sbis;

use App\Models\SbisAccount;
use App\Services\Sbis;
use App\Models\Price as Prices;

class Price
{
    public function getPrices()
    {
        $sbis   = new Sbis(SbisAccount::find(SbisAccount::PROD_SBIS));
        $prices = $sbis->getPriceLists();
        
        foreach ($prices as $price) {
            if ($price['Id'] === -1) {
                continue;
            }
            
            if ( ! empty($price['SalePointsList'])) {
                $salePointsList = json_encode($price['SalePointsList']);
            }
            
            Prices::updateOrCreate(
                ['sbis_id' => $price['Id']],
                [
                    'name'        => $price['Name'],
                    'sale_points' => $salePointsList ?? null,
                    'is_used'     => $price['IsUsed'],
                ]
            );
            
            dump("✅ Обработано: " . $price['Name']);
        }
    }
}