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

    #[Route('/admin/p/{id}', name: 'app_project_edit', methods: ['GET', 'POST'])]
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
