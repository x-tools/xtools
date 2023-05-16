<?php

declare(strict_types = 1);

namespace App\Twig;

use App\Helper\I18nHelper;
use App\Model\Edit;
use App\Model\Project;
use App\Model\User;
use App\Repository\ProjectRepository;
use App\Repository\Repository;
use App\Traits\Chart;
use DateTime;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Wikimedia\IPUtils;

/**
 * Twig functions and filters for XTools.
 */
class AppExtension extends AbstractExtension
{
    use Chart;

    protected I18nHelper $i18n;
    protected ParameterBagInterface $parameterBag;
    protected ProjectRepository $projectRepo;
    protected RequestStack $requestStack;
    protected UrlGeneratorInterface $urlGenerator;

    protected bool $isWMF;
    protected int $replagThreshold;
    protected bool $singleWiki;

    /** @var float Duration of the current HTTP request in seconds. */
    protected float $requestTime;

    /**
     * Constructor, with the I18nHelper through dependency injection.
     * @param RequestStack $requestStack
     * @param I18nHelper $i18n
     * @param UrlGeneratorInterface $generator
     * @param ProjectRepository $projectRepo
     * @param ParameterBagInterface $parameterBag
     * @param bool $isWMF
     * @param bool $singleWiki
     * @param int $replagThreshold
     */
    public function __construct(
        RequestStack $requestStack,
        I18nHelper $i18n,
        UrlGeneratorInterface $generator,
        ProjectRepository $projectRepo,
        ParameterBagInterface $parameterBag,
        bool $isWMF,
        bool $singleWiki,
        int $replagThreshold
    ) {
        $this->requestStack = $requestStack;
        $this->i18n = $i18n;
        $this->urlGenerator = $generator;
        $this->projectRepo = $projectRepo;
        $this->parameterBag = $parameterBag;
        $this->isWMF = $isWMF;
        $this->singleWiki = $singleWiki;
        $this->replagThreshold = $replagThreshold;
    }

    /*********************************** FUNCTIONS ***********************************/

    /**
     * Get all functions that this class provides.
     * @return TwigFunction[]
     * @codeCoverageIgnore
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('request_time', [$this, 'requestTime']),
            new TwigFunction('memory_usage', [$this, 'requestMemory']),
            new TwigFunction('msgIfExists', [$this, 'msgIfExists'], ['is_safe' => ['html']]),
            new TwigFunction('msgExists', [$this, 'msgExists'], ['is_safe' => ['html']]),
            new TwigFunction('msg', [$this, 'msg'], ['is_safe' => ['html']]),
            new TwigFunction('lang', [$this, 'getLang']),
            new TwigFunction('langName', [$this, 'getLangName']),
            new TwigFunction('fallbackLangs', [$this, 'getFallbackLangs']),
            new TwigFunction('allLangs', [$this, 'getAllLangs']),
            new TwigFunction('isRTL', [$this, 'isRTL']),
            new TwigFunction('shortHash', [$this, 'gitShortHash']),
            new TwigFunction('hash', [$this, 'gitHash']),
            new TwigFunction('releaseDate', [$this, 'gitDate']),
            new TwigFunction('enabled', [$this, 'toolEnabled']),
            new TwigFunction('tools', [$this, 'tools']),
            new TwigFunction('color', [$this, 'getColorList']),
            new TwigFunction('chartColor', [$this, 'chartColor']),
            new TwigFunction('isSingleWiki', [$this, 'isSingleWiki']),
            new TwigFunction('getReplagThreshold', [$this, 'getReplagThreshold']),
            new TwigFunction('isWMF', [$this, 'isWMF']),
            new TwigFunction('replag', [$this, 'replag']),
            new TwigFunction('quote', [$this, 'quote']),
            new TwigFunction('bugReportURL', [$this, 'bugReportURL']),
            new TwigFunction('logged_in_user', [$this, 'loggedInUser']),
            new TwigFunction('isUserAnon', [$this, 'isUserAnon']),
            new TwigFunction('nsName', [$this, 'nsName']),
            new TwigFunction('titleWithNs', [$this, 'titleWithNs']),
            new TwigFunction('formatDuration', [$this, 'formatDuration']),
            new TwigFunction('numberFormat', [$this, 'numberFormat']),
            new TwigFunction('buildQuery', [$this, 'buildQuery']),
            new TwigFunction('login_url', [$this, 'loginUrl']),
        ];
    }

    /**
     * Get the duration of the current HTTP request in seconds.
     * @return float
     * Untestable since there is no request stack in the tests.
     * @codeCoverageIgnore
     */
    public function requestTime(): float
    {
        if (!isset($this->requestTime)) {
            $this->requestTime = microtime(true) - $this->getRequest()->server->get('REQUEST_TIME_FLOAT');
        }

        return $this->requestTime;
    }

    /**
     * Get the formatted real memory usage.
     * @return float
     */
    public function requestMemory(): float
    {
        $mem = memory_get_usage(false);
        $div = pow(1024, 2);
        return $mem / $div;
    }

    /**
     * Get an i18n message.
     * @param string $message
     * @param string[] $vars
     * @return string|null
     */
    public function msg(string $message = '', array $vars = []): ?string
    {
        return $this->i18n->msg($message, $vars);
    }

    /**
     * See if a given i18n message exists.
     * @param string|null $message The message.
     * @param string[] $vars
     * @return bool
     */
    public function msgExists(?string $message, array $vars = []): bool
    {
        return $this->i18n->msgExists($message, $vars);
    }

    /**
     * Get an i18n message if it exists, otherwise just get the message key.
     * @param string|null $message
     * @param string[] $vars
     * @return string
     */
    public function msgIfExists(?string $message, array $vars = []): string
    {
        return $this->i18n->msgIfExists($message, $vars);
    }

    /**
     * Get the current language code.
     * @return string
     */
    public function getLang(): string
    {
        return $this->i18n->getLang();
    }

    /**
     * Get the current language name (defaults to 'English').
     * @return string
     */
    public function getLangName(): string
    {
        return $this->i18n->getLangName();
    }

    /**
     * Get the fallback languages for the current language, so we know what to load with jQuery.i18n.
     * @return string[]
     */
    public function getFallbackLangs(): array
    {
        return $this->i18n->getFallbacks();
    }

    /**
     * Get all available languages in the i18n directory
     * @return string[] Associative array of langKey => langName
     */
    public function getAllLangs(): array
    {
        return $this->i18n->getAllLangs();
    }

    /**
     * Whether the current language is right-to-left.
     * @param string|null $lang Optionally provide a specific lanuage code.
     * @return bool
     */
    public function isRTL(?string $lang = null): bool
    {
        return $this->i18n->isRTL($lang);
    }

    /**
     * Get the short hash of the currently checked-out Git commit.
     * @return string
     */
    public function gitShortHash(): string
    {
        return exec('git rev-parse --short HEAD');
    }

    /**
     * Get the full hash of the currently checkout-out Git commit.
     * @return string
     */
    public function gitHash(): string
    {
        return exec('git rev-parse HEAD');
    }

    /**
     * Get the date of the HEAD commit.
     * @return string
     */
    public function gitDate(): string
    {
        $date = new DateTime(exec('git show -s --format=%ci'));
        return $this->dateFormat($date, 'yyyy-MM-dd');
    }

    /**
     * Check whether a given tool is enabled.
     * @param string $tool The short name of the tool.
     * @return bool
     */
    public function toolEnabled(string $tool = 'index'): bool
    {
        $param = false;
        if ($this->parameterBag->has("enable.$tool")) {
            $param = (bool)$this->parameterBag->get("enable.$tool");
        }
        return $param;
    }

    /**
     * Get a list of the short names of all tools.
     * @return string[]
     */
    public function tools(): array
    {
        return $this->parameterBag->get('tools');
    }

    /**
     * Get the color for a given namespace.
     * @param int|null $nsId Namespace ID.
     * @return string Hex value of the color.
     * @codeCoverageIgnore
     */
    public function getColorList(?int $nsId = null): string
    {
        return Chart::getColorList($nsId);
    }

    /**
     * Get color-blind friendly colors for use in charts
     * @param int $num Index of color
     * @return string RGBA color (so you can more easily adjust the opacity)
     */
    public function chartColor(int $num): string
    {
        return Chart::getChartColor($num);
    }

    /**
     * Whether XTools is running in single-project mode.
     * @return bool
     */
    public function isSingleWiki(): bool
    {
        return $this->singleWiki;
    }

    /**
     * Get the database replication-lag threshold.
     * @return int
     */
    public function getReplagThreshold(): int
    {
        return $this->replagThreshold;
    }

    /**
     * Whether XTools is running in WMF mode.
     * @return bool
     */
    public function isWMF(): bool
    {
        return $this->isWMF;
    }

    /**
     * The current replication lag.
     * @return int
     * @codeCoverageIgnore
     */
    public function replag(): int
    {
        $projectIdent = $this->getRequest()->get('project', 'enwiki');
        $project = $this->projectRepo->getProject($projectIdent);
        $dbName = $project->getDatabaseName();
        $sql = "SELECT lag FROM `heartbeat_p`.`heartbeat`";
        return (int)$project->getRepository()->executeProjectsQuery($project, $sql, [
            'project' => $dbName,
        ])->fetchOne();
    }

    /**
     * Get a random quote for the footer
     * @return string
     */
    public function quote(): string
    {
        // Don't show if Quote is turned off, but always show for WMF
        // (so quote is in footer but not in nav).
        if (!$this->isWMF && !$this->parameterBag->get('enable.Quote')) {
            return '';
        }
        $quotes = $this->parameterBag->get('quotes');
        $id = array_rand($quotes);
        return $quotes[$id];
    }

    /**
     * Get the currently logged in user's details.
     * @return string[]|object|null
     */
    public function loggedInUser()
    {
        return $this->requestStack->getSession()->get('logged_in_user');
    }

    /**
     * Get a URL to the login route with parameters to redirect back to the current page after logging in.
     * @param Request $request
     * @return string
     */
    public function loginUrl(Request $request): string
    {
        return $this->urlGenerator->generate('login', [
            'callback' => $this->urlGenerator->generate(
                'oauth_callback',
                ['redirect' => $request->getUri()],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
        ], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /*********************************** FILTERS ***********************************/

    /**
     * Get all filters for this extension.
     * @return TwigFilter[]
     * @codeCoverageIgnore
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('ucfirst', [$this, 'capitalizeFirst']),
            new TwigFilter('percent_format', [$this, 'percentFormat']),
            new TwigFilter('diff_format', [$this, 'diffFormat'], ['is_safe' => ['html']]),
            new TwigFilter('num_format', [$this, 'numberFormat']),
            new TwigFilter('size_format', [$this, 'sizeFormat']),
            new TwigFilter('date_format', [$this, 'dateFormat']),
            new TwigFilter('wikify', [$this, 'wikify']),
        ];
    }

    /**
     * Format a number based on language settings.
     * @param int|float $number
     * @param int $decimals Number of decimals to format to.
     * @return string
     */
    public function numberFormat($number, int $decimals = 0): string
    {
        return $this->i18n->numberFormat($number, $decimals);
    }

    /**
     * Format the given size (in bytes) as KB, MB, GB, or TB.
     * Some code courtesy of Leo, CC BY-SA 4.0
     * @see https://stackoverflow.com/a/2510459/604142
     * @param int $bytes
     * @param int $precision
     * @return string
     */
    public function sizeFormat(int $bytes, int $precision = 2): string
    {
        $base = log($bytes, 1024);
        $suffixes = ['', 'kilobytes', 'megabytes', 'gigabytes', 'terabytes'];

        $index = floor($base);

        if (0 === (int)$index) {
            return $this->numberFormat($bytes);
        }

        $sizeMessage = $this->numberFormat(
            pow(1024, $base - floor($base)),
            $precision
        );

        return $this->i18n->msg('size-'.$suffixes[floor($base)], [$sizeMessage]);
    }

    /**
     * Localize the given date based on language settings.
     * @param string|int|DateTime $datetime
     * @param string $pattern Format according to this ICU date format.
     * @see http://userguide.icu-project.org/formatparse/datetime
     * @return string
     */
    public function dateFormat($datetime, string $pattern = 'yyyy-MM-dd HH:mm'): string
    {
        return $this->i18n->dateFormat($datetime, $pattern);
    }

    /**
     * Convert raw wikitext to HTML-formatted string.
     * @param string $str
     * @param Project $project
     * @return string
     */
    public function wikify(string $str, Project $project): string
    {
        return Edit::wikifyString($str, $project);
    }

    /**
     * Mysteriously missing Twig helper to capitalize only the first character.
     * E.g. used for table headings for translated messages
     * @param string $str The string
     * @return string The string, capitalized
     */
    public function capitalizeFirst(string $str): string
    {
        return ucfirst($str);
    }

    /**
     * Format a given number or fraction as a percentage.
     * @param int|float $numerator Numerator or single fraction if denominator is ommitted.
     * @param int|null $denominator Denominator.
     * @param integer $precision Number of decimal places to show.
     * @return string Formatted percentage.
     */
    public function percentFormat($numerator, ?int $denominator = null, int $precision = 1): string
    {
        return $this->i18n->percentFormat($numerator, $denominator, $precision);
    }

    /**
     * Helper to return whether the given user is an anonymous (logged out) user.
     * @param User|string $user User object or username as a string.
     * @return bool
     */
    public function isUserAnon($user): bool
    {
        if ($user instanceof User) {
            $username = $user->getUsername();
        } else {
            $username = $user;
        }

        return IPUtils::isIPAddress($username);
    }

    /**
     * Helper to properly translate a namespace name.
     * @param int|string $namespace Namespace key as a string or ID.
     * @param string[] $namespaces List of available namespaces as retrieved from Project::getNamespaces().
     * @return string Namespace name
     */
    public function nsName($namespace, array $namespaces): string
    {
        if ('all' === $namespace) {
            return $this->i18n->msg('all');
        } elseif ('0' === $namespace || 0 === $namespace || 'Main' === $namespace) {
            return $this->i18n->msg('mainspace');
        } else {
            return $namespaces[$namespace] ?? $this->i18n->msg('unknown');
        }
    }

    /**
     * Given a page title and namespace, generate the full page title.
     * @param string $title
     * @param int $namespace
     * @param array $namespaces
     * @return string
     */
    public function titleWithNs(string $title, int $namespace, array $namespaces): string
    {
        if (0 === $namespace) {
            return $title;
        }
        return $this->nsName($namespace, $namespaces).':'.$title;
    }

    /**
     * Format a given number as a diff, colouring it green if it's positive, red if negative, gary if zero
     * @param int $size Diff size
     * @return string Markup with formatted number
     */
    public function diffFormat(int $size): string
    {
        if ($size < 0) {
            $class = 'diff-neg';
        } elseif ($size > 0) {
            $class = 'diff-pos';
        } else {
            $class = 'diff-zero';
        }

        $size = $this->numberFormat($size);

        return "<span class='$class'".
            ($this->i18n->isRTL() ? " dir='rtl'" : '').
            ">$size</span>";
    }

    /**
     * Format a time duration as humanized string.
     * @param int $seconds Number of seconds.
     * @param bool $translate Used for unit testing. Set to false to return
     *   the value and i18n key, instead of the actual translation.
     * @return string|array Examples: '30 seconds', '2 minutes', '15 hours', '500 days',
     *   or [30, 'num-seconds'] (etc.) if $translate is false.
     */
    public function formatDuration(int $seconds, bool $translate = true)
    {
        [$val, $key] = $this->getDurationMessageKey($seconds);

        // The following messages are used here:
        // * num-days
        // * num-hours
        // * num-minutes
        if ($translate) {
            return $this->numberFormat($val).' '.$this->i18n->msg("num-$key", [$val]);
        } else {
            return [$this->numberFormat($val), "num-$key"];
        }
    }

    /**
     * Given a time duration in seconds, generate a i18n message key and value.
     * @param int $seconds Number of seconds.
     * @return array<integer|string> [int - message value, string - message key]
     */
    private function getDurationMessageKey(int $seconds): array
    {
        // Value to show in message
        $val = $seconds;

        // Unit of time, used in the key for the i18n message
        $key = 'seconds';

        if ($seconds >= 86400) {
            // Over a day
            $val = (int) floor($seconds / 86400);
            $key = 'days';
        } elseif ($seconds >= 3600) {
            // Over an hour, less than a day
            $val = (int) floor($seconds / 3600);
            $key = 'hours';
        } elseif ($seconds >= 60) {
            // Over a minute, less than an hour
            $val = (int) floor($seconds / 60);
            $key = 'minutes';
        }

        return [$val, $key];
    }

    /**
     * Build URL query string from given params.
     * @param string[]|null $params
     * @return string
     */
    public function buildQuery(?array $params): string
    {
        return $params ? http_build_query($params) : '';
    }

    /**
     * Shorthand to get the current request from the request stack.
     * @return Request
     * There is no request stack in the unit tests.
     * @codeCoverageIgnore
     */
    private function getRequest(): Request
    {
        return $this->requestStack->getCurrentRequest();
    }
}
