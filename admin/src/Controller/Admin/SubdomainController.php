<?php

namespace Symfonicat\Controller\Admin;

use Symfonicat\Entity\Subdomain;
use Symfonicat\Form\SubdomainType;
use Symfonicat\Repository\EnvParentRepository;
use Symfonicat\Repository\SubdomainRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SubdomainController extends AbstractController
{

    #[Route('/admin/s', name: 'symfonicat_subdomain_index', methods: ['GET'])]
    public function index(SubdomainRepository $subdomainRepository, EnvParentRepository $envParentRepository): Response
    {
        $duplicateGroups = $subdomainRepository->findDuplicateCleanIdGroups();
        if ($duplicateGroups !== []) {
            $messages = array_map(
                static fn (array $group): string => sprintf('%s: %s', $group['cleanId'], implode(', ', $group['ids'])),
                $duplicateGroups,
            );

            $this->addFlash(
                'error',
                sprintf('duplicate subdomain ids detected: %s', implode('; ', $messages)),
            );
        }

        return $this->render('@symfonicat/subdomain/index.html.twig', [
            'subdomains' => $subdomainRepository->findAll(),
            'env_parents' => $envParentRepository->findAllOrdered(),
        ]);
    }


    #[Route('/admin/s/create', name: 'symfonicat_subdomain_create', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $subdomain = new Subdomain();
        $form = $this->createForm(SubdomainType::class, $subdomain, [
            'is_admin' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($subdomain);
            $entityManager->flush();

            return $this->redirectToRoute('symfonicat_subdomain_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('@symfonicat/subdomain/create.html.twig', [
            'subdomain' => $subdomain,
            'form' => $form,
        ]);
    }

    #[Route('/admin/s/{id}', name: 'symfonicat_subdomain_edit', methods: ['GET', 'POST'], requirements: ['id' => '.+'])]
    public function edit(
        Request $request,
        string $id,
        SubdomainRepository $subdomainRepository,
        EntityManagerInterface $entityManager,
    ): Response
    {
        $subdomain = $subdomainRepository->find($id);
        if (!$subdomain) {
            throw $this->createNotFoundException(sprintf('Subdomain "%s" not found.', $id));
        }

        $form = $this->createForm(SubdomainType::class, $subdomain, [
            'id_editable' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('symfonicat_subdomain_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('@symfonicat/subdomain/edit.html.twig', [
            'subdomain' => $subdomain,
            'form' => $form,
        ]);
    }

    #[Route('/admin/s/{id}/delete', name: 'symfonicat_subdomain_delete', methods: ['POST'], requirements: ['id' => '.+'], priority: 10)]
    public function delete(Request $request, string $id, SubdomainRepository $subdomainRepository, EntityManagerInterface $entityManager): Response
    {
        $subdomain = $subdomainRepository->find($id);
        if (!$subdomain) {
            return $this->redirectToRoute('symfonicat_subdomain_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('delete'.$subdomain->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($subdomain);
            $entityManager->flush();
        }

        return $this->redirectToRoute('symfonicat_subdomain_index', [], Response::HTTP_SEE_OTHER);
    }
}
