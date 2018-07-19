<?php
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

    /** @var array The params to pass in with the URL. */
    protected $params;

    /** @var bool Whether the exception was thrown as part of an API request. */
    protected $api;

    /**
     * XtoolsHttpException constructor.
     * @param string $message
     * @param string $redirectUrl
     * @param array $params Params to pass in with the redirect URL.
     * @param bool $api Whether this is thrown during an API request.
     */
    public function __construct($message, $redirectUrl, $params = [], $api = false)
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
    public function getRedirectUrl()
    {
        return $this->redirectUrl;
    }

    /**
     * Get the configured parameters, which should be the same parameters parsed from the Request,
     * and passed to the $redirectUrl when handled in the ExceptionListener.
     * @return array
     */
    public function getParams()
    {
        return $this->params;
    }

    /**
     * Whether this exception was thrown as part of a request to the API.
     * @return bool
     */
    public function isApi()
    {
        return $this->api;
    }
}
