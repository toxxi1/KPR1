<?php

namespace App\Services\Sbis;

use App\Models\SbisAccount;
use App\Models\Nomenclature as Nomenclatures;
use App\Services\Sbis;

class Nomenclature
{
    protected int $folder_id;
    protected int $root_folder_id;
    private static array $processedFolders = [];
    private static array $lastBatchIds = [];
    
    public function __construct(int $folder_id = 0, int $root_folder_id = 0)
    {
        $this->folder_id      = $folder_id;
        $this->root_folder_id = $root_folder_id;
    }
    
    public function getNomenclature(): void
    {
        $sbis = new Sbis(SbisAccount::find(SbisAccount::PROD_SBIS));
        
        $batchSize = 1000;
        
        do {
            $nomenclatureList = $sbis->getNomenclatureList($this->folder_id, $batchSize);
            
            $currentBatchIds = array_column($nomenclatureList, 'Id');
            
            if (empty($nomenclatureList)) {
                dump("⛔ API вернуло пустой список, выходим из цикла.");
                break;
            }
            
            if ($currentBatchIds === self::$lastBatchIds) {
                dump("⛔ API зациклилось (возвращает одинаковые ID), останавливаем выполнение.");
                break;
            }
            
            self::$lastBatchIds = $currentBatchIds;
            
            foreach ($nomenclatureList as $nomenclature) {
                $id = $nomenclature['Id'];
                
                if (isset(self::$processedFolders[$id])) {
                    dump("🔄 Пропускаем повторную папку: " . $id);
                    continue;
                }
                
                self::$processedFolders[$id] = true;
                
                if ($nomenclature['IsFolder']) {
                    dump("📂 Обрабатываем папку: " . $id);
                    $rootFolderId       = $this->root_folder_id > 0 ? $this->root_folder_id : $id;
                    $nestedNomenclature = new self($id, $rootFolderId);
                    $nestedNomenclature->getNomenclature();
                }
                
                Nomenclatures::updateOrCreate(
                    ['nomenclature_id_sbis' => $id],
                    [
                        'folder_sbis' => $nomenclature['Folder'],
                        'is_folder'   => $nomenclature['IsFolder'] ?? false,
                        'name'        => $nomenclature['Name'],
                    ]
                );
                
                dump("✅ Обработано: " . $nomenclature['Name']);
            }
        } while (count($nomenclatureList) === $batchSize);
    }
}
