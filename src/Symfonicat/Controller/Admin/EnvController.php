<?php

namespace Symfonicat\Controller\Admin;

use Symfonicat\Entity\Env;
use Symfonicat\Form\EnvType;
use Symfonicat\Repository\EnvRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EnvController extends AbstractController
{
    #[Route('/admin/e/list', name: 'app_env_index', methods: ['GET'])]
    public function index(EnvRepository $envRepository): Response
    {
        return $this->render('admin/env/index.html.twig', [
            'envs' => $envRepository->findAllOrdered(),
        ]);
    }

    #[Route('/admin/e/create', name: 'app_env_create', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $env = new Env();
        $form = $this->createForm(EnvType::class, $env);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($env);
            $entityManager->flush();

            return $this->redirectToRoute('app_env_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/env/create.html.twig', [
            'env' => $env,
            'form' => $form,
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
}
