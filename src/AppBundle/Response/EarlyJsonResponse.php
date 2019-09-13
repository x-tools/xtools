<?php
declare(strict_types = 1);

namespace AppBundle\Response;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * An EarlyJsonResponse is used to send a JsonResponse to the client, and then execute arbitrary code afterwards.
 * This is used primarily for the XTools API, where we want to run database transactions that record usage of the API,
 * but not hold up returning a response in the event the db transaction is slow or fails entirely.
 * Code largely courtesy of Omn from https://stackoverflow.com/a/48352717/604142 - CC BY-SA 4.0
 */
class EarlyJsonResponse extends JsonResponse
{
    /** @var callable|null Callback to be executed after Response has been sent. */
    protected $callbackAction = null;

    /**
     * EarlyResponse constructor.
     * @param mixed $content
     * @param int $status
     * @param array $headers
     * @param callable|null $callback
     */
    public function __construct($content = '', int $status = 200, array $headers = [], ?callable $callback = null)
    {
        $this->callbackAction = $callback;
        parent::__construct($content, $status, $headers);
    }

    /**
     * Set the callback function.
     * @param callable $callback
     */
    public function setCallbackAction(callable $callback): void
    {
        $this->callbackAction = $callback;
    }

    /**
     * Send the Response early, allowing AppKernel::terminate() to run self::callTerminateCallback().
     * @return $this|JsonResponse
     */
    public function send()
    {
        // we don't need the hack when using fast CGI
        if (function_exists('fastcgi_finish_request') || 'cli' === PHP_SAPI) {
            return parent::send();
        }
        //prevent apache killing the process
        ignore_user_abort(true);
        // Check if an ob buffer exists already.
        if (!ob_get_level()) {
            // start the output buffer
            ob_start();
        }
        // Send the content to the buffer
        $this->sendContent();

        // Flush all but the last ob buffer level
        static::closeOutputBuffers(1, true);

        // Set the content length using the last ob buffer level
        $this->headers->set('Content-Length', ob_get_length());

        // Close the Connection
        $this->headers->set('Connection', 'close');

        // This invalid header value will make Apache not delay sending the response while it is
        // See: https://serverfault.com/questions/844526/apache-2-4-7-ignores-response-header-content-encoding-identity-instead-respect
        $this->headers->set('Content-Encoding', 'none');

        // Now that we have the headers, we can send them (which will avoid the ob buffers)
        $this->sendHeaders();

        // Flush the last ob buffer level
        static::closeOutputBuffers(0, true);

        // After we flush the OB buffer to the normal buffer, we still need to send the normal buffer to output
        flush();

        // Close session file on server side to avoid blocking other requests
        session_write_close();

        return $this;
    }

    /**
     * Execute the callback.
     * @return $this
     */
    public function callTerminateCallback()
    {
        if ($this->callbackAction) {
            call_user_func($this->callbackAction);
        }
        return $this;
    }
}
