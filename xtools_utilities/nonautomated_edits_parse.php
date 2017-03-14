<?php
/**
 * This tool parses musikanimal's tools to pull edit summary regular expressions.
 * It generates valid YML to replace  app/config/semi_automated.yml
 */

$url = "https://tools.wmflabs.org/musikanimal/api/nonautomated_edits/tools";

// Set a useragent, as required
ini_set("user_agent", "Xtools-rebirth - http://xtools-dev.wmflabs.org");

// Pull Down the contents and decode
$file = file_get_contents($url);

$data = json_decode($file, true);

// Output the contents to the piped file
print ( "parameters:
  automated_tools:\r\n" );
foreach ($data as $row) {
    print "  - " . $row["name"] . ": '" . $row["regex"] . "'\r\n";
}
print ( "

  semi-automated edits source: $url" );
