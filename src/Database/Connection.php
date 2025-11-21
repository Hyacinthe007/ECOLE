<?php

declare(strict_types=1);

namespace App\Database;

use mysqli;

/**
 * Gestionnaire de connexion à la base de données
 */
class Connection
{
    private static ?mysqli $instance = null;
    
    private function __construct()
    {
    }
    
    public static function getInstance(): mysqli
    {
        if (self::$instance === null) {
            if (!defined('DB_HOST')) {
                define('DB_HOST', 'localhost');
            }
            if (!defined('DB_USER')) {
                define('DB_USER', 'root');
            }
            if (!defined('DB_PASS')) {
                define('DB_PASS', '');
            }
            if (!defined('DB_NAME')) {
                define('DB_NAME', 'ecole');
            }
            
            self::$instance = new mysqli(
                DB_HOST,
                DB_USER,
                DB_PASS,
                DB_NAME
            );
            
            if (self::$instance->connect_error) {
                throw new \RuntimeException(
                    'Erreur de connexion : ' . self::$instance->connect_error
                );
            }
            
            self::$instance->set_charset("utf8mb4");
        }
        
        return self::$instance;
    }
    
    public function __clone()
    {
        throw new \RuntimeException('Clonage interdit');
    }
    
    public function __wakeup()
    {
        throw new \RuntimeException('Désérialisation interdite');
    }
}

