<?php

namespace App\Controller\Api;

use App\Entity\User;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;  // Add this import
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Finder\Exception\AccessDeniedException;

class AuthController extends AbstractController
{
    private JWTTokenManagerInterface $jwtManager;
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher; // Injected service

    public function __construct(JWTTokenManagerInterface $jwtManager, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher)
    {
        $this->jwtManager = $jwtManager;
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    /**
     * @Route("/api/login", name="api_login", methods={"POST"})
     */
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request, KernelInterface $kernel): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email']) || !isset($data['password'])) {
            return new JsonResponse(['error' => 'Username and password are required'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $data['email']]);

        // Log the password comparison
        // dump($user->getPassword()); // Check the password stored in DB
        // dump($data['password']); // Check the password provided by the user

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $data['password'])) {
            return new JsonResponse(['error' => 'Invalid credentials'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $token = $this->jwtManager->create($user); // Generate JWT token

        // Resolve the absolute path properly
        $publicKeyPath = $kernel->getProjectDir() . '/config/jwt/public.pem';

        if (!file_exists($publicKeyPath)) {
            return new JsonResponse(['error' => 'Public key file not found'], JsonResponse::HTTP_INTERNAL_SERVER_ERROR);
        }

        $publicKey = file_get_contents($publicKeyPath);

        $decryptedToken = JWT::decode($token, new Key($publicKey, 'RS256'));

        // return new JsonResponse(['token' => $token, "username" => $decryptedToken->username]);
        return new JsonResponse(['token' => $token]);
    }



    /**
     * @Route("/api/register", name="api_register", methods={"POST"})
     */
    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email']) || !isset($data['password'])) {
            return new JsonResponse(['error' => 'Email and password are required'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $allowedRoles = ['ROLE_ADMIN', 'ROLE_CUSTOMER'];
        $role = $data['role'] ?? 'ROLE_CUSTOMER'; // default to customer if no role provided

        if (!in_array($role, $allowedRoles)) {
            return new JsonResponse(['error' => 'Invalid role provided'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $user = new User();
        $user->setEmail($data['email']);
        $user->setRoles([$role]);  // Assign role dynamically

        $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);

        $user->setCreatedAt(new \DateTimeImmutable());
        $user->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return new JsonResponse(['message' => "User successfully registered with role {$role}"], JsonResponse::HTTP_CREATED);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/api/protected', name: 'api_protected', methods: ['POST'])]
    public function protected(Request $request): JsonResponse
    {

        // $authHeader = $request->headers->get('Authorization');

        // if (!$authHeader || strpos($authHeader, 'Bearer ') !== 0) {
        //     return new JsonResponse(['message' => 'JWT Token not found'], JsonResponse::HTTP_UNAUTHORIZED);
        // }

        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['error' => 'Access Denied'], JsonResponse::HTTP_FORBIDDEN);
        }

        // $token = substr($authHeader, 7); // Extract the token from the header
        return new JsonResponse(["message" => "hello saif "]);
    }


    #[Route('/', name: 'default_page', methods: ['GET'])]
    public function defaults(): Response
    {
        return new Response('');
    }
}
