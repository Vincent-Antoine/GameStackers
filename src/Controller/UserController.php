<?php


namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManager;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
#[Route('/api', name: 'api_')]
class UserController extends AbstractController
{
    #[Route('/signup', name: 'signup', methods: ['GET', 'POST'])]
    public function signup(Request $request,
                           EntityManager $entityManager,
                           SerializerInterface $serializer,
                           ValidatorInterface $validator, UserPasswordHasherInterface $passwordHasher ): JsonResponse
    {

        $user = $serializer->deserialize($request->getContent(), User::class, 'json', ['groups' => 'user:signup']);

        $user->setPassword($passwordHasher->hashPassword($user, $user->getPassword()));

        $currentRoles = $user->getRoles();
        if (!in_array('ROLE_USER', $currentRoles)) {
            $user->setRoles(array_merge($currentRoles, ['ROLE_USER']));
        }

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
        }

        // Persister et enregistrer l'objet User
        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json($user, Response::HTTP_CREATED, [], ['groups' => 'user:signup']);

    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request, UserPasswordHasherInterface $passwordHasher, UserRepository $userRepository, JWTTokenManagerInterface $JWTManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        $user = $userRepository->findOneBy(['email' => $email]);

        if (!$user) {
            return $this->json(['message' => 'Invalid Credential'], Response::HTTP_UNAUTHORIZED);
        }

        if(!$passwordHasher->isPasswordValid($user, $password)){
            return $this->json(['message' => 'Invalid Credential'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $JWTManager->create($user);

        return $this->json(['token' => $token], Response::HTTP_OK);
    }

}
