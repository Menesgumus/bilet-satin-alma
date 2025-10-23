<?php

final class Auth
{
    public static function userId(): ?string
    {
        return $_SESSION['user_id'] ?? null;
    }

    public static function role(): string
    {
        return $_SESSION['role'] ?? 'guest';
    }

    public static function requireRole(string $role): void
    {
        if (self::role() !== $role) {
            http_response_code(403);
            echo 'Erişim reddedildi';
            exit;
        }
    }

    public static function requireAnyRole(array $roles): void
    {
        if (!in_array(self::role(), $roles, true)) {
            http_response_code(403);
            echo 'Erişim reddedildi';
            exit;
        }
    }
}


