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
     * argument. Invocations beyond the list of expectations are not asserted against -
     * use `expects($this->exactly(n))` if the number of calls matters.
     *
     * @param array $expectedArgs One entry per expected invocation
     */
    protected function withConsecutiveArgs(array $expectedArgs): callable
    {
        $remaining = $expectedArgs;

        return function (mixed $actualArg) use (&$remaining): void {
            if ($remaining === []) {
                return;
            }

            $this->assertEquals(array_shift($remaining), $actualArg);
        };
    }
}
