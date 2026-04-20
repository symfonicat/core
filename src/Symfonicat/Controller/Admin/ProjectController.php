<?php

namespace Symfonicat\Controller\Admin;

use Symfonicat\Entity\Project;
use Symfonicat\Form\ProjectType;
use Symfonicat\Repository\ProjectRepository;
use Symfonicat\Service\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProjectController extends AbstractController
{

    #[Route('/admin/p/list', name: 'app_project_index', methods: ['GET'])]
    public function index(ProjectRepository $projectRepository): Response
    {
        return $this->render('admin/project/index.html.twig', [
            'projects' => $projectRepository->findAll(),
        ]);
    }

    #[Route('/admin/p/create', name: 'app_project_create', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        FileUploadService $fileUploadService,
        ProjectRepository $projectRepository,
    ): Response
    {
        $project = new Project();
        $form = $this->createForm(ProjectType::class, $project, [
            'id_editable' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $id = trim((string) $project->getId());
            if ($id === '') {
                $form->get('id')->addError(new FormError('Project id is required.'));
            } elseif ($projectRepository->find($id) instanceof Project) {
                $form->get('id')->addError(new FormError(sprintf('Project "%s" already exists.', $id)));
            }

            if (!$form->isValid()) {
                return $this->render('admin/project/create.html.twig', [
                    'project' => $project,
                    'form' => $form,
                ]);
            }

            try {
                $this->handleIconUpload($form, $project, $fileUploadService);
            } catch (\RuntimeException | \InvalidArgumentException $error) {
                $form->addError(new FormError($error->getMessage()));
            }

            if ($form->isValid()) {
                $entityManager->persist($project);
                $entityManager->flush();

                return $this->redirectToRoute('app_project_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('admin/project/create.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/admin/p/{id}/edit', name: 'app_project_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        string $id,
        ProjectRepository $projectRepository,
        EntityManagerInterface $entityManager,
        FileUploadService $fileUploadService,
    ): Response
    {
        $project = $projectRepository->find($id);
        if (!$project) {
            throw $this->createNotFoundException(sprintf('Project "%s" not found.', $id));
        }

        $form = $this->createForm(ProjectType::class, $project, [
            'id_editable' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->handleIconUpload($form, $project, $fileUploadService);
            } catch (\RuntimeException | \InvalidArgumentException $error) {
                $form->addError(new FormError($error->getMessage()));
            }

            if ($form->isValid()) {
                $entityManager->flush();

                return $this->redirectToRoute('app_project_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('admin/project/edit.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/admin/p/{id}', name: 'app_project_delete', methods: ['POST'])]
    public function delete(Request $request, string $id, ProjectRepository $projectRepository, EntityManagerInterface $entityManager): Response
    {
        $project = $projectRepository->find($id);
        if (!$project) {
            return $this->redirectToRoute('app_project_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('delete'.$project->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($project);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_project_index', [], Response::HTTP_SEE_OTHER);
    }

    private function handleIconUpload(FormInterface $form, Project $project, FileUploadService $fileUploadService): void
    {
        $file = $form->get('icon')->getData();
        if (!$file instanceof UploadedFile) {
            return;
        }

        $id = trim((string) $project->getId());
        if ($id === '') {
            throw new \RuntimeException('Project id is required before uploading an icon.');
        }

        $path = sprintf('icons/%s.png', $id);
        $project->setIcon($fileUploadService->uploadPublicImageAsPng($file, $path));
    }
}
