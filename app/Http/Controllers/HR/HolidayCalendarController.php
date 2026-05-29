<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HolidayCalendarController extends MasterCrudController
{
    protected string $model     = \App\Models\HolidayCalendar::class;
    protected array  $relations = ['holidays'];
    protected array  $rules     = [
        'entity_id' => 'required|exists:entities,id',
        'name'      => 'required|string|max:200',
        'year'      => 'required|integer',
        'region'    => 'nullable|string|max:100',
        'is_active' => 'boolean',
    ];
}
