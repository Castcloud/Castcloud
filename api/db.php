<?php
date_default_timezone_set('UTC');
$current;
GLOBAL $current;
function cmp($a, $b) {
	return strtotime($GLOBALS['current'][$a]->feed["pubDate"]) < strtotime($GLOBALS['current'][$b]->feed["pubDate"]);
}

class DB {
	private $dbh;
	private $db_prefix;
	private $app;

	function __construct($dbh, $app) {
		$this->dbh = $dbh;
		$this->app = $app;
		$this->db_prefix = $GLOBALS['db_prefix'];
	}
	
	function get_casts() {
		include_once 'models/cast.php';
		$userid = $this->app->userid;

		$query = "SELECT
			cast.CastID AS id,
			subs.name,
			cast.url
			FROM 
			{$this->db_prefix}cast AS cast,
			{$this->db_prefix}subscription AS subs
			WHERE
			subs.userid=:userid 
			AND subs.CastID = cast.CastID";
		$inputs = array(":userid" => $userid);
		
		$sth = $this->dbh -> prepare($query);
		$sth->execute($inputs);
		
		$casts = $sth->fetchAll(PDO::FETCH_CLASS, "cast");
		
		foreach ($casts as &$cast) {
			$cast->feed = $this->get_cast($cast->id);
		}
		
		return $casts;
	}

	function get_cast($castid) {
		$sth = $this->dbh->query("SELECT Content FROM {$this->db_prefix}cast WHERE castid=$castid");
		if ($result = $sth->fetch()) {
			$cast = json_decode($result[0], true);
		}

		return $cast;
	}
	
	/**
	 * Cleans up all the users labels
	 * Generate first to get all the info we need
	 * Delete second since the information from the begunning is still fresh
	 * Add third since this affects the information we have generate irreversably
	 */
	function clean_labels(){
		// Lets gather up all the stuff
		$labels = $this->get_label();
		$subs = $this->get_casts();
		
		// Used in Delete:
		// Anything inside any labels that does not actually exist
		$removefromlabels = array();
		
		/*
		 * GENERATE
		 */
		// All the CastIDs from the subscriptions 
		$allcastsinsubs = array();
		foreach ($subs as $cast) {
			$allcastsinsubs[] = $cast->id;
		}
		// All the true LabelIDs
		$alllabelids = array();
		// All the LabelIDs from root
		$labelsinroot = array();
		// All the casts that is in any label
		$allcastsinlabel = array();
		// Look throug all labels to fill up the arrays
		foreach ($labels as $label) {
			// All the individual enteries inside the label
			$content = superexplode($label->content);
			foreach ($content as $contentitem) {
				// If it is a cast, put them in the array
				if (startsWith($contentitem, "cast/")){
					if (strlen(contentAfter($contentitem, "cast/")) >= 1){
						$allcastsinlabel[] = contentAfter($contentitem, "cast/");
					}
				}
				
				// If we are in root and we are finding labels, add them to the array
				if($label->root && startsWith($contentitem, "label/")){
					if (strlen(contentAfter($contentitem, "label/")) >= 1){
						$labelsinroot[] = contentAfter($contentitem, "label/");
					}
				}
				
				// If it is not a label and not a cast. Lets flag it for deletion
				if ((!startsWith($contentitem, "label/")  && !startsWith($contentitem, "cast/")) ||  $contentitem == "label/" || $contentitem == "cast/" ){
					$removefromlabels[] = $contentitem;
				}
			}
			// Take note of all LabelIDs that are not root
			if(!$label->root){
				$alllabelids[] = $label->id;
			}
		}
		
		/*
		 * DELETE
		 */
		// Casts that are inside a label but not subscribed too
		$nonexsistingcasts = array_diff($allcastsinlabel,$allcastsinsubs);
		foreach ($nonexsistingcasts as $castid) {
			$removefromlabels[] = "cast/" . $castid;
		}
		// Labels that are inside root but does not exsist
		$nonexsistinglables = array_diff($labelsinroot,$alllabelids);
		foreach ($nonexsistinglables as $labelid) {
			$removefromlabels[] = "label/" . $labelid;
		}
		
		// No need to bother the database unless there are actuall changes
		if (!empty($removefromlabels)){
			// Now its time to rewrite all the labels while removing nonexsisten stuff
			$query = "UPDATE {$this->db_prefix}label
				SET content = :content
				WHERE labelid = :id
				AND userid = :userid";
			$sth = $this->dbh -> prepare($query);
			$userid = $this->app->userid;
			foreach ($labels as $label) {
				$content = $label->content;
				
				// Remove all unwanted strings
				foreach ($removefromlabels as $removee) {
					$content = str_replace($removee, "", $content);
				}
				
				// Make sure we have the correct blend of , and values
				$content = implode(",",superexplode($content));
				
				$inputs = array();
				$inputs[":content"] = $content;
				$inputs[":id"] = $label->id;
				$inputs[":userid"] = $userid;
				
				$sth -> execute($inputs);
			}
		}
		
		/*
		 * ADD
		 */
		// What root is missing
		$addtoroot = "";
		// Lets find casts that are not in any label
		$castsnotinlabels = array_diff($allcastsinsubs,$allcastsinlabel);
		if (!empty($castsnotinlabels)){
			$addtoroot .= "cast/" . implode(",cast/", $castsnotinlabels);
		}
		// Lets find labels that are not in root
		$labelssnotinrootlabel = array_diff($alllabelids,$labelsinroot);
		if (!empty($labelssnotinrootlabel)){
			if($addtoroot != ""){
				$addtoroot .= ",";
			}
			$addtoroot .= "label/" . implode(",label/", $labelssnotinrootlabel);
		}
		if($addtoroot != ""){
			// Put them into root
			$this->add_to_label_root($addtoroot);
		}
	}
	
	function get_label($name = null, $labelid = null) {
		include_once 'models/label.php';
		$userid = $this->app->userid;

		$query = "SELECT
			label.LabelID AS id,
			label.name,
			label.content,
			label.expanded
			FROM 
			{$this->db_prefix}label AS label
			WHERE
			label.userid = :userid";
		$inputs = array(":userid" => $userid);
		
		if ($name != null){
			$query .= " AND label.name = :name";
			$inputs[":name"] = $name;
		}
		
		if ($labelid != null){
			$query .= " AND label.labelid = :labelid";
			$inputs[":labelid"] = $labelid;
		}
		
		$sth = $this->dbh -> prepare($query);
		$sth->execute($inputs);
		
		$label = $sth->fetchAll(PDO::FETCH_CLASS, "label");
		
		return $label;
	}

	function add_to_label_root($value){
		$this->add_to_label($value, "root");
	}
	
	function add_to_label($value, $labelname){
		$userid = $this->app->userid;
		
		$label = $this->get_label($labelname);
		if(empty($label)){
			$sth = $this->dbh -> prepare("INSERT INTO {$this->db_prefix}label
				(userid, name, content, expanded) 
				VALUES($userid, :labelname, :content, TRUE)");
			$sth -> bindParam(":content",$value);
			$sth -> bindParam(":labelname",$labelname);
			$sth -> execute();
			return;
		}
		
		$label = $label[0];
		
		$label->content .= "," . $value;
		
		$sth = $this->dbh -> prepare("UPDATE {$this->db_prefix}label
			SET content = :content
			WHERE LabelID = :id");
		$sth -> bindParam(":content",$label->content);
		$sth -> bindParam(":id",$label->id);
		$sth -> execute();
	}

	function get_episodes($castid = null, $labelid = null, $episode = null, $since = null, $exclude = "70") {
		include_once 'models/episode.php';
		include_once 'models/event.php';
		
		$label = null;
		$userid = $this->app->userid;
		
		if ($labelid != null){
			$label = $this->get_label(null, $labelid);
			if($label < 1){
				// Label most likely unvalid labelid
				return array();
			}
			$label = superexplode($label[0]->content);
		}
		
		$exclude = superexplode($exclude);
		$inputs = array();
		
		$query = "SELECT
			feed.CastID,
			feed.EpisodeID,
			feed.Content,
			event.type,
			event.positionts,
			event.clientts,
			event.concurrentorder,
			client.Name AS clientname,
			cauth.clientdescription,
			cauth.UUID AS clientinstanceid
			FROM {$this->db_prefix}episode AS feed
			LEFT JOIN 
				{$this->db_prefix}subscription AS subs
				ON subs.CastID = feed.CastID
				AND subs.UserID = :userid
			LEFT JOIN 
				{$this->db_prefix}event AS event
				ON feed.EpisodeID = event.EpisodeID
				AND event.UserID = :userid
				AND event.clientts = (
					SELECT MAX(ev2.clientts)
					FROM {$this->db_prefix}event AS ev2
					WHERE ev2.EpisodeID = event.EpisodeID
					AND ev2.UserID = :ev2userid
         		)
         		AND event.ConcurrentOrder = (
					SELECT MAX(ev3.ConcurrentOrder)
					FROM {$this->db_prefix}event AS ev3
					WHERE ev3.EpisodeID = event.EpisodeID
					AND ev3.UserID = :ev3userid
					AND event.clientts = ev3.clientts
         		)
         	LEFT JOIN 
				{$this->db_prefix}clientauthorization AS cauth
				ON cauth.UniqueClientID = event.UniqueClientID
			LEFT JOIN 
				{$this->db_prefix}client AS client
				ON client.ClientID = cauth.ClientID
			WHERE subs.CastID IS NOT NULL";
		$inputs[":userid"] = $userid;
		$inputs[":ev2userid"] = $userid;
		$inputs[":ev3userid"] = $userid;
		
		if ($label != null){
			$query .= " AND (";
			for ($i = 0; $i < count($label); $i++) {
				if (startsWith($label[$i], "cast/")){
					if ($i != 0){
						$query .= " OR";
					}
					$query .= " feed.castid = :castid" . $i;
					$inputs[":castid" . $i] = substr($label[$i], strlen("cast/"));
				}
			} 
			$query .= " )";
		}
		
		if (!empty($exclude)) {
			for ($i = 0; $i < count($exclude); $i++) {
				$query .= " AND ( event.TYPE != :exclude" . $i . " OR event.TYPE IS NULL)";
				$inputs[":exclude" . $i] = $exclude[$i];
			}
		}

		if ($since != null) {
			$query.=" AND feed.crawlts > :since";
			$inputs[":since"] = $since;
		}

		if ($castid != null) {
			$query.=" AND feed.CastID = :castid";
			$inputs[":castid"] = $castid;
		}
		
		if ($episode != null) {
			$query.=" AND feed.EpisodeID = :itemid";
			$inputs[":itemid"] = $episode;
		}
		
		$query.= " GROUP BY feed.episodeid";
		
		$sth = $this->dbh->prepare($query);
		$sth->execute($inputs);
		
		$episodes = array();
		foreach ($sth as $row) {
			$lastevent = null;
			if ($row["type"] != null){
				$lastevent = new event($row['type'], $row['EpisodeID'], $row['positionts'], $row['clientts'], $row['concurrentorder'], $row['clientname'], $row['clientdescription'], $row['clientinstanceid']);
			}
			$episode = new Episode($row['EpisodeID'], $row['CastID'], $lastevent, json_decode($row['Content']));
			array_push($episodes, $episode);
		}

		return $episodes;
	}	

	function get_events($itemid, $since, $limit = null, $exclude = null, $exclude_self = null) {
		include_once 'models/event.php';
		$userid = $this->app->userid;
		
		$exclude = superexplode($exclude);

		$query = "SELECT
			event.type,
			event.episodeid AS episodeid,
			event.positionts,
			event.clientts,
			event.concurrentorder, 
			client.name AS clientname,
			clientauthorization.clientdescription
			FROM 
			{$this->db_prefix}event AS event,
			{$this->db_prefix}clientauthorization AS clientauthorization,
			{$this->db_prefix}client AS client
			WHERE
			event.userid=:userid 
			AND event.UniqueClientID = clientauthorization.UniqueClientID
			AND clientauthorization.ClientID = client.ClientID";
		$inputs = array(":userid" => $userid);

		if ($itemid != null) {
			$query.=" AND event.episodeid=:itemid";
			$inputs[":itemid"] = $itemid;
		}
		if ($since != null) {
			$query.=" AND event.receivedts >= :since";
			$inputs[":since"] = $since;
		}
		if ($exclude_self) {
			$query.=" AND event.UniqueClientID != :self";
			$inputs[":self"] = $this->app->uniqueclientid;
		}
		
		if (!empty($exclude) && !$since && $itemid === null){
			$eqi = array();
			$eq = "SELECT ev2.EpisodeID
				FROM {$this->db_prefix}event AS ev2
				WHERE ev2.UserID = :ev2userid
				AND ev2.clientts = (
					SELECT MAX(clientts)
					FROM {$this->db_prefix}event AS ev3
					WHERE ev3.UserID = :ev3userid
					AND ev3.EpisodeID = ev2.EpisodeID
				)
				AND ( ev2.ConcurrentOrder = (
						SELECT MAX(ConcurrentOrder)
						FROM {$this->db_prefix}event AS ev4
						WHERE ev4.UserID = :ev4userid
						AND ev4.EpisodeID = ev2.EpisodeID
						AND ev4.clientts = ev2.clientts
					) OR ev2.ConcurrentOrder IS NULL
				)";
			$eqi[":ev2userid"] = $userid;
			$eqi[":ev3userid"] = $userid;
			$eqi[":ev4userid"] = $userid;
					
			for ($i = 0; $i < count($exclude); $i++) {
				$eq .= " AND ev2.TYPE != :exclude" . $i;
				$eqi[":exclude" . $i] = $exclude[$i];
			}
			
			$eq .= " GROUP BY ev2.EpisodeID";
			
			$sth = $this->dbh -> prepare($eq);
			$sth->execute($eqi);
			
			$exclude = array();
			$res = $sth->fetchAll(PDO::FETCH_ASSOC);
			foreach ($res as $resent) {
				$exclude[] = $resent["EpisodeID"];
			}
			
			$query .= " AND event.EpisodeID IN (" . implode(",", $exclude) . ")";
		}

		$query.= " ORDER BY
			event.clientts DESC,
			event.concurrentorder DESC";
		
		if ($limit != null) {
			$query.=" LIMIT :limit";
			$inputs[":limit"] = $limit;
		}
		
		$this->dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES, FALSE);
		$sth = $this->dbh -> prepare($query);
		$sth->execute($inputs);
		
		if ($sth) {
			if ($limit == "1" && $sth->rowCount() == 1){
				$events = $sth->fetchAll(PDO::FETCH_CLASS, "event");
				return $events[0];
			} elseif ($limit == "1" && $sth->rowCount() < 1){
				return null;
			} else {
				return $sth->fetchAll(PDO::FETCH_CLASS, "event");
			}
		}
	}
	
	function get_settings() {
		include_once 'models/setting.php';
		$userid = $this->app->userid;
		$clientid = $this->app->clientid;

		$query = "SELECT
			setting.settingid,
			setting.setting,
			setting.value,
			setting.ClientID IS NOT NULL AS clientspecific
			FROM 
			{$this->db_prefix}setting AS setting
			WHERE
			setting.userid=:userid
			AND (setting.ClientID = :clientid
			OR setting.ClientID IS NULL)";
		$inputs = array(":userid" => $userid,
			":clientid" => $clientid);

		
		$sth = $this->dbh -> prepare($query);
		$sth->execute($inputs);

		if ($sth) {
			return $sth->fetchAll(PDO::FETCH_CLASS, "setting");
		}
	}

	private $subscribe_to_these;
	private $urls;

	function import_opml($opml) {
		include_once 'crawler.php';
		$this->subscribe_to_these = array();
		$this->urls = array();
		$this->opml_next($opml);
		//crawl_urls($this->urls);

		foreach ($this->urls as $url) {
			$sth = $this->dbh->query("SELECT * FROM {$this->db_prefix}cast WHERE url='$url'");
			$cast = $sth->fetch();
			if (!$cast) {
				$this->dbh->exec("INSERT INTO {$this->db_prefix}cast (url, crawlts) VALUES('$url', 0)");
			}
		}

		foreach ($this->subscribe_to_these as $sub) {
			$this->subscribe_to($sub["url"], $sub["title"], $sub["label"]);
		}
	}
	
	function opml_next($opml, $label = null){
		$PERMALABEL = $label;
		foreach ($opml->outline as $outline) {
			
			$title = null;
			$label = $PERMALABEL;
			
			if (isset($outline["title"])){
				$title = $outline["title"];
			} else if (isset($outline["text"])){
				$title = $outline["text"];
			}
			
			if($outline["xmlUrl"] == null){
				if ($label == null){
					$label = $title;
				}
				$this->opml_next($outline, $label);
			} else {
				if ($label != null){
					$label = "label/" . $label;
				}
				array_push($this->subscribe_to_these, array("url" => (string)$outline["xmlUrl"], 
					"title" => (string)$title, "label" => (string)$label));
				array_push($this->urls, (string)$outline["xmlUrl"]);
			}
		}
	}
	
	function subscribe_to($feedurl, $name = null, $label = null, $crawl = false){
		$userid = $this->app -> userid;

		if ($crawl) {
			$castid = crawl($feedurl);
		}
		else {
			$sth = $this->dbh->query("SELECT CastID FROM {$this->db_prefix}cast WHERE url='$feedurl'");
			$castid = $sth->fetch(PDO::FETCH_ASSOC)['CastID'];
		}		
		
		if ($label == null){
			$label = "root";
		}
		
		if ($name == null){
			$castinfo = $this->get_cast($castid);
			if (array_key_exists("title",$castinfo)){
				$name = $castinfo["title"];
			} else {
				$name = $feedurl;
			}
		}

		$sth = $this->dbh -> query("SELECT * FROM {$this->db_prefix}subscription WHERE castid=$castid AND userid=$userid");
		if ($sth && $sth -> rowCount() < 1) {
			$sth = $this->dbh -> prepare("INSERT INTO {$this->db_prefix}subscription (castid, name, userid) 
			VALUES($castid, :name, $userid)");
			$sth -> bindParam(":name",$name);
			$sth -> execute();
			
			$this->add_to_label("cast/" . $castid, $label);
		}
	}
	
	function del_label($labelid){
		$userid = $this->app->userid;
		$sth = $this->dbh -> prepare("DELETE FROM {$this->db_prefix}label WHERE LabelID=:labelid AND userid=:userid");
		$sth->bindParam(":labelid",$labelid);
		$sth->bindParam(":userid",$userid);
		$sth->execute();
	}
}
?>