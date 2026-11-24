<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Exceptions\NotFoundException;
use App\Repositories\ClientRepository;

class ClientController
{
    private ClientRepository $clientRepository;

    public function __construct()
    {
        $this->clientRepository = new ClientRepository();
    }

    public function index(Request $request): void
    {
        $search = (string) $request->getQuery('search', '');
        $clients = $this->clientRepository->findAll($search);
        Response::json(['clients' => $clients]);
    }

    public function show(Request $request): void
    {
        $id = (int) $request->getRouteParam('id');
        $client = $this->clientRepository->findById($id);
        if (!$client) {
            throw new NotFoundException("Клиент №{$id} не найден");
        }
        Response::json(['client' => $client]);
    }
}
