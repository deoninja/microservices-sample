<?php

namespace App\Actions\User;

use App\Gateway\Clients\IdentityProvider;
use App\Gateway\Contracts\UserClientInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserShowAction
{
    public function __construct(
        protected UserClientInterface $userClient,
        protected IdentityProvider $identityProvider,
    ) {}

    public function __invoke(Request $request, int $id): JsonResponse
    {
        $headers = $this->identityProvider->getHeaders($request);
        $result  = $this->userClient->getById($id, $headers);

        return response()->json($result['body'], $result['status']);
    }
}
