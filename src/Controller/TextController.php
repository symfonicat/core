<?php

namespace App\Controller;

use App\Service\TextToolsInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TextController extends AbstractController
{
    #[Route('/text', name: 'symfonicat_text', methods: ['GET'])]
    public function main(TextToolsInterface $textTools): Response
    {
        $str = $textTools->removeString('pizzawonk', 'wonk');
        return new Response($str);
    }
}
