<?php

namespace Symfonicat\Controller\Admin;

use Symfonicat\Entity\Env;
use Symfonicat\Entity\EnvParent;
use Symfonicat\Form\EnvType;
use Symfonicat\Form\EnvParentType;
use Symfonicat\Repository\EnvRepository;
use Symfonicat\Repository\EnvParentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EnvController extends AbstractController
{
    #[Route('/admin/e/list', name: 'app_env_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EnvRepository $envRepository,
        EnvParentRepository $envParentRepository,
        EntityManagerInterface $entityManager,
        FormFactoryInterface $formFactory,
    ): Response
    {
        $envParent = new EnvParent();
        $envParentForm = $formFactory->createNamed('env_parent_create', EnvParentType::class, $envParent);
        $envParentForm->handleRequest($request);

        if ($envParentForm->isSubmitted() && $envParentForm->isValid()) {
            $entityManager->persist($envParent);
            $entityManager->flush();

            return $this->redirectToRoute('app_env_index', [], Response::HTTP_SEE_OTHER);
        }

        $env = new Env();
        $envForm = $formFactory->createNamed('env_create', EnvType::class, $env);
        $envForm->handleRequest($request);

        if ($envForm->isSubmitted() && $envForm->isValid()) {
            $entityManager->persist($env);
            $entityManager->flush();

            return $this->redirectToRoute('app_env_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/env/index.html.twig', [
            'env_parents' => $envParentRepository->findAllOrdered(),
            'envs' => $envRepository->findAllOrdered(),
            'env_parent_form' => $envParentForm,
            'env_form' => $envForm,
        ]);
    }

    #[Route('/admin/e/{id}/edit', name: 'app_env_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $id, EnvRepository $envRepository, EntityManagerInterface $entityManager): Response
    {
        $env = $envRepository->find($id);
        if (!$env instanceof Env) {
            throw $this->createNotFoundException(sprintf('Env "%s" not found.', $id));
        }

        $form = $this->createForm(EnvType::class, $env, [
            'is_edit' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_env_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/env/edit.html.twig', [
            'env' => $env,
            'form' => $form,
        ]);
    }

    #[Route('/admin/e/{id}', name: 'app_env_delete', methods: ['POST'])]
    public function delete(Request $request, string $id, EnvRepository $envRepository, EntityManagerInterface $entityManager): Response
    {
        $env = $envRepository->find($id);
        if (!$env instanceof Env) {
            return $this->redirectToRoute('app_env_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('delete' . $env->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($env);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_env_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/admin/e/parent/{id}/edit', name: 'app_env_parent_edit', methods: ['GET', 'POST'])]
    public function editParent(
        Request $request,
        string $id,
        EnvParentRepository $envParentRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $envParent = $envParentRepository->find($id);
        if (!$envParent instanceof EnvParent) {
            throw $this->createNotFoundException(sprintf('Env parent "%s" not found.', $id));
        }

        $form = $this->createForm(EnvParentType::class, $envParent, [
            'is_edit' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_env_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/env_parent/edit.html.twig', [
            'env_parent' => $envParent,
            'form' => $form,
        ]);
    }

    #[Route('/admin/e/parent/{id}', name: 'app_env_parent_delete', methods: ['POST'])]
    public function deleteParent(
        Request $request,
        string $id,
        EnvParentRepository $envParentRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $envParent = $envParentRepository->find($id);
        if (!$envParent instanceof EnvParent) {
            return $this->redirectToRoute('app_env_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('delete' . $envParent->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($envParent);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_env_index', [], Response::HTTP_SEE_OTHER);
    }
}
