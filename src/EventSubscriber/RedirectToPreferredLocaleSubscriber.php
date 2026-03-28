<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Psr\Log\LoggerInterface;

/**
 * When visiting the homepage, this listener redirects the user to the most
 * appropriate localized version according to the browser settings.
 *
 * See https://symfony.com/doc/current/components/http_kernel/introduction.html#the-kernel-request-event
 *
 * @author Oleg Voronkovich <oleg-voronkovich@yandex.ru>
 */
class RedirectToPreferredLocaleSubscriber implements EventSubscriberInterface {
	private $locales;
	private $defaultLocale;
	private $logger;
	public function __construct(string $locales, string $defaultLocale, LoggerInterface $logger) {
		$this->logger = $logger;

		$this->locales = explode ( '|', trim ( $locales ) );
		if (empty ( $this->locales )) {
			throw new \UnexpectedValueException ( 'The list of supported locales must not be empty.' );
		}

		$this->defaultLocale = $defaultLocale ?: $this->locales [0];

		if (! \in_array ( $this->defaultLocale, $this->locales, true )) {
			throw new \UnexpectedValueException ( sprintf ( 'The default locale ("%s") must be one of "%s".', $this->defaultLocale, $locales ) );
		}

		// Add the default locale at the first position of the array,
		// because Symfony\HttpFoundation\Request::getPreferredLanguage
		// returns the first element when no an appropriate language is found
		array_unshift ( $this->locales, $this->defaultLocale );
		$this->locales = array_unique ( $this->locales );
	}
	public static function getSubscribedEvents(): array {
		return [ 
				KernelEvents::REQUEST => 'onKernelRequest'
		];
	}
	public function onKernelRequest(RequestEvent $event): void {
		$request = $event->getRequest ();

		// Ignore sub-requests and all URLs but the homepage
		if (! $event->isMainRequest () || '/' !== $request->getPathInfo ()) {
			return;
		}

		// Respect an explicit locale choice or a locale that is already persisted in the session.
		if ($this->getRequestedLocale ( $request ) || ($request->hasSession () && $request->getSession ()->has ( '_locale' ))) {
			return;
		}

		// Ignore requests from referrers with the same HTTP host in order to prevent
		// changing language for users who possibly already selected it for this application.
		$referer = $request->headers->get ( 'referer' );
		if ($referer && 0 === mb_stripos ( $referer, $request->getSchemeAndHttpHost () )) {
			return;
		}

		$preferredLanguage = $request->getPreferredLanguage ( $this->locales );

		if ($preferredLanguage && $preferredLanguage !== $this->defaultLocale) {
			if ($request->hasSession ()) {
				$request->getSession ()->set ( '_locale', $preferredLanguage );
			}

			$request->setLocale ( $preferredLanguage );
			$this->logger->debug ( 'Using preferred browser locale for the homepage request.', [
					'preferred_locale' => $preferredLanguage
			] );
		}
	}

	private function getRequestedLocale(Request $request): ?string
	{
		return $request->attributes->get ( '_locale' )
			?? $request->query->get ( '_locale' )
			?? $request->request->get ( '_locale' );
	}
}
