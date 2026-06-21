<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SettingController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        return $this->success([
            'masterWhatsappInstance' => Setting::get('master_whatsapp_instance'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'masterWhatsappInstance' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', 'Dados inválidos.', $validator->errors()->toArray(), 422);
        }

        Setting::set('master_whatsapp_instance', $request->input('masterWhatsappInstance'));

        return $this->success([
            'masterWhatsappInstance' => Setting::get('master_whatsapp_instance'),
        ]);
    }
}
