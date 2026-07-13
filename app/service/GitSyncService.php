<?php

declare(strict_types=1);

namespace Lightdocs\App\Service;

use Lightdocs\App\Model\GitSyncState;
use Throwable;

/**
 * Runs one optional GitHub push and records its audit outcome, so manual
 * Studio pushes and editor auto-sync share the same bookkeeping.
 */
final class GitSyncService
{
    public function __construct(
        private readonly GitHubSync $sync,
        private readonly GitSyncState $state,
    ) {
    }

    /** @return array<string,mixed> */
    public function run(string $token, string $repository, string $policy, string $message): array
    {
        try {
            $result = $this->sync->sync($token, $repository, $policy, $message);
            $this->state->record($repository, $policy, $result['state'], $message, $result);
            return $result;
        } catch (Throwable $exception) {
            $this->state->record($repository, $policy, 'pending', $message, null, $exception->getMessage());
            throw $exception;
        }
    }
}
