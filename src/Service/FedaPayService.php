<?php

namespace App\Service;

use App\Entity\Diocese;
use App\Entity\IntentionMesse;
use App\Entity\Paroisse;
use App\Enum\StatutPaiement;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FedaPayService
{
    private string $apiBaseUrl;

    public function __construct(
        #[Autowire('%env(FEDAPAY_SECRET_KEY)%')]
        private string $secretKey,
        #[Autowire('%env(FEDAPAY_PUBLIC_KEY)%')]
        private string $publicKey,
        #[Autowire('%env(FEDAPAY_ENVIRONMENT)%')]
        private string $environment,
        private UrlGeneratorInterface $urlGenerator,
        private HttpClientInterface $httpClient,
        #[Autowire('@monolog.logger.payment')]
        private LoggerInterface $logger
    ) {
        $this->apiBaseUrl = $this->environment === 'live'
            ? 'https://api.fedapay.com/v1'
            : 'https://sandbox-api.fedapay.com/v1';

        $this->logger->info('FedaPayService initialized', [
            'environment' => $this->environment,
            'apiBaseUrl' => $this->apiBaseUrl,
        ]);
    }

    public function createPayment(IntentionMesse $intention): array
    {
        $diocese = $intention->getDiocese();
        $apiKey = $diocese?->getFedapaySecretKey() ?? $this->secretKey;

        $this->logger->info('Creating payment for intention', [
            'intentionId' => $intention->getId(),
            'numeroReference' => $intention->getNumeroReference(),
            'montant' => $intention->getMontantPaye(),
            'dioceseId' => $diocese?->getId(),
            'usingDioceseKey' => $diocese?->getFedapaySecretKey() !== null,
        ]);

        $callbackUrl = $this->urlGenerator->generate(
            'api_fedapay_callback',
            ['intentionId' => $intention->getId()],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $webhookUrl = $this->urlGenerator->generate(
            'api_fedapay_webhook',
            [],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        try {
            $requestData = [
                'description' => sprintf(
                    'Intention de messe - %s - %s',
                    $intention->getBeneficiaireDisplay(),
                    $intention->getTypeIntentionLabel()
                ),
                'amount' => (int) ((float) $intention->getMontantPaye()),
                'currency' => ['iso' => 'XOF'],
                'callback_url' => $callbackUrl,
                'customer' => $this->buildCustomerData($intention),
                'metadata' => [
                    'intention_id' => $intention->getId(),
                    'numero_reference' => $intention->getNumeroReference(),
                    'paroisse_id' => $intention->getParoisse()?->getId(),
                    'diocese_id' => $diocese?->getId(),
                ],
            ];

            $this->logger->debug('FedaPay transaction request', [
                'url' => $this->apiBaseUrl . '/transactions',
                'data' => $requestData,
            ]);

            $response = $this->httpClient->request('POST', $this->apiBaseUrl . '/transactions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
            ]);

            $data = $response->toArray();
            $transactionId = $data['v1/transaction']['id'] ?? null;

            $this->logger->info('FedaPay transaction created', [
                'transactionId' => $transactionId,
                'intentionId' => $intention->getId(),
                'status' => $data['v1/transaction']['status'] ?? 'unknown',
            ]);

            $tokenResponse = $this->httpClient->request('POST', $this->apiBaseUrl . '/transactions/' . $transactionId . '/token', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                ],
            ]);

            $tokenData = $tokenResponse->toArray();

            $this->logger->info('FedaPay payment token generated', [
                'transactionId' => $transactionId,
                'hasPaymentUrl' => isset($tokenData['url']),
            ]);

            return [
                'transactionId' => (string) $transactionId,
                'paymentUrl' => $tokenData['url'] ?? null,
                'token' => $tokenData['token'] ?? null,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('FedaPay payment creation failed', [
                'intentionId' => $intention->getId(),
                'error' => $e->getMessage(),
                'errorClass' => get_class($e),
                'trace' => array_slice($e->getTrace(), 0, 3),
            ]);
            throw $e;
        }
    }

    private function buildCustomerData(IntentionMesse $intention): array
    {
        $customer = [
            'firstname' => $intention->getNomDemandeur(),
        ];

        if ($intention->getEmail()) {
            $customer['email'] = $intention->getEmail();
        }

        if ($intention->getTelephone()) {
            $phone = preg_replace('/[^0-9]/', '', $intention->getTelephone());
            if (str_starts_with($phone, '229')) {
                $phone = substr($phone, 3);
            }
            $customer['phone_number'] = [
                'number' => $phone,
                'country' => 'bj',
            ];
        }

        // FedaPay requiert au moins un email
        if (!isset($customer['email'])) {
            $customer['email'] = 'fidele@amissa.bj';
        }

        $this->logger->debug('Built customer data', [
            'nomDemandeur' => $intention->getNomDemandeur(),
            'hasEmail' => isset($customer['email']) && $customer['email'] !== 'fidele@amissa.bj',
            'hasPhone' => isset($customer['phone_number']),
        ]);

        return $customer;
    }

    public function verifyWebhook(string $signature, string $payload): bool
    {
        $expectedSignature = hash_hmac('sha256', $payload, $this->secretKey);
        $isValid = hash_equals($expectedSignature, $signature);

        $this->logger->debug('Webhook signature verification', [
            'isValid' => $isValid,
            'payloadLength' => strlen($payload),
        ]);

        return $isValid;
    }

    public function processWebhookEvent(array $data): array
    {
        $eventType = $data['name'] ?? null;
        $entity = $data['entity'] ?? [];

        $transactionId = $entity['id'] ?? null;
        $status = $entity['status'] ?? null;

        $this->logger->info('Processing webhook event', [
            'eventType' => $eventType,
            'transactionId' => $transactionId,
            'status' => $status,
        ]);

        $statutPaiement = match($status) {
            'approved' => StatutPaiement::PAYE,
            'declined', 'canceled', 'refunded' => StatutPaiement::ECHOUE,
            default => StatutPaiement::EN_ATTENTE,
        };

        $this->logger->info('Webhook event processed', [
            'transactionId' => $transactionId,
            'fedapayStatus' => $status,
            'mappedStatus' => $statutPaiement->value,
            'metadata' => $entity['metadata'] ?? [],
        ]);

        return [
            'eventType' => $eventType,
            'transactionId' => (string) $transactionId,
            'status' => $status,
            'statutPaiement' => $statutPaiement,
            'metadata' => $entity['metadata'] ?? [],
        ];
    }

    public function createPayout(Paroisse $paroisse, float $amount, array $intentionIds = []): array
    {
        $diocese = $paroisse->getDiocese();
        $apiKey = $diocese?->getFedapaySecretKey() ?? $this->secretKey;

        $this->logger->info('Creating payout for paroisse', [
            'paroisseId' => $paroisse->getId(),
            'paroisseName' => $paroisse->getNom(),
            'amount' => $amount,
            'intentionCount' => count($intentionIds),
        ]);

        $phoneNumber = $paroisse->getNumeroMobileMoney();
        if (!$phoneNumber) {
            $this->logger->error('Payout failed: no mobile money number', [
                'paroisseId' => $paroisse->getId(),
            ]);
            throw new \InvalidArgumentException('La paroisse n\'a pas de numéro Mobile Money configuré');
        }

        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        if (str_starts_with($phoneNumber, '229')) {
            $phoneNumber = substr($phoneNumber, 3);
        }

        try {
            $response = $this->httpClient->request('POST', $this->apiBaseUrl . '/payouts', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'amount' => (int) $amount,
                    'currency' => ['iso' => 'XOF'],
                    'mode' => 'mtn',
                    'customer' => [
                        'phone_number' => [
                            'number' => $phoneNumber,
                            'country' => 'bj',
                        ],
                    ],
                    'metadata' => [
                        'paroisse_id' => $paroisse->getId(),
                        'paroisse_nom' => $paroisse->getNom(),
                        'intention_ids' => $intentionIds,
                    ],
                ],
            ]);

            $data = $response->toArray();
            $payoutId = $data['v1/payout']['id'] ?? null;

            $this->logger->info('Payout created, starting transfer', [
                'payoutId' => $payoutId,
                'paroisseId' => $paroisse->getId(),
            ]);

            $this->httpClient->request('PUT', $this->apiBaseUrl . '/payouts/' . $payoutId . '/start', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                ],
            ]);

            $this->logger->info('Payout transfer started', [
                'payoutId' => $payoutId,
                'reference' => $data['v1/payout']['reference'] ?? null,
                'status' => $data['v1/payout']['status'] ?? null,
            ]);

            return [
                'payoutId' => (string) $payoutId,
                'reference' => $data['v1/payout']['reference'] ?? null,
                'status' => $data['v1/payout']['status'] ?? null,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Payout creation failed', [
                'paroisseId' => $paroisse->getId(),
                'amount' => $amount,
                'error' => $e->getMessage(),
                'errorClass' => get_class($e),
            ]);
            throw $e;
        }
    }

    public function getTransaction(string $transactionId): array
    {
        $this->logger->debug('Fetching transaction details', [
            'transactionId' => $transactionId,
        ]);

        try {
            $response = $this->httpClient->request('GET', $this->apiBaseUrl . '/transactions/' . $transactionId, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->secretKey,
                ],
            ]);

            $data = $response->toArray();

            $this->logger->debug('Transaction details fetched', [
                'transactionId' => $transactionId,
                'status' => $data['v1/transaction']['status'] ?? 'unknown',
            ]);

            return $data;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to fetch transaction', [
                'transactionId' => $transactionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
