<?php

namespace OroB2B\Bundle\ShippingBundle\Method;

use Oro\Bundle\CurrencyBundle\Entity\Price;

use OroB2B\Bundle\ShippingBundle\Entity\ShippingRuleConfiguration;
use OroB2B\Bundle\ShippingBundle\Provider\ShippingContextAwareInterface;

interface ShippingMethodInterface
{
    /**
     * @return string
     */
    public function getName();

    /**
     * @return string
     */
    public function getLabel();

    /**
     * @return array
     */
    public function getShippingTypes();

    /**
     * @param string $type
     * @return string
     */
    public function getShippingTypeLabel($type);

    /**
     * @return string
     */
    public function getFormType();

    /**
     * @param array $context
     * @return array
     */
    public function getOptions(array $context = []);

    /**
     * @return int
     */
    public function getSortOrder();

    /**
     * @param ShippingContextAwareInterface $context
     * @param ShippingRuleConfiguration $configEntity
     * @return null|Price
     */
    public function calculatePrice(ShippingContextAwareInterface $context, ShippingRuleConfiguration $configEntity);
}
