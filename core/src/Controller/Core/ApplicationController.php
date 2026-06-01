<?php

namespace Symfonicat\Controller\Core;

use Doctrine\ORM\EntityManagerInterface;
use Symfonicat\Entity\Application;
use Symfonicat\Form\ApplicationType;
use Symfonicat\Repository\ApplicationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApplicationController extends AbstractController
{
    #[Route('/core/a', name: 'symfonicat_application_index', methods: ['GET'])]
    public function index(ApplicationRepository $applicationRepository, \Symfonicat\Repository\EnvParentRepository $envParentRepository): Response
    {
        return $this->render('@symfonicat/application/index.html.twig', [
            'applications' => $applicationRepository->findAllOrdered(),
            'env_parents' => $envParentRepository->findAllOrdered(),
        ]);
    }

    #[Route('/core/a/create', name: 'symfonicat_application_create', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $application = new Application();
        $form = $this->createForm(ApplicationType::class, $application);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->isValid()) {
                $entityManager->persist($application);
                $entityManager->flush();

                return $this->redirectToRoute('symfonicat_application_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('@symfonicat/application/create.html.twig', [
            'application' => $application,
            'form' => $form,
        ]);
    }

    #[Route('/core/a/{id}', name: 'symfonicat_application_edit', methods: ['GET', 'POST'], requirements: ['id' => '[^/]+'])]
    public function edit(
        Request $request,
        string $id,
        ApplicationRepository $applicationRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $application = $applicationRepository->find($id);
        if (!$application instanceof Application) {
            $this->addFlash('warning', sprintf('Application "%s" not found.', $id));

            return $this->redirectToRoute('symfonicat_application_index', [], Response::HTTP_SEE_OTHER);
        }

        $form = $this->createForm(ApplicationType::class, $application);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->isValid()) {
                $entityManager->flush();

                return $this->redirectToRoute('symfonicat_application_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('@symfonicat/application/edit.html.twig', [
            'application' => $application,
            'form' => $form,
        ]);
    }

    #[Route('/core/a/{id}/delete', name: 'symfonicat_application_delete', methods: ['POST'], requirements: ['id' => '[^/]+'])]
    public function delete(
        Request $request,
        string $id,
        ApplicationRepository $applicationRepository,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $application = $applicationRepository->find($id);
        if (!$application instanceof Application) {
            $this->addFlash('warning', sprintf('Application "%s" not found.', $id));

            return $this->redirectToRoute('symfonicat_application_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('delete'.$application->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($application);
            $entityManager->flush();
        }

        return $this->redirectToRoute('symfonicat_application_index', [], Response::HTTP_SEE_OTHER);
    }
}
