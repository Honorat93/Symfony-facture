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
use App\Entity\Quote;
use App\Repository\QuoteRepository;
use TCPDF; 
use Symfony\Component\HttpFoundation\RedirectResponse;




class QuoteController extends AbstractController
{
    private $userRepository;
    private $entityManager;
    private $serializer;
    private $passwordEncoder;
    private $jwtManager;
    private $tokenVerifier;
    private $quoteRepository;
    
    public function __construct(
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        SerializerInterface $serializer,
        UserPasswordHasherInterface $passwordEncoder,
        JWTTokenManagerInterface $jwtManager,
        TokenManagementController $tokenVerifier,
        QuoteRepository $quoteRepository
        
    ) {
        $this->userRepository = $userRepository;
        $this->entityManager = $entityManager;
        $this->serializer = $serializer;
        $this->passwordEncoder = $passwordEncoder;
        $this->jwtManager = $jwtManager;
        $this->tokenVerifier = $tokenVerifier;
        $this->quoteRepository = $quoteRepository;
    }

    #[Route('/update_quote/{id}', name: 'quote_update', methods: ['GET'])]
    public function update(Request $request, int $id): Response
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
            // Récupérer le devis à modifier
            $quote = $this->quoteRepository->find($id);
    
            if (!$quote) {
                return $this->json([
                    'error' => true,
                    'message' => 'Devis non trouvé.',
                ], JsonResponse::HTTP_NOT_FOUND);
            }
    
            // Afficher le formulaire de modification du devis
            return $this->render('gestion_devis/update_quote.html.twig', [
                'quote' => $quote,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => true,
                'message' => 'Une erreur est survenue lors de la récupération du devis à modifier : ' . $e->getMessage(),
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    

    #[Route('/create_quote', name: 'quote_create')]
    public function creation(Request $request): Response
    {
        $dataMiddleware = $this->tokenVerifier->checkToken($request);
        if (gettype($dataMiddleware) === 'boolean') {
            return $this->json(
                $this->tokenVerifier->sendJsonErrorToken($dataMiddleware),
                JsonResponse::HTTP_UNAUTHORIZED
            );
        }
        $user = $dataMiddleware;
        return $this->render('gestion_devis/create_quote.html.twig');
    }

    #[Route('/devis', name: 'quote_home')]
    public function index(Request $request): Response
    {
        $dataMiddleware = $this->tokenVerifier->checkToken($request);
        if (gettype($dataMiddleware) === 'boolean') {
            return $this->json(
                $this->tokenVerifier->sendJsonErrorToken($dataMiddleware),
                JsonResponse::HTTP_UNAUTHORIZED
            );
        }
        $user = $dataMiddleware;

        $quotes = $this->quoteRepository->findAll(); // Exemple, ajustez selon votre logique

        // Rendre la vue avec les devis récupérés
        return $this->render('gestion_devis/devis.html.twig', [
            'quotes' => $quotes,
        ]);
    }
        



    #[Route('/quote', name: 'create_quote', methods: ['POST'])]
public function createQuote(Request $request): Response
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

        // Récupérer les données du formulaire encodé en URL
        $data = $request->request->all();

        // Vérifier si l'email de l'utilisateur est présent dans les données
        if (empty($data['user_email'])) {
            return $this->json([
                'error' => true,
                'message' => "L'e-mail de l'utilisateur est requis pour attribuer le devis.",
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Récupérer l'e-mail de l'utilisateur
        $userEmail = $data['user_email'];

        // Rechercher l'utilisateur dans le repository par son e-mail
        $user = $this->userRepository->findOneByEmail($userEmail);

        // Vérifier si l'utilisateur existe
        if (!$user) {
            return $this->json([
                'error' => true,
                'message' => "Aucun utilisateur trouvé avec cet e-mail.",
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        // Vérifier les autres champs du formulaire
        if (empty($data['title']) || empty($data['description']) || empty($data['amount'])) {
            return $this->json([
                'error' => true,
                'message' => 'Le titre, la description et le montant sont requis.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }
        
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
            ->setUser($user);

        // Maintenant que vous avez l'utilisateur, vous pouvez récupérer son nom et son prénom
        $firstName = $user->getFirstName();
        $lastName = $user->getLastName();

        // Vous pouvez maintenant utiliser $firstName et $lastName comme vous le souhaitez, par exemple :
        $authorName = $firstName . ' ' . $lastName;

        // Enregistrer le devis
        $this->entityManager->persist($quote);
        $this->entityManager->flush();
        
        return new RedirectResponse($this->generateUrl('quote_home'));
        
    } catch (\Exception $e) {
        // En cas d'erreur, renvoyer une réponse JSON avec un code d'erreur interne du serveur
        return $this->json([
            'error' => true,
            'message' => 'Erreur lors de la création du devis : ' . $e->getMessage(),
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
    #[Route('/quote/{id}', name: 'read_quote', methods: ['GET'])]
    public function readQuote(Request $request, int $id): Response
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
            
            $quote = $this->quoteRepository->find($id);
    
            if (!$quote) {
                return $this->json([
                    'error' => true,
                    'message' => 'Devis non trouvé.',
                ], JsonResponse::HTTP_NOT_FOUND);
            }

            return $this->render('gestion_devis/get_quote.html.twig', [
                'quote' => $quote,
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'error' => true,
                'message' => 'Une erreur est survenue lors de la lecture du devis : ' . $e->getMessage(),
            ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/quote/{id}', name: 'update_quote', methods: ['POST'])]
public function updateQuote(Request $request, int $id): Response
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

        $quote = $this->quoteRepository->find($id);

        if (!$quote) {
            return $this->json([
                'error' => true,
                'message' => 'Devis non trouvé.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        // Récupérer les données du formulaire encodé en URL
        $data = $request->request->all();

        if (empty($data['title']) || empty($data['description']) || empty($data['amount'])) {
            return $this->json([
                'error' => true,
                'message' => 'Le titre, la description et le montant sont requis.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        // Mise à jour des données du devis
        $quote->setTitle($data['title'])
              ->setDescription($data['description'])
              ->setAmount($data['amount']);

        $this->entityManager->flush();

        return $this->redirectToRoute('quote_home');

    } catch (\Exception $e) {
        return $this->json([
            'error' => true,
            'message' => 'Une erreur est survenue lors de la mise à jour du devis : ' . $e->getMessage(),
        ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
    }
}

#[Route('/quote/{id}', name: 'delete_quote', methods: ['DELETE'])]
public function deleteQuote(Request $request, int $id): JsonResponse
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

        $quote = $this->quoteRepository->find($id);

        if (!$quote) {
            return $this->json([
                'error' => true,
                'message' => 'Devis non trouvé.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        // Suppression du devis
        $this->entityManager->remove($quote);
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Devis supprimé avec succès.',
        ]);
    } catch (\Exception $e) {
        return $this->json([
            'error' => true,
            'message' => 'Une erreur est survenue lors de la suppression du devis : ' . $e->getMessage(),
        ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
    }
}

#[Route('/quotes', name: 'get_all_quotes', methods: ['GET'])]
public function getAllQuotes(Request $request): Response
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

        $quotes = $this->quoteRepository->findAll();

        // Transformation des objets devis en tableau associatif
        $formattedQuotes = [];
        foreach ($quotes as $quote) {
            $formattedQuotes[] = [
                'id' => $quote->getId(),
                'title' => $quote->getTitle(),
                'description' => $quote->getDescription(),
                'amount' => $quote->getAmount(),
                'created_at' => $quote->getCreatedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return $this->render('gestion_devis/get_all_quotes.html.twig', [
            'quotes' => $formattedQuotes,
        ]);
        
    } catch (\Exception $e) {
        return $this->json([
            'error' => true,
            'message' => 'Une erreur est survenue lors de la récupération des devis : ' . $e->getMessage(),
        ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
    }
}

#[Route('/quote/{id}/download', name: 'download_quote_pdf', methods: ['GET'])]
public function downloadQuotePdf(Request $request, int $id): Response
{
    try {
        $dataMiddellware = $this->tokenVerifier->checkToken($request);
        if (gettype($dataMiddellware) == 'boolean') {
            return $this->json($this->tokenVerifier->sendJsonErrorToken($dataMiddellware), JsonResponse::HTTP_UNAUTHORIZED);
        }
        $user = $dataMiddellware;

        $quote = $this->quoteRepository->find($id);

        if (!$quote) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Devis non trouvé.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        // Vérifier si l'utilisateur est associé au devis
        $user = $quote->getUser();
        if (!$user) {
            return new JsonResponse([
                'error' => true,
                'message' => 'Utilisateur associé au devis non trouvé.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $firstName = $user->getFirstName();
        $lastName = $user->getLastName();

        $authorName = $firstName . ' ' . $lastName;
        // Créer un nouvel objet TCPDF
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Configuration du document PDF
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor($authorName); // Nom complet de l'utilisateur comme auteur
        $pdf->SetTitle('Devis: ' . $quote->getTitle());
        $pdf->SetSubject('Devis');
        $pdf->SetKeywords('Devis, PDF');

        // Ajouter une page
        $pdf->AddPage();

        // Contenu du devis (vous pouvez personnaliser cela selon vos besoins)
        $html = '<h1>' . $quote->getTitle() . '</h1>';
        $html .= '<p><strong>Description:</strong> ' . $quote->getDescription() . '</p>';
        $html .= '<p><strong>Montant:</strong> ' . $quote->getAmount() . '</p>';

        // Informations sur l'utilisateur
        $html .= '<h2>Informations sur l\'utilisateur:</h2>';
        $html .= '<p><strong>Nom complet:</strong> ' . $user->getFirstName() . ' ' . $user->getLastName() . '</p>';
        $html .= '<p><strong>Email:</strong> ' . $user->getEmail() . '</p>';

        // Écrire le contenu dans le PDF
        $pdf->writeHTML($html, true, false, true, false, '');

        // Nom du fichier PDF à télécharger
        $fileName = 'quote_' . $quote->getId() . '.pdf';

        // Renvoyer le PDF en réponse
        return new Response($pdf->Output($fileName, 'D'), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    } catch (\Exception $e) {
        return new JsonResponse([
            'error' => true,
            'message' => 'Une erreur est survenue lors du téléchargement du devis en PDF : ' . $e->getMessage(),
        ], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
    }
}
 
} 