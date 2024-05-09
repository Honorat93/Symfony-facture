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
use Symfony\Component\HttpFoundation\RedirectResponse;


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

        #[Route('/', name: 'register_form')]
    public function showRegistrationForm(Request $request): Response
    {
        return $this->render('register.html.twig');
    }
    

    #[Route('/login', name: 'login_form', methods: ['GET'])]
    public function showLoginForm(): Response
    {
        return $this->render('login.html.twig');
    }

    #[Route('/home', name: 'homepage')]
    public function index(): Response
    {
        return $this->render('index.html.twig');
    }
    
    #[Route('/register', name: 'create_user', methods: 'POST')]
    public function createUser(Request $request): Response
    {
        try {
            $firstname = $request->request->get('firstname');
            $lastname = $request->request->get('lastname');
            $email = $request->request->get('email');
            $genre = $request->request->get('genre');
            $rgpd = $request->request->get('rgpd');
            $password = $request->request->get('password');

            $emailRegex = '/^\S+@\S+\.\S+$/';
            if (!preg_match($emailRegex, $email)) {
                return new JsonResponse([
                    'error' => true,
                    'message' => 'Le format de l\'email est invalide.',
                ], JsonResponse::HTTP_BAD_REQUEST);
            }

            $passwordRegex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{8,}$/';
            if (!preg_match($passwordRegex, $password)) {
                return new JsonResponse([
                    'error' => true,
                    'message' => "Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre, un caractère spécial et 8 caractères minimum.",
                ], JsonResponse::HTTP_BAD_REQUEST);
            }

            $existingUser = $this->userRepository->findOneBy(['email' => $email]);
            if ($existingUser) {
                return new JsonResponse([
                    'error' => true,
                    'message' => 'Cet email est déjà utilisé par un autre compte.',
                ], JsonResponse::HTTP_CONFLICT);
            }


            if (empty($email) || empty($password)) {
                return new JsonResponse([
                    'error' => true,
                    'message' => 'L\'email et le mot de passe sont obligatoires.',
                ], JsonResponse::HTTP_BAD_REQUEST);
            }

            $user = new User();
            $user->setFirstName($firstname)
                ->setLastName($lastname)
                ->setEmail($email)
                ->setGenre($genre)
                ->setRgpd($rgpd)
                ->setPassword($this->passwordEncoder->hashPassword($user, $password));

                          
            if ($genre === 'M') {
                $user->setGenre('H');
            } elseif ($genre === 'F') {
                $user->setGenre('F');
            } else {
                return new JsonResponse([
                    'error' => true,
                    'message' => 'Le genre doit être spécifié M pour homme ou F pour femme.',
                ], JsonResponse::HTTP_BAD_REQUEST);
            }

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return new RedirectResponse($this->generateUrl('login_user'));

        }catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Error: ' . $e->getMessage(),
            ], JsonResponse::HTTP_NOT_FOUND);
        }
    }

    #[Route('/login', name: 'login_user', methods: 'POST')]
    public function login(Request $request, JWTTokenManagerInterface $jwtManager): Response
    {
        try {
            $email = $request->request->get('email');
            $password = $request->request->get('password');
    
            if ($email === null || $password === null) {
                return new JsonResponse([
                    'error' => true,
                    'message' => 'Email/password manquants.',
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
    
            $emailRegex = '/^\S+@\S+\.\S+$/';
            if (!preg_match($emailRegex, $email)) {
                return new JsonResponse([
                    'error' => true,
                    'message' => "Le format de l'email est invalide.",
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
    
            $passwordRegex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{8,}$/';
            if (!preg_match($passwordRegex, $password)) {
                return new JsonResponse([
                    'error' => true,
                    'message' => "Le mot de passe doit contenir au moins une majuscule, une minuscule, un chiffre, un caractère spécial et avoir 8 caractères minimum.",
                ], JsonResponse::HTTP_FORBIDDEN);
            }
    
            $user = $this->userRepository->findOneBy(['email' => $email]);
    
            if (!$user || !$this->passwordEncoder->isPasswordValid($user, $password)) {
                return new JsonResponse([
                    'error' => true,
                    'message' => 'Email/password incorrect',
                ], JsonResponse::HTTP_BAD_REQUEST);
            }
    
            $token = $jwtManager->create($user);
            return new RedirectResponse($this->generateUrl('homepage'));
           

           //return new JsonResponse(['token' => $token]);
        }catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Error: ' . $e->getMessage(),
            ], JsonResponse::HTTP_NOT_FOUND);
        }
    }
    
    /*#[Route('/user', name: 'update', methods: 'POST')]
    public function update(Request $request): JsonResponse
    {
        try {

            $dataMiddellware = $this->tokenVerifier->checkToken($request);
            if (gettype($dataMiddellware) == 'boolean') {
                return $this->json($this->tokenVerifier->sendJsonErrorToken($dataMiddellware), JsonResponse::HTTP_UNAUTHORIZED);
            }
            $user = $dataMiddellware;

        $firstname = $request->request->get('firstname');
        $lastname = $request->request->get('lastname');
        $genre = $request->request->get('genre');

        $keys = array_keys($request->request->all());
        $allowedKeys = ['firstname', 'lastname', 'genre'];
        $diff = array_diff($keys, $allowedKeys);
        if (count($diff) > 0) {
            return new JsonResponse(
                [
                    'error' => true,
                    'message' => 'Erreur de validation des données.',
                ],
                JsonResponse::HTTP_UNPROCESSABLE_ENTITY
            );
        }

       
        if ($genre !== null && !in_array($genre, ['M', 'F'])) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Le genre doit être spécifié M pour homme ou F pour femme.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

       
        if (isset($firstname) && (strlen($firstname) < 1 || strlen($firstname) > 60)) {
            return new JsonResponse([
                'error' => true,
                'message' => 'La longueur du prénom doit être comprise entre 1 et 60 caractères.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

       
        if (isset($lastname) && (strlen($lastname) < 1 || strlen($lastname) > 60)) {
            return new JsonResponse([
                'error' => true,
                'message' => 'La longueur du nom de famille doit être comprise entre 1 et 60 caractères.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

    
        if ($firstname !== null) {
            $user->setFirstName($firstname);
        }

        if ($lastname !== null) {
            $user->setLastName($lastname);
        }

        if ($genre !== null) {
            $user->setGenre($genre);
        }

        
        $this->entityManager->flush();

        if (empty($firstname) && empty($lastname) && empty($genre)) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Les données fournies sont invalides ou incomplètes.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'error' => false,
            'message' => 'Votre inscription a bien été prise en compte',
        ]);
    } catch (\Exception $e) {
        return new JsonResponse([
            'error' => 'Error: ' . $e->getMessage(),
        ], JsonResponse::HTTP_NOT_FOUND);
    }
}*/

#[Route('/user/{id}', name: 'update_user', methods: ['PUT'])]
    public function updateUser(Request $request, int $id): JsonResponse
    {
        try {
            $dataMiddleware = $this->tokenVerifier->checkToken($request);
            if (gettype($dataMiddleware) === 'boolean') {
                return $this->json(
                    $this->tokenVerifier->sendJsonErrorToken($dataMiddleware),
                    JsonResponse::HTTP_UNAUTHORIZED
                );
            }
            $user = $dataMiddleware;

            if (!$id) {
                return $this->json([
                    'error' => true,
                    'message' => "L'ID de l'utilisateur est obligatoire pour cette requête."
                ], JsonResponse::HTTP_BAD_REQUEST);
            }

            $user = $this->userRepository->find($id);

            if (!$user) {
                return $this->json([
                    'error' => true,
                    'message' => "Aucun utilisateur trouvé correspndant à l'id fourni."
                ], JsonResponse::HTTP_NOT_FOUND);
            }

            $firstname = $request->request->get('firstname');
            $lastname = $request->request->get('lastname');
            $email = $request->request->get('email');
            $genre = $request->request->get('genre');
            $rgpd = $request->request->get('rgpd');


            $additionalParams = array_diff(array_keys($request->request->all()), ['firstname', 'lastname', 'email', 'genre', 'rgpd']);
            if (!empty($additionalParams)) {
                return $this->json([
                    'error' => true,
                    'message' => "Les paramètres fournis sont invalides. Veuillez vérifier les données soumises."
                ], JsonResponse::HTTP_BAD_REQUEST);
            }


            $existingUser = $this->userRepository->findOneBy(['email' => $email]);
            if ($existingUser) {
                return new JsonResponse([
                    'error' => true,
                    'message' => 'Cet email est déjà utilisé par un autre compte.',
                ], JsonResponse::HTTP_CONFLICT);
            }

            if ($firstname !== null) {
                $user->setFirstName($firstname);
            }

            if ($lastname !== null) {
                $user->setlastName($lastname);
            }

            if ($email !== null) {
                $user->setEmail($email);
            }

            if ($genre !== null) {
                $user->setGenre($genre);
            }

            if ($rgpd !== null) {
                $user->setRgpd($rgpd);
            }

            $this->entityManager->flush();

            return $this->json([
                'error' => false,
                'message' => "Utilisateur mis à jour avec succès."
            ], JsonResponse::HTTP_OK);
        } catch (\Exception $e) {
            return $this->json(['error' => true, 'message' => $e->getMessage()], JsonResponse::HTTP_BAD_REQUEST);
        }
    }


#[Route('/user/{id}', name: 'delete_user', methods: ['DELETE'])]
    public function deleteUser(Request $request, $id): JsonResponse
    {
        try {

            $dataMiddellware = $this->tokenVerifier->checkToken($request);
            if (gettype($dataMiddellware) == 'boolean') {
                return $this->json($this->tokenVerifier->sendJsonErrorToken($dataMiddellware), JsonResponse::HTTP_UNAUTHORIZED);
            }

            $user = $dataMiddellware;

            $user = $this->userRepository->find($id);

            
            if (!$user) {
                return new JsonResponse([
                    'error' => true,
                    'message' => 'Utilisateur non trouvé.',
                ], JsonResponse::HTTP_NOT_FOUND);
            }

            
            $this->entityManager->remove($user);
            $this->entityManager->flush();

           
            return new JsonResponse([
                'error' => false,
                'message' => 'L\'utilisateur a été supprimé avec succès.',
            ]);
        } catch (\Exception $e) {
            
            return new JsonResponse([
                'error' => true,
                'message' => 'Une erreur est survenue lors de la suppression de l\'utilisateur : ' . $e->getMessage(),
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/user/{id}', name: 'get_user', methods: ['GET'])]
public function getUserInfo(Request $request, $id): JsonResponse
{
    try {

        $dataMiddellware = $this->tokenVerifier->checkToken($request);
        if (gettype($dataMiddellware) == 'boolean') {
            return $this->json($this->tokenVerifier->sendJsonErrorToken($dataMiddellware), JsonResponse::HTTP_UNAUTHORIZED);
        }

        $user = $dataMiddellware;
        
        $user = $this->userRepository->find($id);

       
        if (!$user) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Utilisateur non trouvé.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $userData = [
            'firstname' => $user->getFirstName(),
            'lastname' => $user->getLastName(),
            'email' => $user->getEmail(),
            'genre' => $user->getGenre(),
        ];

        return $this->json([
            'error' => false,
            'user' => $userData,
        ]);
    } catch (\Exception $e) {
        return new JsonResponse([
            'error' => true,
            'message' => 'Une erreur est survenue lors de la récupération des informations de l\'utilisateur : ' . $e->getMessage(),
        ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
    }
}

#[Route('/users', name: 'get_all_users', methods: ['GET'])]
public function getAllUsers(Request $request): JsonResponse
{
    try {
        $dataMiddellware = $this->tokenVerifier->checkToken($request);
        if (gettype($dataMiddellware) == 'boolean') {
            return $this->json($this->tokenVerifier->sendJsonErrorToken($dataMiddellware), JsonResponse::HTTP_UNAUTHORIZED);
        }

        $user = $dataMiddellware;

        $users = $this->userRepository->findAll();

        $formattedUsers = [];
        foreach ($users as $user) {
            $formattedUsers[] = [
                'id' => $user->getId(),
                'firstname' => $user->getFirstName(),
                'lastname' => $user->getLastName(),
                'email' => $user->getEmail(),
                'genre' => $user->getGenre(),
                'rgpd' => $user->getRgpd(),
            ];
        }

        return $this->json([
            'error' => false,
            'users' => $formattedUsers,
        ]);
    } catch (\Exception $e) {
        return new JsonResponse([
            'error' => true,
            'message' => 'Une erreur est survenue lors de la récupération des utilisateurs : ' . $e->getMessage(),
        ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
    }
}

/*#[Route('/logout', name: 'logout')]
public function logout()
{
        // Retourne un JsonResponse avec les données appropriées
        return new JsonResponse([
            'message' => 'Traitement effectué avec succès',
        ]);
    }*/
}