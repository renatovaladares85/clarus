<?php

// SPDX-License-Identifier: GPL-3.0-or-later

declare(strict_types=1);

namespace GlpiPlugin\Clarus\Tests\Inspector;

use GlpiPlugin\Clarus\Inspector\ActionEvaluation;
use GlpiPlugin\Clarus\Inspector\ActionInspection;
use GlpiPlugin\Clarus\Inspector\ActionSupport;
use PHPUnit\Framework\TestCase;

final class ActionInspectionTest extends TestCase
{
   public function testUnsupportedSemanticsMustBeIndeterminate(): void {
       $this->expectException(\InvalidArgumentException::class);
       new ActionInspection(
           1,
           'assign',
           'plugin_field',
           ActionSupport::UNSUPPORTED,
           ActionEvaluation::REFLECTED
       );
   }

   public function testIndeterminateEvaluationRequiresReason(): void {
       $this->expectException(\InvalidArgumentException::class);
       new ActionInspection(
           1,
           'assign',
           'urgency',
           ActionSupport::SUPPORTED,
           ActionEvaluation::INDETERMINATE
       );
   }

   public function testUnsafeValuesCannotBeExposed(): void {
       $this->expectException(\InvalidArgumentException::class);
       new ActionInspection(
           1,
           'assign',
           'plugin_field',
           ActionSupport::UNSUPPORTED,
           ActionEvaluation::INDETERMINATE,
           'unsupported_action_semantics',
           false,
           'secret'
       );
   }
}
