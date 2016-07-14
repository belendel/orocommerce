<?php

namespace OroB2B\Bundle\CheckoutBundle\WorkflowState\Manager;

use OroB2B\Bundle\CheckoutBundle\WorkflowState\Mapper\CheckoutStateDiffMapperInterface;

class CheckoutStateDiffManager
{
    /**
     * @var CheckoutStateDiffMapperInterface[]
     */
    private $mappers;

    /**
     * @param CheckoutStateDiffMapperInterface $mapper
     */
    public function addMapper(CheckoutStateDiffMapperInterface $mapper)
    {
        $this->mappers[] = $mapper;
    }

    /**
     * @param object $entity
     * @return array
     */
    public function getCurrentState($entity)
    {
        $currentState = [];
        foreach ($this->mappers as $mapper) {
            $currentState[] = $mapper->getCurrentState($entity);
        }

        return $currentState;
    }

    /**
     * @param object $entity
     * @param array $savedState
     * @return bool
     */
    public function compareStates($entity, array $savedState)
    {
    }
}
