<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class PlanController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->success(PlanResource::collection(Plan::orderBy('name')->get()));
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'slug'                         => 'required|string|max:50|unique:plans,slug',
            'name'                         => 'required|string|max:100',
            'marketingCommissionPercent'   => 'nullable|numeric|min:0|max:100',
            'features'                     => 'required|array',
            'features.*'                   => 'string',
            'isActive'                     => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', 'Dados inválidos.', $validator->errors()->toArray(), 422);
        }

        $plan = Plan::create([
            'slug'                          => $request->input('slug'),
            'name'                          => $request->input('name'),
            'marketing_commission_percent'  => $request->input('marketingCommissionPercent', 0),
            'features'                      => $request->input('features'),
            'is_active'                     => $request->input('isActive', true),
        ]);

        return $this->created(new PlanResource($plan));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $plan = Plan::find($id);
        if (!$plan) {
            return $this->error('NOT_FOUND', 'Plano não encontrado.', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'slug'                         => ['sometimes', 'string', 'max:50', Rule::unique('plans', 'slug')->ignore($plan->id)],
            'name'                         => 'sometimes|string|max:100',
            'marketingCommissionPercent'   => 'sometimes|numeric|min:0|max:100',
            'features'                     => 'sometimes|array',
            'features.*'                   => 'string',
            'isActive'                     => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', 'Dados inválidos.', $validator->errors()->toArray(), 422);
        }

        $plan->update([
            'slug'                          => $request->input('slug', $plan->slug),
            'name'                          => $request->input('name', $plan->name),
            'marketing_commission_percent'  => $request->input('marketingCommissionPercent', $plan->marketing_commission_percent),
            'features'                      => $request->input('features', $plan->features),
            'is_active'                     => $request->input('isActive', $plan->is_active),
        ]);

        return $this->success(new PlanResource($plan));
    }

    public function destroy(string $id): JsonResponse
    {
        $plan = Plan::find($id);
        if (!$plan) {
            return $this->error('NOT_FOUND', 'Plano não encontrado.', [], 404);
        }

        $plan->delete();

        return $this->success(['id' => $id]);
    }
}
