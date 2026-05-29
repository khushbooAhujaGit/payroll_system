<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DesignationController extends MasterCrudController
{
    protected string $model = \App\Models\Designation::class;
    protected array  $rules = [
        'entity_id' => 'required|exists:entities,id',
        'name'      => 'required|string|max:100',
        'code'      => 'nullable|string|max:10',
        'is_active' => 'boolean',
    ];
}
