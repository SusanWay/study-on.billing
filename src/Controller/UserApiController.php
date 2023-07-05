<?php

namespace App\Controller;


use App\DTO\UserDTO;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\PaymentService;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerBuilder;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Routing\Annotation\Route;
use Nelmio\ApiDocBundle\Annotation\Model;
use Nelmio\ApiDocBundle\Annotation\Security;
use OpenApi\Annotations as OA;
use PHP_CodeSniffer\Tokenizers\JS;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UserApiController extends AbstractController
{

    private Serializer $serializer;
    private ValidatorInterface $validator;
    private UserPasswordHasherInterface $passwordHasher;
    private ObjectManager $entityManager;
    private TokenStorageInterface $tokenStorageInterface;
    private JWTTokenManagerInterface $jwtManager;

    public function __construct(
        ManagerRegistry             $doctrine,
        ValidatorInterface          $validator,
        UserPasswordHasherInterface $passwordHasher,
        TokenStorageInterface       $tokenStorageInterface,
        JWTTokenManagerInterface    $jwtManager
    )
    {
        $this->entityManager = $doctrine->getManager();
        $this->serializer = SerializerBuilder::create()->build();
        $this->validator = $validator;
        $this->passwordHasher = $passwordHasher;
        $this->tokenStorageInterface = $tokenStorageInterface;
        $this->jwtManager = $jwtManager;
    }

    /**
     * @Route("/api/v1/auth", name="api_auth", methods={"POST"})
     * @OA\Post(
     *     path="/api/v1/auth",
     *     summary="Авторизация пользователя",
     *     description="Авторизация пользователя и получения ответава в виде JWT токена"
     * )
     * @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="username",
     *          type="string",
     *          description="email",
     *          example="user@gmail.com",
     *        ),
     *        @OA\Property(
     *          property="password",
     *          type="string",
     *          description="password",
     *          example="password",
     *        ),
     *     )
     *)
     * @OA\Response(
     *     response=200,
     *     description="Удачная авторизация пользователя",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="token",
     *          type="string",
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response=401,
     *     description="Ошибка Авторизации",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="code",
     *          type="string",
     *          example="401"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Введены неверные данные"
     *        ),
     *     )
     * )
     * @OA\Tag(name="User")
     */
    public function auth(): void
    {
    }

    /**
     * @Route("/api/v1/register", name="api_register", methods={"POST"})
     * @OA\Post(
     *     path="/api/v1/register",
     *     summary="Регистрация ползователя",
     *     description="Регистрация пользователя и получение JWT токена"
     * )
     * @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="username",
     *          type="string",
     *          description="email",
     *          example="testNewUser@mail.ru",
     *        ),
     *        @OA\Property(
     *          property="password",
     *          type="string",
     *          description="password",
     *          example="12345678",
     *        ),
     *     )
     *  )
     * )
     * @OA\Response(
     *     response=201,
     *     description="Удачная регистрация пользователя",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="token",
     *          type="string",
     *        ),
     *        @OA\Property(
     *          property="roles",
     *          type="array",
     *          @OA\Items(
     *              type="string",
     *          ),
     *        ),
     *     ),
     * )
     * @OA\Response(
     *     response=400,
     *     description="Ошибка валидации",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="errors",
     *          type="array",
     *          @OA\Items(
     *                  type="string",
     *              )
     *          )
     *        )
     *     )
     * )
     * @OA\Response(
     *     response=500,
     *     description="Неизвестная ошибка",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="code",
     *          type="string"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string"
     *        )
     *     )
     * )
     * @OA\Tag(name="User")
     * @param Request $req
     * @param UserRepository $repo
     * @param JWTTokenManagerInterface $jwtManager
     * @param $request
     * @return JsonResponse
     */
    public function register(
        Request                        $request,
        UserRepository                 $repo,
        JWTTokenManagerInterface       $jwtManager,
        RefreshTokenGeneratorInterface $refreshTokenGenerator,
        RefreshTokenManagerInterface   $refreshTokenManager,
        PaymentService                 $paymentService
    ): JsonResponse
    {

        $DTO_user = $this->serializer->deserialize($request->getContent(), UserDTO::class, 'json');
        $errors = $this->validator->validate($DTO_user);
        if (count($errors) > 0) {
            $errors_array = [];
            foreach ($errors as $error) {
                $errors_array[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errors_array], Response::HTTP_BAD_REQUEST);
        }
        if ($this->entityManager->getRepository(User::class)->findOneByEmail($DTO_user->getUsername())) {
            return new JsonResponse(['errors' => ['This email already exists']], Response::HTTP_BAD_REQUEST);
        }

        $user = User::getFromDTO($DTO_user);

        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $user->getPassword())
        );

        $this->entityManager->getRepository(User::class)->add($user, true);

        // TODO Подтягивание начального депозита из конфигуарционного фала
        $paymentService->deposit($user, 100);
        $refreshToken = $refreshTokenGenerator->createForUserWithTtl(
            $user,
            (new \DateTime())->modify('+1 month')->getTimestamp()
        );
        $refreshTokenManager->save($refreshToken);

        return new JsonResponse([
            'token' => $this->jwtManager->create($user),
            'refresh_token' => $refreshToken->getRefreshToken(),
            'roles' => $user->getRoles(),
        ], Response::HTTP_CREATED);
    }

    /**
     * @Route("/api/v1/token/refresh", name="api_refresh_token", methods={"POST"})
     * @OA\Post(
     *     path="/api/v1/token/refresh",
     *     summary="Обновление истекших токенов",
     *     description="Обновление истекших токенов"
     * )
     * @OA\RequestBody(
     *     required=true,
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="refresh_token",
     *          type="string",
     *          description="refresh токен пользователя",
     *          example="refresh_token",
     *        ),
     *     )
     *)
     * @OA\Response(
     *     response=200,
     *     description="Обновление истекших токенов",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="token",
     *          type="string",
     *        ),
     *        @OA\Property(
     *          property="refresh_token",
     *          type="string",
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response=401,
     *     description="Ошибка аутентификации",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="code",
     *          type="string",
     *          example="401"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Invalid credentials."
     *        ),
     *     )
     * )
     * @OA\Response(
     *     response="default",
     *     description="Неизвестная ошибка",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="code",
     *          type="string"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string"
     *        ),
     *     )
     * )
     * @OA\Tag(name="User")
     */
    public function refresh(): void
    {

    }

    /**
     * @Route("/api/v1/users/current", name="api_current_user", methods={"GET"})
     * @OA\Get(
     *     path="/api/v1/users/current",
     *     summary="Получение текущего пользователя",
     *     description="Получение текущего пользователя"
     * )
     * @OA\Response(
     *     response=200,
     *     description="Удачное получение информации о пользователи",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="username",
     *          type="string",
     *        ),
     *        @OA\Property(
     *          property="roles",
     *          type="array",
     *          @OA\Items(
     *              type="string"
     *          )
     *        ),
     *        @OA\Property(
     *          property="balance",
     *          type="number",
     *          format="float"
     *        )
     *     )
     * )
     * @OA\Response(
     *     response=401,
     *     description="Ошибка аунтефикации",
     *     @OA\JsonContent(
     *        @OA\Property(
     *          property="code",
     *          type="string",
     *          example="401"
     *        ),
     *        @OA\Property(
     *          property="message",
     *          type="string",
     *          example="Invalid credentials."
     *        ),
     *     )
     * )
     * @OA\Tag(name="User")
     * @Security(name="Bearer")
     */
    public function currentUser(): JsonResponse
    {
        return new JsonResponse([
            'code' => 200,
            'username' => $this->getUser()->getEmail(),
            'roles' => $this->getUser()->getRoles(),
            'balance' => $this->getUser()->getBalance(),
        ], Response::HTTP_OK);
    }
}