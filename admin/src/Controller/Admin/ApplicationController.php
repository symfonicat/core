<?php

namespace Symfonicat\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Symfonicat\Entity\Application;
use Symfonicat\Form\ApplicationType;
use Symfonicat\Repository\ApplicationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApplicationController extends AbstractController
{
    #[Route('/admin/a', name: 'symfonicat_application_index', methods: ['GET'])]
    public function index(ApplicationRepository $applicationRepository, \Symfonicat\Repository\EnvParentRepository $envParentRepository): Response
    {
        return $this->render('@symfonicat/application/index.html.twig', [
            'applications' => $applicationRepository->findAllOrdered(),
            'env_parents' => $envParentRepository->findAllOrdered(),
        ]);
    }

    #[Route('/admin/a/create', name: 'symfonicat_application_create', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $application = new Application();
        $form = $this->createForm(ApplicationType::class, $application);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->normalizeTypeSelection($application);
            } catch (\RuntimeException | \InvalidArgumentException $error) {
                $form->addError(new FormError($error->getMessage()));
            }

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

    #[Route('/admin/a/{id}', name: 'symfonicat_application_edit', methods: ['GET', 'POST'], requirements: ['id' => '.+'])]
    public function edit(
        Request $request,
        Application $application,
        EntityManagerInterface $entityManager,
    ): Response {
        $form = $this->createForm(ApplicationType::class, $application);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->normalizeTypeSelection($application);
            } catch (\RuntimeException | \InvalidArgumentException $error) {
                $form->addError(new FormError($error->getMessage()));
            }

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

    #[Route('/admin/a/{id}/delete', name: 'symfonicat_application_delete', methods: ['POST'], requirements: ['id' => '.+'])]
    public function delete(Request $request, Application $application, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$application->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($application);
            $entityManager->flush();
        }

        return $this->redirectToRoute('symfonicat_application_index', [], Response::HTTP_SEE_OTHER);
    }

    private function normalizeTypeSelection(Application $application): void
    {
        if ($application->isDomainType()) {
            $application->setSubdomain(null);

            return;
        }

        if ($application->isSubdomainType()) {

            return;
        }
    }
}
