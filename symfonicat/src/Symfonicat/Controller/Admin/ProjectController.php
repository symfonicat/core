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
        return $this->render('project/index.html.twig', [
            'projects' => $projectRepository->findAll(),
        ]);
    }

    #[Route('/admin/p/create', name: 'app_project_create', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, FileUploadService $fileUploadService): Response
    {
        $project = new Project();
        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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

        return $this->render('project/create.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/admin/p/{slug}/edit', name: 'app_project_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        string $slug,
        ProjectRepository $projectRepository,
        EntityManagerInterface $entityManager,
        FileUploadService $fileUploadService,
    ): Response
    {
        $project = $projectRepository->findOneBy(['slug' => $slug]);
        if (!$project) {
            throw $this->createNotFoundException(sprintf('Project "%s" not found.', $slug));
        }

        $form = $this->createForm(ProjectType::class, $project);
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

        return $this->render('project/edit.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/admin/p/{slug}', name: 'app_project_delete', methods: ['POST'])]
    public function delete(Request $request, string $slug, ProjectRepository $projectRepository, EntityManagerInterface $entityManager): Response
    {
        $project = $projectRepository->findOneBy(['slug' => $slug]);
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

        $slug = trim((string) $project->getSlug());
        if ($slug === '') {
            throw new \RuntimeException('Project slug is required before uploading an icon.');
        }

        $path = sprintf('icons/%s.png', $slug);
        $project->setIcon($fileUploadService->uploadPublicImageAsPng($file, $path));
    }
}
