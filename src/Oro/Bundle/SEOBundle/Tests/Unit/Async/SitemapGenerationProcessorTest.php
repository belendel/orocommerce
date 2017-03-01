<?php

namespace Oro\Bundle\SEOBundle\Tests\Unit\Async;

use Oro\Bundle\SEOBundle\Async\SitemapGenerationProcessor;
use Oro\Bundle\SEOBundle\Async\Topics;
use Oro\Bundle\SEOBundle\Provider\UrlItemsProviderRegistry;
use Oro\Bundle\SEOBundle\Sitemap\Dumper\SitemapDumper;
use Oro\Bundle\WebsiteBundle\Entity\Repository\WebsiteRepository;
use Oro\Component\MessageQueue\Consumption\MessageProcessorInterface;
use Oro\Component\MessageQueue\Job\JobRunner;
use Oro\Component\MessageQueue\Test\JobRunner as TestJobRunner;
use Oro\Component\MessageQueue\Transport\MessageInterface;
use Oro\Component\MessageQueue\Transport\SessionInterface;
use Oro\Component\SEO\Tools\Exception\SitemapFileWriterException;
use Oro\Component\Website\WebsiteInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;

class SitemapGenerationProcessorTest extends \PHPUnit_Framework_TestCase
{
    const JOB_ID = 123;
    const WEBSITE_ID = 7;
    const TYPE = 'someType';

    const INVALID_MESSAGE_ERROR = '[SitemapGenerationProcessor] Got invalid message';

    /**
     * @var SessionInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $session;

    /**
     * @var JobRunner|\PHPUnit_Framework_MockObject_MockObject
     */
    private $jobRunner;

    /**
     * @var LoggerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    private $logger;

    /**
     * @var SitemapDumper|\PHPUnit_Framework_MockObject_MockObject
     */
    private $sitemapDumper;

    /**
     * @var WebsiteRepository|\PHPUnit_Framework_MockObject_MockObject
     */
    private $websiteRepository;

    /**
     * @var UrlItemsProviderRegistry|\PHPUnit_Framework_MockObject_MockObject
     */
    private $providerRegistry;

    /**
     * @var SitemapGenerationProcessor
     */
    private $processor;

    protected function setUp()
    {
        $this->session = $this->createMock(SessionInterface::class);

        $this->jobRunner = $this->getMockBuilder(JobRunner::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->sitemapDumper = $this->getMockBuilder(SitemapDumper::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->websiteRepository = $this->getMockBuilder(WebsiteRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->providerRegistry = $this->getMockBuilder(UrlItemsProviderRegistry::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->processor = new SitemapGenerationProcessor(
            $this->jobRunner,
            $this->logger,
            $this->sitemapDumper,
            $this->websiteRepository,
            $this->providerRegistry
        );
    }

    public function testGetSubscribedTopics()
    {
        $this->assertEquals(
            [Topics::GENERATE_SITEMAP_BY_WEBSITE_AND_TYPE],
            SitemapGenerationProcessor::getSubscribedTopics()
        );
    }

    /**
     * @dataProvider badMessageDataProvider
     *
     * @param array $messageBody
     * @param \Exception $expectedException
     */
    public function testProcessWhenSomeOptionsAreMissing(array $messageBody, $expectedException)
    {
        /** @var MessageInterface|\PHPUnit_Framework_MockObject_MockObject $message */
        $message = $this->createMock(MessageInterface::class);
        $message
            ->expects($this->any())
            ->method('getBody')
            ->willReturn(json_encode($messageBody));

        $this->jobRunner
            ->expects($this->never())
            ->method('runDelayed');

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                self::INVALID_MESSAGE_ERROR,
                ['message' => json_encode($messageBody), 'exception' => $expectedException]
            );

        $this->assertEquals(MessageProcessorInterface::REJECT, $this->processor->process($message, $this->session));
    }

    /**
     * @return array
     */
    public function badMessageDataProvider()
    {
        return [
            'all options are missing' => [
                'messageBody' => [],
                'expectedException' => new MissingOptionsException(
                    'The required options "jobId", "type", "websiteId" are missing.'
                )
            ],
            'websiteId option is missing' => [
                'messageBody' => ['jobId' => self::JOB_ID, 'type' => self::TYPE],
                'expectedException' => new MissingOptionsException('The required option "websiteId" is missing.')
            ],
            'type option is missing' => [
                'messageBody' => ['jobId' => self::JOB_ID, 'websiteId' => self::WEBSITE_ID],
                'expectedException' => new MissingOptionsException('The required option "type" is missing.')
            ],
            'jobId option is missing' => [
                'messageBody' => ['websiteId' => self::WEBSITE_ID, 'type' => self::TYPE],
                'expectedException' => new MissingOptionsException('The required option "jobId" is missing.')
            ],
        ];
    }

    public function testProcessWhenWebsiteNotExists()
    {
        $messageBody = ['jobId' => self::JOB_ID, 'websiteId' => self::WEBSITE_ID, 'type' => self::TYPE];

        /** @var MessageInterface|\PHPUnit_Framework_MockObject_MockObject $message */
        $message = $this->createMock(MessageInterface::class);
        $message
            ->expects($this->any())
            ->method('getBody')
            ->willReturn(json_encode($messageBody));

        $this->expectWebsiteExists(false);

        $this->jobRunner
            ->expects($this->never())
            ->method('runDelayed');

        $expectedException = new InvalidOptionsException('No website exists with id "7"');
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                self::INVALID_MESSAGE_ERROR,
                ['message' => json_encode($messageBody), 'exception' => $expectedException]
            );

        $this->assertEquals(MessageProcessorInterface::REJECT, $this->processor->process($message, $this->session));
    }

    public function testProcessWhenProviderTypeNotExists()
    {
        $messageBody = ['jobId' => self::JOB_ID, 'websiteId' => self::WEBSITE_ID, 'type' => self::TYPE];

        /** @var MessageInterface|\PHPUnit_Framework_MockObject_MockObject $message */
        $message = $this->createMock(MessageInterface::class);
        $message
            ->expects($this->any())
            ->method('getBody')
            ->willReturn(json_encode($messageBody));

        $this->expectWebsiteExists(true);

        $this->expectProviderExists(false);

        $this->jobRunner
            ->expects($this->never())
            ->method('runDelayed');

        $expectedException = new InvalidOptionsException('No url item provider exists with name "someType"');
        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                self::INVALID_MESSAGE_ERROR,
                ['message' => json_encode($messageBody), 'exception' => $expectedException]
            );

        $this->assertEquals(MessageProcessorInterface::REJECT, $this->processor->process($message, $this->session));
    }

    /**
     * @dataProvider jobResultDataProvider
     *
     * @param bool $jobResult
     * @param string $expectedResult
     */
    public function testProcessWhenMessageOptionsAreValid($jobResult, $expectedResult)
    {
        $messageBody = ['jobId' => self::JOB_ID, 'websiteId' => self::WEBSITE_ID, 'type' => self::TYPE];

        /** @var MessageInterface|\PHPUnit_Framework_MockObject_MockObject $message */
        $message = $this->createMock(MessageInterface::class);
        $message
            ->expects($this->any())
            ->method('getBody')
            ->willReturn(json_encode($messageBody));

        $this->expectWebsiteExists(true);

        $this->expectProviderExists(true);

        $this->jobRunner
            ->expects($this->once())
            ->method('runDelayed')
            ->willReturn($jobResult);

        $this->logger
            ->expects($this->never())
            ->method('error');

        $this->assertEquals($expectedResult, $this->processor->process($message, $this->session));
    }

    public function testProcessWhenMessageOptionsAreValidAndJobRunnerThrowsException()
    {
        $messageBody = ['jobId' => self::JOB_ID, 'websiteId' => self::WEBSITE_ID, 'type' => self::TYPE];

        /** @var MessageInterface|\PHPUnit_Framework_MockObject_MockObject $message */
        $message = $this->createMock(MessageInterface::class);
        $message
            ->expects($this->any())
            ->method('getBody')
            ->willReturn(json_encode($messageBody));

        $this->expectWebsiteExists(true);

        $this->expectProviderExists(true);

        $exception = new \Exception('Some exception message');
        $this->jobRunner
            ->expects($this->once())
            ->method('runDelayed')
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Unexpected exception occurred during queue message processing',
                [
                    'message' => json_encode($messageBody),
                    'exception' => $exception,
                    'topic' => Topics::GENERATE_SITEMAP_BY_WEBSITE_AND_TYPE
                ]
            );

        $this->assertEquals(MessageProcessorInterface::REJECT, $this->processor->process($message, $this->session));
    }

    /**
     * @return array
     */
    public function jobResultDataProvider()
    {
        return [
            'job succeeds' => [
                'jobResult' => true,
                'expectedResult' => MessageProcessorInterface::ACK
            ],
            'job fails' => [
                'jobResult' => false,
                'expectedResult' => MessageProcessorInterface::REJECT
            ]
        ];
    }

    public function testProcessWhenSitemapDumperThrowsException()
    {
        $messageBody = ['jobId' => self::JOB_ID, 'websiteId' => self::WEBSITE_ID, 'type' => self::TYPE];

        /** @var MessageInterface|\PHPUnit_Framework_MockObject_MockObject $message */
        $message = $this->createMock(MessageInterface::class);
        $message
            ->expects($this->any())
            ->method('getBody')
            ->willReturn(json_encode($messageBody));

        $this->expectWebsiteExists(true);

        /** @var WebsiteInterface $website */
        $website = $this->createMock(WebsiteInterface::class);

        $this->expectFindWebsite($website);

        $this->expectProviderExists(true);

        $exception = new SitemapFileWriterException('Some message');
        $this->sitemapDumper
            ->expects($this->once())
            ->method('dump')
            ->with($website, self::TYPE)
            ->willThrowException($exception);

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with(
                'Unexpected exception occurred during queue message processing',
                [
                    'message' => json_encode($messageBody),
                    'exception' => $exception,
                    'topic' => Topics::GENERATE_SITEMAP_BY_WEBSITE_AND_TYPE
                ]
            );

        $this->createProcessorWithTestJobRunner();

        $this->assertEquals(MessageProcessorInterface::REJECT, $this->processor->process($message, $this->session));
    }

    public function testProcessWhenSitemapDumperSucceeds()
    {
        $messageBody = ['jobId' => self::JOB_ID, 'websiteId' => self::WEBSITE_ID, 'type' => self::TYPE];

        /** @var MessageInterface|\PHPUnit_Framework_MockObject_MockObject $message */
        $message = $this->createMock(MessageInterface::class);
        $message
            ->expects($this->any())
            ->method('getBody')
            ->willReturn(json_encode($messageBody));

        $this->expectWebsiteExists(true);

        /** @var WebsiteInterface $website */
        $website = $this->createMock(WebsiteInterface::class);

        $this->expectFindWebsite($website);

        $this->expectProviderExists(true);

        $this->sitemapDumper
            ->expects($this->once())
            ->method('dump')
            ->with($website, self::TYPE);

        $this->logger
            ->expects($this->never())
            ->method('error');

        $this->createProcessorWithTestJobRunner();

        $this->assertEquals(MessageProcessorInterface::ACK, $this->processor->process($message, $this->session));
    }

    /**
     * @param bool $websiteExists
     */
    private function expectWebsiteExists($websiteExists)
    {
        $this->websiteRepository
            ->expects($this->once())
            ->method('checkWebsiteExists')
            ->with(self::WEBSITE_ID)
            ->willReturn($websiteExists);
    }

    /**
     * @param bool $providerExists
     */
    private function expectProviderExists($providerExists)
    {
        $this->providerRegistry
            ->expects($this->once())
            ->method('hasProviderByName')
            ->with(self::TYPE)
            ->willReturn($providerExists);
    }

    /**
     * @param WebsiteInterface $website
     */
    private function expectFindWebsite(WebsiteInterface $website)
    {
        $this->websiteRepository
            ->expects($this->once())
            ->method('find')
            ->with(self::WEBSITE_ID)
            ->willReturn($website);
    }

    private function createProcessorWithTestJobRunner()
    {
        $this->jobRunner = new TestJobRunner();

        $this->processor = new SitemapGenerationProcessor(
            $this->jobRunner,
            $this->logger,
            $this->sitemapDumper,
            $this->websiteRepository,
            $this->providerRegistry
        );
    }
}
