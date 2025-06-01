<?php namespace Allway\Chat\Classes;

use Allway\Chat\Models\Account;
use Allway\Chat\Models\MessageLog;
use Allway\Chat\Classes\Helpers\Phone;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class AllwayService
{
    public static function sendText(Account $account, string $contactIdentifier, string $content, int $inboxId, string $contactName = '', array $contactCustomAttributes = [], array $conversationCustomAttributes = [], bool $forceNewConversation = false, int $conversationId = null)
    {
        return self::sendMessage($account, $contactIdentifier, $inboxId, $contactName, [
            'content' => $content,
            'message_type' => 'outgoing',
            'private' => false,
        ], $content, $contactCustomAttributes, $conversationCustomAttributes, $forceNewConversation, $conversationId);
    }

    public static function sendImage(Account $account, string $contactIdentifier, string $imageUrl, int $inboxId, string $contactName = '', string $caption = '', array $contactCustomAttributes = [], array $conversationCustomAttributes = [], bool $forceNewConversation = false, int $conversationId = null)
    {
        if (empty($imageUrl)) {
            throw new \Exception('URL da imagem é obrigatória');
        }

        return self::sendAttachment($account, $contactIdentifier, $inboxId, $contactName, $imageUrl, $caption ?: 'Imagem', basename($imageUrl), $contactCustomAttributes, $conversationCustomAttributes, $forceNewConversation, $conversationId);
    }

    public static function sendDocument(Account $account, string $contactIdentifier, string $documentUrl, int $inboxId, string $contactName = '', string $caption = '', string $filename = '', array $contactCustomAttributes = [], array $conversationCustomAttributes = [], bool $forceNewConversation = false, int $conversationId = null)
    {
        if (empty($documentUrl)) {
            throw new \Exception('URL do documento é obrigatória');
        }

        return self::sendAttachment($account, $contactIdentifier, $inboxId, $contactName, $documentUrl, $caption ?: 'Arquivo', $filename ?: basename($documentUrl), $contactCustomAttributes, $conversationCustomAttributes, $forceNewConversation, $conversationId);
    }

    protected static function sendAttachment(Account $account, string $contactIdentifier, int $inboxId, string $contactName, string $fileUrl, string $content, string $filename, array $contactCustomAttributes = [], array $conversationCustomAttributes = [], bool $forceNewConversation = false, int $conversationId = null)
    {
        $client = new Client();
        
        try {
            $inbox = self::getInboxById($account, $inboxId);
            if (!$inbox) {
                throw new \Exception('Inbox not found');
            }
            
            $contact = self::getOrCreateContact($account, $contactIdentifier, $contactName, $inbox, $contactCustomAttributes);
            
            if (!$contact) {
                throw new \Exception('Failed to create or get contact');
            }
            
            $conversation = self::getOrCreateConversationForContact($account, $contact, $inbox, $conversationCustomAttributes, $forceNewConversation, $conversationId);
            
            if (!$conversation) {
                throw new \Exception('Failed to create or get conversation');
            }

            $tempFile = self::downloadFile($fileUrl);
            
            try {
                $response = $client->post($account->api_url . '/accounts/' . $account->account_id . '/conversations/' . $conversation['id'] . '/messages', [
                    'headers' => [
                        'api_access_token' => $account->token,
                    ],
                    'multipart' => [
                        [
                            'name' => 'content',
                            'contents' => $content
                        ],
                        [
                            'name' => 'message_type',
                            'contents' => 'outgoing'
                        ],
                        [
                            'name' => 'private',
                            'contents' => 'false'
                        ],
                        [
                            'name' => 'attachments[]',
                            'contents' => fopen($tempFile, 'r'),
                            'filename' => $filename
                        ]
                    ]
                ]);
                
                $responseData = json_decode($response->getBody(), true);
                
                MessageLog::create([
                    'to_contact' => $contactIdentifier,
                    'account_id' => $account->id,
                    'content' => $content . ' (Anexo: ' . $filename . ')',
                    'conversation_id' => $conversation['id'],
                    'allway_message_id' => $responseData['id'] ?? null,
                ]);
                
                return $responseData;
                
            } finally {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
            
        } catch (GuzzleException $e) {
            MessageLog::create([
                'to_contact' => $contactIdentifier,
                'account_id' => $account->id,
                'content' => $content . ' (Anexo: ' . $filename . ')',
                'error' => $e->getMessage(),
            ]);
            
            throw new \Exception('Failed to send attachment via Allway: ' . $e->getMessage());
        }
    }

    protected static function downloadFile(string $url): string
    {
        $client = new Client();
        
        try {
            $response = $client->get($url, [
                'timeout' => 30,
                'verify' => false
            ]);
            
            $tempFile = tempnam(sys_get_temp_dir(), 'allway_attachment_');
            file_put_contents($tempFile, $response->getBody());
            
            return $tempFile;
            
        } catch (GuzzleException $e) {
            throw new \Exception('Failed to download file from URL: ' . $e->getMessage());
        }
    }

    protected static function sendMessage(Account $account, string $contactIdentifier, int $inboxId, string $contactName, array $messageData, string $logContent, array $contactCustomAttributes = [], array $conversationCustomAttributes = [], bool $forceNewConversation = false, int $conversationId = null)
    {
        $client = new Client();
        
        try {
            $inbox = self::getInboxById($account, $inboxId);
            if (!$inbox) {
                throw new \Exception('Inbox not found');
            }
            
            $contact = self::getOrCreateContact($account, $contactIdentifier, $contactName, $inbox, $contactCustomAttributes);
            
            if (!$contact) {
                throw new \Exception('Failed to create or get contact');
            }
            
            $conversation = self::getOrCreateConversationForContact($account, $contact, $inbox, $conversationCustomAttributes, $forceNewConversation, $conversationId);
            
            if (!$conversation) {
                throw new \Exception('Failed to create or get conversation');
            }
            
            $response = $client->post($account->api_url . '/accounts/' . $account->account_id . '/conversations/' . $conversation['id'] . '/messages', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'api_access_token' => $account->token,
                ],
                'json' => $messageData
            ]);
            
            $responseData = json_decode($response->getBody(), true);
            
            MessageLog::create([
                'to_contact' => $contactIdentifier,
                'account_id' => $account->id,
                'content' => $logContent,
                'conversation_id' => $conversation['id'],
                'allway_message_id' => $responseData['id'] ?? null,
            ]);
            
            return $responseData;
            
        } catch (GuzzleException $e) {
            MessageLog::create([
                'to_contact' => $contactIdentifier,
                'account_id' => $account->id,
                'content' => $logContent,
                'error' => $e->getMessage(),
            ]);
            
            throw new \Exception('Failed to send message via Allway: ' . $e->getMessage());
        }
    }

    protected static function prepareContactData(string $contactIdentifier, string $contactName, array $contactCustomAttributes = [], array $contactAdditionalAttributes = []): array
    {
        $contactData = [];
        
        if (filter_var($contactIdentifier, FILTER_VALIDATE_EMAIL)) {
            $contactData['email'] = $contactIdentifier;
            $contactData['name'] = $contactName ?: $contactIdentifier;
        } else {
            $formattedPhone = self::normalizePhoneNumber($contactIdentifier);
            $contactData['phone_number'] = $formattedPhone;
            $contactData['name'] = $contactName ?: $formattedPhone;
        }
        
        if (!empty($contactCustomAttributes)) {
            $contactData['custom_attributes'] = $contactCustomAttributes;
        }
        
        if (!empty($contactAdditionalAttributes)) {
            $contactData['additional_attributes'] = $contactAdditionalAttributes;
        }
        
        return $contactData;
    }

    /**
     * Normaliza número de telefone para garantir formato consistente
     */
    protected static function normalizePhoneNumber(string $phone): string
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        
        if (substr($cleanPhone, 0, 2) === '55' && strlen($cleanPhone) >= 12) {
            $phoneWithoutCountry = substr($cleanPhone, 2);
            return '+55' . self::normalizeBrazilianCellphone($phoneWithoutCountry);
        }
        
        if (strlen($cleanPhone) === 11) {
            return '+55' . self::normalizeBrazilianCellphone($cleanPhone);
        }
        
        if (strlen($cleanPhone) === 10) {
            return '+55' . self::normalizeBrazilianCellphone($cleanPhone);
        }
        
        try {
            return Phone::formatInternational($phone);
        } catch (\Exception $e) {
            return '+55' . $cleanPhone;
        }
    }

    /**
     * Normaliza número de celular brasileiro adicionando o 9 se necessário
     */
    protected static function normalizeBrazilianCellphone(string $phone): string
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($cleanPhone) === 11 && substr($cleanPhone, 2, 1) === '9') {
            return $cleanPhone;
        }
        
        if (strlen($cleanPhone) === 10) {
            $areaCode = substr($cleanPhone, 0, 2);
            $firstDigit = substr($cleanPhone, 2, 1);
            
            if (in_array($firstDigit, ['6', '7', '8', '9'])) {
                return $areaCode . '9' . substr($cleanPhone, 2);
            }
            
            return $cleanPhone;
        }
        
        if (strlen($cleanPhone) === 9) {
            return '11' . $cleanPhone;
        }
        
        if (strlen($cleanPhone) === 8) {
            $firstDigit = substr($cleanPhone, 0, 1);
            if (in_array($firstDigit, ['6', '7', '8', '9'])) {
                return '119' . $cleanPhone;
            }
        }
        
        return $cleanPhone;
    }

    protected static function getInboxById(Account $account, int $inboxId): ?array
    {
        $client = new Client();
        
        try {
            $response = $client->get($account->api_url . '/accounts/' . $account->account_id . '/inboxes/' . $inboxId, [
                'headers' => [
                    'api_access_token' => $account->token,
                ],
            ]);
            
            return json_decode($response->getBody(), true);
            
        } catch (GuzzleException $e) {
            return null;
        }
    }

    protected static function createContactPublicAPI(Account $account, array $inbox, array $contactData): ?array
    {
        $client = new Client();
        
        try {
            $response = $client->post($account->public_api_url . '/inboxes/' . $inbox['inbox_identifier'] . '/contacts', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Api-Key' => $account->token,
                ],
                'json' => $contactData
            ]);
            
            return json_decode($response->getBody(), true);
            
        } catch (GuzzleException $e) {
            return null;
        }
    }

    protected static function createContactPrivateAPI(Account $account, array $contactData, int $inboxId): ?array
    {
        $client = new Client();
        
        try {
            $contactData['inbox_id'] = $inboxId;
            
            $response = $client->post($account->api_url . '/accounts/' . $account->account_id . '/contacts', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'api_access_token' => $account->token,
                ],
                'json' => $contactData
            ]);
            
            return json_decode($response->getBody(), true);
            
        } catch (GuzzleException $e) {
            if ($e->getCode() === 422) {
                return null;
            }
            return null;
        }
    }

    protected static function updateContactPublicAPI(Account $account, array $inbox, array $contact, array $contactData): ?array
    {
        $client = new Client();
        
        try {
            $identifier = null;
            
            if (!empty($contactData['email'])) {
                $identifier = $contactData['email'];
            } elseif (!empty($contactData['phone_number'])) {
                $identifier = $contactData['phone_number'];
            } else {
                $identifier = $contact['identifier'] ?? $contact['id'] ?? null;
            }
            
            if (!$identifier) {
                return self::updateContactPrivateAPI($account, $contact, $contactData);
            }
            
            $updateData = [];
            
            if (!empty($contactData['name'])) {
                $updateData['name'] = $contactData['name'];
            }
            
            if (!empty($contactData['email'])) {
                $updateData['email'] = $contactData['email'];
            }
            
            if (!empty($contactData['phone_number'])) {
                $updateData['phone_number'] = $contactData['phone_number'];
            }
            
            if (!empty($contactData['custom_attributes'])) {
                $updateData['custom_attributes'] = $contactData['custom_attributes'];
            }
            
            if (!empty($contactData['additional_attributes'])) {
                $updateData['additional_attributes'] = $contactData['additional_attributes'];
            }
            
            $updateData['identifier'] = $identifier;
            
            if (count($updateData) <= 1) {
                return $contact;
            }
            
            $response = $client->patch($account->public_api_url . '/inboxes/' . $inbox['inbox_identifier'] . '/contacts/' . $identifier, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Api-Key' => $account->token,
                ],
                'json' => $updateData
            ]);
            
            if ($response->getStatusCode() === 200) {
                return array_merge($contact, $updateData);
            }
            
            return $contact;
            
        } catch (GuzzleException $e) {
            if ($e->getCode() === 404) {
                return self::updateContactPrivateAPI($account, $contact, $contactData);
            }
            
            return $contact;
        }
    }

    protected static function updateContactPrivateAPI(Account $account, array $contact, array $contactData): ?array
    {
        $client = new Client();
        
        try {
            $updateData = [];
            
            if (!empty($contactData['phone_number'])) {
                $updateData['phone_number'] = $contactData['phone_number'];
            }
            
            if (!empty($contactData['name'])) {
                $updateData['name'] = $contactData['name'];
            }
            
            if (!empty($contactData['email'])) {
                $updateData['email'] = $contactData['email'];
            }
            
            if (!empty($contactData['custom_attributes'])) {
                $updateData['custom_attributes'] = $contactData['custom_attributes'];
            }
            
            if (!empty($contactData['additional_attributes'])) {
                $updateData['additional_attributes'] = $contactData['additional_attributes'];
            }
            
            if (empty($updateData)) {
                return $contact;
            }
            
            $response = $client->put($account->api_url . '/accounts/' . $account->account_id . '/contacts/' . $contact['id'], [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'api_access_token' => $account->token,
                ],
                'json' => $updateData
            ]);
            
            if (in_array($response->getStatusCode(), [200, 204])) {
                return array_merge($contact, $updateData);
            }
            
            return $contact;
            
        } catch (GuzzleException $e) {
            return $contact;
        }
    }

    protected static function filterContact(Account $account, array $contactData): ?array
    {
        $client = new Client();
        
        try {
            if (isset($contactData['email'])) {
                $filters = [
                    [
                        'attribute_key' => 'email',
                        'filter_operator' => 'equal_to',
                        'values' => [$contactData['email']],
                        'query_operator' => null
                    ]
                ];
                
                $response = $client->post($account->api_url . '/accounts/' . $account->account_id . '/contacts/filter', [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'api_access_token' => $account->token,
                    ],
                    'json' => [
                        'payload' => $filters
                    ]
                ]);
                
                $responseData = json_decode($response->getBody(), true);
                
                if (isset($responseData['payload']) && count($responseData['payload']) > 0) {
                    foreach ($responseData['payload'] as $contact) {
                        if (isset($contact['email']) && $contact['email'] === $contactData['email']) {
                            return $contact;
                        }
                    }
                }
            }
            
            if (isset($contactData['phone_number'])) {
                $phoneVariations = self::generatePhoneVariations($contactData['phone_number']);
                
                foreach ($phoneVariations as $phoneVariation) {
                    $normalizedVariation = self::normalizePhoneNumber($phoneVariation);
                    
                    $filters = [
                        [
                            'attribute_key' => 'phone_number',
                            'filter_operator' => 'equal_to',
                            'values' => [$normalizedVariation],
                            'query_operator' => null
                        ]
                    ];
                    
                    $response = $client->post($account->api_url . '/accounts/' . $account->account_id . '/contacts/filter', [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'api_access_token' => $account->token,
                        ],
                        'json' => [
                            'payload' => $filters
                        ]
                    ]);
                    
                    $responseData = json_decode($response->getBody(), true);
                    
                    if (isset($responseData['payload']) && count($responseData['payload']) > 0) {
                        foreach ($responseData['payload'] as $contact) {
                            if (isset($contact['phone_number'])) {
                                $contactPhone = self::normalizePhoneNumber($contact['phone_number']);
                                $searchPhone = self::normalizePhoneNumber($contactData['phone_number']);
                                
                                if ($contactPhone === $searchPhone) {
                                    return $contact;
                                }
                            }
                        }
                    }
                }
                
                return self::searchContactByPhone($account, $contactData['phone_number']);
            }
            
            return null;
            
        } catch (GuzzleException $e) {
            if (isset($contactData['phone_number'])) {
                return self::searchContactByPhone($account, $contactData['phone_number']);
            }
            return null;
        }
    }

    protected static function getOrCreateConversationForContact(Account $account, array $contact, array $inbox, array $conversationCustomAttributes = [], bool $forceNewConversation = false, int $conversationId = null): ?array
    {
        if ($conversationId !== null) {
            $conversation = self::getConversationById($account, $conversationId);
            if ($conversation) {
                if (!empty($conversationCustomAttributes)) {
                    $updatedConversation = self::updateConversation($account, $conversationId, $conversationCustomAttributes);
                    if ($updatedConversation) {
                        return $updatedConversation;
                    }
                }
                return $conversation;
            }
        }
        
        if ($forceNewConversation) {
            return self::createConversation($account, [
                'contact_id' => $contact['id'],
                'inbox_id' => $inbox['id'],
            ], $conversationCustomAttributes);
        }
        
        $conversations = self::getConversationsByInboxAndContact($account, $inbox, $contact);
        
        if (!empty($conversationCustomAttributes)) {
            foreach ($conversations as $conversation) {
                $existingAttrs = $conversation['custom_attributes'] ?? [];
                
                if (self::compareCustomAttributes($existingAttrs, $conversationCustomAttributes)) {
                    return $conversation;
                }
            }
            
            $allConversations = self::getAllConversationsByContact($account, $contact['id']);
            foreach ($allConversations as $conversation) {
                if ($conversation['inbox_id'] == $inbox['id']) {
                    $existingAttrs = $conversation['custom_attributes'] ?? [];
                    
                    if (self::compareCustomAttributes($existingAttrs, $conversationCustomAttributes)) {
                        return $conversation;
                    }
                }
            }
            
            return self::createConversation($account, [
                'contact_id' => $contact['id'],
                'inbox_id' => $inbox['id'],
            ], $conversationCustomAttributes);
        }
        
        if (!empty($conversations)) {
            return $conversations[0];
        }
        
        return self::createConversation($account, [
            'contact_id' => $contact['id'],
            'inbox_id' => $inbox['id'],
        ], []);
    }

    /**
     * Compara dois arrays de custom attributes para verificar se são iguais
     */
    protected static function compareCustomAttributes(array $existingAttrs, array $newAttrs): bool
    {
        // Se os arrays têm tamanhos diferentes, não são iguais
        if (count($existingAttrs) !== count($newAttrs)) {
            return false;
        }
        
        // Verificar se todos os valores dos novos atributos existem e são iguais nos existentes
        foreach ($newAttrs as $key => $value) {
            if (!array_key_exists($key, $existingAttrs) || $existingAttrs[$key] != $value) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Busca todas as conversas de um contato específico (sem filtro de inbox)
     */
    protected static function getAllConversationsByContact(Account $account, int $contactId): array
    {
        $client = new Client();
        
        try {
            $response = $client->get($account->api_url . '/accounts/' . $account->account_id . '/conversations', [
                'headers' => [
                    'api_access_token' => $account->token,
                ],
                'query' => [
                    'contact_id' => $contactId,
                    'sort' => 'latest',
                    'per_page' => 100
                ]
            ]);
            
            $responseData = json_decode($response->getBody(), true);
            
            return $responseData['data']['payload'] ?? [];
            
        } catch (GuzzleException $e) {
            return [];
        }
    }

    public static function getConversationsByInboxAndContact(Account $account, array $inbox, array $contact): array
    {
        $client = new Client();
        
        try {
            $response = $client->get($account->api_url . '/accounts/' . $account->account_id . '/conversations', [
                'headers' => [
                    'api_access_token' => $account->token,
                ],
                'query' => [
                    'inbox_id' => $inbox['id'],
                    'contact_id' => $contact['id'],
                    'sort' => 'latest',
                    'per_page' => 50
                ]
            ]);
            
            $responseData = json_decode($response->getBody(), true);
            
            return $responseData['data']['payload'] ?? [];
            
        } catch (GuzzleException $e) {
            return [];
        }
    }

    public static function createConversation(Account $account, array $conversationData, array $conversationCustomAttributes = []): ?array
    {
        $client = new Client();
        
        try {
            if (!empty($conversationCustomAttributes)) {
                $conversationData['custom_attributes'] = $conversationCustomAttributes;
            }
            
            $response = $client->post($account->api_url . '/accounts/' . $account->account_id . '/conversations', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'api_access_token' => $account->token,
                ],
                'json' => $conversationData
            ]);
            
            return json_decode($response->getBody(), true);
            
        } catch (GuzzleException $e) {
            return null;
        }
    }

    public static function testConnection(Account $account): bool
    {
        $client = new Client();
        
        try {
            $response = $client->get($account->api_url . '/accounts/' . $account->account_id, [
                'headers' => [
                    'api_access_token' => $account->token,
                ],
                'timeout' => 10,
            ]);
            
            $responseData = json_decode($response->getBody(), true);
            
            return isset($responseData['id']) && $responseData['id'] == $account->account_id;
            
        } catch (GuzzleException $e) {
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function getAccountInboxes(Account $account): ?array
    {
        $client = new Client();
        
        try {
            $response = $client->get($account->api_url . '/accounts/' . $account->account_id . '/inboxes', [
                'headers' => [
                    'api_access_token' => $account->token,
                ],
            ]);
            
            $responseData = json_decode($response->getBody(), true);
            
            return $responseData['payload'] ?? null;
            
        } catch (GuzzleException $e) {
            return null;
        }
    }

    /**
     * Centraliza a lógica de busca e criação de contatos
     */
    protected static function getOrCreateContact(Account $account, string $contactIdentifier, string $contactName, array $inbox, array $contactCustomAttributes = [], array $contactAdditionalAttributes = []): ?array
    {
        $contactData = self::prepareContactData($contactIdentifier, $contactName, $contactCustomAttributes, $contactAdditionalAttributes);
        
        $existingContact = self::filterContact($account, $contactData);
        
        $contact = null;
        
        if ($existingContact) {
            $contact = $existingContact;
        } else {
            if (isset($inbox['inbox_identifier'])) {
                $contact = self::createContactPublicAPI($account, $inbox, $contactData);
            } else {
                $contact = self::createContactPrivateAPI($account, $contactData, $inbox['id']);
            }
        }
        
        if ($contact && (!empty($contactCustomAttributes) || !empty($contactAdditionalAttributes) || !empty($contactName))) {
            $needsUpdate = false;
            
            if (!empty($contactCustomAttributes) || !empty($contactAdditionalAttributes)) {
                $needsUpdate = true;
            }
            
            if (isset($contactData['phone_number']) && isset($contact['phone_number'])) {
                $currentPhone = self::normalizePhoneNumber($contact['phone_number']);
                $newPhone = self::normalizePhoneNumber($contactData['phone_number']);
                
                if ($currentPhone !== $newPhone) {
                    $needsUpdate = true;
                }
            }
            
            if (!empty($contactName) && (!isset($contact['name']) || $contact['name'] !== $contactName)) {
                $needsUpdate = true;
            }
            
            if ($needsUpdate) {
                if (isset($inbox['inbox_identifier'])) {
                    $updatedContact = self::updateContactPublicAPI($account, $inbox, $contact, $contactData);
                    if ($updatedContact) {
                        $contact = $updatedContact;
                    }
                } else {
                    $updatedContact = self::updateContactPrivateAPI($account, $contact, $contactData);
                    if ($updatedContact) {
                        $contact = $updatedContact;
                    }
                }
            }
        }
        
        return $contact;
    }

    /**
     * Busca contatos com números similares (com/sem 9) e retorna possíveis duplicatas
     */
    public static function findSimilarContacts(Account $account, string $phoneNumber): array
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Gerar variações possíveis do número
        $variations = self::generatePhoneVariations($cleanPhone);
        
        $foundContacts = [];
        
        foreach ($variations as $variation) {
            $contactData = self::prepareContactData($variation, '');
            $contact = self::filterContact($account, $contactData);
            
            if ($contact) {
                $foundContacts[] = [
                    'phone' => $variation,
                    'normalized' => self::normalizePhoneNumber($variation),
                    'contact' => $contact
                ];
            }
        }
        
        return $foundContacts;
    }

    /**
     * Gera variações possíveis de um número brasileiro (com/sem 9)
     */
    protected static function generatePhoneVariations(string $cleanPhone): array
    {
        $originalPhone = preg_replace('/[^0-9]/', '', $cleanPhone);
        
        $variations = [$originalPhone];
        
        if (!str_starts_with($cleanPhone, '+')) {
            $variations[] = '+' . $originalPhone;
        }
        
        if (strlen($originalPhone) === 13 && substr($originalPhone, 0, 2) === '55') {
            $phoneWithoutCountry = substr($originalPhone, 2);
            $variations[] = $phoneWithoutCountry;
            $variations[] = '+55' . $phoneWithoutCountry;
            $variations[] = '+' . $originalPhone;
            
            if (substr($phoneWithoutCountry, 2, 1) === '9') {
                $withoutNine = substr($phoneWithoutCountry, 0, 2) . substr($phoneWithoutCountry, 3);
                $variations[] = $withoutNine;
                $variations[] = '55' . $withoutNine;
                $variations[] = '+55' . $withoutNine;
                $variations[] = '+' . '55' . $withoutNine;
            }
        }
        
        if (strlen($originalPhone) === 12 && substr($originalPhone, 0, 2) === '55') {
            $phoneWithoutCountry = substr($originalPhone, 2);
            $variations[] = $phoneWithoutCountry;
            $variations[] = '+55' . $phoneWithoutCountry;
            $variations[] = '+' . $originalPhone;
            
            $firstDigit = substr($phoneWithoutCountry, 2, 1);
            if (in_array($firstDigit, ['6', '7', '8', '9'])) {
                $areaCode = substr($phoneWithoutCountry, 0, 2);
                $withNine = $areaCode . '9' . substr($phoneWithoutCountry, 2);
                $variations[] = $withNine;
                $variations[] = '55' . $withNine;
                $variations[] = '+55' . $withNine;
                $variations[] = '+' . '55' . $withNine;
            }
        }
        
        if (strlen($originalPhone) === 11) {
            $variations[] = '55' . $originalPhone;
            $variations[] = '+55' . $originalPhone;
            $variations[] = '+' . '55' . $originalPhone;
            
            if (substr($originalPhone, 2, 1) === '9') {
                $withoutNine = substr($originalPhone, 0, 2) . substr($originalPhone, 3);
                $variations[] = $withoutNine;
                $variations[] = '55' . $withoutNine;
                $variations[] = '+55' . $withoutNine;
                $variations[] = '+' . '55' . $withoutNine;
            }
        }
        
        if (strlen($originalPhone) === 10) {
            $variations[] = '55' . $originalPhone;
            $variations[] = '+55' . $originalPhone;
            $variations[] = '+' . '55' . $originalPhone;
            
            $firstDigit = substr($originalPhone, 2, 1);
            if (in_array($firstDigit, ['6', '7', '8', '9'])) {
                $areaCode = substr($originalPhone, 0, 2);
                $withNine = $areaCode . '9' . substr($originalPhone, 2);
                $variations[] = $withNine;
                $variations[] = '55' . $withNine;
                $variations[] = '+55' . $withNine;
                $variations[] = '+' . '55' . $withNine;
            }
        }
        
        $normalizedVariations = [];
        foreach ($variations as $variation) {
            $normalizedVariations[] = self::normalizePhoneNumber($variation);
        }
        
        $allVariations = array_merge($variations, $normalizedVariations);
        
        $allVariations = array_filter(array_unique($allVariations), function($item) {
            return !empty($item);
        });
        
        return array_values($allVariations);
    }

    /**
     * Busca contato por telefone de forma mais ampla
     */
    public static function searchContactByPhone(Account $account, string $phoneNumber): ?array
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        $contacts = self::getAllContacts($account);
        
        foreach ($contacts as $contact) {
            if (isset($contact['phone_number'])) {
                $contactPhone = preg_replace('/[^0-9]/', '', $contact['phone_number']);
                
                $phoneEnd = substr($cleanPhone, -8);
                $contactPhoneEnd = substr($contactPhone, -8);
                
                if ($phoneEnd === $contactPhoneEnd && strlen($phoneEnd) === 8) {
                    return $contact;
                }
                
                $phoneEnd9 = substr($cleanPhone, -9);
                $contactPhoneEnd9 = substr($contactPhone, -9);
                
                if ($phoneEnd9 === $contactPhoneEnd9 && strlen($phoneEnd9) === 9) {
                    return $contact;
                }
                
                if ($cleanPhone === $contactPhone) {
                    return $contact;
                }
            }
        }
        
        return null;
    }

    public static function findContact(Account $account, string $contactIdentifier): ?array
    {
        $contactData = self::prepareContactData($contactIdentifier, '');
        return self::filterContact($account, $contactData);
    }

    public static function updateContactAttributes(Account $account, string $contactIdentifier, array $customAttributes = [], array $additionalAttributes = [], int $inboxId = null): ?array
    {
        if ($inboxId) {
            $inbox = self::getInboxById($account, $inboxId);
            if (!$inbox) {
                throw new \Exception('Inbox not found');
            }
        } else {
            $inboxes = self::getAccountInboxes($account);
            if (empty($inboxes)) {
                throw new \Exception('No inboxes available');
            }
            $inbox = $inboxes[0];
        }
        
        return self::getOrCreateContact($account, $contactIdentifier, '', $inbox, $customAttributes, $additionalAttributes);
    }

    /**
     * Lista todos os contatos para debug
     */
    public static function getAllContacts(Account $account, int $page = 1): array
    {
        $client = new Client();
        
        try {
            $response = $client->get($account->api_url . '/accounts/' . $account->account_id . '/contacts', [
                'headers' => [
                    'api_access_token' => $account->token,
                ],
                'query' => [
                    'page' => $page,
                    'per_page' => 50
                ]
            ]);
            
            $responseData = json_decode($response->getBody(), true);
            
            return $responseData['payload'] ?? [];
            
        } catch (GuzzleException $e) {
            return [];
        }
    }

    public static function updateConversation(Account $account, int $conversationId, array $conversationCustomAttributes = []): ?array
    {
        if (empty($conversationCustomAttributes)) {
            return null;
        }
        
        $client = new Client();
        
        try {
            $updateData = [
                'custom_attributes' => $conversationCustomAttributes,
                'additional_attributes' => $conversationCustomAttributes
            ];
            
            $response = $client->patch($account->api_url . '/accounts/' . $account->account_id . '/conversations/' . $conversationId, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'api_access_token' => $account->token,
                ],
                'json' => $updateData
            ]);
            
            if (in_array($response->getStatusCode(), [200, 204])) {
                return json_decode($response->getBody(), true);
            }
            
            return null;
            
        } catch (GuzzleException $e) {
            return null;
        }
    }

    public static function getInbox(Account $account, int $inboxId): ?array
    {
        return self::getInboxById($account, $inboxId);
    }

    public static function getConversationById(Account $account, int $conversationId): ?array
    {
        $client = new Client();
        
        try {
            $response = $client->get($account->api_url . '/accounts/' . $account->account_id . '/conversations/' . $conversationId, [
                'headers' => [
                    'api_access_token' => $account->token,
                ],
            ]);
            
            return json_decode($response->getBody(), true);
            
        } catch (GuzzleException $e) {
            return null;
        }
    }

    /**
     * Envia mensagem reutilizando conversa existente (sem custom attributes da conversa)
     */
    public static function sendTextToExistingConversation(Account $account, string $contactIdentifier, string $content, int $inboxId, string $contactName = '', array $contactCustomAttributes = [])
    {
        return self::sendText($account, $contactIdentifier, $content, $inboxId, $contactName, $contactCustomAttributes, [], false, null);
    }

    /**
     * Envia mensagem sempre criando nova conversa
     */
    public static function sendTextNewConversation(Account $account, string $contactIdentifier, string $content, int $inboxId, string $contactName = '', array $contactCustomAttributes = [], array $conversationCustomAttributes = [])
    {
        return self::sendText($account, $contactIdentifier, $content, $inboxId, $contactName, $contactCustomAttributes, $conversationCustomAttributes, true, null);
    }

    /**
     * Envia mensagem para conversa específica por ID
     */
    public static function sendTextToSpecificConversation(Account $account, int $conversationId, string $contactIdentifier, string $content, int $inboxId, string $contactName = '', array $contactCustomAttributes = [], array $conversationCustomAttributes = [])
    {
        return self::sendText($account, $contactIdentifier, $content, $inboxId, $contactName, $contactCustomAttributes, $conversationCustomAttributes, false, $conversationId);
    }

    /**
     * Busca todas as labels disponíveis na conta Chatwoot
     */
    public static function getAccountLabels(Account $account): array
    {
        $client = new Client();
        
        try {
            $response = $client->get($account->api_url . '/accounts/' . $account->account_id . '/labels', [
                'headers' => [
                    'api_access_token' => $account->token,
                ]
            ]);
            
            $responseData = json_decode($response->getBody(), true);
            
            return $responseData['payload'] ?? [];
            
        } catch (GuzzleException $e) {
            throw new \Exception('Failed to get labels from Chatwoot: ' . $e->getMessage());
        }
    }

    /**
     * Adiciona labels a uma conversa
     */
    public static function addLabelsToConversation(Account $account, int $conversationId, array $labels): bool
    {
        if (empty($labels)) {
            return true;
        }

        $client = new Client();
        
        try {
            $response = $client->post($account->api_url . '/accounts/' . $account->account_id . '/conversations/' . $conversationId . '/labels', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'api_access_token' => $account->token,
                ],
                'json' => [
                    'labels' => $labels
                ]
            ]);
            
            return $response->getStatusCode() === 200;
            
        } catch (GuzzleException $e) {
            throw new \Exception('Failed to add labels to conversation: ' . $e->getMessage());
        }
    }

    /**
     * Busca labels de uma conversa específica
     */
    public static function getConversationLabels(Account $account, int $conversationId): array
    {
        $client = new Client();
        
        try {
            $response = $client->get($account->api_url . '/accounts/' . $account->account_id . '/conversations/' . $conversationId . '/labels', [
                'headers' => [
                    'api_access_token' => $account->token,
                ]
            ]);
            
            $responseData = json_decode($response->getBody(), true);
            
            return $responseData['payload'] ?? [];
            
        } catch (GuzzleException $e) {
            throw new \Exception('Failed to get conversation labels: ' . $e->getMessage());
        }
    }
} 