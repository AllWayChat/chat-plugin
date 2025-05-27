<?php

namespace Allway\Chat\Classes\Helpers;

use libphonenumber\CountryCodeToRegionCodeMap;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber;
use libphonenumber\PhoneNumberUtil;
use StdClass;

class Phone
{
    public static function validate($phone, $defaultRegion = 'BR'): bool
    {
        try {
            $numberProto = self::getPhoneNumberObject($phone, $defaultRegion);
            $phoneUtil = PhoneNumberUtil::getInstance();
            return $phoneUtil->isValidNumber($numberProto);
        } catch (NumberParseException $e) {
            return false;
        }
    }

    public static function getPhoneNumberObject($phone, $defaultRegion = 'BR'): PhoneNumber
    {
        $code = self::getCountryCodeByPhone($phone);
        $locale = self::getCountryLocaleByCode($code);

        $phoneUtil = PhoneNumberUtil::getInstance();
        return $phoneUtil->parse(ltrim($phone, '+'), $locale ?: $defaultRegion);
    }

    public static function getCountryCodeByPhone($phone)
    {
        $phone = ltrim($phone, '+');
        foreach (self::getCountriesCode() as $locale => $code) {
            if (substr($phone, 0, strlen($code)) == $code) {
                return $code;
            }
        }

        return null;
    }

    public static function getCountriesCode(): array
    {
        return ['AF' => '93', 'AL' => '355', 'DZ' => '213', 'AS' => '1-684', 'AD' => '376', 'AO' => '244', 'AI' => '1-264', 'AQ' => '672', 'AG' => '1-268', 'AR' => '54', 'AM' => '374', 'AW' => '297', 'AU' => '61', 'AT' => '43', 'AZ' => '994', 'BS' => '1-242', 'BH' => '973', 'BD' => '880', 'BB' => '1-246', 'BY' => '375', 'BE' => '32', 'BZ' => '501', 'BJ' => '229', 'BM' => '1-441', 'BT' => '975', 'BO' => '591', 'BA' => '387', 'BW' => '267', 'BR' => '55', 'IO' => '246', 'VG' => '1-284', 'BN' => '673', 'BG' => '359', 'BF' => '226', 'BI' => '257', 'KH' => '855', 'CM' => '237', 'CA' => '1', 'CV' => '238', 'KY' => '1-345', 'CF' => '236', 'TD' => '235', 'CL' => '56', 'CN' => '86', 'CX' => '61', 'CC' => '61', 'CO' => '57', 'KM' => '269', 'CK' => '682', 'CR' => '506', 'HR' => '385', 'CU' => '53', 'CW' => '599', 'CY' => '357', 'CZ' => '420', 'CD' => '243', 'DK' => '45', 'DJ' => '253', 'DM' => '1-767', 'DO' => '1-809', 'TL' => '670', 'EC' => '593', 'EG' => '20', 'SV' => '503', 'GQ' => '240', 'ER' => '291', 'EE' => '372', 'ET' => '251', 'FK' => '500', 'FO' => '298', 'FJ' => '679', 'FI' => '358', 'FR' => '33', 'PF' => '689', 'GA' => '241', 'GM' => '220', 'GE' => '995', 'DE' => '49', 'GH' => '233', 'GI' => '350', 'GR' => '30', 'GL' => '299', 'GD' => '1-473', 'GU' => '1-671', 'GT' => '502', 'GG' => '44-1481', 'GN' => '224', 'GW' => '245', 'GY' => '592', 'HT' => '509', 'HN' => '504', 'HK' => '852', 'HU' => '36', 'IS' => '354', 'IN' => '91', 'ID' => '62', 'IR' => '98', 'IQ' => '964', 'IE' => '353', 'IM' => '44-1624', 'IL' => '972', 'IT' => '39', 'CI' => '225', 'JM' => '1-876', 'JP' => '81', 'JE' => '44-1534', 'JO' => '962', 'KZ' => '7', 'KE' => '254', 'KI' => '686', 'XK' => '383', 'KW' => '965', 'KG' => '996', 'LA' => '856', 'LV' => '371', 'LB' => '961', 'LS' => '266', 'LR' => '231', 'LY' => '218', 'LI' => '423', 'LT' => '370', 'LU' => '352', 'MO' => '853', 'MK' => '389', 'MG' => '261', 'MW' => '265', 'MY' => '60', 'MV' => '960', 'ML' => '223', 'MT' => '356', 'MH' => '692', 'MR' => '222', 'MU' => '230', 'YT' => '262', 'MX' => '52', 'FM' => '691', 'MD' => '373', 'MC' => '377', 'MN' => '976', 'ME' => '382', 'MS' => '1-664', 'MA' => '212', 'MZ' => '258', 'MM' => '95', 'NA' => '264', 'NR' => '674', 'NP' => '977', 'NL' => '31', 'AN' => '599', 'NC' => '687', 'NZ' => '64', 'NI' => '505', 'NE' => '227', 'NG' => '234', 'NU' => '683', 'KP' => '850', 'MP' => '1-670', 'NO' => '47', 'OM' => '968', 'PK' => '92', 'PW' => '680', 'PS' => '970', 'PA' => '507', 'PG' => '675', 'PY' => '595', 'PE' => '51', 'PH' => '63', 'PN' => '64', 'PL' => '48', 'PT' => '351', 'PR' => '1-787', 'QA' => '974', 'CG' => '242', 'RE' => '262', 'RO' => '40', 'RU' => '7', 'RW' => '250', 'BL' => '590', 'SH' => '290', 'KN' => '1-869', 'LC' => '1-758', 'MF' => '590', 'PM' => '508', 'VC' => '1-784', 'WS' => '685', 'SM' => '378', 'ST' => '239', 'SA' => '966', 'SN' => '221', 'RS' => '381', 'SC' => '248', 'SL' => '232', 'SG' => '65', 'SX' => '1-721', 'SK' => '421', 'SI' => '386', 'SB' => '677', 'SO' => '252', 'ZA' => '27', 'KR' => '82', 'SS' => '211', 'ES' => '34', 'LK' => '94', 'SD' => '249', 'SR' => '597', 'SJ' => '47', 'SZ' => '268', 'SE' => '46', 'CH' => '41', 'SY' => '963', 'TW' => '886', 'TJ' => '992', 'TZ' => '255', 'TH' => '66', 'TG' => '228', 'TK' => '690', 'TO' => '676', 'TT' => '1-868', 'TN' => '216', 'TR' => '90', 'TM' => '993', 'TC' => '1-649', 'TV' => '688', 'VI' => '1-340', 'UG' => '256', 'UA' => '380', 'AE' => '971', 'GB' => '44', 'US' => '1', 'UY' => '598', 'UZ' => '998', 'VU' => '678', 'VA' => '379', 'VE' => '58', 'VN' => '84', 'WF' => '681', 'EH' => '212', 'YE' => '967', 'ZM' => '260', 'ZW' => '263'];
    }

    public static function getCountryLocaleByCode($code)
    {
        return array_flip(self::getCountriesCode())[$code] ?? null;
    }

    public static function parsePhone($phone, $defaultRegion = 'BR'): StdClass
    {
        $result = new StdClass();

        $result->areaCode = '';
        $result->number = '';

        $phoneUtil = PhoneNumberUtil::getInstance();

        $phoneNumberObject = self::getPhoneNumberObject($phone, $defaultRegion);

        $result->regionCode = $phoneNumberObject->getCountryCode();
        $result->countryCode = current(CountryCodeToRegionCodeMap::$countryCodeToRegionCodeMap[$result->regionCode]);

        $nationalSignificantNumber = $phoneUtil->getNationalSignificantNumber($phoneNumberObject);
        $nationalDestinationCodeLength = $phoneUtil->getLengthOfNationalDestinationCode($phoneNumberObject);

        if ($nationalDestinationCodeLength > 0) {
            $result->areaCode = substr($nationalSignificantNumber, 0, $nationalDestinationCodeLength);
            $result->number = substr($nationalSignificantNumber, $nationalDestinationCodeLength);
        } else {
            $result->number = $nationalSignificantNumber;
        }

        $result->number = self::justnumber($result->number);

        return $result;
    }

    /**
     * Remove formatação de uma string e retorna apenas numeros
     * @param string $text
     * @return string
     */
    public static function justnumber($text)
    {
        if ($text) {
            return trim(preg_replace('/[^0-9]/', '', $text));
        }

        return $text;
    }

    public static function validateLocale($locale): bool
    {
        return (bool)self::getCountryNameByLocale($locale);
    }

    public static function getCountryNameByLocale($locale): ?string
    {
        return [
            'AF' => 'Afghanistan',
            'AX' => 'Aland Islands',
            'AL' => 'Albania',
            'DZ' => 'Algeria',
            'AS' => 'American Samoa',
            'AD' => 'Andorra',
            'AO' => 'Angola',
            'AI' => 'Anguilla',
            'AQ' => 'Antarctica',
            'AG' => 'Antigua And Barbuda',
            'AR' => 'Argentina',
            'AM' => 'Armenia',
            'AW' => 'Aruba',
            'AU' => 'Australia',
            'AT' => 'Austria',
            'AZ' => 'Azerbaijan',
            'BS' => 'Bahamas',
            'BH' => 'Bahrain',
            'BD' => 'Bangladesh',
            'BB' => 'Barbados',
            'BY' => 'Belarus',
            'BE' => 'Belgium',
            'BZ' => 'Belize',
            'BJ' => 'Benin',
            'BM' => 'Bermuda',
            'BT' => 'Bhutan',
            'BO' => 'Bolivia',
            'BA' => 'Bosnia And Herzegovina',
            'BW' => 'Botswana',
            'BV' => 'Bouvet Island',
            'BR' => 'Brazil',
            'IO' => 'British Indian Ocean Territory',
            'BN' => 'Brunei Darussalam',
            'BG' => 'Bulgaria',
            'BF' => 'Burkina Faso',
            'BI' => 'Burundi',
            'KH' => 'Cambodia',
            'CM' => 'Cameroon',
            'CA' => 'Canada',
            'CV' => 'Cape Verde',
            'KY' => 'Cayman Islands',
            'CF' => 'Central African Republic',
            'TD' => 'Chad',
            'CL' => 'Chile',
            'CN' => 'China',
            'CX' => 'Christmas Island',
            'CC' => 'Cocos (Keeling) Islands',
            'CO' => 'Colombia',
            'KM' => 'Comoros',
            'CG' => 'Congo',
            'CD' => 'Congo, Democratic Republic',
            'CK' => 'Cook Islands',
            'CR' => 'Costa Rica',
            'CI' => 'Cote D\'Ivoire',
            'HR' => 'Croatia',
            'CU' => 'Cuba',
            'CY' => 'Cyprus',
            'CZ' => 'Czech Republic',
            'DK' => 'Denmark',
            'DJ' => 'Djibouti',
            'DM' => 'Dominica',
            'DO' => 'Dominican Republic',
            'EC' => 'Ecuador',
            'EG' => 'Egypt',
            'SV' => 'El Salvador',
            'GQ' => 'Equatorial Guinea',
            'ER' => 'Eritrea',
            'EE' => 'Estonia',
            'ET' => 'Ethiopia',
            'FK' => 'Falkland Islands (Malvinas)',
            'FO' => 'Faroe Islands',
            'FJ' => 'Fiji',
            'FI' => 'Finland',
            'FR' => 'France',
            'GF' => 'French Guiana',
            'PF' => 'French Polynesia',
            'TF' => 'French Southern Territories',
            'GA' => 'Gabon',
            'GM' => 'Gambia',
            'GE' => 'Georgia',
            'DE' => 'Germany',
            'GH' => 'Ghana',
            'GI' => 'Gibraltar',
            'GR' => 'Greece',
            'GL' => 'Greenland',
            'GD' => 'Grenada',
            'GP' => 'Guadeloupe',
            'GU' => 'Guam',
            'GT' => 'Guatemala',
            'GG' => 'Guernsey',
            'GN' => 'Guinea',
            'GW' => 'Guinea-Bissau',
            'GY' => 'Guyana',
            'HT' => 'Haiti',
            'HM' => 'Heard Island & Mcdonald Islands',
            'VA' => 'Holy See (Vatican City State)',
            'HN' => 'Honduras',
            'HK' => 'Hong Kong',
            'HU' => 'Hungary',
            'IS' => 'Iceland',
            'IN' => 'India',
            'ID' => 'Indonesia',
            'IR' => 'Iran, Islamic Republic Of',
            'IQ' => 'Iraq',
            'IE' => 'Ireland',
            'IM' => 'Isle Of Man',
            'IL' => 'Israel',
            'IT' => 'Italy',
            'JM' => 'Jamaica',
            'JP' => 'Japan',
            'JE' => 'Jersey',
            'JO' => 'Jordan',
            'KZ' => 'Kazakhstan',
            'KE' => 'Kenya',
            'KI' => 'Kiribati',
            'KR' => 'Korea',
            'KW' => 'Kuwait',
            'KG' => 'Kyrgyzstan',
            'LA' => 'Lao People\'s Democratic Republic',
            'LV' => 'Latvia',
            'LB' => 'Lebanon',
            'LS' => 'Lesotho',
            'LR' => 'Liberia',
            'LY' => 'Libyan Arab Jamahiriya',
            'LI' => 'Liechtenstein',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'MO' => 'Macao',
            'MK' => 'Macedonia',
            'MG' => 'Madagascar',
            'MW' => 'Malawi',
            'MY' => 'Malaysia',
            'MV' => 'Maldives',
            'ML' => 'Mali',
            'MT' => 'Malta',
            'MH' => 'Marshall Islands',
            'MQ' => 'Martinique',
            'MR' => 'Mauritania',
            'MU' => 'Mauritius',
            'YT' => 'Mayotte',
            'MX' => 'Mexico',
            'FM' => 'Micronesia, Federated States Of',
            'MD' => 'Moldova',
            'MC' => 'Monaco',
            'MN' => 'Mongolia',
            'ME' => 'Montenegro',
            'MS' => 'Montserrat',
            'MA' => 'Morocco',
            'MZ' => 'Mozambique',
            'MM' => 'Myanmar',
            'NA' => 'Namibia',
            'NR' => 'Nauru',
            'NP' => 'Nepal',
            'NL' => 'Netherlands',
            'AN' => 'Netherlands Antilles',
            'NC' => 'New Caledonia',
            'NZ' => 'New Zealand',
            'NI' => 'Nicaragua',
            'NE' => 'Niger',
            'NG' => 'Nigeria',
            'NU' => 'Niue',
            'NF' => 'Norfolk Island',
            'MP' => 'Northern Mariana Islands',
            'NO' => 'Norway',
            'OM' => 'Oman',
            'PK' => 'Pakistan',
            'PW' => 'Palau',
            'PS' => 'Palestinian Territory, Occupied',
            'PA' => 'Panama',
            'PG' => 'Papua New Guinea',
            'PY' => 'Paraguay',
            'PE' => 'Peru',
            'PH' => 'Philippines',
            'PN' => 'Pitcairn',
            'PL' => 'Poland',
            'PT' => 'Portugal',
            'PR' => 'Puerto Rico',
            'QA' => 'Qatar',
            'RE' => 'Reunion',
            'RO' => 'Romania',
            'RU' => 'Russian Federation',
            'RW' => 'Rwanda',
            'BL' => 'Saint Barthelemy',
            'SH' => 'Saint Helena',
            'KN' => 'Saint Kitts And Nevis',
            'LC' => 'Saint Lucia',
            'MF' => 'Saint Martin',
            'PM' => 'Saint Pierre And Miquelon',
            'VC' => 'Saint Vincent And Grenadines',
            'WS' => 'Samoa',
            'SM' => 'San Marino',
            'ST' => 'Sao Tome And Principe',
            'SA' => 'Saudi Arabia',
            'SN' => 'Senegal',
            'RS' => 'Serbia',
            'SC' => 'Seychelles',
            'SL' => 'Sierra Leone',
            'SG' => 'Singapore',
            'SK' => 'Slovakia',
            'SI' => 'Slovenia',
            'SB' => 'Solomon Islands',
            'SO' => 'Somalia',
            'ZA' => 'South Africa',
            'GS' => 'South Georgia And Sandwich Isl.',
            'ES' => 'Spain',
            'LK' => 'Sri Lanka',
            'SD' => 'Sudan',
            'SR' => 'Suriname',
            'SJ' => 'Svalbard And Jan Mayen',
            'SZ' => 'Swaziland',
            'SE' => 'Sweden',
            'CH' => 'Switzerland',
            'SY' => 'Syrian Arab Republic',
            'TW' => 'Taiwan',
            'TJ' => 'Tajikistan',
            'TZ' => 'Tanzania',
            'TH' => 'Thailand',
            'TL' => 'Timor-Leste',
            'TG' => 'Togo',
            'TK' => 'Tokelau',
            'TO' => 'Tonga',
            'TT' => 'Trinidad And Tobago',
            'TN' => 'Tunisia',
            'TR' => 'Turkey',
            'TM' => 'Turkmenistan',
            'TC' => 'Turks And Caicos Islands',
            'TV' => 'Tuvalu',
            'UG' => 'Uganda',
            'UA' => 'Ukraine',
            'AE' => 'United Arab Emirates',
            'GB' => 'United Kingdom',
            'US' => 'United States',
            'UM' => 'United States Outlying Islands',
            'UY' => 'Uruguay',
            'UZ' => 'Uzbekistan',
            'VU' => 'Vanuatu',
            'VE' => 'Venezuela',
            'VN' => 'Viet Nam',
            'VG' => 'Virgin Islands, British',
            'VI' => 'Virgin Islands, U.S.',
            'WF' => 'Wallis And Futuna',
            'EH' => 'Western Sahara',
            'YE' => 'Yemen',
            'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe',
        ][$locale] ?? null;
    }

    public static function getCountryCodeByLocale($locale)
    {
        return self::getCountriesCode()[$locale] ?? null;
    }

    /**
     * Format phone number with international format
     */
    public static function formatInternational($phone, $defaultRegion = 'BR'): string
    {
        try {
            // Se já tem +, retorna como está
            if (strpos($phone, '+') === 0) {
                return $phone;
            }
            
            $phone = self::justnumber($phone);
            
            if (empty($phone)) {
                return '+55';
            }
            
            $phoneUtil = PhoneNumberUtil::getInstance();
            
            // Verifica se já tem código de país no início
            // Ordena códigos por tamanho decrescente para verificar códigos maiores primeiro
            $countryCodes = self::getCountriesCode();
            uasort($countryCodes, function($a, $b) {
                return strlen(str_replace('-', '', $b)) - strlen(str_replace('-', '', $a));
            });
            
            foreach ($countryCodes as $locale => $code) {
                $cleanCode = str_replace('-', '', $code);
                
                if (substr($phone, 0, strlen($cleanCode)) === $cleanCode) {
                    $phoneWithoutCode = substr($phone, strlen($cleanCode));
                    
                    // Se não sobrou nada ou é muito curto, não é válido
                    if (empty($phoneWithoutCode) || strlen($phoneWithoutCode) < 8) {
                        continue;
                    }
                    
                    try {
                        $phoneNumberObject = $phoneUtil->parse($phoneWithoutCode, $locale);
                        if ($phoneUtil->isValidNumber($phoneNumberObject)) {
                            return '+' . $phone;
                        }
                    } catch (NumberParseException $e) {
                        continue;
                    }
                }
            }
            
            // Se chegou aqui, não tem código de país válido
            // Tenta validar como número da região padrão
            try {
                $phoneNumberObject = $phoneUtil->parse($phone, $defaultRegion);
                
                if ($phoneUtil->isValidNumber($phoneNumberObject)) {
                    $defaultCode = self::getCountryCodeByLocale($defaultRegion);
                    if ($defaultCode) {
                        $cleanDefaultCode = str_replace('-', '', $defaultCode);
                        return '+' . $cleanDefaultCode . $phone;
                    }
                }
            } catch (NumberParseException $e) {
                // Se não conseguiu validar com região padrão
            }
            
            // Default para Brasil se nada funcionou
            return '+55' . $phone;
            
        } catch (\Exception $e) {
            $phone = self::justnumber($phone);
            return '+55' . $phone;
        }
    }

    /**
     * Check if phone number has country code
     */
    public static function hasCountryCode($phone): bool
    {
        $phone = self::justnumber($phone);
        
        foreach (self::getCountriesCode() as $locale => $code) {
            $cleanCode = str_replace('-', '', $code);
            if (substr($phone, 0, strlen($cleanCode)) === $cleanCode) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Format Brazilian phone number specifically
     */
    public static function formatBrazilianPhone($phone): string
    {
        $phone = self::justnumber($phone);
        
        if (empty($phone)) {
            return '+55';
        }

        if (substr($phone, 0, 2) === '55' && strlen($phone) > 2) {
            $phoneWithoutCode = substr($phone, 2);
            
            if (strlen($phoneWithoutCode) >= 10 && strlen($phoneWithoutCode) <= 11) {
                if (preg_match('/^[1-9]\d{9,10}$/', $phoneWithoutCode)) {
                    return '+' . $phone;
                }
            }
            
            $phone = $phoneWithoutCode;
        }

        if (strlen($phone) >= 10 && strlen($phone) <= 11) {
            if (preg_match('/^[1-9]\d{9,10}$/', $phone)) {
                return '+55' . $phone;
            }
        }

        return '+55' . $phone;
    }
} 