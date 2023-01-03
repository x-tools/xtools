<?php

declare(strict_types = 1);

namespace App\Helper;

use DateTime;
use IntlDateFormatter;
use Intuition;
use NumberFormatter;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * The I18nHelper centralizes all methods for i18n and l10n,
 * and interactions with the Intution library.
 */
class I18nHelper
{
    private Intuition $intuition;
    protected ContainerInterface $container;
    protected IntlDateFormatter $dateFormatter;
    protected NumberFormatter $numFormatter;
    protected NumberFormatter $percentFormatter;
    protected RequestStack $requestStack;
    protected SessionInterface $session;

    /**
     * Constructor for the I18nHelper.
     * @param ContainerInterface $container
     * @param RequestStack $requestStack
     * @param SessionInterface $session
     */
    public function __construct(
        ContainerInterface $container,
        RequestStack $requestStack,
        SessionInterface $session
    ) {
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
    public function getIntuition(): Intuition
    {
        // Don't recreate the object.
        if (isset($this->intuition)) {
            return $this->intuition;
        }

        // Find the path, and complain if English doesn't exist.
        $path = $this->container->getParameter('kernel.root_dir') . '/../i18n';
        if (!file_exists("$path/en.json")) {
            throw new Exception("Language directory doesn't exist: $path");
        }

        $useLang = 'en';

        // Current request doesn't exist in unit tests, in which case we'll fall back to English.
        if (null !== $this->getRequest()) {
            $useLang = $this->getIntuitionLang();

            // Save the language to the session.
            if ($this->session->get('lang') !== $useLang) {
                $this->session->set('lang', $useLang);
            }
        }

        // Set up Intuition, using the selected language.
        $intuition = new Intuition('xtools');
        $intuition->registerDomain('xtools', $path);
        $intuition->setLang(strtolower($useLang));

        $this->intuition = $intuition;
        return $intuition;
    }

    /**
     * Get the current language code.
     * @return string
     */
    public function getLang(): string
    {
        return $this->getIntuition()->getLang();
    }

    /**
     * Get the current language name (defaults to 'English').
     * @return string
     */
    public function getLangName(): string
    {
        return in_array(ucfirst($this->getIntuition()->getLangName()), $this->getAllLangs())
            ? $this->getIntuition()->getLangName()
            : 'English';
    }

    /**
     * Get all available languages in the i18n directory
     * @return string[] Associative array of langKey => langName
     */
    public function getAllLangs(): array
    {
        $messageFiles = glob($this->container->getParameter('kernel.root_dir').'/../i18n/*.json');

        $languages = array_values(array_unique(array_map(
            function ($filename) {
                return basename($filename, '.json');
            },
            $messageFiles
        )));

        $availableLanguages = [];

        foreach ($languages as $lang) {
            $availableLanguages[$lang] = ucfirst($this->getIntuition()->getLangName($lang));
        }
        asort($availableLanguages);

        return $availableLanguages;
    }

    /**
     * Whether the current language is right-to-left.
     * @param string|null $lang Optionally provide a specific lanuage code.
     * @return bool
     */
    public function isRTL(?string $lang = null): bool
    {
        return $this->getIntuition()->isRTL(
            $lang ?? $this->getLang()
        );
    }

    /**
     * Get the fallback languages for the current or given language, so we know what to
     * load with jQuery.i18n. Languages for which no file exists are not returned.
     * @param string|null $useLang
     * @return string[]
     */
    public function getFallbacks(?string $useLang = null): array
    {
        $i18nPath = $this->container->getParameter('kernel.root_dir').'/../i18n/';
        $useLang = $useLang ?? $this->getLang();

        $fallbacks = array_merge(
            [$useLang],
            $this->getIntuition()->getLangFallbacks($useLang)
        );

        return array_filter($fallbacks, function ($lang) use ($i18nPath) {
            return is_file($i18nPath.$lang.'.json');
        });
    }

    /******************** MESSAGE HELPERS ********************/

    /**
     * Get an i18n message.
     * @param string|null $message
     * @param string[] $vars
     * @return string|null
     */
    public function msg(?string $message, array $vars = []): ?string
    {
        $vars = is_array($vars) ? $vars : [];
        return $this->getIntuition()->msg($message, ['domain' => 'xtools', 'variables' => $vars]);
    }

    /**
     * See if a given i18n message exists.
     * @param string|null $message The message.
     * @param string[] $vars
     * @return bool
     */
    public function msgExists(?string $message, array $vars = []): bool
    {
        return $this->getIntuition()->msgExists($message, array_merge(
            ['domain' => 'xtools'],
            ['variables' => is_array($vars) ? $vars : []]
        ));
    }

    /**
     * Get an i18n message if it exists, otherwise just get the message key.
     * @param string|null $message
     * @param string[] $vars
     * @return string
     */
    public function msgIfExists(?string $message, array $vars = []): string
    {
        if ($this->msgExists($message, $vars)) {
            return $this->msg($message, $vars);
        } else {
            return $message ?? '';
        }
    }

    /************************ NUMBERS ************************/

    /**
     * Format a number based on language settings.
     * @param int|float $number
     * @param int $decimals Number of decimals to format to.
     * @return string
     */
    public function numberFormat($number, int $decimals = 0): string
    {
        if (!isset($this->numFormatter)) {
            $lang = $this->getNumberFormatterLang();
            $this->numFormatter = new NumberFormatter($lang, NumberFormatter::DECIMAL);
        }

        $this->numFormatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

        return $this->numFormatter->format($number);
    }

    /**
     * Format a given number or fraction as a percentage.
     * @param int|float $numerator Numerator or single fraction if denominator is omitted.
     * @param int|null $denominator Denominator.
     * @param integer $precision Number of decimal places to show.
     * @return string Formatted percentage.
     */
    public function percentFormat($numerator, ?int $denominator = null, int $precision = 1): string
    {
        if (!isset($this->percentFormatter)) {
            $lang = $this->getNumberFormatterLang();
            $this->percentFormatter = new NumberFormatter($lang, NumberFormatter::PERCENT);
        }

        $this->percentFormatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $precision);

        if (null === $denominator) {
            $quotient = $numerator / 100;
        } elseif (0 === $denominator) {
            $quotient = 0;
        } else {
            $quotient = $numerator / $denominator;
        }

        return $this->percentFormatter->format($quotient);
    }

    /************************ DATES ************************/

    /**
     * Localize the given date based on language settings.
     * @param string|int|DateTime $datetime
     * @param string $pattern Format according to this ICU date format.
     * @see http://userguide.icu-project.org/formatparse/datetime
     * @return string
     */
    public function dateFormat($datetime, string $pattern = 'yyyy-MM-dd HH:mm'): string
    {
        if (!isset($this->dateFormatter)) {
            $this->dateFormatter = new IntlDateFormatter(
                $this->getNumberFormatterLang(),
                IntlDateFormatter::SHORT,
                IntlDateFormatter::SHORT
            );
        }

        if (is_string($datetime)) {
            $datetime = new DateTime($datetime);
        } elseif (is_int($datetime)) {
            $datetime = DateTime::createFromFormat('U', (string)$datetime);
        } elseif (!is_a($datetime, 'DateTime')) {
            return ''; // Unknown format.
        }

        $this->dateFormatter->setPattern($pattern);

        return $this->dateFormatter->format($datetime);
    }

    /********************* PRIVATE METHODS *********************/

    /**
     * TODO: Remove this when the fallbacks start working on their own. Production for some reason
     *   doesn't seem to know about ckb, though it has the same version of PHP and ext-intl as my local...
     * @see T213503
     * @return string
     */
    private function getNumberFormatterLang(): string
    {
        return 'ckb' === $this->getIntuition()->getLang() ? 'ar' : $this->getIntuition()->getLang();
    }

    /**
     * Determine the interface language, either from the current request or session.
     * @return string
     */
    private function getIntuitionLang(): string
    {
        $queryLang = $this->getRequest()->query->get('uselang');
        $sessionLang = $this->session->get('lang');

        if ('' !== $queryLang && null !== $queryLang) {
            return $queryLang;
        } elseif ('' !== $sessionLang && null !== $sessionLang) {
            return $sessionLang;
        }

        // English as default.
        return 'en';
    }

    /**
     * Shorthand to get the current request from the request stack.
     * @return Request|null Null in test suite.
     * There is no request stack in the tests.
     * @codeCoverageIgnore
     */
    private function getRequest(): ?Request
    {
        return $this->container->get('request_stack')->getCurrentRequest();
    }
}
