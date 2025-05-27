<?php namespace Allway\Chat\Classes;

use Allway\Chat\Models\Account;
use Allway\Chat\Models\MessageLog;
use Allway\Chat\Classes\Helpers\Phone;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class AllwayService
{
    public static function sendText(Account $account, string $contactIdentifier, string $content, int $inboxId, string $contactName = '')
    {
        return self::sendMessage($account, $contactIdentifier, $inboxId, $contactName, [
            'content' => $content,
            'message_type' => 'outgoing',
            'private' => false,
        ], $content);
    }

    public static function sendImage(Account $account, string $contactIdentifier, string $imageUrl, int $inboxId, string $contactName = '', string $caption = '')
    {
        if (empty($imageUrl)) {
            throw new \Exception('URL da imagem é obrigatória');
        }

        return self::sendAttachment($account, $contactIdentifier, $inboxId, $contactName, $imageUrl, $caption ?: 'Imagem', basename($imageUrl));
    }

    public static function sendDocument(Account $account, string $contactIdentifier, string $documentUrl, int $inboxId, string $contactName = '', string $caption = '', string $filename = '')
    {
        if (empty($documentUrl)) {
            throw new \Exception('URL do documento é obrigatória');
        }

        return self::sendAttachment($account, $contactIdentifier, $inboxId, $contactName, $documentUrl, $caption ?: 'Arquivo', $filename ?: basename($documentUrl));
    }

    protected static function sendAttachment(Account $account, string $contactIdentifier, int $inboxId, string $contactName, string $fileUrl, string $content, string $filename)
    {
        $client = new Client();
        
        try {
            $inbox = self::getInboxById($account, $inboxId);
            if (!$inbox) {
                throw new \Exception('Inbox not found');
            }
            
            $contactData = self::prepareContactData($contactIdentifier, $contactName);
            
            if (isset($inbox['inbox_identifier'])) {
                $contact = self::createContactPublicAPI($account, $inbox, $contactData);
            } else {
                $contact = self::createContactPrivateAPI($account, $contactData, $inboxId);
            }
            
            if (!$contact) {
                throw new \Exception('Failed to create contact');
            }
            
            $conversation = self::getOrCreateConversationForContact($account, $contact, $inbox);
            
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

    protected static function sendMessage(Account $account, string $contactIdentifier, int $inboxId, string $contactName, array $messageData, string $logContent)
    {
        $client = new Client();
        
        try {
            $inbox = self::getInboxById($account, $inboxId);
            if (!$inbox) {
                throw new \Exception('Inbox not found');
            }
            
            $contactData = self::prepareContactData($contactIdentifier, $contactName);
            
            if (isset($inbox['inbox_identifier'])) {
                $contact = self::createContactPublicAPI($account, $inbox, $contactData);
            } else {
                $contact = self::createContactPrivateAPI($account, $contactData, $inboxId);
            }
            
            if (!$contact) {
                throw new \Exception('Failed to create contact');
            }
            
            $conversation = self::getOrCreateConversationForContact($account, $contact, $inbox);
            
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

    protected static function prepareContactData(string $contactIdentifier, string $contactName): array
    {
        $contactData = [];
        
        if (filter_var($contactIdentifier, FILTER_VALIDATE_EMAIL)) {
            $contactData['email'] = $contactIdentifier;
            $contactData['name'] = $contactName ?: $contactIdentifier;
        } else {
            $formattedPhone = Phone::formatInternational($contactIdentifier);
            $contactData['phone_number'] = $formattedPhone;
            $contactData['name'] = $contactName ?: $formattedPhone;
        }
        
        return $contactData;
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
            if ($e->getCode() === 422) {
                return self::filterContact($account, $contactData);
            }
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
                return self::filterContact($account, $contactData);
            }
            return null;
        }
    }

    protected static function filterContact(Account $account, array $contactData): ?array
    {
        $client = new Client();
        
        try {
            $filters = [];
            
            if (isset($contactData['email'])) {
                $filters[] = [
                    'attribute_key' => 'email',
                    'filter_operator' => 'equal_to',
                    'values' => [$contactData['email']],
                    'query_operator' => null
                ];
            }
            
            if (isset($contactData['phone_number'])) {
                $filters[] = [
                    'attribute_key' => 'phone_number',
                    'filter_operator' => 'equal_to',
                    'values' => [$contactData['phone_number']],
                    'query_operator' => null
                ];
            }
            
            if (empty($filters)) {
                return null;
            }
            
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
                return $responseData['payload'][0];
            }
            
            return null;
            
        } catch (GuzzleException $e) {
            return null;
        }
    }

    protected static function getOrCreateConversationForContact(Account $account, array $contact, array $inbox): ?array
    {
        $conversations = self::getConversationsByInboxAndContact($account, $inbox, $contact);
        
        if (!empty($conversations)) {
            return $conversations[0];
        }
        
        return self::createConversation($account, [
            'contact_id' => $contact['id'],
            'inbox_id' => $inbox['id'],
        ]);
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
                ]
            ]);
            
            $responseData = json_decode($response->getBody(), true);
            
            return $responseData['data']['payload'] ?? [];
            
        } catch (GuzzleException $e) {
            return [];
        }
    }

    public static function createConversation(Account $account, array $conversationData): ?array
    {
        $client = new Client();
        
        try {
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
} 