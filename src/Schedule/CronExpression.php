<?php

declare(strict_types=1);

namespace Wolfcharaa\Cron\Schedule;

use Cron\CronExpression as CronExpressionParser;

/** Validated five-field cron expression with immutable field updates. */
final readonly class CronExpression implements \Stringable
{
    public const DEFAULT = '* * * * *';

    private string $minute;

    private string $hour;

    private string $dayOfMonth;

    private string $month;

    private string $dayOfWeek;

    /** Parses and validates a five-field expression. */
    public function __construct(string $expression = self::DEFAULT)
    {
        $parser = new CronExpressionParser($expression);

        $this->minute = self::part($parser, CronExpressionParser::MINUTE);
        $this->hour = self::part($parser, CronExpressionParser::HOUR);
        $this->dayOfMonth = self::part($parser, CronExpressionParser::DAY);
        $this->month = self::part($parser, CronExpressionParser::MONTH);
        $this->dayOfWeek = self::part($parser, CronExpressionParser::WEEKDAY);
    }

    /** Named constructor equivalent to `new CronExpression($expression)`. */
    public static function parse(string $expression): self
    {
        return new self($expression);
    }

    /** Returns the minute rule. */
    public function minute(): string
    {
        return $this->minute;
    }

    /** Returns the hour rule. */
    public function hour(): string
    {
        return $this->hour;
    }

    /** Returns the day-of-month rule. */
    public function dayOfMonth(): string
    {
        return $this->dayOfMonth;
    }

    /** Returns the month rule. */
    public function month(): string
    {
        return $this->month;
    }

    /** Returns the day-of-week rule. */
    public function dayOfWeek(): string
    {
        return $this->dayOfWeek;
    }

    /** Returns a copy with a different minute rule. */
    public function withMinute(int|string $rule): self
    {
        return $this->withPart(CronExpressionParser::MINUTE, $rule);
    }

    /** Returns a copy with a different hour rule. */
    public function withHour(int|string $rule): self
    {
        return $this->withPart(CronExpressionParser::HOUR, $rule);
    }

    /** Returns a copy with a different day-of-month rule. */
    public function withDayOfMonth(int|string $rule): self
    {
        return $this->withPart(CronExpressionParser::DAY, $rule);
    }

    /** Returns a copy with a different month rule. */
    public function withMonth(int|string $rule): self
    {
        return $this->withPart(CronExpressionParser::MONTH, $rule);
    }

    /** Returns a copy with a different day-of-week rule. */
    public function withDayOfWeek(int|string $rule): self
    {
        return $this->withPart(CronExpressionParser::WEEKDAY, $rule);
    }

    /**
     * @return array{
     *     minute: string,
     *     hour: string,
     *     dayOfMonth: string,
     *     month: string,
     *     dayOfWeek: string
     * }
     */
    public function rules(): array
    {
        return [
            'minute' => $this->minute,
            'hour' => $this->hour,
            'dayOfMonth' => $this->dayOfMonth,
            'month' => $this->month,
            'dayOfWeek' => $this->dayOfWeek,
        ];
    }

    /** Serializes the canonical five-field expression. */
    public function toString(): string
    {
        return implode(' ', [
            $this->minute,
            $this->hour,
            $this->dayOfMonth,
            $this->month,
            $this->dayOfWeek,
        ]);
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    private function withPart(int $position, int|string $rule): self
    {
        $parts = [
            $this->minute,
            $this->hour,
            $this->dayOfMonth,
            $this->month,
            $this->dayOfWeek,
        ];
        $parts[$position] = (string) $rule;

        return new self(implode(' ', $parts));
    }

    private static function part(CronExpressionParser $parser, int $position): string
    {
        $part = $parser->getExpression($position);

        if ($part === null) {
            throw new \LogicException(\sprintf('Cron expression part %d is missing.', $position));
        }

        return $part;
    }
}
