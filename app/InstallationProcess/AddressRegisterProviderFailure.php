<?php
declare(strict_types=1);
namespace FMonitor2\InstallationProcess;

final class AddressRegisterProviderFailure extends \RuntimeException
{
    public function __construct(
        public readonly string $reasonCode,
        public readonly bool $retryable,
        public readonly bool $outcomeUnknown,
        string $message = 'Address provider request failed',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
