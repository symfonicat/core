<?php

namespace Symfonicat\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Symfonicat\Entity\Bundle;
use Symfonicat\Form\BundleType;
use Symfonicat\Repository\BundleRepository;
use Symfonicat\Repository\EnvParentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BundleController extends AbstractController
{
    #[Route('/admin/b/list', name: 'symfonicat_bundle_index', methods: ['GET'])]
    public function index(BundleRepository $bundleRepository, EnvParentRepository $envParentRepository): Response
    {
        return $this->render('@symfonicat/bundle/index.html.twig', [
            'bundles' => $bundleRepository->findAllOrderedById(),
            'env_parents' => $envParentRepository->findAllOrdered(),
        ]);
    }

    #[Route('/admin/b/{id}', name: 'symfonicat_bundle_edit', methods: ['GET', 'POST'], requirements: ['id' => '.+'])]
    public function edit(Request $request, string $id, BundleRepository $bundleRepository, EntityManagerInterface $entityManager): Response
    {
        $bundle = $bundleRepository->find($id);
        if (!$bundle instanceof Bundle) {
            throw $this->createNotFoundException(sprintf('Bundle "%s" not found.', $id));
        }

        $form = $this->createForm(BundleType::class, $bundle, [
            'id_editable' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('symfonicat_bundle_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('@symfonicat/bundle/edit.html.twig', [
            'bundle' => $bundle,
            'form' => $form,
        ]);
    }
}
