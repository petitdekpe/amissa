<?php

namespace App\Controller\Api;

use App\Enum\StatutPaiement;
use App\Repository\IntentionMesseRepository;
use App\Service\ActivityLoggerService;
use App\Service\FedaPayService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/webhook')]
class FedaPayWebhookController extends AbstractController
{
    public function __construct(
        private ActivityLoggerService $activityLogger
    ) {
    }

    #[Route('/fedapay', name: 'api_fedapay_webhook', methods: ['POST'])]
    public function handleWebhook(
        Request $request,
        FedaPayService $fedaPayService,
        IntentionMesseRepository $intentionRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $payload = $request->getContent();
        $signature = $request->headers->get('X-FedaPay-Signature', '');

        $this->activityLogger->logWebhook(
            'webhook_received',
            'Webhook FedaPay reçu',
            'info',
            [
                'payloadLength' => strlen($payload),
                'hasSignature' => !empty($signature),
                'ip' => $request->getClientIp(),
            ]
        );

        $data = json_decode($payload, true);

        if (!$data) {
            $this->activityLogger->logWebhook(
                'webhook_invalid_payload',
                'Payload webhook invalide (JSON parse error)',
                'error',
                [
                    'payload' => substr($payload, 0, 500),
                    'jsonError' => json_last_error_msg(),
                ]
            );
            return new JsonResponse(['error' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        }

        $this->activityLogger->logWebhook(
            'webhook_parsed',
            'Payload webhook parsé',
            'debug',
            [
                'eventName' => $data['name'] ?? 'unknown',
                'entityId' => $data['entity']['id'] ?? null,
                'entityStatus' => $data['entity']['status'] ?? null,
            ]
        );

        $result = $fedaPayService->processWebhookEvent($data);

        if (!$result['transactionId']) {
            $this->activityLogger->logWebhook(
                'webhook_missing_transaction',
                'Transaction ID manquant dans le webhook',
                'error',
                ['data' => $data]
            );
            return new JsonResponse(['error' => 'Transaction ID missing'], Response::HTTP_BAD_REQUEST);
        }

        $intention = $intentionRepository->findOneBy([
            'transactionFedapay' => $result['transactionId'],
        ]);

        if (!$intention) {
            $this->activityLogger->logWebhook(
                'webhook_intention_not_found',
                sprintf('Intention non trouvée pour transaction: %s', $result['transactionId']),
                'warning',
                [
                    'transactionId' => $result['transactionId'],
                    'eventType' => $result['eventType'],
                ]
            );
            return new JsonResponse(['error' => 'Intention not found'], Response::HTTP_NOT_FOUND);
        }

        $oldStatut = $intention->getStatutPaiement();
        $intention->setStatutPaiement($result['statutPaiement']);

        $this->activityLogger->logWebhook(
            'webhook_status_update',
            sprintf('Statut paiement mis à jour: %s → %s', $oldStatut->value, $result['statutPaiement']->value),
            'info',
            [
                'intentionId' => $intention->getId(),
                'numeroReference' => $intention->getNumeroReference(),
                'transactionId' => $result['transactionId'],
                'oldStatus' => $oldStatut->value,
                'newStatus' => $result['statutPaiement']->value,
                'fedapayStatus' => $result['status'],
            ]
        );

        if ($result['statutPaiement'] === StatutPaiement::PAYE) {
            $occurrence = $intention->getOccurrence();
            if ($occurrence) {
                $occurrence->incrementNombreIntentions();

                $this->activityLogger->logWebhook(
                    'webhook_payment_confirmed',
                    sprintf('Paiement confirmé pour %s', $intention->getNumeroReference()),
                    'info',
                    [
                        'intentionId' => $intention->getId(),
                        'numeroReference' => $intention->getNumeroReference(),
                        'montant' => $intention->getMontantPaye(),
                        'occurrenceId' => $occurrence->getId(),
                        'nomDemandeur' => $intention->getNomDemandeur(),
                        'paroisseId' => $intention->getParoisse()?->getId(),
                    ]
                );
            }
        }

        $entityManager->flush();

        $this->activityLogger->logWebhook(
            'webhook_processed',
            sprintf('Webhook traité avec succès pour %s', $intention->getNumeroReference()),
            'info',
            [
                'intentionId' => $intention->getId(),
                'statutPaiement' => $result['statutPaiement']->value,
            ]
        );

        return new JsonResponse([
            'status' => 'processed',
            'intentionId' => $intention->getId(),
            'statutPaiement' => $result['statutPaiement']->value,
        ]);
    }

    #[Route('/fedapay/callback/{intentionId}', name: 'api_fedapay_callback', methods: ['GET'])]
    public function handleCallback(
        int $intentionId,
        IntentionMesseRepository $intentionRepository
    ): Response {
        $this->activityLogger->logWebhook(
            'callback_received',
            sprintf('Callback FedaPay reçu pour intention: %d', $intentionId),
            'info',
            ['intentionId' => $intentionId]
        );

        $intention = $intentionRepository->find($intentionId);

        if (!$intention) {
            $this->activityLogger->logWebhook(
                'callback_intention_not_found',
                sprintf('Intention non trouvée pour callback: %d', $intentionId),
                'warning',
                ['intentionId' => $intentionId]
            );
            return $this->redirectToRoute('public_home', [
                'error' => 'intention_not_found',
            ]);
        }

        $this->activityLogger->logWebhook(
            'callback_redirect',
            sprintf('Redirection vers confirmation pour %s', $intention->getNumeroReference()),
            'info',
            [
                'intentionId' => $intentionId,
                'numeroReference' => $intention->getNumeroReference(),
                'statutPaiement' => $intention->getStatutPaiement()->value,
            ]
        );

        return $this->redirectToRoute('public_intention_confirmation', [
            'id' => $intentionId,
        ]);
    }
}
