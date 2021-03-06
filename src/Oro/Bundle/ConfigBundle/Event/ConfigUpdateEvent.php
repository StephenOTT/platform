<?php

namespace Oro\Bundle\ConfigBundle\Event;

use Symfony\Component\EventDispatcher\Event;

use Oro\Bundle\ConfigBundle\Config\ConfigChangeSet;
use Oro\Bundle\ConfigBundle\Exception\UnexpectedTypeException;

class ConfigUpdateEvent extends Event
{
    const EVENT_NAME = 'oro_config.update_after';

    /** @var ConfigChangeSet */
    protected $changeSet = [];

    /**
     * @param ConfigChangeSet|array $changeSet
     */
    public function __construct($changeSet)
    {
        if ($changeSet instanceof ConfigChangeSet) {
            $this->changeSet = $changeSet;
        } elseif (is_array($changeSet)) {
            $this->changeSet = new ConfigChangeSet($changeSet);
        } else {
            throw new UnexpectedTypeException(
                $changeSet,
                'Oro\Bundle\ConfigBundle\Config\ConfigChangeSet or array'
            );
        }
    }

    /**
     * Returns config change set
     *
     * @return array [name => ['new' => value, 'old' => value], ...]
     */
    public function getChangeSet()
    {
        return $this->changeSet->getChanges();
    }

    /**
     * Checks whenever configuration value is changed
     *
     * @param string $name
     *
     * @return bool
     */
    public function isChanged($name)
    {
        return $this->changeSet->isChanged($name);
    }

    /**
     * Retrieve new value from change set
     *
     * @param string $name
     *
     * @return mixed
     * @throws \LogicException
     *
     */
    public function getNewValue($name)
    {
        return $this->changeSet->getNewValue($name);
    }

    /**
     * Retrieve old value from change set
     *
     * @param string $name
     *
     * @return mixed
     * @throws \LogicException
     */
    public function getOldValue($name)
    {
        return $this->changeSet->getOldValue($name);
    }
}
