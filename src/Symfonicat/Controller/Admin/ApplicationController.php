<?php

namespace Symfonicat\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Symfonicat\Entity\Application;
use Symfonicat\Form\ApplicationType;
use Symfonicat\Repository\ApplicationRepository;
use Symfonicat\Repository\EnvParentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ApplicationController extends AbstractController
{
    #[Route('/admin/a/list', name: 'symfonicat_application_index', methods: ['GET'])]
    public function index(ApplicationRepository $applicationRepository, EnvParentRepository $envParentRepository): Response
    {
        return $this->render('admin/application/index.html.twig', [
            'applications' => $applicationRepository->findAllOrderedById(),
            'env_parents' => $envParentRepository->findAllOrdered(),
        ]);
    }

    #[Route('/admin/a/{id}', name: 'symfonicat_application_edit', methods: ['GET', 'POST'], requirements: ['id' => '.+'])]
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

            return $this->redirectToRoute('symfonicat_application_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/application/edit.html.twig', [
            'application' => $application,
            'form' => $form,
        ]);
    }

}
