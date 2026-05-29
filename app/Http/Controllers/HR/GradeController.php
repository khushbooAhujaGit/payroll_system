<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradeController extends MasterCrudController
{
    protected string $model = \App\Models\Grade::class;
    protected array  $rules = [
        'entity_id' => 'required|exists:entities,id',
        'name'      => 'required|string|max:100',
        'code'      => 'required|string|max:10',
        'level'     => 'required|integer|min:1',
        'min_ctc'   => 'nullable|numeric|min:0',
        'max_ctc'   => 'nullable|numeric|min:0',
        'is_active' => 'boolean',
    ];
}
