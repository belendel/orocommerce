<?php

namespace Oro\Bundle\CustomerBundle\Tests\Functional\Security;

use Oro\Bundle\CustomerBundle\Tests\Functional\DataFixtures\LoadFrontendProductVisibilityData;
use Oro\Bundle\CustomerBundle\Entity\Visibility\ProductVisibility;
use Oro\Bundle\FrontendTestFrameworkBundle\Migrations\Data\ORM\LoadAccountUserData;
use Oro\Bundle\PricingBundle\Tests\Functional\DataFixtures\LoadCombinedPriceLists;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Tests\Functional\DataFixtures\LoadProductData;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

/**
 * @dbIsolation
 */
class ProductVisibilityTest extends WebTestCase
{
    const VISIBILITY_SYSTEM_CONFIGURATION_PATH = 'oro_customer.product_visibility';

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->initClient(
            [],
            $this->generateBasicAuthHeader(LoadAccountUserData::AUTH_USER, LoadAccountUserData::AUTH_PW)
        );
        $this->client->useHashNavigation(true);
        $this->loadFixtures([
            LoadFrontendProductVisibilityData::class,
            LoadCombinedPriceLists::class,
        ]);
    }

    /**
     * @dataProvider visibilityDataProvider
     *
     * @param string $configValue
     * @param array $expectedData
     */
    public function testVisibility($configValue, $expectedData)
    {
        $configManager = $this->getContainer()->get('oro_config.global');
        $configManager->set(self::VISIBILITY_SYSTEM_CONFIGURATION_PATH, $configValue);
        $configManager->flush();

        $indexer = $this->getContainer()->get('oro_website_search.indexer');
        foreach ($expectedData as $productSKU => $resultCode) {
            $product = $this->getReference($productSKU);
            $indexer->save($product);

            $this->assertInstanceOf(Product::class, $product);
            $this->client->request(
                'GET',
                $this->getUrl('oro_product_frontend_product_view', ['id' => $product->getId()])
            );
            $result = $this->client->getResponse();
            $this->assertHtmlResponseStatusCodeEquals($result, $resultCode);
        }
    }

    /**
     * @return array
     */
    public function visibilityDataProvider()
    {
        return [
            'config visible' => [
                'configValue' => ProductVisibility::VISIBLE,
                'expectedData' => [
                    LoadProductData::PRODUCT_1 => 200,
                    LoadProductData::PRODUCT_2 => 200,
                    LoadProductData::PRODUCT_3 => 200,
                    LoadProductData::PRODUCT_4 => 403, // inventoryStatus: discontinued and visibility: hidden
                    LoadProductData::PRODUCT_5 => 403, // status: disabled
                    LoadProductData::PRODUCT_6 => 200,
                    LoadProductData::PRODUCT_7 => 200,
                    LoadProductData::PRODUCT_8 => 200,
                ]
            ],
            'config hidden' => [
                'configValue' => ProductVisibility::HIDDEN,
                'expectedData' => [
                    LoadProductData::PRODUCT_1 => 403,
                    LoadProductData::PRODUCT_2 => 200,
                    LoadProductData::PRODUCT_3 => 200,
                    LoadProductData::PRODUCT_4 => 403, // inventoryStatus: discontinued and visibility: hidden
                    LoadProductData::PRODUCT_5 => 403, // status: disabled
                    LoadProductData::PRODUCT_6 => 200, // config for Default website only, visibility is for US
                    LoadProductData::PRODUCT_7 => 200, // config for Default website only, visibility is for US
                    LoadProductData::PRODUCT_8 => 200, // config for Default website only, visibility is for US
                ]
            ],
        ];
    }

    protected function tearDown()
    {
        $configManager = $this->getClientInstance()->getContainer()->get('oro_config.global');
        $configManager->reset(self::VISIBILITY_SYSTEM_CONFIGURATION_PATH);
        $configManager->flush();

        parent::tearDown();
    }
}