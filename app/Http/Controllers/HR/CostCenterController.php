<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CostCenterController extends MasterCrudController
{
    protected string $model = \App\Models\CostCenter::class;
    protected array  $rules = [
        'entity_id' => 'required|exists:entities,id',
        'name'      => 'required|string|max:100',
        'code'      => 'required|string|max:20',
        'type'      => 'required|in:department,project,location,other',
        'is_active' => 'boolean',
    ];
}
