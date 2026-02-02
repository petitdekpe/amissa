<?php

namespace App\Controller\Api;

use App\Enum\StatutPaiement;
use App\Repository\IntentionMesseRepository;
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
    #[Route('/fedapay', name: 'api_fedapay_webhook', methods: ['POST'])]
    public function handleWebhook(
        Request $request,
        FedaPayService $fedaPayService,
        IntentionMesseRepository $intentionRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $payload = $request->getContent();
        $signature = $request->headers->get('X-FedaPay-Signature', '');

        $data = json_decode($payload, true);

        if (!$data) {
            return new JsonResponse(['error' => 'Invalid payload'], Response::HTTP_BAD_REQUEST);
        }

        $result = $fedaPayService->processWebhookEvent($data);

        if (!$result['transactionId']) {
            return new JsonResponse(['error' => 'Transaction ID missing'], Response::HTTP_BAD_REQUEST);
        }

        $intention = $intentionRepository->findOneBy([
            'transactionFedapay' => $result['transactionId'],
        ]);

        if (!$intention) {
            return new JsonResponse(['error' => 'Intention not found'], Response::HTTP_NOT_FOUND);
        }

        $intention->setStatutPaiement($result['statutPaiement']);

        if ($result['statutPaiement'] === StatutPaiement::PAYE) {
            $occurrence = $intention->getOccurrence();
            if ($occurrence) {
                $occurrence->incrementNombreIntentions();
            }
        }

        $entityManager->flush();

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
        $intention = $intentionRepository->find($intentionId);

        if (!$intention) {
            return $this->redirectToRoute('public_home', [
                'error' => 'intention_not_found',
            ]);
        }

        return $this->redirectToRoute('public_intention_confirmation', [
            'id' => $intentionId,
        ]);
    }
}
