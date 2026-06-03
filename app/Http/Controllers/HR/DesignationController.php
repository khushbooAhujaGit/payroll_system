<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesignationController extends MasterCrudController
{
    protected string $model = \App\Models\Designation::class;
    protected array  $rules = [
        'company_id' => 'required|exists:companies,id',
        'name'      => 'required|string|max:100',
        'code'      => 'nullable|string|max:10',
        'is_active' => 'boolean',
    ];
}
