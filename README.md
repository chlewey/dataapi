Data API
========

Data API is an API server for keeping track of automated data,
it can be used by several resources including
[Data Transmit](/chlewey/datatransmit )

A DataAPI server is an HTTP server, which normally respond in JSON.  It will
originally be written in PHP but might be ported to another language in the
future.

All JSON responses from DataAPI will include the following fields:

 * `api`	the specific name of this DataAPI (ASCII string)
 * `version`	the version of the DataAPI (ASCII string with dot separated
			decimal numbers)
 * `status`	the HTTP status (integer), should be equal to the actual
			HTTP status.
 * `message`	an accompanying message (human readable UTF-8 string) explaining
			the status.
 * `action`	optional field, a data structure commanding to make any further
			action such as sending a particular update.
 * `data`		optional field, a data structure responding the request
  
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
where <VERB> is GET, POST, PUT or DELETE; <dataapi URI> is the resource
locator of this API, <resource> is the resource being asked about, <request>
is any further action that is requested, and <format> is the response format.

If <format> is blank or "cgi" the response will be in JSON format.  So far
no other format is defined.

The API will receive an authentication user/password pair per session, either
as an HTTP authentication, or as GET or POST fields "user" and "password", or
as a "user" and "upasswd" fields in a data-structure body.

The general structure of the response codes will be:
  200	Everything is okay, request is fullfilled in the response
  201	Request accepted and requested modifications commited
  202	Request accepted and requested modifications are not commited (yet).
  304	No modification from data-lined request.
  401	authentication failed (for any reasson)
  403	Request not accepted or cannot be committed (for any reason)
  404	Resource does not exist
  
The specifics for each kind of message are:

 1. Keep-alive
	A keep-alive is a GET request where no request verb is provided and
	authentication user is equal to requested resource.
	
	A successful keep-alive will return no data, and will either respond 200
	if sending IP address is equal to registered IP address or 201 if not (and
	thus the new address is recorded).
	
	An unsuccessful keep-alive will return 404 (if resource does not exist
	regardless of authentication) or 401 if authentication fails.

 2. Request
	A general request is a GET request where either a request verb is provided
	or the user is not equal to the requested resource.
	
	It is also a request when no resource is provided.
	
	Request verbs include
		list	(implicit when resource is void or a group)
		status	(implicit when no verb is provided)
		summary
		feed
		
	The resource might be either void, a group, or a base station.
	
	List request:
		In a list request the data is a list containing pairs.  First pair
		field is the name of a resource, and the second pair field is a
		structure indicating full name, avatar code, and group of each
		resource.
	
		The list will provide only resources to which the authentication user
		has reading access to probably filtered by group or base station.
	
	Status request:
		For a status request the resource must exist and must be a final
		resource (as different from a group).
		
		The response data in a status request provides further information for
		the resource, including registered IP address.
		
	Summary request:
		For a summary request the resource must exist and must be a final
		resource (as different from a group).
		
		Along with basic information, the summary request will provide a
		summary of the last status.
		
		Optional GET fields might include a range for the summary.
	
	Feed request:
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

 3. Update
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

 4. Greeting
	A greeting is a PUT request in which the resource and the authenticating
	user are the same.  The body will be a JSON stream with the following
	fields:
	 a) name,	full UTF-8 name of the resource
	 b) group,	ASCII name of the group
	 c) modify,	set to boolean False
	
	Response:
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

 5. Modification
	A modification is a PUT request in which the resource is existent, and the
	body is a JSON stream with the following compulsory fields:
	 a) name,	full UTF-8 name of the resource
	 b) group,	ASCII name of the group
	 c) modify,	set to boolean True
	And the following optional fields
	 d) user,	a username of a user with writing privileges to the resource
	 e) upasswd,	the password of that user
	 f) passwd,	a UTF-8 string with the resource password
	
	The answer should be:
	401 if authentication fails (if user/upasswd provided, this is the one to
	authenticate, otherwise the user/passwd "get" fields, or the HTTP
	authentication fields.
	
	403 if authentication is okay but either user has no writing privileges
	to the resource, or changes will not be committed by any other reason.
	
	201 success modification request, changes are committed.
	
	202 success modification request, changes are not (yet) committed (either
	because changes are not necesary or they are scheduled).
	
 6. Creation
	A creation is a PUT request in which the resource is not existent and the
	body is a JSON stream with the following compulsory fields:
	 a) name,	full UTF-8 name of the resource
	 b) group,	ASCII name of the group
	 c) passwd,	a UTF-8 string with the resource password.
	and the following optional fields
	 d) user,	a username of a user with writing privileges to the resource
	 e) upasswd,	the password of that user
	 f) modify,	set to boolean True

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

 7. Destruction
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