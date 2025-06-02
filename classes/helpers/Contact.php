<?php

namespace Allway\Chat\Classes\Helpers;

use Allway\Chat\Classes\Helpers\Phone;
use Allway\Chat\Classes\Helpers\Email;

class Contact
{
    /**
     * Valida um identificador de contato (email ou telefone)
     */
    public static function validateIdentifier(string $contactIdentifier): bool
    {
        if (empty($contactIdentifier)) {
            return false;
        }
        
        // Se parece com email, valida como email
        if (filter_var($contactIdentifier, FILTER_VALIDATE_EMAIL)) {
            return Email::validate($contactIdentifier);
        } else {
            // Senão, valida como telefone
            return Phone::validate($contactIdentifier);
        }
    }
    
    /**
     * Identifica o tipo de identificador (email ou phone)
     */
    public static function getIdentifierType(string $contactIdentifier): ?string
    {
        if (empty($contactIdentifier)) {
            return null;
        }
        
        if (filter_var($contactIdentifier, FILTER_VALIDATE_EMAIL)) {
            return 'email';
        } else {
            return 'phone';
        }
    }
    
    /**
     * Normaliza um identificador de contato
     */
    public static function normalizeIdentifier(string $contactIdentifier): string
    {
        $type = self::getIdentifierType($contactIdentifier);
        
        if ($type === 'email') {
            return Email::normalize($contactIdentifier);
        } elseif ($type === 'phone') {
            return Phone::formatInternational($contactIdentifier);
        }
        
        return $contactIdentifier;
    }
    
    /**
     * Valida se um email é válido
     */
    public static function validateEmail(string $email): bool
    {
        return Email::validate($email);
    }
    
    /**
     * Valida se um telefone é válido
     */
    public static function validatePhone(string $phone): bool
    {
        return Phone::validate($phone);
    }
} 