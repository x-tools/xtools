<?php
/**
 * This file contains only the Extension class.
 */

namespace AppBundle\Twig;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Intuition;
use Symfony\Component\HttpFoundation\Session\Session;
use Twig_Extension;

/**
 * The parent class for all of XTools' Twig extensions, in order to centralize the i18n set-up.
 */
abstract class Extension extends Twig_Extension
{

    /** @var ContainerInterface The DI container. */
    protected $container;

    /** @var RequestStack The request stack. */
    protected $requestStack;

    /** @var Session User's current session. */
    protected $session;

    /** @var Intuition The i18n object. */
    private $intuition;

    /**
     * Extension constructor.
     * @param ContainerInterface $container The DI container.
     * @param RequestStack $requestStack The request stack.
     * @param Session $session
     */
    public function __construct(ContainerInterface $container, RequestStack $requestStack, Session $session)
    {
        $this->container = $container;
        $this->requestStack = $requestStack;
        $this->session = $session;
    }

    /**
     * Get an Intuition object, set to the current language based on the query string or session
     * of the current request.
     * @return Intuition
     * @throws \Exception If the 'i18n/en.json' file doesn't exist (as it's the default).
     */
    protected function getIntuition()
    {
        // Don't recreate the object.
        if ($this->intuition instanceof Intuition) {
            return $this->intuition;
        }

        // Find the path, and complain if English doesn't exist.
        $path = $this->container->getParameter("kernel.root_dir") . '/../i18n';
        if (!file_exists("$path/en.json")) {
            throw new Exception("Language directory doesn't exist: $path");
        }

        // Determine the interface language.
        $queryLang = $this->requestStack->getCurrentRequest()->query->get('uselang');
        $sessionLang = $this->session->get("lang");
        $useLang = "en";
        if ($queryLang !== "" && $queryLang !== null) {
            $useLang = $queryLang;
        } elseif ($sessionLang !== "" && $sessionLang !== null) {
            $useLang = $sessionLang;
        }

        // Set up Intuition, using the selected language.
        $intuition = new Intuition('xtools');
        $intuition->registerDomain('xtools', $path);
        $intuition->setLang(strtolower($useLang));

        // Save the language to the session.
        if ($sessionLang !== $useLang) {
            $this->session->set("lang", $useLang);
        }

        // Return.
        $this->intuition = $intuition;
        return $intuition;
    }
}
