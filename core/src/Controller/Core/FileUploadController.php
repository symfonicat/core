<?php

namespace Symfonicat\Controller\Core;

use Symfonicat\Form\FileUploadType;
use Symfonicat\Form\Model\FileUploadData;
use Symfonicat\Form\Model\FileUploadItemData;
use Symfonicat\Service\PublicFileUploadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FileUploadController extends AbstractController
{
    #[Route('/core/f', name: 'symfonicat_file_upload', methods: ['GET', 'POST'])]
    public function index(Request $request, PublicFileUploadService $fileUploadService): Response
    {
        $data = new FileUploadData();
        $form = $this->createForm(FileUploadType::class, $data);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedPaths = [];

            foreach ($data->files as $item) {
                if (!$item instanceof FileUploadItemData || !$item->file instanceof UploadedFile) {
                    $form->addError(new FormError('Select a file for each upload row.'));

                    continue;
                }

                try {
                    $uploadedPaths[] = $fileUploadService->upload(
                        $data->name,
                        $item->type,
                        $item->domain,
                        $item->subdomain,
                        $item->file,
                    );
                } catch (\InvalidArgumentException | \RuntimeException $error) {
                    $form->addError(new FormError($error->getMessage()));
                }
            }

            if ($form->isValid()) {
                $this->addFlash('success', sprintf('uploaded %d file%s: %s', count($uploadedPaths), count($uploadedPaths) === 1 ? '' : 's', implode(', ', $uploadedPaths)));

                return $this->redirectToRoute('symfonicat_file_upload', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('@symfonicat/file_upload/index.html.twig', [
            'form' => $form,
        ]);
    }
}
