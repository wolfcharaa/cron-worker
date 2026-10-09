<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Tests\Schedule;

use Testo\Assert;
use Testo\Expect;
use Testo\Test;
use Wolfcharaa\Cron\Schedule\CronExpression;

#[Test]
final class CronExpressionTest
{
    public function createsEveryMinuteExpressionByDefault(): void
    {
        $expression = new CronExpression();

        Assert::same($expression->toString(), '* * * * *');
        Assert::same((string) $expression, '* * * * *');
    }

    public function parsesExpressionIntoNamedRules(): void
    {
        $expression = CronExpression::parse('*/5 8-18 1,15 JAN,MAR MON-FRI');

        Assert::same($expression->minute(), '*/5');
        Assert::same($expression->hour(), '8-18');
        Assert::same($expression->dayOfMonth(), '1,15');
        Assert::same($expression->month(), 'JAN,MAR');
        Assert::same($expression->dayOfWeek(), 'MON-FRI');
        Assert::same($expression->toString(), '*/5 8-18 1,15 JAN,MAR MON-FRI');
    }

    public function configuresDefaultExpressionWithImmutableApi(): void
    {
        $default = new CronExpression();
        $configured = $default
            ->withMinute('*/10')
            ->withHour('9-17')
            ->withDayOfMonth('*')
            ->withMonth('*')
            ->withDayOfWeek('MON-FRI');

        Assert::same($default->toString(), '* * * * *');
        Assert::same($configured->toString(), '*/10 9-17 * * MON-FRI');
        Assert::same($configured->rules(), [
            'minute' => '*/10',
            'hour' => '9-17',
            'dayOfMonth' => '*',
            'month' => '*',
            'dayOfWeek' => 'MON-FRI',
        ]);
    }

    public function acceptsIntegerRules(): void
    {
        $expression = (new CronExpression())
            ->withMinute(30)
            ->withHour(2);

        Assert::same($expression->toString(), '30 2 * * *');
    }

    public function expandsKnownAliases(): void
    {
        $expression = new CronExpression('@daily');

        Assert::same($expression->toString(), '0 0 * * *');
    }

    public function rejectsInvalidFieldRule(): void
    {
        Expect::exception(\InvalidArgumentException::class);

        (new CronExpression())->withMinute(60);
    }
}
