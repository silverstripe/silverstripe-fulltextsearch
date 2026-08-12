<?php

namespace SilverStripe\FullTextSearch\Tests\Traits;

/**
 * Replacement for PHPUnit's `withConsecutive()`, which was removed in PHPUnit 10.
 *
 * Use with `willReturnCallback()` to assert that consecutive invocations of a mocked
 * method receive the expected arguments, in order.
 */
trait AssertsConsecutiveCalls
{
    /**
     * Returns a callback which asserts that each invocation receives the next expected
     * argument. Invoking the mocked method more often than there are expectations fails
     * the test, so the list must cover every call which is expected to be made.
     *
     * @param array $expectedArgs One entry per expected invocation
     */
    protected function withConsecutiveArgs(array $expectedArgs): callable
    {
        $remaining = $expectedArgs;
        $expectedCount = count($expectedArgs);

        return function (mixed $actualArg) use (&$remaining, $expectedCount): void {
            $this->assertNotEmpty(
                $remaining,
                "Mocked method was called more than the {$expectedCount} expected time(s)"
            );

            $this->assertEquals(array_shift($remaining), $actualArg);
        };
    }
}
