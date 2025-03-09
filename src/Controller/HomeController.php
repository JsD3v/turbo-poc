<?php

namespace App\Controller;

use App\Entity\Message;
use App\Form\ChatType;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\UX\Turbo\TurboBundle;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(Request $request, EntityManagerInterface $entityManager, MessageRepository $messageRepository): Response
    {
        $message = new Message();
        $form = $this->createForm(ChatType::class, $message);
        $emptyForm = clone $form;
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($message);
            $entityManager->flush();
            $form = $emptyForm;
        }


        return $this->render('home/index.html.twig', [
            'form' => $form,
            'messages' => $messageRepository->findBy([], ['createdAt' => 'DESC']),
        ]);
    }
}
