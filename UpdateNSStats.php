<?php

/* UpdateNSStats.php
 * 
 * NOTE: This script must be run on the same server as the NewsScape Solr
 * search index. The index won't accept queries from other hosts.
 *
 * Author: Peter Broadwell <broadwell@library.ucla.edu>
 * Date: 15 February, 2014
 */

mb_internal_encoding("UTF-8");

date_default_timezone_set('UTC');

require "/home/broadwell/NewsScapeStats/TNAQuery.php";
include "CheckCCTiming.php";

function addToHash($key, $val, &$hash) {
  if (array_key_exists($key, $hash)) {
    $hash[$key][] = $val;
  } else {
    $hash[$key] = array($val);
  }
}

function matrixIncrement($first, $second, &$hash) {
  if (array_key_exists($first, $hash)) {
    if (isset ($hash[$first][$second])) {
      $hash[$first][$second] += 1;
    } else {
      $hash[$first][$second] = 1;
    }
  } else {
    $hash[$first] = array();
    $hash[$first][$second] = 1;
  }
}

function durToSecs ($dur, $filePath) { // $dur must be a string like "H:mm:ss.hh"

  // 00:59:50
  // 00:59:50.00

  $parse = array();
  if (preg_match ('#^(?<hours>[\d]{1}):(?<mins>[\d]{2}):(?<secs>[\d]{2})\.(?<subseconds>[\d]{1,})$#',$dur,$parse)) {
//    return (int) $parse['hours'] * 3600 + (int) $parse['mins'] * 60 + (int) $parse['secs'] + (int) $parse['subseconds'];
    return (int) $parse['hours'] * 3600 + (int) $parse['mins'] * 60 + (int) $parse['secs'];
  } else if (preg_match ('#^(?<hours>[\d]{2}):(?<mins>[\d]{2}):(?<secs>[\d]{2})$#',$dur,$parse)) {
  return (int) $parse['hours'] * 3600 + (int) $parse['mins'] * 60 + (int) $parse['secs'];
  } else if (preg_match ('#^(?<hours>[\d]{2}):(?<mins>[\d]{2}):(?<secs>[\d]{2})\.(?<subseconds>[\d]{2,})$#',$dur,$parse)) {
    return (int) $parse['hours'] * 3600 + (int) $parse['mins'] * 60 + (int) $parse['secs'];
  } else {
    echo "Duration format invalid: " . $dur . " for " . $filePath . "\n";
    return 0;
  }
}

function GetNetStartYear($networkName, $lastYear) {

  $startYear = 2004;

  while($startYear <= $lastYear) {
    $startDate = '01/01/' . $startYear;
    $endDate = '12/31/' . $startYear;
    $query = 'date_from:"' . $startDate . '" date_to:"' . $endDate . '" network:' . $networkName . ' display_format:list regex_mode:multi tz_filter:lbt tz_group:utc limit:10';
    if (getHits($query) > 0) {
      return $startYear;
    } else {
      $startYear++;
    }
  }
  return $lastYear;

}

function GetNetRecordings($networkName, $endTimestamp) {

  echo "Getting net recordings for " . $networkName . ", endTimestamp " . $endTimestamp . "\n";

  global $UTCtz;

  $startDate = '03/01/2004';
  $endTime = DateTime::createFromFormat('YmdGi', $endTimestamp, $UTCtz);
  $endDate = date_format($endTime, 'm/d/Y');

  #echo "endDate is " . $endDate . "\n";

  $query = 'date_from:"' . $startDate . '" date_to:"' . $endDate . '" network:' . $networkName . ' display_format:list regex_mode:multi tz_filter:lbt tz_group:utc limit:10';

  $recs = getHits($query);
//  echo "Got " . $recs . " total recordings for network " . $networkName . "\n";

  return $recs;
}

/* Mapping from the internal network IDs used in the NewsScape to
 * the real, canonical network names and regions. Note that some
 * network IDs map to the same canonical name (e.g., CNN Headline and HLN);
 * internally, the last network ID in the list is the one that is used in
 * the JSON structures.
 */
$NetworkInfo = array("24h" => array("24h (Spain)", "Europe"),
                     "AlJazeera" => array("Al Jazeera (Qatar)", "Asia"),
                     "BBC" => array("BBC Persian", "Asia"),
                     "CampaignAds" => array("Political Ads", "Research"),
                     "CNN" => array("CNN", "Cable (US)"),
                     "CNN-International" => array("CNN International", "Cable (US)"),
                     "CNN-Headline" => array("HLN/Headline News", "Cable (US)"),
                     "CSPAN" => array("C-SPAN", "Cable (US)"),
                     "CSPAN2" => array("C-SPAN2", "Cable (US)"),
                     "ČT1" => array("ČT1 (Czech Republic)", "Europe"),
                     "ComedyCentral" => array("Comedy Central", "Cable (US)"),
                     "Court" => array("truTV/Court TV", "Cable (US)"),
                     "Current" => array("Current TV", "Cable (US)"),
                     "DigitalEphemera" => array("Digital Ephemera", "Research"),
                     "DR1" => array("DR1 (Denmark)", "Europe"),
                     "DasErste" => array("Das Erste (Germany)", "Europe"),
                     "France-2" => array("France 2", "Europe"),
                     "France-3" => array("France 3", "Europe"),
                     "FOX-News" => array("Fox News", "Cable (US)"),
                     "Globo" => array("Globo", "Brazil"),
                     "HBO" => array("HBO", "Cable (US)"),
                     "HLN" => array("HLN/Headline News", "Cable (US)"),
                     "KABC" => array("KABC", "Los Angeles"),
                     "KCAL" => array("KCAL (independent)", "Los Angeles"), // CBS/independent
                     "KCBS" => array("KCBS", "Los Angeles"),
                     "KCET" => array("KCET (independent)", "Los Angeles"), // Independent/NC
                     "KMEX" => array("KMEX (Univision)", "Los Angeles"), // Univision
                     "KNBC" => array("KNBC", "Los Angeles"),
                     "KOCE" => array("KOCE (PBS)", "Los Angeles"), // PBS
                     "KTLA" => array("KTLA (CW)", "Los Angeles"), // CW
                     "KTTV-FOX" => array("KTTV (Fox)", "Los Angeles"), // Fox
                     "La-1" => array("La 1 (Spain)", "Europe"),
                     "MSNBC" => array("MSNBC", "Cable (US)"),
                     "NRK1" => array("NRK1 (Norway)", "Europe"),
                     "Record" => array("Record", "Brazil"),
                     "RT" => array("RT America", "Cable (US)"),
                     "RTP-1" => array("RTP1 (Portugal)", "Europe"),
                     "SIC" => array("SIC (Portuguese)", "Europe"),
                     "SVT1" => array("SVT1 (Sweden)", "Europe"),
                     "SVT2" => array("SVT2 (Sweden)", "Europe"),
                     "Tagesschau24" => array("Tagesschau24 (Germany)", "Europe"),
                     "ТВЦ" => array("TVC (Russia)", "Europe"),
                     "Tolo" => array("Tolo (Afghanistan)", "Asia"),
                     "TruTV" => array("truTV/Court TV", "Cable (US)"),
                     "TVP1" => array("TVP1 (Poland)", "Europe"),
                     "TV2-NOR" => array("TV 2 (Norway)", "Europe"), // Norway
                     "TV5" => array("TV5 (French)", "Europe"), // France
                     "WEWS" => array("WEWS (ABC)", "Cleveland, OH"), // ABC
                     "WKYC" => array("WKYC (NBC)", "Cleveland, OH"), // NBC
                     "WOIO" => array("WOIO (CBS)", "Cleveland, OH"), // CBS/syndicated
                     "WUAB" => array("WUAB (MyNetworkTV)", "Cleveland, OH"), // MyNetworkTV
                     "WWW" => array("Web (various)", "Research"),
                     "ZDF" => array("ZDF (Germany)", "Europe"));

$totalVideoFiles = 0;
$lastVideoFiles = 0;
$totalOCRFiles = 0;
$lastOCRFiles = 0;
$totalTPTFiles = 0;
$lastTPTFiles = 0;
$totalMetadataFiles = 0;
$lastMetadataFiles = 0;

$totalDuration = 0;
$lastDuration = 0;

$showWords = 0;
$lastShowWords = 0;
$ocrWords = 0;
$tptWords = 0;
$lastOCRWords = 0;
$lastTPTWords = 0;

$totalGigs = 100386; // Approx. size of archive as of 02-20-2016
$lastGigs = $totalGigs;
$newBytes = 0;

$startYear = 2004;
$startMonth = 03;
$startDay = 01;

// The first year each network was recorded
$networkStartYear = array();
// Number of recordings made each year
$recordingsEachYear = array();

// Keys are filtered, updated list of "real" networks in the archive, values are
// total recordings on that network
$PrettyNetworks = array();

$YearToNetsAdded = array();

$startTimestamp = "200403010000";
$lastTimestamp = "200403010000";
$oldLastTimestamp = $lastTimestamp;

$LAtz = new DateTimeZone('America/Los_Angeles');
$UTCtz = new DateTimeZone('GMT');

$baseSummary = '/home/broadwell/NewsScapeStats/BaseNSStats.csv';
if (file_exists($baseSummary)) {

  $baseSummaryFile = fopen($baseSummary, "r");

  while ($lastStats = fgetcsv($baseSummaryFile)) {

    $label = $lastStats[0];
    $value = $lastStats[1];

    switch ($label) {
      case "lastYear":
        $startYear = $value;
      break;
      case "lastMonth":
        $startMonth = $value;
      break;
      case "lastDay":
        $startDay = $value;
      break;
      case "lastTimestamp":
        $lastTimestamp = $value;
        $oldLastTimestamp = $lastTimestamp;
      break;
      case "hours":
        $totalDuration = $value * 3600;
        $lastDuration = $totalDuration;
      break;
      case "metafiles":
        $totalMetadataFiles = $value;
        $lastMetadataFiles = $totalMetadataFiles;
      break;
      case "videofiles":
        $totalVideoFiles = $value;
        $lastVideoFiles = $totalVideoFiles;
      break;
      case "ocrfiles":
        $totalOCRFiles = $value;
        $lastOCRFiles = $totalOCRFiles;
      break;
      case "tptfiles":
        $totalTPTFiles = $value;
        $lastTPTFiles = $totalTPTFiles;
      break;
      case "metawords":
        $showWords = $value;
        $lastShowWords = $showWords;
      break;
      case "ocrwords":
        $ocrWords = $value;
        $lastOCRWords = $ocrWords;
      break;
      case "tptwords":
        $tptWords = $value;
        $lastTPTWords = $tptWords;
      break;
      case "totalGigs";
        $totalGigs = $value;
        $lastTotalGigs = $totalGigs;
      break;
      case "netStartYear":
        $networkName = $value;
        $startYear = $lastStats[2];
        if (!isset($NetworkInfo[$networkName]))
          continue;
        $networkInfo = $NetworkInfo[$networkName];
        $prettyNetwork = $networkInfo[0];
        if ((!isset($networkStartYear[$prettyNetwork])) || ($startYear < $networkStartYear[$prettyNetwork])) {
          $networkStartYear[$prettyNetwork] = $startYear;
        }
      break;
      case "totalYearRecs":
        $theYear = $value;
        $recordingsThisYear = $lastStats[2];
        $recordingsEachYear[$theYear] = $recordingsThisYear;
      break; 
      case "networkRecs":
        $prettyNetwork = $value;
        $netRecordings = $lastStats[2];
        $PrettyNetworks[$prettyNetwork] = $netRecordings;
      break;
    }

  }

  fclose($baseSummaryFile);

  // Add 30 minutes to the start time and update all values

  $startTime = DateTime::createFromFormat('YmdGi', $lastTimestamp, $UTCtz);
  $startTime->add(new DateInterval('PT30M'));
  $startYear = date_format($startTime, 'Y');
  $startMonth = date_format($startTime, 'Y-m');
  $startDay = date_format($startTime, 'Y-m-d');
  $startTimestamp = date_format($startTime, 'YmdHi');

  #echo "startTimestamp is " . $startTimestamp . "\n";

}

foreach ($NetworkInfo as $networkName => $networkInfo) {

  $prettyNetwork = $networkInfo[0];

  #echo "looking up info for " . $networkName . ", pretty name is " . $prettyNetwork . ", startTimestamp is " . $startTimestamp . "\n";

  if (!isset($PrettyNetworks[$prettyNetwork])) {
    $netRecordings = GetNetRecordings($networkName, $startTimestamp);

    foreach ($NetworkInfo as $networkName2 => $networkInfo2) {
      $prettyNetwork2 = $networkInfo2[0];
      if (($prettyNetwork == $prettyNetwork2) && ($networkName != $networkName2)) {
        $netRecordings += GetNetRecordings($networkName2, $startTimestamp);
      }
   }
   $PrettyNetworks[$prettyNetwork] = $netRecordings;
   }

}

$ShowNames = array();

$yearsDir = new DirectoryIterator('/mnt/isilon/tv/');
$allYears = array();
foreach ($yearsDir as $yearinfo) {
  if ($yearinfo->isDot())
    continue;
  if ($yearinfo->isDir()) { // && ($yearinfo->getFilename() == "2009"))
    $year = $yearinfo->getFilename();
    if (($year < $startYear) || ($year == "tv"))
      continue;
    $allYears[] = $year;
  }
}
sort($allYears);
foreach ($allYears as $year) {
  $allMonths = array();
  $monthsDir = new DirectoryIterator('/mnt/isilon/tv/' . $year . '/');
  foreach ($monthsDir as $monthinfo) {
    if ($monthinfo->isDot())
      continue;
    if ($monthinfo->isDir()) { // && ($monthinfo->getFilename() == "2009-08"))
      $month = $monthinfo->getFilename();
      if (($year <= $startYear) && ($month < $startMonth)) {
        continue;
      }
      $allMonths[] = $month;
    }
  }
  sort($allMonths);
  foreach ($allMonths as $month) {
    echo "Looking at month " . $month . "\n";
    $allDays = array();
    $daysDir = new DirectoryIterator('/mnt/isilon/tv/' . $year . '/' . $month . '/');
    foreach ($daysDir as $dayinfo) {
      if ($dayinfo->isDot())
        continue;
      if ($dayinfo->isDir()) {
        $day = $dayinfo->getFilename();
        if (($year <= $startYear) && ($month <= $startMonth) && ($day < $startDay)) {
//              echo "skipping day " . $day . ", startDay is " . $startDay . "\n";
          continue;
        }
        $allDays[] = $day;
      }
    }
    sort($allDays);
    foreach($allDays as $day) {
//      $allFiles = array();
      $dateDir = new DirectoryIterator('/mnt/isilon/tv/' . $year . '/' . $month . '/' . $day . '/');
      foreach ($dateDir as $dateinfo) {
        if ($dateinfo->isDot())
          continue;
        if ($dateinfo->isDir()) {
          // Process the thumbnail image folders (and anything else)
          $extraDir = new DirectoryIterator($dateinfo->getPathName());
          $filename = $dateinfo->getFilename();
          if ($filename == "ost")
            continue;
          $nameArray = explode('_', $filename);
          $dateSection = implode('', array_slice($nameArray, 0, 2));
          $thisTimestamp = str_replace('-', '', $dateSection);
          $thisTimestamp = str_replace('_', '', $thisTimestamp);
          if (strlen($thisTimestamp) != 12) {
            echo "ERROR: malformed timestamp: " . $thisTimestamp . " for " . $filename . "\n";
          } else if ($thisTimestamp < $startTimestamp) {
//                  echo "skipping " . $filename . ", thisTimestamp is " . $thisTimestamp . ", startTimestamp is " . $startTimestamp . "\n";
            continue;
          }
          foreach ($extraDir as $extrainfo) {
            if ($extrainfo->isDot())
              continue;
            $newBytes += filesize($dateinfo->getPathName());
          }
        }
        if ($dateinfo->isFile()) {
          $filename = $dateinfo->getFilename();
          $newBytes += filesize($dateinfo->getPathName());
          $extensionArray = explode('.', $filename);
          $extension = end($extensionArray);
          $nameArray = explode('_', $filename);
          $dateSection = implode('', array_slice($nameArray, 0, 2));
          $thisTimestamp = str_replace('-', '', $dateSection);
          $thisTimestamp = str_replace('_', '', $thisTimestamp);

          if (strlen($thisTimestamp) != 12) {
            echo "ERROR: malformed timestamp: " . $thisTimestamp . " for " . $filename . "\n";
          } else if ($thisTimestamp < $startTimestamp) {
//                  echo "skipping " . $filename . ", thisTimestamp is " . $thisTimestamp . ", startTimestamp is " . $startTimestamp . "\n";
            continue;
          } else if ($thisTimestamp > $lastTimestamp) {
            $lastTimestamp = $thisTimestamp;
//            echo "lastTimestamp is now " . $lastTimestamp . "\n";
          }
//          $extension = $dateinfo->getExtension();
          if ($extension == "txt") {
            $totalMetadataFiles++;

            $thisNetwork = $nameArray[3];
            if (isset($NetworkInfo[$thisNetwork])) {
              $networkInfo = $NetworkInfo[$thisNetwork];
              $thisPrettyNetwork = $networkInfo[0];
              $PrettyNetworks[$thisPrettyNetwork]++;
            }

            $textContents = file_get_contents($dateinfo->getPathName());
            if ($textContents === false)
              continue;

            if (preg_match('/\nDUR\|(.*?)\n/', $textContents, $matches)) {
              $progDur = $matches[1];
              $totalDuration += durToSecs($progDur, $filename);
            }

            $ccWords = preg_replace('/^[0-9\.]*?\|[0-9\.]*?\|[^\|]*?\|/', "", $textContents);

            $wordcount = str_word_count($ccWords);

//            echo $filename . " has " . $wordcount . " non-timestamp words in it\n";

            $showWords += $wordcount;

          } else if ($extension == "mp4") {
            $totalVideoFiles++;
            $filebase = $dateinfo->getBasename('.mp4');
          } else if ($extension == "ocr") {
	    $totalOCRFiles++;
            $textContents = file_get_contents($dateinfo->getPathName());
            if ($textContents === false)
              continue;
            $ocrContent = preg_replace('/^[0-9\.]*?\|[0-9\.]*?\|[^\|]*?\|[0-9]*?\|[0-9\s]*?\|/', "", $textContents);
            $wordcount = str_word_count($ocrContent);
//            echo $filename . " has " . $wordcount . " OCRed words in it\n";
            $ocrWords += $wordcount;
          } else if ($extension == "tpt") {
	    $totalTPTFiles++;
            $textContents = file_get_contents($dateinfo->getPathName());
            if ($textContents === false)
              continue;
            $tptContent = preg_replace('/^[0-9\.]*?\|[0-9\.]*?\|[^\|]*?\|/', "", $textContents);
            $wordcount = str_word_count($tptContent);
//            echo $filename . " has " . $wordcount . " transcript words in it\n";
            $tptWords += $wordcount;
          }
        }
      }
    }
  }
}

//echo "creating datetime from lastTimestamp, which is " . $lastTimestamp . "\n";

$lastTime = DateTime::createFromFormat('YmdGi', $lastTimestamp, $UTCtz);

$lastYear = date_format($lastTime, 'Y');
$lastMonthOnly = date_format($lastTime, 'm');
$lastMonth = date_format($lastTime, 'Y-m');
$lastDayOnly = date_format($lastTime, 'd');
$lastDay = date_format($lastTime, 'Y-m-d');

$dateTo = date_format($lastTime, 'm/d/Y');

$prettyTimestamp = date_format($lastTime, 'Y-m-d G:i');
$prettyTimestamp .= " GMT";

$oldLastTime = DateTime::createFromFormat('YmdGi', $oldLastTimestamp, $UTCtz);
$lastPrettyTimestamp = date_format($oldLastTime, 'Y-m-d G:i');
$lastPrettyTimestamp .= " GMT";

/* Fields that must be filled in: date_to, network, network_count  */
//$queryBase = 'http://newsscape.library.ucla.edu/index.php?';
$queryBase = 'http://tvnews.library.ucla.edu/search?';

//$networkQueryTemplate = 'mode=job_run&q=&display_format=list&limit=10&date_from=03/01/2004&date_to=%s&tz_filter=lbt&sort_by=datetime_desc&tz_sort=utc&tz_group=utc&network[]=%s&network_count=%s';
$networkQueryTemplate = 'search=&start=&end=&network=%s';

/* Fields that must be filled in: date_to, network, network_count, network_series,
 * network_series_count
 * network_series is formatted: network_name show name words separated by spaces */
//$seriesQueryTemplate = 'mode=job_run&q=&display_format=list&limit=10&date_from=03/01/2004&date_to=%s&tz_filter=lbt&sort_by=datetime_desc&tz_sort=utc&tz_group=utc&network[]=%s&network_count=%s&network_series[]=%s&network_series_count=%s';
$seriesQueryTemplate = 'search=&start=&end=&network=%s&network_series=%s';

/* Query the Solr index (must be accessible locally) to get the
 * total number of networks and series in the indexed collection. */

$totalNetworks = 0;
$totalSeries = 0;

$networks = array();
$networkSeries = array();

$solrAdvancedURL = 'http://localhost:8983/solr/tna/advancedsearch?wt=json'; 

//echo "Running advanced search query\n";
$advancedData = solrQuery($solrAdvancedURL);

foreach ($advancedData as $key=>$value) {

  if ($key == 'responseHeader') {
    $responseHeader = $value;
  } else if ($key == 'dateMin') {
    $dateMin = $value;
  } else if ($key == 'dateMax') {
    $dateMax = $value;
  } else if ($key == 'dateMinStr') {
    $dateMinStr = $value;
  } else if ($key == 'dateMaxStr') {
    $dateMaxStr = $value;
  } else if ($key == 'networks') {
    $networksArray = $value;
    $totalNetworks = count($networksArray);

    foreach ($PrettyNetworks as $prettyNetwork => $netRecordings) {
      $networkName = "";
      foreach ($NetworkInfo as $thisNetworkName => $networkInfo) {
        if ($networkInfo[0] == $prettyNetwork) {
          $networkName = $thisNetworkName;
          $networkRegion = $networkInfo[1];
        }
      }
      if ($networkName == "")
        continue;

//      $networkQuery = $queryBase . urlencode(sprintf($networkQueryTemplate, $dateTo, $networkName, $totalNetworks));
      $networkQuery = $queryBase . urlencode(sprintf($networkQueryTemplate, $networkName));
      
      if (!isset($networkStartYear[$prettyNetwork])) {
        $netStartYear = GetNetStartYear($networkName, $lastYear);
        $networkStartYear[$prettyNetwork] = $netStartYear;
      } else {
        $netStartYear = $networkStartYear[$prettyNetwork];
      }

      addToHash($netStartYear, array('id' => $networkName, 'network' => $prettyNetwork), $YearToNetsAdded);          
      $netRecordings = $PrettyNetworks[$prettyNetwork];

      $networks[] = array('id' => $networkName,
                          'network' => $prettyNetwork,
                          'region' => $networkRegion,
                          'networkURL' => $networkQuery,
                          'year_added' => $netStartYear,
                          'recordings' => number_format($netRecordings, 0, '.', ','));
    }
  } else if ($key == 'networkSeries') {
    foreach ($value as $network => $seriesArray) {
      $totalSeries += count($seriesArray);
    }
    foreach ($value as $network => $seriesArray) {
      foreach($seriesArray as $series) {
//        $debugSeriesQuery = $queryBase . sprintf($seriesQueryTemplate, $dateTo, $network, $totalNetworks, $network . " " . $series, $totalSeries);
//        $networkSeriesQuery = $queryBase . urlencode(sprintf($seriesQueryTemplate, $dateTo, $network, $totalNetworks, $network . " " . $series, $totalSeries));
        $networkSeriesQuery = $queryBase . urlencode(sprintf($seriesQueryTemplate, $network, $series));
        $networkSeries[] = array('id' => $network . '_' . str_replace(" ", "_", $series), 'network' => $network, 'series' => $series, 'seriesURL' => $networkSeriesQuery);
        // XXX Add new networks here if it's a series from a foreign network rebroadcast
        // on a domestic one (happens a lot with KCET)?
      }
    }
  }
}

$json = array();

$totalYears = ($lastYear - 2005) + ($lastMonthOnly / 12) - ((28 - $lastDayOnly) / 365);
$totalPrettyNetworks = count($PrettyNetworks);

$json['generatedDate'] = $lastTimestamp;

$statistics = array(array("name" => "totalYears", "statistic" => number_format($totalYears, 1, '.', ',')),
                    array("name" => "totalNetworks", "statistic" => $totalNetworks),
                    array("name" => "visibleNetworks", "statistic" => $totalPrettyNetworks),
                    array("name" => "totalSeries", 'statistic' => number_format($totalSeries, 0)));

$json['statistics'] = $statistics;

$yearStats = array(); 

$startYear = 2004;
while($startYear < $lastYear) {
  if (isset($recordingsEachYear[$startYear])) {
    $recordingsThisYear = $recordingsEachYear[$startYear];
  } else {
    $startDate = '01/01/' . $startYear;
    $endDate = '12/31/' . $startYear;
//    echo "Running query for year " . $startYear . "\n";
    $query = 'date_from:"' . $startDate . '" date_to:"' . $endDate . '" display_format:list regex_mode:multi tz_filter:lbt tz_group:utc limit:10';
    $recordingsThisYear = getHits($query);
    $recordingsEachYear[$startYear] = $recordingsThisYear;
  }

  $netsAdded = array();
  if (isset($YearToNetsAdded[$startYear])) {
    $netsAdded = $YearToNetsAdded[$startYear];
  }

  $yearStats[] = array('id' => $startYear, 'year' => $startYear , 'recordings' => number_format($recordingsThisYear, 0, '.', ','), 'networksAdded' => $netsAdded);
  $startYear++;
}
// Always update the recording count for the current year
// XXX note this assumes the recordings for past years never change, which is
// a very questionable assumption but OK for now
$startDate = '01/01/' . $startYear;
$endDate = '12/31/' . $startYear;
$query = 'date_from:"' . $startDate . '" date_to:"' . $endDate . '" display_format:list regex_mode:multi tz_filter:lbt tz_group:utc limit:10';
$recordingsThisYear = getHits($query);
$netsAdded = array();
if (isset($YearToNetsAdded[$startYear])) {
  $netsAdded = $YearToNetsAdded[$startYear];
}
$yearStats[] = array('id' => $startYear, 'year' => $startYear, 'recordings' => number_format($recordingsThisYear, 0, '.', ','), 'networksAdded' => $netsAdded);
$recordingsEachYear[$startYear] = $recordingsThisYear;

$factoids = array();

$factoids[] = array('name' => "hours", 'text' => number_format($totalDuration/3600, 0, '.', ',') . " hours");
$factoids[] = array('name' => "networks", 'text' => $totalPrettyNetworks . " networks");
$factoids[] = array('name' => "series", 'text' => number_format($totalSeries, 0, '.', ',') . " series");
$factoids[] = array('name' => "recordings", 'text' => number_format($totalMetadataFiles, 0, '.', ',') . " recordings");
$factoids[] = array('name' => "captions", 'text' => number_format($showWords/1000000000, 2, '.', ',') . " billion captions");
$factoids[] = array('name' => "ost", 'text' => number_format($ocrWords/1000000, 0, '.', ',') . " million on-screen words");
$factoids[] = array('name' => "countries", 'text' => '14 countries, 11 languages');
$factoids[] = array('name' => "nonUShours", 'text' => '25,000 hrs non-US content');
$factoids[] = array('name' => "nonUSnets", 'text' => '20 non-US networks');

$json['years'] = $yearStats;

$json['factoids'] = $factoids;

$json['networks'] = $networks;
$json['networkSeries'] = $networkSeries;

$totalGigs += intval($newBytes / 1000000000);

$mdFile = '/home/broadwell/public_html/NewsScapeBrowsing.json';

file_put_contents($mdFile, json_encode($json));

$lastSummary = "/home/broadwell/NewsScapeStats/NSStats_" . $lastTimestamp;

$lastSummaryFile = fopen($lastSummary, "w");

fputcsv($lastSummaryFile, array("lastYear", $lastYear));
fputcsv($lastSummaryFile, array("lastMonth", $lastMonth));
fputcsv($lastSummaryFile, array("lastDay", $lastDay));
fputcsv($lastSummaryFile, array("lastTimestamp", $lastTimestamp));
fputcsv($lastSummaryFile, array("networks", $totalPrettyNetworks));
fputcsv($lastSummaryFile, array("series", $totalSeries));
fputcsv($lastSummaryFile, array("hours", $totalDuration/3600));
fputcsv($lastSummaryFile, array("metafiles", $totalMetadataFiles));
fputcsv($lastSummaryFile, array("ocrfiles", $totalOCRFiles));
fputcsv($lastSummaryFile, array("tptfiles", $totalTPTFiles));
fputcsv($lastSummaryFile, array("videofiles", $totalVideoFiles));
fputcsv($lastSummaryFile, array("metawords", $showWords));
fputcsv($lastSummaryFile, array("ocrwords", $ocrWords));
fputcsv($lastSummaryFile, array("tptwords", $tptWords));
fputcsv($lastSummaryFile, array("totalGigs", $totalGigs));

foreach ($recordingsEachYear as $thisYear => $recsThisYear) {
  fputcsv($lastSummaryFile, array("totalYearRecs", $thisYear, $recsThisYear));
}
 
foreach ($networkStartYear as $prettyNetwork => $netStartYear) {
  fputcsv($lastSummaryFile, array("netStartYear", $prettyNetwork, $netStartYear));
}

foreach ($PrettyNetworks as $prettyNetwork => $netRecordings) {
  fputcsv($lastSummaryFile, array("networkRecs", $prettyNetwork, $netRecordings));
}

fclose($lastSummaryFile);

$summaryText = "NewsScape summary statistics at " . $prettyTimestamp . " (previous values from checkpoint at " . $lastPrettyTimestamp . ")\n\n";

$summaryText .= "Total networks: " . $totalPrettyNetworks . "\n";
$summaryText .= "Total series: " . number_format($totalSeries, 0, '.', ',') . "\n";
$summaryText .= "Total duration in hours: " . number_format($totalDuration/3600, 0, '.', ',') . " (" . number_format($lastDuration/3600, 0, '.', ',') . ")\n";
$summaryText .= "Total metadata files (CC, OCR, TPT): " . number_format(($totalMetadataFiles + $totalOCRFiles + $totalTPTFiles), 0, '.', ',') . " (" . number_format(($lastMetadataFiles + $lastOCRFiles + $lastTPTFiles), 0, '.', ',') . ")\n";
$summaryText .= "Total words in metadata files (CC, OCR, TPT): " . number_format(($showWords+$ocrWords+$tptWords)/1000000000, 2, '.', ',') . " billion, " . number_format(($showWords+$ocrWords+$tptWords), 0, '.', ',') . " exactly (" . number_format(($lastShowWords+$lastOCRWords+$lastTPTWords)/1000000000, 2, '.', ',') . ")\n";
$summaryText .= "Total caption files: " . number_format($totalMetadataFiles, 0, '.', ',') . " (" . number_format($lastMetadataFiles, 0, '.', ',') . ")\n";
$summaryText .= "Total words in caption files: " . number_format($showWords/1000000000, 2, '.', ',') . " billion, " . number_format($showWords, 0, '.', ',') . " exactly (" . number_format($lastShowWords/1000000000, 2, '.', ',') . ")\n";
$summaryText .= "Total OCR files: " . number_format($totalOCRFiles, 0, '.', ',') . " (" . number_format($lastOCRFiles, 0, '.', ',') . ")\n";
$summaryText .= "Total TPT files: " . number_format($totalTPTFiles, 0, '.', ',') . " (" . number_format($lastTPTFiles, 0, '.', ',') . ")\n";
$summaryText .= "Total words in OCR files: " . number_format($ocrWords/1000000, 2, '.', ',') . " million, " . number_format($ocrWords, 0, '.', ',') . " exactly (" . number_format($lastOCRWords/1000000, 2, '.', ',') . ")\n";
$summaryText .= "Total words in TPT files: " . number_format($tptWords/1000000, 2, '.', ',') . " million, " . number_format($tptWords, 0, '.', ',') . " exactly (" . number_format($lastTPTWords/1000000, 2, '.', ',') . ")\n";
$summaryText .= "Total video files: " . number_format($totalVideoFiles, 0, '.', ',') . " (" . number_format($lastVideoFiles, 0, '.', ',') . ")\n";
$summaryText .= "Total thumbnail images: " . number_format($totalDuration/10, 0, '.', ',') . " (" . number_format($lastDuration/10, 0, '.', ',') . ")\n";
$summaryText .= "Storage used for core data: " . number_format($totalGigs/1000, 2, '.', ',') . " terabytes (" . number_format($lastGigs/1000, 2, '.', ',') . ")\n";

$summaryText .= "\n" . checkTiming() . "\n";

$headers = "Content-Type: text/plain; charset=utf-8";

mail('broadwell@library.ucla.edu, grappone@library.ucla.edu, steen@commstds.ucla.edu, mark.turner@case.edu, kai@ssc.ucla.edu', 'NewsScape weekly summary statistics as of ' . $prettyTimestamp, $summaryText, $headers);

#echo $summaryText;

?>
