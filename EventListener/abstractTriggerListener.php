<?php
/**
 * This file is part of the <Trigger> project.
 *
 * @category   Trigger
 * @package    EventListener
 * @subpackage abstract
 * @author     Etienne de Longeaux <etienne.delongeaux@gmail.com>
 * @copyright  2015 PI-GROUPE
 * @license    http://opensource.org/licenses/gpl-license.php GNU Public License
 * @version    2.3
 * @link       http://opensource.org/licenses/gpl-license.php
 * @since      2015-02-16
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Sfynx\TriggerBundle\EventListener;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * abstract listener manager.
 * This event is called after an entity is constructed by the EntityManager.
 *
 * @subpackage Core
 * @package    EventListener
 * @abstract
 * @author     Etienne de Longeaux <etienne.delongeaux@gmail.com>
 */
abstract class abstractTriggerListener
{
    /**
     * @var array $associations
     */
    protected $associations = [];

    /**
     * @var array $discriminators
     */
    protected $discriminators = [];

    /**
     * @var array $discriminatorColumns
     */
    protected $discriminatorColumns = [];

    /**
     * @var array $inheritanceTypes
     */
    protected $inheritanceTypes = [];

    /**
     * @var array $doctrine
     */
    protected $doctrine = [];

    /**
     * @var array $indexes
     */
    protected $indexes = [];

    /**
     * @var array $associations
     */
    protected $uniques = [];

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var \Sfynx\TriggerBundle\EventListener\EntitiesContainer
     */
    private $EntitiesContainer;

    /**
     * @var UserStorage
     */
    protected $tokenStorage;

    /**
     * Constructor
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container         = $container;
        $this->EntitiesContainer = $container->get('sfynx.trigger.entities.listener');
        $this->tokenStorage = new UserStorage($this->container->get('security.token_storage'));
    }

    /**
     * Gets the name of the table.
     *
     * @return string the name of the table entity that we have to insert.
     * @access private
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    protected function getOwningTable($eventArgs, $entity)
    {
        return $this->EntitiesContainer->getOwningTable($eventArgs, $entity);
    }

    /**
     * Update a entity
     *
     * @param LifecycleEventArgs $eventArgs
     * @param object $entity
     * @param array array() The update criteria. An associative array containing column-value pairs.
     *
     * @return boolean
     * @access protected
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    protected function _updateEntity($eventArgs, $entity, $Identifier)
    {
        return $this->EntitiesContainer->executeUpdate($eventArgs, $entity, $Identifier);
    }

    /**
     * Update a entity
     *
     * @param LifecycleEventArgs $eventArgs
     * @param object $entity
     * @param array array() The update criteria. An associative array containing column-value pairs.
     *
     * @return boolean
     * @access protected
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    protected function _deleteEntity($entity, $Identifier)
    {
        return $this->EntitiesContainer->executeDelete($entity, $Identifier);
    }

    /**
     * Persist all entities which are in the persistEntities container.
     *
     * @param LifecycleEventArgs $eventArgs
     *
     * @return void
     * @access protected
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    protected function _persistEntities($eventArgs)
    {
        $this->EntitiesContainer->persistEntities($eventArgs);
    }

    /**
     * Persist all entities which are in the deleteEntities container.
     *
     * @param LifecycleEventArgs $eventArgs
     *
     * @return void
     * @access protected
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    protected function _deleteEntities($eventArgs)
    {
        $this->EntitiesContainer->deleteEntities($eventArgs);
    }

    /**
     * Add an entity in the persistEntities container.
     *
     * @param Object $entity
     *
     * @return void
     * @access protected
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    protected function _addPersistEntities($entity)
    {
        $this->EntitiesContainer->addPersistEntities($entity);
    }

    /**
     * Add an entity in the deleteEntities container.
     *
     * @param Object $entity
     *
     * @return void
     * @access protected
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    protected function _addDeleteEntities($entity, $Identifier)
    {
        $this->EntitiesContainer->addDeleteEntities($entity, $Identifier);
    }

    /**
     * Gets the connexion of the database.
     *
     * @param LifecycleEventArgs $eventArgs
     *
     * @return \Doctrine\DBAL\Connection
     * @access protected
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    protected function _connexion($eventArgs)
    {
        return $this->EntitiesContainer->getConnection($eventArgs);
    }

    /**
     * Sets the flash message.
     *
     * @param string $message
     * @param string $type
     *
     * @return void
     * @access protected
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    protected function setFlash($message, $type = "permission")
    {
        $this->getFlashBag()->add($type, $message);
    }

    /**
     * Gets the flash bag.
     *
     * @return \Symfony\Component\HttpFoundation\Session\Flash\FlashBag
     * @access protected
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    protected function getFlashBag()
    {
        return $this->container->get('request_stack')->getCurrentRequest()->getSession()->getFlashBag();
    }

    /**
     * Persist the entity if the create permission is done.
     *
     * @return boolean
     * @access protected
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    protected function isPersistRight()
    {
        if (in_array('CREATE', $this->tokenStorage->getUserPermissions())
            || in_array('ROLE_SUPER_ADMIN', $this->tokenStorage->getUserRoles())
        ) {
            return true;
        }

        return false;
    }

    /**
     * Update the entity if the edit permission is done.
     *
     * @return boolean
     * @access protected
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    protected function isUpdateRight()
    {
        if (in_array('EDIT', $this->tokenStorage->getUserPermissions())
            || in_array('ROLE_SUPER_ADMIN', $this->tokenStorage->getUserRoles())
        ) {
            return true;
        }

        return false;
    }

    /**
     * Remove the entity if the delete permission is done.
     *
     * @return boolean
     * @access protected
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    protected function isDeleteRight()
    {
        if (in_array('DELETE', $this->tokenStorage->getUserPermissions())
            || in_array('ROLE_SUPER_ADMIN', $this->tokenStorage->getUserRoles())
        ) {
            return true;
        }

        return false;
    }

    /**
     * Forwards the request to another controller.
     *
     * @param string $controller The controller name (a string like BlogBundle:Post:index)
     * @param array  $path       An array of path parameters
     * @param array  $query      An array of query parameters
     *
     * @access protected
     * @return Response A Response instance
     */
    protected function forward($controller, array $params = [], array $GET = [], $POST = null)
    {
    	$params['_controller'] = $controller;
    	$subRequest = $this->container->get('request_stack')->getCurrentRequest()->duplicate($GET, $POST, $params);

    	return $this->container->get('http_kernel')->handle($subRequest, HttpKernelInterface::SUB_REQUEST);
    }

    /**
     * @param LoadClassMetadataEventArgs $args
     *
     * @return void
     * @author Etienne de Longeaux <etienne.delongeaux@gmail.com>
     */
    public function loadClassMetadata(LoadClassMetadataEventArgs $eventArgs)
    {
	    $metadata = $eventArgs->getClassMetadata();

        $this->loadAssociations($eventArgs, $metadata);
        $this->loadIndexes($eventArgs, $metadata);
        $this->loadUniques($eventArgs, $metadata);

        $this->loadDiscriminatorColumns($eventArgs, $metadata);
        $this->loadDiscriminators($eventArgs, $metadata);
        $this->loadInheritanceTypes($eventArgs, $metadata);

//        $namingStrategy = $eventArgs
//            ->getEntityManager()
//            ->getConfiguration()
//            ->getNamingStrategy()
//        ;
//        $metadata->mapManyToMany(array(
//            'targetEntity'  => UploadedDocument::CLASS,
//            'fieldName'     => 'uploadedDocuments',
//            'cascade'       => array('persist'),
//            'joinTable'     => array(
//                'name'        => strtolower($namingStrategy->classToTableName($metadata->getName())) . '_document',
//                'joinColumns' => array(
//                    array(
//                        'name'                  => $namingStrategy->joinKeyColumnName($metadata->getName()),
//                        'referencedColumnName'  => $namingStrategy->referenceColumnName(),
//                        'onDelete'  => 'CASCADE',
//                        'onUpdate'  => 'CASCADE',
//                    ),
//                ),
//                'inverseJoinColumns'    => array(
//                    array(
//                        'name'                  => 'document_id',
//                        'referencedColumnName'  => $namingStrategy->referenceColumnName(),
//                        'onDelete'  => 'CASCADE',
//                        'onUpdate'  => 'CASCADE',
//                    ),
//                )
//            )
//        ));
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @throws \RuntimeException
     */
    protected function loadAssociations($eventArgs, ClassMetadataInfo $metadata)
    {
        if (!array_key_exists($metadata->getName(), $this->associations)) {
            return;
        }
        try {
            foreach ($this->associations[$metadata->getName()] as $type => $mappings) {
                foreach ($mappings as $mapping) {
                    if ($metadata->hasAssociation($mapping['fieldName'])) {
                        continue;
                    }
                    call_user_func([$metadata, $type], $mapping);
                }
            }
        } catch (\ReflectionException $e) {
            throw new \RuntimeException(
                sprintf('Error with class %s : %s', $metadata->getName(), $e->getMessage()),
                404,
                $e
            );
        }
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @throws \RuntimeException
     */
    protected function loadDiscriminatorColumns($eventArgs, ClassMetadataInfo $metadata)
    {
        if (!array_key_exists($metadata->getName(), $this->discriminatorColumns)) {
            return;
        }
        try {
            if (isset($this->discriminatorColumns[$metadata->getName()])) {
                $arrayDiscriminatorColumns = $this->discriminatorColumns[$metadata->getName()];
                if (isset($metadata->discriminatorColumn)) {
                    $arrayDiscriminatorColumns = array_merge(
                        $metadata->discriminatorColumn,
                        $this->discriminatorColumns[$metadata->name]
                    );
                }
                $metadata->setDiscriminatorColumn($arrayDiscriminatorColumns);
            }
        } catch (\ReflectionException $e) {
            throw new \RuntimeException(
                sprintf('Error with class %s : %s', $metadata->getName(), $e->getMessage()),
                404,
                $e
            );
        }
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @throws \RuntimeException
     */
    protected function loadInheritanceTypes($eventArgs, ClassMetadataInfo $metadata)
    {

        if (!array_key_exists($metadata->getName(), $this->inheritanceTypes)) {
            return;
        }
        try {
            if (isset($this->inheritanceTypes[$metadata->getName()])) {
                $metadata->setInheritanceType($this->inheritanceTypes[$metadata->getName()]);
            }
        } catch (\ReflectionException $e) {
            throw new \RuntimeException(
                sprintf('Error with class %s : %s', $metadata->getName(), $e->getMessage()),
                404,
                $e
            );
        }
    }

    /**
     * @param ClassMetadataInfo $metadata
     *
     * @throws \RuntimeException
     */
    protected function loadDiscriminators($eventArgs, ClassMetadataInfo $metadata)
    {
        if (!array_key_exists($metadata->getName(), $this->discriminators)) {
            return;
        }
        try {
            foreach ($this->discriminators[$metadata->getName()] as $key => $class) {
                if (in_array($key, $metadata->discriminatorMap)) {
                    continue;
                }
                $metadata->setDiscriminatorMap([$key=>$class]);
            }
        } catch (\ReflectionException $e) {
            throw new \RuntimeException(sprintf('Error with class %s : %s', $metadata->getName(), $e->getMessage()), 404, $e);
        }
    }

    /**
     * @param ClassMetadataInfo $metadata
     */
    protected function loadIndexes($eventArgs, ClassMetadataInfo $metadata)
    {
        if (!array_key_exists($metadata->getName(), $this->indexes)) {
            return;
        }
        foreach ($this->indexes[$metadata->getName()] as $name => $columns) {
            $metadata->table['indexes'][$name] = ['columns' => $columns];
        }
    }

    /**
     * @param ClassMetadataInfo $metadata
     */
    protected function loadUniques($eventArgs, ClassMetadataInfo $metadata)
    {
        if (!array_key_exists($metadata->getName(), $this->uniques)) {
            return;
        }
        foreach ($this->uniques[$metadata->getName()] as $name => $columns) {
            $metadata->table['uniqueConstraints'][$name] = ['columns' => $columns];
        }
    }

    /**
     * Add a discriminator to a class.
     *
     * @param  string  $class               The Class
     * @param  string  $key                 Key is the database value and values are the classes
     * @param  string  $discriminatorClass  The mapped class
     */
    public function addDiscriminator($class, $key, $discriminatorClass)
    {
        if (!isset($this->discriminators[$class])) {
            $this->discriminators[$class] = [];
        }
        if (!isset($this->discriminators[$class][$key])) {
            $this->discriminators[$class][$key] = $discriminatorClass;
        }
    }

    /**
     * Add the Discriminator Column.
     *
     * @param string $class
     * @param array  $columnDef
     */
    public function addDiscriminatorColumn($class, array $columnDef)
    {
        if (!isset($this->discriminatorColumns[$class])) {
            $this->discriminatorColumns[$class] = $columnDef;
        }
    }

    /**
     * @param string $class
     * @param string $type
     */
    public function addInheritanceType($class, $type)
    {
        if (!isset($this->inheritanceTypes[$class])) {
            $this->inheritanceTypes[$class] = $type;
        }
    }

    /**
     * @param string $class
     * @param string $type
     * @param array $options
     */
    public function addAssociation($class, $type, array $options)
    {
        if (!isset($this->associations[$class])) {
            $this->associations[$class] = [];
        }
        if (!isset($this->associations[$class][$type])) {
            $this->associations[$class][$type] = [];
        }
        $this->associations[$class][$type][] = $options;
    }

    /**
     * @param string $class
     * @param string $name
     * @param array  $columns
     */
    public function addIndex($class, $name, array $columns)
    {
        if (!isset($this->indexes[$class])) {
            $this->indexes[$class] = [];
        }
        if (isset($this->indexes[$class][$name])) {
            return;
        }
        $this->indexes[$class][$name] = $columns;
    }

    /**
     * @param string $class
     * @param string $name
     * @param array  $columns
     */
    public function addUnique($class, $name, array $columns)
    {
        if (!isset($this->indexes[$class])) {
            $this->uniques[$class] = [];
        }
        if (isset($this->uniques[$class][$name])) {
            return;
        }
        $this->uniques[$class][$name] = $columns;
    }
}
