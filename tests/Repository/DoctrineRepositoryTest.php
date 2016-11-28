<?php

namespace Chubbyphp\Tests\Model\Doctrine\DBAL\Repository;

use Chubbyphp\Model\Cache\ModelCacheInterface;
use Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository;
use Chubbyphp\Model\ModelInterface;
use Chubbyphp\Tests\Model\Resources\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * @covers Chubbyphp\Model\Doctrine\DBAL\Repository\AbstractDoctrineRepository
 */
final class DoctrineRepositoryTest extends \PHPUnit_Framework_TestCase
{
    public function testFindNotFound()
    {
        $queryBuilder = $this->getQueryBuilder([
            $this->getStatement(\PDO::FETCH_ASSOC, false),
        ]);

        $cache = $this->getCache();
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(['queryBuilder' => [$queryBuilder]]),
            $cache,
            $logger,
            User::class,
            'users'
        );

        self::assertNull($repository->find('id1'));

        self::assertEquals(
            [
                'select' => [
                    [
                        '*',
                    ],
                ],
                'from' => [
                    [
                        'users',
                        null,
                    ],
                ],
                'where' => [
                    [
                        [
                            'method' => 'eq',
                            'arguments' => [
                                'id',
                                ':id',
                            ],
                        ],
                    ],
                ],
                'setParameter' => [
                    [
                        'id',
                        'id1',
                        null,
                    ],
                ],
            ],
            $queryBuilder->__calls
        );

        self::assertCount(0, $cache->__cache);

        self::assertCount(2, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find model {model} with id {id}', $logger->__logs[0]['message']);
        self::assertSame(['model' => User::class, 'id' => 'id1'], $logger->__logs[0]['context']);
        self::assertSame(LogLevel::WARNING, $logger->__logs[1]['level']);
        self::assertSame('model: model {model} with id {id} not found', $logger->__logs[1]['message']);
        self::assertSame(['model' => User::class, 'id' => 'id1'], $logger->__logs[1]['context']);
    }

    public function testFindFound()
    {
        $queryBuilder = $this->getQueryBuilder([
            $this->getStatement(\PDO::FETCH_ASSOC, [
                'id' => 'id1',
                'username' => 'username',
                'password' => 'password',
                'active' => true,
            ]),
        ]);

        $cache = $this->getCache();
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(['queryBuilder' => [$queryBuilder]]),
            $cache,
            $logger,
            User::class,
            'users'
        );

        /** @var User $user */
        $user = $repository->find('id1');

        self::assertInstanceOf(User::class, $user);

        self::assertSame('id1', $user->getId());
        self::assertSame('username', $user->getUsername());
        self::assertSame('password', $user->getPassword());
        self::assertTrue($user->isActive());

        self::assertEquals(
            [
                'select' => [
                    [
                        '*',
                    ],
                ],
                'from' => [
                    [
                        'users',
                        null,
                    ],
                ],
                'where' => [
                    [
                        [
                            'method' => 'eq',
                            'arguments' => [
                                'id',
                                ':id',
                            ],
                        ],
                    ],
                ],
                'setParameter' => [
                    [
                        'id',
                        'id1',
                        null,
                    ],
                ],
            ],
            $queryBuilder->__calls
        );

        self::assertCount(1, $cache->__cache);
        self::assertArrayHasKey('id1', $cache->__cache);
        self::assertInstanceOf(ModelInterface::class, $cache->__cache['id1']);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find model {model} with id {id}', $logger->__logs[0]['message']);
        self::assertSame(['model' => User::class, 'id' => 'id1'], $logger->__logs[0]['context']);
    }

    public function testFindFoundWithinCache()
    {
        $data = [
            'id' => 'id1',
            'username' => 'username',
            'password' => 'password',
            'active' => true,
        ];

        $queryBuilder = $this->getQueryBuilder([
            $this->getStatement(\PDO::FETCH_ASSOC, $data),
        ]);

        $cache = $this->getCache(['id1' => User::fromPersistence($data)]);
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(['queryBuilder' => [$queryBuilder]]),
            $cache,
            $logger,
            User::class,
            'users'
        );

        /** @var User $user */
        $user = $repository->find('id1');

        self::assertInstanceOf(User::class, $user);

        self::assertSame('id1', $user->getId());
        self::assertSame('username', $user->getUsername());
        self::assertSame('password', $user->getPassword());
        self::assertTrue($user->isActive());

        self::assertCount(0, $queryBuilder->__calls);

        self::assertCount(1, $cache->__cache);
        self::assertInstanceOf(ModelInterface::class, $cache->__cache['id1']);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find model {model} with id {id}', $logger->__logs[0]['message']);
        self::assertSame(['model' => User::class, 'id' => 'id1'], $logger->__logs[0]['context']);
    }

    public function testFindOneByNotFound()
    {
        $queryBuilder = $this->getQueryBuilder([
            $this->getStatement(\PDO::FETCH_ASSOC, false),
        ]);

        $cache = $this->getCache();
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(['queryBuilder' => [$queryBuilder]]),
            $cache,
            $logger,
            User::class,
            'users'
        );

        self::assertNull($repository->findOneBy(['username' => 'user1']));

        self::assertEquals(
            [
                'select' => [
                    [
                        '*',
                    ],
                ],
                'from' => [
                    [
                        'users',
                        null,
                    ],
                ],
                'andWhere' => [
                    [
                        [
                            'method' => 'eq',
                            'arguments' => [
                                'username',
                                ':username',
                            ],
                        ],
                    ],
                ],
                'setParameter' => [
                    [
                        'username',
                        'user1',
                        null,
                    ],
                ],
                'setMaxResults' => [
                    [
                        1,
                    ],
                ],
            ],
            $queryBuilder->__calls
        );

        self::assertCount(0, $cache->__cache);

        self::assertCount(2, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find model {model} with criteria {criteria}', $logger->__logs[0]['message']);
        self::assertSame(['model' => User::class, 'criteria' => ['username' => 'user1']], $logger->__logs[0]['context']);
        self::assertSame(LogLevel::WARNING, $logger->__logs[1]['level']);
        self::assertSame('model: model {model} with criteria {criteria} not found', $logger->__logs[1]['message']);
        self::assertSame(['model' => User::class, 'criteria' => ['username' => 'user1']], $logger->__logs[1]['context']);
    }

    public function testFindOneByFound()
    {
        $queryBuilder = $this->getQueryBuilder([
            $this->getStatement(\PDO::FETCH_ASSOC, [
                'id' => 'id1',
                'username' => 'user1',
                'password' => 'password',
                'active' => true,
            ]),
        ]);

        $cache = $this->getCache();
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(['queryBuilder' => [$queryBuilder]]),
            $cache,
            $logger,
            User::class,
            'users'
        );

        /** @var User $user */
        $user = $repository->findOneBy(['username' => 'user1']);

        self::assertInstanceOf(User::class, $user);

        self::assertSame('id1', $user->getId());
        self::assertSame('user1', $user->getUsername());
        self::assertSame('password', $user->getPassword());
        self::assertTrue($user->isActive());

        self::assertEquals(
            [
                'select' => [
                    [
                        '*',
                    ],
                ],
                'from' => [
                    [
                        'users',
                        null,
                    ],
                ],
                'andWhere' => [
                    [
                        [
                            'method' => 'eq',
                            'arguments' => [
                                'username',
                                ':username',
                            ],
                        ],
                    ],
                ],
                'setParameter' => [
                    [
                        'username',
                        'user1',
                        null,
                    ],
                ],
                'setMaxResults' => [
                    [
                        1,
                    ],
                ],
            ],
            $queryBuilder->__calls
        );

        self::assertCount(0, $cache->__cache);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find model {model} with criteria {criteria}', $logger->__logs[0]['message']);
        self::assertSame(['model' => User::class, 'criteria' => ['username' => 'user1']], $logger->__logs[0]['context']);
    }

    public function testFindByNotFound()
    {
        $queryBuilder = $this->getQueryBuilder([
            $this->getStatement(\PDO::FETCH_ASSOC, []),
        ]);

        $cache = $this->getCache();
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(['queryBuilder' => [$queryBuilder]]),
            $cache,
            $logger,
            User::class,
            'users'
        );

        self::assertSame([], $repository->findBy(['active' => true]));

        self::assertEquals(
            [
                'select' => [
                    [
                        '*',
                    ],
                ],
                'from' => [
                    [
                        'users',
                        null,
                    ],
                ],
                'andWhere' => [
                    [
                        [
                            'method' => 'eq',
                            'arguments' => [
                                'active',
                                ':active',
                            ],
                        ],
                    ],
                ],
                'setParameter' => [
                    [
                        'active',
                        true,
                        null,
                    ],
                ],
            ],
            $queryBuilder->__calls
        );

        self::assertCount(0, $cache->__cache);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find model {model} with criteria {criteria}', $logger->__logs[0]['message']);
        self::assertSame(['model' => User::class, 'criteria' => ['active' => true]], $logger->__logs[0]['context']);
    }

    public function testFindByFound()
    {
        $queryBuilder = $this->getQueryBuilder([
            $this->getStatement(\PDO::FETCH_ASSOC, [
                [
                    'id' => 'id1',
                    'username' => 'user1',
                    'password' => 'password',
                    'active' => true,
                ],
                [
                    'id' => 'id2',
                    'username' => 'user2',
                    'password' => 'password',
                    'active' => true,
                ],
            ]),
        ]);

        $cache = $this->getCache();
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(['queryBuilder' => [$queryBuilder]]),
            $cache,
            $logger,
            User::class,
            'users'
        );

        $users = $repository->findBy(['active' => true]);

        self::assertCount(2, $users);

        self::assertInstanceOf(User::class, $users[0]);

        self::assertSame('id1', $users[0]->getId());
        self::assertSame('user1', $users[0]->getUsername());
        self::assertSame('password', $users[0]->getPassword());
        self::assertTrue($users[0]->isActive());

        self::assertInstanceOf(User::class, $users[1]);

        self::assertSame('id2', $users[1]->getId());
        self::assertSame('user2', $users[1]->getUsername());
        self::assertSame('password', $users[1]->getPassword());
        self::assertTrue($users[1]->isActive());

        self::assertEquals(
            [
                'select' => [
                    [
                        '*',
                    ],
                ],
                'from' => [
                    [
                        'users',
                        null,
                    ],
                ],
                'andWhere' => [
                    [
                        [
                            'method' => 'eq',
                            'arguments' => [
                                'active',
                                ':active',
                            ],
                        ],
                    ],
                ],
                'setParameter' => [
                    [
                        'active',
                        true,
                        null,
                    ],
                ],
            ],
            $queryBuilder->__calls
        );

        self::assertCount(0, $cache->__cache);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: find model {model} with criteria {criteria}', $logger->__logs[0]['message']);
        self::assertSame(['model' => User::class, 'criteria' => ['active' => true]], $logger->__logs[0]['context']);
    }

    public function testInsert()
    {
        $cache = $this->getCache();
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(
                [
                    'insert' => [
                        [
                            'arguments' => [
                                'users',
                                [
                                    'id' => 'id1',
                                    'username' => 'user1',
                                    'password' => 'password',
                                    'active' => true,
                                ],
                                [],
                            ],
                            'return' => 1,
                        ],
                    ],
                ]
            ),
            $cache,
            $logger,
            User::class,
            'users'
        );

        $user = new User('id1');
        $user->setUsername('user1');
        $user->setPassword('password');
        $user->setActive(true);

        $repository->insert($user);

        self::assertCount(1, $cache->__cache);
        self::assertArrayHasKey('id1', $cache->__cache);
        self::assertInstanceOf(ModelInterface::class, $cache->__cache['id1']);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: insert model {model} with id {id}', $logger->__logs[0]['message']);
        self::assertSame(['model' => User::class, 'id' => 'id1'], $logger->__logs[0]['context']);
    }

    public function testUpdate()
    {
        $cache = $this->getCache();
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(
                [
                    'update' => [
                        [
                            'arguments' => [
                                'users',
                                [
                                    'id' => 'id1',
                                    'username' => 'user1',
                                    'password' => 'password',
                                    'active' => true,
                                ],
                                [
                                    'id' => 'id1',
                                ],
                                [],
                            ],
                            'return' => 1,
                        ],
                    ],
                ]
            ),
            $cache,
            $logger,
            User::class,
            'users'
        );

        $user = new User('id1');
        $user->setUsername('user1');
        $user->setPassword('password');
        $user->setActive(true);

        $repository->update($user);

        self::assertCount(1, $cache->__cache);
        self::assertArrayHasKey('id1', $cache->__cache);
        self::assertInstanceOf(ModelInterface::class, $cache->__cache['id1']);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: update model {model} with id {id}', $logger->__logs[0]['message']);
        self::assertSame(['model' => User::class, 'id' => 'id1'], $logger->__logs[0]['context']);
    }

    public function testRemove()
    {
        $cache = $this->getCache();
        $logger = $this->getLogger();

        $repository = $this->getDoctrineRepository(
            $this->getConnection(
                [
                    'delete' => [
                        [
                            'arguments' => [
                                'users',
                                [
                                    'id' => 'id1',
                                ],
                                [],
                            ],
                            'return' => 1,
                        ],
                    ],
                ]
            ),
            $cache,
            $logger,
            User::class,
            'users'
        );

        $user = new User('id1');
        $user->setUsername('user1');
        $user->setPassword('password');
        $user->setActive(true);

        $repository->remove($user);

        self::assertCount(0, $cache->__cache);

        self::assertCount(1, $logger->__logs);
        self::assertSame(LogLevel::INFO, $logger->__logs[0]['level']);
        self::assertSame('model: remove model {model} with id {id}', $logger->__logs[0]['message']);
        self::assertSame(['model' => User::class, 'id' => 'id1'], $logger->__logs[0]['context']);
    }

    /**
     * @param Connection          $connection
     * @param ModelCacheInterface $cache
     * @param LoggerInterface     $logger
     * @param string              $modelClass
     * @param string              $table
     *
     * @return AbstractDoctrineRepository
     */
    private function getDoctrineRepository(
        Connection $connection,
        ModelCacheInterface $cache,
        LoggerInterface $logger,
        string $modelClass,
        string $table
    ): AbstractDoctrineRepository {
        /** @var AbstractDoctrineRepository|\PHPUnit_Framework_MockObject_MockObject $repository */
        $repository = $this
            ->getMockBuilder(AbstractDoctrineRepository::class)
            ->setConstructorArgs([$connection, $cache, $logger])
            ->setMethods(['getModelClass', 'getTable'])
            ->getMockForAbstractClass();

        $repository->expects(self::any())->method('getModelClass')->willReturn($modelClass);
        $repository->expects(self::any())->method('getTable')->willReturn($table);

        return $repository;
    }

    /**
     * @param array $stacks
     *
     * @return Connection
     */
    private function getConnection(array $stacks = []): Connection
    {
        /* @var Connection|\PHPUnit_Framework_MockObject_MockObject $connection */
        $repository = $this
            ->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->setMethods(['createQueryBuilder', 'insert', 'update', 'delete'])
            ->getMockForAbstractClass();

        $queryBuilderStack = $stacks['queryBuilder'] ?? [];
        $insertStack = $stacks['insert'] ?? [];
        $updateStack = $stacks['update'] ?? [];
        $deleteStack = $stacks['delete'] ?? [];

        $queryBuilderCounter = 0;

        $repository
            ->expects(self::any())
            ->method('createQueryBuilder')
            ->willReturnCallback(function () use (&$queryBuilderStack, &$queryBuilderCounter) {
                ++$queryBuilderCounter;

                $queryBuilder = array_shift($queryBuilderStack);

                self::assertNotNull($queryBuilder,
                    sprintf(
                        'createQueryBuilder failed, cause there was no data within $queryBuilderStack at call %d',
                        $queryBuilderCounter
                    )
                );

                return $queryBuilder;
            });

        $insertStackCounter = 0;

        $repository
            ->expects(self::any())
            ->method('insert')
            ->willReturnCallback(
                function (
                    $tableExpression,
                    array $data,
                    array $types = []
                ) use (&$insertStack, &$insertStackCounter) {
                    ++$insertStackCounter;

                    $insert = array_shift($insertStack);

                    self::assertNotNull($insert,
                        sprintf(
                            'insert failed, cause there was no data within $insertStack at call %d',
                            $insertStack
                        )
                    );

                    self::assertSame($insert['arguments'][0], $tableExpression);
                    self::assertSame($insert['arguments'][1], $data);
                    self::assertSame($insert['arguments'][2], $types);

                    return $insert['return'];
                }
            );

        $updateStackCounter = 0;

        $repository
            ->expects(self::any())
            ->method('update')
            ->willReturnCallback(
                function (
                    $tableExpression,
                    array $data,
                    array $identifier,
                    array $types = []
                ) use (&$updateStack, &$updateStackCounter) {
                    ++$updateStackCounter;

                    $update = array_shift($updateStack);

                    self::assertNotNull($update,
                        sprintf(
                            'update failed, cause there was no data within $updateStack at call %d',
                            $updateStack
                        )
                    );

                    self::assertSame($update['arguments'][0], $tableExpression);
                    self::assertSame($update['arguments'][1], $data);
                    self::assertSame($update['arguments'][2], $identifier);
                    self::assertSame($update['arguments'][3], $types);

                    return $update['return'];
                }
            );

        $deleteStackCounter = 0;

        $repository
            ->expects(self::any())
            ->method('delete')
            ->willReturnCallback(
                function (
                    $tableExpression,
                    array $identifier,
                    array $types = []
                ) use (&$deleteStack, &$deleteStackCounter) {
                    ++$deleteStackCounter;

                    $delete = array_shift($deleteStack);

                    self::assertNotNull($delete,
                        sprintf(
                            'delete failed, cause there was no data within $deleteStack at call %d',
                            $deleteStack
                        )
                    );

                    self::assertSame($delete['arguments'][0], $tableExpression);
                    self::assertSame($delete['arguments'][1], $identifier);
                    self::assertSame($delete['arguments'][2], $types);

                    return $delete['return'];
                }
            );

        return $repository;
    }

    /**
     * @param array $executeStack
     *
     * @return QueryBuilder
     */
    private function getQueryBuilder(array $executeStack): QueryBuilder
    {
        $modifiers = [
            'setParameter',
            'setParameters',
            'setFirstResult',
            'setMaxResults',
            'add',
            'select',
            'addSelect',
            'delete',
            'update',
            'insert',
            'from',
            'innerJoin',
            'leftJoin',
            'rightJoin',
            'set',
            'where',
            'andWhere',
            'orWhere',
            'groupBy',
            'addGroupBy',
            'setValue',
            'values',
            'having',
            'andHaving',
            'orHaving',
            'orderBy',
            'addOrderBy',
            'resetQueryParts',
            'resetQueryPart',
        ];

        /** @var QueryBuilder|\PHPUnit_Framework_MockObject_MockObject $queryBuilder */
        $queryBuilder = $this
            ->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->setMethods(array_merge($modifiers, ['expr', 'execute']))
            ->getMockForAbstractClass();

        $queryBuilder->__calls = [];

        foreach ($modifiers as $modifier) {
            $queryBuilder
                ->expects(self::any())
                ->method($modifier)
                ->willReturnCallback(function () use ($queryBuilder, $modifier) {
                    if (!isset($queryBuilder->__calls[$modifier])) {
                        $queryBuilder->__calls[$modifier] = [];
                    }

                    $queryBuilder->__calls[$modifier][] = func_get_args();

                    return $queryBuilder;
                });
        }

        $queryBuilder
            ->expects(self::any())
            ->method('expr')
            ->willReturnCallback(function () {
                return $this->getExpressionBuilder();
            });

        $executeStackCounter = 0;

        $queryBuilder
            ->expects(self::any())
            ->method('execute')
            ->willReturnCallback(function () use ($queryBuilder, &$executeStack, &$executeStackCounter) {
                ++$executeStackCounter;

                $execute = array_shift($executeStack);

                self::assertNotNull($execute,
                    sprintf(
                        'execute failed, cause there was no data within $executeStack at call %d',
                        $executeStackCounter
                    )
                );

                return $execute;
            });

        return $queryBuilder;
    }

    /**
     * @return ExpressionBuilder
     */
    private function getExpressionBuilder(): ExpressionBuilder
    {
        $comparsions = [
            'andX',
            'orX',
            'comparison',
            'eq',
            'neq',
            'lt',
            'lte',
            'gt',
            'gte',
            'isNull',
            'isNotNull',
            'like',
            'notLike',
            'in',
            'notIn',
            'literal',
        ];

        /** @var ExpressionBuilder|\PHPUnit_Framework_MockObject_MockObject $expr */
        $expr = $this
            ->getMockBuilder(ExpressionBuilder::class)
            ->disableOriginalConstructor()
            ->setMethods($comparsions)
            ->getMockForAbstractClass();

        foreach ($comparsions as $comparsion) {
            $expr
                ->expects(self::any())
                ->method($comparsion)
                ->willReturnCallback(function () use ($comparsion) {
                    return ['method' => $comparsion, 'arguments' => func_get_args()];
                });
        }

        return $expr;
    }

    /**
     * @param int   $checkType
     * @param mixed $data
     *
     * @return Statement
     */
    private function getStatement(int $checkType, $data): Statement
    {
        /** @var Statement|\PHPUnit_Framework_MockObject_MockObject $stmt */
        $stmt = $this
            ->getMockBuilder(Statement::class)
            ->setMethods(['fetch', 'fetchAll'])
            ->getMockForAbstractClass();

        $stmt
            ->expects(self::any())
            ->method('fetch')
            ->willReturnCallback(function (int $type) use ($checkType, $data) {
                self::assertSame($checkType, $type);

                return $data;
            });

        $stmt
            ->expects(self::any())
            ->method('fetchAll')
            ->willReturnCallback(function (int $type) use ($checkType, $data) {
                self::assertSame($checkType, $type);

                return $data;
            });

        return $stmt;
    }

    /**
     * @param array $cacheData
     *
     * @return ModelCacheInterface
     */
    private function getCache(array $cacheData = []): ModelCacheInterface
    {
        /** @var ModelCacheInterface|\PHPUnit_Framework_MockObject_MockObject $cache */
        $cache = $this
            ->getMockBuilder(ModelCacheInterface::class)
            ->setMethods(['has', 'get', 'set', 'remove'])
            ->getMockForAbstractClass()
        ;

        $cache->__cache = $cacheData;

        $cache
            ->expects(self::any())
            ->method('has')
            ->willReturnCallback(function (string $id) use ($cache) {
                return array_key_exists($id, $cache->__cache);
            })
        ;

        $cache
            ->expects(self::any())
            ->method('get')
            ->willReturnCallback(function (string $id) use ($cache) {
                return $cache->__cache[$id];
            })
        ;

        $cache
            ->expects(self::any())
            ->method('set')
            ->willReturnCallback(function (ModelInterface $model) use ($cache) {
                $cache->__cache[$model->getId()] = $model;

                return $cache;
            })
        ;

        $cache
            ->expects(self::any())
            ->method('remove')
            ->willReturnCallback(function (string $id) use ($cache) {
                unset($cache->__cache[$id]);

                return $cache;
            })
        ;

        return $cache;
    }

    /**
     * @return LoggerInterface
     */
    private function getLogger(): LoggerInterface
    {
        $methods = [
            'emergency',
            'alert',
            'critical',
            'error',
            'warning',
            'notice',
            'info',
            'debug',
        ];

        /** @var LoggerInterface|\PHPUnit_Framework_MockObject_MockObject $logger */
        $logger = $this
            ->getMockBuilder(LoggerInterface::class)
            ->setMethods(array_merge($methods, ['log']))
            ->getMockForAbstractClass()
        ;

        $logger->__logs = [];

        foreach ($methods as $method) {
            $logger
                ->expects(self::any())
                ->method($method)
                ->willReturnCallback(
                    function (string $message, array $context = []) use ($logger, $method) {
                        $logger->log($method, $message, $context);
                    }
                )
            ;
        }

        $logger
            ->expects(self::any())
            ->method('log')
            ->willReturnCallback(
                function (string $level, string $message, array $context = []) use ($logger) {
                    $logger->__logs[] = ['level' => $level, 'message' => $message, 'context' => $context];
                }
            )
        ;

        return $logger;
    }
}