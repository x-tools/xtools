<?php
/**
 * This file contains only the QuoteController class.
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
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
     * Get the name of the tool's index route.
     * @return string
     * @codeCoverageIgnore
     */
    public function getIndexRoute()
    {
        return 'Quote';
    }

    /**
     * Method for rendering the Bash Main Form. This method redirects if valid parameters are found,
     * making it a valid form endpoint as well.
     * @Route("/bash", name="Bash")
     * @Route("/quote", name="Quote")
     * @Route("/bash/base.php", name="BashBase")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction()
    {
        // Check to see if the quote is a param.  If so,
        // redirect to the proper route.
        if ($this->request->query->get('id') != '') {
            return $this->redirectToRoute(
                'QuoteID',
                ['id' => $this->request->query->get('id')]
            );
        }

        // Otherwise render the form.
        return $this->render(
            'quote/index.html.twig',
            [
                'xtPage' => 'bash',
                'xtPageTitle' => 'tool-bash',
                'xtSubtitle' => 'tool-bash-desc',
            ]
        );
    }

    /**
     * Method for rendering a random quote. This should redirect to the /quote/{id} path below.
     * @Route("/quote/random", name="QuoteRandom")
     * @Route("/bash/random", name="BashRandom")
     * @return RedirectResponse
     */
    public function randomQuoteAction()
    {
        // Choose a random quote by ID. If we can't find the quotes, return back to
        // the main form with a flash notice.
        try {
            $id = rand(1, sizeof($this->getParameter('quotes')));
        } catch (InvalidParameterException $e) {
            $this->addFlash('notice', ['noquotes']);
            return $this->redirectToRoute('Quote');
        }

        return $this->redirectToRoute('quoteID', ['id' => $id]);
    }

    /**
     * Method to show all quotes.
     * @Route("/quote/all", name="QuoteAll")
     * @Route("/bash/all", name="BashAll")
     * @return Response
     */
    public function quoteAllAction()
    {
        // Load up an array of all the quotes.
        // if we can't find the quotes, return back to  the main form with
        // a flash notice.
        try {
            $quotes = $this->getParameter('quotes');
        } catch (InvalidParameterException $e) {
            $this->addFlash('notice', ['noquotes']);
            return $this->redirectToRoute('Quote');
        }

        // Render the page.
        return $this->render(
            'quote/all.html.twig',
            [
                'xtPage' => 'bash',
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
    public function quoteAction($id)
    {
        // Get the singular quote.
        // if we can't find the quotes, return back to  the main form with
        // a flash notice.
        try {
            if (isset($this->getParameter('quotes')[$id])) {
                $text = $this->getParameter('quotes')[$id];
            } else {
                throw new InvalidParameterException("Quote doesn't exist'");
            }
        } catch (InvalidParameterException $e) {
            $this->addFlash('notice', ['noquotes']);
            return $this->redirectToRoute('Quote');
        }

        // If the text is undefined, that quote doesn't exist.
        // Redirect back to the main form.
        if (!isset($text)) {
            $this->addFlash('notice', ['noquotes']);
            return $this->redirectToRoute('Quote');
        }

        // Show the quote.
        return $this->render(
            'quote/view.html.twig',
            [
                'xtPage' => 'bash',
                'text' => $text,
                'id' => $id,
            ]
        );
    }

    /************************ API endpoints ************************/

    /**
     * Get random quote.
     * @Route("/api/quote/random", name="QuoteApiRandom")
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function randomQuoteApiAction()
    {
        $this->recordApiUsage('quote/random');

        // Don't show if bash is turned off, but always show for Labs (so quote is in footer but not in nav).
        $isLabs = $this->container->getParameter('app.is_labs');
        if (!$isLabs && !$this->container->getParameter('enable.bash')) {
            return '';
        }
        $quotes = $this->container->getParameter('quotes');
        $id = array_rand($quotes);

        return new JsonResponse(
            [$id => $quotes[$id]],
            Response::HTTP_OK
        );
    }

    /**
     * Get all quotes.
     * @Route("/api/quote/all", name="QuoteApiAll")
     * @return Response
     * @codeCoverageIgnore
     */
    public function allQuotesApiAction()
    {
        $this->recordApiUsage('quote/all');

        // Don't show if bash is turned off, but always show for Labs
        // (so quote is in footer but not in nav).
        $isLabs = $this->container->getParameter('app.is_labs');
        if (!$isLabs && !$this->container->getParameter('enable.bash')) {
            return '';
        }
        $quotes = $this->container->getParameter('quotes');
        $numberedQuotes = [];

        // Number the quotes, since they somehow have significance.
        foreach ($quotes as $index => $quote) {
            $numberedQuotes[(string)($index + 1)] = $quote;
        }

        return new JsonResponse($numberedQuotes, Response::HTTP_OK);
    }

    /**
     * Get the quote with the given ID.
     * @Route("/api/quote/{id}", name="QuoteApiQuote", requirements={"id"="\d+"})
     * @param int $id
     * @return Response|string
     * @codeCoverageIgnore
     */
    public function singleQuotesApiAction($id)
    {
        $this->recordApiUsage('quote/id');

        // Don't show if bash is turned off, but always show for Labs
        // (so quote is in footer but not in nav).
        $isLabs = $this->container->getParameter('app.is_labs');
        if (!$isLabs && !$this->container->getParameter('enable.bash')) {
            return '';
        }
        $quotes = $this->container->getParameter('quotes');

        if (!isset($quotes[$id])) {
            return new JsonResponse(
                [
                    'error' => [
                        'code' => Response::HTTP_NOT_FOUND,
                        'message' => 'No quote found with ID '.$id,
                    ]
                ],
                Response::HTTP_NOT_FOUND
            );
        }

        return new JsonResponse([
            $id => $quotes[$id]
        ], Response::HTTP_OK);
    }
}
