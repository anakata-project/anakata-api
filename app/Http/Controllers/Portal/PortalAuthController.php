<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Actions\Portal\AcceptAgencyInvitation;
use App\Actions\Portal\LoginAgencyUser;
use App\Actions\Portal\LogoutAgencyUser;
use App\Actions\Portal\ResetAgencyUserPassword;
use App\Actions\Portal\SendAgencyPasswordReset;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\AcceptInviteRequest;
use App\Http\Requests\Portal\PortalForgotPasswordRequest;
use App\Http\Requests\Portal\PortalLoginRequest;
use App\Http\Requests\Portal\PortalResetPasswordRequest;
use App\Http\Resources\Portal\PortalMeResource;
use App\Models\AgencyUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class PortalAuthController extends Controller
{
    public function accept(AcceptInviteRequest $request, AcceptAgencyInvitation $action): PortalMeResource
    {
        $agencyUser = $action->handle(
            $request,
            (string) $request->validated('email'),
            (string) $request->validated('token'),
            (string) $request->validated('password'),
        );

        return new PortalMeResource($agencyUser);
    }

    public function login(PortalLoginRequest $request, LoginAgencyUser $action): PortalMeResource
    {
        $agencyUser = $action->handle(
            $request,
            (string) $request->validated('email'),
            (string) $request->validated('password'),
        );

        return new PortalMeResource($agencyUser);
    }

    public function logout(Request $request, LogoutAgencyUser $action): Response
    {
        $action->handle($request);

        return response()->noContent();
    }

    public function me(Request $request): PortalMeResource
    {
        $agencyUser = $request->user('agency');

        if (! $agencyUser instanceof AgencyUser) {
            abort(401);
        }

        return new PortalMeResource($agencyUser);
    }

    public function forgot(PortalForgotPasswordRequest $request, SendAgencyPasswordReset $action): JsonResponse
    {
        $action->handle((string) $request->validated('email'));

        return response()->json([
            'message' => __('passwords.sent'),
        ]);
    }

    public function reset(PortalResetPasswordRequest $request, ResetAgencyUserPassword $action): JsonResponse
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
}
