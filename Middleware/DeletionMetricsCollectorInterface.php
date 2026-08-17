<?php

declare(strict_types=1);

namespace Shared\Deletion\Middleware;

/**
 * Small adapter for the project's real metrics client.
 *
 * The deletion module should not know whether metrics go to Prometheus,
 * StatsD, Datadog or another backend, so the middleware depends only on
 * counter and histogram-like operations.
 */
interface DeletionMetricsCollectorInterface
{
    /**
     * Increments a counter metric.
     *
     * @param array<string, int|string|bool|null> $labels
     */
    public function increment(string $name, array $labels = [], int $value = 1): void;

    /**
     * Records a measured value, usually operation duration in seconds.
     *
     * @param array<string, int|string|bool|null> $labels
     */
    public function observe(string $name, float $value, array $labels = []): void;
}
