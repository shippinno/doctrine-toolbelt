<?php
declare(strict_types=1);

namespace Shippinno\DoctrineToolbelt;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMException;
use Throwable;

trait HandlesMultipleEntityManagers
{
    /**
     * @var ManagerRegistry
     */
    protected $managerRegistry;

    /**
     * @param ManagerRegistry $managerRegistry
     */
    protected function setManagerRegistry(ManagerRegistry $managerRegistry): void
    {
        $this->managerRegistry = $managerRegistry;
    }

    /**
     * @param string[] $names
     * @throws RollbackException
     */
    public function flushManagersAtomically(array $names): void
    {
        $this->beginTransactions($names);
        try {
            try {
                $this->flush($names);
                $this->commit($names);
                return;
            } catch (Throwable $e) {
                $this->rollback($names);
                throw new RollbackException($e);
            }
        } catch (ConnectionException $e) {
            throw new RollbackFailedException($e);
        }
    }

    /**
     * @throws RollbackException
     */
    public function flushAllManagersAtomically(): void
    {
        $this->flushManagersAtomically($this->managerRegistry->getManagerNames());
    }

    /**
     * @param string[] $names
     */
    public function clearManagers(array $names): void
    {
        foreach ($names as $name) {
            $this->getEntityManager($name)->clear();
        }
    }

    /**
     * @return void
     */
    public function clearAllManagers(): void
    {
        $this->clearManagers($this->managerRegistry->getManagerNames());
    }

    /**
     * @param string[] $names
     * @throws ORMException
     */
    protected function flush(array $names): void
    {
        foreach ($names as $name) {
            $this->getEntityManager($name)->flush();
        }
    }

    /**
     * @param string[] $names
     */
    protected function beginTransactions(array $names): void
    {
        foreach ($names as $name) {
            $this->getConnection($name)->beginTransaction();
        }
    }

    /**
     * @param string[] $names
     * @throws ConnectionException
     */
    protected function commit(array $names): void
    {
        foreach ($names as $name) {
            $this->getConnection($name)->commit();
        }
    }

    /**
     * @param string[] $names
     * @throws ConnectionException
     */
    protected function rollback(array $names): void
    {
        foreach ($names as $name) {
            $this->getConnection($name)->rollBack();
        }
    }

    /**
     * @param string $name
     * @return EntityManager
     */
    protected function getEntityManager(string $name): EntityManager
    {
        return $this->managerRegistry->getManager($name);
    }

    /**
     * @param string $name
     * @return Connection
     */
    protected function getConnection(string $name): Connection
    {
        return $this->getEntityManager($name)->getConnection();
    }
}
