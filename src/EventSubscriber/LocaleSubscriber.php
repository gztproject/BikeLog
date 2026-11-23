<?php

// src/EventSubscriber/LocaleSubscriber.php
namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Psr\Log\LoggerInterface;

class LocaleSubscriber implements EventSubscriberInterface {
	private $defaultLocale;
	private $locales;
	private $logger;
	public function __construct(LoggerInterface $logger, $defaultLocale = 'en', string $locales = 'en|sl') {
		$this->logger = $logger;
		$this->locales = array_values ( array_filter ( explode ( '|', trim ( $locales ) ), 'strlen' ) );
		if (empty ( $this->locales )) {
			throw new \UnexpectedValueException ( 'The list of supported locales must not be empty.' );
		}
		if (! \in_array ( $defaultLocale, $this->locales, true )) {
			throw new \UnexpectedValueException ( sprintf ( 'The default locale ("%s") must be one of "%s".', $defaultLocale, $locales ) );
		}
		$this->defaultLocale = $defaultLocale;
	}
	
	public function onKernelRequest(RequestEvent $event): void {
		$request = $event->getRequest ();

		// Symfony's LocaleListener runs after this subscriber and applies the _locale attribute unchecked.
		if ($request->attributes->has ( '_locale' ) && ! $this->isEnabledLocale ( $request->attributes->get ( '_locale' ) )) {
			$request->attributes->remove ( '_locale' );
		}

		// try to see if the locale has been set as a _locale routing parameter
		if ($locale = $this->getRequestedLocale ( $request )) {
			$this->logger->debug("Setting requested locale: ". $locale);
			if ($request->hasSession ()) {
				$request->getSession ()->set ( '_locale', $locale );
			}
			$request->setLocale ( $locale );
			return;
		}

		if ($request->hasPreviousSession ()) {
			// if no explicit locale has been set on this request, use one from the session
			$session = $request->getSession ();
			$locale = $session->get ( '_locale', $this->defaultLocale );
			if (! $this->isEnabledLocale ( $locale )) {
				$session->remove ( '_locale' );
				$locale = $this->defaultLocale;
			}
			$request->setLocale ( $locale );
			$this->logger->debug("Settting default locale: ". $this->defaultLocale);
			return;
		}

		$request->setLocale ( $this->defaultLocale );
		$this->logger->debug("No existing session locale found, using the default locale.");
	}
	public static function getSubscribedEvents(): array {
		return [ 
				// must be registered before (i.e. with a higher priority than) the default Locale listener
				KernelEvents::REQUEST => [ 
						[ 
								'onKernelRequest',
								20
						]
				]
		];
	}

	private function getRequestedLocale(Request $request): ?string
	{
		$locale = $request->attributes->get ( '_locale' )
			?? $request->query->all ()['_locale']
			?? $request->request->all ()['_locale']
			?? null;

		return $this->isEnabledLocale ( $locale ) ? $locale : null;
	}

	private function isEnabledLocale($locale): bool
	{
		return \is_string ( $locale ) && \in_array ( $locale, $this->locales, true );
	}
}
