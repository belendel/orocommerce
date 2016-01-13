<?php


namespace OroB2B\Bundle\PricingBundle\Tests\Unit\Form\Type;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;

use Oro\Component\Testing\Unit\EntityTrait;

use OroB2B\Bundle\AccountBundle\Entity\Account;
use OroB2B\Bundle\PricingBundle\Entity\PriceListAccountFallback;
use OroB2B\Bundle\PricingBundle\Entity\PriceListFallback;
use OroB2B\Bundle\PricingBundle\Form\Type\PriceListCollectionType;
use OroB2B\Bundle\PricingBundle\Form\Type\PriceListsSettingsType;
use OroB2B\Bundle\WebsiteBundle\Entity\Website;

class PriceListsSettingsTypeTest extends \PHPUnit_Framework_TestCase
{
    use EntityTrait;

    /** @var  Registry|\PHPUnit_Framework_MockObject_MockObject */
    protected $registry;

    /** @var  PriceListsSettingsType|\PHPUnit_Framework_MockObject_MockObject */
    protected $priceListsSettingsType;

    /** @var EntityManager|\PHPUnit_Framework_MockObject_MockObject */
    protected $em;

    public function setUp()
    {
        $this->registry = $this->getMockBuilder('Doctrine\Bundle\DoctrineBundle\Registry')
            ->disableOriginalConstructor()
            ->getMock();
        $this->priceListsSettingsType = new PriceListsSettingsType($this->registry);
    }

    public function testBuildForm()
    {
        $fallbackChoices = ['some', 'choises'];
        /** @var FormBuilderInterface|\PHPUnit_Framework_MockObject_MockObject $builder */
        $builder = $this->getMock('Symfony\Component\Form\FormBuilderInterface');
        $builder->expects($this->exactly(2))->method('add')->will(
            $this->returnValueMap(
                [
                    [
                        PriceListsSettingsType::FALLBACK_FIELD,
                        'choice',
                        [
                            'label' => 'orob2b.pricing.fallback.label',
                            'mapped' => true,
                            'choices' => $fallbackChoices,
                        ],
                        $builder,
                    ],
                    [
                        PriceListsSettingsType::PRICE_LIST_COLLECTION_FIELD,
                        PriceListCollectionType::NAME,
                        ['label' => 'orob2b.pricing.pricelist.entity_plural_label', 'mapped' => true],
                        $builder,
                    ],
                ]
            )
        );
        $builder->expects($this->exactly(2))->method('addEventListener')->will(
            $this->returnValueMap(
                [
                    [
                        FormEvents::POST_SET_DATA,
                        [$builder, 'onPostSetData'],
                        $builder,
                    ],
                    [
                        FormEvents::POST_SUBMIT,
                        [$builder, 'onPostSubmit'],
                        $builder,
                    ],
                ]
            )
        );
        $this->priceListsSettingsType->buildForm($builder, ['fallback_choices' => $fallbackChoices]);
    }

    public function testConfigureOptions()
    {
        /** @var OptionsResolver|\PHPUnit_Framework_MockObject_MockObject $resolver */
        $resolver = $this->getMock('Symfony\Component\OptionsResolver\OptionsResolver');
        $resolver->expects($this->once())->method('setDefaults')->with(
            [
                'render_as_widget' => true,
                'label' => false,
            ]
        );
        $resolver->expects($this->once())
            ->method('setRequired')
            ->with(['fallback_class_name', 'target_field_name', 'fallback_choices', 'website', 'default_fallback']);
        $this->priceListsSettingsType->configureOptions($resolver);
    }

    /**
     * @dataProvider onPostSetDataDataProvider
     * @param $fallbackEntity
     * @param $expectedFallbackValue
     */
    public function testOnPostSetData($fallbackEntity, $expectedFallbackValue)
    {
        $form = $this->getForm();
        $targetEntity = $this->getEntity('OroB2B\Bundle\AccountBundle\Entity\Account', ['id' => 123]);
        $this->setDataFromMainForm($form, $targetEntity);
        $fallbackField = $this->getForm();
        $fallbackField->expects($this->once())->method('setData')->with($expectedFallbackValue);
        $form->expects($this->once())
            ->method('get')
            ->with(PriceListsSettingsType::FALLBACK_FIELD)
            ->willReturn($fallbackField);
        /** @var FormConfigInterface|\PHPUnit_Framework_MockObject_MockObject $config */
        $config = $this->getMock('Symfony\Component\Form\FormConfigInterface');

        $fallbackClassName = 'ClassName';
        $targetFieldName = 'targetFieldName';
        $website = new Website();
        $this->setFallbackExpectations($fallbackClassName, $targetEntity, $targetFieldName, $website, $fallbackEntity);
        $config->expects($this->any())->method('getOption')->will(
            $this->returnValueMap(
                [
                    ['fallback_class_name', null, $fallbackClassName],
                    ['target_field_name', null, $targetFieldName],
                    ['website', null, $website],
                    ['default_fallback', null, PriceListAccountFallback::ACCOUNT_GROUP],
                ]
            )
        );
        $form->expects($this->once())->method('getConfig')->willReturn($config);
        $this->priceListsSettingsType->onPostSetData(new FormEvent($form, null));
    }

    /**
     * @return array
     */
    public function onPostSetDataDataProvider()
    {
        return [
            'notExistingFallback' => [
                'fallbackEntity' => null,
                'expectedFallbackValue' => PriceListAccountFallback::ACCOUNT_GROUP,
            ],
            'existingFallback' => [
                'fallbackEntity' => new PriceListAccountFallback(),
                'expectedFallbackValue' => PriceListAccountFallback::CURRENT_ACCOUNT_ONLY,
            ],
            'existingDefaultFallback' => [
                'fallbackEntity' => (new PriceListAccountFallback())
                    ->setFallback(PriceListAccountFallback::ACCOUNT_GROUP),
                'expectedFallbackValue' => PriceListAccountFallback::ACCOUNT_GROUP,
            ],
        ];
    }

    /**
     * @dataProvider withoutTargetEntityDataProvider
     * @param null|object $targetEntity
     */
    public function testOnPostSetDataWithoutTargetEntity($targetEntity)
    {
        $form = $this->getForm();
        $this->setDataFromMainForm($form, $targetEntity);
        $form->expects($this->never())->method('getConfig');
        $this->priceListsSettingsType->onPostSetData(new FormEvent($form, null));
    }

    public function withoutTargetEntityDataProvider()
    {
        return [
            'withoutTargetEntity' => [null],
            'targetEntityWithoutId' => [new Account()],
        ];
    }

    public function testOnPostSubmitWithInvalidForm()
    {
        $form = $this->getForm();
        $form->expects($this->once())->method('isValid')->willReturn(false);
        $form->expects($this->never())->method('getParent');
        $this->priceListsSettingsType->onPostSubmit(new FormEvent($form, null));
    }

    /**
     * @dataProvider withoutTargetEntityDataProvider
     * @param null|object $targetEntity
     */
    public function testOnPostSubmitWithoutTargetEntity($targetEntity)
    {
        $form = $this->getForm();
        $form->expects($this->once())->method('isValid')->willReturn(true);
        $this->setDataFromMainForm($form, $targetEntity);
        $form->expects($this->never())->method('getConfig');
        $this->priceListsSettingsType->onPostSubmit(new FormEvent($form, null));
    }

    /**
     * @dataProvider testOnPostSubmitDataProvider
     * @param PriceListFallback|null $fallbackEntity
     */
    public function testOnPostSubmit(PriceListFallback $fallbackEntity = null)
    {
        $form = $this->getForm();
        $form->expects($this->once())->method('isValid')->willReturn(true);
        /** @var Account $targetEntity */
        $targetEntity = $this->getEntity('OroB2B\Bundle\AccountBundle\Entity\Account', ['id' => 123]);
        $this->setDataFromMainForm($form, $targetEntity);
        $website = new Website();
        $fallbackClassName = 'OroB2B\Bundle\PricingBundle\Entity\PriceListAccountFallback';
        $targetFieldName = 'account';
        $fallbackValue = 42;
        $fallbackForm = $this->getForm();
        $fallbackForm->expects($this->once())->method('getData')->willReturn($fallbackValue);
        $form->expects($this->once())
            ->method('get')
            ->with(PriceListsSettingsType::FALLBACK_FIELD)
            ->willReturn($fallbackForm);

        $this->setFallbackExpectations($fallbackClassName, $targetEntity, $targetFieldName, $website, $fallbackEntity);
        $config = $this->getConfig();
        $config->expects($this->any())->method('getOption')->will(
            $this->returnValueMap(
                [
                    ['fallback_class_name', null, $fallbackClassName],
                    ['target_field_name', null, $targetFieldName],
                    ['website', null, $website],
                    ['default_fallback', null, PriceListAccountFallback::ACCOUNT_GROUP],
                ]
            )
        );

        if (!$fallbackEntity) {
            $this->em->expects($this->once())->method('persist');
        }
        $form->expects($this->once())->method('getConfig')->willReturn($config);
        $this->priceListsSettingsType->onPostSubmit(new FormEvent($form, null));
        if ($fallbackEntity) {
            $this->assertEquals($targetEntity, $fallbackEntity->getAccount());
            $this->assertEquals($fallbackValue, $fallbackEntity->getFallback());
            $this->assertEquals($website, $fallbackEntity->getWebsite());
        }
    }

    public function testOnPostSubmitDataProvider()
    {
        return [
            'withoutExistingFallback' =>
                ['fallback' => null],
            'withExistingFallback' =>
                ['fallback' => new PriceListAccountFallback()],
        ];
    }

    /**
     * @return FormInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getForm()
    {
        return $this->getMock('Symfony\Component\Form\FormInterface');
    }

    /**
     * @param FormInterface|\PHPUnit_Framework_MockObject_MockObject $form
     * @param object|null $data
     */
    public function setDataFromMainForm(FormInterface $form, $data)
    {
        $mainForm = $this->getForm();
        $mainForm->expects($this->once())->method('getData')->willReturn($data);
        $parentForm = $this->getForm();
        $parentForm->expects($this->once())->method('getParent')->willReturn($mainForm);
        $form->expects($this->once())->method('getParent')->willReturn($parentForm);
    }

    /**
     * @param string $className
     * @param object $targetEntity
     * @param string $targetFieldName
     * @param Website $website
     * @param null|PriceListFallback $result
     */
    protected function setFallbackExpectations($className, $targetEntity, $targetFieldName, Website $website, $result)
    {
        /** @var EntityRepository|\PHPUnit_Framework_MockObject_MockObject $repo */
        $repo = $this->getMockBuilder('Doctrine\ORM\EntityRepository')->disableOriginalConstructor()->getMock();
        $repo->expects($this->once())
            ->method('findOneBy')
            ->with([$targetFieldName => $targetEntity, 'website' => $website])
            ->willReturn($result);
        /** @var EntityManager|\PHPUnit_Framework_MockObject_MockObject $em */
        $this->em = $this->getMockBuilder('Doctrine\ORM\EntityManager')->disableOriginalConstructor()->getMock();
        $this->em->expects($this->once())->method('getRepository')->with($className)->willReturn($repo);
        $this->registry->expects($this->any())->method('getManagerForClass')->with($className)->willReturn($this->em);
    }

    /**
     * @return FormConfigInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected function getConfig()
    {
        return $this->getMock('Symfony\Component\Form\FormConfigInterface');
    }
}
