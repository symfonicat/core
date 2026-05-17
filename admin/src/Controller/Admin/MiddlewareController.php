<?php

namespace Symfonicat\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Symfonicat\Entity\Middleware;
use Symfonicat\Form\MiddlewareType;
use Symfonicat\Repository\MiddlewareRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MiddlewareController extends AbstractController
{
    #[Route('/admin/m', name: 'symfonicat_middleware_index', methods: ['GET'])]
    public function index(MiddlewareRepository $middlewareRepository): Response
    {
        return $this->render('@symfonicat/middleware/index.html.twig', [
            'middlewares' => $middlewareRepository->findAllOrderedById(),
        ]);
    }

    #[Route('/admin/m/{id}', name: 'symfonicat_middleware_edit', methods: ['GET', 'POST'], requirements: ['id' => '.+'])]
    public function edit(Request $request, Middleware $middleware, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(MiddlewareType::class, $middleware);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('symfonicat_middleware_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('@symfonicat/middleware/edit.html.twig', [
            'middleware' => $middleware,
            'form' => $form,
        ]);
    }
}
