<?php
/**
 * This file contains the code that powers the RfX Vote Calculator page of XTools.
 */

namespace AppBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Xtools\PageRepository;
use Xtools\RFX;

/**
 * Controller for the RfX Vote Calculator.
 */
class RfXVoteCalculatorController extends XtoolsController
{
    /**
     * Get the name of the tool's index route.
     * @return string
     * @codeCoverageIgnore
     */
    public function getIndexRoute()
    {
        return 'RfXVoteCalculator';
    }

    /**
     * Renders the index page for RfXVoteCalculator
     *
     * @Route("/rfxvote", name="RfXVoteCalculator")
     * @Route("/rfxvote/", name="RfXVoteCalculatorSlash")
     * @Route("/rfxvote/index.php", name="RfXVoteCalculatorIndexPhp")
     * @return Response
     */
    public function indexAction()
    {
        // Redirect if at minimum project, username and categories are provided.
        if (isset($this->params['project']) && isset($this->params['username'])) {
            return $this->redirectToRoute('RfXVoteResult', $this->params);
        }

        return $this->render(
            'rfxVoteCalculator/index.html.twig',
            [
                'xtPageTitle' => 'tool-rfxvote',
                'xtSubtitle' => 'tool-rfxvote-desc',
                'xtPage' => 'rfxvote',
                'project' => $this->project,
            ]
        );
    }

    /**
     * Result View of RfXVoteCalculator
     * @Route("/rfxvote/{project}/{username}", name="RfXVoteResult")
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultAction()
    {
        $conn = $this->getDoctrine()->getManager('replicas')->getConnection();

        $projectRepo = $this->project->getRepository();
        $pageRepo = new PageRepository();
        $pageRepo->setContainer($this->container);

        $dbName = $this->project->getDatabaseName();

        $rfxParam = $this->getParameter('rfx');

        $namespaces = $this->project->getNamespaces();

        if (!isset($rfxParam[$this->project->getDomain()])) {
            $this->addFlash('notice', ['invalid-project-cant-use', $this->project->getDomain()]);
            return $this->redirectToRoute('RfXVoteCalculator');
        }

        $pageTypes = $rfxParam[$this->project->getDomain()]['pages'];
        $namespace = $rfxParam[$this->project->getDomain()]['rfx_namespace'] !== null
            ? $rfxParam[$this->project->getDomain()]['rfx_namespace']
            : 4;

        $finalData = [];

        // We should probably figure out a better way to do this...
        $ignoredPages = '';

        if (isset($rfxParam[$this->project->getDomain()]['excluded_title'])) {
            $titlesExcluded
                = $rfxParam[$this->project->getDomain()]['excluded_title'];
            foreach ($titlesExcluded as $ignoredPage) {
                $ignoredPages .= "AND p.page_title != \"$ignoredPage\"\r\n";
            }
        }

        if (isset($rfxParam[$this->project->getDomain()]['excluded_regex'])) {
            $titlesExcluded
                = $rfxParam[$this->project->getDomain()]['excluded_regex'];
            foreach ($titlesExcluded as $ignoredPage) {
                $ignoredPages .= "AND p.page_title NOT LIKE \"%$ignoredPage%\"\r\n";
            }
        }

        /**
         * Contains the total number of !votes the user made, keyed by the RfX
         * type and then the vote type.
         * @var array
         */
        $totals = [];

        foreach ($pageTypes as $type) {
            $type = explode(':', $type, 2)[1];

            $type = str_replace(' ', '_', $type);

            $pageTable = $projectRepo->getTableName($dbName, 'page');
            $revisionTable = $projectRepo->getTableName($dbName, 'revision');
            $username = $this->user->getUsername();

            $sql = "SELECT DISTINCT p.page_namespace, p.page_title
                    FROM $pageTable p
                    RIGHT JOIN $revisionTable r on p.page_id=r.rev_page
                    WHERE p.page_namespace = :namespace
                    AND r.rev_user_text = :username
                    And p.page_title LIKE \"$type/%\"
                    AND p.page_title NOT LIKE \"%$type/$username%\"
                    $ignoredPages";

            $sth = $conn->prepare($sql);
            $sth->bindParam('namespace', $namespace);
            $sth->bindParam('username', $username);

            $sth->execute();

            $titles = [];

            while ($row = $sth->fetch()) {
                $titles[] = $namespaces[$row['page_namespace']] .
                    ':' .$row['page_title'];
            }

            // Chunking... it's possible to make a URI too long
            $titleArray = array_chunk($titles, 20);

            foreach ($titleArray as $titlesWorked) {
                $pageData = $pageRepo->getPagesWikitext($this->project, $titlesWorked);

                foreach ($pageData as $title => $text) {
                    $type = str_replace('_', ' ', $type);
                    $rfx = new RFX(
                        $text,
                        $rfxParam[$this->project->getDomain()]['sections'],
                        $namespaces[2],
                        $rfxParam[$this->project->getDomain()]['date_regexp'],
                        $username
                    );
                    $section = $rfx->getUserSectionFound();

                    if ($section == '') {
                        // Skip over ones where the user didn't !vote.
                        continue;
                    }

                    if (!isset($totals[$type])) {
                        $totals[$type] = [];
                    }
                    if (!isset($totals[$type][$section])) {
                        $totals[$type][$section] = 0;
                    }
                    if (!isset($totals[$type]['total'])) {
                        $totals[$type]['total'] = 0;
                    }
                    $totals[$type][$section] += 1;
                    $totals[$type]['total'] += 1;

                    // Todo: i18n-ize this
                    $finalData[$type][$section][$title]['Support']
                        = sizeof($rfx->getSection('support'));
                    $finalData[$type][$section][$title]['Oppose']
                        = sizeof($rfx->getSection('oppose'));
                    $finalData[$type][$section][$title]['Neutral']
                        = sizeof($rfx->getSection('neutral'));
                    $finalData[$type][$section][$title]['Date']
                        = $rfx->getEndDate();
                    $finalData[$type][$section][$title]['name']
                        = explode('/', $title)[1];

                    unset($rfx);
                }
            }
        }

        return $this->render(
            'rfxVoteCalculator/result.html.twig',
            [
                'xtPage' => 'rfxvote',
                'xtTitle' => $this->user->getUsername(),
                'user' => $this->user,
                'project' => $this->project,
                'data'=> $finalData,
                'totals' => $totals,
            ]
        );
    }
}
