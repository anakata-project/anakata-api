<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\AcceptInvitation;
use App\Actions\Auth\LoginUser;
use App\Actions\Auth\LogoutUser;
use App\Actions\Auth\ResetUserPassword;
use App\Actions\Auth\SendPasswordReset;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\MeResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AuthController extends Controller
{
    public function login(LoginRequest $request, LoginUser $action): MeResource
    {
        $user = $action->handle(
            $request,
            (string) $request->validated('email'),
            (string) $request->validated('password'),
        );

        return new MeResource($user);
    }

    public function logout(Request $request, LogoutUser $action): Response
    {
        $action->handle($request);

        return response()->noContent();
    }

    public function me(Request $request): MeResource
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return new MeResource($user);
    }

    public function forgotPassword(ForgotPasswordRequest $request, SendPasswordReset $action): JsonResponse
    {
        $action->handle((string) $request->validated('email'));

        return response()->json([
            'message' => __('passwords.sent'),
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request, ResetUserPassword $action): JsonResponse
    {
        $action->handle(
            (string) $request->validated('email'),
            (string) $request->validated('token'),
            (string) $request->validated('password'),
        );

        return response()->json([
            'message' => __('passwords.reset'),
        ]);
    }

    public function acceptInvitation(AcceptInvitationRequest $request, AcceptInvitation $action): MeResource
    {
        $user = $action->handle(
            $request,
            (string) $request->validated('email'),
            (string) $request->validated('token'),
            (string) $request->validated('password'),
        );

        return new MeResource($user);
    }
}
