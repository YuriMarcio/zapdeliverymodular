<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    use ApiResponse;

    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', 'Dados inválidos.', $validator->errors()->toArray(), 422);
        }

        $token = auth('api')->attempt($request->only('email', 'password'));

        if (!$token) {
            return $this->error('INVALID_CREDENTIALS', 'E-mail ou senha incorretos.', [], 401);
        }

        $user = auth('api')->user();

        return $this->success([
            'accessToken'  => $token,
            'refreshToken' => $token,
            'user'         => new UserResource($user),
        ]);
    }

    public function logout(): JsonResponse
    {
        auth('api')->logout();

        return $this->success(null, 'Sessão encerrada com sucesso.');
    }

    public function refresh(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'refreshToken' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', 'refreshToken é obrigatório.', [], 422);
        }

        try {
            $newToken = auth('api')->setToken($request->refreshToken)->refresh();

            return $this->success(['accessToken' => $newToken]);
        } catch (\Throwable) {
            return $this->error('INVALID_TOKEN', 'Token de refresh inválido ou expirado.', [], 401);
        }
    }

    public function me(): JsonResponse
    {
        return $this->success(new UserResource(auth('api')->user()));
    }

    public function changePassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'newPassword' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return $this->error('VALIDATION_ERROR', 'Dados inválidos.', $validator->errors()->toArray(), 422);
        }

        $user = auth('api')->user();
        $user->password = $request->input('newPassword');
        $user->must_change_password = false;
        $user->save();

        return $this->success(new UserResource($user), 'Senha atualizada com sucesso.');
    }
}
