<?php
/**
 * This file contains only the MetaController class.
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
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

        $query = $client->prepare("SELECT * FROM $table
                                 WHERE date >= :start AND date <= :end");
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
        for ($dateObj = new DateTime($start); $dateObj <= $endObj; $dateObj->modify('+1 day')) {
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

        return $this->render('meta/result.html.twig', [
            'xtPage' => 'meta',
            'start' => $start,
            'end' => $end,
            'data' => $data,
            'totals' => $totals,
            'grandSum' => $grandSum,
            'dateLabels' => $dateLabels,
            'timeline' => $timeline,
        ]);
    }
}
