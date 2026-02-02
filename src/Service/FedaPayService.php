<?php

namespace App\Service;

use App\Entity\Diocese;
use App\Entity\IntentionMesse;
use App\Entity\Paroisse;
use App\Enum\StatutPaiement;
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
        private HttpClientInterface $httpClient
    ) {
        $this->apiBaseUrl = $this->environment === 'live'
            ? 'https://api.fedapay.com/v1'
            : 'https://sandbox-api.fedapay.com/v1';
    }

    public function createPayment(IntentionMesse $intention): array
    {
        $diocese = $intention->getDiocese();
        $apiKey = $diocese?->getFedapaySecretKey() ?? $this->secretKey;

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

        $response = $this->httpClient->request('POST', $this->apiBaseUrl . '/transactions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
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
            ],
        ]);

        $data = $response->toArray();
        $transactionId = $data['v1/transaction']['id'] ?? null;

        $tokenResponse = $this->httpClient->request('POST', $this->apiBaseUrl . '/transactions/' . $transactionId . '/token', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
            ],
        ]);

        $tokenData = $tokenResponse->toArray();

        return [
            'transactionId' => (string) $transactionId,
            'paymentUrl' => $tokenData['url'] ?? null,
            'token' => $tokenData['token'] ?? null,
        ];
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

        return $customer;
    }

    public function verifyWebhook(string $signature, string $payload): bool
    {
        $expectedSignature = hash_hmac('sha256', $payload, $this->secretKey);
        return hash_equals($expectedSignature, $signature);
    }

    public function processWebhookEvent(array $data): array
    {
        $eventType = $data['name'] ?? null;
        $entity = $data['entity'] ?? [];

        $transactionId = $entity['id'] ?? null;
        $status = $entity['status'] ?? null;

        $statutPaiement = match($status) {
            'approved' => StatutPaiement::PAYE,
            'declined', 'canceled', 'refunded' => StatutPaiement::ECHOUE,
            default => StatutPaiement::EN_ATTENTE,
        };

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

        $phoneNumber = $paroisse->getNumeroMobileMoney();
        if (!$phoneNumber) {
            throw new \InvalidArgumentException('La paroisse n\'a pas de numéro Mobile Money configuré');
        }

        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        if (str_starts_with($phoneNumber, '229')) {
            $phoneNumber = substr($phoneNumber, 3);
        }

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

        $this->httpClient->request('PUT', $this->apiBaseUrl . '/payouts/' . $payoutId . '/start', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
            ],
        ]);

        return [
            'payoutId' => (string) $payoutId,
            'reference' => $data['v1/payout']['reference'] ?? null,
            'status' => $data['v1/payout']['status'] ?? null,
        ];
    }

    public function getTransaction(string $transactionId): array
    {
        $response = $this->httpClient->request('GET', $this->apiBaseUrl . '/transactions/' . $transactionId, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->secretKey,
            ],
        ]);

        return $response->toArray();
    }
}
