## Company Equipment

### About:
This is an unpolished school project that includes a web app and api to interact with a database intended to track company equipment.

--Data Not Included--

Neither is the nginx config, but I'll include the necessary lines for the api here.

### Nginx:

This project is hosted online and configured through nginx.
To have a functioning API which can take curl requests you must add this to your server configuration at 
`/etc/nginx/sites-enabled/default`

Add this redirect
```
location ~ ^/api/(.*)$ {
  proxy_pass https://$server_name/api/index.php?$1&$args;
}
```

### //TBD

Might polish this up a bit, web app needs some eyecandy and api needs a bit of a refactor.
