<?php

namespace Oro\Bundle\EntityBundle\Tests\Unit\ORM;

use Oro\Bundle\EntityBundle\ORM\DoctrineHelper;
use Oro\Bundle\EntityBundle\Tests\Unit\ORM\Fixtures\TestEntity;
use Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub;
use Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\__CG__\ItemStubProxy;

class DoctrineHelperTest extends \PHPUnit_Framework_TestCase
{
    const TEST_IDENTIFIER = 42;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $registry;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $em;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    protected $classMetadata;

    /**
     * @var DoctrineHelper
     */
    protected $doctrineHelper;

    protected function setUp()
    {
        $this->registry      = $this->getMockBuilder('Doctrine\Common\Persistence\ManagerRegistry')
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $this->em            = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->disableOriginalConstructor()
            ->getMock();
        $this->classMetadata = $this->getMockBuilder('Doctrine\ORM\Mapping\ClassMetadata')
            ->disableOriginalConstructor()
            ->getMock();

        $this->doctrineHelper = new DoctrineHelper($this->registry);
    }

    protected function tearDown()
    {
        unset($this->registry);
        unset($this->em);
        unset($this->classMetadata);
        unset($this->doctrineHelper);
    }

    /**
     * @param object|string $entityOrClass
     * @param string        $expectedClass
     * @dataProvider getEntityClassDataProvider
     */
    public function testGetEntityClass($entityOrClass, $expectedClass)
    {
        $this->assertEquals(
            $expectedClass,
            $this->doctrineHelper->getEntityClass($entityOrClass)
        );
    }

    /**
     * @return array
     */
    public function getEntityClassDataProvider()
    {
        return array(
            'existing entity'    => array(
                'entity'        => new ItemStub(),
                'expectedClass' => 'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub',
            ),
            'entity proxy'       => array(
                'entity'        => new ItemStubProxy(),
                'expectedClass' => 'ItemStubProxy',
            ),
            'real entity class'  => array(
                'entity'        => 'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub',
                'expectedClass' => 'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub',
            ),
            'proxy entity class' => array(
                'entity'        => 'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\__CG__\ItemStubProxy',
                'expectedClass' => 'ItemStubProxy',
            ),
        );
    }

    public function testGetEntityIdentifierWithGetIdMethod()
    {
        $identifiers = array('id' => self::TEST_IDENTIFIER);

        $entity = new TestEntity($identifiers['id']);

        $this->registry->expects($this->never())
            ->method('getManagerForClass');

        $this->assertEquals(
            $identifiers,
            $this->doctrineHelper->getEntityIdentifier($entity)
        );
    }

    /**
     * @param object $entity
     * @param string $class
     * @dataProvider getEntityIdentifierDataProvider
     */
    public function testGetEntityIdentifier($entity, $class)
    {
        $identifiers = array('id' => self::TEST_IDENTIFIER);

        $this->classMetadata->expects($this->once())
            ->method('getIdentifierValues')
            ->with($entity)
            ->will($this->returnValue($identifiers));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->assertEquals(
            $identifiers,
            $this->doctrineHelper->getEntityIdentifier($entity)
        );
    }

    public function testGetEntityIdentifierNotManageableEntity()
    {
        $entity = $this->getMock('FooEntity');

        $this->setExpectedException(
            'Oro\Bundle\EntityBundle\Exception\NotManageableEntityException',
            sprintf('Entity class "%s" is not manageable', get_class($entity))
        );

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(get_class($entity))
            ->will($this->returnValue(null));

        $this->doctrineHelper->getEntityIdentifier($entity);
    }

    /**
     * @return array
     */
    public function getEntityIdentifierDataProvider()
    {
        return array(
            'existing entity' => array(
                'entity' => new ItemStub(),
                'class'  => 'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub',
            ),
            'entity proxy'    => array(
                'entity' => new ItemStubProxy(),
                'class'  => 'ItemStubProxy',
            ),
        );
    }

    /**
     * @param       $expected
     * @param array $identifiers
     * @param bool  $exception
     * @dataProvider getSingleEntityIdentifierDataProvider
     */
    public function testGetSingleEntityIdentifier($expected, array $identifiers, $exception = true)
    {
        $entity = new ItemStubProxy();
        $class  = 'ItemStubProxy';

        $this->classMetadata->expects($this->once())
            ->method('getIdentifierValues')
            ->with($entity)
            ->will($this->returnValue($identifiers));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->assertEquals(
            $expected,
            $this->doctrineHelper->getSingleEntityIdentifier($entity, $exception)
        );
    }

    public function getSingleEntityIdentifierDataProvider()
    {
        return array(
            'valid identifier'                  => array(
                'expected' => self::TEST_IDENTIFIER,
                'actual'   => array('id' => self::TEST_IDENTIFIER),
            ),
            'empty identifier'                  => array(
                'expected' => null,
                'actual'   => array(),
            ),
            'multiple identifier, no exception' => array(
                'expected'  => null,
                'actual'    => array('first_id' => 1, 'second_id' => 2),
                'exception' => false,
            ),
        );
    }

    /**
     * @expectedException \Oro\Bundle\EntityBundle\Exception\InvalidEntityException
     * @expectedExceptionMessage Can't get single identifier for the entity
     */
    public function testGetSingleEntityIdentifierIncorrectIdentifier()
    {
        $identifiers = array('key1' => 'value1', 'key2' => 'value2');

        $entity = new ItemStubProxy();
        $class  = 'ItemStubProxy';

        $this->classMetadata->expects($this->once())
            ->method('getIdentifierValues')
            ->with($entity)
            ->will($this->returnValue($identifiers));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->doctrineHelper->getSingleEntityIdentifier($entity);
    }

    /**
     * @param object $entity
     * @param string $class
     * @dataProvider getEntityIdentifierFieldNamesDataProvider
     */
    public function testGetEntityIdentifierFieldNames($entity, $class)
    {
        $identifiers = array('id' => self::TEST_IDENTIFIER);

        $this->classMetadata->expects($this->any())
            ->method('getIdentifierFieldNames')
            ->will($this->returnValue(array_keys($identifiers)));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->assertEquals(
            array_keys($identifiers),
            $this->doctrineHelper->getEntityIdentifierFieldNames($entity)
        );
    }

    public function testGetEntityIdentifierFieldNamesNotManageableEntity()
    {
        $entity = $this->getMock('FooEntity');

        $this->setExpectedException(
            'Oro\Bundle\EntityBundle\Exception\NotManageableEntityException',
            sprintf('Entity class "%s" is not manageable', get_class($entity))
        );

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(get_class($entity))
            ->will($this->returnValue(null));

        $this->doctrineHelper->getEntityIdentifierFieldNames($entity);
    }

    /**
     * @return array
     */
    public function getEntityIdentifierFieldNamesDataProvider()
    {
        return array(
            'existing entity' => array(
                'entity' => new ItemStub(),
                'class'  => 'Oro\Bundle\EntityBundle\Tests\Unit\ORM\Stub\ItemStub',
            ),
            'entity proxy'    => array(
                'entity' => new ItemStubProxy(),
                'class'  => 'ItemStubProxy',
            ),
        );
    }

    /**
     * @param       $expected
     * @param array $identifiers
     * @param bool  $exception
     * @dataProvider getSingleEntityIdentifierFieldNameDataProvider
     */
    public function testGetSingleEntityIdentifierFieldName($expected, array $identifiers, $exception = true)
    {
        $entity = new ItemStubProxy();
        $class  = 'ItemStubProxy';

        $this->classMetadata->expects($this->any())
            ->method('getIdentifierFieldNames')
            ->will($this->returnValue(array_keys($identifiers)));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->assertEquals(
            $expected,
            $this->doctrineHelper->getSingleEntityIdentifierFieldName($entity, $exception)
        );
    }

    public function getSingleEntityIdentifierFieldNameDataProvider()
    {
        return array(
            'valid identifier'                  => array(
                'expected' => 'id',
                'actual'   => array('id' => self::TEST_IDENTIFIER),
            ),
            'empty identifier'                  => array(
                'expected' => null,
                'actual'   => array(),
            ),
            'multiple identifier, no exception' => array(
                'expected'  => null,
                'actual'    => array('first_id' => 1, 'second_id' => 2),
                'exception' => false,
            ),
        );
    }

    /**
     * @expectedException \Oro\Bundle\EntityBundle\Exception\InvalidEntityException
     * @expectedExceptionMessage Can't get single identifier field name for the entity
     */
    public function testGetSingleEntityIdentifierIncorrectIdentifierFieldName()
    {
        $identifiers = array('key1' => 'value1', 'key2' => 'value2');

        $entity = new ItemStubProxy();
        $class  = 'ItemStubProxy';

        $this->classMetadata->expects($this->any())
            ->method('getIdentifierFieldNames')
            ->will($this->returnValue(array_keys($identifiers)));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->doctrineHelper->getSingleEntityIdentifierFieldName($entity);
    }

    public function testIsManageableEntity()
    {
        $entity = new ItemStubProxy();

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($this->doctrineHelper->getEntityClass($entity))
            ->will($this->returnValue($this->em));

        $this->assertTrue(
            $this->doctrineHelper->isManageableEntity($entity)
        );
    }

    public function testIsManageableEntityForNotManageableEntity()
    {
        $entity = new ItemStubProxy();

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($this->doctrineHelper->getEntityClass($entity))
            ->will($this->returnValue(null));

        $this->assertFalse(
            $this->doctrineHelper->isManageableEntity($entity)
        );
    }

    public function testGetEntityMetadataByEntity()
    {
        $entity = new ItemStubProxy();
        $class  = 'ItemStubProxy';

        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($this->doctrineHelper->getEntityClass($entity))
            ->will($this->returnValue($this->em));

        $this->assertSame(
            $this->classMetadata,
            $this->doctrineHelper->getEntityMetadata($entity)
        );
    }

    public function testGetEntityMetadataByClass()
    {
        $class = 'ItemStubProxy';

        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->assertSame(
            $this->classMetadata,
            $this->doctrineHelper->getEntityMetadata($class)
        );
    }

    public function testGetEntityMetadataNotManageableEntity()
    {
        $class = 'ItemStubProxy';

        $this->setExpectedException(
            'Oro\Bundle\EntityBundle\Exception\NotManageableEntityException',
            sprintf('Entity class "%s" is not manageable', $class)
        );

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue(null));

        $this->doctrineHelper->getEntityMetadata($class);
    }

    public function testGetEntityManagerByEntity()
    {
        $entity = new ItemStubProxy();

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($this->doctrineHelper->getEntityClass($entity))
            ->will($this->returnValue($this->em));

        $this->assertSame(
            $this->em,
            $this->doctrineHelper->getEntityManager($entity)
        );
    }

    public function testGetEntityManagerByClass()
    {
        $class = 'ItemStubProxy';

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->assertSame(
            $this->em,
            $this->doctrineHelper->getEntityManager($class)
        );
    }

    public function testGetEntityManagerNotManageableEntity()
    {
        $class = 'ItemStubProxy';

        $this->setExpectedException(
            'Oro\Bundle\EntityBundle\Exception\NotManageableEntityException',
            sprintf('Entity class "%s" is not manageable', $class)
        );

        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue(null));

        $this->doctrineHelper->getEntityManager($class);
    }

    public function testGetEntityReference()
    {
        $expectedResult = $this->getMock('MockEntityReference');
        $entityClass    = 'MockEntity';
        $entityId       = 100;

        $this->em->expects($this->once())->method('getReference')
            ->with($entityClass, $entityId)
            ->will($this->returnValue($expectedResult));
        $this->registry->expects($this->any())
            ->method('getManagerForClass')
            ->with($entityClass)
            ->will($this->returnValue($this->em));

        $this->assertEquals(
            $expectedResult,
            $this->doctrineHelper->getEntityReference($entityClass, $entityId)
        );
    }

    public function testGetEntity()
    {
        $expectedResult = new TestEntity();
        $entityClass    = 'MockEntity';
        $entityId       = 100;

        $repo = $this->getMockBuilder('Doctrine\ORM\EntityRepository')
            ->disableOriginalConstructor()
            ->getMock();
        $repo->expects($this->once())
            ->method('find')
            ->with($entityId)
            ->will($this->returnValue($expectedResult));

        $this->em->expects($this->once())
            ->method('getRepository')
            ->with($entityClass)
            ->will($this->returnValue($repo));
        $this->registry->expects($this->any())
            ->method('getManagerForClass')
            ->with($entityClass)
            ->will($this->returnValue($this->em));

        $this->assertSame(
            $expectedResult,
            $this->doctrineHelper->getEntity($entityClass, $entityId)
        );
    }

    public function testCreateEntityInstance()
    {
        $entity = new ItemStubProxy();
        $class  = 'ItemStubProxy';

        $this->classMetadata->expects($this->once())
            ->method('newInstance')
            ->will($this->returnValue($entity));
        $this->em->expects($this->once())
            ->method('getClassMetadata')
            ->with($class)
            ->will($this->returnValue($this->classMetadata));
        $this->registry->expects($this->once())
            ->method('getManagerForClass')
            ->with($class)
            ->will($this->returnValue($this->em));

        $this->assertSame(
            $entity,
            $this->doctrineHelper->createEntityInstance($class)
        );
    }
}
