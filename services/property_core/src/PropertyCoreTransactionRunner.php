<?php

final class PropertyCoreTransactionRunner
{
    private Closure $begin;
    private Closure $commit;
    private Closure $rollBack;
    private Closure $inTransaction;

    public function __construct(
        callable $begin,
        callable $commit,
        callable $rollBack,
        callable $inTransaction
    ) {
        $this->begin = Closure::fromCallable($begin);
        $this->commit = Closure::fromCallable($commit);
        $this->rollBack = Closure::fromCallable($rollBack);
        $this->inTransaction = Closure::fromCallable($inTransaction);
    }

    public static function forPdo(PDO $pdo): self
    {
        return new self(
            static fn (): bool => $pdo->beginTransaction(),
            static fn (): bool => $pdo->commit(),
            static fn (): bool => $pdo->rollBack(),
            static fn (): bool => $pdo->inTransaction()
        );
    }

    public function run(callable $operation): mixed
    {
        if (!(($this->begin)())) {
            throw new RuntimeException(
                'Property Core transaction could not begin.'
            );
        }

        try {
            $result = $operation();

            if (!(($this->commit)())) {
                throw new RuntimeException(
                    'Property Core transaction could not commit.'
                );
            }

            return $result;
        } catch (Throwable $error) {
            if (($this->inTransaction)()) {
                ($this->rollBack)();
            }
            throw $error;
        }
    }
}
