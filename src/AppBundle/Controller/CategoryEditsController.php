<?php
/**
 * This file contains only the CategoryEditsController class.
 */

declare(strict_types=1);

namespace AppBundle\Controller;

use AppBundle\Exception\XtoolsHttpException;
use AppBundle\Helper\I18nHelper;
use AppBundle\Model\CategoryEdits;
use AppBundle\Repository\CategoryEditsRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * This controller serves the Category Edits tool.
 */
class CategoryEditsController extends XtoolsController
{
    /** @var CategoryEdits The CategoryEdits instance. */
    protected $categoryEdits;

    /** @var string[] The categories, with or without namespace. */
    protected $categories;

    /** @var array Data that is passed to the view. */
    private $output;

    /**
     * Get the name of the tool's index route.
     * This is also the name of the associated model.
     * @return string
     * @codeCoverageIgnore
     */
    public function getIndexRoute(): string
    {
        return 'CategoryEdits';
    }

    /**
     * CategoryEditsController constructor.
     * @param RequestStack $requestStack
     * @param ContainerInterface $container
     * @param I18nHelper $i18n
     */
    public function __construct(RequestStack $requestStack, ContainerInterface $container, I18nHelper $i18n)
    {
        // Will redirect back to index if the user has too high of an edit count.
        $this->tooHighEditCountAction = $this->getIndexRoute();

        parent::__construct($requestStack, $container, $i18n);
    }

    /**
     * Display the search form.
     * @Route("/categoryedits", name="CategoryEdits")
     * @Route("/categoryedits/{project}", name="CategoryEditsProject")
     * @return Response
     * @codeCoverageIgnore
     */
    public function indexAction(): Response
    {
        // Redirect if at minimum project, username and categories are provided.
        if (isset($this->params['project']) && isset($this->params['username']) && isset($this->params['categories'])) {
            return $this->redirectToRoute('CategoryEditsResult', $this->params);
        }

        return $this->render('categoryEdits/index.html.twig', array_merge([
            'xtPageTitle' => 'tool-categoryedits',
            'xtSubtitle' => 'tool-categoryedits-desc',
            'xtPage' => 'CategoryEdits',

            // Defaults that will get overridden if in $params.
            'namespace' => 0,
            'start' => '',
            'end' => '',
            'username' => '',
            'categories' => '',
        ], $this->params, ['project' => $this->project]));
    }

    /**
     * Set defaults, and instantiate the CategoryEdits model. This is called at the top of every view action.
     * @codeCoverageIgnore
     */
    private function setupCategoryEdits(): void
    {
        $this->extractCategories();

        $this->categoryEdits = new CategoryEdits(
            $this->project,
            $this->user,
            $this->categories,
            $this->start,
            $this->end,
            $this->offset
        );
        $categoryEditsRepo = new CategoryEditsRepository();
        $categoryEditsRepo->setContainer($this->container);
        $this->categoryEdits->setRepository($categoryEditsRepo);

        $this->output = [
            'xtPage' => 'CategoryEdits',
            'xtTitle' => $this->user->getUsername(),
            'project' => $this->project,
            'user' => $this->user,
            'ce' => $this->categoryEdits,
            'is_sub_request' => $this->isSubRequest,
        ];
    }

    /**
     * Go through the categories and normalize values, and set them on class properties.
     * @codeCoverageIgnore
     */
    private function extractCategories(): void
    {
        // Split categories by pipe.
        $categories = explode('|', $this->request->get('categories'));

        // Loop through the given categories, stripping out the namespace.
        // If a namespace was removed, it is flagged it as normalize
        // We look for the wiki's category namespace name, and the MediaWiki default
        // 'Category:', which sometimes is used cross-wiki (because it still works).
        $normalized = false;
        $nsName = $this->project->getNamespaces()[14].':';
        $this->categories = array_map(function ($category) use ($nsName, &$normalized) {
            if (0 === strpos($category, $nsName) || 0 === strpos($category, 'Category:')) {
                $normalized = true;
            }
            return preg_replace('/^'.$nsName.'/', '', $category);
        }, $categories);

        // Redirect if normalized, since we don't want the Category: prefix in the URL.
        if ($normalized) {
            throw new XtoolsHttpException(
                '',
                $this->generateUrl($this->request->get('_route'), array_merge(
                    $this->request->attributes->get('_route_params'),
                    ['categories' => implode('|', $this->categories)]
                ))
            );
        }
    }

    /**
     * Display the results.
     * @Route(
     *     "/categoryedits/{project}/{username}/{categories}/{start}/{end}",
     *     name="CategoryEditsResult",
     *     requirements={
     *         "categories"="(.+?)(?!\/(?:|\d{4}-\d{2}-\d{2})(?:\/(|\d{4}-\d{2}-\d{2}))?)?$",
     *         "start"="|\d{4}-\d{2}-\d{2}",
     *         "end"="|\d{4}-\d{2}-\d{2}"
     *     },
     *     defaults={"start" = false, "end" = false}
     * )
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultAction(): Response
    {
        $this->setupCategoryEdits();

        return $this->getFormattedResponse('categoryEdits/result', $this->output);
    }

    /**
     * Get edits my by a user to pages in given categories.
     * @Route(
     *   "/categoryedits-contributions/{project}/{username}/{categories}/{start}/{end}/{offset}",
     *   name="CategoryContributionsResult",
     *   requirements={
     *       "categories"="(.+?)(?!\/(?:|\d{4}-\d{2}-\d{2}))?",
     *       "start"="|\d{4}-\d{2}-\d{2}",
     *       "end"="|\d{4}-\d{2}-\d{2}",
     *       "offset"="|\d{4}-?\d{2}-?\d{2}T?\d{2}:?\d{2}:?\d{2}",
     *   },
     *   defaults={"start"=false, "end"=false, "offset"=false}
     * )
     * @return Response
     * @codeCoverageIgnore
     */
    public function categoryContributionsAction(): Response
    {
        $this->setupCategoryEdits();

        return $this->render('categoryEdits/contributions.html.twig', $this->output);
    }

    /************************ API endpoints ************************/

    /**
     * Count the number of category edits the given user has made.
     * @Route(
     *   "/api/user/category_editcount/{project}/{username}/{categories}/{start}/{end}",
     *   name="UserApiCategoryEditCount",
     *   requirements={
     *       "categories" = "(.+?)(?!\/(?:|\d{4}-\d{2}-\d{2})(?:\/(|\d{4}-\d{2}-\d{2}))?)?$",
     *       "start" = "|\d{4}-\d{2}-\d{2}",
     *       "end" = "|\d{4}-\d{2}-\d{2}"
     *   },
     *   defaults={"start" = false, "end" = false}
     * )
     * @return JsonResponse
     * @codeCoverageIgnore
     */
    public function categoryEditCountApiAction(): JsonResponse
    {
        $this->recordApiUsage('user/category_editcount');

        $this->setupCategoryEdits();

        $ret = [
            'total_editcount' => $this->categoryEdits->getEditCount(),
            'category_editcount' => $this->categoryEdits->getCategoryEditCount(),
        ];

        return $this->getFormattedApiResponse($ret);
    }
}
