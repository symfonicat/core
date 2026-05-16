<?php

namespace Symfonicat\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Symfonicat\Entity\Parcel;
use Symfonicat\Form\ParcelType;
use Symfonicat\Repository\ParcelRepository;
use Symfonicat\Repository\EnvParentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ParcelController extends AbstractController
{
    #[Route('/admin/b', name: 'symfonicat_parcel_index', methods: ['GET'])]
    public function index(ParcelRepository $parcelRepository, EnvParentRepository $envParentRepository): Response
    {
        return $this->render('@symfonicat/parcel/index.html.twig', [
            'parcels' => $parcelRepository->findAllOrderedById(),
            'env_parents' => $envParentRepository->findAllOrdered(),
        ]);
    }

    #[Route('/admin/b/{id}', name: 'symfonicat_parcel_edit', methods: ['GET', 'POST'], requirements: ['id' => '.+'])]
    public function edit(Request $request, string $id, ParcelRepository $parcelRepository, EntityManagerInterface $entityManager): Response
    {
        $parcel = $parcelRepository->find($id);
        if (!$parcel instanceof Parcel) {
            throw $this->createNotFoundException(sprintf('Parcel "%s" not found.', $id));
        }

        $form = $this->createForm(ParcelType::class, $parcel, [
            'id_editable' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('symfonicat_parcel_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('@symfonicat/parcel/edit.html.twig', [
            'parcel' => $parcel,
            'form' => $form,
        ]);
    }
}
