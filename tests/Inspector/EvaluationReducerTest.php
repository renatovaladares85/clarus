<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Tests\Inspector;

use GlpiPlugin\Clarus\Inspector\Evaluation;
use GlpiPlugin\Clarus\Inspector\EvaluationReducer;
use PHPUnit\Framework\TestCase;

final class EvaluationReducerTest extends TestCase
{
    /**
     * @dataProvider reductionCases
     * @param list<Evaluation> $criteria
     */
   public function testReductionUsesThreeValuedLogic(
        string $mode,
        array $criteria,
        Evaluation $expected
    ): void {
       self::assertSame($expected, EvaluationReducer::reduce($mode, $criteria));
   }

    /** @return iterable<string, array{string, list<Evaluation>, Evaluation}> */
   public static function reductionCases(): iterable {
       yield 'and match' => ['AND', [Evaluation::MATCH, Evaluation::MATCH], Evaluation::MATCH];
       yield 'and no match wins' => [
           'AND',
           [Evaluation::INDETERMINATE, Evaluation::NO_MATCH],
           Evaluation::NO_MATCH,
       ];
       yield 'and indeterminate' => [
           'AND',
           [Evaluation::MATCH, Evaluation::INDETERMINATE],
           Evaluation::INDETERMINATE,
       ];
       yield 'or no match' => ['OR', [Evaluation::NO_MATCH, Evaluation::NO_MATCH], Evaluation::NO_MATCH];
       yield 'or match wins' => [
           'OR',
           [Evaluation::INDETERMINATE, Evaluation::MATCH],
           Evaluation::MATCH,
       ];
       yield 'or indeterminate' => [
           'OR',
           [Evaluation::NO_MATCH, Evaluation::INDETERMINATE],
           Evaluation::INDETERMINATE,
       ];
       yield 'empty and is logical identity' => ['AND', [], Evaluation::MATCH];
       yield 'empty or is logical identity' => ['OR', [], Evaluation::NO_MATCH];
   }

   public function testUnknownMatchingModeIsRejected(): void {
       $this->expectException(\InvalidArgumentException::class);
       EvaluationReducer::reduce('XOR', []);
   }
}
