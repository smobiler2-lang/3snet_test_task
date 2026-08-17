<?php

declare(strict_types=1);

namespace Shared\Deletion\Middleware;

final class MetricsDeletionMiddleware implements DeletionMiddlewareInterface
{
    /** @var array<int, float> */
    private array $rootStartedAt = [];

    /** @var array<string, float> */
    private array $childrenStartedAt = [];

    /** @var array<string, float> */
    private array $detachStartedAt = [];

    /**
     * @param list<class-string>|null $supportedRootClasses
     */
    public function __construct(
        private readonly DeletionMetricsCollectorInterface $collector,
        private readonly ?array $supportedRootClasses = null
    )
    {
    }

    public function supports(string $entityClass): bool
    {
        return $this->supportedRootClasses === null || in_array($entityClass, $this->supportedRootClasses, true);
    }

    public function beforeDetachRelations(string $parentClass, string $childClass, array $childIds, array $relation, object $root): void
    {
        if (!$this->supports($root::class)) {
            return;
        }

        $labels = [
            'root_class' => $root::class,
            'parent_class' => $parentClass,
            'child_class' => $childClass,
            'join_table' => $relation['joinTable'] ?? null,
        ];

        $this->detachStartedAt[$this->operationKey($root, 'detach', $childClass)] = microtime(true);
        $this->collector->increment('deletion_detach_batches_total', $labels);
        $this->collector->increment('deletion_detach_rows_total', $labels, count($childIds));
    }

    public function afterDetachRelations(string $parentClass, string $childClass, array $childIds, array $relation, object $root): void
    {
        if (!$this->supports($root::class)) {
            return;
        }

        $key = $this->operationKey($root, 'detach', $childClass);
        $startedAt = $this->detachStartedAt[$key] ?? null;
        unset($this->detachStartedAt[$key]);

        if ($startedAt === null) {
            return;
        }

        $this->collector->observe('deletion_detach_duration_seconds', microtime(true) - $startedAt, [
            'root_class' => $root::class,
            'parent_class' => $parentClass,
            'child_class' => $childClass,
            'join_table' => $relation['joinTable'] ?? null,
        ]);
    }

    public function beforeDeleteChildren(string $childClass, array $childIds, object $root): void
    {
        if (!$this->supports($root::class)) {
            return;
        }

        $labels = [
            'root_class' => $root::class,
            'child_class' => $childClass,
        ];

        $this->childrenStartedAt[$this->operationKey($root, 'delete_children', $childClass)] = microtime(true);
        $this->collector->increment('deletion_child_delete_batches_total', $labels);
        $this->collector->increment('deletion_child_delete_rows_total', $labels, count($childIds));
    }

    public function afterDeleteChildren(string $childClass, array $childIds, object $root): void
    {
        if (!$this->supports($root::class)) {
            return;
        }

        $key = $this->operationKey($root, 'delete_children', $childClass);
        $startedAt = $this->childrenStartedAt[$key] ?? null;
        unset($this->childrenStartedAt[$key]);

        if ($startedAt === null) {
            return;
        }

        $this->collector->observe('deletion_child_delete_duration_seconds', microtime(true) - $startedAt, [
            'root_class' => $root::class,
            'child_class' => $childClass,
        ]);
    }

    public function beforeDeleteRoot(object $root): void
    {
        if (!$this->supports($root::class)) {
            return;
        }

        $this->rootStartedAt[spl_object_id($root)] = microtime(true);
        $this->collector->increment('deletion_root_delete_started_total', [
            'root_class' => $root::class,
        ]);
    }

    public function afterDeleteRoot(object $root): void
    {
        if (!$this->supports($root::class)) {
            return;
        }

        $key = spl_object_id($root);
        $startedAt = $this->rootStartedAt[$key] ?? null;
        unset($this->rootStartedAt[$key]);

        $labels = [
            'root_class' => $root::class,
        ];

        $this->collector->increment('deletion_root_delete_succeeded_total', $labels);

        if ($startedAt !== null) {
            $this->collector->observe('deletion_root_delete_duration_seconds', microtime(true) - $startedAt, $labels);
        }
    }

    private function operationKey(object $root, string $stage, string $class): string
    {
        return sprintf('%d:%s:%s', spl_object_id($root), $stage, $class);
    }
}
