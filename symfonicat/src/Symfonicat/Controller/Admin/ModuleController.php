<?php

namespace Symfonicat\Controller\Admin;

use Symfonicat\Entity\Module;
use Symfonicat\Form\ModuleType;
use Symfonicat\Repository\ModuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ModuleController extends AbstractController
{
    
    #[Route('/admin/m/list', name: 'app_module_index', methods: ['GET'])]
    public function index(ModuleRepository $moduleRepository): Response
    {
        return $this->render('module/index.html.twig', [
            'modules' => $moduleRepository->findAllOrderedBySlug(),
        ]);
    }

    #[Route('/admin/m/create', name: 'app_module_create', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $module = new Module();
        $form = $this->createForm(ModuleType::class, $module);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($module);
            $entityManager->flush();

            return $this->redirectToRoute('app_module_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('module/create.html.twig', [
            'module' => $module,
            'form' => $form,
        ]);
    }

    #[Route('/admin/m/{slug}', name: 'app_module_show', methods: ['GET'])]
    public function show(string $slug, ModuleRepository $moduleRepository): Response
    {
        $module = $moduleRepository->findOneBy(['slug' => $slug]);
        if (!$module) {
            throw $this->createNotFoundException(sprintf('module "%s" not found.', $slug));
        }

        return $this->render('module/show.html.twig', [
            'module' => $module,
        ]);
    }

    #[Route('/admin/m/{slug}/edit', name: 'app_module_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $slug, ModuleRepository $moduleRepository, EntityManagerInterface $entityManager): Response
    {
        $module = $moduleRepository->findOneBy(['slug' => $slug]);
        if (!$module) {
            throw $this->createNotFoundException(sprintf('Module "%s" not found.', $slug));
        }

        $form = $this->createForm(ModuleType::class, $module);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_module_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('module/edit.html.twig', [
            'module' => $module,
            'form' => $form,
        ]);
    }

    #[Route('/admin/m/{slug}', name: 'app_module_delete', methods: ['POST'])]
    public function delete(Request $request, string $slug, ModuleRepository $moduleRepository, EntityManagerInterface $entityManager): Response
    {
        $module = $moduleRepository->findOneBy(['slug' => $slug]);
        if (!$module) {
            return $this->redirectToRoute('app_module_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('delete'.$module->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($module);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_module_index', [], Response::HTTP_SEE_OTHER);
    }
}
