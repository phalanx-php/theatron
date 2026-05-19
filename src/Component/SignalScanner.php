<?php

declare(strict_types=1);

namespace Phalanx\Theatron\Component;

use Phalanx\Theatron\Reactive\DirtyBatch;
use Phalanx\Theatron\Reactive\Signal;
use ReflectionClass;
use ReflectionNamedType;

final class SignalScanner
{
    /** @param array<string, mixed> $runtimeParams */
    public static function scan(object $component, DirtyBatch $batch, array $runtimeParams = []): SignalScanResult
    {
        $ownedSignals = [];
        $subscriptions = [];

        /** @var array<int, true> */
        $borrowed = [];
        foreach ($runtimeParams as $value) {
            if ($value instanceof Signal) {
                $borrowed[spl_object_id($value)] = true;
            }
        }

        $ref = new ReflectionClass($component);
        foreach ($ref->getProperties() as $prop) {
            $type = $prop->getType();
            if (!$type instanceof ReflectionNamedType || $type->getName() !== Signal::class) {
                continue;
            }

            $value = $prop->getValue($component);
            if (!$value instanceof Signal) {
                continue;
            }

            $subscriptions[] = $value->subscribe(static function () use ($batch): void {
                $batch->request();
            });

            if (!isset($borrowed[spl_object_id($value)])) {
                $ownedSignals[] = $value;
            }
        }

        return new SignalScanResult($ownedSignals, $subscriptions);
    }
}
