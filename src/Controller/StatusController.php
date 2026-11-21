<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SyncMonitor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StatusController extends AbstractController
{
    #[Route('/status', name: 'app_status')]
    public function index(SyncMonitor $syncMonitor): Response
    {
        return $this->render('status/index.html.twig', [
            'syncs' => $syncMonitor->states(),
        ]);
    }
}
