<?php

declare(strict_types=1);

namespace Jengo\Pesa\DTO;

final class DisbursementResponse
{
    public function __construct(
        public bool $successful,
        public string $originatorConversationId,
        public string $conversationId,
        public string $responseCode,
        public string $responseDescription,
        public ?string $reference = null,
        public array $raw = [],
    ) {
    }
}
