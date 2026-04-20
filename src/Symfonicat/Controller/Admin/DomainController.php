<?php

namespace Symfonicat\Controller\Admin;

use Symfonicat\Entity\Domain;
use Symfonicat\Form\DomainType;
use Symfonicat\Repository\DomainRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
final class DomainController extends AbstractController
{
    #[Route('/admin/d/list', name: 'app_domain_index', methods: ['GET'])]
    public function index(DomainRepository $domainRepository): Response
    {
        return $this->render('admin/domain/index.html.twig', [
            'domains' => $domainRepository->findAll(),
        ]);
    }

    #[Route('/admin/d/create', name: 'app_domain_create', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $domain = new Domain();
        $form = $this->createForm(DomainType::class, $domain, [
            'is_admin' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($domain);
            $entityManager->flush();

            return $this->redirectToRoute('app_domain_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/domain/create.html.twig', [
            'domain' => $domain,
            'form' => $form,
        ]);
    }

    #[Route('/admin/d/{id}/edit', name: 'app_domain_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $id, DomainRepository $domainRepository, EntityManagerInterface $entityManager): Response
    {
        $domain = $domainRepository->find($id);
        if (!$domain) {
            throw $this->createNotFoundException(sprintf('Domain "%s" not found.', $id));
        }
        $form = $this->createForm(DomainType::class, $domain, [
            'is_admin' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_domain_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('admin/domain/edit.html.twig', [
            'domain' => $domain,
            'form' => $form,
        ]);
    }

    #[Route('/admin/d/{id}', name: 'app_domain_delete', methods: ['POST'])]
    public function delete(Request $request, string $id, DomainRepository $domainRepository, EntityManagerInterface $entityManager): Response
    {
        $domain = $domainRepository->find($id);
        if (!$domain) {
            return $this->redirectToRoute('app_domain_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('delete'.$domain->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($domain);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_domain_index', [], Response::HTTP_SEE_OTHER);
    }
}
