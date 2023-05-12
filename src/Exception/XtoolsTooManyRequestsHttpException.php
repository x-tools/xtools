<?php

declare(strict_types = 1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;
use Twig\Markup;

class XtoolsTooManyRequestsHttpException extends TooManyRequestsHttpException
{
    protected Markup $markup;

    /**
     * @param Markup $markup
     * @param int $retryAfter
     * @param Throwable|null $previous
     * @param int|null $code
     * @param array $headers
     */
    public function __construct(
        Markup $markup,
        int $retryAfter,
        ?Throwable $previous = null,
        ?int $code = 0,
        array $headers = []
    ) {
        $this->markup = $markup;
        parent::__construct($retryAfter, $this->getMessage(), $previous, $code, $headers);
    }

    /**
     * @return Markup
     */
    public function __toString(): string
    {
        return (string)$this->markup;
    }
}
