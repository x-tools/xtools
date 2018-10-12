<?php
declare(strict_types = 1);

/**
 * This file contains only the XtoolsHttpException class.
 */

namespace AppBundle\Exception;

/**
 * An XtoolsHttpException is used to show error messages based on user input and redirect back to a route.
 */
class XtoolsHttpException extends \RuntimeException
{
    /** @var string What URL to redirect to. */
    protected $redirectUrl;

    /** @var mixed[] The params to pass in with the URL. */
    protected $params;

    /** @var bool Whether the exception was thrown as part of an API request. */
    protected $api;

    /**
     * XtoolsHttpException constructor.
     * @param string $message
     * @param string $redirectUrl
     * @param mixed[] $params Params to pass in with the redirect URL.
     * @param bool $api Whether this is thrown during an API request.
     */
    public function __construct(string $message, string $redirectUrl, array $params = [], bool $api = false)
    {
        $this->redirectUrl = $redirectUrl;
        $this->params = $params;
        $this->api = $api;

        parent::__construct($message);
    }

    /**
     * The URL that should be redirected to.
     * @return string
     */
    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }

    /**
     * Get the configured parameters, which should be the same parameters parsed from the Request,
     * and passed to the $redirectUrl when handled in the ExceptionListener.
     * @return mixed[]
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Whether this exception was thrown as part of a request to the API.
     * @return bool
     */
    public function isApi(): bool
    {
        return $this->api;
    }
}
