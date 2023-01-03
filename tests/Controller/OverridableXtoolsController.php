<?php

declare(strict_types = 1);

namespace App\Tests\Controller;

use App\Controller\XtoolsController;
use App\Helper\I18nHelper;
use App\Repository\PageRepository;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use GuzzleHttp\Client;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * This class can be used in unit tests where you need to override methods
 * of XtoolsController, such as 'tooHighEditCountRoute'. To do this, pass
 * in an associative array as the last argument to the constructor, with the
 * names of the methods as the keys, and the values what they should return.
 */
class OverridableXtoolsController extends XtoolsController
{
    protected array $overrides = [];

    /**
     * @param RequestStack $requestStack
     * @param ContainerInterface $container
     * @param CacheItemPoolInterface $cache
     * @param Client $guzzle
     * @param I18nHelper $i18n
     * @param ProjectRepository $projectRepo
     * @param UserRepository $userRepo
     * @param PageRepository $pageRepo
     * @param string[] $overrides Keys are method names, values are what they should return.
     */
    public function __construct(
        RequestStack $requestStack,
        ContainerInterface $container,
        CacheItemPoolInterface $cache,
        Client $guzzle,
        I18nHelper $i18n,
        ProjectRepository $projectRepo,
        UserRepository $userRepo,
        PageRepository $pageRepo,
        array $overrides = []
    ) {
        parent::__construct($requestStack, $container, $cache, $guzzle, $i18n, $projectRepo, $userRepo, $pageRepo);
        $this->overrides = $overrides;
    }

    /**
     * @inheritDoc
     */
    public function getIndexRoute(): string
    {
        return $this->overrides['getIndexRoute'] ?? 'homepage';
    }

    /**
     * @inheritDoc
     */
    public function tooHighEditCountRoute(): ?string
    {
        return $this->overrides['tooHighEditCountRoute'] ?? parent::tooHighEditCountRoute();
    }

    /**
     * @inheritDoc
     */
    public function tooHighEditCountActionAllowlist(): array
    {
        return $this->overrides['tooHighEditCountActionAllowlist'] ?? parent::tooHighEditCountActionAllowlist();
    }

    /**
     * @inheritDoc
     */
    public function supportedProjects(): array
    {
        return $this->overrides['supportedProjects'] ?? parent::supportedProjects();
    }

    /**
     * @inheritDoc
     */
    public function restrictedApiActions(): array
    {
        return $this->overrides['restrictedApiActions'] ?? parent::restrictedApiActions();
    }

    /**
     * @inheritDoc
     */
    public function maxDays(): ?int
    {
        return $this->overrides['maxDays'] ?? parent::maxDays();
    }

    /**
     * @inheritDoc
     */
    public function defaultDays(): ?int
    {
        return $this->overrides['defaultDays'] ?? parent::defaultDays();
    }

    /**
     * @inheritDoc
     */
    protected function maxLimit(): int
    {
        return $this->overrides['maxLimit'] ?? parent::maxLimit();
    }
}
