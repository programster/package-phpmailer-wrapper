<?php

/*
 * A small test runner for the smoke tests in testing/tests/. Each test is a named callback, which fails if it throws
 * (including a failed assert()) or raises a warning, notice or deprecation.
 */

namespace Programster\Phpmailer\Testing;


final class SmokeTest
{
    private int $m_numPassed = 0;

    /** @var list<string> */
    private array $m_failures = [];

    /** @var list<string> */
    private array $m_skipped = [];

    /** @var list<string> */
    private array $m_warnings = [];


    public function __construct()
    {
        error_reporting(-1);

        $recordWarning = function (int $errorNumber, string $message, string $file, int $line) : bool {
            # Respect the @ operator, in case a dependency deliberately uses it.
            if (error_reporting() & $errorNumber)
            {
                $this->m_warnings[] = "[E{$errorNumber}] {$message} @ " . basename($file) . ":{$line}";
            }

            return true;
        };

        set_error_handler($recordWarning);
    }


    /**
     * Run one test, recording it as failed if it throws, or raises any warnings/deprecations.
     * @param string $label - the name of the test, shown if it fails.
     * @param callable $test - the test to run.
     * @return void
     */
    public function run(string $label, callable $test) : void
    {
        $numWarningsBefore = count($this->m_warnings);
        $error = "";

        try
        {
            if ($this->isSelected($label))
            {
                $test();
            }
        }
        catch (\Throwable $e)
        {
            $error = get_class($e) . ": " . $e->getMessage() . " @ " . basename($e->getFile()) . ":" . $e->getLine();
        }

        $newWarnings = array_slice($this->m_warnings, $numWarningsBefore);

        if (!$this->isSelected($label))
        {
            # Not selected, so neither passed nor failed.
        }
        elseif ($error === "" && count($newWarnings) === 0)
        {
            $this->m_numPassed++;
        }
        else
        {
            $this->m_failures[] = trim("{$label}: {$error} " . implode(" | ", $newWarnings));
        }
    }


    /**
     * Record a test as skipped, as what it needs isn't available here (e.g. it's running as root, which can read any
     * file), so it's listed in the results, rather than silently passing.
     *
     * @param string $label - the name of the test.
     *
     * @param string $reason - why it was skipped.
     *
     * @return void
     */
    public function skip(string $label, string $reason) : void
    {
        if ($this->isSelected($label))
        {
            $this->m_skipped[] = "{$label}: {$reason}";
        }
    }


    /**
     * Assert that a callback throws a specific type of exception, and return it, so it can be checked further.
     *
     * @template T of \Throwable
     *
     * @param class-string<T> $exceptionClass - the class of exception that should be thrown.
     *
     * @param callable $callback - the code that should throw it.
     *
     * @param string $messageContains - optionally, text the exception's message must contain, for when the same class
     * of exception can be thrown for different reasons.
     *
     * @return T
     *
     * @throws \Throwable - if the callback throws a different exception, it is rethrown.
     * @throws \Exception - if the callback doesn't throw at all, or the message doesn't contain $messageContains.
     */
    public static function expectException(
        string $exceptionClass,
        callable $callback,
        string $messageContains = ""
    ) : \Throwable
    {
        $nothingThrown = new \Exception("Expected {$exceptionClass} to be thrown, but nothing was thrown.");

        try
        {
            $callback();
            throw $nothingThrown;
        }
        catch (\Throwable $e)
        {
            if ($e === $nothingThrown || !($e instanceof $exceptionClass))
            {
                throw $e;
            }

            if (!str_contains($e->getMessage(), $messageContains))
            {
                throw new \Exception(
                    "Expected the {$exceptionClass} message to contain \"{$messageContains}\", but it was: " .
                    $e->getMessage()
                );
            }

            $thrown = $e;
        }

        return $thrown;
    }


    /**
     * Assert that two values are identical, showing both if they aren't, which a bare assert() doesn't.
     * @param mixed $expected - the value it should be.
     * @param mixed $actual - the value it is.
     * @param string $what - what is being compared, to show if it fails.
     * @return void
     */
    public static function assertSame(mixed $expected, mixed $actual, string $what) : void
    {
        $describe = function (mixed $value) : string {
            $flags = JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE;
            return json_encode($value, $flags | JSON_THROW_ON_ERROR);
        };

        assert($expected === $actual, "{$what}: expected {$describe($expected)}, got {$describe($actual)}");
    }


    /**
     * Find out whether a test is to be run: PHPMAILER_TEST_ONLY runs only the tests whose name contains it.
     * @param string $label - the name of the test.
     * @return bool
     */
    private function isSelected(string $label) : bool
    {
        $only = getenv("PHPMAILER_TEST_ONLY");
        return ($only === false || $only === "" || str_contains($label, $only));
    }


    # Accessors
    public function getNumPassed() : int { return $this->m_numPassed; }

    /** @return list<string> */
    public function getFailures() : array { return $this->m_failures; }

    /** @return list<string> */
    public function getSkipped() : array { return $this->m_skipped; }
}
