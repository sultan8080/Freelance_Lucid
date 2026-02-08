<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProjectOverviewController extends AbstractController
{
    #[Route('/project_overview', name: 'app_project_overview')]
    public function index(): Response
    {
        return $this->render('project_overview/index.html.twig', [
            'github_url' => 'https://github.com/sultan8080/Freelance_Lucid',
        ]);
    }
}
