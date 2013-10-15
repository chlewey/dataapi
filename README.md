Data API
========

Data API is an API server for keeping track of automated data,
it can be used by several resources including
[Data Transmit](/chlewey/datatransmit )

A DataAPI server is an HTTP server, which normally respond in JSON.  It will
originally be written in PHP but might be ported to another language in the
future.

This API is a colection of individual APIs.

So far four APIs are bundled:
 1. `status` For reporting HTTP statuses
 1. `datalog` For the storage of updates
 1. `avatar` For retrieving avatar information of elements in the db
	and displaying avatars
 1. `banner` For retrieving banner information of elements in the db
	and displaying user banners

All JSON responses from DataAPI will include the following fields:

 * `api`	the specific API name (ASCII string)
 * `version`	the version of the DataAPI (ASCII string with dot
		separated decimal numbers)
 * `status`	the HTTP status (integer), should be equal to the actual
		HTTP status. (might be ommited in successful requests)
 * `title`	The standard title of the HTTP status (ASCII string).
 * `message`	an accompanying message (human readable UTF-8 string)
		explaining the status.
 * `action`	optional field, a data structure commanding to make any
		further action such as sending a particular update.
 * `data`	optional field, a data structure responding the request.

Status API
----------

**Usage:**

    GET <Server URL>/status/<###>

Where `<Server URL>` is the URL of the Server.  This is irrelevant to the API.
The combination `<Server URL>/status` can be referred as `<Status API URI>`.

The `<###>` is the three digits HTTP status number.

This API might not respond all possible HTTP status codes, and so far
only the status `200`, `201`, `401`, `403`, `404` and `500` are coded.

This API is not designed to work with any other different HTTP verb (method)
however it does not verify.  If the server allows a method it will be
responded as a GET.

**Response**

For all status, except the redirection status, the HTTP response will
match the `<###>` status.  For the `<3##>` redirection statuses, a 200
HTTP response is sent.

The responding message is a JSON stream with
the fields `api`, `version`, `status` and `title`.


Datalog API
-----------

**Usage**

    <METHOD> <Server URL>/datalog/<Aditional Info>

Where `<Server URL>` is the URL of the Server.  This is irrelevant to the API.
The combination `<Server URL>/datalog` can be referred as `<Datalog API URI>`.

Several HTTP verbs (methods) are recognized, and `<METHOD>` might be
either `GET`, `PUT`, `POST` and `DELETE`.

The `<Aditional Info>` usually consist of a `<element>` name.
The `<element>` is an ASCII all-lowercase word that uniquely identify
a data cointainer in the datalog.

The Datalog API has three type of queries: auto-queries, user queries,
and administrative queries.

An **auto-query** is a query performed by an element on its own data.
The auto-queries include:
 1.	A **greeting** query
 2. A **self-modification** query
 2.	A **self-creation** query
 3. A **keep-alive** query
 4. An **update** query
 
Most of these queries are handled by the [Data Transmit][1] program.

All successful response to an autoquery might include an `action` field.
This field commands the [Data Transmit][1] program
to perform an aditional query such as sending a **self-modification**
query or an **update** for older data.

A **user query** is a query that request for storaged data.
These user queries are always safe queries that do not modify the database
and are always performed by the `GET` method.


User queries include:
 1.	**List**ing queries (queries that list the elments that are visible by a user)
 2. **Status** queries (queries that list standard information on an element)
 3. **Sumary** queries (queries that list a sumary of the status of the different data elements of an element)
 4. **Feed** queries (queries that list the last status updates for an element)
 
 An **administrative query** is a query made by a priviledged user that
 modifies element data, but not usually storaged updates.
 Administrative queries include:
  1. The element **creation** queries.
  1. The element **modification** queries.
  2. The element **deletion** queries.
  
**Specific queries**

 1.	**Greating query**
 
	The greeting query is sent by a base element to identify itself
	to the server at the begining of an update session.
	The format is:
	
		GET <Datalog API URI>/<element>?group=<group>&name=<name>
		
	Where `<group>` is the ASCII short name of the group the base element
	belogns to, and `<name>` is	the full UTF-8 name of the base element.
	(Both correctly URL-encoded).
	
	This query must be authenticated via HTTP authentication per session
	basis.  (As this is usually the first query in a session, the HTTP
	authentication is usually include in this header)
	
	The authenticating user **IS** the same as `<element>`.
	
	**Response**
	
	`404` (both HTTP and JSON message) if the element does not exist,
		regardless of authentication.
		[Data Transmit][1] interprets this as the
		need of a **Self-creation** query.
		
	`401` (both HTTP and JSON message) if authentication fails after
		checking that the element exists in the database.
		
	`200` if everithing is okay.  The JSON response will only include
		the fields `api` and `version` and an optional `action` field
		if either `group` or `name` do not match.
		[Data Transmit][1] interprets this `action`
		field as the need of a **Self-modification** query.
		
	An aditional `action` field might request for an older update.

 1.	**Self-modification query**

	The self-modification query is sent by a base element to uptade
	information on itself.
	The format is:
	
		PUT <Datalog API URI>/<element>
		
	And the body includes a JSON stream with at least the following
	fields:
	
	 *	`group`	set to the small ASCII name of the group
	 *	`name`	set to the full UTF-8 name of the base element
	 *	`modify`	set to boolean True.
	 
	The following aditional fields might be included in the query
	
	 *	`passwd`	a new password.
	 *	`items`	a list of item structures that will create or modify
		item elements.  Elements not in the list will be preserved.
		Note that each item structure might contain a list of
		meter elements to be created, modified or deleted.
	 *	`obsolet`	a list of item element identifiers that should
		be removed.

	This query must be HTTP-authenticated per session.
	The authenticating user **IS** the same as `<element>`.
		
	**Response**
	
	`404` (both HTTP and JSON message) if the element does not exist,
		regardless of authentication.
		[Data Transmit][1] interprets this as the
		need of a **Self-creation** query.
		
	`401` (both HTTP and JSON message) if authentication fails after
		checking that the element exists in the database.
		
	`200` (both HTTP and JSON message) if no modification was made
		in the database as all elements were already updated.
		An aditional message will recognize this.
	
	`201` or `202` on success, the JSON message will only contain
		the `api` and `version` fields and an aditional `new-ip` field
		if the recorded IP address was also changed.
		
	An aditional `action` field might request for an older update.

 1.	**Self-cretion query**

	The self-creation query is sent by a base element to create itself.
	The format is:
	
		PUT <Datalog API URI>/<element>
		
	And the body includes a JSON stream with at least the following
	fields:
	
	 *	`group`	set to the small ASCII name of the group
	 *	`name`	set to the full UTF-8 name of the base element
	 *	`user`	set to the small ASCII name of a privileged user.
	 *	`userpw`	set to the password of the privileged user,
		encripted or unencripted.
	 *	`items`	a list of item structures that will create item
		elements.
		Note that each item structure might contain a list of
		meter elements to be created.

	**Response**
	
	`401`	(Both HTTP and JSON message) If the `user`/`userpw` pair
		fails to authenticate.
		
	`403`	(Both HTTP and JSON message) If the `user` has no creation
		privileges on the group.
	
	`409`	(Both HTTP and JSON message) If the element cannot be
		created for any reason.  An aditional JSON field `code` will
		be interpreted as following:
	
	 *	`1`	element already exists.
	 *	`2`	database is blocked.
	 
	`201` or `202` on success, the JSON message will only contain
		the `api` and `version` fields.
		
	An aditional `action` field might request for an older update.

 1.	**Keep-alive query**
 
	A keep-alive query is a simple query that indicates that the base
	element is up and transmiting, even if no update is present.
	
	The format is:
	
		GET <Datalog API URI>/<element>
		
	The keep-alive is HTTP-authenticated per session.
	The authenticating user **IS** the same as `<element>`.

	** Response **
	
	`404` (both HTTP and JSON message) if the element does not exist,
		regardless of authentication.
		[Data Transmit][1] interprets this as the
		need of a **Self-creation** query.
		
	`401` (both HTTP and JSON message) if authentication fails after
		checking that the element exists in the database.
	
	`200` (HTTP, and small JSON message) normal response when everything
		is okay.
		
	`201` or `202` (HTTP, and small JSON message) normal response when
		everything is okay, but sending IP address did not match
		recorded IP address.  An aditional `new-ip` field in the
		answer acknoleges the new IP address.
		
	An aditional `action` field might request for an older update.

 1.	**Update query**
 
	A keep-alive query is a simple query that indicates that the base
	element is up and transmiting, even if no update is present.
	
	The format is:
	
		POST <Datalog API URI>/<element>
		
	The POST data will include the updates in either JSON or Sqlite3
	formats.
		
	The update query is HTTP-authenticated per session.
	The authenticating user **IS** the same as `<element>`.

	** Response **

	Responses are similar to a keep-alive except:
	
	`409` (both HTTP and JSON message) if the update could not be
		recorded after authentication succeeds.  A `code` field
		will give a further reason.
		
	`201` and `202` will be normally issued acknoleging a successful
		update, unless the updates were already recorded (in which
		case a `200` was sent).
		
	An aditional `action` field might request for an older update.

 1.	**List query**
 
	A list query is a query requesting a list of elments that can be
	visible by a user on per-user, per-group or per base-element basis.
	
	This query can be HTTP authenticated per session, or GET-query
	authenticated or not authenticated at all.
	The format is:
	
		GET <Datalog API URI>[/][?user=<user>&passwd=<password>]
		GET <Datalog API URI>/G/<group>[?user=<user>&passwd=<password>]
		GET <Datalog API URI>/<element>/list[?user=<user>&passwd=<password>]

	(For further user query usage the `user` and `passwd` query fields
	 will be omited but assumed:)
	
		GET <Datalog API URI>
		GET <Datalog API URI>/G/<group>
		GET <Datalog API URI>/<element>/list

	** Response **
	
	`401`	if authentication sent but fails.
	
	`404`	if group or base element does not exist.
	
	`200`	otherwise.  The JSON response will include the fields
		`api`, `version` and `list`, the later a list of pairs
		where first memeber of the pair is each base element and
		the second elmenent is a list of item elements.
		
	The returning list might be empty if the user has no reading
	privileges on the elements (or if there are no elements).

 1.	**Status query**
 
	A status query asks for general information on an element.
	The format is:

		GET <Datalog API URI>/<element>
		GET <Datalog API URI>/<element>/<item>
		
	A status query is diferenciated to a keep-alive query as either
	there is no authenticating user o the autenticating user **is not**
	the same as `<element>`.
	
	** Responses**
	
	The following responses can occure: `404`, `401`, `403` or `200`.
	
	A success query will include a `data` field retrieving general data
	on the requested element.

 1.	**Summary query**

	A summary query asks for general information on an element
	and a summary of the updates.
	The format is:

		GET <Datalog API URI>/<element>/summary
		GET <Datalog API URI>/<element>/<item>/summary
		GET <Datalog API URI>/<element>/<item>/<meter>
	
	The summary query can be authenticated or not authenticated.	
	
	** Responses**
	
	The following responses can occur: `404`, `401`, `403` or `200`.
	
	A success query will include a `data` field retrieving general 
	and summary data on the requested element.
  
 1.	**Feed query**

	A feed query asks for the latest status updates of an element
	or for the updates in a given period.
	The format is:

		GET <Datalog API URI>/<element>/feed[/<n>]
		GET <Datalog API URI>/<element>/<item>/feed[/<n>]
		GET <Datalog API URI>/<element>/<item>/<meter>/feed[/<n>]
		
	Two aditional GET query fields can be sent: `begin` and `end`
	providing the begin and end period for the updates.
	
	An aditional GET query field `alarm` can have the value `yes`,
	`no` or `only`.  This will filter alarm status.
	If the query field is not provided is assumed as `yes`.
	If the query field is provided with no value is assumed as `only`. 
	
	The summary query can be authenticated or not authenticated.
	
	** Responses**
	
	The following responses can occur: `404`, `401`, `403` or `200`.
	
	A success query will include a `feed` field retrieving a list of
	status.
  
 
 1.	**Element creation query**
 
 2.	**Element modification query**
 
 3. **Element deletion query**
 
Avatar and Banner APIs
----------------------

**Usage**

    <METHOD> <Server URL>/<API>/<Aditional Info>

Where `<METHOD>` might be a `GET`, `PUT` or `DELETE` HTTP verb.
(so far only `GET` method is implemented.)

The `<Server URL>` is the URL of the Server.  This is irrelevant to the API.
The combination `<Server URL>/<API>` can be referred as `<Image API URI>`,
where `<API>` might be either `avatar` or `banner`.

The `<Aditional Info>` might refer to an image index or an element identifier
plus and optional size and format.

	<Aditional Info> := <image index>[/[<size>][.<format>]]
	<Aditional Info> := <element>[/[<size>][.<format>]]
	
 1.	**The `GET` query**

	If not size and format is given, the `GET` query will return a JSON
	stream listing all available sizes of the image, plus aditional
	information.

	If a size is given, the `GET` query will return the image for that
	size.  If a format is given, it will convert the image to that
	format.  Only the `png`, `gif` and `jpeg` formats are recognized
	with case insensitive comparision for `png`, `gif` and `jpe?g`.
	
	**Response**
	
	The API will return a 404 HTTP response and a JSON `/status/404`
	with a `message` further explaining what could not be found if
	an image data could not be found.
	
	The API will return a 200 HTTP resposne with either a JSON message
	if no size is given or with the actual image data (and proper
	`Content-type` declaration) is the image was found.
	
	Avatars and banners are considered safe data, and no authentication
	is required, therefor no 401 or 403 will be returned.
	
 2. **the `PUT` query**
 
    The `PUT` query requires user authentication (and relevant privileges).
    This query will add (or replace) an image for the given resource.
    
    If no size is specified the call will create all relevant sizes
    for that element or index.  If a size is specified, the call will
    only add or replace that size.
    
    **Response**
    
    Possible responses are:
    
    `401`	(both HTTP and as a JSON body) if the user does not
		authenticate or fails in the authentication process.
	
	`403`	(both HTTP and as a JSON body) if the user does not
		have privileges to modify that particular element or
		avatar index. (Only site superusers can modify an avatar index.)
		Also if there is something wrong with the data, v.g. if the
		file format is not recognized or no file was provided.
	
	`404`	(both HTTP and as a JSON body) if the element does not
		exists.  (Never issued if an index is provided, but this might
		bring a 403 error)
	
	`201` or `202`	(both HTTP and as a JSON body) on success.
		The `201` will be issued if the change is ready and the `202`
		if it is scheduled.

 2. **the `DELETE` query**
 
    The `DELETE` query requires user authentication (and relevant privileges).
    This query will remove an image for the given resource.
    
    If no size is specified the call will delete all relevant sizes
    for that element or index.  If a size is specified, the call will
    only remove that size.

    **Response**
    
    Possible responses are:
    
    `401`	(both HTTP and as a JSON body) if the user does not
		authenticate or fails in the authentication process.
	
	`403`	(both HTTP and as a JSON body) if the user does not
		have privileges to modify that particular element or
		avatar index. (Only site superusers can modify an avatar index.)
	
	`404`	(both HTTP and as a JSON body) if the element or index does
		not exists.
	
	`204`	(HTTP only, no data as body) on success.



  
The Data API will receive the following type of messages:

 1. Keep-alive
 2. Request
 3. Update
 4. Greeting
 5. Modification
 6. Creation
 7. Destruction

Keep-alive and Requests are GET messages, Update is a POST message, Greeting,
Modification and Creation are PUT messages, and Destruction is a DELETE
message.

The general format of an API request is:

    <VERB> <dataapi URI>[/<resource>[/<request>[.<format>]]]

where `<VERB>` is GET, POST, PUT or DELETE; `<dataapi URI>` is the resource
locator of this API, `<resource>` is the resource being asked about, `<request>`
is any further action that is requested, and <format> is the response format.

If `<format>` is blank or `cgi` the response will be in JSON format.  So far
no other format is defined.

The API will receive an authentication user/password pair per session, either
as an HTTP authentication, or as GET or POST fields `user` and `password`, or
as a `user` and `upasswd` fields in a data-structure body.

The general structure of the response codes will be:

 * 200	Everything is okay, request is fulfilled in the response
 * 201	Request accepted and requested modifications committed
 * 202	Request accepted and requested modifications are not committed (yet).
 * 202	Destroy request accepted and fulfilled.
 * 304	No modification from data-lined request.
 * 401	authentication failed (for any reason)
 * 403	Request not accepted or cannot be committed (for any reason)
 * 404	Resource does not exist
  
The specifics for each kind of message are:

 1. **Keep-alive**
 
	A keep-alive is a GET request where no request verb is provided and
	authentication user is equal to requested resource.
	
	A successful keep-alive will return no data, and will either respond 200
	if sending IP address is equal to registered IP address or 201 if not (and
	thus the new address is recorded).
	
	An unsuccessful keep-alive will return 404 (if resource does not exist
	regardless of authentication) or 401 if authentication fails.

 2. **Request**
 
	A general request is a GET request where either a request verb is provided
	or the user is not equal to the requested resource.
	
	It is also a request when no resource is provided.
	
	Request verbs include
	 1.	list	(implicit when resource is void or a group)
	 2.	status	(implicit when no verb is provided)
	 3.	summary
	 4.	feed
		
	The resource might be either void, a group, or a base station.
	
	 1.	List request:
	 
		In a list request the data is a list containing pairs.  First pair
		field is the name of a resource, and the second pair field is a
		structure indicating full name, avatar code, and group of each
		resource.
	
		The list will provide only resources to which the authentication user
		has reading access to probably filtered by group or base station.
	
	 2.	Status request:
		For a status request the resource must exist and must be a final
		resource (as different from a group).
		
		The response data in a status request provides further information for
		the resource, including registered IP address.
		
	 3.	Summary request:
		For a summary request the resource must exist and must be a final
		resource (as different from a group).
		
		Along with basic information, the summary request will provide a
		summary of the last status.
		
		Optional GET fields might include a range for the summary.
	
	 4.	Feed request:
		For a feed request the resource must exist, and can be either a group
		or a final resource.
		
		Along with basic information, the feed request will provide a list of
		the last reported status.
		
		Optional GET fields might include a range for the feed or an specific
		instrument or meter.
	
	A successful general request will typically be answered by a 200 status,
	but might be answered by a 304 if a last-modified date is provided in the
	HTTP request and no status update is provided since.
	
	Unsuccessful general requests will be answered by 404 if resource is
	provided and does not exist or an unrecognised request verb is provided,
	regardless if authentication fails or not.
	
	A 401 response is given if authentication fails, and a 403 if the
	authenticating user has no reading access to the group or final resource.

 3. **Update**

	An update request is a POST request aimed to record new status updates to
	a resource.
	
	The authentication user should be either the base station or a user with
	writing privileges to that base station.
	
	Successful updates will be responded by 200 if no update needed to be
	committed, 201 if all updates are committed, and 202 if updates are
	scheduled to be committed.
	
	Unsuccessful updates will be answerd by 404 if resource is not found (or
	provided), 401 if authentication fails, and 403 if user has no writing
	privileges to the resource (or the update will not be committed for any
	other reason).

 4. **Greeting**

	A greeting is a PUT request in which the resource and the authenticating
	user are the same.  The body will be a JSON stream with the following
	fields:
	
	 a) name,	full UTF-8 name of the resource
	 b) group,	ASCII name of the group
	 c) modify,	set to boolean False
	
	**Response:**
	If the resource does not exist, a 404 response is provided regardless of
	authentication.
	
	If the resource exists but authentication fails, a 401 response is sent.

	If the resource exists, authentication is okay and name and group are
	equal to those recorded, the Greeting will be answered as a successful
	keep-alive.
	
	If the resource exists, authentication is okay, but the name or group are
	not equal to those recorded, the response will be similar to a successful
	keep-alive but an action field will be added to the response with the
	value "Modified" and a data field equal to a status request.

 5. **Modification**

	A modification is a PUT request in which the resource is existent, and the
	body is a JSON stream with the following compulsory fields:
	
	 1. name,	full UTF-8 name of the resource
	 2. group,	ASCII name of the group
	 3. modify,	set to boolean True
	
	And the following optional fields
	
	 4. user,	a username of a user with writing privileges to the resource
	 5. upasswd,	the password of that user
	 6. passwd,	a UTF-8 string with the resource password
	
	The answer should be:
	
	401 if authentication fails (if user/upasswd provided, this is the one to
	authenticate, otherwise the user/passwd "get" fields, or the HTTP
	authentication fields.
	
	403 if authentication is okay but either user has no writing privileges
	to the resource, or changes will not be committed by any other reason.
	
	201 success modification request, changes are committed.
	
	202 success modification request, changes are not (yet) committed (either
	because changes are not necesary or they are scheduled).
	
 6. **Creation**
 
	A creation is a PUT request in which the resource is not existent and the
	body is a JSON stream with the following compulsory fields:
	
	 1. name,	full UTF-8 name of the resource
	 2. group,	ASCII name of the group
	 3. passwd,	a UTF-8 string with the resource password.
	 
	and the following optional fields
	
	 4. user,	a username of a user with writing privileges to the resource
	 5. upasswd,	the password of that user
	 6. modify,	set to boolean True

	(Note that if HTTP or "get" authentication is provided for the same name
	 as the resource, the user/upasswd are compulsory.)
	
	The answer should be:
	401 if authentication fails (if user/upasswd provided, this is the one to
	authenticate, otherwise the user/passwd "get" fields, or the HTTP
	authentication fields.
	
	403 if authentication is okay but either user has no writing privileges
	to the group, or changes will not be committed by any other reason.

	201 success creation request, resource has been created.
	
	202 success creation request, resource has not been created yet.

 7. **Destruction**
 
	A destruction is a DELETE request.
	
	Responses are:
	
	404	if resource does not exist, regardless of authentication.
	
	401 authentication fails.
	
	403 resource cannot be destroyed, usually because the authentication user
	has no deleting privileges (a resource user has no deleting privileges
	on itself) on that resource.
	
	202 destruction accepted but will not be commited yet.
	
	204 destruction accepted and commited.
	
Incomplete or malformed requests:

    POST/PUT/DELETE <dataapi URI>	404
    POST/PUT/DELETE <dataapi URI>/	404
    POST/PUT/DELETE <dataapi URI>/<resource>/<anything else>	404
    GET/POST/DELETE <dataapi URI>/<non-existent-resource>	404
    GET/POST/DELETE <dataapi URI>/<non-existent-resource>/	404
    GET <dataapi URI>/<resource>/<non-existent-verb>	404
    GET <dataapi URI>/<resource>/<verb>/<anything else>	404

Authenticating data should be processed after request analysis. Failed
authentication is answered by 401.

If authenticating user has no enough privileges on resource: answer 403

Additional data (GET query, POST query, etc.) if not recognized should be
ignored.  If recognized but malformed should be answered by 403.

If the request is successful and a modification is committed to the database
answer 201 (or 204 if it was a DELETE request).

If the request is successful and a modification is requested to the database
but has not been committed yet, answer 202.  If it will not be committed by
conflict or by lock-down, answer 409.  If it will not be committed by any
other reason, answer 403.

Otherwise answer 200.

	[1] http://github.com/chlewey/datatransmit