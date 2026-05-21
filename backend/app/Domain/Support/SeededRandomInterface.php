<?php

namespace App\Domain\Support;

/**
 * Contract for deterministic PRNG implementations (DI friendly).
 */
interface SeededRandomInterface
{
    public function next(): int;
    public function nextFloat(): float;
    public function nextInt(int $min, int $max): int;
    public function poisson(float $lambda): int;
}
