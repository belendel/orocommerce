<?php

namespace OroB2B\Bundle\ShippingBundle\Form\Extension;

use OroB2B\Bundle\ShippingBundle\Form\Type\ShippingOriginType;
use OroB2B\Bundle\ShippingBundle\Provider\ShippingOriginProvider;
use OroB2B\Bundle\WarehouseBundle\Entity\Warehouse;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

use OroB2B\Bundle\WarehouseBundle\Form\Type\WarehouseType;

class WarehouseShippingOriginExtension extends AbstractTypeExtension
{
    /**
     * @var ShippingOriginProvider
     */
    private $shippingOriginProvider;

    /**
     * @var ShippingOriginType
     */
    private $shippingOriginType;

    public function __construct(ShippingOriginProvider $shippingOriginProvider, ShippingOriginType $shippingOriginType)
    {
        $this->shippingOriginProvider = $shippingOriginProvider;
        $this->shippingOriginType = $shippingOriginType;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add(
            'shipping_origin',
            $this->shippingOriginType,
            [
                'required' => false,
                'mapped' => false,
                'label' => 'orob2b.tax.system_configuration.fields.use_as_base.shipping_origin.label'
            ]
        );

        $builder->add(
            'use_system_shipping_origin',
            'checkbox',
            ['mapped' => false, 'label' => 'Use system shipping origin address']
        );

        $builder->addEventListener(FormEvents::PRE_SET_DATA, [$this, 'preSetData']);
        $builder->addEventListener(FormEvents::POST_SUBMIT, [$this, 'postSubmit']);
    }

    public function postSubmit(FormEvent $formEvent)
    {
        $data = $formEvent->getData();

        gettype($data);
    }

    /**
     * {@inheritdoc}
     */
    public function preSetData(FormEvent $formEvent)
    {
        $data = $formEvent->getData();

        $form = $formEvent->getForm();

        if (!$data instanceof Warehouse) {
            return;
        }

        $shippingOrigin = $this->shippingOriginProvider->getShippingOriginByWarehouse($data);

        if ($shippingOrigin && !$shippingOrigin->isSystem()) {
            $form->get('shipping_origin')->setData($shippingOrigin);
        }
    }

    public function getExtendedType()
    {
        return WarehouseType::NAME;
    }
}