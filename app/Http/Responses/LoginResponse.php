<?php

namespace App\Http\Responses;

use App\Services\LoginLanding;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * Role-aware password-login landing (mirrors the SAML login path through the
 * same LoginLanding rule). The old config('fortify.home') pointed at a dead
 * /dashboard route; this resolves the tenant-less-user landing gap flagged in
 * TODO.md with the one landing rule the app already uses everywhere else.
 */
class LoginResponse implements LoginResponseContract
{
    public function __construct(protected LoginLanding $landing) {}

    /**
     * @param  Request  $request
     * @return Response
     */
    public function toResponse($request)
    {
        return $this->landing->redirect($request->user());
    }
}
