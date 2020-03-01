# graf-backend

A backend for the FME graf. To use with a MySQL db backend (other SQL dialects might or might not work with minor changes in the code). It handles all editing operations and checks and validates most input parameters.
Always returns a JSON object with a status code indicating the result of the request, a helpful message in case of error and in some cases, other information. Internal server errors are logged to syslog.
