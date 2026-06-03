<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class MasterCrudController extends Controller
{
    protected string $model;
    protected array  $rules     = [];
    protected array  $relations = [];

    public function index(Request $request): JsonResponse
    {
        $q = $this->model::query()
            ->when(method_exists($this->model, 'scopeForEntity'), fn($q) => $q->forEntity($request->company_id))
            ->when(!empty($this->relations), fn($q) => $q->with($this->relations))
            ->when($request->search, fn($q) => $q->where('name', 'like', "%{$request->search}%"));

        return response()->json($request->has('paginate') ? $q->paginate(25) : $q->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize(class_basename($this->model) . '.create');
        $data   = $request->validate($this->rules);
        $record = $this->model::create($data);
        return response()->json(['data' => $record], 201);
    }

    public function show($id): JsonResponse
    {
        return response()->json(['data' => $this->model::with($this->relations)->findOrFail($id)]);
    }

    public function update(Request $request, $id): JsonResponse
    {
        $record = $this->model::findOrFail($id);
        $rules  = collect($this->rules)->mapWithKeys(fn($r, $k) => [$k => "sometimes|{$r}"])->toArray();
        $record->update($request->validate($rules));
        return response()->json(['data' => $record]);
    }

    public function destroy($id): JsonResponse
    {
        $this->model::findOrFail($id)->delete();
        return response()->json(['message' => 'Deleted.']);
    }
}
