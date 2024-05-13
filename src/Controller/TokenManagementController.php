<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\UserRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWSProvider\JWSProviderInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

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
        $jwtToken = $request->cookies->get('jwt_token');

        if ($jwtToken) {
            try {
                $dataToken = $this->jwtProvider->load($jwtToken);
                if ($dataToken->isVerified()) {
                    $user = $this->userRepository->findOneBy(["email" => $dataToken->getPayload()["username"]]);
                    return ($user) ? $user : false;
                }
            } catch (\Throwable $th) {
                return false;
            }
        } else {
            return true; 
        }
        return false; 
    }

    public function sendJsonErrorToken($nullToken): array
    {
        return [
            'error' => true,
            'message' => ($nullToken) ? "Authentification requise. Vous devez être connecté pour effectuer cette action." : "Token incorrect",
        ];
    }
}
