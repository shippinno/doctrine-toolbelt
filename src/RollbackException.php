<?php
declare(strict_types=1);

namespace Shippinno\DoctrineToolbelt;

use Exception;
use Throwable;

class RollbackException extends Exception
{
    /**
     * @param Throwable|null $previous
     */
    public function __construct(Throwable $previous = null)
    {
        parent::__construct('Transaction rolled back.', 0, $previous);
    }
}
