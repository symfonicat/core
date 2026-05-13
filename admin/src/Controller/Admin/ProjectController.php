<?php

namespace Symfonicat\Controller\Admin;

use Symfonicat\Entity\Project;
use Symfonicat\Form\ProjectType;
use Symfonicat\Repository\EnvParentRepository;
use Symfonicat\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProjectController extends AbstractController
{

    #[Route('/admin/p/list', name: 'symfonicat_project_index', methods: ['GET'])]
    public function index(ProjectRepository $projectRepository, EnvParentRepository $envParentRepository): Response
    {
        $duplicateGroups = $projectRepository->findDuplicateCleanIdGroups();
        if ($duplicateGroups !== []) {
            $messages = array_map(
                static fn (array $group): string => sprintf('%s: %s', $group['cleanId'], implode(', ', $group['ids'])),
                $duplicateGroups,
            );

            $this->addFlash(
                'error',
                sprintf('duplicate project ids detected: %s', implode('; ', $messages)),
            );
        }

        return $this->render('@symfonicat/project/index.html.twig', [
            'projects' => $projectRepository->findAll(),
            'env_parents' => $envParentRepository->findAllOrdered(),
        ]);
    }

    #[Route('/admin/p/{id}', name: 'symfonicat_project_edit', methods: ['GET', 'POST'], requirements: ['id' => '.+'])]
    public function edit(
        Request $request,
        string $id,
        ProjectRepository $projectRepository,
        EntityManagerInterface $entityManager,
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
            $entityManager->flush();

            return $this->redirectToRoute('symfonicat_project_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('@symfonicat/project/edit.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }
}
