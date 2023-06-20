<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class UserDto
{
    /**
     * @Assert\NotBlank(message="Email пуст!")
     * @Assert\Email( message="Email заполнен не по формату |почтовыйАдрес@почтовыйДомен.домен| ." )
     */
    public string $username;


    /**
     * @Assert\NotBlank(message="Пароль пуст!")
     */
    public string $password;
}