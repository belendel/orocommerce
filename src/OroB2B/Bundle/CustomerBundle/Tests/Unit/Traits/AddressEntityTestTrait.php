<?php

namespace OroB2B\Bundle\CustomerBundle\Tests\Unit\Traits;

use Oro\Bundle\AddressBundle\Entity\AddressType;

use OroB2B\Bundle\CustomerBundle\Entity\AbstractDefaultTypedAddress;
use OroB2B\Bundle\CustomerBundle\Entity\Traits\AddressEntityTrait;

trait AddressEntityTestTrait
{
    public function testAddressesCollection()
    {
        $customer = $this->createTestedEntity();
        static::assertPropertyCollections($customer, [['addresses', $this->createAddressEntity()]]);
    }

    /**
     * @param AbstractDefaultTypedAddress[] $addresses
     * @param string            $searchName
     * @param AbstractDefaultTypedAddress   $expectedAddress
     * @dataProvider getAddressByTypeNameProvider
     */
    public function testGetAddressByTypeName($addresses, $searchName, $expectedAddress)
    {
        $customer = $this->createTestedEntity();
        foreach ($addresses as $address) {
            $customer->addAddress($address);
        }

        $actualAddress = $customer->getAddressByTypeName($searchName);
        $this->assertEquals($expectedAddress, $actualAddress);
    }

    public function getAddressByTypeNameProvider()
    {
        $billingType = new AddressType(AddressType::TYPE_BILLING);
        $shippingType = new AddressType(AddressType::TYPE_SHIPPING);

        $addressWithBilling = $this->createAddressEntity();
        $addressWithBilling->addType($billingType);

        $addressWithShipping = $this->createAddressEntity();
        $addressWithShipping->addType($shippingType);

        $addressWithShippingAndBilling = $this->createAddressEntity();
        $addressWithShippingAndBilling->addType($shippingType);
        $addressWithShippingAndBilling->addType($billingType);

        return [
            'not found address with type (empty addresses)' => [
                'addresses' => [],
                'searchName' => AddressType::TYPE_BILLING,
                'expectedAddress' => null
            ],
            'not found address with type (some address exists)' => [
                'addresses' => [$addressWithShipping],
                'searchName' => AddressType::TYPE_BILLING,
                'expectedAddress' => null
            ],
            'find address by shipping name' => [
                'addresses' => [$addressWithShipping],
                'searchName' => AddressType::TYPE_SHIPPING,
                'expectedAddress' => $addressWithShipping
            ],
            'find first address by shipping name' => [
                'addresses' => [$addressWithShippingAndBilling, $addressWithShipping],
                'searchName' => AddressType::TYPE_SHIPPING,
                'expectedAddress' => $addressWithShippingAndBilling
            ],
        ];
    }

    /**
     * @param $addresses
     * @param $expectedAddress
     * @dataProvider getPrimaryAddressProvider
     */
    public function testGetPrimaryAddress($addresses, $expectedAddress)
    {
        $customer = $this->createTestedEntity();
        foreach ($addresses as $address) {
            $customer->addAddress($address);
        }

        $this->assertEquals($expectedAddress, $customer->getPrimaryAddress());
    }

    public function getPrimaryAddressProvider()
    {
        $primaryAddress = $this->createAddressEntity();
        $primaryAddress->setPrimary(true);

        $notPrimaryAddress = $this->createAddressEntity();

        return [
            'without primary address' => [
                'addresses' => [$notPrimaryAddress],
                'expectedAddress' => null
            ],
            'one primary address' => [
                'addresses' => [$primaryAddress],
                'expectedAddress' => $primaryAddress
            ],
            'get one primary by few address' => [
                'addresses' => [$primaryAddress, $notPrimaryAddress],
                'expectedAddress' => $primaryAddress
            ],
        ];
    }

    /**
     * Return tested entity
     *
     * @return AddressEntityTrait
     */
    abstract protected function createTestedEntity();

    /**
     * Return address entity related with entity
     * returned from `createTestedEntity`
     *
     * @return AbstractDefaultTypedAddress
     */
    abstract protected function createAddressEntity();
}
