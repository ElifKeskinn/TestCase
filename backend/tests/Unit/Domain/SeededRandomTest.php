<?php

namespace Tests\Unit\Domain;

use App\Domain\Support\SeededRandom;
use PHPUnit\Framework\TestCase;

final class SeededRandomTest extends TestCase
{
    public function test_same_seed_same_sequence(): void
    {
        $a = new SeededRandom(42);
        $b = new SeededRandom(42);
        for ($i = 0; $i < 100; $i++) {
            $this->assertSame($a->next(), $b->next());
        }
    }

    public function test_different_seed_different_sequence(): void
    {
        $a = new SeededRandom(1);
        $b = new SeededRandom(2);
        $aSeq = []; $bSeq = [];
        for ($i = 0; $i < 50; $i++) {
            $aSeq[] = $a->next();
            $bSeq[] = $b->next();
        }
        $this->assertNotSame($aSeq, $bSeq);
    }

    public function test_next_float_range(): void
    {
        $r = new SeededRandom(7);
        for ($i = 0; $i < 1000; $i++) {
            $f = $r->nextFloat();
            $this->assertGreaterThanOrEqual(0.0, $f);
            $this->assertLessThan(1.0, $f);
        }
    }

    public function test_next_int_inclusive_bounds(): void
    {
        $r = new SeededRandom(123);
        for ($i = 0; $i < 1000; $i++) {
            $v = $r->nextInt(3, 7);
            $this->assertGreaterThanOrEqual(3, $v);
            $this->assertLessThanOrEqual(7, $v);
        }
    }

    public function test_poisson_returns_nonnegative(): void
    {
        $r = new SeededRandom(99);
        for ($i = 0; $i < 100; $i++) {
            $this->assertGreaterThanOrEqual(0, $r->poisson(2.0));
        }
    }

    public function test_derive_subseed_deterministic(): void
    {
        $a = SeededRandom::deriveSubseed(42, 7, 3);
        $b = SeededRandom::deriveSubseed(42, 7, 3);
        $this->assertSame($a, $b);

        $c = SeededRandom::deriveSubseed(42, 7, 4);
        $this->assertNotSame($a, $c);
    }
}
