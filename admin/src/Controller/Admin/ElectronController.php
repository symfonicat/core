<?php

namespace Symfonicat\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Symfonicat\Entity\Electron;
use Symfonicat\Form\ElectronType;
use Symfonicat\Repository\ElectronRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ElectronController extends AbstractController
{
    #[Route('/admin/e', name: 'symfonicat_electron_index', methods: ['GET'])]
    public function index(ElectronRepository $electronRepository, \Symfonicat\Repository\EnvParentRepository $envParentRepository): Response
    {
        return $this->render('@symfonicat/electron/index.html.twig', [
            'electrons' => $electronRepository->findAllOrdered(),
            'env_parents' => $envParentRepository->findAllOrdered(),
        ]);
    }

    #[Route('/admin/e/create', name: 'symfonicat_electron_create', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $electron = new Electron();
        $form = $this->createForm(ElectronType::class, $electron);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->normalizeTypeSelection($electron);
            } catch (\RuntimeException | \InvalidArgumentException $error) {
                $form->addError(new FormError($error->getMessage()));
            }

            if ($form->isValid()) {
                $entityManager->persist($electron);
                $entityManager->flush();

                return $this->redirectToRoute('symfonicat_electron_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('@symfonicat/electron/create.html.twig', [
            'electron' => $electron,
            'form' => $form,
        ]);
    }

    #[Route('/admin/e/{id}', name: 'symfonicat_electron_edit', methods: ['GET', 'POST'], requirements: ['id' => '.+'])]
    public function edit(
        Request $request,
        Electron $electron,
        EntityManagerInterface $entityManager,
    ): Response {
        $form = $this->createForm(ElectronType::class, $electron);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->normalizeTypeSelection($electron);
            } catch (\RuntimeException | \InvalidArgumentException $error) {
                $form->addError(new FormError($error->getMessage()));
            }

            if ($form->isValid()) {
                $entityManager->flush();

                return $this->redirectToRoute('symfonicat_electron_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('@symfonicat/electron/edit.html.twig', [
            'electron' => $electron,
            'form' => $form,
        ]);
    }

    #[Route('/admin/e/{id}/delete', name: 'symfonicat_electron_delete', methods: ['POST'], requirements: ['id' => '.+'])]
    public function delete(Request $request, Electron $electron, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$electron->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($electron);
            $entityManager->flush();
        }

        return $this->redirectToRoute('symfonicat_electron_index', [], Response::HTTP_SEE_OTHER);
    }

    private function normalizeTypeSelection(Electron $electron): void
    {
        if ($electron->isDomainType()) {
            $electron->setProject(null);
            $electron->setApplication(null);

            return;
        }

        if ($electron->isProjectType()) {
            $electron->setApplication(null);

            return;
        }

        if ($electron->isApplicationType()) {
            $electron->setDomain(null);
            $electron->setProject(null);
        }
    }
}
