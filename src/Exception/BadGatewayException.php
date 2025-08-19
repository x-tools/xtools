<?php

declare(strict_types = 1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * A BadGatewayException is for custom handling of upstream errors that are beyond the control of
 * XTools maintainers. These errors (502s) are not logged by Monolog and hence no error email is sent out.
 */
class BadGatewayException extends HttpException
{
    /**
     * @param string $msgKey i18n key
     * @param array $msgParams Params for i18n message, if applicable.
     * @param Throwable|null $e
     */
    public function __construct(string $msgKey, protected array $msgParams = [], ?Throwable $e = null)
    {
        parent::__construct(Response::HTTP_BAD_GATEWAY, $msgKey, $e);
    }

    /**
     * @return array
     */
    public function getMsgParams(): array
    {
        return $this->msgParams;
    }
}
