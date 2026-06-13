<?php

namespace App\Http\Controllers;

use App\Models\ApiUser;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * @param array<string, mixed> $params
     * @param array<string, string> $settings
     */
    public function findErrors(array $params, array $settings): ?JsonResponse
    {
        $validator = Validator::make($params, $settings);
        $messages = $validator->errors();

        if (!$messages->isEmpty()) {
            return $this->basicResponse(
                403,
                [
                    'error' => "Request invalid.",
                    'field_errors' => $messages
                ]
            );
        }

        return null;
    }

    /**
     * @param array<string, mixed> $extra
     */
    public function basicResponse(int $code = 200, array $extra = []): JsonResponse
    {
        $data = [
            'success' => $code == 200,
        ];
        $data = array_merge($data, $extra);

        return response()->json(
            $data,
            $code
        );
    }

    protected function checkOwner(mixed $entityOwner): bool
    {
        if ($this->getOwner() !== $entityOwner) {
            return true;
        }
        return false;
    }

    protected function getOwner(): string
    {
        /** @var ApiUser $user */
        $user = Auth::user();
        return $user->owner;
    }
}
