<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header('Access-Control-Allow-Headers: If-None-Match, Authorization');
header('Access-Control-Expose-Headers: Etag');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
	exit;
}

require '../lib/Slim/Slim.php';
require '../lib/password_compat/password.php';
\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();
GLOBAL $app;

include 'cc-settings.php';
GLOBAL $db_prefix, $dbh;

include 'authmiddleware.php';
include 'util.php';
include 'crawler.php';
include 'login.php';
include 'db.php';

$app->db = new DB($dbh, $app);

$app->add(new AuthMiddleware());

/**
 * @SWG\Resource(
 * 	apiVersion="1.0.0",
 * 	swaggerVersion="1.2",
 * 	resourcePath="/account",
 * 	description="Account related operations",
 * 	produces="['application/json']"
 * )
 */
$app -> group('/account', function() use ($app) {
	$app -> post('/login', function() use ($app) {
		post_login($app);
	});

	/**
	 * @SWG\Api(
	 * 	path="/account/ping",
	 * 	description="Tests if token works",
	 * 	@SWG\Operation(
	 * 		method="GET",
	 * 		nickname="Ping",
	 * 		summary="Test token",
	 * 		type="void",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\ResponseMessage(
	 * 			code=200,
	 * 			message="All ok"
	 * 		),
	 * 		@SWG\ResponseMessage(
	 * 			code=400,
	 * 			message="Bad token"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> get('/ping', function() {});

	/**
	 * @SWG\Api(
	 * 	path="/account/settings",
	 * 	description="Settings",
	 * 	@SWG\Operation(
	 * 		method="GET",
	 * 		nickname="Get Settings",
	 * 		summary="Get Settings",
	 * 		type="array",
	 * 		items="$ref:setting",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\ResponseMessage(
	 * 			code=400,
	 * 			message="Bad token"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> get('/settings', function() use ($app) {
		json($app->db->get_settings(), true);
	});

	/**
	 * @SWG\Api(
	 * 	path="/account/settings",
	 * 	description="Settings",
	 * 	@SWG\Operation(
	 * 		method="POST",
	 * 		nickname="Set Settings",
	 * 		summary="Set Settings",
	 * 		type="void",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="json",
	 * 			description="New or modified settings",
	 * 			paramType="form",
	 * 			required=true,
	 * 			type="array",
	 * 			items="$ref:setting"
	 * 		),
	 * 		@SWG\ResponseMessage(
	 * 			code=400,
	 * 			message="Bad token"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> post('/settings', function() use ($app) {
		$settings = json_decode($app->request->params("json"));
		$userid = $app->userid;
		
		$dbh = $GLOBALS['dbh'];
		$db_prefix = $GLOBALS['db_prefix'];
		foreach($settings as $setting) {
			$ClientID = null;
			
			if($setting->clientspecific == "true"){
				$ClientID = $app->clientid;
			}
			
			$sth = $dbh->prepare ("SELECT * FROM {$db_prefix}setting
				WHERE userid=:userid
				AND setting=:setting
				AND ClientID = :ClientID");
			$sth->bindParam(':userid', $userid);
			$sth->bindParam(':setting', $setting->setting);
			$sth->bindParam(':ClientID', $ClientID);
			$sth->execute();
			
			if ($sth && $sth->rowCount() > 0) {
				$sth = $dbh->prepare ("UPDATE {$db_prefix}setting
					SET value=:value
					WHERE userid=:userid
					AND setting=:setting
					AND ClientID =:ClientID");
				$sth->bindParam(':userid', $userid);
				$sth->bindParam(':setting', $setting->setting);
				$sth->bindParam(':value', $setting->value);
				$sth->bindParam(':ClientID', $ClientID);
				$sth->execute();		
			}
			else {
				$sth = $dbh->prepare ("INSERT INTO {$db_prefix}setting (userid, setting, value, ClientID)
					VALUES(:userid, :setting, :value, :ClientID)");
				$sth->bindParam(':userid', $userid);
				$sth->bindParam(':setting', $setting->setting);
				$sth->bindParam(':value', $setting->value);
				$sth->bindParam(':ClientID', $ClientID);
				$sth->execute();
			}
		}
	});
	
	/**
	 * @SWG\Api(
	 * 	path="/account/settings/{settingid}",
	 * 	description="Settings",
	 * 	@SWG\Operation(
	 * 		method="DELETE",
	 * 		nickname="Delete Setting",
	 * 		summary="Delete Setting",
	 * 		type="void",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="settingid",
	 * 			description="ID of the setting that is to be removed",
	 * 			paramType="path",
	 * 			required=true,
	 * 			type="integer"
	 * 		),
	 * 		@SWG\ResponseMessage(
	 * 			code=400,
	 * 			message="Bad token"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> delete('/settings/:settingid', function($settingid) use ($app) {
		$userid = $app->userid;
		$db_prefix = $GLOBALS['db_prefix'];
		$dbh = $GLOBALS['dbh'];
		$sth = $dbh -> prepare("DELETE FROM {$db_prefix}setting WHERE settingid=:settingid AND userid=:userid");
		$sth->bindParam(":settingid",$settingid);
		$sth->bindParam(":userid",$userid);
		$sth->execute();
	});

	$app -> get('/takeout', function() use ($app) {
		json(array("Not" => "Implemented"));
	});

});

/**
 * @SWG\Resource(
 *   apiVersion="1.0.0",
 *   swaggerVersion="1.2",
 *   resourcePath="/library",
 *   description="Library related operations",
 *   produces="['application/json']"
 * )
 */
$app -> group('/library', function() use ($app) {

	/**
	 * @SWG\Api(
	 * 	path="/library/newepisodes",
	 * 	description="Get new episodes",
	 * 	@SWG\Operation(
	 * 		method="GET",
	 * 		nickname="Get new episodes",
	 * 		summary="Get new episodes",
     * 		type="newepisodesresult",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="since",
	 * 			description="timestamp of last call",
	 * 			paramType="query",
	 * 			required=false,
	 * 			type="integer"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="exclude",
	 * 			description="Exclude episode with latest event type. Comma separated event type ids to exclude. Default: 70",
	 * 			paramType="query",
	 * 			required=false,
	 * 			type="integer"
	 * 		),
	 * 		@SWG\ResponseMessage(
	 * 			code=400,
	 * 			message="Bad token"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> get('/newepisodes', function() use ($app) {
		include_once 'models/newepisodesresult.php';
		
		$exclude = $app -> request -> params('exclude');
		$episodes = array();
		
		if ($exclude != null){
			$episodes = $app->db->get_episodes(null, null, null, $app->request->params('since'), $exclude);
		} else {
			$episodes = $app->db->get_episodes(null, null, null, $app->request->params('since'));
		}
		
		json(new newepisodesresult($episodes));
	});

	/**
	 * @SWG\Api(
	 * 	path="/library/episodes/{castid}",
	 * 	description="Get all episodes of a cast",
	 * 	@SWG\Operation(
	 * 		method="GET",
	 * 		nickname="Get all episodes of a cast",
	 * 		summary="Get all episodes of a cast",
     * 		type="array",
     * 		items="$ref:episode",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="castid",
	 * 			description="The casts id",
	 * 			paramType="path",
	 * 			required=true,
	 * 			type="integer"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="exclude",
	 * 			description="Exclude episode with latest event type. Comma separated event type ids to exclude. Default: 70",
	 * 			paramType="query",
	 * 			required=false,
	 * 			type="integer"
	 * 		),
	 * 		@SWG\ResponseMessage(
	 * 			code=400,
	 * 			message="Bad token"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> get('/episodes/:castid', function($castid) use ($app) {
		$exclude = $app->request->params('exclude');
		json($app->db->get_episodes($castid, null, null, null, $exclude), true);
	});
	
		/**
	 * @SWG\Api(
	 * 	path="/library/episode/{episodeid}",
	 * 	description="Get a spesific episode",
	 * 	@SWG\Operation(
	 * 		method="GET",
	 * 		nickname="Get a spesific episode",
	 * 		summary="Get a spesific episode",
     * 		type="array",
     * 		items="$ref:episode",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="episodeid",
	 * 			description="The episodes id",
	 * 			paramType="path",
	 * 			required=true,
	 * 			type="integer"
	 * 		),
	 * 		@SWG\ResponseMessage(
	 * 			code=400,
	 * 			message="Bad token"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> get('/episode/:episodeid', function($episodeid) use ($app) {
		$episode = $app->db->get_episodes(null, null, $episodeid, null, "");
		if (!empty($episode)){
			json($episode[0], true);
		}
	});
	
	/**
	 * @SWG\Api(
	 * 	path="/library/episodes/label/{label}",
	 * 	description="Get all episodes of a label",
	 * 	@SWG\Operation(
	 * 		method="GET",
	 * 		nickname="Get episodes for label",
	 * 		summary="Get episodes for label",
     * 		type="array",
     * 		items="$ref:episode",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="label",
	 * 			description="The labelid",
	 * 			paramType="path",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="exclude",
	 * 			description="Comma separated event ids to exclude. Default: 70",
	 * 			paramType="query",
	 * 			required=false,
	 * 			type="integer"
	 * 		),
	 * 		@SWG\ResponseMessage(
	 * 			code=400,
	 * 			message="Bad token"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> get('/episodes/label/:label', function($label) use ($app) {
		$exclude = $app -> request -> params('exclude');
		if ($exclude != null){
			json($app->db->get_episodes(null, $label, null, null, $exclude), true);
		} else {
			json($app->db->get_episodes(null, $label), true);
		}
	});

	/**
	 * @SWG\Api(
	 * 	path="/library/casts",
	 * 	description="Get users subcriptions",
	 * 	@SWG\Operation(
	 * 		method="GET",
	 * 		nickname="Get users subcriptions",
	 * 		summary="Get users subcriptions",
	 * 		type="array",
	 * 		items="$ref:cast",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\ResponseMessage(
	 * 			code=400,
	 * 			message="Bad token"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> get('/casts', function() use ($app) {
		json($app->db->get_casts(), true);
	});
	
	/**
	 * @SWG\Api(
	 * 	path="/library/casts.opml",
	 * 	description="Get users subcriptions",
	 * 	@SWG\Operation(
	 * 		method="GET",
	 * 		nickname="Get users subcriptions as opml",
	 * 		summary="Get users subcriptions as opml",
	 * 		type="array",
	 * 		produces="['text/x-opml']",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\ResponseMessage(
	 * 			code=400,
	 * 			message="Bad token"
	 * 		)
	 * 	)
	 * )
	 */
	$app->get('/casts.opml', function() use($app) {
		opml($app->db->get_label(), $app->db->get_casts());
	});
	
	
	/**
	 * @SWG\Api(
	 * 	path="/library/casts.opml",
	 * 	description="Adds content of opml to users subscriptions",
	 * 	@SWG\Operation(
	 * 		method="POST",
	 * 		nickname="Adds content of opml to users subscriptions",
	 * 		summary="Adds content of opml to users subscriptions",
     * 		type="void",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="opml",
	 * 			description="Content of a regular opml file",
	 * 			paramType="form",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\ResponseMessage(
	 * 			code=400,
	 * 			message="Bad token"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> post('/casts.opml', function() use ($app) {
		set_time_limit(0);
		$opml = $app->request->params('opml');
		$opml = simplexml_load_string($opml);
		$app->db->import_opml($opml->body);
	});

	/**
	 * @SWG\Api(
	 * 	path="/library/casts",
	 * 	description="Subcribe to a cast",
	 * 	@SWG\Operation(
	 * 		method="POST",
	 * 		nickname="Subcribe to a cast",
	 * 		summary="Subcribe to a cast",
	 * 		type="void",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="feedurl",
	 * 			description="URL of podcast feed",
	 * 			paramType="form",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="name",
	 * 			description="The displayname for the cast",
	 * 			paramType="form",
	 * 			required=false,
	 * 			type="string"
	 * 		),
	 * 		@SWG\ResponseMessage(
	 * 			code=400,
	 * 			message="Bad token"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> post('/casts', function() use ($app) {
		$feedurl = $app -> request -> params('feedurl');
		$name = $app -> request -> params('name');
		$app->db->subscribe_to($feedurl, $name, null, true);
	});
	
	/**
	 * @SWG\Api(
	 * 	path="/library/casts/{id}",
	 * 	description="Edit a subcription",
	 * 	@SWG\Operation(
	 * 		method="PUT",
	 * 		nickname="Edit a subcription",
	 * 		summary="Edit a subcription",
	 * 		type="void",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="id",
	 * 			description="The casts id",
	 * 			paramType="path",
	 * 			required=true,
	 * 			type="integer"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="name",
	 * 			description="The feeds display name",
	 * 			paramType="form",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\ResponseMessage(
	 * 			code=400,
	 * 			message="Bad token"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> put('/casts/:id', function($id) use ($app) {
		$name = $app -> request -> params('name');
		$dbh = $GLOBALS['dbh'];
		$db_prefix = $GLOBALS['db_prefix'];
		$sth = $dbh -> prepare("UPDATE {$db_prefix}subscription
			SET name=:name
			WHERE CastID=:id");
		$sth -> bindParam(":name",$name);
		$sth -> bindParam(":id",$id);
		$sth -> execute();
	});

	/**
	 * @SWG\Api(
	 * 	path="/library/casts/{id}",
	 * 	description="Unsubscribe from a cast",
	 * 	@SWG\Operation(
	 * 		method="DELETE",
	 * 		nickname="Unsubscribe from a cast",
	 * 		summary="Unsubscribe from a cast",
     * 		type="void",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="id",
	 * 			description="The casts id",
	 * 			paramType="path",
	 * 			required=true,
	 * 			type="integer"
	 * 		),
	 * 		@SWG\ResponseMessage(
	 * 			code=400,
	 * 			message="Bad token"
	 * 		)
	 * 	)
	 * )
	 */
	$app->delete('/casts/:id', function($id) use($app) {
		$userid = $app->userid;
		$db_prefix = $GLOBALS['db_prefix'];
		$GLOBALS['dbh']->exec("DELETE FROM {$db_prefix}subscription WHERE castid=$id AND userid=$userid");
	});

	/**
	 * @SWG\Api(
	 * 	path="/library/events",
	 * 	description="Get events",
	 * 	@SWG\Operation(
	 * 		method="GET",
	 * 		nickname="Get users tags",
	 * 		summary="Get users tags",
     * 		type="array",
     * 		items="$ref:event",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="since",
	 * 			description="timestamp of last call",
	 * 			paramType="query",
	 * 			required=false,
	 * 			type="integer"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="episodeid",
	 * 			description="filter by episodeid",
	 * 			paramType="query",
	 * 			required=false,
	 * 			type="integer"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="exclude",
	 * 			description="Comma separated event type ids to exclude. Exclude events for episodes where the most recent event has this type. Default: 70. Ignored if since or episodeid parameters are set.",
	 * 			paramType="query",
	 * 			required=false,
	 * 			type="integer"
	 * 		),
	 * 		@SWG\ResponseMessage(
	 * 			code=400,
	 * 			message="Bad token"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> get('/events', function() use ($app) {
		include_once 'models/eventsresult.php';
		
		$episodeid = $app->request->params('episodeid');
		$since = $app->request->params('since');
		$limit = $app->request->params('limit');
		$exclude = $app->request->params('exclude');
		$exclude_self = $app->request->params('exclude_self') === 'true' ? true : false;
		
		if ($exclude != null){
			json(new eventsresult($app->db->get_events($episodeid, $since, $limit, $exclude, $exclude_self)));
		} else {
			json(new eventsresult($app->db->get_events($episodeid, $since, $limit, "70", $exclude_self)));
		}
	});

	/**
	 * @SWG\Api(
	 * 	path="/library/events",
	 * 	description="Add events",
	 * 	@SWG\Operation(
	 * 		method="POST",
	 * 		nickname="Add events",
	 * 		summary="Add events",
	 * 		type="void",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="json",
	 * 			description="New events",
	 * 			paramType="form",
	 * 			required=true,
	 * 			type="array",
	 * 			items="$ref:event"
	 * 		),
	 * 		@SWG\ResponseMessage(
	 * 			code=400,
	 * 			message="Bad token"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> post('/events', function() use ($app) {
		$db_prefix = $GLOBALS['db_prefix'];
		$receivedts = time();
				
		$json = json_decode($app->request->params('json'));

		foreach ($json as $event) {
			$sth = $GLOBALS['dbh']->prepare("INSERT INTO {$db_prefix}event (userid, type, episodeid, positionts, concurrentorder, clientts, receivedts, uniqueclientid) VALUES($app->userid, $event->type, $event->episodeid, $event->positionts, :concurrentorder, $event->clientts, $receivedts, $app->uniqueclientid)");
			$sth->bindParam(":concurrentorder", $event->concurrentorder, PDO::PARAM_INT);
			$sth->execute();
		}
	});

	/**
	 * @SWG\Api(
	 * 	path="/library/labels",
	 * 	description="Get users labels",
	 * 	@SWG\Operation(
	 * 		method="GET",
	 * 		nickname="Get users labels",
	 * 		summary="Get users labels",
	 * 		type="array",
	 * 		@SWG\Items("string"),
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\ResponseMessage(
	 * 			code=400,
	 * 			message="Bad token"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> get('/labels', function() use ($app) {
		$app->db->clean_labels();
		json($app->db->get_label(), true);
	});
	
	/**
	 * @SWG\Api(
	 * 	path="/library/labels",
	 * 	description="Create a new label",
	 * 	@SWG\Operation(
	 * 		method="POST",
	 * 		nickname="Create a new label",
	 * 		summary="Create a new label",
	 * 		type="void",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="name",
	 * 			description="The name of the new label. Mimimum 1 character",
	 * 			paramType="form",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="content",
	 * 			description="The content of the label. See GET label for formatting",
	 * 			paramType="form",
	 * 			required=false,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="expanded",
	 * 			description="Wether or not the label is expanded in the client UI. Default false. root is always true.",
	 * 			paramType="form",
	 * 			required=false,
	 * 			type="string"
	 * 		),
	 * 		@SWG\ResponseMessage(
	 * 			code=400,
	 * 			message="Bad token"
	 * 		),
	 * 		@SWG\ResponseMessage(
	 * 			code=400,
	 * 			message="Name to short"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> post('/labels', function() use ($app) {
		$name = $app -> request -> params('name');
		$content = $app -> request -> params('content');
		$expanded = $app -> request -> params('expanded');
		
		if (!(strpos($name,"label/") === 0)){
			$name = "label/" . $name; 
		}
		
		if (strlen($name) < (strlen("label/") + 1)){
			$app->halt(400, "Name to short");
		}
		
		$expanded = $expanded != "false";
		
		$dbh = $GLOBALS['dbh'];
		$db_prefix = $GLOBALS['db_prefix'];
		$userid = $app -> userid;
		
		$sth = $dbh -> prepare("SELECT count(*)
			FROM {$db_prefix}label AS label
			WHERE
			label.name = :name
			AND label.userid=$userid");
		$sth -> bindParam(":name",$name);
		$sth -> execute();
		
		$result = $sth -> fetchAll();
		
		if ($result["0"]["0"] < 1){
			$sth = $dbh -> prepare("INSERT INTO {$db_prefix}label
				(userid, name, content, expanded) 
				VALUES($userid, :name, :content, :expanded)");
			$sth -> bindParam(":name",$name);
			$sth -> bindParam(":content",$content);
			$sth -> bindParam(":expanded",$expanded);
			$sth -> execute();
			log_db_errors($sth);
			$labelid = $dbh->lastInsertId();
			
			$app->db->add_to_label_root("label/" . $labelid);
		} else {
			$app->halt(400, "Exsisting label");
		}
	});
	
	/**
	 * @SWG\Api(
	 * 	path="/library/labels/{id}",
	 * 	description="Edit a label",
	 * 	@SWG\Operation(
	 * 		method="PUT",
	 * 		nickname="Edit a label",
	 * 		summary="Edit a label",
	 * 		type="void",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="id",
	 * 			description="The id of the label you want to edit",
	 * 			paramType="path",
	 * 			required=true,
	 * 			type="integer"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="name",
	 * 			description="The name of the new label",
	 * 			paramType="form",
	 * 			required=false,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="content",
	 * 			description="The content of the label. See GET label for formatting",
	 * 			paramType="form",
	 * 			required=false,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="expanded",
	 * 			description="Wether or not the label is expanded in the client UI. Default false. root is always true.",
	 * 			paramType="form",
	 * 			required=false,
	 * 			type="string"
	 * 		),
	 * 		@SWG\ResponseMessage(
	 * 			code=400,
	 * 			message="Bad token"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> put('/labels/:id', function($id) use ($app) {
		$name = $app -> request -> params('name');
		$content = $app -> request -> params('content');
		$expanded = $app -> request -> params('expanded');
		
		$dbh = $GLOBALS['dbh'];
		$db_prefix = $GLOBALS['db_prefix'];
		$userid = $app -> userid;
		
		$sth = $dbh -> prepare("SELECT count(*)
			FROM {$db_prefix}label AS label
			WHERE
			label.labelid = :id
			AND label.name=\"root\"");
		$sth -> bindParam(":id",$id);
		$sth -> execute();
		$result = $sth -> fetchAll();
		$isroot = ($result["0"]["0"] >= 1);
				
		if ($name != null && !(strpos($name,"label/") === 0)){
			$name = "label/" . $name; 
		}
				
		if($expanded != null){
			$expanded = ($expanded == "true");
		}
		
		$query = "UPDATE {$db_prefix}label
				SET";
		$inputs = array();
		
		$wheres = 0;
		
		if(!$isroot && $name != null){
			$query .= " name = :name";
			$inputs[":name"] = $name;
			$wheres++;
		}
		
		if($content != null){
			if($wheres > 0){
				$query .= ",";
			}
			$query .= " content = :content";
			$inputs[":content"] = $content;
			$wheres++;
		}

		if(!$isroot && ($expanded !== null)){
			if($wheres > 0){ 
				$query .= ",";
			}
			$query .= " expanded = :expanded";
			$inputs[":expanded"] = $expanded;
		}
		
		$query .= " WHERE labelid = :id
			AND userid = :userid";
		$inputs[":id"] = $id;
		$inputs[":userid"] = $app->userid;
		
		$sth = $dbh -> prepare($query);
		$sth -> execute($inputs);
	});
	
	/**
	 * @SWG\Api(
	 * 	path="/library/labels/{labelid}",
	 * 	description="Delete a label",
	 * 	@SWG\Operation(
	 * 		method="DELETE",
	 * 		nickname="Delete Label",
	 * 		summary="Delete Label",
	 * 		type="void",
	 * 		@SWG\Parameter(
	 * 			name="Authorization",
	 * 			description="clients login token",
	 * 			paramType="header",
	 * 			required=true,
	 * 			type="string"
	 * 		),
	 * 		@SWG\Parameter(
	 * 			name="labelid",
	 * 			description="ID of the label that is to be removed",
	 * 			paramType="path",
	 * 			required=true,
	 * 			type="integer"
	 * 		),
	 * 		@SWG\ResponseMessage(
	 * 			code=400,
	 * 			message="Bad token"
	 * 		)
	 * 	)
	 * )
	 */
	$app -> delete('/labels/:labelid', function($labelid) use ($app) {
		$app->db->del_label($labelid);
	});

});

$app -> run();
?>
