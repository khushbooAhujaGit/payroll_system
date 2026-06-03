<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchController extends MasterCrudController
{
    protected string $model     = \App\Models\Branch::class;
    protected array  $relations = ['entity'];
    protected array  $rules     = [
        'company_id' => 'required|exists:companies,id',
        'name'      => 'required|string|max:100',
        'code'      => 'required|string|max:10',
        'city'      => 'nullable|string|max:100',
        'state'     => 'nullable|string|max:100',
        'pincode'   => 'nullable|string|max:10',
        'phone'     => 'nullable|string|max:15',
        'is_active' => 'boolean',
    ];
}
