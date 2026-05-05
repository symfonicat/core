<?php

namespace Symfonicat\Controller\Admin;

use Symfonicat\Entity\RoutingRule;
use Symfonicat\Form\RoutingRuleType;
use Symfonicat\Repository\RoutingRuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RoutingRuleController extends AbstractController
{
    #[Route('/admin/r/list', name: 'symfonicat_routing_rule_index', methods: ['GET'])]
    public function index(RoutingRuleRepository $routingRuleRepository): Response
    {
        return $this->render('@symfonicat/routing_rule/index.html.twig', [
            'rules' => $routingRuleRepository->findAll(),
        ]);
    }

    #[Route('/admin/r/create', name: 'symfonicat_routing_rule_create', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $rule = new RoutingRule();
        $form = $this->createForm(RoutingRuleType::class, $rule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($rule);
            $entityManager->flush();

            return $this->redirectToRoute('symfonicat_routing_rule_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('@symfonicat/routing_rule/create.html.twig', [
            'rule' => $rule,
            'form' => $form,
        ]);
    }

    #[Route('/admin/r/{id}/edit', name: 'symfonicat_routing_rule_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, string $id, RoutingRuleRepository $routingRuleRepository, EntityManagerInterface $entityManager): Response
    {
        $rule = $routingRuleRepository->find($id);
        if (!$rule) {
            throw $this->createNotFoundException(sprintf('Routing rule "%s" not found.', $id));
        }

        $form = $this->createForm(RoutingRuleType::class, $rule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('symfonicat_routing_rule_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('@symfonicat/routing_rule/edit.html.twig', [
            'rule' => $rule,
            'form' => $form,
        ]);
    }

    #[Route('/admin/r/{id}', name: 'symfonicat_routing_rule_delete', methods: ['POST'])]
    public function delete(Request $request, string $id, RoutingRuleRepository $routingRuleRepository, EntityManagerInterface $entityManager): Response
    {
        $rule = $routingRuleRepository->find($id);
        if (!$rule) {
            return $this->redirectToRoute('symfonicat_routing_rule_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isCsrfTokenValid('delete'.$rule->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($rule);
            $entityManager->flush();
        }

        return $this->redirectToRoute('symfonicat_routing_rule_index', [], Response::HTTP_SEE_OTHER);
    }
}
