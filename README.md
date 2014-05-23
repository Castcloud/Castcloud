# Castcloud API documentation
## Introduction
Castcloud is a RESTful API specifying how podcast clients can communicate a user’s podcast library to each other via a server.

### Features
The server side of the API provides centralized metadata storage for making personal podcast libraries available to multiple clients. The metadata of the library includes subscriptions, episodes, playback status and settings. The server side takes care of feed crawling so that clients don't have to individually crawl all the users feeds. This improves speed and simplifies some of the clients work.

### Reference implementations
We have made reference implementations of both the server and the client. The reference server implementation has all server side functionality and the reference client uses a synchronizing model as far as possible for modern browsers (offline media storage is currently problematic), and falling back to streaming when synchronizing is impossible.

### License
All code is GPLv3 with some included utilities and libraries being Apache 2.0 and MIT License. This means you are free to make commercial software or solutions with our code, and in fact we encourage it!

In the spirit of making something really useful for all users of podcasting software, we hope that all developers can come together and make this a common standard and improve upon it instead of creating incompatibilities.

### Who we are
We are 3 bachelor computer science students from Narvik University College in Norway. We are working on this project as our bachelor thesis. We hope to have a specification ready for implementation in time for our graduation in the beginning of June 2014. We hope this API specification will help solve an issue that in our opinion has plagued and hampered further growth and user adoption of podcasts.

## Clients
A client in this documentation is a piece of software run by a user on any compatible platform. A client is identified by its `name`, regardless of developer or platform. Different instances of a client is identified to the server by its `UUID` or `token`, and by its client `name` and `decription` to users.

There are 2 client models intended for use with the API. Hybrid models are also possible. Both streaming and synchronizing are equal in functionality, however it is recommended to implement as much synchronizing functionality as possible on the clients platform. Synchronizing improves the user experience as the client has a local copy of the library state. This increases the clients speed and enables offline functionality.

### Synchronizing
The primary part of a synchronizing client is making sure it has `episodes`, `casts`, `events`, `labels` and `settings` stored locally. The fetching of `events` and `episodes` can be accelerated using a `since` timestamp and correct filtering. Clients will have to remove `episodes` from its library when it gets delete `events` or a `cast` gets removed (unsubscribed to).

To be fully offline capable, the client needs to manage the related media files locally.
### Streaming
Streaming clients fetch information as required. The first part consists of `casts` (and `labels` if the client implements folders and sorting). After letting the user select a `cast` or `label`, the client then fetch the related `episodes`.

## Server
A server in this documentation is a piece of software running on a network capable device. It respondes to REST requests in the pattern <!-- MORE --> described in this documentation. It handles permanent storage of information and information gathering from podcast feeds.

Most of the data processing is moved server side to simplify client implementation. This documentation will attempt to explain some of the required data processing to ease implementation. The explanation is located under the heading "Server logic" for each of the different calls.

### Crawling
It is essential that the server implements crawling correctly. All clients using the server rely on it to have fetched the required feed infomation. If this were to stop all clients would stop working. The server should be a responsible crawler, throttling and properly identifying itself with [a useragent](https://feedpress.it/support/tutorials/feedfetcher "How to be a good feed fetcher?"). It should implement optimisations like PubSubHubbub to be as fast as possible. It needs to identify content and give it an `episode id` without causing duplication. Using the `items` `GUID` might mostly work, but writing a good crawler is [a never ending saga](http://inessential.com/2013/03/18/brians_stupid_feed_tricks "Brians stupid feed tricks").

The server will need to store all `channel` information in the feed for `/library/cast` and all data inside each `item` for the `episodes` related calls. When storing new `episodes` after having crawled a feed, the servers time (API server, not the database server) should be included for when it was stored. This will be used when clients use the `since` filter in the `/library/newepisodes` call.

## Calls
This document will attempt to explain proper usage of the API. All functions are explained in an interactive documentation called swagger. This can be viewed at http://api.castcloud.org/utils/swagger-ui/ or run on your own server instance after having compiled the swagger documentation.

To compile the swagger documentation:
```Shell
cd /your/castcloud/directory/
/usr/bin/php utils/swagger-php/swagger.phar api/ -o api-docs/ --default-base-path http://your.domain.name/api
```

### XML to JSON conversion
Some data returned from the server has been parsed from XML to JSON. This is mostly straight forward with one exception of tags having both attributes and content inside itself. In these cases the content inside the tag gets put in a field named “_”.

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
The `UUID` is a string used to identify the client instance even if the `token` is expired. The `UUID` is used to reduce the number of duplicate client registrations on the server. The client can accwire a `UUID` string from its platform, or just generate a random string and permanently write it to storage. The string should be at least 8 characters, preferably 256 bit / 32 characters. The string should be unique for every device, but the same for the duration of the deployment on the device.

The `UUID` is the only thing that is intended to be kept after a user explicitly signs out.

#### Name and description
The `name` and `description` information is used to identify the client to the user. The `name` should be hard coded into the client and not change with versioning or platforms (unless features vary). `description` can be something less rigidly defined. It can describe the device it is running on “iPad” or “Windows 7 (x64)”, but the best `description` would be something that relates to the user, for example “Livingroom media center” or “Bedroom iPad”.

#### Server logic
When a user logs in through the API, the client calling the server provides a lot of information. `clientname`, `clientdescription` and `uuid` are intended to  simplify backend management. `clientname` and `clientdescription` are exposed through the API. `clientversion` and `apikey` are intended for future use with a possible client developer backend. The `UUID` is intended to prevent duplicate client registrations. If a user has an active `token` for a client with a matching `UUID` and `name`, `clientdescription` must be updated.

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
Clients are able to store the users `settings` server side. This makes the synchronizing experience more seamless. By default all `settings` are global for all clients, but clients can create `clientspecific` overrides. `clientspecific` overrides will apply for all clients with the same client `name`. Global settings are intended for common `settings` with common `values`. If a client uses uncommon settings `values` or doesn't understand the current setting `value`, the client must override the `setting` to avoid conflicts. A user might use one client differently than their other clients, and might want to configure this client separately from the rest. In these cases clients should offer the ability for users to toggle overrides of settings so that they don't propagate to other clients. When a user untoggles a settings-override the client must ask if the user wants to keep the `value` from the override or the global `setting`.

The client has to implement its own set of supported `settings` and `values` with appropriate default `values`. If a `setting` does not exist, the client should set and use the default values. A client should not overwrite the users global settings with its defaults, unless the user asks for it. If a current settings `value` is not understood by the client, the client should set its default as an override and use the override. The server side does not change its behaviour depending on settings, it only stores them. It is up to the client to implement the settings functionality.

Clients are not required to show any UI for configuring a setting. If some information about the clients state is required to save, `settings` with `clientspecific` overrides might be a good place to put it.

Changes to `settings` should be sent to the server as fast as possible. It is recommended that clients keep an output buffer of these changes as they occur, then retry sending them until they successfully gets sent.

We hope that developers can come together and create a common list of `setting keys` and `values`. Therefore we suggest using these keys and `values` when implementing settings for related functionality in clients:
<table style="overflow: auto;">
	<tr>
		<th>Setting key</th>
		<th>Values (String formatted)</th>
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
		<td>Boolean. Whether playback should continue after end of track</td>
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
		<td>Boolean. If true clients can auto delete podcasts with EOT events. Default `exclude` parameter for episode and event related calls should be "60,70".<br/>
<b>Notice:</b> It is likely this might change from being a boolean to something with more details.</td>
	</tr>
</table>

__Notice:__ `Setting keys` are not a rigid part of the specification, but an attempt to find common ground. This list will be modified as per developer adoption unrelated of API versioning to set a common ground.

#### Server logic
The server should send all the users global `settings` and all `settings` with `clientspecific` overrides for the active client. The server needs to keep track of which client specified the `clientspecific` override. The `clientspecific` override is applicable to all clients with the same client name string, and it is not specific to the clients `UUID` or `token`. Several instances of a client can keep their `clientspecific` `settings` in sync across several devices.

### Library/Casts
`casts` is the the users subscriptions. Each `cast` has its own unique `cast id`. Adding a cast to the user subscription list only requires an `URL` of a valid podcast feed. By using the `cast id` the client can edit and delete a subscription from the library. It is also possible to import and export casts via opml.

Changes to `casts` should be sent to the server as fast as possible. It is recommended that clients keep an output buffer of these changes as they occur, then retry sending them until they successfully get sent.

__Example:__
```Shell
curl http:// UrlPath /api/library/casts -H "Authorization:SuperSecretToken"
```
<script src="https://gist.github.com/basso/0b84947441aeac8c8c2e.js?file=library-casts"></script>

#### Server logic
A subscription should be stored on the server as a reference between the user and the cast. That way, if two users subscribe to the same cast will they both have the same `cast id` and the episodes will have the same `episode ids`. This will reduce the amount of feeds the server will need to crawl. `casts.opml` are the only parts of the api that use xml. Make sure you maintain the proper label structure when you export the opml, and validate it with a validation tool. Importing to iTunes can also be a good test. When importing opml's, make sure you maintain proper label stucture. Remember that labels should only be one layer deep, so you might have to flatten it. Importing a large opml from a feedreader like bazqux.com might be a good choice. Do not forget to crawl the imported opmls.

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

#### Synchronizing model (Library/Newepisodes)
If the client is using a synchronizing client model we recommend using `newepisodes` and related calls as you can get episodes for all feeds with 1 call. In addition you can save a lot of data transfer when using the `since` parameter. When providing a `since` parameter, please use the `timestamp` included with the last result, and not one from the client side as these might differ.

Please note that using a synchronizing model will force you to get events from `/library/events` as the `lastevent` included with each `episode` will quickly get outdated. If you see a new `event` for an `episode` that you don't have locally, this means the user has undeleted the `episode`. Retrieve it with `/library/episode/{episodeid}`.

#### Streaming model (Library/Episodes)
If you are using a streaming model all you will need to use is `/library/episodes`. Before it can be fetched, the client will need to have information from `/library/casts` or `/library/labels` as `cast id` or `label id` is a required parameter.

### Deleted / hidden episodes
A user would normaly keep deleting episodes as they complete listening to it. This means the episodes would disappear from the normal interface. Deleted `episodes` are still stored on the server. To get the complete list of `episodes` use the `/library/episodes/{castid}` with "" as `exclude` parameter. When a client displays the complete list of `episodes`, the user should have the option to reset the playback status. Clients undeletes `episodes` with sending a new start event (type 10). Most synchronizing clients should implement this as an online only feature.

#### Server logic
`/library/episodes`, `/library/episode` and `/library/newepisodes` all return `episodes` in the same format. The biggest difference are the filters defined in input parameters. `/library/newepisodes` must also include a timestamp of when it was generated. The `feed` in each `episode` is a JSON representation of `items` xml. `/library/episode` only returns one episode at a time, please note that this should not be inside a list of episodes.
<!-- MORE TABLE SEMPAI! -->
`since` filter in `/library/newepisode`. The call should filter to only return episodes stored after this time.

`episode id` filter in `/library/episode`. Required parameter, should show error if not provided. The call should only return the one episode with the specified episode id.

`cast id` filter in `/library/episodes`. Required parameter, should show error if not provided. The call should only return episodes for the casts with the specified `cast id`.

`label id` filter in `/library/episodes/label`. Required parameter, should show error if not provided. The call should only return episodes for casts inside the specified label.

`exclude` filter in `/library/episodes`, `/library/episodes/label` and `/library/newepisodes`. Parameter is comma separated integers. The call should only return `episodes` where the `episodes` `lastevent` is not one of the listed types. If no exclude parameter is provided must the server use a default filter of “70” (deleted). If the parameter is empty (“”) must no `episodes` be excluded.

### Library/Events
`events` are how clients keeps track of the users playback progression. They relate to common actions performed on the client.

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
		<td>Sleeptimer start. This indicates where a sleeptimer was initiated. Concurrentorder must be one less than the `concurrentorder` for the Sleeptimer end event.</td>
	</tr>
	<tr>
		<td>50</td>
		<td>Sleeptimer end. This indicates where a sleeptimer is intended to end. Should be sent with Sleeptimer start, the actual end of a sleeptimer is indicated with a pause at the end of the timer. `concurrentorder` must be one more than the `concurrentorder` for the Sleeptimer end event.</td>
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

More complex `events` are built up of combinations of `events`. If a users skips from one position to another, the client should then send two events with the same `ClientTS`. The first event should be a pause `event` with a `concurrentorder` of 0. The second `event` should be a play event with the new `positionts` and a `concurrentorder` of 1.

Some clients might not skip but seek. A seek begins with a pause `event` when playback starts at seeking speeds. Seek is ended with a play `event` when seeking speed is ended and playback is returned to regular playback speed.

Clients following a streaming model might not need to fetch `events`, as the most recent `event` is included when getting episodes. Some clients might offer the ability to show a list of the users events. This might better help the user find back to where they last were. Streaming model clients that offer a complete eventlist should use the `cast id` to speed up the request. A synchronizing model client that does not offer this functionality, do not need to store more than the last event for each `episode`.

`events` should be sent to the server as fast as possible. It is recommended that clients keep an output buffer of `events` as they occur, then retry sending them until they successfully get sent.

__Example:__
```Shell
curl https:// UrlPath /api/library/events -H "Authorization:SuperSecretToken" 
```
<script src="https://gist.github.com/basso/0b84947441aeac8c8c2e.js?file=library-events"></script>

#### Server logic
<!-- MORE TABLE SEMPAI! -->
`since` filter. The call should filter to only return events received/stored after this time.

`episode id` filter. The call should only return `events` for the specified `episode`.

`exclude` filter. The call should only return `events` where the `episodes` last `event` is not one of the listed `types`. If no `exclude` parameter is provided, the server must use a default filter of “70” (deleted). To completely disable the exclude filter submit the parameter but leave it empty (“”).

### Library/Labels
With labels you group and sort your subscriptions in clients.

__Example:__
```Shell
curl https:// UrlPath /api/library/labels -H "Authorization:SuperSecretToken" 
```
<script src="https://gist.github.com/basso/0b84947441aeac8c8c2e.js?file=library-labels"></script>

#### Server logic
The most important part of this call is making sure the output is clean and valid. Therefore the output should be validated every time before it gets sent to a client. Clients should not have to change the `labels` when they unsubscribe from a podcast or subscribe to a new one. As default all `casts` and `labels` should be added to the root `label`. A `cast` does not have to be in root when it is inside another `label`. You might be interested in the function `clean_Labels()` inside `api/db.php` if you are trying to ensure clean `labels`.

<style>
.gist-data { max-height: 400px; overflow: auto; } 
.gist .line, .gist .line-number { font-size:14px !important; line-height:25px !important; }
.gist table { margin-bottom: 0 !important;}
</style>
