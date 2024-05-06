<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\UserRepository;
use App\Entity\User;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Cache\Adapter\CacheItemPoolInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserInterface;



class UserController extends AbstractController
{
    private $userRepository;
    private $entityManager;
    private $serializer;
    private $passwordEncoder;
    private $jwtManager;
    private $tokenVerifier;
    
    public function __construct(
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        UserPasswordHasherInterface $passwordEncoder,
        JWTTokenManagerInterface $jwtManager,
        TokenManagementController $tokenVerifier
        
    ) {
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->passwordEncoder = $passwordEncoder;
        $this->jwtManager = $jwtManager;
        $this->tokenVerifier = $tokenVerifier;
    }


    #[Route('/quote', name: 'create_quote', methods: ['POST'])]
    public function createQuote(Request $request): JsonResponse
    {
        try {

            $dataMiddellware = $this->tokenVerifier->checkToken($request);
            if (gettype($dataMiddellware) == 'boolean') {
                return $this->json($this->tokenVerifier->sendJsonErrorToken($dataMiddellware), JsonResponse::HTTP_UNAUTHORIZED);
            }
            $user = $dataMiddellware;

            // Récupérer les données du formulaire encodé en URL
            $data = $request->request->all();

            // Récupérer les champs du formulaire
            $title = $data['title'];
            $description = $data['description'];
            $amount = $data['amount'];

            if (!is_numeric($amount)) {
                return $this->json([
                    'error' => true,
                    'message' => 'Le montant doit être un nombre.',
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
    
            // Créer un nouvel objet Quote
            $quote = new Quote();
            $quote->setTitle($title)
                ->setDescription($description)
                ->setAmount($amount)
                ->setCreatedAt(new \DateTime())
                ->setUser($this->getUser());

            $this->entityManager->persist($quote);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Devis créé avec succès.',
                'quote' => [
                    'id' => $quote->getId(),
                    'title' => $quote->getTitle(),
                    'description' => $quote->getDescription(),
                    'amount' => $quote->getAmount(),
                    'created_at' => $quote->getCreatedAt()->format('Y-m-d H:i:s'),
                ],
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return $this->json([
                'error' => true,
                'message' => 'Erreur lors de la création du devis : ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}