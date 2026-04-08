<?php

declare(strict_types=1);

namespace Tests\Unit\Services\GitLab;

use App\Services\GitLab\BranchParser;
use PHPUnit\Framework\TestCase;

class BranchParserTest extends TestCase
{
    private BranchParser $parser;

    /**
     * Case 1: dev_53642_23.12.2025
     * -> parent=dev, info=53642, task=53642, date=23.12.2025
     */
    public function test_branch_with_numeric_task_and_date(): void
    {
        $result = $this->parser->parse('dev_53642_23.12.2025');

        $this->assertTrue($result->isParsed());
        $this->assertSame('dev', $result->parentBranch);
        $this->assertSame('53642', $result->info);
        $this->assertSame(53642, $result->parsedTaskNumber);
        $this->assertNotNull($result->parsedDate);
        $this->assertSame('2025-12-23', $result->parsedDate->format('Y-m-d'));
        $this->assertNull($result->parsedTime);
    }

    /**
     * Case 2: dev_admin-fix_26.12.2025
     * -> parent=dev, info=admin-fix, task=NULL, date=26.12.2025
     */
    public function test_branch_with_text_info_and_date(): void
    {
        $result = $this->parser->parse('dev_admin-fix_26.12.2025');

        $this->assertTrue($result->isParsed());
        $this->assertSame('dev', $result->parentBranch);
        $this->assertSame('admin-fix', $result->info);
        $this->assertNull($result->parsedTaskNumber);
        $this->assertNotNull($result->parsedDate);
        $this->assertSame('2025-12-26', $result->parsedDate->format('Y-m-d'));
    }

    /**
     * Case 3: dev_import_22.01.2026-16.00
     * -> parent=dev, info=import, task=NULL, date=22.01.2026, time=16:00
     */
    public function test_branch_with_date_and_time_with_minutes(): void
    {
        $result = $this->parser->parse('dev_import_22.01.2026-16.00');

        $this->assertTrue($result->isParsed());
        $this->assertSame('dev', $result->parentBranch);
        $this->assertSame('import', $result->info);
        $this->assertNull($result->parsedTaskNumber);
        $this->assertNotNull($result->parsedDate);
        $this->assertSame('2026-01-22', $result->parsedDate->format('Y-m-d'));
        $this->assertSame('16:00', $result->parsedTime);
    }

    /**
     * Case 4: dev_fix_admin_controllers_29.12.2025
     * -> parent=dev, info=fix_admin_controllers, task=NULL, date=29.12.2025
     * Test greedy capture of info with multiple underscores
     */
    public function test_branch_with_multiple_underscores_in_info(): void
    {
        $result = $this->parser->parse('dev_fix_admin_controllers_29.12.2025');

        $this->assertTrue($result->isParsed());
        $this->assertSame('dev', $result->parentBranch);
        $this->assertSame('fix_admin_controllers', $result->info);
        $this->assertNull($result->parsedTaskNumber);
        $this->assertNotNull($result->parsedDate);
        $this->assertSame('2025-12-29', $result->parsedDate->format('Y-m-d'));
    }

    /**
     * Case 5: dev_add_features_grid_block
     * -> parent=dev, info=add_features_grid_block, task=NULL, date=NULL
     * Fallback: no date
     */
    public function test_branch_without_date(): void
    {
        $result = $this->parser->parse('dev_add_features_grid_block');

        $this->assertTrue($result->isParsed());
        $this->assertSame('dev', $result->parentBranch);
        $this->assertSame('add_features_grid_block', $result->info);
        $this->assertNull($result->parsedTaskNumber);
        $this->assertNull($result->parsedDate);
        $this->assertNull($result->parsedTime);
    }

    /**
     * Case 6: main
     * -> unrecognized format, all fields NULL
     */
    public function test_unrecognized_branch_format(): void
    {
        $result = $this->parser->parse('main');

        $this->assertFalse($result->isParsed());
        $this->assertNull($result->parentBranch);
        $this->assertNull($result->info);
        $this->assertNull($result->parsedTaskNumber);
        $this->assertNull($result->parsedDate);
        $this->assertNull($result->parsedTime);
        $this->assertSame('main', $result->branchName);
    }

    /**
     * Case 7: dev_import_22.01.2026-16
     * -> parent=dev, info=import, date=22.01.2026, time=16:00
     * Time without minutes
     */
    public function test_branch_with_date_and_time_without_minutes(): void
    {
        $result = $this->parser->parse('dev_import_22.01.2026-16');

        $this->assertTrue($result->isParsed());
        $this->assertSame('import', $result->info);
        $this->assertNotNull($result->parsedDate);
        $this->assertSame('2026-01-22', $result->parsedDate->format('Y-m-d'));
        $this->assertSame('16:00', $result->parsedTime);
    }

    /**
     * Additional: hasTaskNumber() returns true for numeric info
     */
    public function test_has_task_number_returns_true_for_numeric(): void
    {
        $result = $this->parser->parse('dev_53642_23.12.2025');
        $this->assertTrue($result->hasTaskNumber());
    }

    /**
     * Additional: hasTaskNumber() returns false for text info
     */
    public function test_has_task_number_returns_false_for_text(): void
    {
        $result = $this->parser->parse('dev_admin-fix_26.12.2025');
        $this->assertFalse($result->hasTaskNumber());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new BranchParser();
    }
}
