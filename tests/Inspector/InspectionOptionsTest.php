<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Tests\Inspector;

use GlpiPlugin\Clarus\Inspector\InspectionOptions;
use PHPUnit\Framework\TestCase;

final class InspectionOptionsTest extends TestCase
{
   public function testDefaultLimitIsOneThousand(): void {
       $options = new InspectionOptions();

       self::assertSame(1000, $options->ruleLimit);
       self::assertFalse($options->includeActions);
   }

   public function testLimitAboveOneThousandIsAccepted(): void {
       self::assertSame(2500, (new InspectionOptions(2500))->ruleLimit);
   }

   public function testActionAnalysisCanBeEnabledAdditively(): void {
       self::assertTrue((new InspectionOptions(1000, true))->includeActions);
   }

    /** @dataProvider invalidLimits */
   public function testNonPositiveLimitIsRejected(int $limit): void {
       $this->expectException(\InvalidArgumentException::class);
       new InspectionOptions($limit);
   }

    /** @return iterable<array{int}> */
   public static function invalidLimits(): iterable {
       yield [0];
       yield [-1];
   }
}
