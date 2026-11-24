<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Exceptions\NotFoundException;
use App\Repositories\MasterRepository;

class MasterController
{
    private MasterRepository $masterRepository;

    public function __construct()
    {
        $this->masterRepository = new MasterRepository();
    }

    public function index(Request $request): void
    {
        $masters = $this->masterRepository->findAll();
        Response::json(['masters' => $masters]);
    }

    public function specializations(Request $request): void
    {
        $id = (int) $request->getRouteParam('id');
        $master = $this->masterRepository->findById($id);
        if (!$master) {
            throw new NotFoundException("Мастер №{$id} не найден");
        }

        $specs = $this->masterRepository->getSpecializations($id);
        Response::json(['master' => $master, 'specializations' => $specs]);
    }
}
