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
    #[Route('/admin/a/list', name: 'app_application_index', methods: ['GET'])]
    public function index(ApplicationRepository $applicationRepository): Response
    {
        return $this->render('admin/application/index.html.twig', [
            'applications' => $applicationRepository->findAllOrderedById(),
        ]);
    }

    #[Route('/admin/a/create', name: 'app_application_create', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        ApplicationRepository $applicationRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $application = new Application();
        $form = $this->createForm(ApplicationType::class, $application, [
            'id_editable' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $id = trim((string) $application->getId());
            if ($id === '') {
                $form->get('id')->addError(new FormError('Application id is required.'));
            } elseif ($applicationRepository->find($id) instanceof Application) {
                $form->get('id')->addError(new FormError(sprintf('Application "%s" already exists.', $id)));
            }

            if ($form->isValid()) {
                $entityManager->persist($application);
                $entityManager->flush();

                return $this->redirectToRoute('app_application_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('admin/application/create.html.twig', [
            'application' => $application,
            'form' => $form,
        ]);
    }

    #[Route('/admin/a/{id}', name: 'app_application_show', methods: ['GET'])]
    public function show(string $id, ApplicationRepository $applicationRepository): Response
    {
        $application = $applicationRepository->find($id);
        if (!$application instanceof Application) {
            throw $this->createNotFoundException(sprintf('Application "%s" not found.', $id));
        }

        return $this->render('admin/application/show.html.twig', [
            'application' => $application,
        ]);
    }

    #[Route('/admin/a/{id}/edit', name: 'app_application_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        string $id,
        ApplicationRepository $applicationRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $application = $applicationRepository->find($id);
        if (!$application instanceof Application) {
            throw $this->createNotFoundException(sprintf('Application "%s" not found.', $id));
        }

        $form = $this->createForm(ApplicationType::class, $application, [
            'id_editable' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_application_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/application/edit.html.twig', [
            'application' => $application,
            'form' => $form,
        ]);
    }

    #[Route('/admin/a/{id}', name: 'app_application_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        string $id,
        ApplicationRepository $applicationRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $application = $applicationRepository->find($id);
        if (!$application instanceof Application) {
            return $this->redirectToRoute('app_application_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('delete'.$application->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($application);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_application_index', [], Response::HTTP_SEE_OTHER);
    }
}
