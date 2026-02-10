<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class ActivityLoggerService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security,
        private RequestStack $requestStack,
        private LoggerInterface $paymentLogger,
        private LoggerInterface $intentionLogger,
        private LoggerInterface $authLogger,
        private LoggerInterface $adminLogger,
        private LoggerInterface $webhookLogger,
        private LoggerInterface $logger
    ) {
    }

    public function log(
        string $category,
        string $action,
        string $message,
        string $level = ActivityLog::LEVEL_INFO,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $context = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $user = null
    ): ActivityLog {
        // Log to file
        $this->logToFile($category, $level, $message, $context);

        // Log to database
        return $this->logToDatabase(
            $category,
            $action,
            $message,
            $level,
            $entityType,
            $entityId,
            $context,
            $oldValues,
            $newValues,
            $user
        );
    }

    private function logToFile(string $category, string $level, string $message, ?array $context = null): void
    {
        $logger = match ($category) {
            ActivityLog::CATEGORY_PAYMENT => $this->paymentLogger,
            ActivityLog::CATEGORY_INTENTION => $this->intentionLogger,
            ActivityLog::CATEGORY_AUTH => $this->authLogger,
            ActivityLog::CATEGORY_ADMIN => $this->adminLogger,
            ActivityLog::CATEGORY_WEBHOOK => $this->webhookLogger,
            default => $this->logger,
        };

        $logContext = $context ?? [];

        match ($level) {
            ActivityLog::LEVEL_DEBUG => $logger->debug($message, $logContext),
            ActivityLog::LEVEL_INFO => $logger->info($message, $logContext),
            ActivityLog::LEVEL_WARNING => $logger->warning($message, $logContext),
            ActivityLog::LEVEL_ERROR => $logger->error($message, $logContext),
            ActivityLog::LEVEL_CRITICAL => $logger->critical($message, $logContext),
            default => $logger->info($message, $logContext),
        };
    }

    private function logToDatabase(
        string $category,
        string $action,
        string $message,
        string $level,
        ?string $entityType,
        ?int $entityId,
        ?array $context,
        ?array $oldValues,
        ?array $newValues,
        ?User $user
    ): ActivityLog {
        $activityLog = new ActivityLog();
        $activityLog->setCategory($category);
        $activityLog->setAction($action);
        $activityLog->setMessage($message);
        $activityLog->setLevel($level);
        $activityLog->setEntityType($entityType);
        $activityLog->setEntityId($entityId);
        $activityLog->setContext($context);
        $activityLog->setOldValues($oldValues);
        $activityLog->setNewValues($newValues);

        // Get current user if not provided
        $logUser = $user ?? $this->getCurrentUser();
        $activityLog->setUser($logUser);

        // Get request info
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $activityLog->setIpAddress($request->getClientIp());
            $activityLog->setUserAgent($request->headers->get('User-Agent'));
        }

        $this->entityManager->persist($activityLog);
        $this->entityManager->flush();

        return $activityLog;
    }

    private function getCurrentUser(): ?User
    {
        $user = $this->security->getUser();
        return $user instanceof User ? $user : null;
    }

    // Convenience methods for common log types

    public function logAuth(string $action, string $message, string $level = ActivityLog::LEVEL_INFO, ?array $context = null): ActivityLog
    {
        return $this->log(ActivityLog::CATEGORY_AUTH, $action, $message, $level, 'User', null, $context);
    }

    public function logPayment(
        string $action,
        string $message,
        ?int $intentionId = null,
        string $level = ActivityLog::LEVEL_INFO,
        ?array $context = null
    ): ActivityLog {
        return $this->log(ActivityLog::CATEGORY_PAYMENT, $action, $message, $level, 'IntentionMesse', $intentionId, $context);
    }

    public function logIntention(
        string $action,
        string $message,
        ?int $intentionId = null,
        string $level = ActivityLog::LEVEL_INFO,
        ?array $context = null
    ): ActivityLog {
        return $this->log(ActivityLog::CATEGORY_INTENTION, $action, $message, $level, 'IntentionMesse', $intentionId, $context);
    }

    public function logAdmin(
        string $action,
        string $message,
        ?string $entityType = null,
        ?int $entityId = null,
        string $level = ActivityLog::LEVEL_INFO,
        ?array $context = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): ActivityLog {
        return $this->log(
            ActivityLog::CATEGORY_ADMIN,
            $action,
            $message,
            $level,
            $entityType,
            $entityId,
            $context,
            $oldValues,
            $newValues
        );
    }

    public function logWebhook(
        string $action,
        string $message,
        string $level = ActivityLog::LEVEL_INFO,
        ?array $context = null
    ): ActivityLog {
        return $this->log(ActivityLog::CATEGORY_WEBHOOK, $action, $message, $level, null, null, $context);
    }

    public function logError(
        string $category,
        string $action,
        string $message,
        ?\Throwable $exception = null,
        ?array $context = null
    ): ActivityLog {
        $errorContext = $context ?? [];

        if ($exception) {
            $errorContext['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => array_slice($exception->getTrace(), 0, 5),
            ];
        }

        return $this->log($category, $action, $message, ActivityLog::LEVEL_ERROR, null, null, $errorContext);
    }
}
