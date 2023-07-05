<?php

namespace App\DataFixtures;

use App\Entity\Course;
use App\Entity\User;
use App\Service\PaymentService;
use DateInterval;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Gesdinet\JWTRefreshTokenBundle\Generator\RefreshTokenGeneratorInterface;
use Gesdinet\JWTRefreshTokenBundle\Model\RefreshTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;
    private RefreshTokenGeneratorInterface $refreshTokenGenerator;
    private RefreshTokenManagerInterface $refreshTokenManager;
    private PaymentService $paymentService;
    public function __construct(
        UserPasswordHasherInterface $passwordHasher,
        RefreshTokenGeneratorInterface $refreshTokenGenerator,
        RefreshTokenManagerInterface $refreshTokenManager,
        PaymentService $paymentService
    ) {
        $this->passwordHasher = $passwordHasher;
        $this->refreshTokenGenerator = $refreshTokenGenerator;
        $this->refreshTokenManager = $refreshTokenManager;
        $this->paymentService = $paymentService;
    }

    public function load(ObjectManager $manager): void
    {
        $User = new User();

        $User->setRoles(['ROLE_USER']);
        $password = $this->passwordHasher->hashPassword($User, 'password');
        $User->setEmail("user@gmail.com");
        $User->setPassword($password);

        $manager->persist($User);
        $manager->flush();

        $Admin = new User();

        $Admin->setRoles(['ROLE_SUPER_ADMIN']);
        $adminPassword = $this->passwordHasher->hashPassword($Admin, 'password');
        $Admin->setEmail("admin@gmail.com");
        $Admin->setPassword($adminPassword);

        $manager->persist($Admin);
        $manager->flush();

        foreach (self::COURSES_DATA as $courseData) {
            $course = (new Course())
                ->setCode($courseData['code'])
                ->setType($courseData['type']);
            if (isset($courseData['price'])) {
                $course->setPrice($courseData['price']);
            }

            $coursesByCode[$courseData['code']] = $course;
            $manager->persist($course);
            $manager->flush();
        }
    }

    private const COURSES_DATA = [
        [
            'code' => '00a1',
            'type' => 0 // free
        ],
        [
            'code' => '00с3',
            'type' => 1,
            // rent
            'price' => 20
        ],
        [
            'code' => '032v',
            'type' => 2,
            // buy
            'price' => 30
        ],
        [
            'code' => '032у',
            'type' => 2,
            // buy
            'price' => 40
        ],
        [
            'code' => '0а2у',
            'type' => 1,
            // rent
            'price' => 10
        ],
    ];
}

