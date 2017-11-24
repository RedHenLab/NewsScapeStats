<?php

//$querystring = $argv[1];
//$baseURL = 'http://localhost:8983/solr/tna/jobqueue?action=parseQuery&wt=json&query=';
$jobQueueURL = 'http://localhost:8983/solr/tna/jobqueue';

function randomString($length, $chars = null) {
  if (!$chars)
    $chars = '0123456789ABCDEF';
  $result = '';
  for ($i = 0; $i < $length; $i++) {
    $result .= $chars[rand(0, strlen($chars) - 1)];
  }
  return $result;
}

function solrQuery($url, $return = true, $timeout = 600) {

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, $return ? 1 : 0);
  curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
  $content = curl_exec($ch);
  curl_close($ch);
  return json_decode($content, true);
//  return $content;
}

function dispatchQuery($query) {

  global $jobQueueURL;

  $hash = randomString(8);

  $queryString = http_build_query(array(
    'action' => 'runJob',
    'export' => 'false',
    'fileFormat' => 'JSON',
    'hash' => $hash,
    'query' => $query,
    'useLimit' => 'true',
    'wt' => 'json',
  ));

  $response = solrQuery($jobQueueURL . '?' . $queryString, true, 5);

  $job_finished = false;

  while ($job_finished == false) {

    $response = solrQuery($jobQueueURL . '?' . http_build_query(array(
      'action' => 'getJobStatus',
      'hash' => $hash,
      'wt' => 'json',
    )));

    if ($response !== null && isset($response['jobs'][0])) {
      $job = $response['jobs'][0];
      if ($job['status'] == 'FINISHED') {
        $job_finished = true;
      }
    } else {
      sleep(1);
    }
  }

  $response2 = solrQuery($jobQueueURL . '?' . http_build_query(array(
    'action' => 'getJobProduct',
    'hash' => $hash,
    'wt' => 'raw',
    )));

  return $response2;

}

function getHits($querystring) {

  $result = dispatchQuery($querystring);

  foreach ($result as $info) {
    foreach ($info as $key=>$value) {
      if ($key == 'totalHits') {
        return $value;
   // if (($key == 'type') && ($value == 'tna.search.searchresponse.detailed.heading'))
      }
    }
  }
  return 0;
}

//$query = 'http://newsscape.library.ucla.edu/index.php?mode=job_run&q=&display_format=list&q_required_words=&q_or_words=&q_excluded_words=&q_near_distance=0%2FSEGMENT&q_near_words=&regex_mode=multi&limit=10&date_from=01%2F01%2F2005&date_to=02%2F17%2F2014&tz_filter=lbt&sort_by=datetime_desc&tz_sort=utc&group_by=&tz_group=utc&uuid=&filename=&network%5B%5D=CNN&network_count=48&network_series%5B%5D=&network_series_count=2095';

//$query = 'http://newsscape.library.ucla.edu/index.php?mode=job_run&q=&display_format=list&limit=10&date_from=01%2F01%2F2005&date_to=02%2F17%2F2014&tz_filter=lbt&sort_by=datetime_desc&tz_sort=utc&tz_group=utc&network%5B%5D=CNN&network_count=48&network_series%5B%5D=&network_series_count=2095';

//$query = 'http://newsscape.library.ucla.edu/index.php?mode=job_run&q=&display_format=list&q_required_words=&q_or_words=&q_excluded_words=&q_near_distance=0%2FSEGMENT&q_near_words=&regex_mode=multi&limit=10&date_from=01%2F03%2F2005&date_to=02%2F17%2F2014&tz_filter=lbt&sort_by=datetime_desc&tz_sort=utc&group_by=&tz_group=utc&uuid=&filename=&network%5B%5D=CNN&network_count=48&network_series%5B%5D=cnn%20cnn%20newsroom&network_series_count=2095';
/*
$querystring = 'date_from:"01/01/2005" date_to:"12/31/2005" display_format:list regex_mode:multi tz_filter:lbt tz_group:utc limit:10';
echo "Running query " . $querystring . "\n";
echo "total hits found: " . getHits($querystring) . "\n";
*/
?>
