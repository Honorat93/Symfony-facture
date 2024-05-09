<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\User;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWSProvider\JWSProviderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class TokenManagementController extends AbstractController
{
    private $jwtManager;
    private $jwtProvider;
    private $userRepository;
    public function __construct(JWTTokenManagerInterface $jwtManager, JWSProviderInterface $jwtProvider, UserRepository $userRepository)
    {
        $this->jwtManager = $jwtManager;
        $this->jwtProvider = $jwtProvider;
        $this->userRepository = $userRepository;
    }



    public function checkToken(Request $request)
{
    if ($request->headers->has('Authorization')) {
        $data = explode(" ", $request->headers->get('Authorization'));
        if (count($data) == 2) {
            $token = $data[1];
            try {
                $dataToken = $this->jwtProvider->load($token);
                if ($dataToken && $dataToken->isVerified()) {
                    $user = $this->userRepository->findOneBy(['email' => $dataToken->getPayload()['username']]);
                    return $user;
                }
            } catch (\Throwable $th) {
                // Log the error for debugging purposes
                error_log('Error verifying JWT token: ' . $th->getMessage());
                return false;
            }
        }
    }
    // No Authorization header or invalid token format
    return null;
}

    public function sendJsonErrorToken($nullToken): array
    {
        return [
            'error' => true,
            'message' => ($nullToken) ? "Authentification requise. Vous devez être connecté pour effectuer cette action." : "Le token fourni est incorrect",
        ];
    }
}