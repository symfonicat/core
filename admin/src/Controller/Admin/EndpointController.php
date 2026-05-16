<?php

namespace Symfonicat\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Symfonicat\Entity\Endpoint;
use Symfonicat\Form\EndpointType;
use Symfonicat\Repository\EnvParentRepository;
use Symfonicat\Repository\EndpointRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class EndpointController extends AbstractController
{
    #[Route('/admin/e', name: 'symfonicat_endpoint_index', methods: ['GET'])]
    public function index(EndpointRepository $endpointRepository, EnvParentRepository $envParentRepository): Response
    {
        return $this->render('@symfonicat/endpoint/index.html.twig', [
            'endpoints' => $endpointRepository->findAllOrderedById(),
            'env_parents' => $envParentRepository->findAllOrdered(),
        ]);
    }

    #[Route('/admin/e/create', name: 'symfonicat_endpoint_create', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, EnvParentRepository $envParentRepository): Response
    {
        $endpoint = new Endpoint();
        $form = $this->createForm(EndpointType::class, $endpoint);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($endpoint);
            $entityManager->flush();

            return $this->redirectToRoute('symfonicat_endpoint_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('@symfonicat/endpoint/create.html.twig', [
            'endpoint' => $endpoint,
            'form' => $form,
            'env_parents' => $envParentRepository->findAllOrdered(),
        ]);
    }

    #[Route('/admin/e/{id}', name: 'symfonicat_endpoint_edit', methods: ['GET', 'POST'], requirements: ['id' => '.+'])]
    public function edit(Request $request, string $id, EndpointRepository $endpointRepository, EntityManagerInterface $entityManager, EnvParentRepository $envParentRepository): Response
    {
        $endpoint = $endpointRepository->find($id);
        if (!$endpoint instanceof Endpoint) {
            throw $this->createNotFoundException(sprintf('Endpoint "%s" not found.', $id));
        }

        $form = $this->createForm(EndpointType::class, $endpoint, [
            'id_editable' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('symfonicat_endpoint_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('@symfonicat/endpoint/edit.html.twig', [
            'endpoint' => $endpoint,
            'form' => $form,
            'env_parents' => $envParentRepository->findAllOrdered(),
        ]);
    }

    #[Route('/admin/e/{id}/delete', name: 'symfonicat_endpoint_delete', methods: ['POST'], requirements: ['id' => '.+'], priority: 10)]
    public function delete(Request $request, string $id, EndpointRepository $endpointRepository, EntityManagerInterface $entityManager): Response
    {
        $endpoint = $endpointRepository->find($id);
        if (!$endpoint instanceof Endpoint) {
            return $this->redirectToRoute('symfonicat_endpoint_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('delete'.$endpoint->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($endpoint);
            $entityManager->flush();
        }

        return $this->redirectToRoute('symfonicat_endpoint_index', [], Response::HTTP_SEE_OTHER);
    }
}
