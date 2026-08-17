<?php

declare(strict_types=1);

namespace Shared\Deletion\Middleware;

interface DeletionMetricsCollectorInterface
{
    /**
     * @param array<string, int|string|bool|null> $labels
     */
    public function increment(string $name, array $labels = [], int $value = 1): void;

    /**
     * @param array<string, int|string|bool|null> $labels
     */
    public function observe(string $name, float $value, array $labels = []): void;
}
