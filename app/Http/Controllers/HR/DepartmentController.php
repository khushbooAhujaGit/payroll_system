<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends MasterCrudController
{
    protected string $model     = \App\Models\Department::class;
    protected array  $relations = ['parent', 'children'];
    protected array  $rules     = [
        'entity_id' => 'required|exists:entities,id',
        'name'      => 'required|string|max:100',
        'code'      => 'required|string|max:10',
        'parent_id' => 'nullable|exists:departments,id',
        'is_active' => 'boolean',
    ];
}
