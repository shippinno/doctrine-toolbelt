<?php
declare(strict_types=1);

namespace Shippinno\DoctrineToolbelt;

use RuntimeException;
use Throwable;

class RollbackFailedException extends RuntimeException
{
    /**
     * @param Throwable|null $previous
     */
    public function __construct(Throwable $previous = null)
    {
        parent::__construct('Transaction could not be rolled back.', 0, $previous);
    }
}
