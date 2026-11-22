<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Exceptions\BadRequestException;
use App\Repositories\UserRepository;

class ProfileController
{
    private UserRepository $userRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
    }

    public function show(Request $request): void
    {
        $accountId = $request->getUserId();
        $user = $this->userRepository->findById($accountId);
        Response::json(['profile' => $user]);
    }

    public function update(Request $request): void
    {
        $clientId = $request->getClientId();
        if (!$clientId) {
            throw new BadRequestException('Редактирование профиля доступно для клиентов');
        }

        $body = $request->getBody();
        $phone = isset($body['phone']) ? trim($body['phone']) : null;
        $email = isset($body['email']) ? trim($body['email']) : null;
        $lastName = isset($body['last_name']) ? trim($body['last_name']) : null;
        $firstName = isset($body['first_name']) ? trim($body['first_name']) : null;
        $middleName = isset($body['middle_name']) ? trim($body['middle_name']) : null;

        $this->userRepository->updateClientProfile($clientId, $phone, $email, $lastName, $firstName, $middleName);

        $updated = $this->userRepository->findById($request->getUserId());
        Response::json([
            'message' => 'Профиль успешно обновлён',
            'profile' => $updated,
        ]);
    }
}
