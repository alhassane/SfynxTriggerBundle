<?php
namespace Sfynx\TriggerBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\EventManager;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Persist Entities listener manager.
 * This event is called after an entity is constructed by the EntityManager.
 *
 * @subpackage   Core
 * @package    EventListener
 * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
 */
class EntitiesContainer
{
    /**
     * Metadata object that describes the mapping of the mapped entity class.
     *
     * @var \Doctrine\ORM\Mapping\ClassMetadata
     */
    protected $_class;

    /**
     * The name of the entity the persister is used for.
     *
     * @var string
     */
    protected $_entityName;

    /**
     * The list of the entity fields
     *
     * @var string
     */
    protected $_reflFields;

    /**
     * The Connection instance.
     *
     * @var Connection $conn
     */
    protected $_conn;

    /**
     * The EntityManager instance.
     *
     * @var EntityManagerInterface
     */
    protected $_em;

    /**
     * The EventManager instance.
     *
     * @var EventManager
     */
    protected $_evm;

    /**
     * Queued inserts.
     *
     * @var array
     */
    protected $_queuedInserts = [];

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * Constructs a new instance of SecurityListener.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Get the instance of the connection.
     *
     * @param \Doctrine\ORM\Event\LifecycleEventArgs $eventArgs
     *
     * @return Connection
     * @access public
     */
    public function getConnection($eventArgs)
    {
        $this->_em   = $eventArgs->getEntityManager();
        $this->_conn = $this->_em->getConnection();

        return $this->_conn;
    }

    /**
     * Add an entity in the persistEntities container.
     *
     * @param Object $entity
     *
     * @return void
     * @access public
     */
    public function addPersistEntities($entity)
    {
        $this->_queuedInserts[spl_object_hash($entity)] = $entity;
    }

    /**
     * Return all entities which are to be insert in the database.
     *
     * @return array    user roles
     * @access public
     */
    public function getPersistEntities()
    {
        return array_unique($this->_queuedInserts);
    }

    /**
     * Unset an entity in the queued.
     *
     * @param Object $entity
     *
     * @return boolean true if the entity has been deleted in the queued Entity
     * @access public
     */
    public function unsetPersistEntities($entity)
    {
        if (isset($this->_queuedInserts[spl_object_hash($entity)])){
            unset($this->_queuedInserts[spl_object_hash($entity)]);
            return true;
        }
        return false;
    }

    /**
     * Persist all entities which are in the persistEntities container.
     *
     * @param \Doctrine\ORM\Event\LifecycleEventArgs $eventArgs
     *
     * @return void
     * @access public
     */
    public function persistEntities($eventArgs)
    {
        $this->_em       = $eventArgs->getEntityManager();
        $this->_evm      = $this->_em->getEventManager();
        $this->_conn     = $this->_em->getConnection();
        $this->_platform = $this->_conn->getDatabasePlatform();

        foreach($this->getPersistEntities() as $hash_entity =>$entity)
        {
               $this->executeInsert($entity);
               $this->unsetPersistEntities($entity);
        }
    }

    /**
     * Executes all queued deletes.
     *
     * @param object $entity
     * @param array $Identifier
     * @return integer The number of affected rows.
     * @access private
     */
    protected function executeDelete($entity, array $Identifier)
    {
        if ( ! $this->_queuedDeletes) {
            return;
        }

        $this->_class      = $this->_em->getClassMetadata(get_class($entity));
        $this->_entityName = $this->_class->name;
        $this->_reflFields = $this->_class->reflFields;

        return $this->_conn->delete($this->getOwningTable(), $Identifier);
    }

    /**
     * Executes all queued inserts.
     *
     * @param    object    $entity
     * @return integer The number of affected rows.
     * @access private
     */
    protected function executeInsert($entity)
    {
        if ( ! $this->_queuedInserts) {
            return;
        }

        $this->_class         = $this->_em->getClassMetadata(get_class($entity));
        $this->_entityName     = $this->_class->name;
        $this->_reflFields     = $this->_class->reflFields;

        $data = $this->_prepareData($entity, true);

        return $this->_conn->insert($this->getOwningTable(), $data);
    }

    /**
     * Executes update.
     *
     * @param \Doctrine\ORM\Event\LifecycleEventArgs $eventArgs
     * @param    object    $entity
     * @param     array $identifier The update criteria. An associative array containing column-value pairs.
     * @return integer The number of affected rows.
     * @access private
     */
    public function executeUpdate($eventArgs, $entity, $Identifier)
    {
        $this->_em            = $eventArgs->getEntityManager();
        $this->_evm         = $this->_em->getEventManager();
        $this->_conn         = $this->_em->getConnection();
        $this->_platform     = $this->_conn->getDatabasePlatform();

        $this->_class         = $this->_em->getClassMetadata(get_class($entity));
        $this->_entityName     = $this->_class->name;
        $this->_reflFields     = $this->_class->reflFields;

        //$data = $this->_prepareData($entity, true);
        //foreach($Identifier as $key=>$value)
        //    unset($data[$key]);

        $data         = $this->_prepareUpdateData($entity);
        $id         = $this->_em->getUnitOfWork()->getEntityIdentifier($entity);
        $tableName     = $this->getOwningTable();

        if (isset($data[$tableName]) && $data[$tableName]) {
            return $this->_conn->update($this->getOwningTable(), $data, $Identifier);
        }
        return false;
    }

    /**
     * Prepares the data changeset of an entity for database insertion.
     *
     * @param object $entity
     * @param boolean $isInsert Whether the preparation is for an INSERT (or UPDATE, if FALSE).
     *
     * return The reference to the data array.
     * @access private
     */
    protected function _prepareData($entity, $isInsert = false)
    {
        $result   = [];
        $platform = $this->_conn->getDatabasePlatform();
        $uow      = $this->_em->getUnitOfWork();

        foreach($this->_reflFields as $field => $ReflectionProperty)
        {
            $newVal     = $this->_class->getFieldValue($entity, $field);
            $columnName = $this->_class->getColumnName($field);

            if (isset($this->_class->associationMappings[$field])) {
                $assocMapping = $this->_class->associationMappings[$field];

                // Only owning side of x-1 associations can have a FK column.
                if ( ! $assocMapping['isOwningSide'] ) {
                    continue;
                }

                // Special case: One-one self-referencing of the same class with IDENTITY type key generation.
                if ($this->_class->isIdGeneratorIdentity() && $newVal !== null &&
                        $assocMapping['sourceEntity'] == $assocMapping['targetEntity']) {
                    $oid = spl_object_hash($newVal);
                    $isScheduledForInsert = $uow->isScheduledForInsert($newVal);
                    if (isset($this->_queuedInserts[$oid]) || $isScheduledForInsert) {
                        // The associated entity $newVal is not yet persisted, so we must
                        // set $newVal = null, in order to insert a null value and schedule an
                        // extra update on the UnitOfWork.
                        $uow->scheduleExtraUpdate($entity, array(
                                $field => array(null, $newVal)
                        ));
                        $newVal = null;
                    } else if ($isInsert && ! $isScheduledForInsert && $uow->getEntityState($newVal) == UnitOfWork::STATE_MANAGED) {
                        // $newVal is already fully persisted.
                        // Schedule an extra update for it, so that the foreign key(s) are properly set.
                        $uow->scheduleExtraUpdate($newVal, array(
                                $field => array(null, $entity)
                        ));
                    }
                }

                foreach ($assocMapping['sourceToTargetKeyColumns'] as $sourceColumn => $targetColumn) {
                    if ($newVal === null) {
                        $result[$sourceColumn] = null;
                    } else {
                        $otherClass             = $this->_em->getClassMetadata($assocMapping['targetEntity']);
                        $result[$sourceColumn]     = $otherClass->reflFields[$otherClass->fieldNames[$targetColumn]]->getValue($newVal);
                    }
                }
            } else if ($newVal === null) {
                $result[$columnName] = null;
            } else {
                $result[$columnName] = Type::getType(
                        $this->_class->fieldMappings[$field]['type'])->convertToDatabaseValue($newVal, $platform);
            }
        }

        return $result;
    }

    /**
     * Gets the name of the table.
     *
     * @param null $eventArgs
     * @param null $entity
     * @return mixed
     */
    public function getOwningTable($eventArgs = null, $entity = null)
    {
        if (is_object($eventArgs) && is_object($entity)) {
            $this->_em    = $eventArgs->getEntityManager();
            $this->_class = $this->_em->getClassMetadata(get_class($entity));
        }

        return $this->_class->table['name'];
    }

    /**
     * Prepares the changeset of an entity for database insertion (UPDATE).
     *
     * The changeset is obtained from the currently running UnitOfWork.
     *
     * During this preparation the array that is passed as the second parameter is filled with
     * <columnName> => <value> pairs, grouped by table name.
     *
     * Example:
     * <code>
     * array(
     *    'foo_table' => array('column1' => 'value1', 'column2' => 'value2', ...),
     *    'bar_table' => array('columnX' => 'valueX', 'columnY' => 'valueY', ...),
     *    ...
     * )
     * </code>
     *
     * @param object $entity The entity for which to prepare the data.
     * @return array The prepared data.
     */
    protected function _prepareUpdateData($entity)
    {
        $result = [];
        $uow = $this->_em->getUnitOfWork();

        if (($versioned = $this->_class->isVersioned) != false) {
            $versionField = $this->_class->versionField;
        }

        foreach ($uow->getEntityChangeSet($entity) as $field => $change) {
            if ($versioned && $versionField == $field) {
                continue;
            }

            $oldVal = $change[0];
            $newVal = $change[1];

            if (isset($this->_class->associationMappings[$field])) {
                $assoc = $this->_class->associationMappings[$field];

                // Only owning side of x-1 associations can have a FK column.
                if ( ! $assoc['isOwningSide'] || ! ($assoc['type'] & \Doctrine\ORM\Mapping\ClassMetadata::TO_ONE)) {
                    continue;
                }

                if ($newVal !== null) {
                    $oid = spl_object_hash($newVal);

                    if (isset($this->_queuedInserts[$oid]) || $uow->isScheduledForInsert($newVal)) {
                        // The associated entity $newVal is not yet persisted, so we must
                        // set $newVal = null, in order to insert a null value and schedule an
                        // extra update on the UnitOfWork.
                        $uow->scheduleExtraUpdate($entity, array(
                                $field => array(null, $newVal)
                        ));
                        $newVal = null;
                    }
                }

                if ($newVal !== null) {
                    $newValId = $uow->getEntityIdentifier($newVal);
                }

                $targetClass = $this->_em->getClassMetadata($assoc['targetEntity']);
                $owningTable = $this->getOwningTable($field);

                foreach ($assoc['sourceToTargetKeyColumns'] as $sourceColumn => $targetColumn) {
                    if ($newVal === null) {
                        $result[$owningTable][$sourceColumn] = null;
                    } else if ($targetClass->containsForeignIdentifier) {
                        $result[$owningTable][$sourceColumn] = $newValId[$targetClass->getFieldForColumn($targetColumn)];
                    } else {
                        $result[$owningTable][$sourceColumn] = $newValId[$targetClass->fieldNames[$targetColumn]];
                    }

                    $this->_columnTypes[$sourceColumn] = $targetClass->getTypeOfColumn($targetColumn);
                }
            } else {
                $columnName = $this->_class->columnNames[$field];
                $this->_columnTypes[$columnName] = $this->_class->fieldMappings[$field]['type'];
                $result[$this->getOwningTable($field)][$columnName] = $newVal;
            }
        }

        return $result;
    }
}
