0f0dcf06f src/Xtools/ArticleInfo.php          (MusikAnimal 2017-09-25 13:23:14 -0400    1) <?php
0f0dcf06f src/Xtools/ArticleInfo.php          (MusikAnimal 2017-09-25 13:23:14 -0400    2) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500    3) declare( strict_types = 1 );
5f2715533 src/AppBundle/Model/ArticleInfo.php (MusikAnimal 2018-10-12 02:48:36 -0400    4) 
33c9b9c47 src/Model/ArticleInfo.php           (MusikAnimal 2022-07-18 22:58:21 -0400    5) namespace App\Model;
0f0dcf06f src/Xtools/ArticleInfo.php          (MusikAnimal 2017-09-25 13:23:14 -0400    6) 
8964b9d38 src/Xtools/ArticleInfo.php          (MusikAnimal 2018-03-12 12:57:55 -0400    7) use DateTime;
0f0dcf06f src/Xtools/ArticleInfo.php          (MusikAnimal 2017-09-25 13:23:14 -0400    8) 
0f0dcf06f src/Xtools/ArticleInfo.php          (MusikAnimal 2017-09-25 13:23:14 -0400    9) /**
673ae4f02 src/Model/PageInfo.php              (MusikAnimal 2024-05-27 20:23:04 -0400   10)  * A PageInfo provides statistics about a page on a project.
0f0dcf06f src/Xtools/ArticleInfo.php          (MusikAnimal 2017-09-25 13:23:14 -0400   11)  */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   12) class PageInfo extends PageInfoApi {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   13) 	/** @var int Number of revisions that were actually processed. */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   14) 	protected int $numRevisionsProcessed;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   15) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   16) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   17) 	 * Various statistics about editors to the page. These are not User objects
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   18) 	 * so as to preserve memory.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   19) 	 * @var array
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   20) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   21) 	protected array $editors = [];
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   22) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   23) 	/** @var array The top 10 editors to the page by number of edits. */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   24) 	protected array $topTenEditorsByEdits;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   25) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   26) 	/** @var array The top 10 editors to the page by added text. */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   27) 	protected array $topTenEditorsByAdded;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   28) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   29) 	/** @var int Number of edits made by the top 10 editors. */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   30) 	protected int $topTenCount;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   31) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   32) 	/** @var array Various counts about each individual year and month of the page's history. */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   33) 	protected array $yearMonthCounts;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   34) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   35) 	/** @var string[] Localized labels for the years, to be used in the 'Year counts' chart. */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   36) 	protected array $yearLabels = [];
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   37) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   38) 	/** @var string[] Localized labels for the months, to be used in the 'Month counts' chart. */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   39) 	protected array $monthLabels = [];
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   40) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   41) 	/** @var Edit|null The first edit to the page. */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   42) 	protected ?Edit $firstEdit = null;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   43) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   44) 	/** @var Edit|null The last edit to the page. */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   45) 	protected ?Edit $lastEdit = null;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   46) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   47) 	/** @var Edit|null Edit that made the largest addition by number of bytes. */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   48) 	protected ?Edit $maxAddition = null;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   49) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   50) 	/** @var Edit|null Edit that made the largest deletion by number of bytes. */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   51) 	protected ?Edit $maxDeletion = null;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   52) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   53) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   54) 	 * Maximum number of edits that were created across all months. This is used as a comparison
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   55) 	 * for the bar charts in the months section.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   56) 	 * @var int
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   57) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   58) 	protected int $maxEditsPerMonth = 0;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   59) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   60) 	/** @var string[][] List of (semi-)automated tools that were used to edit the page. */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   61) 	protected array $tools = [];
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   62) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   63) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   64) 	 * Total number of bytes added throughout the page's history. This is used as a comparison
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   65) 	 * when computing the top 10 editors by added text.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   66) 	 * @var int
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   67) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   68) 	protected int $addedBytes = 0;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   69) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   70) 	/** @var int Number of days between first and last edit. */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   71) 	protected int $totalDays;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   72) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   73) 	/** @var int Number of minor edits to the page. */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   74) 	protected int $minorCount = 0;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   75) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   76) 	/** @var int Number of anonymous edits to the page. */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   77) 	protected int $anonCount = 0;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   78) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   79) 	/** @var int Number of automated edits to the page. */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   80) 	protected int $automatedCount = 0;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   81) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   82) 	/** @var int Number of edits to the page that were reverted with the subsequent edit. */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   83) 	protected int $revertCount = 0;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   84) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   85) 	/** @var int Number of edits to the page that were tagged as mobile edits. */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   86) 	protected int $mobileCount = 0;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   87) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   88) 	/** @var int Number of edits to the page that were tagged as visual edits. */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   89) 	protected int $visualCount = 0;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   90) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   91) 	/** @var int[] The "edits per <time>" counts. */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   92) 	protected array $countHistory = [
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   93) 		'day' => 0,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   94) 		'week' => 0,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   95) 		'month' => 0,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   96) 		'year' => 0,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   97) 	];
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   98) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500   99) 	/** @var int Number of revisions with deleted information that could effect accuracy of the stats. */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  100) 	protected int $numDeletedRevisions = 0;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  101) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  102) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  103) 	 * Get the day of last date we should show in the month/year sections,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  104) 	 * based on $this->end or the current date.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  105) 	 * @return int As Unix timestamp.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  106) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  107) 	private function getLastDay(): int {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  108) 		if ( is_int( $this->end ) ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  109) 			return ( new DateTime( "@$this->end" ) )
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  110) 				->modify( 'last day of this month' )
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  111) 				->getTimestamp();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  112) 		} else {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  113) 			return strtotime( 'last day of this month' );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  114) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  115) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  116) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  117) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  118) 	 * Return the start/end date values as associative array, with YYYY-MM-DD as the date format.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  119) 	 * This is used mainly as a helper to pass to the pageviews Twig macros.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  120) 	 * @return array
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  121) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  122) 	public function getDateParams(): array {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  123) 		if ( !$this->hasDateRange() ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  124) 			return [];
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  125) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  126) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  127) 		$ret = [
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  128) 			'start' => $this->firstEdit->getTimestamp()->format( 'Y-m-d' ),
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  129) 			'end' => $this->lastEdit->getTimestamp()->format( 'Y-m-d' ),
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  130) 		];
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  131) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  132) 		if ( is_int( $this->start ) ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  133) 			$ret['start'] = date( 'Y-m-d', $this->start );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  134) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  135) 		if ( is_int( $this->end ) ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  136) 			$ret['end'] = date( 'Y-m-d', $this->end );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  137) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  138) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  139) 		return $ret;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  140) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  141) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  142) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  143) 	 * Get the number of revisions that are actually getting processed. This goes by the APP_MAX_PAGE_REVISIONS
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  144) 	 * env variable, or the actual number of revisions, whichever is smaller.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  145) 	 * @return int
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  146) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  147) 	public function getNumRevisionsProcessed(): int {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  148) 		if ( isset( $this->numRevisionsProcessed ) ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  149) 			return $this->numRevisionsProcessed;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  150) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  151) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  152) 		if ( $this->tooManyRevisions() ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  153) 			$this->numRevisionsProcessed = $this->repository->getMaxPageRevisions();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  154) 		} else {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  155) 			$this->numRevisionsProcessed = $this->getNumRevisions();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  156) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  157) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  158) 		return $this->numRevisionsProcessed;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  159) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  160) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  161) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  162) 	 * Fetch and store all the data we need to show the PageInfo view.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  163) 	 * @codeCoverageIgnore
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  164) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  165) 	public function prepareData(): void {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  166) 		$this->parseHistory();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  167) 		$this->setLogsEvents();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  168) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  169) 		// Bots need to be set before setting top 10 counts.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  170) 		$this->bots = $this->getBots();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  171) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  172) 		$this->doPostPrecessing();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  173) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  174) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  175) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  176) 	 * Get the number of editors that edited the page.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  177) 	 * @return int
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  178) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  179) 	public function getNumEditors(): int {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  180) 		return count( $this->editors );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  181) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  182) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  183) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  184) 	 * Get the number of days between the first and last edit.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  185) 	 * @return int
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  186) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  187) 	public function getTotalDays(): int {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  188) 		if ( isset( $this->totalDays ) ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  189) 			return $this->totalDays;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  190) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  191) 		$dateFirst = $this->firstEdit->getTimestamp();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  192) 		$dateLast = $this->lastEdit->getTimestamp();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  193) 		$interval = date_diff( $dateLast, $dateFirst, true );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  194) 		$this->totalDays = (int)$interval->format( '%a' );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  195) 		return $this->totalDays;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  196) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  197) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  198) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  199) 	 * Returns length of the page.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  200) 	 * @return int|null
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  201) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  202) 	public function getLength(): ?int {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  203) 		if ( $this->hasDateRange() ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  204) 			return $this->lastEdit->getLength();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  205) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  206) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  207) 		return $this->page->getLength();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  208) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  209) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  210) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  211) 	 * Get the average number of days between edits to the page.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  212) 	 * @return float
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  213) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  214) 	public function averageDaysPerEdit(): float {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  215) 		return round( $this->getTotalDays() / $this->getNumRevisionsProcessed(), 1 );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  216) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  217) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  218) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  219) 	 * Get the average number of edits per day to the page.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  220) 	 * @return float
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  221) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  222) 	public function editsPerDay(): float {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  223) 		$editsPerDay = $this->getTotalDays()
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  224) 			? $this->getNumRevisionsProcessed() / ( $this->getTotalDays() / ( 365 / 12 / 24 ) )
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  225) 			: 0;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  226) 		return round( $editsPerDay, 1 );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  227) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  228) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  229) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  230) 	 * Get the average number of edits per month to the page.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  231) 	 * @return float
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  232) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  233) 	public function editsPerMonth(): float {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  234) 		$editsPerMonth = $this->getTotalDays()
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  235) 			? $this->getNumRevisionsProcessed() / ( $this->getTotalDays() / ( 365 / 12 ) )
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  236) 			: 0;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  237) 		return min( $this->getNumRevisionsProcessed(), round( $editsPerMonth, 1 ) );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  238) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  239) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  240) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  241) 	 * Get the average number of edits per year to the page.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  242) 	 * @return float
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  243) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  244) 	public function editsPerYear(): float {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  245) 		$editsPerYear = $this->getTotalDays()
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  246) 			? $this->getNumRevisionsProcessed() / ( $this->getTotalDays() / 365 )
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  247) 			: 0;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  248) 		return min( $this->getNumRevisionsProcessed(), round( $editsPerYear, 1 ) );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  249) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  250) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  251) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  252) 	 * Get the average number of edits per editor.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  253) 	 * @return float
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  254) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  255) 	public function editsPerEditor(): float {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  256) 		if ( count( $this->editors ) > 0 ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  257) 			return round( $this->getNumRevisionsProcessed() / count( $this->editors ), 1 );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  258) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  259) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  260) 		// To prevent division by zero error; can happen if all usernames are removed (see T303724).
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  261) 		return 0;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  262) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  263) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  264) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  265) 	 * Get the percentage of minor edits to the page.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  266) 	 * @return float
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  267) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  268) 	public function minorPercentage(): float {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  269) 		return round(
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  270) 			( $this->minorCount / $this->getNumRevisionsProcessed() ) * 100,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  271) 			1
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  272) 		);
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  273) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  274) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  275) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  276) 	 * Get the percentage of anonymous edits to the page.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  277) 	 * @return float
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  278) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  279) 	public function anonPercentage(): float {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  280) 		return round(
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  281) 			( $this->anonCount / $this->getNumRevisionsProcessed() ) * 100,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  282) 			1
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  283) 		);
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  284) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  285) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  286) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  287) 	 * Get the percentage of edits made by the top 10 editors.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  288) 	 * @return float
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  289) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  290) 	public function topTenPercentage(): float {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  291) 		return round( ( $this->topTenCount / $this->getNumRevisionsProcessed() ) * 100, 1 );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  292) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  293) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  294) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  295) 	 * Get the number of automated edits made to the page.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  296) 	 * @return int
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  297) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  298) 	public function getAutomatedCount(): int {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  299) 		return $this->automatedCount;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  300) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  301) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  302) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  303) 	 * Get the number of mobile edits.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  304) 	 * @return int
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  305) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  306) 	public function getMobileCount(): int {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  307) 		return $this->mobileCount;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  308) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  309) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  310) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  311) 	 * Get the number of visual edits.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  312) 	 * @return int
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  313) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  314) 	public function getVisualCount(): int {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  315) 		return $this->visualCount;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  316) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  317) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  318) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  319) 	 * Get the number of edits to the page that were reverted with the subsequent edit.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  320) 	 * @return int
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  321) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  322) 	public function getRevertCount(): int {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  323) 		return $this->revertCount;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  324) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  325) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  326) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  327) 	 * Get the number of edits to the page made by logged out users.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  328) 	 * @return int
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  329) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  330) 	public function getAnonCount(): int {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  331) 		return $this->anonCount;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  332) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  333) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  334) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  335) 	 * Get the number of minor edits to the page.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  336) 	 * @return int
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  337) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  338) 	public function getMinorCount(): int {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  339) 		return $this->minorCount;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  340) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  341) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  342) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  343) 	 * Get the number of edits to the page made in the past day, week, month and year.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  344) 	 * @return int[] With keys 'day', 'week', 'month' and 'year'.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  345) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  346) 	public function getCountHistory(): array {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  347) 		return $this->countHistory;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  348) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  349) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  350) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  351) 	 * Get the number of edits to the page made by the top 10 editors.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  352) 	 * @return int
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  353) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  354) 	public function getTopTenCount(): int {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  355) 		return $this->topTenCount;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  356) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  357) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  358) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  359) 	 * Get the first edit to the page.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  360) 	 * @return Edit
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  361) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  362) 	public function getFirstEdit(): Edit {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  363) 		return $this->firstEdit;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  364) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  365) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  366) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  367) 	 * Get the last edit to the page.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  368) 	 * @return Edit
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  369) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  370) 	public function getLastEdit(): Edit {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  371) 		return $this->lastEdit;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  372) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  373) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  374) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  375) 	 * Get the edit that made the largest addition to the page (by number of bytes).
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  376) 	 * @return Edit|null
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  377) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  378) 	public function getMaxAddition(): ?Edit {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  379) 		return $this->maxAddition;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  380) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  381) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  382) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  383) 	 * Get the edit that made the largest removal to the page (by number of bytes).
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  384) 	 * @return Edit|null
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  385) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  386) 	public function getMaxDeletion(): ?Edit {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  387) 		return $this->maxDeletion;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  388) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  389) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  390) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  391) 	 * Get the subpage count.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  392) 	 * @return int
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  393) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  394) 	public function getSubpageCount(): int {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  395) 		return $this->repository->getSubpageCount( $this->page );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  396) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  397) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  398) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  399) 	 * Get the list of editors to the page, including various statistics.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  400) 	 * @return array
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  401) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  402) 	public function getEditors(): array {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  403) 		return $this->editors;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  404) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  405) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  406) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  407) 	 * Get usernames of human editors (not bots).
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  408) 	 * @param int|null $limit
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  409) 	 * @return string[]
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  410) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  411) 	public function getHumans( ?int $limit = null ): array {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  412) 		return array_slice( array_diff(
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  413) 			array_keys( $this->getEditors() ),
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  414) 			array_keys( $this->getBots() )
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  415) 		), 0, $limit );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  416) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  417) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  418) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  419) 	 * Get the list of the top editors to the page (by edits), including various statistics.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  420) 	 * @return array
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  421) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  422) 	public function topTenEditorsByEdits(): array {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  423) 		return $this->topTenEditorsByEdits;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  424) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  425) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  426) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  427) 	 * Get the list of the top editors to the page (by added text), including various statistics.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  428) 	 * @return array
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  429) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  430) 	public function topTenEditorsByAdded(): array {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  431) 		return $this->topTenEditorsByAdded;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  432) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  433) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  434) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  435) 	 * Get various counts about each individual year and month of the page's history.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  436) 	 * @return array
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  437) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  438) 	public function getYearMonthCounts(): array {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  439) 		return $this->yearMonthCounts;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  440) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  441) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  442) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  443) 	 * Get the localized labels for the 'Year counts' chart.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  444) 	 * @return string[]
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  445) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  446) 	public function getYearLabels(): array {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  447) 		return $this->yearLabels;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  448) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  449) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  450) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  451) 	 * Get the localized labels for the 'Month counts' chart.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  452) 	 * @return string[]
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  453) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  454) 	public function getMonthLabels(): array {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  455) 		return $this->monthLabels;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  456) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  457) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  458) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  459) 	 * Get the maximum number of edits that were created across all months. This is used as a
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  460) 	 * comparison for the bar charts in the months section.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  461) 	 * @return int
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  462) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  463) 	public function getMaxEditsPerMonth(): int {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  464) 		return $this->maxEditsPerMonth;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  465) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  466) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  467) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  468) 	 * Get a list of (semi-)automated tools that were used to edit the page, including
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  469) 	 * the number of times they were used, and a link to the tool's homepage.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  470) 	 * @return string[]
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  471) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  472) 	public function getTools(): array {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  473) 		return $this->tools;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  474) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  475) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  476) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  477) 	 * Parse the revision history, collecting our core statistics.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  478) 	 *
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  479) 	 * Untestable because it relies on getting a PDO statement. All the important
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  480) 	 * logic lives in other methods which are tested.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  481) 	 * @codeCoverageIgnore
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  482) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  483) 	private function parseHistory(): void {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  484) 		$limit = $this->tooManyRevisions() ? $this->repository->getMaxPageRevisions() : null;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  485) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  486) 		// numRevisions is ignored if $limit is null.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  487) 		$revs = $this->page->getRevisions(
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  488) 			null,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  489) 			$this->start,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  490) 			$this->end,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  491) 			$limit,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  492) 			$this->getNumRevisions()
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  493) 		);
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  494) 		$revCount = 0;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  495) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  496) 		/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  497) 		 * Data about previous edits so that we can use them as a basis for comparison.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  498) 		 * @var Edit[] $prevEdits
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  499) 		 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  500) 		$prevEdits = [
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  501) 			// The previous Edit, used to discount content that was reverted.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  502) 			'prev' => null,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  503) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  504) 			// The SHA-1 of the edit *before* the previous edit. Used for more
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  505) 			// accurate revert detection.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  506) 			'prevSha' => null,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  507) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  508) 			// The last edit deemed to be the max addition of content. This is kept track of
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  509) 			// in case we find out the next edit was reverted (and was also a max edit),
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  510) 			// in which case we'll want to discount it and use this one instead.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  511) 			'maxAddition' => null,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  512) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  513) 			// Same as with maxAddition, except the maximum amount of content deleted.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  514) 			// This is used to discount content that was reverted.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  515) 			'maxDeletion' => null,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  516) 		];
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  517) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  518) 		foreach ( $revs as $rev ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  519) 			/** @var Edit $edit */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  520) 			$edit = $this->repository->getEdit( $this->page, $rev );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  521) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  522) 			if ( $edit->getDeleted() !== 0 ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  523) 				$this->numDeletedRevisions++;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  524) 			}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  525) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  526) 			if ( in_array( 'mobile edit', $edit->getTags() ) ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  527) 				$this->mobileCount++;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  528) 			}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  529) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  530) 			if ( in_array( 'visualeditor', $edit->getTags() ) ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  531) 				$this->visualCount++;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  532) 			}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  533) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  534) 			if ( $revCount === 0 ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  535) 				$this->firstEdit = $edit;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  536) 			}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  537) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  538) 			// Sometimes, with old revisions (2001 era), the revisions from 2002 come before 2001
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  539) 			if ( $edit->getTimestamp() < $this->firstEdit->getTimestamp() ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  540) 				$this->firstEdit = $edit;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  541) 			}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  542) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  543) 			$prevEdits = $this->updateCounts( $edit, $prevEdits );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  544) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  545) 			$revCount++;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  546) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  547) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  548) 		$this->numRevisionsProcessed = $revCount;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  549) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  550) 		// Various sorts
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  551) 		arsort( $this->editors );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  552) 		ksort( $this->yearMonthCounts );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  553) 		if ( $this->tools ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  554) 			arsort( $this->tools );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  555) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  556) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  557) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  558) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  559) 	 * Update various counts based on the current edit.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  560) 	 * @param Edit $edit
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  561) 	 * @param Edit[] $prevEdits With 'prev', 'prevSha', 'maxAddition' and 'maxDeletion'
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  562) 	 * @return Edit[] Updated version of $prevEdits.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  563) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  564) 	private function updateCounts( Edit $edit, array $prevEdits ): array {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  565) 		// Update the counts for the year and month of the current edit.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  566) 		$this->updateYearMonthCounts( $edit );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  567) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  568) 		// Update counts for the user who made the edit.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  569) 		$this->updateUserCounts( $edit );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  570) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  571) 		// Update the year/month/user counts of anon and minor edits.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  572) 		$this->updateAnonMinorCounts( $edit );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  573) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  574) 		// Update counts for automated tool usage, if applicable.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  575) 		$this->updateToolCounts( $edit );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  576) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  577) 		// Increment "edits per <time>" counts
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  578) 		$this->updateCountHistory( $edit );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  579) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  580) 		// Update figures regarding content addition/removal, and the revert count.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  581) 		$prevEdits = $this->updateContentSizes( $edit, $prevEdits );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  582) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  583) 		// Now that we've updated all the counts, we can reset
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  584) 		// the prev and last edits, which are used for tracking.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  585) 		// But first, let's copy over the SHA of the actual previous edit
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  586) 		// and put it in our $prevEdits['prev'], so that we'll know
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  587) 		// that content added after $prevEdit['prev'] was reverted.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  588) 		if ( $prevEdits['prev'] !== null ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  589) 			$prevEdits['prevSha'] = $prevEdits['prev']->getSha();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  590) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  591) 		$prevEdits['prev'] = $edit;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  592) 		$this->lastEdit = $edit;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  593) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  594) 		return $prevEdits;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  595) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  596) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  597) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  598) 	 * Update various figures about content sizes based on the given edit.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  599) 	 * @param Edit $edit
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  600) 	 * @param Edit[] $prevEdits With 'prev', 'prevSha', 'maxAddition' and 'maxDeletion'.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  601) 	 * @return Edit[] Updated version of $prevEdits.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  602) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  603) 	private function updateContentSizes( Edit $edit, array $prevEdits ): array {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  604) 		// Check if it was a revert
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  605) 		if ( $this->isRevert( $edit, $prevEdits ) ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  606) 			$edit->setReverted( true );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  607) 			return $this->updateContentSizesRevert( $prevEdits );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  608) 		} else {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  609) 			return $this->updateContentSizesNonRevert( $edit, $prevEdits );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  610) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  611) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  612) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  613) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  614) 	 * Is the given Edit a revert?
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  615) 	 * @param Edit $edit
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  616) 	 * @param Edit[] $prevEdits With 'prev', 'prevSha', 'maxAddition' and 'maxDeletion'.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  617) 	 * @return bool
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  618) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  619) 	private function isRevert( Edit $edit, array $prevEdits ): bool {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  620) 		return $edit->getSha() === $prevEdits['prevSha'] || $edit->isRevert();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  621) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  622) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  623) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  624) 	 * Updates the figures on content sizes assuming the given edit was a revert of the previous one.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  625) 	 * In such a case, we don't want to treat the previous edit as legit content addition or removal.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  626) 	 * @param Edit[] $prevEdits With 'prev', 'prevSha', 'maxAddition' and 'maxDeletion'.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  627) 	 * @return Edit[] Updated version of $prevEdits, for tracking.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  628) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  629) 	private function updateContentSizesRevert( array $prevEdits ): array {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  630) 		$this->revertCount++;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  631) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  632) 		// Adjust addedBytes given this edit was a revert of the previous one.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  633) 		if ( $prevEdits['prev'] && $prevEdits['prev']->isReverted() === false && $prevEdits['prev']->getSize() > 0 ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  634) 			$this->addedBytes -= $prevEdits['prev']->getSize();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  635) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  636) 			// Also deduct from the user's individual added byte count.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  637) 			// We don't do this if the previous edit was reverted, since that would make the net bytes zero.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  638) 			if ( $prevEdits['prev']->getUser() ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  639) 				$username = $prevEdits['prev']->getUser()->getUsername();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  640) 				$this->editors[$username]['added'] -= $prevEdits['prev']->getSize();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  641) 			}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  642) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  643) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  644) 		// @TODO: Test this against an edit war (use your sandbox).
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  645) 		// Also remove as max added or deleted, if applicable.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  646) 		if ( $this->maxAddition && $prevEdits['prev']->getId() === $this->maxAddition->getId() ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  647) 			$this->maxAddition = $prevEdits['maxAddition'];
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  648) 			// In the event of edit wars.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  649) 			$prevEdits['maxAddition'] = $prevEdits['prev'];
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  650) 		} elseif ( $this->maxDeletion && $prevEdits['prev']->getId() === $this->maxDeletion->getId() ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  651) 			$this->maxDeletion = $prevEdits['maxDeletion'];
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  652) 			// In the event of edit wars.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  653) 			$prevEdits['maxDeletion'] = $prevEdits['prev'];
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  654) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  655) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  656) 		return $prevEdits;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  657) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  658) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  659) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  660) 	 * Updates the figures on content sizes assuming the given edit was NOT a revert of the previous edit.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  661) 	 * @param Edit $edit
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  662) 	 * @param Edit[] $prevEdits With 'prev', 'prevSha', 'maxAddition' and 'maxDeletion'.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  663) 	 * @return Edit[] Updated version of $prevEdits, for tracking.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  664) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  665) 	private function updateContentSizesNonRevert( Edit $edit, array $prevEdits ): array {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  666) 		$editSize = $this->getEditSize( $edit, $prevEdits );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  667) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  668) 		// Edit was not a revert, so treat size > 0 as content added.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  669) 		if ( $editSize > 0 ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  670) 			$this->addedBytes += $editSize;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  671) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  672) 			if ( $edit->getUser() ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  673) 				$this->editors[$edit->getUser()->getUsername()]['added'] += $editSize;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  674) 			}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  675) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  676) 			// Keep track of edit with max addition.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  677) 			if ( !$this->maxAddition || $editSize > $this->maxAddition->getSize() ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  678) 				// Keep track of old maxAddition in case we find out the next $edit was reverted
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  679) 				// (and was also a max edit), in which case we'll want to use this one ($edit).
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  680) 				$prevEdits['maxAddition'] = $this->maxAddition;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  681) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  682) 				$this->maxAddition = $edit;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  683) 			}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  684) 		} elseif ( $editSize < 0 && ( !$this->maxDeletion || $editSize < $this->maxDeletion->getSize() ) ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  685) 			// Keep track of old maxDeletion in case we find out the next edit was reverted
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  686) 			// (and was also a max deletion), in which case we'll want to use this one.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  687) 			$prevEdits['maxDeletion'] = $this->maxDeletion;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  688) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  689) 			$this->maxDeletion = $edit;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  690) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  691) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  692) 		return $prevEdits;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  693) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  694) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  695) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  696) 	 * Get the size of the given edit, based on the previous edit (if present).
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  697) 	 * We also don't return the actual edit size if last revision had a length of null.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  698) 	 * This happens when the edit follows other edits that were revision-deleted.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  699) 	 * @see T148857 for more information.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  700) 	 * @todo Remove once T101631 is resolved.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  701) 	 * @param Edit $edit
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  702) 	 * @param Edit[] $prevEdits With 'prev', 'prevSha', 'maxAddition' and 'maxDeletion'.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  703) 	 * @return int|null
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  704) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  705) 	private function getEditSize( Edit $edit, array $prevEdits ): ?int {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  706) 		if ( $prevEdits['prev'] && $prevEdits['prev']->getLength() === null ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  707) 			return 0;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  708) 		} else {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  709) 			return $edit->getSize();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  710) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  711) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  712) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  713) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  714) 	 * Update counts of automated tool usage for the given edit.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  715) 	 * @param Edit $edit
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  716) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  717) 	private function updateToolCounts( Edit $edit ): void {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  718) 		$automatedTool = $edit->getTool();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  719) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  720) 		if ( !$automatedTool ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  721) 			// Nothing to do.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  722) 			return;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  723) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  724) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  725) 		$editYear = $edit->getYear();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  726) 		$editMonth = $edit->getMonth();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  727) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  728) 		$this->automatedCount++;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  729) 		$this->yearMonthCounts[$editYear]['automated']++;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  730) 		$this->yearMonthCounts[$editYear]['months'][$editMonth]['automated']++;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  731) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  732) 		if ( !isset( $this->tools[$automatedTool['name']] ) ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  733) 			$this->tools[$automatedTool['name']] = [
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  734) 				'count' => 1,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  735) 				'link' => $automatedTool['link'],
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  736) 			];
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  737) 		} else {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  738) 			$this->tools[$automatedTool['name']]['count']++;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  739) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  740) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  741) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  742) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  743) 	 * Update various counts for the year and month of the given edit.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  744) 	 * @param Edit $edit
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  745) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  746) 	private function updateYearMonthCounts( Edit $edit ): void {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  747) 		$editYear = $edit->getYear();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  748) 		$editMonth = $edit->getMonth();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  749) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  750) 		// Fill in the blank arrays for the year and 12 months if needed.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  751) 		if ( !isset( $this->yearMonthCounts[$editYear] ) ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  752) 			$this->addYearMonthCountEntry( $edit );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  753) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  754) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  755) 		// Increment year and month counts for all edits
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  756) 		$this->yearMonthCounts[$editYear]['all']++;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  757) 		$this->yearMonthCounts[$editYear]['months'][$editMonth]['all']++;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  758) 		// This will ultimately be the size of the page by the end of the year
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  759) 		$this->yearMonthCounts[$editYear]['size'] = $edit->getLength();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  760) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  761) 		// Keep track of which month had the most edits
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  762) 		$editsThisMonth = $this->yearMonthCounts[$editYear]['months'][$editMonth]['all'];
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  763) 		if ( $editsThisMonth > $this->maxEditsPerMonth ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  764) 			$this->maxEditsPerMonth = $editsThisMonth;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  765) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  766) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  767) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  768) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  769) 	 * Add a new entry to $this->yearMonthCounts for the given year,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  770) 	 * with blank values for each month. This called during self::parseHistory().
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  771) 	 * @param Edit $edit
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  772) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  773) 	private function addYearMonthCountEntry( Edit $edit ): void {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  774) 		$this->yearLabels[] = $this->i18n->dateFormat( $edit->getTimestamp(), 'yyyy' );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  775) 		$editYear = $edit->getYear();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  776) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  777) 		// Beginning of the month at 00:00:00.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  778) 		$firstEditTime = mktime( 0, 0, 0, (int)$this->firstEdit->getMonth(), 1, (int)$this->firstEdit->getYear() );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  779) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  780) 		$this->yearMonthCounts[$editYear] = [
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  781) 			'all' => 0,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  782) 			'minor' => 0,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  783) 			'anon' => 0,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  784) 			'automated' => 0,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  785) 			// Keep track of the size by the end of the year.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  786) 			'size' => 0,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  787) 			'events' => [],
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  788) 			'months' => [],
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  789) 		];
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  790) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  791) 		for ( $i = 1; $i <= 12; $i++ ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  792) 			$timeObj = mktime( 0, 0, 0, $i, 1, (int)$editYear );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  793) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  794) 			// Don't show zeros for months before the first edit or after the current month.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  795) 			if ( $timeObj < $firstEditTime || $timeObj > $this->getLastDay() ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  796) 				continue;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  797) 			}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  798) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  799) 			$this->monthLabels[] = $this->i18n->dateFormat( $timeObj, 'yyyy-MM' );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  800) 			$this->yearMonthCounts[$editYear]['months'][sprintf( '%02d', $i )] = [
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  801) 				'all' => 0,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  802) 				'minor' => 0,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  803) 				'anon' => 0,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  804) 				'automated' => 0,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  805) 			];
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  806) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  807) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  808) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  809) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  810) 	 * Update the counts of anon and minor edits for year, month, and user of the given edit.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  811) 	 * @param Edit $edit
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  812) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  813) 	private function updateAnonMinorCounts( Edit $edit ): void {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  814) 		$editYear = $edit->getYear();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  815) 		$editMonth = $edit->getMonth();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  816) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  817) 		// If anonymous, increase counts
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  818) 		if ( $edit->isAnon( $this->page->getProject() ) ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  819) 			$this->anonCount++;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  820) 			$this->yearMonthCounts[$editYear]['anon']++;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  821) 			$this->yearMonthCounts[$editYear]['months'][$editMonth]['anon']++;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  822) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  823) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  824) 		// If minor edit, increase counts
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  825) 		if ( $edit->isMinor() ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  826) 			$this->minorCount++;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  827) 			$this->yearMonthCounts[$editYear]['minor']++;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  828) 			$this->yearMonthCounts[$editYear]['months'][$editMonth]['minor']++;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  829) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  830) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  831) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  832) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  833) 	 * Update various counts for the user of the given edit.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  834) 	 * @param Edit $edit
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  835) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  836) 	private function updateUserCounts( Edit $edit ): void {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  837) 		if ( !$edit->getUser() ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  838) 			return;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  839) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  840) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  841) 		$username = $edit->getUser()->getUsername();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  842) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  843) 		// Initialize various user stats if needed.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  844) 		if ( !isset( $this->editors[$username] ) ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  845) 			$this->editors[$username] = [
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  846) 				'all' => 0,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  847) 				'minor' => 0,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  848) 				'minorPercentage' => 0,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  849) 				'first' => $edit->getTimestamp(),
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  850) 				'firstId' => $edit->getId(),
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  851) 				'last' => null,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  852) 				'atbe' => 0,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  853) 				'added' => 0,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  854) 			];
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  855) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  856) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  857) 		// Increment user counts
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  858) 		$this->editors[$username]['all']++;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  859) 		$this->editors[$username]['last'] = $edit->getTimestamp();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  860) 		$this->editors[$username]['lastId'] = $edit->getId();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  861) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  862) 		// Increment minor counts for this user
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  863) 		if ( $edit->isMinor() ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  864) 			$this->editors[$username]['minor']++;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  865) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  866) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  867) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  868) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  869) 	 * Increment "edits per <time>" counts based on the given edit.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  870) 	 * @param Edit $edit
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  871) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  872) 	private function updateCountHistory( Edit $edit ): void {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  873) 		$editTimestamp = $edit->getTimestamp();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  874) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  875) 		if ( $editTimestamp > new DateTime( '-1 day' ) ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  876) 			$this->countHistory['day']++;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  877) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  878) 		if ( $editTimestamp > new DateTime( '-1 week' ) ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  879) 			$this->countHistory['week']++;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  880) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  881) 		if ( $editTimestamp > new DateTime( '-1 month' ) ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  882) 			$this->countHistory['month']++;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  883) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  884) 		if ( $editTimestamp > new DateTime( '-1 year' ) ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  885) 			$this->countHistory['year']++;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  886) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  887) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  888) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  889) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  890) 	 * Query for log events during each year of the page's history, and set the results in $this->yearMonthCounts.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  891) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  892) 	private function setLogsEvents(): void {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  893) 		$logData = $this->repository->getLogEvents(
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  894) 			$this->page,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  895) 			$this->start,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  896) 			$this->end
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  897) 		);
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  898) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  899) 		foreach ( $logData as $event ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  900) 			$time = strtotime( $event['timestamp'] );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  901) 			$year = date( 'Y', $time );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  902) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  903) 			if ( !isset( $this->yearMonthCounts[$year] ) ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  904) 				break;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  905) 			}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  906) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  907) 			$yearEvents = $this->yearMonthCounts[$year]['events'];
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  908) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  909) 			// Convert log type value to i18n key.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  910) 			switch ( $event['log_type'] ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  911) 				// count pending-changes protections along with normal protections.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  912) 				case 'stable':
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  913) 				case 'protect':
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  914) 					$action = 'protections';
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  915) 					break;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  916) 				case 'delete':
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  917) 					$action = 'deletions';
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  918) 					break;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  919) 				case 'move':
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  920) 					$action = 'moves';
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  921) 					break;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  922) 			}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  923) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  924) 			if ( empty( $yearEvents[$action] ) ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  925) 				$yearEvents[$action] = 1;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  926) 			} else {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  927) 				$yearEvents[$action]++;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  928) 			}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  929) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  930) 			$this->yearMonthCounts[$year]['events'] = $yearEvents;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  931) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  932) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  933) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  934) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  935) 	 * Set statistics about the top 10 editors by added text and number of edits.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  936) 	 * This is ran *after* parseHistory() since we need the grand totals first.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  937) 	 * Various stats are also set for each editor in $this->editors to be used in the charts.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  938) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  939) 	private function doPostPrecessing(): void {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  940) 		$topTenCount = $counter = 0;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  941) 		$topTenEditorsByEdits = [];
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  942) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  943) 		foreach ( $this->editors as $editor => $info ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  944) 			// Count how many users are in the top 10% by number of edits, excluding bots.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  945) 			if ( $counter < 10 && !array_key_exists( $editor, $this->bots ) ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  946) 				$topTenCount += $info['all'];
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  947) 				$counter++;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  948) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  949) 				// To be used in the Top Ten charts.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  950) 				$topTenEditorsByEdits[] = [
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  951) 					'label' => $editor,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  952) 					'value' => $info['all'],
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  953) 				];
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  954) 			}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  955) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  956) 			// Compute the percentage of minor edits the user made.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  957) 			$this->editors[$editor]['minorPercentage'] = $info['all']
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  958) 				? ( $info['minor'] / $info['all'] ) * 100
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  959) 				: 0;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  960) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  961) 			if ( $info['all'] > 1 ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  962) 				// Number of seconds/days between first and last edit.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  963) 				$secs = $info['last']->getTimestamp() - $info['first']->getTimestamp();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  964) 				$days = $secs / ( 60 * 60 * 24 );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  965) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  966) 				// Average time between edits (in days).
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  967) 				$this->editors[$editor]['atbe'] = round( $days / ( $info['all'] - 1 ), 1 );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  968) 			}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  969) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  970) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  971) 		// Loop through again and add percentages.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  972) 		$this->topTenEditorsByEdits = array_map( static function ( $editor ) use ( $topTenCount ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  973) 			$editor['percentage'] = 100 * ( $editor['value'] / $topTenCount );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  974) 			return $editor;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  975) 		}, $topTenEditorsByEdits );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  976) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  977) 		$this->topTenEditorsByAdded = $this->getTopTenByAdded();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  978) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  979) 		$this->topTenCount = $topTenCount;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  980) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  981) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  982) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  983) 	 * Get the top ten editors by added text.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  984) 	 * @return array With keys 'label', 'value' and 'percentage', ready to be used by the pieChart Twig helper.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  985) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  986) 	private function getTopTenByAdded(): array {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  987) 		// First sort editors array by the amount of text they added.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  988) 		$topTenEditorsByAdded = $this->editors;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  989) 		uasort( $topTenEditorsByAdded, static function ( $a, $b ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  990) 			if ( $a['added'] === $b['added'] ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  991) 				return 0;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  992) 			}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  993) 			return $a['added'] > $b['added'] ? -1 : 1;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  994) 		} );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  995) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  996) 		// Slice to the top 10.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  997) 		$topTenEditorsByAdded = array_keys( array_slice( $topTenEditorsByAdded, 0, 10, true ) );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  998) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500  999) 		 // Get the sum of added text so that we can add in percentages.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1000) 		 $topTenTotalAdded = array_sum( array_map( function ( $editor ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1001) 			 return $this->editors[$editor]['added'];
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1002) 		 }, $topTenEditorsByAdded ) );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1003) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1004) 		// Then build a new array of top 10 editors by added text in the data structure needed for the chart.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1005) 		return array_map( function ( $editor ) use ( $topTenTotalAdded ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1006) 			$added = $this->editors[$editor]['added'];
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1007) 			return [
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1008) 				'label' => $editor,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1009) 				'value' => $added,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1010) 				'percentage' => $this->addedBytes === 0 ? 0 : 100 * ( $added / $topTenTotalAdded ),
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1011) 			];
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1012) 		}, $topTenEditorsByAdded );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1013) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1014) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1015) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1016) 	 * Get the number of times the page has been viewed in the last PageInfoApi::PAGEVIEWS_OFFSET days.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1017) 	 * If the PageInfo instance has a date range, it is used instead of the last N days.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1018) 	 * To reduce logic in the view, this method returns an array also containing the localized string
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1019) 	 * for the pageviews count, as well as the tooltip to be used on the link to the Pageviews tool.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1020) 	 * @return ?array With keys 'count'<int>, 'formatted'<string> and 'tooltip'<string>
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1021) 	 * @see PageInfoApi::PAGEVIEWS_OFFSET
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1022) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1023) 	public function getPageviews(): ?array {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1024) 		if ( !$this->hasDateRange() ) {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1025) 			$pageviews = $this->page->getLatestPageviews();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1026) 		} else {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1027) 			$dateRange = $this->getDateParams();
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1028) 			$pageviews = $this->page->getPageviews( $dateRange['start'], $dateRange['end'] );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1029) 		}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1030) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1031) 		return [
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1032) 			'count' => $pageviews,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1033) 			'formatted' => $this->getPageviewsFormatted( $pageviews ),
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1034) 			'tooltip' => $this->getPageviewsTooltip( $pageviews ),
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1035) 		];
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1036) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1037) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1038) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1039) 	 * Convenience method for the view to get the value of the offset constant.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1040) 	 * (Twig code like `ai.PAGEVIEWS_OFFSET` just looks odd!)
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1041) 	 * @return int
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1042) 	 * @see PageInfoApi::PAGEVIEWS_OFFSET
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1043) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1044) 	public function getPageviewsOffset(): int {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1045) 		return PageInfoApi::PAGEVIEWS_OFFSET;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1046) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1047) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1048) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1049) 	 * Used to avoid putting too much logic in the view.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1050) 	 * @param int|null $pageviews
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1051) 	 * @return string Formatted number or "Data unavailable".
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1052) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1053) 	private function getPageviewsFormatted( ?int $pageviews ): string {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1054) 		return $pageviews !== null ?
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1055) 			$this->i18n->numberFormat( $pageviews ) :
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1056) 			$this->i18n->msg( 'data-unavailable' );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1057) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1058) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1059) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1060) 	 * Another convenience method for the view. Simply checks if there's data available,
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1061) 	 * and if not, provides an informative message to be used in the tooltip.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1062) 	 * @param int|null $pageviews
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1063) 	 * @return string
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1064) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1065) 	private function getPageviewsTooltip( ?int $pageviews ): string {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1066) 		return $pageviews ? '' : $this->i18n->msg( 'api-error-wikimedia', [ 'Pageviews' ] );
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1067) 	}
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1068) 
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1069) 	/**
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1070) 	 * Number of revisions with deleted information that could effect accuracy of the stats.
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1071) 	 * @return int
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1072) 	 */
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1073) 	public function numDeletedRevisions(): int {
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1074) 		return $this->numDeletedRevisions;
780ea3c57 src/Model/PageInfo.php              (MusikAnimal 2026-01-02 21:35:59 -0500 1075) 	}
0f0dcf06f src/Xtools/ArticleInfo.php          (MusikAnimal 2017-09-25 13:23:14 -0400 1076) }
