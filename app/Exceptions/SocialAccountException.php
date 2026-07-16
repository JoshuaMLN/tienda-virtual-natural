<?php

namespace App\Exceptions;

use RuntimeException;

class SocialAccountException extends RuntimeException
{
    public static function invalidGoogleResponse(): self
    {
        return new self('Google no devolvio una identidad valida. Intentalo nuevamente.');
    }

    public static function unverifiedGoogleEmail(): self
    {
        return new self('Google no pudo confirmar que el correo electronico este verificado.');
    }

    public static function emailMismatch(): self
    {
        return new self('Selecciona en Google la misma direccion de correo de tu cuenta VitaNatural.');
    }

    public static function identityInUse(): self
    {
        return new self('Esta cuenta de Google ya esta vinculada a otra cuenta VitaNatural.');
    }

    public static function providerAlreadyLinked(): self
    {
        return new self('Tu cuenta VitaNatural ya tiene otra cuenta de Google vinculada.');
    }

    public static function existingEmail(): self
    {
        return new self('Ya existe una cuenta VitaNatural con este correo electronico.');
    }

    public static function missingProvider(): self
    {
        return new self('No hay una cuenta de Google vinculada.');
    }

    public static function lastAuthenticationMethod(): self
    {
        return new self('Primero define una contrasena para no quedarte sin un metodo de acceso.');
    }

    public static function customerAccessOnly(): self
    {
        return new self('Este correo no puede utilizarse en el acceso de clientes con Google.');
    }
}
