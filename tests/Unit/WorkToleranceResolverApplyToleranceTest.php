<?php

namespace Tests\Unit;

use App\Services\WorkToleranceResolver;
use PHPUnit\Framework\TestCase;

class WorkToleranceResolverApplyToleranceTest extends TestCase
{
    private WorkToleranceResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new WorkToleranceResolver;
    }

    public function test_dead_band_positive_inside_band(): void
    {
        $t = 10;
        $this->assertSame(0, $this->resolver->applyToleranceToDiff(9, $t, WorkToleranceResolver::MODE_DAILY_DEAD_BAND));
        $this->assertSame(0, $this->resolver->applyToleranceToDiff(10, $t, WorkToleranceResolver::MODE_DAILY_DEAD_BAND));
    }

    public function test_dead_band_positive_outside_band(): void
    {
        $this->assertSame(11, $this->resolver->applyToleranceToDiff(11, 10, WorkToleranceResolver::MODE_DAILY_DEAD_BAND));
    }

    public function test_discount_positive(): void
    {
        $t = 10;
        $this->assertSame(0, $this->resolver->applyToleranceToDiff(9, $t, WorkToleranceResolver::MODE_DAILY_DISCOUNT));
        $this->assertSame(0, $this->resolver->applyToleranceToDiff(10, $t, WorkToleranceResolver::MODE_DAILY_DISCOUNT));
        $this->assertSame(5, $this->resolver->applyToleranceToDiff(15, $t, WorkToleranceResolver::MODE_DAILY_DISCOUNT));
    }

    public function test_dead_band_negative(): void
    {
        $t = 10;
        $this->assertSame(0, $this->resolver->applyToleranceToDiff(-9, $t, WorkToleranceResolver::MODE_DAILY_DEAD_BAND));
        $this->assertSame(-15, $this->resolver->applyToleranceToDiff(-15, $t, WorkToleranceResolver::MODE_DAILY_DEAD_BAND));
    }

    public function test_discount_negative(): void
    {
        $t = 10;
        $this->assertSame(0, $this->resolver->applyToleranceToDiff(-9, $t, WorkToleranceResolver::MODE_DAILY_DISCOUNT));
        $this->assertSame(-5, $this->resolver->applyToleranceToDiff(-15, $t, WorkToleranceResolver::MODE_DAILY_DISCOUNT));
    }

    public function test_zero_diff_always_zero(): void
    {
        $this->assertSame(0, $this->resolver->applyToleranceToDiff(0, 10, WorkToleranceResolver::MODE_DAILY_DEAD_BAND));
        $this->assertSame(0, $this->resolver->applyToleranceToDiff(0, 10, WorkToleranceResolver::MODE_DAILY_DISCOUNT));
    }

    public function test_float_diff_is_rounded(): void
    {
        $this->assertSame(5, $this->resolver->applyToleranceToDiff(15.4, 10, WorkToleranceResolver::MODE_DAILY_DISCOUNT));
        $this->assertSame(11, $this->resolver->applyToleranceToDiff(10.6 + 0.4, 10, WorkToleranceResolver::MODE_DAILY_DEAD_BAND));
    }
}
