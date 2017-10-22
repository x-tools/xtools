<?php
/**
 * This file contains only the QuoteController class.
 */

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\HttpFoundation\Request;

/**
 * A quick note: This tool is referred to as "bash" in much of the legacy code base.  As such,
 * the terms "quote" and "bash" are used interchangeably here, so as to not break many conventions.
 *
 * This tool is intentionally disabled in the WMF installation.
 * @codeCoverageIgnore
 */
class QuoteController extends Controller
{

    /**
     * Get the tool's shortname.
     * @return string
     * @codeCoverageIgnore
     */
    public function getToolShortname()
    {
        return 'bash';
    }

    /**
     * Method for rendering the Bash Main Form.
     * This method redirects if valid parameters are found, making it a
     * valid form endpoint as well.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request Given by Symfony
     *
     * @Route("/bash",  name="bash")
     * @Route("/quote", name="quote")
     * @Route("/bash/base.php", name="bashBase")
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Request $request)
    {
        // Check to see if the quote is a param.  If so,
        // redirect to the proper route.
        if ($request->query->get('id') != '') {
            return $this->redirectToRoute(
                "quoteID",
                ["id"=>$request->query->get('id')]
            );
        }

        // Oterwise render the form.
        return $this->render(
            'quote/index.html.twig',
            [
                'base_dir' => realpath($this->getParameter('kernel.root_dir').'/..'),
                'xtPage' => 'bash',
                'xtPageTitle' => 'tool-bash',
                'xtSubtitle' => 'tool-bash-desc',
            ]
        );
    }

    /**
     * Method for rendering a random quote.
     * This should redirect to the /quote/{id} path below
     *
     * @Route("/quote/random", name="quoteRandom")
     * @Route("/bash/random",  name="bashRandom")
     *
     * @return RedirectResponse
     */
    public function randomQuoteAction()
    {
        // Choose a random quote by ID.
        // if we can't find the quotes, return back to  the main form with
        // a flash notice.
        try {
            $id = rand(1, sizeof($this->getParameter("quotes")));
        } catch (InvalidParameterException $e) {
            $this->addFlash("notice", ["noquotes"]);
            return $this->redirectToRoute("quote");
        }

        return $this->redirectToRoute("quoteID", ["id"=>$id]);
    }

    /**
     * Method to show all quotes.
     *
     * @Route("/quote/all", name="quoteAll")
     * @Route("/bash/all",  name="bashAll")
     *
     * @return Response
     */
    public function quoteAllAction()
    {
        // Load up an array of all the quotes.
        // if we can't find the quotes, return back to  the main form with
        // a flash notice.
        try {
            $quotes = $this->getParameter("quotes");
        } catch (InvalidParameterException $e) {
            $this->addFlash("notice", ["noquotes"]);
            return $this->redirectToRoute("quote");
        }

        // Render the page.
        return $this->render(
            'quote/all.html.twig',
            [
                'base_dir' => realpath(
                    $this->getParameter('kernel.root_dir') . '/..'
                ),
                'xtPage' => 'bash',
                'quotes' => $quotes,
            ]
        );
    }

    /**
     * Method to render a single quote.
     *
     * @param int $id ID of the quote
     *
     * @Route("/quote/{id}", name="quoteID")
     * @Route("/bash/{id}",  name="bashID")
     *
     * @return Response
     */
    public function quoteAction($id)
    {
        // Get the singular quote.
        // if we can't find the quotes, return back to  the main form with
        // a flash notice.
        try {
            if (isset($this->getParameter("quotes")[$id])) {
                $text = $this->getParameter("quotes")[$id];
            } else {
                throw new InvalidParameterException("Quote doesn't exist");
            }
        } catch (InvalidParameterException $e) {
            $this->addFlash("notice", ["noquotes"]);
            return $this->redirectToRoute("quote");
        }

        // If the text is undefined, that quote doesn't exist.
        // Redirect back to the main form.
        if (!isset($text)) {
            $this->addFlash("notice", ["noquotes"]);
            return $this->redirectToRoute("quote");
        }

        // Show the quote.
        return $this->render(
            'quote/view.html.twig',
            [
                'base_dir' => realpath(
                    $this->getParameter('kernel.root_dir') . '/..'
                ),
                "xtPage" => "bash",
                "text" => $text,
                "id" => $id,
            ]
        );
    }
}
