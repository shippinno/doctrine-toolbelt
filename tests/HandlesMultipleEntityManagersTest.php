<?php

namespace Shippinno\DoctrineToolbelt;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\Mock;
use PHPUnit\Framework\TestCase;

class HandlesMultipleEntityManagersTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** @var EntityManager|Mock */
    private $entityManager1;

    /** @var EntityManager|Mock  */
    private $entityManager2;

    /** @var Connection|Mock  */
    private $connection1;

    /** @var Connection|Mock  */
    private $connection2;

    /** @var ManagerRegistry|Mock  */
    private $managerRegistry;

    /** @var Handler|Mock  */
    private $handler;

    public function setUp()
    {
        $this->connection1 = Mockery::spy(Connection::class);
        $this->connection2 = Mockery::spy(Connection::class);
        $this->entityManager1 = Mockery::spy(EntityManager::class);
        $this->entityManager1
            ->shouldReceive('getConnection')
            ->andReturn($this->connection1);
        $this->entityManager2 = Mockery::spy(EntityManager::class);
        $this->entityManager2
            ->shouldReceive('getConnection')
            ->andReturn($this->connection2);
        $this->managerRegistry = Mockery::mock(ManagerRegistry::class);
        $this->managerRegistry
            ->shouldReceive('getManagerNames')
            ->andReturn(['one', 'two'])
            ->shouldReceive('getManager')
            ->with('one')
            ->andReturn($this->entityManager1)
            ->shouldReceive('getManager')
            ->with('two')
            ->andReturn($this->entityManager2);
        $this->handler = new Handler($this->managerRegistry);
    }

    public function testItFlushesEntityManagersAtomically()
    {
        $this->handler->flushAllManagersAtomically();
        $this->connection1->shouldHaveReceived('beginTransaction');
        $this->connection1->shouldHaveReceived('commit');
        $this->connection1->shouldNotHaveReceived('rollBack');
        $this->connection2->shouldHaveReceived('beginTransaction');
        $this->connection2->shouldHaveReceived('commit');
        $this->connection2->shouldNotHaveReceived('rollBack');
        $this->entityManager1->shouldHaveReceived('flush');
        $this->entityManager2->shouldHaveReceived('flush');
    }

    public function testItRollsBackIfItFailsToFlush()
    {
        $this->entityManager2
            ->shouldReceive('flush')
            ->andThrow(new ORMException);
        try {
            $this->handler->flushAllManagersAtomically();
        } catch (RollbackException $e) {
            $this->connection1->shouldHaveReceived('beginTransaction');
            $this->connection1->shouldNotHaveReceived('commit');
            $this->connection1->shouldHaveReceived('rollBack');
            $this->connection2->shouldHaveReceived('beginTransaction');
            $this->connection2->shouldNotHaveReceived('commit');
            $this->connection2->shouldHaveReceived('rollBack');
            $this->entityManager1->shouldHaveReceived('flush');
            $this->entityManager2->shouldHaveReceived('flush');
        }
    }

    public function testItRollsBackIfItFailsToCommit()
    {
        $this->entityManager2
            ->shouldReceive('commit')
            ->andThrow(new ORMException);
        try {
            $this->handler->flushAllManagersAtomically();
        } catch (RollbackException $e) {
            $this->connection1->shouldHaveReceived('beginTransaction');
            $this->connection1->shouldHaveReceived('commit');
            $this->connection1->shouldHaveReceived('rollBack');
            $this->connection2->shouldHaveReceived('beginTransaction');
            $this->connection2->shouldHaveReceived('commit');
            $this->connection2->shouldHaveReceived('rollBack');
            $this->entityManager1->shouldHaveReceived('flush');
            $this->entityManager2->shouldHaveReceived('flush');
        }
    }

    public function testItThrowsExceptionIfItFailsToRollback()
    {
        $this->connection2
            ->shouldReceive('commit')
            ->andThrow(new ORMException);
        $this->connection2
            ->shouldReceive('rollBack')
            ->andThrow(new ConnectionException);
        try {
            $this->handler->flushAllManagersAtomically();
        } catch (RollbackFailedException $e) {
            $this->connection1->shouldHaveReceived('beginTransaction');
            $this->connection1->shouldHaveReceived('commit');
            $this->connection1->shouldHaveReceived('rollBack');
            $this->connection2->shouldHaveReceived('beginTransaction');
            $this->connection2->shouldHaveReceived('commit');
            $this->connection2->shouldHaveReceived('rollBack');
            $this->entityManager1->shouldHaveReceived('flush');
            $this->entityManager2->shouldHaveReceived('flush');
        }
    }

    public function testItClearsEntityManagers()
    {
        $this->handler->clearAllManagers();
        $this->entityManager1->shouldHaveReceived('clear');
        $this->entityManager2->shouldHaveReceived('clear');
    }
}

class Handler
{
    use HandlesMultipleEntityManagers;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->setManagerRegistry($managerRegistry);
    }
}
