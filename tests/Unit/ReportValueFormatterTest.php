<?php

namespace Tests\Unit;

use App\Support\Reports\ReportValueFormatter;
use PHPUnit\Framework\TestCase;

class ReportValueFormatterTest extends TestCase
{
    public function test_total_discounts_is_rendered_from_minor_units_as_money(): void
    {
        $formatter = new ReportValueFormatter;

        $this->assertSame('30.00', $formatter->display('total_discounts', 3000));
        $this->assertSame('30.00', $formatter->csv('total_discounts', 3000));
    }
}
