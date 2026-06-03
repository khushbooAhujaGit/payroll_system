<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends MasterCrudController
{
    protected string $model     = \App\Models\Company::class;
    protected array  $relations = [];
    protected array  $rules     = [
        'name'       => 'required|string|max:200',
        'short_name' => 'required|string|max:20',
        'email'      => 'nullable|email',
        'pan'        => 'nullable|string|max:10',
        'is_active'  => 'boolean',
    ];
}
