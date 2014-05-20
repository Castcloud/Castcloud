# Castcloud API documentation
## Introduction
Castcloud is a RESTful API specifying how podcast clients can communicate a user’s podcast library to each other via a server.
<!-- INSERT MORE -->

### Features
The server side of the API provides centralized metadata storage for making personal podcast libraries available to multiple clients. The metadata of the library includes subscriptions, episodes, playback status and settings. The server side takes care of feedcrawling so that clients doesn't have to individually crawl all the users feeds. This improves speed and simplefies some of the clients work.

### Reference implementations
We have made reference implementations of both server and client. The referance server implementation has all server side functionality and the referance client uses a syncing model as far as possible for modern browsers (offline media storage is currently problematic), and falls back to streaming when syncing is impossible.

### License
All code is GPLv3 with some included utilities and libraries being Apache 2.0 and MIT License. This means you are free to make commercial software or solutions with our code, and in fact we encourage it!

In the spirit of making something really useful for all users of podcasting software, we hope that all developers come together and make this a common standard and improved upon it instead of creating incompatibilities.
<!-- MAYBE GET MORE SENTENCES -->

### Who we are
We are 3 bachelor computer science students from Narvik University College in Norway. We are working on this project as our bachelor thesis. We hope to have a specification ready for implementation in time for our graduation in the beginning of June 2014. We hope this API specification will help solve an issue that in our opinion have plagued and hampered further growth and user adoption of podcasts.

## Clients
A client in this documentation is a piece of software run by a user on any compatible platform. A client is identified by its name, regardless of developer or platform. Different instanses of a client is identified by a `UUID` or `token` to the server, and by its client `decription` to users.

There are 2 client models intended for use with the API. Hybrid models are also possible. Both streaming and syncing are equal in functionality, however it is recommend implementing as much syncing functionaity as possible on the clients platform. Syncing improves userexperience as the client has a local copy of the library state. This increases the clients speed and enables offline functionality.

### Synchronizing
The primary part of creating a syncing client is making sure you have `episodes`, `casts`, `events`, `labels` and `settings` stored locally. Fetching of `events` and `episodes` can be accelerated using a `since` timestamp and correct filtering. Clients will have to remove episodes from its library when it gets delete `events` (or End of track `events` depending on `settings`) or a `cast`gets removed (unsubscribed to).

To be fully offline capable, the client needs manage the related mediafiles locally.
### Streaming
Streaming clients fetch information as required. The first part consists of `casts` (and `labels` if the client implements folders and sorting). After letting the user select a `cast` or `label` can the client then fetch the related `episodes`.

## Server
A server in this documentation is a piece of software runing on a network capable device. It respondes to REST request in the pattern described in this documentation. It handles permanent storage of information and information gathering from podcast feeds.

Most of the data posessing is moved server side to simplify client implementation. This documentation will attempt to explain some of the required data prossesing to ease implementation. The explenation is located under the heading "Server logic" for each of the different calls.

### Crawling
It is essensial that the server implements crawling correcly. All clients using the server relies on it to have fetched the needed feed infomation. If this was to stop would all clients stop working. The server should be a responsible crawler, trottling and propperly identifying itself with [a useragent](https://feedpress.it/support/tutorials/feedfetcher "How to be a good feed fetcher?"). It should implement optimisations like PubSubHubbub to be as fast as it can. It needs to identify content and give it an `episode id` without causing duplication. Using the `items` `GUID` might mostly work, but writing a good crawler is [a never ending saga](http://inessential.com/2013/03/18/brians_stupid_feed_tricks "Brians stupid feed tricks").

The server will need to store all `channel` information in the feed for `/library/cast` and all data inside each `item` for the `episodes` related calls. When storing new `episodes` after having crawled a feed, should the servers time for when it got stored (API server, not the database server) be included. This will be used when clients use the `since` filter in the `/library/newepisodes` call.

## Calls
This document will attempt to explain proper usage of the api. All functions are explained in an interactive documentation called swagger. This can be viewed at http://api.castcloud.org/utils/swagger-ui/ or run on your own server instance after having recompiled the swagger documentation.

To recompile the swagger documentation:
```Shell
cd /your/castcloud/directory/
/usr/bin/php utils/swagger-php/swagger.phar api/ -o api-docs/ --default-base-path http://your.domain.name/api
```
__Notice:__ When sending pull requests. Make sure you have kept http://api.castcloud.org/api as basepath.

### XML to JSON conversion
Some data returned from the server has been parsed from xml to json. This is mostly straight forward with one exception of tags having both attributes and content inside itself. In these cases the content inside the tag gets put in a field named “_”.

Example:
```XML
<guid isPermaLink="false">5B4B1B2C-F9C6-4DC2-B09F-B6DE889A9E25</guid>
```
becomes
```JSON
"guid":{
	"_" : "5B4B1B2C-F9C6-4DC2-B09F-B6DE889A9E25",
	"isPermaLink" : "false"
}
```

### Account/Login
This call registers the client to the user and returns the client authorization token. The authorization token is used for all other requests. `UUID`, `name` and `description` fields are important as these are used actively. 

__Example:__
```Shell
curl https:// UrlPath /api/account/login -d username=user -d password=*** -d clientname=Superclient -d clientdescription="Bob's iPhone" -d uuid=1234567890 
```
<script src="https://gist.github.com/basso/0b84947441aeac8c8c2e.js?file=account-login"></script>

#### UUID
The `UUID` is used to identify the client even if the token is expired. This is to reduce the number of duplicate client registrations. The UUID can be given by the platform or just some random characters (should be 8 characters or more, preferably 256 bit / 32 characters) that is stored for further reference.

The `UUID` is the only thing that is intended to be kept after a user explicitly signs out.

#### Name and description
The `name` and `description` information is used to identify the client to the user. The `name` should be hard coded into the client and not change with versioning or platforms (unless features vary). `description` can be something less rigidly defined. It can describe the device it is running on “iPad” or “Windows 7 (x64)”, but the best `description` would be something that relates to the user, for example “Livingroom media center” or “Bedroom iPad”.

#### Server logic
When a user logs in through the API, the client calling the server provides a lot of information. `clientname`, `clientdescription` and `uuid` is intended to  simplefy backend management. `clientname` and `clientdescription` exposed though the API. `clientversion` and `apikey` are intended for future use with a possible client developer backend. The `UUID` is intended to prevent duplicate client registrations. If a user have a active token for a client with a matching `UUID` and `name` should the `clientdescription` and `clientversion` be updated.

### Account/Ping
This can be used to check if the token is good.

__Example:__
```Shell
curl http:// UrlPath /api/account/ping -H "Authorization:1337EGAh10qpLDq7xDTXG41r6T//ONLRvF5hd\/M3AX9I="
```
<script src="https://gist.github.com/basso/0b84947441aeac8c8c2e.js?file=account-ping"></script>

#### Server logic
Check if the token is valid.

### Account/Settings
Clients are able to store the users `settings` server side. This makes the syncing experience more seamless. By default all `settings` are global for all clients, but clients can create `clientspesific` overrides. `clientspesific` overrides will apply for all clients with the same client `name`. Global settings are intended for common `settings` with common `values`. If a client uses uncommon settings `values` or doesn't understand the current setting `value`, must the client create overrides with its `settings` to avoid conflicts. A user might use one client differently than their other clients, and might want to configure this client separately from than the rest. In these cases clients should offer the abillety for users to toggle overrides of settings so that they don't propagate to other clients. When a user untoggles a settings override must the client ask if is the user wants to keep the `value` from the override or the global `setting`.

Clients needs to bring their own set of supported `settings` and `values` with client recomended defaults. If a `setting` does not exsist should the client set and use its default. A client should not overwrite the users global settings with it's defaults unless the user asks for it. If a current settings value is not understood should the client set it's default as a setting override and use it. The server side does not change its behaviur depending on settings, it merly is storing them. It is up to the client to implement the settings functionality.

Clients are not required to show any UI for configuring a setting. If some information about the clients state is required to save, `settings` with `clientspesific` overrides might be a good place to put it.

We hope that developers can come together and create a common list of `setting keys` and `values`. Therefor we suggest using these keys and `values` when implementing settings for related functionality in clients:

Changes to `settings` should be sent to the server as fast as possible. It is recomended that clients keep an output buffer of these changes as they occur, then retry sending them until they successfully gets sent.
<!-- MORE, til review -->

<table style="overflow: auto;">
	<tr>
		<th>Setting key</th>
		<th>Values</th>
		<th style="min-width:160px"></th>
	</tr>
	<tr>
		<td>playback/forward_skip</td>
		<td rowspan="2">15s, 10%</td>
		<td rowspan="2">Integer followed by a unit character. Available unit characters are "s" = seconds, "%" = percentage of the medias total duration. </td
	</tr>
	<tr>
		<td>playback/backward_skip</td>
	</tr>
	<tr>
		<td>playback/speed</td>
		<td>1.0</td>
		<td>Decimal number decribing the default rate of playback. 1 = normal speed. 1.5 = 150% speed</td>
	</tr>
	<tr>
		<td>playback/continous_playback</td>
		<td>true</td>
		<td>Stringformated boolean. Whether playback should continue after end of track</td>
	</tr>
	<tr>
		<td>gui/episode_sortorder</td>
		<td>published, reverse published, alfa, reverse alfa</td>
		<td></td>
	</tr>
	<tr>
		<td>hotkey/playpause</td>
		<td>spacebar,P,PlayPause</td>
		<td rowspan="5">csv keyboard hotkeys</td>
	</tr>
	<tr>
		<td>hotkey/forward_skip</td>
		<td>ArrowRight</td>
	</tr>
	<tr>
		<td>hotkey/backward_skip</td>
		<td>ArrowLeft</td>
	</tr>
	<tr>
		<td>hotkey/next</td>
		<td>J,N,PageDown</td>
	</tr>
	<tr>
		<td>hotkey/previous</td>
		<td>K,P,PageUp</td>
	</tr>
	<tr>
		<td>sync/autodelete</td>
		<td>false</td>
		<td>Stringformated boolean. If true clients can autodelete podcasts with EOT events. Default `exclude` parameter for episode and event related calls should be "60,70".<br/>
<b>Notice:</b> It is likely this might change from being a boolean to something with more details.</td>
	</tr>
</table>

__Notice:__ `Setting keys` are not a rigid part of the specification, but an attempt to find common ground. This list will be modified as per developer adoption unrelated of api versioning to set a common ground.

#### Server logic
The server should send all the users global `settings` and all `settings` with `clientspesific` overrides for the active client. The server needs to keep track of what client set `settings` with `clientspesific` overrides. The `clientspesific` override is applicable to all clients with the same client name string, and is not spesific to the clients `UUID` or `token`. Several instanses of a client can keep their `clientspesific` `settings` in sync across serveral devices.

### Library/Casts
`casts` is the the users subscriptions. Each `cast` has its own unique `cast id`. Adding a cast to the user subscription list only requires a `URL` of a valid podcast feed. By using the `cast id` the client can edit and delete a subscription from the library. It is also possible to import and export casts via opml.

Changes to `casts` should be sent to the server as fast as possible. It is recomended that clients keep an output buffer of these changes as they occur, then retry sending them until they successfully gets sent.

__Example:__
```Shell
curl http:// UrlPath /api/library/casts -H "Authorization:SuperSecretToken"
```
<script src="https://gist.github.com/basso/0b84947441aeac8c8c2e.js?file=library-casts"></script>

#### Server logic
A subscription should be stored on the server as a reference between the user and the cast. That way, if two users subscribe to the same cast will they both have the same `cast id` and the episodes will have the same `episode ids`. This will reduce the amount of feeds the server will need to crawl. `casts.opml` are the only parts of the api that uses xml. Make sure you maintain the propper label structure when you export the opml, and validate it with a validation tool. Importing to iTunes can also be a good test. When importing opmls, make sure you maintain propper label stucture. Remeber that labels should only be one layer deep, so you might have to flatten it. Importing a large opml from a feedreader like bazqux.com might be a good choice. Do not forget to crawl the imported opmls.

### Library/Episodes and Library/Newepisodes
Both these calls return somewhat similar results. What to use depends on the client model.

__Example:__
```Shell
curl https:// UrlPath /api/library/episodes/ID -H "Authorization:SuperSecretToken" 
```

```Shell
curl https:// UrlPath /api/library/newepisodes -H "Authorization:SuperSecretToken" 
```
<script src="https://gist.github.com/basso/0b84947441aeac8c8c2e.js?file=library-episodes"></script>

#### Syncing model (Library/Newepisodes)
If the client is using a syncing client model we recommend using `newepisodes` and related calls as you can get episodes for all feeds with 1 call. In addition you can save a lot of data transfer when using the `since` parameter. When providing a `since` parameter, please use the `timestamp` included with the last result, and not one from the client side as these might differ.

Please note that using a syncing model will force you to get events from `/library/events` as the `lastevent` included with each `episode` will quickly get outdated. If you see a new `event` for an `episode` that you don't have locally, this means the user has undeleted the `episode`. Retrieve it with `/library/episode/{episodeid}`.

If the user wants to see all `episodes` of a podcast (included deleted) use the `/library/episodes/{castid}` with "" as a filter. With this information the user will be able to reset playback status (undelete) for the `casts` `episodes`. Clients undeletes `episodes` with sending a new start event (type 10). Most syncing client should implement this as a online only feature.

#### Streaming model (Library/Episodes)
If you are using a streaming model all you will need to use is `/library/episodes`. Before you can call it however you need have the information from `/library/casts` or `/library/labels` as `cast id` or `label id` is a required parameter.

#### Server logic
`/library/episodes`, `/library/episode` and `/library/newepisodes` all return `episodes` in the same format. The biggest difference are the filters defined in input parameters. `/library/newepisodes` must also include a timestamp of when it was generated. The `feed` in each `episode` is a json representation of `items` xml. `/library/episode` only returns 1 episode at the time, please note that this should not be inside a list of episodes.

`since` filter in `/library/newepisode`. The call should filter to only return episodes stored after this time.

`episode id` filter in `/library/episode`. Required parameter, should show error if not provided. The call should only return the one episode with the specified episode id.

`cast id` filter in `/library/episodes`. Required parameter, should show error if not provided. The call should only return episodes for the casts with the specified `cast id`.

`label id` filter in `/library/episodes/label`. Required parameter, should show error if not provided. The call should only return episodes for casts inside the specified label.

`exclude` filter in `/library/episodes`, `/library/episodes/label` and `/library/newepisodes`. Parameter is comma separated integers. The call should only return `episodes` where the `episodes` `lastevent` is not one of the listed types. If no exclude parameter is provided must the server use a default filter of “70” (deleted). If the parameter is empty (“”) must no `episodes` be excluded.

### Library/Events
`events` are how the clients keeps track of the users playback progression. They relate to common actions performed on the client.

<table>
	<tr>
		<th>Event type</th>
		<th>Description</th>
	</tr>
	<tr>
		<td>10</td>
		<td>Start. Playback started. Also used to undelete/reset playback status. Allmost equal to no event (`positionts` must be 0).</td>
	</tr>
	<tr>
		<td>20</td>
		<td>Pause. Playback paused.</td>
	</tr>
	<tr>
		<td>30</td>
		<td>Play. Playback resumed after a pause</td>
	</tr>
	<tr>
		<td>40</td>
		<td>Sleeptimer start. This indicates where a sleeptimer was initiated. Concurrentorder must be 1 less than the `concurrentorder` for the Sleeptimer end event.</td>
	</tr>
	<tr>
		<td>50</td>
		<td>Sleeptimer end. This indicates where a sleeptimer is intended to end. Should be sent with Sleeptimer start, the actual end of a sleeptimer is indicated with a pause at the end of the timer. `concurrentorder` must be 1 more than the `concurrentorder` for the Sleeptimer end event.</td>
	</tr>
	<tr>
		<td>60</td>
		<td>End of track. The playback has reached the end of the file and can be expected to have been listened to completely.</td>
	</tr>
	<tr>
		<td>70</td>
		<td>Delete. The user has explicitly indicated (by action) that the episode should be deleted. Clients no longer needs to maintain data about this episode unless the user explicitly ask for it.</td>
	</tr>
</table>

More complex `events` are built up of combinations of `events`. If a users skips from one position to another, the client should then send 2 events with the same `ClientTS`. The first event should be a pause `event` with a `concurrentorder` of 0. The second `event` should be a play event with the new `positionts` and a `concurrentorder` of 1.

Some clients might not skip but seek. A seek begins with a pause `event` when playback starts at seeking speeds. Seek is ended with a play `event` when seeking speed is ended and playback is returned to regual playback speed.

Clients following a streaming model might not need to fetch `events`, as the most recent `event` is included when getting episodes. Some clients might offer the ability to show a list of the users events. This might better help the user find back to where they last were. Streaming model clients that offers a complete eventlist should use the `cast id` to speed up the request. Syncing model client that does not offer this functionality, do not need to store more than the `lastevent` for each `episode`.

`events` should be sent to the server as fast as possible. It is recomended that clients keep an output buffer of `events` as they occur, then retry sending them until they successfully gets sent.

__Example:__
```Shell
curl https:// UrlPath /api/library/events -H "Authorization:SuperSecretToken" 
```
<script src="https://gist.github.com/basso/0b84947441aeac8c8c2e.js?file=library-events"></script>

#### Server logic
`since` filter. The call should filter to only return events received/stored after this time.

`episode id` filter. The call should only return `events` for the specified `episode`.

`exclude` filter. The call should only return `events` where the `episodes` last `event` is not one of the listed `types`. If no exclude parameter is provided should the server use a default filter of “70” (deleted). To completely disable the exclude filter submit the parameter but leave it empty (“”).

### Library/Labels
With labels you group and sort your subscriptions in clients.

__Example:__
```Shell
curl https:// UrlPath /api/library/labels -H "Authorization:SuperSecretToken" 
```
<script src="https://gist.github.com/basso/0b84947441aeac8c8c2e.js?file=library-labels"></script>

#### Server logic
The most important part of this call in making sure the output is clean and valid. Therefor the output should be validated every time before it gets sent to a client. Clients should not have change the `labels` when they unsubscriobe from a podcast or subscribe to a new one. As defaut all `casts` and `labels` should be added to the root `label`. A `cast` does not have to be in root when it is inside another `label`. You might be interested in the function `clean_Labels()` inside `api/db.php` if you are trying to ensure clean `labels`.

<style>
.gist-data { max-height: 400px; overflow: auto; } 
.gist .line, .gist .line-number { font-size:14px !important; line-height:25px !important; }
.gist table { margin-bottom: 0 !important;}
</style>
