<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\LocaleSubscriber;
use App\EventSubscriber\RedirectToPreferredLocaleSubscriber;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class LocaleSubscribersTest extends TestCase
{
	public function testHomepageUsesPreferredBrowserLocaleWithoutRedirect(): void
	{
		$request = Request::create('/', 'GET', [], [], [], [
				'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9,sl;q=0.8',
		]);
		$session = new Session(new MockArraySessionStorage());
		$request->setSession($session);

		$subscriber = new RedirectToPreferredLocaleSubscriber('en|sl', 'sl', new NullLogger());
		$event = $this->createRequestEvent($request);

		$subscriber->onKernelRequest($event);

		self::assertSame('en', $request->getLocale());
		self::assertSame('en', $session->get('_locale'));
		self::assertFalse($event->hasResponse());
	}

	public function testHomepageRespectsExistingSessionLocale(): void
	{
		$request = Request::create('/', 'GET', [], [], [], [
				'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9,sl;q=0.8',
		]);
		$session = new Session(new MockArraySessionStorage());
		$session->set('_locale', 'sl');
		$request->setSession($session);
		$request->setLocale('sl');

		$subscriber = new RedirectToPreferredLocaleSubscriber('en|sl', 'sl', new NullLogger());
		$event = $this->createRequestEvent($request);

		$subscriber->onKernelRequest($event);

		self::assertSame('sl', $request->getLocale());
		self::assertSame('sl', $session->get('_locale'));
		self::assertFalse($event->hasResponse());
	}

	public function testExplicitLocaleIsAppliedWithoutPreviousSession(): void
	{
		$request = Request::create('/', 'GET', ['_locale' => 'en']);
		$session = new Session(new MockArraySessionStorage());
		$request->setSession($session);

		$subscriber = new LocaleSubscriber(new NullLogger(), 'sl');
		$event = $this->createRequestEvent($request);

		$subscriber->onKernelRequest($event);

		self::assertSame('en', $request->getLocale());
		self::assertSame('en', $session->get('_locale'));
	}

	private function createRequestEvent(Request $request): RequestEvent
	{
		$kernel = $this->createMock(HttpKernelInterface::class);

		return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
	}
}
