<?php

namespace App\Controller;


use App\Dto\UserDto;
use App\Entity\User;
use App\Repository\UserRepository;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializerBuilder;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Nelmio\ApiDocBundle\Annotation\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Annotations as OA;

class UserApiController extends AbstractController
{

    private ValidatorInterface $validator;

    private Serializer $serializer;

    private UserPasswordHasherInterface $hasher;

    public function __construct(
        ValidatorInterface          $validator,
        UserPasswordHasherInterface $hasher
    )
    {
        $this->validator = $validator;
        $this->serializer = SerializerBuilder::create()->build();
        $this->hasher = $hasher;
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
    @OA\Post(
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
     * @OA\Tag(name="User")
     */
    public function register(Request $req, UserRepository $repo, JWTTokenManagerInterface $jwtManager): JsonResponse
    {
        $dto = $this->serializer->deserialize($req->getContent(), UserDTO::class, 'json');
        $errs = $this->validator->validate($dto);

        if (count($errs) > 0) {
            $jsonErrors = [];
            foreach ($errs as $error) {
                $jsonErrors[$error->getPropertyPath()] = $error->getMessage();
            }
            return new JsonResponse(['error' => $jsonErrors], Response::HTTP_BAD_REQUEST);
        }

        if ($repo->findOneBy(['email' => $dto->username])) {
            return new JsonResponse(['error' => 'Email уже используется.'], Response::HTTP_CONFLICT);
        }
        $user = User::formDTO($dto);
        $user->setPassword(
            $this->hasher->hashPassword($user, $user->getPassword())
        );
        $repo->add($user, true);
        return new JsonResponse([
            'token' => $jwtManager->create($user),
            'roles' => $user->getRoles(),
        ], Response::HTTP_CREATED);
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
