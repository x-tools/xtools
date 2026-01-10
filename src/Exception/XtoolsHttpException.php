<?php

declare( strict_types = 1 );

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * An XtoolsHttpException is used to show error messages based on user input and redirect back to a route.
 */
class XtoolsHttpException extends HttpException {
	/**
	 * XtoolsHttpException constructor.
	 * @param string $message
	 * @param string $redirectUrl
	 * @param array $params Params to pass in with the redirect URL.
	 * @param bool $api Whether this is thrown during an API request.
	 * @param int $statusCode
	 */
	public function __construct(
		string $message,
		/** @var string What URL to redirect to. */
		protected string $redirectUrl,
		/** @var array The params to pass in with the URL. */
		protected array $params = [],
		/** @var bool Whether the exception was thrown as part of an API request. */
		protected bool $api = false,
		int $statusCode = Response::HTTP_NOT_FOUND
	) {
		parent::__construct( $statusCode, $message );
	}

	/**
	 * The URL that should be redirected to.
	 * @return string
	 */
	public function getRedirectUrl(): string {
		return $this->redirectUrl;
	}

	/**
	 * Get the configured parameters, which should be the same parameters parsed from the Request,
	 * and passed to the $redirectUrl when handled in the ExceptionListener.
	 * @return array
	 */
	public function getParams(): array {
		return $this->params;
	}

	/**
	 * Whether this exception was thrown as part of a request to the API.
	 * @return bool
	 */
	public function isApi(): bool {
		return $this->api;
	}
}
