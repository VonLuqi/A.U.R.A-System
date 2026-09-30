<?php

namespace App\Http\Controllers;

use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Requests\Profile\UploadAvatarRequest;
use App\Http\Resources\AuthUserResource;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Self-service profile API (Etapa I — PLAN_PERFIL_BRANDING §2).
 *
 * Self-only: always `$request->user()`. No Policy — Admin users stay on `/api/users`.
 */
class ProfileController extends Controller
{
    public function __construct(
        private readonly ProfileService $profiles,
    ) {}

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $this->profiles->update($request->user(), $validated);

        return response()->json([
            'user' => (new AuthUserResource($user))->resolve(),
        ]);
    }

    public function storeAvatar(UploadAvatarRequest $request): JsonResponse
    {
        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('avatar');

        $user = $this->profiles->storeAvatar($request->user(), $file);

        return response()->json([
            'user' => (new AuthUserResource($user))->resolve(),
        ], 201);
    }

    public function destroyAvatar(Request $request): JsonResponse
    {
        $user = $this->profiles->destroyAvatar($request->user());

        return response()->json([
            'user' => (new AuthUserResource($user))->resolve(),
        ]);
    }
}
