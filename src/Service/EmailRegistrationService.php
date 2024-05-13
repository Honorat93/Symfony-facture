<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface; // Ajoutez ceci pour importer UserPasswordHasherInterface

class EmailRegistrationService
{
    private $entityManager;
    private $mailer;
    private $jwtManager;
    private $passwordEncoder;
    private $validator;
    private $logger;

    public function __construct(EntityManagerInterface $entityManager, MailerInterface $mailer, UserPasswordHasherInterface $passwordEncoder, JWTTokenManagerInterface $jwtManager, ValidatorInterface $validator, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->mailer = $mailer;
        $this->passwordEncoder = $passwordEncoder;
        $this->jwtManager = $jwtManager;
        $this->validator = $validator;
        $this->logger = $logger;
    }

    public function registerByEmail(string $email, string $password): JsonResponse
    {
        // Valider les données d'entrée
        $errors = $this->validator->validate(['email' => $email, 'password' => $password]);
        if (count($errors) > 0) {
            $message = [];
            foreach ($errors as $error) {
                $message[] = $error->getMessage();
            }
            return new JsonResponse(['error' => true, 'message' => implode(', ', $message)], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier si l'email est déjà utilisé
        if ($this->entityManager->getRepository(User::class)->findOneBy(['email' => $email])) {
            return new JsonResponse(['error' => true, 'message' => 'Cet email est déjà utilisé par un autre compte.'], Response::HTTP_CONFLICT);
        }

        // Créer l'utilisateur et le persister
        $user = new User();
        $user->setEmail($email);

        $hashedPassword = $this->passwordEncoder->hashPassword($user, $password);
        $user->setPassword($hashedPassword);

        try {
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Envoyer l'email de confirmation
            $this->sendConfirmationEmail($user);

            return new JsonResponse(['message' => 'Inscription réussie'], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'inscription : ' . $e->getMessage());
            return new JsonResponse(['error' => true, 'message' => 'Échec de l\'inscription'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function sendConfirmationEmail(UserInterface $user): void
    {
        $email = (new Email())
            ->from('your@example.com')
            ->to($user->getEmail())
            ->subject('Confirmation de votre inscription')
            ->html('<p>Merci de vous être inscrit.</p>');

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Erreur lors de l\'envoi de l\'email de confirmation : ' . $e->getMessage());
        }
    }
}