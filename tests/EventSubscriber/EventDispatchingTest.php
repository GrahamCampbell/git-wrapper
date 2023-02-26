<?php

declare(strict_types=1);

namespace GrahamCampbell\GitWrapper\Tests\EventSubscriber;

use GrahamCampbell\GitWrapper\Tests\AbstractGitWrapperTestCase;

final class EventDispatchingTest extends AbstractGitWrapperTestCase
{
    public function test(): void
    {
        $eventSubscriber = $this->registerAndReturnEventSubscriber();
        $this->gitWrapper->version();

        self::assertTrue($eventSubscriber->wasMethodCalled('onPrepare'));
        self::assertTrue($eventSubscriber->wasMethodCalled('onSuccess'));
        self::assertFalse($eventSubscriber->wasMethodCalled('onError'));
        self::assertFalse($eventSubscriber->wasMethodCalled('onBypass'));
    }

    public function testError(): void
    {
        $eventSubscriber = $this->registerAndReturnEventSubscriber();
        $this->runBadCommand(true);

        self::assertTrue($eventSubscriber->wasMethodCalled('onPrepare'));
        self::assertFalse($eventSubscriber->wasMethodCalled('onSuccess'));
        self::assertTrue($eventSubscriber->wasMethodCalled('onError'));
        self::assertFalse($eventSubscriber->wasMethodCalled('onBypass'));
    }

    public function testGitBypass(): void
    {
        $this->createRegisterAndReturnBypassEventSubscriber();
        $eventSubscriber = $this->registerAndReturnEventSubscriber();

        $output = $this->gitWrapper->version();

        self::assertTrue($eventSubscriber->wasMethodCalled('onPrepare'));
        self::assertFalse($eventSubscriber->wasMethodCalled('onSuccess'));
        self::assertFalse($eventSubscriber->wasMethodCalled('onError'));
        self::assertTrue($eventSubscriber->wasMethodCalled('onBypass'));

        self::assertEmpty($output);
    }
}
