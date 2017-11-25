# NewsScapeStats
Scripts for counting files and words in the NewsScape

The main script (UpdateNSStats.php) needs access to both the /tv tree and the production Solr index running on port 8983 of the localhost to work. The other PHP scripts are used as helpers to this main script.

UpdateNSStats.php uses the values in BaseNSStats.csv as a checkpoint, only counting files that were created after the lastTimestamp value in the file and computing the deltas from this checkpoint. After a successful run, the script produces a summary file, NSStats_NEWLASTTIMESTAMP, that can be copied over BaseNSStats.csv to update the checkpoint, though this is not done by default. If the BaseNSStats.csv file is not found, the script counts all files starting from the default earliest timestamp (set to January 1, 2004 in the code).

After running successfully, UpdateNSStats.php  produces an outptut file, NewsScapeBrowsing.json, that when placed in the proper public_html/ directory is then read daily by the Drupal site (tvnews.library.ucla.edu) and used to populate the network/year/show browsing interface. The script also produces a summary of the files, word counts, and any anomalies found in the caption files and emails it to the collection administrators.
