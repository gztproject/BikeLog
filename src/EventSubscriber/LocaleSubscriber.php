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
	private $logger;
	public function __construct(LoggerInterface $logger, $defaultLocale = 'en') {
		$this->logger = $logger;
		$this->defaultLocale = $defaultLocale;
	}
	
	public function onKernelRequest(RequestEvent $event): void {
		$request = $event->getRequest ();

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
			$request->setLocale ( $request->getSession ()->get ( '_locale', $this->defaultLocale ) );
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
		return $request->attributes->get ( '_locale' )
			?? $request->query->get ( '_locale' )
			?? $request->request->get ( '_locale' );
	}
}
