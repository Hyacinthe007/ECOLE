<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Configuration de l'application
 */
class Config
{
    public const DB_HOST = 'localhost';
    public const DB_USER = 'root';
    public const DB_PASS = '';
    public const DB_NAME = 'ecole';
    
    public const SESSION_LIFETIME = 3600;
    
    public static function init(): void
    {
        // Configuration des erreurs
        error_reporting(E_ALL & ~E_NOTICE);
        
        // Configuration de la session
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_only_cookies', '1');
        
        // Sécurité HTTPS
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}

