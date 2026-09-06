<?php

declare(strict_types=1);

namespace App\UI\Http\Rest;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DemoController extends AbstractController
{
    #[Route('/', name: 'demo_home', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('demo/index.html.twig');
    }
}
