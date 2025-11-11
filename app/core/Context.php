<?php
namespace Core;

class Context
{
    private static string $file = APP_ROOT . '/storage/db_connection.json.enc';
    private static string $secret = 'your-secret-key-change-this'; // store in env variable ideally
    private static string $method = 'AES-256-CBC';

    private static function getIv(): string
    {
        return substr(hash('sha256', self::$secret), 0, 16);
    }

    public static function setActiveConnection(string $username, string $conn): void
    {
        $data = [];
        if (file_exists(self::$file)) {
            $json = self::decrypt(file_get_contents(self::$file));
            $data = json_decode($json, true) ?: [];
        }

        $data[$username] = [
            'active_connection' => $conn,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $encrypted = self::encrypt(json_encode($data, JSON_PRETTY_PRINT));
        file_put_contents(self::$file, $encrypted);
    }

    public static function getActiveConnection(string $username): ?string
    {
        if (!file_exists(self::$file)) return null;
        $json = self::decrypt(file_get_contents(self::$file));
        $data = json_decode($json, true);
        return $data[$username]['active_connection'] ?? null;
    }

    private static function encrypt(string $plaintext): string
    {
        return openssl_encrypt($plaintext, self::$method, self::$secret, 0, self::getIv());
    }

    private static function decrypt(string $cipher): string
    {
        return openssl_decrypt($cipher, self::$method, self::$secret, 0, self::getIv());
    }
}
