<?php
// backend/app/Models/MesFormula.php
//
// Cache công thức nhuộm (thuốc nhuộm + hóa chất) đồng bộ từ MES module mesPfM, khóa theo
// pfmPfno. Xem migration create_mes_formulas_table + SyncMesBatchCompletionsCommand
// (phần đồng bộ công thức) + BpdbMachineMonitoringService::getBatchRecipe.

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MesFormula extends Model
{
    protected $table = 'mes_formulas';

    protected $fillable = [
        'pfm_pfno',
        'formula_id',
        'color_code',
        'article_code',
        'machine_code',
        'dye_count',
        'aux_count',
        'materials',
        'raw',
        'process',
        'source',
        'synced_at',
    ];

    protected $casts = [
        'materials' => 'array',
        'raw' => 'array',
        'process' => 'array',
        'synced_at' => 'datetime',
    ];
}
