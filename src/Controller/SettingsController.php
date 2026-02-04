<?php

namespace App\Controller;

use App\Form\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SettingsController extends AbstractController
{
    #[Route('/profile', name: 'app_profile_edit')]
    public function edit(Request $request, EntityManagerInterface $entityManager): Response
    {

        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        $user = $this->getUser();
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $entityManager->flush();
                $this->addFlash('success', 'Profile updated successfully!');

                return $this->redirectToRoute('app_profile_edit');
            }
        }
        $statusCode = ($form->isSubmitted() && !$form->isValid()) ? 422 : 200;
        return $this->render('settings/profile.html.twig', [
            'userForm' => $form->createView(),
        ], new Response(null, $statusCode));
    }
}
