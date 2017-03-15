<?php

namespace AppBundle\Twig;

use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Intuition;
use Symfony\Component\HttpFoundation\Session\Session;
use Twig_Extension;

class Extension extends Twig_Extension
{

    /** @var ContainerInterface */
    protected $container;

    /** @var RequestStack */
    protected $requestStack;

    /** @var Session */
    protected $session;

    /** @var Intuition */
    private $intuition;

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
