<?php

declare(strict_types=1);

abstract class TestCase
{
    private string $suiteName;
    private array $results = [];

    public function __construct(?string $suiteName = null)
    {
        $this->suiteName = $suiteName ?? static::class;
    }

    public function getSuiteName(): string
    {
        return $this->suiteName;
    }

    public function getResults(): array
    {
        return $this->results;
    }

    protected function assertTrue(bool $condition, string $description): void
    {
        $this->recordAssertion($condition, $description, 'true', $condition ? 'true' : 'false');
    }

    protected function assertFalse(bool $condition, string $description): void
    {
        $this->recordAssertion(!$condition, $description, 'false', $condition ? 'true' : 'false');
    }

    protected function assertEquals($expected, $actual, string $description): void
    {
        $passed = ($expected === $actual);
        $this->recordAssertion($passed, $description, json_encode($expected), json_encode($actual));
    }

    protected function assertNotNull($actual, string $description): void
    {
        $passed = ($actual !== null);
        $this->recordAssertion($passed, $description, 'not null', json_encode($actual));
    }

    protected function assertThrows(string $expectedException, callable $callback, string $description): void
    {
        $thrown = false;
        $actualException = null;
        try {
            $callback();
        } catch (Throwable $e) {
            $actualException = get_class($e);
            if ($e instanceof $expectedException) {
                $thrown = true;
            }
        }

        $this->recordAssertion(
            $thrown,
            $description,
            "Exception {$expectedException}",
            $actualException ? "Exception {$actualException}" : "No exception thrown"
        );
    }

    private function recordAssertion(bool $passed, string $description, string $expected, string $actual): void
    {
        $this->results[] = [
            'suite' => $this->suiteName,
            'description' => $description,
            'passed' => $passed,
            'expected' => $expected,
            'actual' => $actual
        ];
    }
}
