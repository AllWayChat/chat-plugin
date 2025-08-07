<?php namespace Allway\Chat\Classes;

use Allway\Chat\Models\Account;
use Allway\Chat\Models\MessageLog;
use Allway\Chat\Classes\Helpers\Contact;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class AllwayService
{

    public static function sendText(Account $account, string $contactIdentifier, string $content, int $inboxId, string $contactName = '', array $contactCustomAttributes = [], array $conversationCustomAttributes = [], bool $forceNewConversation = false, int $conversationId = null, string $conversationStatus = null)
    {
        if (!Contact::validateIdentifier($contactIdentifier)) {
            Log::debug('AllwayService: Identificador do contato inválido: ' . $contactIdentifier);
            return;
        }
        
        return self::sendMessage($account, $contactIdentifier, $inboxId, $contactName, [
            'content' => $content,
            'message_type' => 'outgoing',
            'private' => false,
        ], $content, $contactCustomAttributes, $conversationCustomAttributes, $forceNewConversation, $conversationId, $conversationStatus);
    }

    public static function sendImage(Account $account, string $contactIdentifier, string $imageUrl, int $inboxId, string $contactName = '', string $caption = '', array $contactCustomAttributes = [], array $conversationCustomAttributes = [], bool $forceNewConversation = false, int $conversationId = null, string $conversationStatus = null)
    {
        if (!Contact::validateIdentifier($contactIdentifier)) {
            Log::debug('AllwayService: Identificador do contato inválido: ' . $contactIdentifier);
            return;
        }
        
        if (empty($imageUrl)) {
            throw new \Exception('URL da imagem é obrigatória');
        }

        return self::sendAttachment($account, $contactIdentifier, $inboxId, $contactName, $imageUrl, $caption ?: 'Imagem', basename($imageUrl), $contactCustomAttributes, $conversationCustomAttributes, $forceNewConversation, $conversationId, $conversationStatus);
    }

    public static function sendDocument(Account $account, string $contactIdentifier, string $documentUrl, int $inboxId, string $contactName = '', string $caption = '', string $filename = '', array $contactCustomAttributes = [], array $conversationCustomAttributes = [], bool $forceNewConversation = false, int $conversationId = null, string $conversationStatus = null)
    {
        if (!Contact::validateIdentifier($contactIdentifier)) {
            Log::debug('AllwayService: Identificador do contato inválido: ' . $contactIdentifier);
            return;
        }
        
        if (empty($documentUrl)) {
            throw new \Exception('URL do documento é obrigatória');
        }

        return self::sendAttachment($account, $contactIdentifier, $inboxId, $contactName, $documentUrl, $caption ?: 'Arquivo', $filename ?: basename($documentUrl), $contactCustomAttributes, $conversationCustomAttributes, $forceNewConversation, $conversationId, $conversationStatus);
    }

    protected static function sendAttachment(Account $account, string $contactIdentifier, int $inboxId, string $contactName, string $fileUrl, string $content, string $filename, array $contactCustomAttributes = [], array $conversationCustomAttributes = [], bool $forceNewConversation = false, int $conversationId = null, string $conversationStatus = null)
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
            
            $conversation = self::getOrCreateConversationForContact($account, $contact, $inbox, $conversationCustomAttributes, $forceNewConversation, $conversationId, $conversationStatus);
            
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

    protected static function sendMessage(Account $account, string $contactIdentifier, int $inboxId, string $contactName, array $messageData, string $logContent, array $contactCustomAttributes = [], array $conversationCustomAttributes = [], bool $forceNewConversation = false, int $conversationId = null, string $conversationStatus = null)
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
            
            $conversation = self::getOrCreateConversationForContact($account, $contact, $inbox, $conversationCustomAttributes, $forceNewConversation, $conversationId, $conversationStatus);
            
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
            if (!Contact::validateEmail($contactIdentifier)) {
                throw new \Exception('Email inválido: ' . $contactIdentifier);
            }
            $contactData['email'] = $contactIdentifier;
            $contactData['name'] = $contactName ?: $contactIdentifier;
        } else {
            if (!Contact::validatePhone($contactIdentifier)) {
                throw new \Exception('Número de telefone inválido: ' . $contactIdentifier);
            }
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
            return Contact::normalizeIdentifier($phone);
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

    protected static function getOrCreateConversationForContact(Account $account, array $contact, array $inbox, array $conversationCustomAttributes = [], bool $forceNewConversation = false, int $conversationId = null, string $conversationStatus = null): ?array
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
            ], $conversationCustomAttributes, $conversationStatus);
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
            ], $conversationCustomAttributes, $conversationStatus);
        }
        
        if (!empty($conversations)) {
            return $conversations[0];
        }
        
        return self::createConversation($account, [
            'contact_id' => $contact['id'],
            'inbox_id' => $inbox['id'],
        ], [], $conversationStatus);
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

    public static function createConversation(Account $account, array $conversationData, array $conversationCustomAttributes = [], string $conversationStatus = null): ?array
    {
        $client = new Client();
        
        try {
            if (!empty($conversationCustomAttributes)) {
                $conversationData['custom_attributes'] = $conversationCustomAttributes;
            }
            
            // Adicionar status na criação da conversa se especificado
            if (!empty($conversationStatus) && in_array($conversationStatus, ['open', 'resolved', 'pending'])) {
                $conversationData['status'] = $conversationStatus;
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
        if (!Contact::validateIdentifier($contactIdentifier)) {
            Log::debug('AllwayService: Identificador do contato inválido: ' . $contactIdentifier);
            return [];
        }
        
        $contactData = self::prepareContactData($contactIdentifier, '');
        return self::filterContact($account, $contactData);
    }

    public static function updateContactAttributes(Account $account, string $contactIdentifier, array $customAttributes = [], array $additionalAttributes = [], int $inboxId = null): ?array
    {
        if (!Contact::validateIdentifier($contactIdentifier)) {
            Log::debug('AllwayService: Identificador do contato inválido: ' . $contactIdentifier);
            return [];
        }
        
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
        if (!Contact::validateIdentifier($contactIdentifier)) {
            Log::debug('AllwayService: Identificador do contato inválido: ' . $contactIdentifier);
            return;
        }
        
        return self::sendText($account, $contactIdentifier, $content, $inboxId, $contactName, $contactCustomAttributes, [], false, null);
    }

    /**
     * Envia mensagem sempre criando nova conversa
     */
    public static function sendTextNewConversation(Account $account, string $contactIdentifier, string $content, int $inboxId, string $contactName = '', array $contactCustomAttributes = [], array $conversationCustomAttributes = [])
    {
        if (!Contact::validateIdentifier($contactIdentifier)) {
            Log::debug('AllwayService: Identificador do contato inválido: ' . $contactIdentifier);
            return;
        }
        
        return self::sendText($account, $contactIdentifier, $content, $inboxId, $contactName, $contactCustomAttributes, $conversationCustomAttributes, true, null);
    }

    /**
     * Envia mensagem para conversa específica por ID
     */
    public static function sendTextToSpecificConversation(Account $account, int $conversationId, string $contactIdentifier, string $content, int $inboxId, string $contactName = '', array $contactCustomAttributes = [], array $conversationCustomAttributes = [])
    {
        if (!Contact::validateIdentifier($contactIdentifier)) {
            Log::debug('AllwayService: Identificador do contato inválido: ' . $contactIdentifier);
            return;
        }
        
        return self::sendText($account, $contactIdentifier, $content, $inboxId, $contactName, $contactCustomAttributes, $conversationCustomAttributes, false, $conversationId);
    }

    /**
     * Busca todas as labels disponíveis na conta Allway Chat
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
            throw new \Exception('Failed to get labels from Allway Chat: ' . $e->getMessage());
        }
    }

    /**
     * Adiciona labels a uma conversa
     * @param Account $account
     * @param int $conversationId
     * @param array $labels
     * @param string $mode 'replace' (padrão) ou 'append'
     * @return bool
     */
    public static function addLabelsToConversation(Account $account, int $conversationId, array $labels, string $mode = 'replace'): bool
    {
        if (empty($labels)) {
            return true;
        }

        $finalLabels = $labels;
        
        // Se o modo for 'append', primeiro buscar as labels atuais
        if ($mode === 'append') {
            try {
                $currentLabels = self::getConversationLabels($account, $conversationId);
                
                // A API retorna um array simples de strings, então usamos diretamente
                // Combinar labels atuais com as novas, removendo duplicatas
                $finalLabels = array_unique(array_merge($currentLabels, $labels));
                
            } catch (\Exception $e) {
                // Se não conseguir buscar as labels atuais, continua apenas com as novas
                \Log::warning('Não foi possível buscar labels atuais da conversa ' . $conversationId . ': ' . $e->getMessage());
            }
        }

        $client = new Client();
        
        try {
            $response = $client->post($account->api_url . '/accounts/' . $account->account_id . '/conversations/' . $conversationId . '/labels', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'api_access_token' => $account->token,
                ],
                'json' => [
                    'labels' => $finalLabels
                ]
            ]);
            
            return $response->getStatusCode() === 200;
            
        } catch (GuzzleException $e) {
            throw new \Exception('Failed to add labels to conversation: ' . $e->getMessage());
        }
    }

    /**
     * Busca labels de uma conversa específica
     * @param Account $account
     * @param int $conversationId
     * @return array Array de strings com os nomes das labels
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

    /**
     * Busca custom attributes da conta do Allway Chat
     */
    public static function getAccountCustomAttributes(Account $account, int $attributeModel = 0): array
    {
        $client = new Client();
        
        try {
            $response = $client->get($account->api_url . '/accounts/' . $account->account_id . '/custom_attribute_definitions', [
                'headers' => [
                    'api_access_token' => $account->token,
                ],
                'query' => [
                    'attribute_model' => $attributeModel // 0 = conversation, 1 = contact
                ]
            ]);
            
            $responseData = json_decode($response->getBody(), true);
            
            return $responseData ?? [];
            
        } catch (GuzzleException $e) {
            \Log::error('Erro ao buscar custom attributes do Allway Chat: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Altera o status de uma conversa
     * @param Account $account
     * @param int $conversationId
     * @param string $status open|resolved|pending
     * @return bool
     */
    public static function updateConversationStatus(Account $account, int $conversationId, string $status): bool
    {
        if (!in_array($status, ['open', 'resolved', 'pending'])) {
            throw new \Exception('Status inválido. Use: open, resolved ou pending');
        }

        $client = new Client();
        
        try {
            $response = $client->post($account->api_url . '/accounts/' . $account->account_id . '/conversations/' . $conversationId . '/toggle_status', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'api_access_token' => $account->token,
                ],
                'json' => [
                    'status' => $status
                ]
            ]);
            
            return $response->getStatusCode() === 200;
            
        } catch (GuzzleException $e) {
            \Log::error('Erro ao alterar status da conversa: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Busca estatísticas de conversas na API do Chatwoot
     * @param Account $account
     * @param string $period hoje|ontem|7dias|30dias|este_mes|mes_passado|esta_semana|semana_passada
     * @param array $inboxIds Array de IDs dos inboxes (opcional)
     * @param array $labels Array de labels para filtrar (opcional)
     * @return array
     */
    public static function getConversationsStats(Account $account, string $period = '30dias', array $inboxIds = [], array $labels = [], string $labelsMode = 'sum'): array
    {
        $client = new Client();
        
        try {
            [$dateStart, $dateEnd] = self::getPeriodDates($period);
            $sinceTimestamp = strtotime($dateStart);
            $untilTimestamp = strtotime($dateEnd);
            
            $apiUrl = str_replace('/api/v1', '/api/v2', $account->api_url);
            
            // Se há filtro por labels, usar type=label para cada label
            if (!empty($labels)) {
                // Buscar IDs das labels pelo nome
                $labelIds = self::getLabelIdsByNames($account, $labels);
                
                if ($labelsMode === 'intersect' && count($labelIds) > 1) {
                    // Modo interseção: buscar conversas que têm TODAS as labels
                    $totalConversations = self::getConversationsWithAllLabels($account, $labelIds, $sinceTimestamp, $untilTimestamp);
                } else {
                    // Modo soma: somar conversas de cada label separadamente (comportamento original)
                    $totalConversations = 0;
                    
                    foreach ($labelIds as $labelId) {
                        $params = [
                            'metric' => 'conversations_count',
                            'type' => 'label',
                            'id' => $labelId,
                            'since' => $sinceTimestamp,
                            'until' => $untilTimestamp
                        ];
                        
                        $response = $client->get($apiUrl . '/accounts/' . $account->account_id . '/reports', [
                            'headers' => [
                                'api_access_token' => $account->token,
                            ],
                            'query' => $params
                        ]);
                        
                        $responseData = json_decode($response->getBody(), true);
                        
                        if (is_array($responseData)) {
                            foreach ($responseData as $dataPoint) {
                                $totalConversations += (int) ($dataPoint['value'] ?? 0);
                            }
                        }
                    }
                }
            } else {
                // Sem filtro por labels, usar type=account
                $params = [
                    'metric' => 'conversations_count',
                    'type' => 'account',
                    'since' => $sinceTimestamp,
                    'until' => $untilTimestamp
                ];
                
                $response = $client->get($apiUrl . '/accounts/' . $account->account_id . '/reports', [
                    'headers' => [
                        'api_access_token' => $account->token,
                    ],
                    'query' => $params
                ]);
                
                $responseData = json_decode($response->getBody(), true);
                
                $totalConversations = 0;
                if (is_array($responseData)) {
                    foreach ($responseData as $dataPoint) {
                        $totalConversations += (int) ($dataPoint['value'] ?? 0);
                    }
                }
            }
            
            return [
                'total_conversations' => $totalConversations,
                'open_conversations' => $totalConversations,
                'resolved_conversations' => 0,
                'pending_conversations' => 0
            ];
            
        } catch (GuzzleException $e) {
            \Log::error('Erro ao buscar estatísticas de conversas: ' . $e->getMessage());
            return [
                'total_conversations' => 0,
                'open_conversations' => 0,
                'resolved_conversations' => 0,
                'pending_conversations' => 0
            ];
        }
    }

    /**
     * Busca estatísticas de mensagens na API do Chatwoot
     * @param Account $account
     * @param string $period
     * @param array $inboxIds
     * @return array
     */
    public static function getMessagesStats(Account $account, string $period = '30dias', array $inboxIds = []): array
    {
        $client = new Client();
        
        try {
            [$dateStart, $dateEnd] = self::getPeriodDates($period);
            $sinceTimestamp = strtotime($dateStart);
            $untilTimestamp = strtotime($dateEnd);
            
            $apiUrl = str_replace('/api/v1', '/api/v2', $account->api_url);
            
            // Buscar incoming_messages_count
            $paramsIncoming = [
                'metric' => 'incoming_messages_count',
                'type' => 'account',
                'since' => $sinceTimestamp,
                'until' => $untilTimestamp
            ];
            
            $responseIncoming = $client->get($apiUrl . '/accounts/' . $account->account_id . '/reports', [
                'headers' => [
                    'api_access_token' => $account->token,
                ],
                'query' => $paramsIncoming
            ]);
            
            // Buscar outgoing_messages_count
            $paramsOutgoing = [
                'metric' => 'outgoing_messages_count',
                'type' => 'account',
                'since' => $sinceTimestamp,
                'until' => $untilTimestamp
            ];
            
            $responseOutgoing = $client->get($apiUrl . '/accounts/' . $account->account_id . '/reports', [
                'headers' => [
                    'api_access_token' => $account->token,
                ],
                'query' => $paramsOutgoing
            ]);
            
            $incomingData = json_decode($responseIncoming->getBody(), true);
            $outgoingData = json_decode($responseOutgoing->getBody(), true);
            
            // Somar valores
            $incomingMessages = 0;
            if (is_array($incomingData)) {
                foreach ($incomingData as $dataPoint) {
                    $incomingMessages += (int) ($dataPoint['value'] ?? 0);
                }
            }
            
            $outgoingMessages = 0;
            if (is_array($outgoingData)) {
                foreach ($outgoingData as $dataPoint) {
                    $outgoingMessages += (int) ($dataPoint['value'] ?? 0);
                }
            }
            
            return [
                'total_messages' => $incomingMessages + $outgoingMessages,
                'outgoing_messages' => $outgoingMessages,
                'incoming_messages' => $incomingMessages
            ];
            
        } catch (GuzzleException $e) {
            \Log::error('Erro ao buscar estatísticas de mensagens: ' . $e->getMessage());
            return [
                'total_messages' => 0,
                'outgoing_messages' => 0,
                'incoming_messages' => 0
            ];
        }
    }

    /**
     * Busca IDs das labels pelo nome usando cache
     * @param Account $account
     * @param array $labelNames
     * @return array
     */
    private static function getLabelIdsByNames(Account $account, array $labelNames): array
    {
        $labelsMap = self::getCachedLabelsMap($account);
        $labelIds = [];
        
        foreach ($labelNames as $labelName) {
            // Tentar buscar pelo nome exato primeiro
            if (isset($labelsMap[$labelName])) {
                $labelIds[] = $labelsMap[$labelName];
                continue;
            }
            
            // Tentar buscar pelo slug
            $slug = self::createLabelSlug($labelName);
            if (isset($labelsMap[$slug])) {
                $labelIds[] = $labelsMap[$slug];
                continue;
            }
            
            // Busca case-insensitive por último
            foreach ($labelsMap as $cachedName => $cachedId) {
                if (strtolower($cachedName) === strtolower($labelName)) {
                    $labelIds[] = $cachedId;
                    break;
                }
            }
        }
        
        return $labelIds;
    }

    /**
     * Busca e cacheia o mapeamento de labels
     * @param Account $account
     * @return array
     */
    private static function getCachedLabelsMap(Account $account): array
    {
        $cacheKey = "allway_chat_labels_map_account_{$account->account_id}";
        
        return \Cache::remember($cacheKey, 3600, function () use ($account) {
            return self::fetchLabelsFromApi($account);
        });
    }

    /**
     * Busca labels da API do Chatwoot
     * @param Account $account
     * @return array
     */
    private static function fetchLabelsFromApi(Account $account): array
    {
        $client = new Client();
        $labelsMap = [];
        
        try {
            $response = $client->get($account->api_url . '/accounts/' . $account->account_id . '/labels', [
                'headers' => [
                    'api_access_token' => $account->token,
                ]
            ]);
            
            $responseData = json_decode($response->getBody(), true);
            $allLabels = $responseData['payload'] ?? [];
            
            foreach ($allLabels as $label) {
                $labelTitle = $label['title'] ?? '';
                $labelId = $label['id'] ?? null;
                
                if ($labelTitle && $labelId) {
                    // Mapear por nome original
                    $labelsMap[$labelTitle] = $labelId;
                    
                    // Mapear por slug também
                    $slug = self::createLabelSlug($labelTitle);
                    if ($slug !== $labelTitle) {
                        $labelsMap[$slug] = $labelId;
                    }
                }
            }
            
        } catch (GuzzleException $e) {
            \Log::error('Erro ao buscar labels da API: ' . $e->getMessage());
        }
        
        return $labelsMap;
    }

    /**
     * Cria slug de uma label
     * @param string $labelName
     * @return string
     */
    private static function createLabelSlug(string $labelName): string
    {
        // Converter para minúsculas
        $slug = strtolower($labelName);
        
        // Remover acentos
        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug);
        
        // Substituir espaços e caracteres especiais por underscore
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug);
        
        // Remover underscores do início e fim
        $slug = trim($slug, '_');
        
        return $slug;
    }

    /**
     * Limpa o cache de labels para uma conta
     * @param Account $account
     * @return void
     */
    public static function clearLabelsCache(Account $account): void
    {
        $cacheKey = "allway_chat_labels_map_account_{$account->account_id}";
        \Cache::forget($cacheKey);
    }

    /**
     * Converte período em datas
     * @param string $period
     * @return array [dateStart, dateEnd]
     */
    private static function getPeriodDates(string $period): array
    {
        $now = now();
        $today = $now->copy();
        
        switch ($period) {
            case 'hoje':
                return [
                    $today->copy()->startOfDay()->toISOString(), 
                    $today->copy()->endOfDay()->toISOString()
                ];
            case 'ontem':
                $yesterday = $today->copy()->subDay();
                return [
                    $yesterday->copy()->startOfDay()->toISOString(), 
                    $yesterday->copy()->endOfDay()->toISOString()
                ];
            case '7dias':
                return [
                    $today->copy()->subDays(7)->startOfDay()->toISOString(), 
                    $today->copy()->endOfDay()->toISOString()
                ];
            case '30dias':
                return [
                    $today->copy()->subDays(30)->startOfDay()->toISOString(), 
                    $today->copy()->endOfDay()->toISOString()
                ];
            case 'este_mes':
                return [
                    $today->copy()->startOfMonth()->toISOString(), 
                    $today->copy()->endOfMonth()->toISOString()
                ];
            case 'mes_passado':
                $lastMonth = $today->copy()->subMonth();
                return [
                    $lastMonth->copy()->startOfMonth()->toISOString(), 
                    $lastMonth->copy()->endOfMonth()->toISOString()
                ];
            case 'esta_semana':
                return [
                    $today->copy()->startOfWeek()->toISOString(), 
                    $today->copy()->endOfWeek()->toISOString()
                ];
            case 'semana_passada':
                $lastWeek = $today->copy()->subWeek();
                return [
                    $lastWeek->copy()->startOfWeek()->toISOString(), 
                    $lastWeek->copy()->endOfWeek()->toISOString()
                ];
            default:
                return [
                    $today->copy()->subDays(30)->startOfDay()->toISOString(), 
                    $today->copy()->endOfDay()->toISOString()
                ];
        }
    }

    /**
     * Busca conversas que possuem TODAS as labels especificadas (interseção)
     * @param Account $account
     * @param array $labelIds
     * @param int $sinceTimestamp
     * @param int $untilTimestamp
     * @return int
     */
    private static function getConversationsWithAllLabels(Account $account, array $labelIds, int $sinceTimestamp, int $untilTimestamp): int
    {
        $client = new Client();
        $apiUrl = $account->api_url;
        
        try {
            // Buscar todas as conversas no período especificado
            $allConversations = [];
            $page = 1;
            $perPage = 25; // Limite da API
            
            do {
                $response = $client->get($apiUrl . '/accounts/' . $account->account_id . '/conversations', [
                    'headers' => [
                        'api_access_token' => $account->token,
                    ],
                    'query' => [
                        'page' => $page,
                        'per_page' => $perPage,
                        'created_after' => date('Y-m-d H:i:s', $sinceTimestamp),
                        'created_before' => date('Y-m-d H:i:s', $untilTimestamp),
                    ]
                ]);
                
                $data = json_decode($response->getBody(), true);
                $conversations = $data['data']['conversations'] ?? [];
                
                foreach ($conversations as $conversation) {
                    $allConversations[] = $conversation;
                }
                
                $page++;
                
                // Continuar se há mais páginas
            } while (count($conversations) == $perPage);
            
            // Filtrar conversas que têm TODAS as labels especificadas
            $conversationsWithAllLabels = [];
            
            foreach ($allConversations as $conversation) {
                $conversationLabels = collect($conversation['labels'] ?? [])->pluck('id')->toArray();
                
                // Verificar se a conversa tem todas as labels necessárias
                $hasAllLabels = true;
                foreach ($labelIds as $requiredLabelId) {
                    if (!in_array($requiredLabelId, $conversationLabels)) {
                        $hasAllLabels = false;
                        break;
                    }
                }
                
                if ($hasAllLabels) {
                    $conversationsWithAllLabels[] = $conversation['id'];
                }
            }
            
            return count($conversationsWithAllLabels);
            
        } catch (Exception $e) {
            \Log::error('Erro ao buscar conversas com todas as labels: ' . $e->getMessage());
            return 0;
        }
    }
} 