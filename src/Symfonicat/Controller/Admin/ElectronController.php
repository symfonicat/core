<?php

namespace Symfonicat\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Symfonicat\Entity\Electron;
use Symfonicat\Form\ElectronType;
use Symfonicat\Repository\ElectronRepository;
use Symfonicat\Service\FileUploadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ElectronController extends AbstractController
{
    #[Route('/admin/e', name: 'app_electron_index', methods: ['GET'])]
    public function index(ElectronRepository $electronRepository): Response
    {
        return $this->render('admin/electron/index.html.twig', [
            'electrons' => $electronRepository->findAllOrdered(),
        ]);
    }

    #[Route('/admin/e/create', name: 'app_electron_create', methods: ['GET', 'POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        FileUploadService $fileUploadService,
    ): Response {
        $electron = new Electron();
        $form = $this->createForm(ElectronType::class, $electron);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->normalizeTypeSelection($electron);
                $this->handleFaviconUpload($form, $electron, $fileUploadService);
            } catch (\RuntimeException | \InvalidArgumentException $error) {
                $form->addError(new FormError($error->getMessage()));
            }

            if ($form->isValid()) {
                $entityManager->persist($electron);
                $entityManager->flush();

                return $this->redirectToRoute('app_electron_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('admin/electron/create.html.twig', [
            'electron' => $electron,
            'form' => $form,
        ]);
    }

    #[Route('/admin/e/{id}', name: 'app_electron_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Electron $electron,
        EntityManagerInterface $entityManager,
        FileUploadService $fileUploadService,
    ): Response {
        $form = $this->createForm(ElectronType::class, $electron);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->normalizeTypeSelection($electron);
                $this->handleFaviconUpload($form, $electron, $fileUploadService);
            } catch (\RuntimeException | \InvalidArgumentException $error) {
                $form->addError(new FormError($error->getMessage()));
            }

            if ($form->isValid()) {
                $entityManager->flush();

                return $this->redirectToRoute('app_electron_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('admin/electron/edit.html.twig', [
            'electron' => $electron,
            'form' => $form,
        ]);
    }

    #[Route('/admin/e/{id}', name: 'app_electron_delete', methods: ['POST'])]
    public function delete(Request $request, Electron $electron, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$electron->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($electron);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_electron_index', [], Response::HTTP_SEE_OTHER);
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

    private function handleFaviconUpload(FormInterface $form, Electron $electron, FileUploadService $fileUploadService): void
    {
        $file = $form->get('favicon')->getData();
        if (!$file instanceof UploadedFile) {
            return;
        }

        $type = trim($electron->getType());
        $targetId = trim((string) $electron->getTargetId());
        if ($type === '' || $targetId === '') {
            throw new \RuntimeException('Select the Electron target before uploading a favicon.');
        }

        $path = sprintf('electron/favicon/%s/%s.png', $type, $targetId);
        $electron->setFavicon($fileUploadService->uploadPublicImageAsPng($file, $path));
    }
}
