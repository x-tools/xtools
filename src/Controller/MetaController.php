<?php

declare( strict_types=1 );

namespace App\Controller;

use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * This controller serves everything for the Meta tool.
 */
class MetaController extends XtoolsController {
	/**
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function getIndexRoute(): string {
		return 'Meta';
	}

	/**
	 * Display the form.
	 * @Route("/meta", name="meta")
	 * @Route("/meta", name="Meta")
	 * @Route("/meta/index.php", name="MetaIndexPhp")
	 * @return Response
	 */
	public function indexAction(): Response {
		if ( isset( $this->params['start'] ) && isset( $this->params['end'] ) ) {
			return $this->redirectToRoute( 'MetaResult', $this->params );
		}

		return $this->render( 'meta/index.html.twig', [
			'xtPage' => 'Meta',
			'xtPageTitle' => 'tool-meta',
			'xtSubtitle' => 'tool-meta-desc',
		] );
	}

	/**
	 * Display the results.
	 * @Route(
	 *     "/meta/{start}/{end}/{legacy}",
	 *     name="MetaResult",
	 *     requirements={
	 *         "start"="\d{4}-\d{2}-\d{2}",
	 *         "end"="\d{4}-\d{2}-\d{2}",
	 *     },
	 * )
	 * @param ManagerRegistry $managerRegistry
	 * @param bool $legacy Non-blank value indicates to show stats for legacy XTools
	 * @return Response
	 * @codeCoverageIgnore
	 */
	public function resultAction( ManagerRegistry $managerRegistry, bool $legacy = false ): Response {
		$db = $legacy ? 'toolsdb' : 'default';
		$table = $legacy ? 's51187__metadata.xtools_timeline' : 'usage_timeline';
		$client = $managerRegistry->getConnection( $db );

		$toolUsage = $this->getToolUsageStats( $client, $table );
		$apiUsage = $this->getApiUsageStats( $client );

		return $this->render( 'meta/result.html.twig', [
			'xtPage' => 'Meta',
			'start' => $this->start,
			'end' => $this->end,
			'toolUsage' => $toolUsage,
			'apiUsage' => $apiUsage,
		] );
	}

	/**
	 * Get usage statistics of the core tools.
	 * @param object $client
	 * @param string $table Table to query.
	 * @return array
	 * @codeCoverageIgnore
	 */
	private function getToolUsageStats( object $client, string $table ): array {
		$start = date( 'Y-m-d', $this->start );
		$end = date( 'Y-m-d', $this->end );
		$data = $client->executeQuery( "SELECT * FROM $table WHERE date >= :start AND date <= :end", [
			'start' => $start,
			'end' => $end,
		] )->fetchAllAssociative();

		// Create array of totals, along with formatted timeline data as needed by Chart.js
		$totals = [];
		$dateLabels = [];
		$timeline = [];
		$startObj = new DateTime( $start );
		$endObj = new DateTime( $end );
		$numDays = (int)$endObj->diff( $startObj )->format( "%a" );
		$grandSum = 0;

		// Generate array of date labels
		for ( $dateObj = new DateTime( $start ); $dateObj <= $endObj; $dateObj->modify( '+1 day' ) ) {
			$dateLabels[] = $dateObj->format( 'Y-m-d' );
		}

		foreach ( $data as $entry ) {
			if ( !isset( $totals[$entry['tool']] ) ) {
				$totals[$entry['tool']] = (int)$entry['count'];

				// Create arrays for each tool, filled with zeros for each date in the timeline
				$timeline[$entry['tool']] = array_fill( 0, $numDays, 0 );
			} else {
				$totals[$entry['tool']] += (int)$entry['count'];
			}

			$date = new DateTime( $entry['date'] );
			$dateIndex = (int)$date->diff( $startObj )->format( "%a" );
			$timeline[$entry['tool']][$dateIndex] = (int)$entry['count'];

			$grandSum += $entry['count'];
		}
		arsort( $totals );

		return [
			'totals' => $totals,
			'grandSum' => $grandSum,
			'dateLabels' => $dateLabels,
			'timeline' => $timeline,
		];
	}

	/**
	 * Get usage statistics of the API.
	 * @param object $client
	 * @return array
	 * @codeCoverageIgnore
	 */
	private function getApiUsageStats( object $client ): array {
		$start = date( 'Y-m-d', $this->start );
		$end = date( 'Y-m-d', $this->end );
		$data = $client->executeQuery( "SELECT * FROM usage_api_timeline WHERE date >= :start AND date <= :end", [
			'start' => $start,
			'end' => $end,
		] )->fetchAllAssociative();

		// Create array of totals, along with formatted timeline data as needed by Chart.js
		$totals = [];
		$dateLabels = [];
		$timeline = [];
		$startObj = new DateTime( $start );
		$endObj = new DateTime( $end );
		$numDays = (int)$endObj->diff( $startObj )->format( "%a" );
		$grandSum = 0;

		// Generate array of date labels
		for ( $dateObj = new DateTime( $start ); $dateObj <= $endObj; $dateObj->modify( '+1 day' ) ) {
			$dateLabels[] = $dateObj->format( 'Y-m-d' );
		}

		foreach ( $data as $entry ) {
			if ( !isset( $totals[$entry['endpoint']] ) ) {
				$totals[$entry['endpoint']] = (int)$entry['count'];

				// Create arrays for each endpoint, filled with zeros for each date in the timeline
				$timeline[$entry['endpoint']] = array_fill( 0, $numDays, 0 );
			} else {
				$totals[$entry['endpoint']] += (int)$entry['count'];
			}

			$date = new DateTime( $entry['date'] );
			$dateIndex = (int)$date->diff( $startObj )->format( "%a" );
			$timeline[$entry['endpoint']][$dateIndex] = (int)$entry['count'];

			$grandSum += $entry['count'];
		}
		arsort( $totals );

		return [
			'totals' => $totals,
			'grandSum' => $grandSum,
			'dateLabels' => $dateLabels,
			'timeline' => $timeline,
		];
	}

	/**
	 * Record usage of a particular XTools tool. This is called automatically
	 *   in base.html.twig via JavaScript so that it is done asynchronously.
	 * @Route("/meta/usage/{tool}/{project}/{token}")
	 * @param Request $request
	 * @param ParameterBagInterface $parameterBag
	 * @param ManagerRegistry $managerRegistry
	 * @param bool $singleWiki
	 * @param string $tool Internal name of tool.
	 * @param string $project Project domain such as en.wikipedia.org
	 * @param string $token Unique token for this request, so we don't have people meddling with these statistics.
	 * @return JsonResponse
	 * @codeCoverageIgnore
	 */
	public function recordUsageAction(
		Request $request,
		ParameterBagInterface $parameterBag,
		ManagerRegistry $managerRegistry,
		bool $singleWiki,
		string $tool,
		string $project,
		string $token
	): Response {
		$response = new JsonResponse();

		// Validate method and token.
		if ( 'PUT' !== $request->getMethod() || !$this->isCsrfTokenValid( 'intention', $token ) ) {
			$response->setStatusCode( Response::HTTP_FORBIDDEN );
			$response->setContent( json_encode( [
				'error' => 'This endpoint is for internal use only.',
			] ) );
			return $response;
		}

		// Don't update counts for tools that aren't enabled
		$configKey = 'enable.' . ucfirst( $tool );
		if ( !$parameterBag->has( $configKey ) || !$parameterBag->get( $configKey ) ) {
			$response->setStatusCode( Response::HTTP_FORBIDDEN );
			$response->setContent( json_encode( [
				'error' => 'This tool is disabled',
			] ) );
			return $response;
		}

		/** @var Connection $conn */
		$conn = $managerRegistry->getConnection( 'default' );
		$date = date( 'Y-m-d' );

		// Tool name needs to be lowercase.
		$tool = strtolower( $tool );

		$sql = "INSERT INTO usage_timeline
                VALUES(NULL, :date, :tool, 1)
                ON DUPLICATE KEY UPDATE `count` = `count` + 1";
		$conn->executeStatement( $sql, [
			'date' => $date,
			'tool' => $tool,
		] );

		// Update per-project usage, if applicable
		if ( !$singleWiki ) {
			$sql = "INSERT INTO usage_projects
                    VALUES(NULL, :tool, :project, 1)
                    ON DUPLICATE KEY UPDATE `count` = `count` + 1";
			$conn->executeStatement( $sql, [
				'tool' => $tool,
				'project' => $project,
			] );
		}

		$response->setStatusCode( Response::HTTP_NO_CONTENT );
		$response->setContent( json_encode( [] ) );
		return $response;
	}
}
