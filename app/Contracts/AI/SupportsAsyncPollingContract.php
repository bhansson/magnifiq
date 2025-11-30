<?php

namespace App\Contracts\AI;

interface SupportsAsyncPollingContract
{
    /**
     * Check the status of a pending prediction.
     *
     * @return array{status: string, output: mixed, error: ?string}
     */
    public function getPredictionStatus(string $predictionId): array;

    /**
     * Get the maximum time to wait for a prediction to complete (in seconds).
     */
    public function getPollingTimeout(): int;

    /**
     * Get the interval between status checks (in seconds).
     */
    public function getPollingInterval(): float;
}
