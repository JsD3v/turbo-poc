<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    public function __construct(private EmailVerifier $emailVerifier)
    {
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // encode the plain password
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            $entityManager->persist($user);
            $entityManager->flush();

            // generate a signed url and email it to the user
            $this->emailVerifier->sendEmailConfirmation('app_verify_email', $user,
                (new TemplatedEmail())
                    ->from(new Address('welcome@turbo-poc.com', 'Turbo-poc'))
                    ->to((string)$user->getEmail())
                    ->subject('Please Confirm your Email')
                    ->htmlTemplate('registration/confirmation_email.html.twig')
            );

            // do anything else you need here, like send an email

            return $this->redirectToRoute('app_home');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/verify/prepare', name: 'app_prepare_verify_email')]
    public function prepareVerifyEmail(Request $request): Response
    {
        // Récupérer l'URL signée depuis les paramètres de la requête
        $signedUrl = $request->query->get('signedUrl');

        if (!$signedUrl) {
            throw $this->createNotFoundException('URL de vérification manquante');
        }

        // Stocker l'URL complète dans la session
        $request->getSession()->set('verification_url', urldecode($signedUrl));

        $this->addFlash('info', 'Veuillez vous connecter pour confirmer votre adresse email.');

        return $this->redirectToRoute('app_login');
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator, EntityManagerInterface $entityManager): Response
    {
        // Supprimer cette ligne qui exige l'authentification
        // $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        try {
            // Le helper du bundle SymfonyCasts va extraire l'id de l'utilisateur
            // directement à partir de l'URL signée
            $id = $request->get('id');

            if (!$id) {
                throw $this->createNotFoundException('Identifiant utilisateur manquant');
            }

            $user = $entityManager->getRepository(User::class)->find($id);

            if (!$user) {
                throw $this->createNotFoundException('Utilisateur non trouvé');
            }

            $this->emailVerifier->handleEmailConfirmation($request, $user);

            $this->addFlash('success', 'Votre adresse e-mail a été vérifiée.');

            // S'ils ne sont pas connectés, redirigez-les vers la page de connexion
            if (!$this->getUser()) {
                return $this->redirectToRoute('app_login');
            }

            // Sinon, vers la page de chat
            return $this->redirectToRoute('app_chat');

        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));
            return $this->redirectToRoute('app_register');
        }
    }
}