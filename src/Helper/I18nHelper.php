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

/**
 * The I18nHelper centralizes all methods for i18n and l10n,
 * and interactions with the Intuition library.
 */
class I18nHelper
{
    private string $projectDir;
    private \Closure $addFlash;
    protected ContainerInterface $container;
    protected Intuition $intuition;
    protected IntlDateFormatter $dateFormatter;
    protected NumberFormatter $numFormatter;
    protected NumberFormatter $percentFormatter;
    protected RequestStack $requestStack;

    /**
     * Constructor for the I18nHelper.
     * @param RequestStack $requestStack
     * @param string $projectDir
     */
    public function __construct(
        RequestStack $requestStack,
        string $projectDir
    ) {
        $this->requestStack = $requestStack;
        $this->projectDir = $projectDir;
    }

    /**
     * Get an Intuition object, set to the current language based on the query string or session
     * of the current request.
     * @return Intuition
     * @throws Exception If the 'i18n/en.json' file doesn't exist (as it's the default).
     */
    public function getIntuition(): Intuition
    {
        // Don't recreate the object.
        if (isset($this->intuition)) {
            return $this->intuition;
        }

        // Find the path, and complain if English doesn't exist.
        $path = $this->projectDir . '/i18n';
        if (!file_exists("$path/en.json")) {
            throw new Exception("Language directory doesn't exist: $path");
        }

        // Have to initialise these two here, because getIntuitionLang
        // uses an Intuition helper to validate lang codes.
        $intuition = new Intuition('xtools');
        $this->intuition = $intuition;

        $useLang = $this->getIntuitionLang();

        // Save the language to the session.
        $session = $this->requestStack->getSession();
        if ($session->get('lang') !== $useLang) {
            $session->set('lang', $useLang);
        }

        // Set up Intuition, using the selected language.
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
        $messageFiles = glob($this->projectDir.'/i18n/*.json');

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
     * @param string|null $lang Optionally provide a specific language code.
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
        $i18nPath = $this->projectDir.'/i18n/';
        $useLang = $useLang ?? $this->getLang();

        $fallbacks = array_merge(
            [$useLang],
            $this->getIntuition()->getLangFallbacks($useLang)
        );

        return array_filter($fallbacks, function ($lang) use ($i18nPath) {
            return is_file($i18nPath.$lang.'.json');
        });
    }

    /**
     * Set the function used to add flashes.
     * (As that comes from the controller.)
     * @param function $addFlash
     */
    public function setFlash(\Closure $addFlash): void
    {
        $this->addFlash = $addFlash;
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
            ['variables' => $vars]
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
        $lang = $this->getLangForTranslatingNumerals();
        if (!isset($this->numFormatter)) {
            $this->numFormatter = new NumberFormatter($lang, NumberFormatter::DECIMAL);
        }

        $this->numFormatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);

        return $this->numFormatter->format((float)$number ?? 0);
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
        $lang = $this->getLangForTranslatingNumerals();
        if (!isset($this->percentFormatter)) {
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
        $lang = $this->getLangForTranslatingNumerals();
        if (!isset($this->dateFormatter)) {
            $this->dateFormatter = new IntlDateFormatter(
                $lang,
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
     * Return the language to be used when translating numberals.
     * Currently this just disables numeral translation for Arabic.
     * @see https://mediawiki.org/wiki/Topic:Y4ufad47v5o4ebpe
     * @todo This should go by $wgTranslateNumerals.
     * @return string
     */
    private function getLangForTranslatingNumerals(): string
    {
        return 'ar' === $this->getIntuition()->getLang() ? 'en': $this->getIntuition()->getLang();
    }

    /**
     * Determine the interface language, either from the current request or session.
     * @return string
     */
    private function getIntuitionLang(): string
    {
        $queryLang = $this->getRequest()->query->get('uselang');
        $sessionLang = $this->requestStack->getSession()->get('lang');

        // English as the default
        $tempLang = 'en';
        if ('' !== $queryLang && null !== $queryLang) {
            $tempLang = $queryLang;
        } elseif ('' !== $sessionLang && null !== $sessionLang) {
            $tempLang = $sessionLang;
        }

        // Intuition::getLangName returns '' only for invalid language codes
        if ('' === $this->intuition->getLangName($tempLang)) {
            // Flash content is already escaped automatically.
            ($this->addFlash)('notice', 'No translations found for language '.$tempLang.', switching to en.');
            $tempLang = 'en';
        }

        return $tempLang
    }

    /**
     * Shorthand to get the current request from the request stack.
     * @return Request|null Null in test suite.
     * There is no request stack in the tests.
     * @codeCoverageIgnore
     */
    private function getRequest(): ?Request
    {
        return $this->requestStack->getCurrentRequest();
    }
}
