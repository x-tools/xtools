<?php

declare(strict_types=1);

namespace App\Controller;

use OpenApi\Annotations as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Exception\InvalidParameterException;

/**
 * A quick note: This tool is referred to as "bash" in much of the legacy code base.  As such,
 * the terms "quote" and "bash" are used interchangeably here, so as to not break many conventions.
 *
 * This tool is intentionally disabled in the WMF installation.
 * @codeCoverageIgnore
 */
class QuoteController extends XtoolsController
{

    /**
     * @inheritDoc
     * @codeCoverageIgnore
     */
    public function getIndexRoute(): string
    {
        return 'Quote';
    }

    /**
     * Method for rendering the Bash Main Form. This method redirects if valid parameters are found,
     * making it a valid form endpoint as well.
     * @Route("/bash", name="Bash")
     * @Route("/quote", name="Quote")
     * @Route("/bash/base.php", name="BashBase")
     * @return Response
     */
    public function indexAction(): Response
    {
        // Check to see if the quote is a param.  If so,
        // redirect to the proper route.
        if ('' != $this->request->query->get('id')) {
            return $this->redirectToRoute(
                'QuoteID',
                ['id' => $this->request->query->get('id')]
            );
        }

        // Otherwise render the form.
        return $this->render(
            'quote/index.html.twig',
            [
                'xtPage' => 'Quote',
                'xtPageTitle' => 'tool-quote',
                'xtSubtitle' => 'tool-quote-desc',
            ]
        );
    }

    /**
     * Method for rendering a random quote. This should redirect to the /quote/{id} path below.
     * @Route("/quote/random", name="QuoteRandom")
     * @Route("/bash/random", name="BashRandom")
     * @return RedirectResponse
     */
    public function randomQuoteAction(): RedirectResponse
    {
        // Choose a random quote by ID. If we can't find the quotes, return back to
        // the main form with a flash notice.
        try {
            $id = rand(1, sizeof($this->getParameter('quotes')));
        } catch (InvalidParameterException $e) {
            $this->addFlashMessage('notice', 'noquotes');
            return $this->redirectToRoute('Quote');
        }

        return $this->redirectToRoute('QuoteID', ['id' => $id]);
    }

    /**
     * Method to show all quotes.
     * @Route("/quote/all", name="QuoteAll")
     * @Route("/bash/all", name="BashAll")
     * @return Response
     */
    public function quoteAllAction(): Response
    {
        // Load up an array of all the quotes.
        // if we can't find the quotes, return back to  the main form with
        // a flash notice.
        try {
            $quotes = $this->getParameter('quotes');
        } catch (InvalidParameterException $e) {
            $this->addFlashMessage('notice', 'noquotes');
            return $this->redirectToRoute('Quote');
        }

        // Render the page.
        return $this->render(
            'quote/all.html.twig',
            [
                'xtPage' => 'Quote',
                'quotes' => $quotes,
            ]
        );
    }

    /**
     * Method to render a single quote.
     * @param int $id ID of the quote
     * @Route("/quote/{id}", name="QuoteID")
     * @Route("/bash/{id}", name="BashID")
     * @return Response
     */
    public function quoteAction(int $id): Response
    {
        // Get the singular quote.
        // If we can't find the quotes, return back to  the main form with a flash notice.
        try {
            if (isset($this->getParameter('quotes')[$id])) {
                $text = $this->getParameter('quotes')[$id];
            } else {
                throw new InvalidParameterException("Quote doesn't exist'");
            }
        } catch (InvalidParameterException $e) {
            $this->addFlashMessage('notice', 'noquotes');
            return $this->redirectToRoute('Quote');
        }

        // If the text is undefined, that quote doesn't exist.
        // Redirect back to the main form.
        if (!isset($text)) {
            $this->addFlashMessage('notice', 'noquotes');
            return $this->redirectToRoute('Quote');
        }

        // Show the quote.
        return $this->render(
            'quote/view.html.twig',
            [
                'xtPage' => 'Quote',
                'text' => $text,
                'id' => $id,
            ]
        );
    }

    /************************ API endpoints ************************/

    /**
     * Get random quote.
     * @Route("/api/quote/random", name="QuoteApiRandom", methods={"GET"})
     * @OA\Tag(name="Quote API")
     * @OA\Get(description="Get a random quote. The quotes are sourced from [developer quips](https://w.wiki/6rpo)
           and [IRC quotes](https://meta.wikimedia.org/wiki/IRC/Quotes/archives).")
     * @OA\Response(
     *     response=200,
     *     description="Quote keyed by ID.",
     *     @OA\JsonContent(
     *         @OA\Property(property="<quote-id>", type="string")
     *     )
     * )
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function randomQuoteApiAction(): JsonResponse
    {
        $this->validateIsEnabled();

        $this->recordApiUsage('quote/random');
        $quotes = $this->getParameter('quotes');
        $id = array_rand($quotes);

        return new JsonResponse(
            [$id => $quotes[$id]],
            Response::HTTP_OK
        );
    }

    /**
     * Get all quotes.
     * @Route("/api/quote/all", name="QuoteApiAll", methods={"GET"})
     * @OA\Tag(name="Quote API")
     * @OA\Get(description="Get a list of all quotes, sourced from [developer quips](https://w.wiki/6rpo)
           and [IRC quotes](https://meta.wikimedia.org/wiki/IRC/Quotes/archives).")
     * @OA\Response(
     *     response=200,
     *     description="All quotes, keyed by ID.",
     *     @OA\JsonContent(
     *         @OA\Property(property="<quote-id>", type="string")
     *     )
     * )
     * @return Response
     * @codeCoverageIgnore
     */
    public function allQuotesApiAction(): Response
    {
        $this->validateIsEnabled();

        $this->recordApiUsage('quote/all');
        $quotes = $this->getParameter('quotes');
        $numberedQuotes = [];

        // Number the quotes, since they somehow have significance.
        foreach ($quotes as $index => $quote) {
            $numberedQuotes[(string)($index + 1)] = $quote;
        }

        return new JsonResponse($numberedQuotes, Response::HTTP_OK);
    }

    /**
     * Get the quote with the given ID.
     * @Route("/api/quote/{id}", name="QuoteApiQuote", requirements={"id"="\d+"}, methods={"GET"})
     * @OA\Tag(name="Quote API")
     * @OA\Get(description="Get a quote with the given ID.")
     * @OA\Parameter(name="id", in="path", required="true", @OA\Schema(type="integer", minimum=0))
     * @OA\Response(
     *     response=200,
     *     description="Quote keyed by ID.",
     *     @OA\JsonContent(
     *         @OA\Property(property="<quote-id>", type="string")
     *     )
     * )
     * @param int $id
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function singleQuotesApiAction(int $id): JsonResponse
    {
        $this->validateIsEnabled();

        $this->recordApiUsage('quote/id');
        $quotes = $this->getParameter('quotes');

        if (!isset($quotes[$id])) {
            return new JsonResponse(
                [
                    'error' => [
                        'code' => Response::HTTP_NOT_FOUND,
                        'message' => 'No quote found with ID '.$id,
                    ],
                ],
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse([
            $id => $quotes[$id],
        ], Response::HTTP_OK);
    }

    /**
     * Validate that the Quote tool is enabled, and throw a 404 if it is not.
     * This is normally done by DisabledToolSubscriber but we have special logic here, because for Labs we want to
     * show the quote in the footer but not expose the web interface.
     * @throws NotFoundHttpException
     */
    private function validateIsEnabled(): void
    {
        $isLabs = $this->getParameter('app.is_wmf');
        if (!$isLabs && !$this->getParameter('enable.Quote')) {
            throw $this->createNotFoundException('This tool is disabled');
        }
    }
}
