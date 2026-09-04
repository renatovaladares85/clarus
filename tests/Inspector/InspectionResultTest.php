<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Tests\Inspector;

use GlpiPlugin\Clarus\Inspector\Evaluation;
use GlpiPlugin\Clarus\Inspector\InspectionResult;
use GlpiPlugin\Clarus\Inspector\RuleInspection;
use PHPUnit\Framework\TestCase;

final class InspectionResultTest extends TestCase
{
   public function testTruncationMetadataIsExplicitAndConsistent(): void {
       $rule = new RuleInspection(1, 'rule', 1, 0, true, 1, 'AND', [], Evaluation::MATCH);
       $result = new InspectionResult(8, 1, 1, 2, 1, true, [$rule]);

       self::assertSame(1, $result->configuredLimit);
       self::assertSame(2, $result->candidateCount);
       self::assertSame(1, $result->evaluatedCount);
       self::assertTrue($result->truncated);
   }

   public function testInconsistentTruncationIsRejected(): void {
       $this->expectException(\InvalidArgumentException::class);
       new InspectionResult(8, 1, 1000, 2, 0, false, []);
   }
}
