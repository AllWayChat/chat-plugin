<?php

namespace Allway\Chat\Classes\Helpers;

class Email
{
    /**
     * Valida se um email é válido
     */
    public static function validate(string $email): bool
    {
        if (empty($email)) {
            return false;
        }
        
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Normaliza um email removendo espaços e convertendo para minúsculo
     */
    public static function normalize(string $email): string
    {
        return strtolower(trim($email));
    }
    
    /**
     * Extrai o domínio de um email
     */
    public static function getDomain(string $email): ?string
    {
        if (!self::validate($email)) {
            return null;
        }
        
        $parts = explode('@', $email);
        return isset($parts[1]) ? $parts[1] : null;
    }
    
    /**
     * Extrai a parte local (antes do @) de um email
     */
    public static function getLocalPart(string $email): ?string
    {
        if (!self::validate($email)) {
            return null;
        }
        
        $parts = explode('@', $email);
        return isset($parts[0]) ? $parts[0] : null;
    }
} 