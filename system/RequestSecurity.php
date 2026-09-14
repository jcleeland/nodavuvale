<?php

final class RequestSecurity
{
    public static function token(): string
    {
        if (empty($_SESSION['administration_csrf'])) {
            $_SESSION['administration_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['administration_csrf'];
    }

    public static function validToken($token): bool
    {
        return is_string($token) && isset($_SESSION['administration_csrf'])
            && hash_equals($_SESSION['administration_csrf'], $token);
    }
}
