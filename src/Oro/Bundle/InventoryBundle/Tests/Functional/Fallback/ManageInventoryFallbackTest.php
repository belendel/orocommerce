<?php

namespace Oro\Bundle\InventoryBundle\Tests\Functional\Fallback;

use Symfony\Component\DomCrawler\Crawler;

use Oro\Bundle\CatalogBundle\Tests\Functional\DataFixtures\LoadCategoryData;
use Oro\Bundle\CatalogBundle\Tests\Functional\DataFixtures\LoadCategoryProductData;
use Oro\Bundle\ProductBundle\Entity\Product;
use Oro\Bundle\ProductBundle\Tests\Functional\DataFixtures\LoadProductData;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

class ManageInventoryFallbackTest extends InventoryFallbackTest
{
    const VIEW_MANAGED_INVENTORY_XPATH =
        "//label[text() = 'Managed Inventory']/following-sibling::div/div[contains(@class,  'control-label')]";

    /**
     * @param mixed $systemValue
     * @param string $expectedProductValue
     * @param bool $updateProduct
     * @param bool $updateCategory
     *
     * @dataProvider productCategorySystemFallbackProvider
     */
    public function testProductCategorySystemFallback(
        $systemValue,
        $expectedProductValue,
        $updateProduct = false,
        $updateCategory = false
    ) {
        $product = $this->getReference(LoadProductData::PRODUCT_1);
        if ($updateProduct) {
            $this->setProductInventoryField($product, null, true, 'category');
        }
        if ($updateCategory) {
            $this->setCategoryInventoryField(null, true, 'systemConfig');
        }

        $crawler = $this->client->request(
            'GET',
            $this->getUrl(
                'oro_config_configuration_system',
                ['activeGroup' => 'commerce', 'activeSubGroup' => 'product_options']
            )
        );
        $form = $crawler->selectButton('Save settings')->form();
        $formValues = $form->getPhpValues();
        $formValues['product_options']['oro_inventory___manage_inventory']['use_parent_scope_value'] = false;
        $formValues['product_options']['oro_inventory___manage_inventory']['value'] = $systemValue;
        $this->client->request($form->getMethod(), $form->getUri(), $formValues);

        $crawler = $this->client->request('GET', $this->getUrl('oro_product_view', ['id' => $product->getId()]));
        $this->assertProductInventoryValue($crawler, $expectedProductValue);
    }
}
