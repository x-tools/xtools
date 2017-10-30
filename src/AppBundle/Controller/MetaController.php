<?php
/**
 * This file contains only the MetaController class.
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use DateTime;
use Symfony\Component\HttpFoundation\Response;

/**
 * This controller serves everything for the Meta tool.
 */
class MetaController extends Controller
{
    /**
     * Display the form.
     * @Route("/meta", name="meta")
     * @Route("/meta", name="Meta")
     * @Route("/meta/", name="MetaSlash")
     * @Route("/meta/index.php", name="MetaIndexPhp")
     * @param Request $request
     * @return Response
     */
    public function indexAction(Request $request)
    {
        $start = $request->query->get('start');
        $end = $request->query->get('end');

        if ($start != '' && $end != '') {
            return $this->redirectToRoute('MetaResult', [ 'start' => $start, 'end' => $end ]);
        }

        return $this->render('meta/index.html.twig', [
            'xtPage' => 'meta',
            'xtPageTitle' => 'tool-meta',
            'xtSubtitle' => 'tool-meta-desc',
        ]);
    }

    /**
     * Display the results.
     * @Route("/meta/{start}/{end}/{legacy}", name="MetaResult")
     * @param string $start    Start date
     * @param string $end      End date
     * @param string [$legacy] Non-blank value indicates to show stats for legacy XTools
     * @return Response
     */
    public function resultAction($start, $end, $legacy = false)
    {
        $db = $legacy ? 'toolsdb' : 'default';
        $table = $legacy ? 's51187__metadata.xtools_timeline' : 'usage_timeline';

        $client = $this->container
            ->get('doctrine')
            ->getManager($db)
            ->getConnection();

        $query = $client->prepare(
            "SELECT * FROM $table WHERE date >= :start AND date <= :end"
        );
        $query->bindParam('start', $start);
        $query->bindParam('end', $end);
        $query->execute();

        $data = $query->fetchAll();

        // Create array of totals, along with formatted timeline data as needed by Chart.js
        $totals = [];
        $dateLabels = [];
        $timeline = [];
        $startObj = new DateTime($start);
        $endObj = new DateTime($end);
        $numDays = (int) $endObj->diff($startObj)->format("%a");
        $grandSum = 0;

        // Generate array of date labels
        for (
            $dateObj = new DateTime($start);
            $dateObj <= $endObj;
            $dateObj->modify('+1 day')
        ) {
            $dateLabels[] = $dateObj->format('Y-m-d');
        }

        foreach ($data as $entry) {
            if (!isset($totals[$entry['tool']])) {
                $totals[$entry['tool']] = (int) $entry['count'];

                // Create arrays for each tool, filled with zeros for each date in the timeline
                $timeline[$entry['tool']] = array_fill(0, $numDays, 0);
            } else {
                $totals[$entry['tool']] += (int) $entry['count'];
            }

            $date = new DateTime($entry['date']);
            $dateIndex = (int) $date->diff($startObj)->format("%a");
            $timeline[$entry['tool']][$dateIndex] = (int) $entry['count'];

            $grandSum += $entry['count'];
        }
        arsort($totals);

        return $this->render(
            'meta/result.html.twig',
            [
            'xtPage' => 'meta',
            'start' => $start,
            'end' => $end,
            'data' => $data,
            'totals' => $totals,
            'grandSum' => $grandSum,
            'dateLabels' => $dateLabels,
            'timeline' => $timeline,
            ]
        );
    }

    /**
     * Display the tagging interface.
     *
     * @Route("/meta/tag", name="MetaTag")
     *
     * @return Response
     */
    public function tagIndexAction()
    {
        if ($this->container->getParameter("kernel.environment") !== "dev") {
            // We don't want any more than this, this must be hidden entirely on
            // prod environments.
            throw new FileNotFoundException();
        }

        $path = realpath($this->getParameter('kernel.root_dir').'/..');

        $currentVersion = $this->container->getParameter("app.version");

        $request = Request::createFromGlobals();

        $newVersion = $request->get("version");
        $newReleaseNotes = $request->get("releaseNotes");
        $newGit = $request->get("git");

        dump($request);

        if ($newVersion !== null) {

            $fRL = "$path/RELEASE_NOTES.md";
            $fAV = "$path/app/config/version.yml";
            $fRM = "$path/README.md";

            // Release Notes First

            $releaseNotes = file_get_contents($fRL);

            $releaseNotes = str_replace("# Release Notes #", "", $releaseNotes);

            $releaseNotes = $newReleaseNotes . "\r\n\r\n" . $releaseNotes;

            $releaseNotes = "## $newVersion ##" . "\r\n" . $releaseNotes;

            $releaseNotes = "# Release Notes #\r\n\r\n$releaseNotes";

            file_put_contents($fRL, $releaseNotes);

            unset($releaseNotes);

            // Version parameter next

            $version = file_get_contents($fAV);

            $version = str_replace($currentVersion, $newVersion, $version);

            file_put_contents($fAV, $version);

            unset($version);

            // Readme

            $readme = file_get_contents($fRM);

            $readme = str_replace($currentVersion, $newVersion, $readme);

            file_put_contents($fRM, $readme);

            $this->addFlash("success", "Files have been saved");

            // Lastly, git operations

            if ($newGit == "on") {
                chdir($path);

                system("git add $fRL");
                system("git add $fAV");
                system("git add $fRM");

                system(
                    "git commit -m \"Tagging version $newVersion (automated)\""
                );

                system("git tag -a $newVersion -m \"version $newVersion\"");

                $this->addFlash(
                    "success",
                    "Git operations succeeded.  Please \"git push\" to finish."
                );
            }

            return ($this->redirectToRoute("MetaTag"));
        }

        return $this->render(
            "meta/tagIndex.html.twig",
            [
                'xtPage' => 'Tagging Interface',
                'currentVersion' => $currentVersion
            ]
        );
    }

}