<?php

namespace Oro\Bundle\InventoryBundle\Tests\Unit\EventListener;

use Oro\Bundle\CatalogBundle\Entity\Category;
use Oro\Bundle\InventoryBundle\EventListener\CategoryQuantityToOrderFormViewListener;

class CategoryQuantityToOrderFormViewListenerTest extends AbstractFallbackFieldsFormViewTest
{
    /** @var CategoryQuantityToOrderFormViewListener */
    protected $listener;

    protected function setUp()
    {
        parent::setUp();

        $this->listener = new CategoryQuantityToOrderFormViewListener(
            $this->requestStack,
            $this->doctrine,
            $this->translator
        );
    }

    protected function tearDown()
    {
        unset($this->listener);

        parent::tearDown();
    }

    /**
     * @return void
     */
    protected function callTestMethod()
    {
        $this->listener->onCategoryEdit($this->event);
    }

    /**
     * @return array
     */
    protected function getExpectedScrollData()
    {
        return ['dataBlocks' => [1 => ['title' => 'oro.catalog.sections.default_options.trans']]];
    }

    /**
     * @return Category
     */
    protected function getEntity()
    {
        return new Category();
    }
}
