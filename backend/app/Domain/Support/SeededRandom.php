<?php

namespace App\Domain\Support;

/**
 * SeededRandom — deterministic PRNG (US-B-02, US-C-04, NFR-03).
 *
 * - Pure PHP, framework-agnostic.
 * - Linear-congruential generator (Numerical Recipes constants), 32-bit safe.
 * - Stateful: same seed → same sequence (`next()` calls).
 * - Sub-seeding strategy: hash(seed, match_id, week) (used by callers, §4.5.9).
 */
class SeededRandom implements SeededRandomInterface
{
    /** 32-bit unsigned state. */
    private int $state;

    public function __construct(int $seed)
    {
        // Normalize to 32-bit unsigned.
        $this->state = $seed & 0xFFFFFFFF;
        if ($this->state === 0) {
            $this->state = 1; // avoid stuck-at-zero degeneracy.
        }
    }

    /**
     * Advance the LCG and return a 32-bit unsigned integer.
     *
     * Numerical Recipes parameters: a=1664525, c=1013904223, m=2^32.
     */
    public function next(): int
    {
        // Use intval/modulo because PHP integers are 64-bit on modern platforms;
        // we clamp to 32-bit at the end.
        $this->state = (($this->state * 1664525) + 1013904223) & 0xFFFFFFFF;
        return $this->state;
    }

    /**
     * Random float in [0, 1).
     */
    public function nextFloat(): float
    {
        return $this->next() / 4294967296.0; // 2^32
    }

    /**
     * Random integer in [min, max] inclusive.
     */
    public function nextInt(int $min, int $max): int
    {
        if ($min > $max) {
            [$min, $max] = [$max, $min];
        }
        $range = $max - $min + 1;
        return $min + (int) floor($this->nextFloat() * $range);
    }

    /**
     * Sample from a Poisson distribution with mean λ ≥ 0.
     * Knuth's multiplicative algorithm — adequate for small λ (typical < 10).
     */
    public function poisson(float $lambda): int
    {
        if ($lambda <= 0) {
            return 0;
        }
        $L = exp(-$lambda);
        $k = 0;
        $p = 1.0;
        do {
            $k++;
            $p *= $this->nextFloat();
        } while ($p > $L);
        return $k - 1;
    }

    /**
     * Derive a stable sub-seed from arbitrary inputs (deterministic replay).
     */
    public static function deriveSubseed(int ...$inputs): int
    {
        $material = implode(':', $inputs);
        // Take first 8 hex chars (32-bit) of sha256 for deterministic mapping.
        return (int) hexdec(substr(hash('sha256', $material), 0, 8));
    }
}
