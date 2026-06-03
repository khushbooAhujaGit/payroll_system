<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EntityController extends MasterCrudController
{
    protected string $model     = \App\Models\Company::class;
    protected array  $relations = ['company'];
    protected array  $rules     = [
        'company_id'        => 'required|exists:companies,id',
        'name'              => 'required|string|max:200',
        'short_name'        => 'required|string|max:20',
        'pan'               => 'nullable|string|max:10',
        'pf_number'         => 'nullable|string|max:50',
        'esic_number'       => 'nullable|string|max:50',
        'state'             => 'required|string|max:100',
        'payroll_cycle'     => 'required|in:monthly,biweekly,weekly',
        'payroll_cutoff_day'=> 'required|integer|min:1|max:31',
        'is_active'         => 'boolean',
    ];
}
